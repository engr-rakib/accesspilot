<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!defined('_CORE_ADMIN_')) {
    define('_CORE_ADMIN_', true);
}

require_once __DIR__ . '/../../Support/helpers.php';
require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Domain/Licensing/license_service.php';
require_once __DIR__ . '/../../../Domain/Audit/audit_service.php';
require_once __DIR__ . '/../../../Ldap/ldap_module.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!has_permission('page_system_config') && !has_permission('page_ad_administration')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden: Administrative access required.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$username = (string) ($_SESSION['username'] ?? 'unknown');

if (!function_exists('sync_active_domain_to_shared_config')) {
    function sync_active_domain_to_shared_config(string $domainKey): void {
        $cfg = app_config();
        $domain = ldap_get_domain($domainKey);
        $adName = $domain !== null ? ($domain['label'] ?? $domainKey) : $domainKey;
        // Read base_dn from the domain entry (domains.json) — not from app.php,
        // because app.php 'base_dn' is a legacy single-domain value that may be
        // stale/wrong after switching domains.
        $domainBaseDn = $domain !== null ? ($domain['base_dn'] ?? '') : '';
        $payload = [
            'default_password'       => $cfg['default_password'] ?? '',
            'app_name'               => $cfg['app_info']['name'] ?? '',
            'domain_name'            => $cfg['domain_name'] ?? '',
            'org_name'               => $cfg['org_name'] ?? '',
            'base_dn'                => $domainBaseDn ?: ($cfg['base_dn'] ?? ''),
            'active_domain'          => $domainKey,
            'active_domain_ad_name'  => $adName,
        ];
        // Write ONLY to vault — codebase shared_config.json is never written.
        if (function_exists('vault_shared_config_path')) {
            file_put_contents(vault_shared_config_path(), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }
}

if ($method === 'GET' && $action === 'list_domains') {
    $domains = ldap_get_domains();
    foreach ($domains as &$d) {
        $k = $d['key'] ?? '';
        $d['bind_password_stored'] = $k !== '' && ldap_read_domain_secret($k) !== '';
        if (!isset($d['exchange']) || !is_array($d['exchange'])) {
            $d['exchange'] = [];
        }
        $legacyExchangePassword = !empty($d['exchange']['ps_password']);
        $d['exchange']['ps_password_stored'] = ($k !== '' && ldap_has_exchange_password($k)) || $legacyExchangePassword;
        unset($d['exchange']['ps_password']);
    }
    unset($d);
    $activeKey = ldap_active_domain_key();
    $licStatus = function_exists('license_get_status') ? license_get_status() : [];

    echo json_encode([
        'success' => true,
        'domains' => $domains,
        'active_key' => $activeKey,
        'license' => [
            'max_domains' => (int) ($licStatus['max_domains'] ?? 1),
            'domains_used' => count($domains),
            'domains_remaining' => (int) ($licStatus['domains_remaining'] ?? 0),
        ],
    ]);
    exit;
}

if ($method === 'GET' && $action === 'test_domain') {
    $key = trim((string) ($_GET['key'] ?? ''));
    if ($key === '') {
        echo json_encode(['success' => false, 'message' => 'Domain key is required.']);
        exit;
    }

    $domain = ldap_get_domain($key);
    if ($domain === null) {
        echo json_encode(['success' => false, 'message' => 'Domain not found: ' . htmlspecialchars($key)]);
        exit;
    }

    $password = ldap_read_domain_secret($key);
    if ($password === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Bind password is not stored for domain: ' . htmlspecialchars($key),
            'latency_ms' => 0,
        ]);
        exit;
    }

    echo json_encode(ldap_test_connection($domain, $password));
    exit;
}

if ($method === 'GET' && $action === 'test_user') {
    $key = trim((string) ($_GET['key'] ?? ''));
    $testUser = trim((string) ($_GET['username'] ?? ''));

    if ($key === '') {
        echo json_encode(['success' => false, 'message' => 'Domain key is required.']);
        exit;
    }
    if ($testUser === '') {
        echo json_encode(['success' => false, 'message' => 'Username is required.']);
        exit;
    }

    $domain = ldap_get_domain($key);
    if ($domain === null) {
        echo json_encode(['success' => false, 'message' => 'Domain not found: ' . htmlspecialchars($key)]);
        exit;
    }

    $password = ldap_read_domain_secret($key);
    if ($password === '') {
        echo json_encode(['success' => false, 'message' => 'Bind password is not stored for domain: ' . htmlspecialchars($key)]);
        exit;
    }

    try {
        if (!ldap_extension_loaded()) {
            throw new RuntimeException('PHP ldap extension is not loaded.');
        }

        ldap_set_tls_never();

        $uri = ldap_build_uri($domain);
        $connection = @ldap_connect($uri);
        if ($connection === false) {
            throw new RuntimeException('ldap_connect failed for ' . $uri);
        }

        ldap_apply_connection_options($connection, $domain);
        ldap_start_tls_if_needed($connection, $domain);

        $bindDn = trim((string) ($domain['bind_dn'] ?? ''));
        if ($bindDn === '') {
            throw new RuntimeException('Bind DN is not configured for domain: ' . $key);
        }
        if (!@ldap_bind($connection, $bindDn, $password)) {
            $error = ldap_error($connection);
            @ldap_unbind($connection);
            throw new RuntimeException('LDAP bind failed: ' . $error);
        }

        $baseDn = ldap_search_base_dn($domain);
        if ($baseDn === '') {
            @ldap_unbind($connection);
            throw new RuntimeException('Base DN is not configured for domain: ' . $key);
        }

        $entry = ldap_user_lookup_entry($connection, $baseDn, $testUser);
        @ldap_unbind($connection);

        if ($entry === null) {
            echo json_encode([
                'success' => false,
                'message' => "User '{$testUser}' not found in {$key}.",
                'domain_key' => $key,
                'base_dn' => $baseDn,
            ]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => "User '{$testUser}' found in {$key}.",
            'domain_key' => $key,
            'base_dn' => $baseDn,
            'user' => ldap_adapt_get_ad_user_info($entry),
        ]);
        exit;
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'domain_key' => $key,
        ]);
        exit;
    }
}

if ($method === 'GET' && $action === 'get_upn_suffixes') {
    $connResult = ldap_connect_and_bind();
    if (!$connResult['connection']) {
        echo json_encode(['success' => false, 'message' => 'LDAP connection failed.', 'suffixes' => []]);
        exit;
    }
    $suffixes = ldap_get_upn_suffixes($connResult['connection'], $connResult['config']);
    echo json_encode(['success' => true, 'suffixes' => $suffixes, 'default' => $suffixes[0] ?? '']);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

switch ($action) {
    case 'switch_domain':
        $key = trim((string) ($data['key'] ?? ''));
        if ($key === '') {
            echo json_encode(['success' => false, 'message' => 'Domain key is required.']);
            exit;
        }
        if (ldap_get_domain($key) === null) {
            echo json_encode(['success' => false, 'message' => 'Domain not found: ' . htmlspecialchars($key)]);
            exit;
        }
        $targetDomain = ldap_get_domain($key);
        $currentBackend = strtolower((string) (ldap_read_config()['backend'] ?? ''));
        if (is_array($targetDomain)
            && strtolower((string) ($targetDomain['backend'] ?? '')) !== 'ldap'
            && $currentBackend === 'ldap') {
            $targetDomain['backend'] = 'ldap';
            ldap_upsert_domain($targetDomain);
        }
        if (!ldap_set_active_domain($key)) {
            echo json_encode(['success' => false, 'message' => 'Failed to switch active domain.']);
            exit;
        }
        sync_active_domain_to_shared_config($key);
        log_activity($username, 'domain_switch', 'success', 'Switched active domain to: ' . $key);
        echo json_encode(['success' => true, 'message' => 'Active domain switched to: ' . htmlspecialchars($key)]);
        exit;

    case 'add_domain':
        $key = trim((string) ($data['key'] ?? ''));
        if ($key === '') {
            echo json_encode(['success' => false, 'message' => 'Domain key is required.']);
            exit;
        }
        if (preg_match('/[^a-zA-Z0-9_.-]/', $key)) {
            echo json_encode(['success' => false, 'message' => 'Domain key must be alphanumeric, dash, underscore, or dot only.']);
            exit;
        }

        $requestedBackend = strtolower(trim((string) ($data['backend'] ?? (ldap_read_config()['backend'] ?? 'ldap'))));
        if (!in_array($requestedBackend, ['ldap', 'powershell', 'auto'], true)) {
            $requestedBackend = 'ldap';
        }

        $exchange = [
            'enabled' => !empty($data['exchange']['enabled']),
            'server_override' => trim((string) ($data['exchange']['server_override'] ?? '')),
            'ps_uri_override' => trim((string) ($data['exchange']['ps_uri_override'] ?? '')),
            'ps_use_https' => !empty($data['exchange']['ps_use_https']),
            'ps_username' => trim((string) ($data['exchange']['ps_username'] ?? '')),
        ];
        $exchangePassword = (string) ($data['exchange']['ps_password'] ?? '');

        $domain = [
            'key' => $key,
            'label' => trim((string) ($data['label'] ?? $key)),
            'host' => trim((string) ($data['host'] ?? '')),
            'ip' => trim((string) ($data['ip'] ?? '')),
            'port' => (int) ($data['port'] ?? 389),
            'use_tls' => !empty($data['use_tls']),
            'base_dn' => trim((string) ($data['base_dn'] ?? '')),
            'user_search_base' => trim((string) ($data['user_search_base'] ?? '')),
            'bind_dn' => trim((string) ($data['bind_dn'] ?? '')),
            'enabled' => true,
            'backend' => $requestedBackend,
            'naming' => isset($data['naming']) && is_array($data['naming']) ? $data['naming'] : [],
            'exchange' => $exchange,
        ];

        if ($domain['host'] === '' || $domain['base_dn'] === '') {
            echo json_encode(['success' => false, 'message' => 'Host and Base DN are required.']);
            exit;
        }

        $password = (string) ($data['bind_password'] ?? '');
        if ($password === '' && ldap_get_domain($key) === null) {
            echo json_encode(['success' => false, 'message' => 'Bind password is required for new domains.']);
            exit;
        }

        if (!ldap_upsert_domain($domain)) {
            echo json_encode(['success' => false, 'message' => ldap_domain_limit_message()]);
            exit;
        }

        if ($password !== '') {
            ldap_write_domain_secret($key, $password);
        }
        if ($exchangePassword !== '') {
            ldap_write_exchange_secret($key, $exchangePassword);
        }

        log_activity($username, 'domain_add', 'success', 'Added domain: ' . $key);
        echo json_encode(['success' => true, 'message' => 'Domain added: ' . htmlspecialchars($key)]);
        exit;

    case 'update_domain':
        $key = trim((string) ($data['key'] ?? ''));
        if ($key === '') {
            echo json_encode(['success' => false, 'message' => 'Domain key is required.']);
            exit;
        }

        $existing = ldap_get_domain($key);
        if ($existing === null) {
            echo json_encode(['success' => false, 'message' => 'Domain not found: ' . htmlspecialchars($key)]);
            exit;
        }

        $domain = $existing;
        if (isset($data['label'])) $domain['label'] = trim((string) $data['label']);
        if (isset($data['host'])) $domain['host'] = trim((string) $data['host']);
        if (isset($data['ip'])) $domain['ip'] = trim((string) $data['ip']);
        if (isset($data['port'])) $domain['port'] = (int) $data['port'];
        if (isset($data['use_tls'])) $domain['use_tls'] = !empty($data['use_tls']);
        if (isset($data['base_dn'])) $domain['base_dn'] = trim((string) $data['base_dn']);
        if (isset($data['user_search_base'])) $domain['user_search_base'] = trim((string) $data['user_search_base']);
        if (isset($data['bind_dn'])) $domain['bind_dn'] = trim((string) $data['bind_dn']);
        if (isset($data['enabled'])) $domain['enabled'] = !empty($data['enabled']);
        if (isset($data['backend'])) {
            $backend = strtolower(trim((string) $data['backend']));
            if (in_array($backend, ['ldap', 'powershell', 'auto'], true)) {
                $domain['backend'] = $backend;
            }
        }

        if (isset($data['naming']) && is_array($data['naming'])) {
            $domain['naming'] = $data['naming'];
        }

        if (isset($data['ou_config']) && is_array($data['ou_config'])) {
            $domain['ou_config'] = $data['ou_config'];
        }

        if (isset($data['group_config']) && is_array($data['group_config'])) {
            $domain['group_config'] = $data['group_config'];
        }

        if (isset($data['exchange']) && is_array($data['exchange'])) {
            $domain['exchange'] = $domain['exchange'] ?? [];
            if (isset($data['exchange']['enabled'])) $domain['exchange']['enabled'] = !empty($data['exchange']['enabled']);
            if (isset($data['exchange']['server_override'])) $domain['exchange']['server_override'] = trim((string) $data['exchange']['server_override']);
            if (isset($data['exchange']['ps_uri_override'])) $domain['exchange']['ps_uri_override'] = trim((string) $data['exchange']['ps_uri_override']);
            if (isset($data['exchange']['ps_use_https'])) $domain['exchange']['ps_use_https'] = !empty($data['exchange']['ps_use_https']);
            if (isset($data['exchange']['ps_username'])) $domain['exchange']['ps_username'] = trim((string) $data['exchange']['ps_username']);
            unset($domain['exchange']['ps_password']);
        }

        if (!ldap_upsert_domain($domain)) {
            echo json_encode(['success' => false, 'message' => 'Failed to update domain.']);
            exit;
        }

        $password = (string) ($data['bind_password'] ?? '');
        if ($password !== '') {
            ldap_write_domain_secret($key, $password);
        }
        $exchangePassword = (string) ($data['exchange']['ps_password'] ?? '');
        if ($exchangePassword !== '') {
            ldap_write_exchange_secret($key, $exchangePassword);
        }

        log_activity($username, 'domain_update', 'success', 'Updated domain: ' . $key);
        echo json_encode(['success' => true, 'message' => 'Domain updated: ' . htmlspecialchars($key)]);
        exit;

    case 'delete_domain':
        $key = trim((string) ($data['key'] ?? ''));
        if ($key === '') {
            echo json_encode(['success' => false, 'message' => 'Domain key is required.']);
            exit;
        }

        $activeKey = ldap_active_domain_key();
        if ($key === $activeKey) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete the active domain. Switch to another domain first.']);
            exit;
        }

        if (!ldap_delete_domain($key)) {
            echo json_encode(['success' => false, 'message' => 'Domain not found or could not be deleted.']);
            exit;
        }

        log_activity($username, 'domain_delete', 'success', 'Deleted domain: ' . $key);
        echo json_encode(['success' => true, 'message' => 'Domain deleted: ' . htmlspecialchars($key)]);
        exit;

    case 'test_connection':
        $host = trim((string) ($data['host'] ?? ''));
        $port = (int) ($data['port'] ?? 389);
        $useTls = !empty($data['use_tls']);
        $baseDn = trim((string) ($data['base_dn'] ?? ''));
        $bindDn = trim((string) ($data['bind_dn'] ?? ''));
        $bindPassword = (string) ($data['bind_password'] ?? '');

        if ($host === '') {
            echo json_encode(['success' => false, 'message' => 'Host is required.', 'status' => ['host' => 'missing', 'bind' => 'missing']]);
            exit;
        }

        $errors = [];
        $hostStatus = 'unknown';
        $bindStatus = 'unknown';
        $resolved = null;
        $start = microtime(true);

        $lookup = @gethostbynamel($host);
        if ($lookup !== false && !empty($lookup)) {
            $resolved = $lookup;
            $hostStatus = 'reachable';
        } elseif (filter_var($host, FILTER_VALIDATE_IP)) {
            $resolved = [$host];
            $hostStatus = 'reachable';
        } else {
            $errors[] = 'Host not found: ' . $host;
            $hostStatus = 'unreachable';
        }

        $latencyMs = 0;
        if (empty($errors) && $baseDn !== '' && $bindDn !== '' && $bindPassword !== '') {
            ldap_set_tls_never();
            $ds = @ldap_connect($host, $port);
            if ($ds) {
                if ($useTls) {
                    @ldap_start_tls($ds);
                }
                @ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
                @ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
                $latencyMs = round((microtime(true) - $start) * 1000);

                $bindOk = @ldap_bind($ds, $bindDn, $bindPassword);
                if ($bindOk) {
                    $bindStatus = 'success';
                } else {
                    $bindErr = ldap_error($ds);
                    $errors[] = 'Bind failed: ' . $bindErr;
                    $bindStatus = 'failed';
                }
                @ldap_unbind($ds);
            } else {
                $errors[] = 'Could not connect to ' . $host . ':' . $port;
                $hostStatus = 'unreachable';
                $bindStatus = 'failed';
            }
        } elseif (empty($errors)) {
            $bindStatus = 'skipped';
        }

        echo json_encode([
            'success' => empty($errors),
            'message' => empty($errors) ? ($bindStatus === 'skipped' ? 'Host reachable. Provide bind credentials for full test.' : 'Connection OK') : implode('; ', $errors),
            'status' => ['host' => $hostStatus, 'bind' => $bindStatus],
            'latency_ms' => $latencyMs,
            'resolved_ip' => $resolved !== null ? $resolved[0] : null,
        ]);
        exit;

    case 'resolve_host':
        $hostname = trim((string) ($data['host'] ?? ''));
        if ($hostname === '') {
            echo json_encode(['success' => false, 'message' => 'Hostname is required.']);
            exit;
        }
        $ips = gethostbynamel($hostname);
        if ($ips === false || empty($ips)) {
            echo json_encode(['success' => false, 'message' => 'Could not resolve: ' . $hostname]);
            exit;
        }
        echo json_encode(['success' => true, 'ip' => $ips[0], 'all' => $ips]);
        exit;

    case 'reverse_resolve':
        $ip = trim((string) ($data['ip'] ?? ''));
        if ($ip === '') {
            echo json_encode(['success' => false, 'message' => 'IP address is required.']);
            exit;
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            echo json_encode(['success' => false, 'message' => 'Invalid IP address format.']);
            exit;
        }
        $hostname = gethostbyaddr($ip);
        if ($hostname === false || $hostname === $ip) {
            echo json_encode(['success' => false, 'message' => 'Could not reverse resolve: ' . $ip]);
            exit;
        }
        echo json_encode(['success' => true, 'hostname' => $hostname]);
        exit;

    case 'save_health_admin_creds':
        $key = trim((string) ($data['key'] ?? ''));
        $adminUser = trim((string) ($data['username'] ?? ''));
        $adminPass = (string) ($data['password'] ?? '');
        if ($key === '' || $adminUser === '' || $adminPass === '') {
            echo json_encode(['success' => false, 'message' => 'Domain key, username and password are required.']);
            exit;
        }
        $usernameStored = ldap_write_health_admin_username($key, $adminUser);
        $passwordStored = ldap_write_health_admin_secret($key, $adminPass);
        if ($usernameStored && $passwordStored) {
            log_activity($_SESSION['username'] ?? 'unknown', 'health_creds_save', 'success', 'Saved health check admin credentials for domain: ' . $key);
            echo json_encode(['success' => true, 'message' => 'Health check admin credentials saved successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save health check admin credentials.']);
        }
        exit;

    case 'get_health_admin_status':
        $key = trim((string) ($data['key'] ?? ldap_active_domain_key()));
        $hasStored = ldap_has_health_admin_password($key);
        $username = $hasStored ? ldap_read_health_admin_username($key) : '';
        echo json_encode([
            'success' => true,
            'has_stored_creds' => $hasStored,
            'username' => $username
        ]);
        exit;

    case 'delete_health_admin_creds':
        $key = trim((string) ($data['key'] ?? ''));
        if ($key === '') {
            echo json_encode(['success' => false, 'message' => 'Domain key is required.']);
            exit;
        }
        $passwordPath = ldap_health_admin_secret_path($key);
        $usernamePath = ldap_health_admin_username_path($key);
        $deleted = true;
        if (file_exists($passwordPath)) $deleted = unlink($passwordPath) && $deleted;
        if (file_exists($usernamePath)) $deleted = unlink($usernamePath) && $deleted;
        echo json_encode(['success' => $deleted, 'message' => $deleted ? 'Health check admin credentials deleted.' : 'Failed to delete health check admin credentials.']);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
        exit;
}
