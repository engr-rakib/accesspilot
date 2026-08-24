<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!defined('_CORE_ADMIN_')) {
    define('_CORE_ADMIN_', true);
}

require_once __DIR__ . '/../../Support/helpers.php';
require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Domain/Licensing/license_service.php';
require_once __DIR__ . '/../../../Infrastructure/PowerShell/powershell_runner.php';
require_once __DIR__ . '/../../../Domain/Audit/audit_service.php';
require_once __DIR__ . '/../../../Domain/UserManagement/user_management_service.php';
require_once __DIR__ . '/../../../Ldap/ldap_module.php';
require_once __DIR__ . '/../../../Domain/HRMS/directory_info_service.php';

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

if ($method === 'GET' && $action === 'ldap_test_connect') {
    $override = null;
    $passwordOverride = null;

    if (!empty($_GET['ldap_host'])) {
        $override = [
            'host' => trim((string) $_GET['ldap_host']),
            'port' => (int) ($_GET['ldap_port'] ?? 389),
            'use_tls' => !empty($_GET['ldap_use_tls']),
            'base_dn' => trim((string) ($_GET['ldap_base_dn'] ?? '')),
            'bind_dn' => trim((string) ($_GET['ldap_bind_dn'] ?? '')),
            'connect_timeout' => 5,
        ];
        if (!empty($_GET['ldap_bind_password'])) {
            $passwordOverride = (string) $_GET['ldap_bind_password'];
        }
    }

    $testResult = ldap_run_test_and_persist($override, $passwordOverride);
    echo json_encode([
        'success' => !empty($testResult['success']),
        'message' => (string) ($testResult['message'] ?? ''),
        'ldap' => $testResult,
        'status' => ldap_status_for_api(),
    ]);
    exit;
}

if ($method === 'GET' && $action === 'ldap_test_user') {
    $testUser = trim((string) ($_GET['username'] ?? ''));
    if ($testUser === '') {
        echo json_encode(['success' => false, 'message' => 'Username is required for LDAP test lookup.']);
        exit;
    }

    $lookup = ldap_user_repository_find([
        'Username' => $testUser,
        'ExecutedBy' => $username,
    ], $username);

    $decoded = $lookup['decoded'] ?? json_decode((string) ($lookup['output'] ?? ''), true);
    echo json_encode([
        'success' => is_array($decoded) && !empty($decoded['success']),
        'message' => is_array($decoded) ? ($decoded['message'] ?? '') : 'Invalid LDAP response',
        'user' => is_array($decoded) ? ($decoded['user'] ?? null) : null,
        'backend' => ad_resolve_backend('get_user_info'),
    ]);
    exit;
}

if ($method === 'GET') {
    if (function_exists('license_clear_status_cache')) {
        license_clear_status_cache();
    }
    
    $secureMetadata = license_parse_secure_config_metadata();
    
    // Check Connectivity Statuses
    $secureBasePath = get_secure_base_path();
    $logBasePath = get_external_log_base();
    
    $secureStatus = is_dir($secureBasePath) && is_writable($secureBasePath);
    $logStatus = is_dir($logBasePath) && is_writable($logBasePath);

    // Auto-generate deployment_id — encrypted with org+domain when available
    // Use merged config (codebase + vault overrides) so deployment_id from vault
    // is never regenerated after a codebase upgrade.
    $appCfg = app_config();
    $needsRegen = empty($appCfg['deployment_id']);
    $hasOrgAndDomain = !empty($appCfg['org_name']) && !empty($appCfg['domain_name']);
    if ($needsRegen) {
        if ($hasOrgAndDomain) {
            $plaintext = $appCfg['org_name'] . '|' . $appCfg['domain_name'];
            $appCfg['deployment_id'] = encrypt_deployment_data($plaintext);
        } else {
            $appCfg['deployment_id'] = sprintf(
                '%s-%s-%s-%s-%s',
                bin2hex(random_bytes(4)),
                bin2hex(random_bytes(2)),
                bin2hex(random_bytes(2)),
                bin2hex(random_bytes(2)),
                bin2hex(random_bytes(6))
            );
        }
        $cfgContent = '<?php return ' . var_export($appCfg, true) . ';' . PHP_EOL;
        @opcache_invalidate(app_root('config/app.php'), true);
        file_put_contents(app_root('config/app.php'), $cfgContent);
        // Also persist deployment_id to vault so it survives codebase upgrade
        if (function_exists('write_vault_config')) {
            write_vault_config('app_overrides.php', ['deployment_id' => $appCfg['deployment_id']]);
        }
        $appCfg = include app_root('config/app.php');
    }

    // Auto-migrate: if CLIXML has Domain but app.php only has default, sync once
    if (!empty($secureMetadata['Domain']) && ($secureMetadata['Domain'] !== ($appCfg['domain_name'] ?? ''))) {
        sync_shared_config(['domain' => $secureMetadata['Domain']]);
    }

    $ldapPublic = ldap_public_config();
    $ready = (array) config_get('ldap_ready', []);
    $catalog = ldap_operation_catalog();
    $ldapOperations = [];
    foreach ($catalog as $op => $meta) {
        $ldapOperations[] = [
            'operation' => $op,
            'phase' => $meta['phase'] ?? null,
            'api_endpoint' => $meta['api_endpoint'] ?? null,
            'ldap_ready' => !empty($ready[$op]),
        ];
    }

    $responseCfg = app_config();

    echo json_encode([
        'success' => true,
        'config' => [
            'domain' => $responseCfg['domain_name'] ?? $secureMetadata['Domain'] ?? '',
            'base_dn' => $responseCfg['base_dn'] ?? $secureMetadata['BaseDN'] ?? '',
            'app_name' => $responseCfg['app_info']['name'] ?? $secureMetadata['AppName'] ?? '',
            'base_log_path' => $logBasePath,
            'default_password' => $responseCfg['default_password'] ?? $secureMetadata['DefaultPassword'] ?? '',
            'pwd_reset_use_random' => !empty($responseCfg['pwd_reset_use_random']),
            'org_name' => $responseCfg['org_name'] ?? '',
            'admin_username' => $secureMetadata['UserName'] ?? '',
            'has_password' => !empty($secureMetadata['HasPassword']),
            'secure_base_path' => $secureBasePath,
            'application_user_password' => $responseCfg['default_password'] ?? '',
            'deployment_id' => $responseCfg['deployment_id'] ?? '',
            'license_status' => (function(){
                if (function_exists('license_get_status')) {
                    $s = license_get_status();
                    return [
                        'status' => $s['status'] ?? 'unknown',
                        'is_restricted' => $s['is_restricted'] ?? true,
                        'message' => $s['message'] ?? '',
                        'domain_name' => $s['domain_name'] ?? '',
                        'product_name' => $s['product_name'] ?? '',
                        'issued_to' => $s['issued_to'] ?? '',
                        'license_id' => $s['license_id'] ?? '',
                        'deployment_id' => $s['deployment_id'] ?? '',
                        'runtime_deployment_id' => $s['runtime_deployment_id'] ?? '',
                        'expires_on' => $s['expires_on'] ?? null,
                        'days_remaining' => $s['days_remaining'] ?? null,
                    ];
                }
                return null;
            })(),
            'ldap_backend_mode' => $ldapPublic['backend'] ?? 'powershell',
            'ldap_enabled' => !empty($ldapPublic['enabled']),
            'ldap_has_bind_password' => !empty($ldapPublic['has_bind_password']),
        ],
        'status' => [
            'secure_vault' => [
                'path' => $secureBasePath,
                'connected' => $secureStatus,
                'message' => $secureStatus ? 'Connected & Writable' : 'Disconnected or Read-Only'
            ],
            'log_storage' => [
                'path' => $logBasePath,
                'connected' => $logStatus,
                'message' => $logStatus ? 'Connected & Writable' : 'Disconnected or Read-Only'
            ]
        ],
        'paths' => [
            'secure_config' => license_secure_config_path(),
            'license_state' => license_state_path(),
            'ldap_config' => ldap_config_file_path(),
        ],
        'ldap' => $ldapPublic,
        'ldap_status' => ldap_status_for_api(),
        'ldap_operations' => $ldapOperations,
        'domain_hint' => [
            'domain' => $secureMetadata['Domain'] ?? '',
            'base_dn' => $secureMetadata['BaseDN'] ?? '',
        ],
    ]);
    exit;
}

/**
 * Write shared non-sensitive config (default_password, domain, app_name)
 * to config/app.php and config/shared_config.json (for PowerShell consumption).
 */
function sync_shared_config(array $data): void {
    $configFile = app_root('config/app.php');
    $content = file_get_contents($configFile);
    if ($content === false) return;

    $updates = [];

    if (array_key_exists('default_password', $data)) {
        $v = $data['default_password'];
        $updates["'default_password'"] = $v;
    }
    if (array_key_exists('domain', $data)) {
        $v = $data['domain'];
        $updates["'domain_name'"] = $v;
    }
    if (array_key_exists('org_name', $data)) {
        $v = $data['org_name'];
        $updates["'org_name'"] = $v;
    }
    if (array_key_exists('base_dn', $data)) {
        $v = $data['base_dn'];
        $updates["'base_dn'"] = $v;
    }
    if (array_key_exists('active_domain', $data)) {
        $v = $data['active_domain'];
        $updates["'active_domain'"] = $v;
    }
    if (array_key_exists('pwd_reset_use_random', $data)) {
        $v = $data['pwd_reset_use_random'];
        // This is a boolean, stored as true/false (no quotes)
        $pattern = "/'pwd_reset_use_random'\s*=>\s*(true|false)/";
        $replacement = "'pwd_reset_use_random' => " . ($v ? 'true' : 'false');
        $content = preg_replace($pattern, $replacement, $content, 1);
        // Skip the generic string replacement below for this key
        unset($updates["'pwd_reset_use_random'"]);
    }
    // app_name is hardcoded as 'AccessPilot' — not user-configurable

    foreach ($updates as $key => $v) {
        $pattern = "/{$key}\s*=>\s*'[^']*'/";
        $replacement = "{$key} => '" . str_replace("'", "\\'", $v) . "'";
        $content = preg_replace($pattern, $replacement, $content, 1, $count);
    }

    // Regenerate deployment ID with encrypted org+domain when both are set
    $hasOrg = array_key_exists('org_name', $data);
    $hasDomain = array_key_exists('domain', $data);
    if ($hasOrg || $hasDomain) {
        $cfg = include $configFile;
        $finalOrg = $hasOrg ? $data['org_name'] : ($cfg['org_name'] ?? '');
        $finalDomain = $hasDomain ? $data['domain'] : ($cfg['domain_name'] ?? '');
        if (!empty($finalOrg) && !empty($finalDomain) && function_exists('encrypt_deployment_data')) {
            $newDeployId = encrypt_deployment_data($finalOrg . '|' . $finalDomain);
            $pattern = "/'deployment_id'\s*=>\s*'[^']*'/";
            $replacement = "'deployment_id' => '" . str_replace("'", "\\'", $newDeployId) . "'";
            $content = preg_replace($pattern, $replacement, $content, 1);
        }
    }

    file_put_contents($configFile, $content);

    // Write JSON mirror to vault for PowerShell (flush opcache so include sees fresh data)
    @opcache_invalidate($configFile, true);
    $cfg = include $configFile;
    $jsonPayload = [
        'default_password'       => $cfg['default_password'] ?? '',
        'app_name'               => $cfg['app_info']['name'] ?? '',
        'domain_name'            => $cfg['domain_name'] ?? '',
        'org_name'               => $cfg['org_name'] ?? '',
        'base_dn'                => $cfg['base_dn'] ?? '',
        'active_domain'          => $cfg['active_domain'] ?? (function_exists('ldap_active_domain_key') ? ldap_active_domain_key() : ''),
        'active_domain_ad_name'  => function_exists('ldap_active_domain_ad_name') ? ldap_active_domain_ad_name() : '',
    ];
    // Write ONLY to vault — codebase shared_config.json is never written.
    if (function_exists('vault_shared_config_path')) {
        $jsonContent = json_encode($jsonPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents(vault_shared_config_path(), $jsonContent);
    }

    // Also persist to secure vault (external storage) so config survives codebase upgrade.
    $vaultOverrides = [];
    if (isset($cfg['domain_name'])) $vaultOverrides['domain_name'] = $cfg['domain_name'];
    if (isset($cfg['org_name'])) $vaultOverrides['org_name'] = $cfg['org_name'];
    if (isset($cfg['base_dn'])) $vaultOverrides['base_dn'] = $cfg['base_dn'];
    if (isset($cfg['deployment_id'])) $vaultOverrides['deployment_id'] = $cfg['deployment_id'];
    if (isset($cfg['default_password'])) $vaultOverrides['default_password'] = $cfg['default_password'];
    if (isset($cfg['pwd_reset_use_random'])) $vaultOverrides['pwd_reset_use_random'] = $cfg['pwd_reset_use_random'];
    if (isset($cfg['active_domain'])) $vaultOverrides['active_domain'] = $cfg['active_domain'];
    if (!empty($vaultOverrides)) {
        write_vault_config('app_overrides.php', $vaultOverrides);
    }
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    // These actions bypass credential check (tests / org registration)
    if ($action === 'save_org') {
        sync_shared_config($data);
        echo json_encode(['success' => true, 'message' => 'Organization registered.']);
        exit;
    }

    if ($action === 'test_integration') {
        $testUrl = trim((string) ($data['test_url'] ?? ''));
        if ($testUrl === '') {
            echo json_encode(['success' => false, 'message' => 'Test URL is required.']);
            exit;
        }
        $startTime = microtime(true);
        if (strpos($testUrl, '?emp_id=') === false && strpos($testUrl, '?') === false) {
            $testUrl .= '00000';
        }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $testUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => 'AccessPilot-Portal/4.0',
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        $elapsed = (microtime(true) - $startTime) * 1000;
        if ($body === false || $httpCode >= 400) {
            // Fallback to PowerShell (Invoke-RestMethod uses Windows cert store)
            $encodedUrl = base64_encode($testUrl);
            $psCmd = "powershell -NoProfile -ExecutionPolicy Bypass -Command \"\$url = [System.Text.Encoding]::UTF8.GetString([System.Convert]::FromBase64String('$encodedUrl')); try { (Invoke-RestMethod -Uri \$url -Method Get -UseBasicParsing) | ConvertTo-Json -Compress -Depth 10 } catch { return \$null }\"";
            $psBody = shell_exec($psCmd);
            if ($psBody && ($psJson = json_decode($psBody, true)) && is_array($psJson) && !empty($psJson)) {
                $json = $psJson;
                $elapsed = (microtime(true) - $startTime) * 1000;
                echo json_encode([
                    'success' => true,
                    'message' => 'API reachable (' . count($json) . ' fields, via PowerShell fallback).',
                    'keys' => array_keys($json),
                    'response_time' => round($elapsed, 0),
                    'response_data' => $json,
                ]);
                exit;
            }
            echo json_encode(['success' => false, 'message' => 'Connection failed: ' . ($error ?: "HTTP $httpCode"), 'response_time' => round($elapsed, 0)]);
            exit;
        }
        $json = json_decode($body, true);
        if (!is_array($json) || empty($json)) {
            echo json_encode(['success' => false, 'message' => 'Response is not valid JSON or empty.', 'response_time' => round($elapsed, 0)]);
            exit;
        }
        echo json_encode([
            'success' => true,
            'message' => 'API reachable (' . count($json) . ' fields).',
            'keys' => array_keys($json),
            'response_time' => round($elapsed, 0),
            'response_data' => $json,
        ]);
        exit;
    }

    if ($action === 'test_emp_sts') {
        $status = trim((string) ($data['status'] ?? 'ACTIVE'));
        $testUrl = trim((string) ($data['test_url'] ?? ''));
        if (!function_exists('getHRMSEmployeesByStatus')) {
            echo json_encode(['success' => false, 'message' => 'HRMS module not available.', 'employees' => []]);
            exit;
        }
        $startTime = microtime(true);
        $result = getHRMSEmployeesByStatus($status, $testUrl ?: null);
        $elapsed = (microtime(true) - $startTime) * 1000;
        $result['response_time'] = round($elapsed, 0);
        if (!empty($result['employees'])) {
            $result['response_data'] = $result['employees'];
        }
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'save_ldap') {
        $saveResult = ldap_save_settings($data);
        if (!empty($saveResult['success'])) {
            log_activity($username, 'ldap_config_update', 'success', 'LDAP settings updated via System Configuration.');
        } else {
            log_activity($username, 'ldap_config_update', 'failure', (string) ($saveResult['message'] ?? 'Unknown error'));
        }
        echo json_encode($saveResult);
        exit;
    }

    if ($action === 'ldap_test_connect') {
        $override = null;
        $passwordOverride = null;
        if (!empty($data['ldap_host'])) {
            $override = [
                'host' => trim((string) ($data['ldap_host'] ?? '')),
                'port' => (int) ($data['ldap_port'] ?? 389),
                'use_tls' => !empty($data['ldap_use_tls']),
                'base_dn' => trim((string) ($data['ldap_base_dn'] ?? '')),
                'bind_dn' => trim((string) ($data['ldap_bind_dn'] ?? '')),
                'connect_timeout' => 5,
            ];
            if (!empty($data['ldap_bind_password'])) {
                $passwordOverride = (string) $data['ldap_bind_password'];
            }
        }
        $testResult = ldap_run_test_and_persist($override, $passwordOverride);
        echo json_encode([
            'success' => !empty($testResult['success']),
            'message' => (string) ($testResult['message'] ?? ''),
            'ldap' => $testResult,
            'status' => ldap_status_for_api(),
        ]);
        exit;
    }

    $confirmUserId = trim((string) ($data['confirm_user_id'] ?? ''));
    $confirmPassword = (string) ($data['confirm_password'] ?? '');
    if ($confirmUserId === '' || $confirmPassword === '') {
        echo json_encode(['success' => false, 'message' => 'User ID and Password are required for authorization.']);
        exit;
    }
    $users = readUsers();
    if (!isset($users[$confirmUserId]) || !password_verify($confirmPassword, $users[$confirmUserId]['password'])) {
        log_activity($username, 'credential_confirm_failure', 'failure', "Credential re-verification failed for user '$confirmUserId'.");
        echo json_encode(['success' => false, 'message' => 'Invalid credentials. Please check your User ID and Password and try again.']);
        exit;
    }

    if ($action === 'save_storage') {
        $oldLogPath = get_external_log_base();
        $oldSecurePath = get_secure_base_path();
        $baseLogPath = $data['base_log_path'] ?? $oldLogPath;
        $secureBasePath = $data['secure_base_path'] ?? $oldSecurePath;
        
        // Update Mapping Config only
        $mappingFile = app_root('config/storage.php');
        $appRoot = dirname(dirname(dirname(__DIR__)));
        $mappingContent = "<?php\n/**\n * Consolidated storage configuration.\n */\n\n\$appRoot = dirname(__DIR__);\n\nreturn [\n    'paths' => [\n        'app_root' => \$appRoot,\n        'config_root' => __DIR__,\n        'scripts_root' => \$appRoot . '/scripts',\n        'powershell_root' => \$appRoot . '/scripts/powershell',\n        'app_data_root' => \$appRoot . '/App_Data',\n    ],\n    'storage' => [\n";
        $mappingContent .= "        'secure_base_path' => '" . str_replace('\\', '/', $secureBasePath) . "',\n";
        $mappingContent .= "        'log_base_path' => '" . str_replace('\\', '/', $baseLogPath) . "',\n";
        $mappingContent .= "        'secure_xml_config' => '" . str_replace('\\', '/', $secureBasePath) . "/accesspilot_deployment_identity.xml',\n";
        $mappingContent .= "    ],\n    'fail_safe' => [\n        'enabled' => true,\n        'path' => \$appRoot . '/App_Data/internal_admin.json',\n    ]\n];\n";
        
        $success = (file_put_contents($mappingFile, $mappingContent) !== false);
        
        // Persist to secure vault (external storage)
        if (function_exists('write_vault_config')) {
            write_vault_config('app_storage.php', [
                'storage' => [
                    'secure_base_path' => str_replace('\\', '/', $secureBasePath),
                    'log_base_path' => str_replace('\\', '/', $baseLogPath),
                ],
            ]);
        }
        
        if ($success) {
            $changes = [];
            if ($oldSecurePath !== $secureBasePath) $changes[] = "Vault: '$oldSecurePath' → '$secureBasePath'";
            if ($oldLogPath !== $baseLogPath) $changes[] = "Logs: '$oldLogPath' → '$baseLogPath'";
            $detail = count($changes) ? implode('; ', $changes) : 'No path changes';
            log_activity($username, 'system_storage_update', 'success', $detail);
        }

        echo json_encode([
            'success' => $success,
            'message' => $success ? "Storage mapping updated successfully!" : "Failed to write storage configuration file."
        ]);
        exit;
    }

    if ($action === 'save_config') {
        $oldLogPath = get_external_log_base();
        $oldSecurePath = get_secure_base_path();
        $oldMeta = license_parse_secure_config_metadata();
        $baseLogPath = $data['base_log_path'] ?? $oldLogPath;
        $secureBasePath = $data['secure_base_path'] ?? $oldSecurePath;

        // 0. Save backend mode (LDAP / PowerShell) — must run before PS config sync
        if (isset($data['ldap_backend_mode'])) {
            ldap_save_settings(['ldap_backend_mode' => $data['ldap_backend_mode']]);
        }
        
        // 1. Update Mapping Config
        $mappingFile = app_root('config/storage.php');
        $appRoot = dirname(dirname(dirname(__DIR__)));
        $mappingContent = "<?php\n/**\n * Consolidated storage configuration.\n */\n\n\$appRoot = dirname(__DIR__);\n\nreturn [\n    'paths' => [\n        'app_root' => \$appRoot,\n        'config_root' => __DIR__,\n        'scripts_root' => \$appRoot . '/scripts',\n        'powershell_root' => \$appRoot . '/scripts/powershell',\n        'app_data_root' => \$appRoot . '/App_Data',\n    ],\n    'storage' => [\n";
        $mappingContent .= "        'secure_base_path' => '" . str_replace('\\', '/', $secureBasePath) . "',\n";
        $mappingContent .= "        'log_base_path' => '" . str_replace('\\', '/', $baseLogPath) . "',\n";
        $mappingContent .= "        'secure_xml_config' => '" . str_replace('\\', '/', $secureBasePath) . "/accesspilot_deployment_identity.xml',\n";
        $mappingContent .= "    ],\n    'fail_safe' => [\n        'enabled' => true,\n        'path' => \$appRoot . '/App_Data/internal_admin.json',\n    ]\n];\n";
        file_put_contents($mappingFile, $mappingContent);

        // 2. Update PowerShell Config (XML) — only include fields that were provided
        $params = [];
        if (!empty($data['domain']) || array_key_exists('domain', $data)) $params['Domain'] = $data['domain'] ?? '';
        if (!empty($data['base_dn']) || array_key_exists('base_dn', $data)) $params['BaseDN'] = $data['base_dn'] ?? '';
        if (!empty($data['admin_username']) || array_key_exists('admin_username', $data)) $params['AdminUser'] = $data['admin_username'] ?? '';
        if (!empty($data['admin_password']) || array_key_exists('admin_password', $data)) $params['AdminPass'] = $data['admin_password'] ?? '';
        if (!empty($data['app_name']) || array_key_exists('app_name', $data)) $params['AppName'] = $data['app_name'] ?? 'AccessPilot';
        if (!empty($data['default_password']) || array_key_exists('default_password', $data)) $params['DefaultPassword'] = $data['default_password'] ?? '';
        if (!empty($data['domain_user_password']) || array_key_exists('domain_user_password', $data)) $params['DomainUserPassword'] = $data['domain_user_password'] ?? '';
        $params['BaseLogPath'] = $baseLogPath;
        $params['SecureConfigPath'] = license_secure_config_path() ?: $secureBasePath . '/accesspilot_deployment_identity.xml';
        $params['LicenseStatePath'] = license_state_path();

        $scriptPath = app_root('scripts/powershell/Web-Deploy-Config.ps1');
        $psCommand = powershell_build_command('web_deploy_config', $params, [
            'script_path' => $scriptPath,
            'non_interactive' => true,
        ]);
        $saveResult = powershell_exec_command($psCommand, ['mode' => 'shell']);
        $outputLines = is_array($saveResult['output'] ?? null) ? $saveResult['output'] : [];
        $output = trim(implode(PHP_EOL, $outputLines));
        $success = !empty($saveResult['success']) && strpos($output, 'DEPLOY_OK') !== false;
        
        if ($success) {
            // Also sync to config/app.php + shared_config.json (vault-independent fallback)
            sync_shared_config($data);

            $changes = [];
            $fieldMap = ['Domain' => 'Domain', 'BaseDN' => 'BaseDN', 'AppName' => 'AppName', 'DefaultPassword' => 'DefaultPassword', 'UserName' => 'AdminUser'];
            foreach ($fieldMap as $metaKey => $dataKey) {
                $oldVal = $oldMeta[$metaKey] ?? '';
                $newVal = $params[$dataKey] ?? '';
                if ($oldVal !== $newVal) $changes[] = "$metaKey: '$oldVal' → '$newVal'";
            }
            if ($oldSecurePath !== $secureBasePath) $changes[] = "SecurePath: '$oldSecurePath' → '$secureBasePath'";
            if ($oldLogPath !== $baseLogPath) $changes[] = "LogPath: '$oldLogPath' → '$baseLogPath'";
            $detail = count($changes) ? implode('; ', $changes) : 'No field changes detected';
            log_activity($username, 'system_config_update', 'success', $detail);
        } else {
            $failureDetails = $output !== '' ? $output : ('PowerShell exited with code ' . (int) ($saveResult['exit_code'] ?? 1));
            log_activity($username, 'system_config_update', 'failure', $failureDetails);
        }
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? "Configuration synchronized successfully!" : "Update failed: " . ($output !== '' ? $output : 'Unknown PowerShell execution failure.')
        ]);
        exit;
    }

    if ($action === 'save_integrations') {
        $apiPaths = $data['api_paths'] ?? [];
        $hrmsUrl = $apiPaths['hrms_api_url'] ?? '';
        $imgUrl = $apiPaths['hrms_img_url'] ?? '';
        $stsUrl = $apiPaths['hrms_emp_sts_url'] ?? '';
        // Write ONLY to vault/api/ — codebase is never written.
        $success = write_vault_api_config('integrations.php', [
            'api_paths' => [
                'hrms_api_url' => $hrmsUrl,
                'hrms_img_url' => $imgUrl,
                'hrms_emp_sts_url' => $stsUrl,
            ],
        ]);
        if ($success) {
            log_activity($username, 'integrations_update', 'success', 'API integration URLs updated.');
        }
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'API URLs saved successfully.' : 'Failed to write integrations config.',
        ]);
        exit;
    }

    if ($action === 'save_passwords') {
        $appUserPassword = $data['application_user_password'] ?? '';

        if ($appUserPassword === '') {
            echo json_encode(['success' => false, 'message' => 'No application password value provided.']);
            exit;
        }

        $configFile = app_root('config/app.php');
        $configContent = file_get_contents($configFile);
        $changes = [];

        if (preg_match("/'default_password'\s*=>\s*'[^']*'/", $configContent)) {
            $oldApp = '';
            if (preg_match("/'default_password'\s*=>\s*'([^']*)'/", $configContent, $m)) {
                $oldApp = $m[1];
            }
            if ($oldApp !== $appUserPassword) {
                $configContent = preg_replace(
                    "/'default_password'\s*=>\s*'[^']*'/",
                    "'default_password' => '" . str_replace("'", "\\'", $appUserPassword) . "'",
                    $configContent
                );
                $changes[] = "App_Default: '$oldApp' → '$appUserPassword'";
            }
        }

        // Persist pwd_reset_use_random toggle
        $useRandom = !empty($data['pwd_reset_use_random']);
        if (preg_match("/'pwd_reset_use_random'\s*=>\s*(true|false)/", $configContent)) {
            $configContent = preg_replace(
                "/'pwd_reset_use_random'\s*=>\s*(true|false)/",
                "'pwd_reset_use_random' => " . ($useRandom ? 'true' : 'false'),
                $configContent
            );
        } else {
            $configContent = preg_replace(
                "/^<\?php return array \(/",
                "<?php return array (\n  'pwd_reset_use_random' => " . ($useRandom ? 'true' : 'false') . ",",
                $configContent
            );
        }

        $success = file_put_contents($configFile, $configContent) !== false;

        if ($success) {
            $useRandom = !empty($data['pwd_reset_use_random']);
            sync_shared_config([
                'default_password' => $appUserPassword,
                'pwd_reset_use_random' => $useRandom,
            ]);
            $detail = count($changes) ? implode('; ', $changes) : 'No password changes detected';
            log_activity($username, 'system_password_update', 'success', $detail);
        }

        echo json_encode([
            'success' => $success,
            'message' => $success ? "Passwords updated successfully!" : "Failed to write configuration file."
        ]);
        exit;
    }
}
