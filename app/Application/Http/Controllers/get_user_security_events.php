<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../Support/helpers.php';
require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Infrastructure/PowerShell/powershell_runner.php';
require_once __DIR__ . '/../../../Ldap/Config/ldap_config_repository.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.']);
    exit();
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

if (!has_permission('action_security_events')) {
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission.']);
    exit();
}

if (!defined('API_GATEWAY')) {
    die('Direct access not permitted');
}

set_time_limit(120);
ini_set('memory_limit', '256M');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$username = trim($_POST['username'] ?? '');
$eventIds = trim($_POST['event_ids'] ?? '');
$workstation = trim($_POST['workstation'] ?? '');
$daysBack = (int) ($_POST['days_back'] ?? 7);
$maxResults = (int) ($_POST['max_results'] ?? 200);
$dateFrom = trim($_POST['date_from'] ?? '');
$dateTo = trim($_POST['date_to'] ?? '');

if ($daysBack < 1) $daysBack = 1;
if ($daysBack > 90) $daysBack = 90;
if ($maxResults < 10) $maxResults = 10;
if ($maxResults > 500) $maxResults = 500;

session_write_close();

// Obtain Kerberos ticket from LDAP bind credentials
$config = ldap_read_config();
$targetDC = (string) ($config['host'] ?? '');
$bindDn = (string) ($config['bind_dn'] ?? '');
$bindPassword = ldap_read_bind_password();
if ($targetDC !== '' && $bindDn !== '' && $bindPassword !== '') {
    $userUpn = str_replace("'", "''", $bindDn);
    if (strpos($userUpn, '@') !== false) {
        $parts = explode('@', $userUpn);
        $parts[1] = strtoupper($parts[1]);
        $userUpn = implode('@', $parts);
    }
    $pwd = str_replace("'", "''", $bindPassword);
    $keytab = '/tmp/sec_krb5.keytab';
    $ktutilInput = "add_entry -password -p {$userUpn} -k 1 -e aes256-cts-hmac-sha1-96\n{$pwd}\nwrite_kt {$keytab}\nquit\n";
    $ktutilFile = tempnam(sys_get_temp_dir(), 'kt_') . '.txt';
    @file_put_contents($ktutilFile, $ktutilInput);
    exec('ktutil < ' . escapeshellarg($ktutilFile) . ' 2>/dev/null', $ktutilOut, $ktutilExit);
    @unlink($ktutilFile);
    if ($ktutilExit === 0) {
        exec('kinit -k -t ' . escapeshellarg($keytab) . ' ' . escapeshellarg($userUpn) . ' 2>/dev/null', $kinitOut, $kinitExit);
        @unlink($keytab);
    }
}

$psParams = [
    'EventIDs' => $eventIds,
    'Workstation' => $workstation,
    'DaysBack' => $daysBack,
    'DateFrom' => $dateFrom,
    'DateTo' => $dateTo,
    'MaxResults' => $maxResults,
    'TargetDC' => $targetDC,
];
if ($username !== '') {
    $psParams['Username'] = $username;
}

$result = powershell_run_json_script('UserSecurityEvents', $psParams, ['non_interactive' => true, 'mode' => 'exec']);

$success = false;
$message = '';
$events = [];
$queryTime = '0 sec';
$domainController = '';

if ($result['json_valid'] && isset($result['decoded'])) {
    $decoded = $result['decoded'];
    $success = !empty($decoded['success']);
    $message = $success ? 'Events fetched successfully.' : ($decoded['error'] ?? 'Failed to fetch events.');
    $events = $decoded['events'] ?? [];
    $queryTime = $decoded['queryTime'] ?? 'N/A';
    $domainController = $decoded['domainController'] ?? 'N/A';

    if (!$success && empty($message)) {
        $message = 'PowerShell script returned an error.';
    }
} else {
    $rawOutput = $result['output'] ?? '';
    $exitCode = $result['exit_code'] ?? -1;
    $message = "PowerShell script failed (exit: $exitCode).";
    if (!empty($rawOutput)) {
        $message = 'PowerShell output: ' . substr($rawOutput, 0, 800);
    }
    error_log("SecurityEvents: exit=$exitCode, output: " . substr($rawOutput, 0, 2000));
}

echo json_encode([
    'success' => $success,
    'message' => $message,
    'username' => $username,
    'data' => [
        'events' => $events,
        'total' => count($events),
        'queryTime' => $queryTime,
        'domainController' => $domainController,
        'workstations' => $decoded['workstations'] ?? [],
    ]
]);
