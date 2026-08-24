<?php

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../Support/helpers.php';
require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Domain/Dns/dns_resolver.php';
require_once __DIR__ . '/../../../Domain/Email/email_validator.php';
require_once __DIR__ . '/../../../Domain/Email/header_parser.php';
require_once __DIR__ . '/../../../Domain/Email/rbl_lookup.php';
require_once __DIR__ . '/../../../Domain/Email/mail_tools.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? ($_GET['action'] ?? '');

$emailPermissionMap = [
    'dns_lookup'     => 'action_email_dns_lookup',
    'header_parse'   => 'action_email_header_parse',
    'blacklist_check' => 'action_email_blacklist_check',
    'email_validate' => 'action_email_email_validate',
    'smtp_test'      => 'action_email_smtp_test',
    'bimi_check'     => 'action_email_bimi_check',
    'mta_sts_check'  => 'action_email_mta_sts_check',
    'port_scan'      => 'action_email_port_scan',
];

$requiredPermission = $emailPermissionMap[$action] ?? 'page_email_tools';
if (!has_permission('page_email_tools') || !has_permission($requiredPermission)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden: Email tools permission required.']);
    exit;
}

try {
    switch ($action) {
        case 'dns_lookup':
            handle_dns_lookup($input);
            break;
        case 'header_parse':
            handle_header_parse($input);
            break;
        case 'blacklist_check':
            handle_blacklist_check($input);
            break;
        case 'email_validate':
            handle_email_validate($input);
            break;
        case 'smtp_test':
            handle_smtp_test($input);
            break;
        case 'bimi_check':
            handle_bimi_check($input);
            break;
        case 'mta_sts_check':
            handle_mta_sts_check($input);
            break;
        case 'port_scan':
            handle_port_scan($input);
            break;
        default:
            echo json_encode(['success' => false, 'message' => "Unknown action: {$action}"]);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function handle_dns_lookup(array $input): void
{
    $domain = trim((string)($input['domain'] ?? ''));
    if ($domain === '') {
        echo json_encode(['success' => false, 'message' => 'Domain is required.']);
        return;
    }

    $start = microtime(true);
    $mx = dns_resolve_mx($domain);
    $spf = dns_check_spf($domain);
    $dmarc = dns_check_dmarc($domain);
    $dkimSelectors = ['default', 'google', 'selector1', 'selector2', 'dkim', 's1', 's2'];
    $dkim = [];
    foreach ($dkimSelectors as $sel) {
        $result = dns_check_dkim($domain, $sel);
        if (!empty($result)) {
            $dkim = array_merge($dkim, $result);
        }
    }

    // Resolve MX IPs
    $mxIps = [];
    foreach ($mx as $mxRecord) {
        $host = $mxRecord['host'];
        $mxIps[$host] = dns_resolve_a($host);
    }

    $duration = round((microtime(true) - $start) * 1000, 1);

    echo json_encode([
        'success' => true,
        'domain' => $domain,
        'mx' => $mx,
        'mx_count' => count($mx),
        'mx_ips' => $mxIps,
        'spf' => [
            'records' => $spf,
            'count' => count($spf),
            'parsed' => !empty($spf) ? dns_parse_spf($spf[0]) : null,
        ],
        'dkim' => [
            'records' => $dkim,
            'count' => count($dkim),
        ],
        'dmarc' => [
            'records' => $dmarc,
            'count' => count($dmarc),
        ],
        'bimi' => mt_check_bimi($domain),
        'mta_sts' => mt_check_mta_sts($domain),
        'duration' => $duration,
    ]);
}

function handle_header_parse(array $input): void
{
    $rawHeaders = $input['raw_headers'] ?? '';
    if (trim($rawHeaders) === '') {
        echo json_encode(['success' => false, 'message' => 'Raw headers are required.']);
        return;
    }

    $analysis = header_full_analysis($rawHeaders);
    $start = microtime(true);

    echo json_encode([
        'success' => true,
        'analysis' => $analysis,
        'raw_length' => strlen($rawHeaders),
        'duration' => round((microtime(true) - $start) * 1000, 1),
    ]);
}

function handle_blacklist_check(array $input): void
{
    $target = trim((string)($input['target'] ?? ''));
    if ($target === '') {
        echo json_encode(['success' => false, 'message' => 'IP address or domain is required.']);
        return;
    }

    // Resolve domain to IP if needed
    $ip = $target;
    if (!filter_var($target, FILTER_VALIDATE_IP)) {
        $resolved = @gethostbyname($target);
        if ($resolved !== $target && filter_var($resolved, FILTER_VALIDATE_IP)) {
            $ip = $resolved;
        } else {
            echo json_encode(['success' => false, 'message' => "Could not resolve '{$target}' to an IP address."]);
            return;
        }
    }

    $result = rbl_check_ip($ip);
    $result['original_target'] = $target;
    $result['success'] = true;

    echo json_encode($result);
}

function handle_email_validate(array $input): void
{
    $email = trim((string)($input['email'] ?? ''));
    if ($email === '') {
        echo json_encode(['success' => false, 'message' => 'Email address is required.']);
        return;
    }

    $start = microtime(true);
    $syntax = email_validate_syntax($email);
    $checks = [];
    $score = 0;

    // Check 1: Format
    if ($syntax['valid']) {
        $checks[] = ['check' => 'Format', 'passed' => true, 'message' => "Valid email format ({$syntax['local']} @ {$syntax['domain']})"];
        $score += 20;
    } else {
        $checks[] = ['check' => 'Format', 'passed' => false, 'message' => 'Invalid email format'];
        echo json_encode(['success' => true, 'email' => $email, 'valid' => false, 'score' => 0, 'checks' => $checks, 'duration' => round((microtime(true) - $start) * 1000, 1)]);
        return;
    }

    // Check 2: Domain MX
    $domainCheck = email_check_domain_mx($syntax['domain']);
    if ($domainCheck['has_mx']) {
        $checks[] = ['check' => 'MX Records', 'passed' => true, 'message' => "Domain has {$domainCheck['mx_count']} MX record(s)"];
        $score += 20;
    } else {
        $checks[] = ['check' => 'MX Records', 'passed' => false, 'message' => 'No MX records found for domain'];
    }

    // Check 3: Disposable
    if (email_is_disposable($syntax['domain'])) {
        $checks[] = ['check' => 'Disposable', 'passed' => false, 'message' => 'Disposable email domain detected'];
        $score -= 30;
    } else {
        $checks[] = ['check' => 'Disposable', 'passed' => true, 'message' => 'Not a disposable email domain'];
        $score += 10;
    }

    // Check 4: Role account
    if (email_is_role_account($syntax['local'])) {
        $checks[] = ['check' => 'Role Account', 'passed' => false, 'message' => 'Role-based email address (e.g. admin@, info@)'];
    } else {
        $checks[] = ['check' => 'Role Account', 'passed' => true, 'message' => 'Personal email address'];
        $score += 10;
    }

    // Check 5: SMTP verify (optional, may fail)
    $smtpResult = null;
    if ($domainCheck['has_mx']) {
        $smtpResult = email_smtp_verify($email, 5);
        if ($smtpResult['reachable']) {
            $checks[] = ['check' => 'SMTP Verify', 'passed' => true, 'message' => "Mailbox accepted by {$smtpResult['mx_host']} (" . round($smtpResult['latency'], 0) . "ms)"];
            $score += 40;
        } else {
            $checks[] = ['check' => 'SMTP Verify', 'passed' => false, 'message' => $smtpResult['error'] ?? 'Mailbox rejected by server'];
        }
    }

    $score = max(0, min(100, $score));

    echo json_encode([
        'success' => true,
        'email' => $email,
        'valid' => $score >= 50,
        'score' => $score,
        'checks' => $checks,
        'smtp_result' => $smtpResult,
        'duration' => round((microtime(true) - $start) * 1000, 1),
    ]);
}

function handle_smtp_test(array $input): void
{
    $host = trim((string)($input['host'] ?? ''));
    $port = (int)($input['port'] ?? 25);
    if ($host === '') {
        echo json_encode(['success' => false, 'message' => 'Host is required.']);
        return;
    }
    $result = mt_smtp_test($host, $port);
    $result['success'] = true;
    echo json_encode($result);
}

function handle_bimi_check(array $input): void
{
    $domain = trim((string)($input['domain'] ?? ''));
    if ($domain === '') {
        echo json_encode(['success' => false, 'message' => 'Domain is required.']);
        return;
    }
    $result = mt_check_bimi($domain);
    $result['success'] = true;
    echo json_encode($result);
}

function handle_mta_sts_check(array $input): void
{
    $domain = trim((string)($input['domain'] ?? ''));
    if ($domain === '') {
        echo json_encode(['success' => false, 'message' => 'Domain is required.']);
        return;
    }
    $result = mt_check_mta_sts($domain);
    $result['success'] = true;
    echo json_encode($result);
}

function handle_port_scan(array $input): void
{
    $host = trim((string)($input['host'] ?? ''));
    if ($host === '') {
        echo json_encode(['success' => false, 'message' => 'Host is required.']);
        return;
    }
    $results = mt_port_scan($host);
    echo json_encode(['success' => true, 'host' => $host, 'results' => $results]);
}
