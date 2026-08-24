<?php
/**
 * Configuration health guide — LDAP + PowerShell + license + storage.
 */

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!defined('_CORE_ADMIN_')) {
    define('_CORE_ADMIN_', true);
}

require_once __DIR__ . '/../../Support/helpers.php';
require_once __DIR__ . '/../../Support/diagnostics_guide.php';
require_once __DIR__ . '/../../../Domain/Licensing/license_service.php';
require_once __DIR__ . '/../../../Infrastructure/PowerShell/powershell_runner.php';
require_once __DIR__ . '/../../../Ldap/ldap_module.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$liveRefresh = !empty($_GET['refresh']) && $_GET['refresh'] !== '0';

$ldapConfig = ldap_read_config();
$ldapPublic = ldap_public_config();
$backend = strtolower((string) ($ldapConfig['backend'] ?? 'powershell'));
$ldapEnabled = !empty($ldapConfig['enabled']);
$domain = get_domain_name();
$securePath = license_secure_config_path();
$secureMeta = function_exists('license_parse_secure_config_metadata')
    ? license_parse_secure_config_metadata()
    : [];

$appCfg = app_config();
$orgRegistered = trim((string) ($appCfg['org_name'] ?? '')) !== '';

$licenseStatus = function_exists('license_get_status') ? license_get_status() : [];
$licenseRestricted = !empty($licenseStatus['is_restricted']);

$secureBasePath = get_secure_base_path();
$logBasePath = get_external_log_base();
$secureStatus = is_dir($secureBasePath) && is_writable($secureBasePath);
$logStatus = is_dir($logBasePath) && is_writable($logBasePath);

$credentials = [
    'active_backend' => $backend,
    'ldap_enabled' => $ldapEnabled,
    'ldap_bind_dn_set' => trim((string) ($ldapPublic['bind_dn'] ?? '')) !== '',
    'ldap_bind_password_stored' => ldap_has_bind_password(),
    'ldap_host_set' => trim((string) ($ldapPublic['host'] ?? '')) !== '',
    'ps_username' => (string) ($secureMeta['UserName'] ?? ''),
    'ps_username_set' => trim((string) ($secureMeta['UserName'] ?? '')) !== '',
    'ps_password_stored' => !empty($secureMeta['HasPassword']),
    'domain_configured' => trim($domain) !== '',
];

$response = [
    'success' => true,
    'active_backend' => $backend,
    'overall_health' => 'healthy',
    'credentials' => $credentials,
    'ldap_extension' => [
        'loaded' => ldap_extension_loaded(),
        'message' => ldap_extension_loaded() ? 'OK' : 'MISSING — enable extension=ldap in php.ini',
    ],
    'ldap' => [
        'success' => false,
        'message' => $ldapEnabled ? 'Not tested yet' : 'LDAP module disabled',
        'reachable' => null,
        'latency_ms' => 0,
        'source' => 'none',
    ],
    'powershell' => [
        'success' => false,
        'message' => 'Not tested',
        'ping' => ['success' => false, 'message' => 'Not tested'],
        'auth' => ['success' => false, 'message' => 'Not tested'],
        'skipped' => false,
    ],
    'ping' => ['success' => false, 'message' => 'Not tested'],
    'auth' => ['success' => false, 'message' => 'Not tested'],
    'storage' => [
        'secure_vault' => ['connected' => $secureStatus, 'message' => $secureStatus ? 'Connected & Writable' : 'Disconnected or Read-Only'],
        'log_storage' => ['connected' => $logStatus, 'message' => $logStatus ? 'Connected & Writable' : 'Disconnected or Read-Only'],
    ],
    'license' => [
        'is_restricted' => $licenseRestricted,
        'is_active' => !$licenseRestricted && $orgRegistered,
        'message' => (string) ($licenseStatus['message'] ?? ''),
    ],
    'issues' => [],
];

// --- LDAP ---
$ldapHost = trim((string) ($ldapPublic['host'] ?? ''));

if ($ldapEnabled) {
    $lastTest = ldap_read_last_test();
    $useLive = $liveRefresh
        || empty($lastTest['at'])
        || (time() - strtotime((string) $lastTest['at']) > 300);

    $ldapResult = null;
    if ($useLive && $credentials['ldap_bind_password_stored'] && $credentials['ldap_bind_dn_set'] && $credentials['ldap_host_set']) {
        $ldapResult = ldap_test_connection();
        $fsReachable = null;
        if ($ldapHost !== '') {
            $fp = @fsockopen($ldapHost, (int)($ldapPublic['port'] ?? 389), $e, $s, 3);
            $fsReachable = is_resource($fp);
            if ($fsReachable) fclose($fp);
        }
        ldap_write_last_test([
            'success' => !empty($ldapResult['success']),
            'message' => (string) ($ldapResult['message'] ?? ''),
            'latency_ms' => (int) ($ldapResult['latency_ms'] ?? 0),
            'server_naming_context' => (string) ($ldapResult['server_naming_context'] ?? ''),
            'reachable' => $fsReachable ?? !empty($ldapResult['success']),
        ]);
        $response['ldap']['source'] = 'live';
    } elseif (!empty($lastTest['at'])) {
        $ldapResult = [
            'success' => !empty($lastTest['success']),
            'message' => (string) ($lastTest['message'] ?? ''),
            'latency_ms' => (int) ($lastTest['latency_ms'] ?? 0),
        ];
        $response['ldap']['source'] = 'cached';
    }

    if ($ldapResult !== null) {
        $response['ldap']['success'] = !empty($ldapResult['success']);
        $response['ldap']['message'] = diagnostics_humanize_message((string) ($ldapResult['message'] ?? ''), 'ldap');
        $response['ldap']['latency_ms'] = (int) ($ldapResult['latency_ms'] ?? 0);

        // Use cached reachable from last test when available
        $response['ldap']['reachable'] = !empty($lastTest['reachable']) || !empty($ldapResult['success']);
        if (isset($lastTest['reachable'])) {
            $response['ldap']['reachable'] = !empty($lastTest['reachable']);
        }
        if (!empty($ldapResult['success'])) {
            $response['ldap']['reachable'] = true;
        }
    }

    // Only run fsockopen on live refresh to avoid blocking page load
    if ($ldapHost !== '' && $useLive) {
        $port = (int) ($ldapPublic['port'] ?? 389);
        $reachable = @fsockopen($ldapHost, $port, $errno, $errstr, 3);
        if (is_resource($reachable)) {
            fclose($reachable);
            $response['ldap']['reachable'] = true;
        } elseif (empty($response['ldap']['success'])) {
            $response['ldap']['reachable'] = false;
            $response['ldap']['reach_message'] = $errstr ?: 'Host unreachable';
        }
    } elseif ($ldapHost !== '' && !isset($response['ldap']['reachable'])) {
        $response['ldap']['reachable'] = null;
    }
}

// --- PowerShell (skip when LDAP-only and no PS password) ---
$shouldRunPs = $credentials['domain_configured']
    && $credentials['ps_password_stored']
    && ($backend === 'powershell' || $backend === 'auto' || ($backend === 'ldap' && $liveRefresh));

if (!$credentials['ps_password_stored']) {
    $response['powershell']['skipped'] = true;
    $response['powershell']['message'] = $backend === 'ldap'
        ? 'Not required for LDAP-only mode'
        : 'Admin password not stored in vault';
    $response['powershell']['success'] = true;
    $response['powershell']['ping']['success'] = true;
    $response['powershell']['ping']['message'] = 'Skipped — LDAP is active backend';
    $response['powershell']['auth']['success'] = true;
    $response['powershell']['auth']['message'] = 'Skipped — LDAP is active backend';
} elseif ($shouldRunPs) {
    try {
        $psResult = powershell_run_json_script('test_ad_connection', [
            'Domain' => $domain,
            'SecureConfigPath' => $securePath,
            'UseStoredCredentials' => true,
        ], [
            'script_path' => app_root('scripts/powershell/Test-AD-Connection.ps1'),
        ]);

        if (!empty($psResult['json_valid']) && is_array($psResult['decoded'])) {
            $decoded = $psResult['decoded'];
            $ping = $decoded['ping'] ?? ['success' => false, 'message' => 'No ping data'];
            $auth = $decoded['auth'] ?? ['success' => false, 'message' => 'No auth data'];
            $ping['message'] = diagnostics_humanize_message((string) ($ping['message'] ?? ''), 'powershell');
            $auth['message'] = diagnostics_humanize_message((string) ($auth['message'] ?? ''), 'powershell');
            $response['powershell'] = [
                'success' => !empty($ping['success']) || !empty($auth['success']),
                'message' => 'PowerShell diagnostics completed',
                'ping' => $ping,
                'auth' => $auth,
                'skipped' => false,
            ];
        } else {
            $raw = is_array($psResult['output'] ?? null) ? implode(' ', $psResult['output']) : (string) ($psResult['output'] ?? '');
            $response['powershell']['message'] = diagnostics_humanize_message($raw, 'powershell');
            $response['powershell']['auth']['message'] = $response['powershell']['message'];
        }
    } catch (Throwable $e) {
        $response['powershell']['message'] = diagnostics_humanize_message($e->getMessage(), 'powershell');
        $response['powershell']['auth']['message'] = $response['powershell']['message'];
    }
} else {
    $response['powershell']['skipped'] = true;
    $response['powershell']['success'] = true;
    $response['powershell']['message'] = 'Skipped — LDAP is primary backend';
    $response['powershell']['ping']['success'] = true;
    $response['powershell']['ping']['message'] = 'Skipped — LDAP is primary backend';
    $response['powershell']['auth']['success'] = true;
    $response['powershell']['auth']['message'] = 'Skipped — LDAP is primary backend';
}

// Primary strip reflects active backend
if ($ldapEnabled && in_array($backend, ['ldap', 'auto'], true)) {
    $response['ping'] = [
        'success' => ($response['ldap']['reachable'] ?? false) || $response['ldap']['success'],
        'message' => ($response['ldap']['reachable'] ?? null) === false
            ? diagnostics_humanize_message((string) ($response['ldap']['reach_message'] ?? 'Unreachable'), 'ldap')
            : ($response['ldap']['success'] ? 'LDAP host OK' : diagnostics_humanize_message((string) $response['ldap']['message'], 'ldap')),
    ];
    $response['auth'] = [
        'success' => $response['ldap']['success'],
        'message' => $response['ldap']['success']
            ? 'Bind OK' . ($response['ldap']['latency_ms'] ? ' · ' . $response['ldap']['latency_ms'] . 'ms' : '')
            : diagnostics_humanize_message((string) $response['ldap']['message'], 'ldap'),
    ];
} else {
    $response['ping'] = $response['powershell']['ping'];
    $response['auth'] = $response['powershell']['auth'];
}

// --- Per-domain tests ---
$response['domains'] = [];
$allDomains = function_exists('ldap_get_domains') ? ldap_get_domains() : [];
$activeKey = function_exists('ldap_active_domain_key') ? ldap_active_domain_key() : '';
$domainsCachePath = function_exists('ldap_config_dir') ? ldap_config_dir() . DIRECTORY_SEPARATOR . 'domains_cache.json' : '';
$domainsCacheTtl = 300;
$domainsCache = [];
if ($domainsCachePath !== '' && file_exists($domainsCachePath) && is_readable($domainsCachePath)) {
    $raw = (string) file_get_contents($domainsCachePath);
    $decoded = json_decode($raw, true);
    if (is_array($decoded) && isset($decoded['at'])) {
        $age = time() - strtotime((string) $decoded['at']);
        if ($age < $domainsCacheTtl) {
            $domainsCache = $decoded['domains'] ?? [];
        }
    }
}

foreach ($allDomains as $dom) {
    $key = (string) ($dom['key'] ?? '');
    if ($key === '') continue;

    $entry = [
        'key' => $key,
        'label' => (string) ($dom['label'] ?? $key),
        'host' => (string) ($dom['host'] ?? ''),
        'is_active' => ($key === $activeKey),
        'bind_dn' => (string) ($dom['bind_dn'] ?? ''),
        'has_password' => function_exists('ldap_read_domain_secret') && ldap_read_domain_secret($key) !== '',
        'reachable' => null,
        'bind_success' => null,
        'latency_ms' => 0,
        'service_user' => null,
    ];

    $cached = $domainsCache[$key] ?? null;

    // Use cached result if available and not a forced live refresh
    if (!$liveRefresh && $cached !== null) {
        $entry['reachable'] = $cached['reachable'] ?? null;
        $entry['bind_success'] = $cached['bind_success'] ?? null;
        $entry['latency_ms'] = (int) ($cached['latency_ms'] ?? 0);
        $entry['message'] = (string) ($cached['message'] ?? '');
        $entry['service_user'] = $cached['service_user'] ?? null;
    } elseif ($ldapEnabled && $entry['has_password']) {
        $password = ldap_read_domain_secret($key);
        $test = ldap_test_connection($dom, $password);
        $entry['bind_success'] = !empty($test['success']);
        $msg = (string) ($test['message'] ?? '');
        $entry['message'] = $msg;
        // Host is reachable if connect succeeded (bind failed due to creds/server) or overall success
        $entry['reachable'] = !empty($test['success'])
            || stripos($msg, 'Invalid credentials') !== false
            || (stripos($msg, 'LDAP bind failed') !== false && stripos($msg, 'ldap_connect failed') === false);
        $entry['latency_ms'] = (int) ($test['latency_ms'] ?? 0);

        // Service user lookup — only on live refresh (expensive)
        if ($liveRefresh && !empty($test['success']) && ($dom['bind_dn'] ?? '') !== '' && ($dom['base_dn'] ?? '') !== '') {
            try {
                ldap_set_tls_never();
                $uri = ldap_build_uri($dom);
                $conn = @ldap_connect($uri);
                if ($conn !== false) {
                    ldap_apply_connection_options($conn, $dom);
                    ldap_start_tls_if_needed($conn, $dom);
                    if (@ldap_bind($conn, $dom['bind_dn'], $password)) {
                        $lookup = ldap_user_lookup_entry($conn, $dom['base_dn'], $dom['bind_dn']);
                        if ($lookup !== null) {
                            $entry['service_user'] = ldap_adapt_get_ad_user_info($lookup);
                        }
                    }
                    @ldap_unbind($conn);
                }
            } catch (Throwable $e) {
                $entry['service_user'] = ['error' => $e->getMessage()];
            }
        }

        // Update cache entry for this domain
        $domainsCache[$key] = [
            'reachable' => $entry['reachable'],
            'bind_success' => $entry['bind_success'],
            'latency_ms' => $entry['latency_ms'],
            'message' => $entry['message'],
            'service_user' => $entry['service_user'],
        ];
    }

    $response['domains'][] = $entry;
}

// Write domains cache to disk
if ($domainsCachePath !== '' && !empty($domainsCache)) {
    $dir = dirname($domainsCachePath);
    if (!is_dir($dir)) @mkdir($dir, 0770, true);
    file_put_contents($domainsCachePath, json_encode([
        'at' => gmdate('c'),
        'domains' => $domainsCache,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

$response['issues'] = diagnostics_build_issues([
    'backend' => $backend,
    'credentials' => $credentials,
    'ldap' => $response['ldap'],
    'powershell' => $response['powershell'],
    'license' => $response['license'],
    'storage' => $response['storage'],
    'org_registered' => $orgRegistered,
]);

$response['overall_health'] = diagnostics_overall_health($response['issues']);

echo json_encode($response);
