<?php

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!defined('_CORE_ADMIN_')) {
    define('_CORE_ADMIN_', true);
}

require_once __DIR__ . '/../../Support/helpers.php';
require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Domain/Audit/audit_service.php';
require_once __DIR__ . '/../../../Ldap/ldap_module.php';

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

$exchangePermissionMap = [
    'discover' => 'action_exchange_settings',
    'connection_test' => 'action_exchange_settings',
    'exchange_diagnostic_test' => 'action_exchange_settings',
    'settings_save' => 'action_exchange_settings',
    'mailbox_list' => 'action_exchange_mailbox_view',
    'mailbox_search' => 'action_exchange_mailbox_view',
    'mailbox_stats' => 'action_exchange_mailbox_view',
    'mailbox_get_archive' => 'action_exchange_mailbox_view',
    'mailbox_enable' => 'action_exchange_mailbox_enable',
    'mailbox_user_create' => 'action_exchange_mailbox_enable',
    'mailbox_disable' => 'action_exchange_mailbox_disable',
    'mailbox_set_quota' => 'action_exchange_mailbox_quota',
    'mailbox_set_forward' => 'action_exchange_mailbox_forward',
    'mailbox_set_primary_smtp' => 'action_exchange_mailbox_address',
    'mailbox_add_address' => 'action_exchange_mailbox_address',
    'mailbox_remove_address' => 'action_exchange_mailbox_address',
    'mailbox_add_full_access' => 'action_exchange_mailbox_address',
    'mailbox_remove_full_access' => 'action_exchange_mailbox_address',
    'mailbox_add_send_as' => 'action_exchange_mailbox_address',
    'mailbox_remove_send_as' => 'action_exchange_mailbox_address',
    'mailbox_set_litigation_hold' => 'action_exchange_mailbox_quota',
    'mailbox_set_hidden_gal' => 'action_exchange_mailbox_address',
    'mailbox_update_profile' => 'action_exchange_mailbox_address',
    'mailbox_set_oof' => 'action_exchange_mailbox_address',
    'mailbox_move_request' => 'action_exchange_mailbox_quota',
    'mailbox_create_shared' => 'action_exchange_mailbox_enable',
    'mailbox_create_room' => 'action_exchange_mailbox_enable',
    'mailbox_create_equipment' => 'action_exchange_mailbox_enable',
    'mailbox_enable_archive' => 'action_exchange_mailbox_enable',
    'mailbox_disable_archive' => 'action_exchange_mailbox_disable',
    'mailbox_set_mail_tip' => 'action_exchange_mailbox_address',
    'mailbox_set_calendar_permissions' => 'action_exchange_mailbox_address',
    'mailbox_remove_calendar_permissions' => 'action_exchange_mailbox_address',
    'mailbox_restore_request' => 'action_exchange_mailbox_enable',
    'group_search' => 'action_exchange_group_view',
    'group_members' => 'action_exchange_group_view',
    'group_create' => 'action_exchange_group_create',
    'group_add_member' => 'action_exchange_group_modify',
    'group_remove_member' => 'action_exchange_group_modify',
    'group_delete' => 'action_exchange_group_delete',
    'monitoring_databases' => 'action_exchange_monitoring',
    'monitoring_quota' => 'action_exchange_monitoring',
    'monitoring_queues' => 'action_exchange_monitoring',
    'monitoring_message_tracking' => 'action_exchange_monitoring',
    'monitoring_transport_rules' => 'action_exchange_monitoring',
    'monitoring_retention_policies' => 'action_exchange_monitoring',
];

$requiredPermission = $exchangePermissionMap[$action] ?? 'page_exchange';
if (!has_permission('page_exchange') || !has_permission($requiredPermission)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden: Exchange permission required.']);
    exit;
}

ob_start();
try {
    switch ($action) {
        case 'discover':
            handle_discover();
            break;
        case 'mailbox_list':
            handle_mailbox_list($input);
            break;
        case 'mailbox_search':
            handle_mailbox_search($input);
            break;
        case 'mailbox_stats':
            handle_mailbox_stats($input);
            break;
        case 'mailbox_enable':
            handle_mailbox_enable($input);
            break;
        case 'mailbox_disable':
            handle_mailbox_disable($input);
            break;
        case 'mailbox_user_create':
            handle_mailbox_user_create($input);
            break;
        case 'group_search':
            handle_group_search($input);
            break;
        case 'group_members':
            handle_group_members($input);
            break;
        case 'monitoring_databases':
            handle_monitoring_databases();
            break;
        case 'monitoring_quota':
            handle_monitoring_quota();
            break;
        case 'connection_test':
            handle_discover();
            break;
        case 'exchange_diagnostic_test':
            handle_exchange_diagnostic_test($input);
            break;
        case 'mailbox_set_quota':
            handle_mailbox_set_quota($input);
            break;
        case 'mailbox_set_forward':
            handle_mailbox_set_forward($input);
            break;
        case 'mailbox_set_primary_smtp':
            handle_mailbox_set_primary_smtp($input);
            break;
        case 'mailbox_add_address':
            handle_mailbox_add_address($input);
            break;
        case 'mailbox_remove_address':
            handle_mailbox_remove_address($input);
            break;
        case 'group_create':
            handle_group_create($input);
            break;
        case 'group_add_member':
            handle_group_add_member($input);
            break;
        case 'group_remove_member':
            handle_group_remove_member($input);
            break;
        case 'group_delete':
            handle_group_delete($input);
            break;
        case 'monitoring_queues':
            handle_monitoring_queues($input);
            break;
        case 'monitoring_message_tracking':
            handle_monitoring_message_tracking($input);
            break;
        // P2 — Nice to Have
        case 'mailbox_add_full_access':
            handle_mailbox_add_full_access($input);
            break;
        case 'mailbox_remove_full_access':
            handle_mailbox_remove_full_access($input);
            break;
        case 'mailbox_add_send_as':
            handle_mailbox_add_send_as($input);
            break;
        case 'mailbox_remove_send_as':
            handle_mailbox_remove_send_as($input);
            break;
        case 'mailbox_set_litigation_hold':
            handle_mailbox_set_litigation_hold($input);
            break;
        case 'mailbox_set_hidden_gal':
            handle_mailbox_set_hidden_gal($input);
            break;
        case 'mailbox_update_profile':
            handle_mailbox_update_profile($input);
            break;
        case 'mailbox_set_oof':
            handle_mailbox_set_oof($input);
            break;
        case 'mailbox_move_request':
            handle_mailbox_move_request($input);
            break;
        case 'monitoring_transport_rules':
            handle_monitoring_transport_rules();
            break;
        // P3 — Future Features
        case 'mailbox_create_shared':
            handle_mailbox_create_shared($input);
            break;
        case 'mailbox_create_room':
            handle_mailbox_create_room($input);
            break;
        case 'mailbox_create_equipment':
            handle_mailbox_create_equipment($input);
            break;
        case 'mailbox_enable_archive':
            handle_mailbox_enable_archive($input);
            break;
        case 'mailbox_disable_archive':
            handle_mailbox_disable_archive($input);
            break;
        case 'mailbox_get_archive':
            handle_mailbox_get_archive($input);
            break;
        case 'mailbox_set_mail_tip':
            handle_mailbox_set_mail_tip($input);
            break;
        case 'mailbox_set_calendar_permissions':
            handle_mailbox_set_calendar_permissions($input);
            break;
        case 'mailbox_remove_calendar_permissions':
            handle_mailbox_remove_calendar_permissions($input);
            break;
        case 'mailbox_restore_request':
            handle_mailbox_restore_request($input);
            break;
        case 'monitoring_retention_policies':
            handle_monitoring_retention_policies();
            break;
        case 'settings_save':
            handle_settings_save($input);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$exchangeResponseBody = ob_get_clean();
exchange_audit_response($action, $input, $exchangeResponseBody);
echo $exchangeResponseBody;

function exchange_audit_response(string $action, array $input, string $responseBody): void
{
    $mutatingActions = [
        'settings_save',
        'mailbox_enable',
        'mailbox_disable',
        'mailbox_set_quota',
        'mailbox_set_forward',
        'mailbox_set_primary_smtp',
        'mailbox_add_address',
        'mailbox_remove_address',
        'mailbox_add_full_access',
        'mailbox_remove_full_access',
        'mailbox_add_send_as',
        'mailbox_remove_send_as',
        'mailbox_set_litigation_hold',
        'mailbox_set_hidden_gal',
        'mailbox_update_profile',
        'mailbox_user_create',
        'mailbox_set_oof',
        'mailbox_move_request',
        'mailbox_create_shared',
        'mailbox_create_room',
        'mailbox_create_equipment',
        'mailbox_enable_archive',
        'mailbox_disable_archive',
        'mailbox_set_mail_tip',
        'mailbox_set_calendar_permissions',
        'mailbox_remove_calendar_permissions',
        'mailbox_restore_request',
        'group_create',
        'group_add_member',
        'group_remove_member',
        'group_delete',
    ];

    if (!in_array($action, $mutatingActions, true) || !function_exists('log_activity')) {
        return;
    }

    $decoded = json_decode($responseBody, true);
    $success = is_array($decoded) && !empty($decoded['success']);
    $message = is_array($decoded) ? (string)($decoded['message'] ?? '') : trim($responseBody);
    $target = exchange_audit_target($input);
    $details = trim(($target !== '' ? "Target: {$target}. " : '') . $message);
    $username = $_SESSION['username'] ?? 'UnknownUser';

    log_activity($username, 'exchange_' . $action, $success ? 'success' : 'failure', $details);

    if (function_exists('ldap_write_script_log')) {
        ldap_write_script_log(
            $action,
            $target,
            $success,
            $message,
            $username
        );
    }
}

function exchange_audit_target(array $input): string
{
    $value = '';
    foreach (['identity', 'user', 'group', 'member', 'name', 'email', 'source_mailbox', 'target_mailbox'] as $key) {
        $value = trim((string)($input[$key] ?? ''));
        if ($value !== '') {
            break;
        }
    }
    return $value;
}

function handle_exchange_diagnostic_test(array $input = []): void
{
    $result = ldap_run_with_connection(function ($connection, $config) use ($input) {
        $baseDn = ldap_search_base_dn($config);
        if ($baseDn === '') {
            return ['success' => false, 'message' => 'LDAP base DN not configured.', 'diagnostic' => []];
        }

        // Use form override if provided, otherwise fall back to stored config
        $override = $input['config_override'] ?? [];
        $exchangeConfig = ldap_exchange_active_domain_config();
        if (!empty($override)) {
            $exchangeConfig = array_replace($exchangeConfig, $override);
        }
        $isEnabled = !isset($exchangeConfig['enabled']) || !empty($exchangeConfig['enabled']);

        if (!$isEnabled) {
            return [
                'success' => false,
                'message' => 'Exchange is disabled in domain configuration. Enable it first.',
                'diagnostic' => ['enabled' => false],
            ];
        }

        $resolution = [];
        $issues = [];
        $suggestions = [];

        // Step 1: Discover server
        $servers = ldap_exchange_discover_servers($connection, $baseDn);
        $serverOverride = $exchangeConfig['server_override'] ?? '';
        $resolvedServer = $serverOverride ?: ($servers[0]['name'] ?? '');
        $resolution['server'] = $resolvedServer ?: 'Not found';

        if (!$resolvedServer) {
            $issues[] = 'No Exchange server detected via LDAP or override.';
            $suggestions[] = 'Set a server_override or ensure LDAP config NC has Exchange server objects.';
        }

        // Step 2: Resolve PS URI
        $uriOverride = $exchangeConfig['ps_uri_override'] ?? '';
        $useHttps = $exchangeConfig['ps_use_https'] ?? true;
        if ($uriOverride !== '') {
            $resolution['uri'] = $uriOverride;
            $resolution['port'] = (string) parse_url($uriOverride, PHP_URL_PORT) ?: '80';
            $resolution['uri_source'] = 'override';
        } elseif ($resolvedServer) {
            $port = $useHttps ? '5986' : '5985';
            $protocol = $useHttps ? 'https' : 'http';
            $resolution['uri'] = "{$protocol}://{$resolvedServer}:{$port}/PowerShell/";
            $resolution['port'] = $port;
            $resolution['uri_source'] = 'auto-built';
        } else {
            $resolution['uri'] = '';
            $resolution['port'] = '';
            $resolution['uri_source'] = 'none';
            $issues[] = 'Could not resolve Exchange PS URI.';
            $suggestions[] = 'Configure ps_uri_override or ensure server is discovered.';
        }

        // Step 3: Resolve credentials
        $credMode = $exchangeConfig['cred_mode'] ?? '';
        $psUsername = $exchangeConfig['ps_username'] ?? '';
        $psPassword = $exchangeConfig['ps_password'] ?? '';
        $ldapBindDn = $config['bind_dn'] ?? '';

        if ($credMode === 'override' || ($psUsername !== '' && $psPassword !== '')) {
            $resolution['credential_mode'] = 'custom_account';
            $resolution['effective_user'] = $psUsername ?: $ldapBindDn;
        } else {
            $resolution['credential_mode'] = 'bind_user';
            $resolution['effective_user'] = $ldapBindDn;
        }
        $resolution['bind_dn'] = $ldapBindDn;

        // Step 4: Test cmdlet execution (if URI is available)
        $cmdletTest = null;
        if ($resolution['uri'] !== '') {
            $scriptPath = __DIR__ . '/../../../Infrastructure/PowerShell/ExchangePsRunner.php';
            if (file_exists($scriptPath)) {
                require_once $scriptPath;
                if (function_exists('exchange_run_cmdlet')) {
                    $cmdletOverride = !empty($override) ? $override : [];
                    $cmdletResult = exchange_run_cmdlet('Get-OrganizationConfig', ['ResultSize' => 1], $cmdletOverride);
                    $decoded = $cmdletResult['decoded'] ?? null;
                    // Success: cmdlet ran without PS error, decoded has org data (no success=false)
                    $cmdletFailed = ($decoded !== null && !empty($decoded['success']) && $decoded['success'] === false) || !empty($cmdletResult['exit_code']);
                    if ($decoded !== null && !$cmdletFailed) {
                        $cmdletTest = [
                            'success' => true,
                            'message' => 'Exchange cmdlet executed successfully. Account has Exchange permissions.',
                            'output' => (is_string($decoded) ? $decoded : ($decoded['Name'] ?? 'Organization config retrieved')),
                        ];
                    } else {
                        $errMsg = $cmdletResult['output'] ?? ($decoded['message'] ?? 'Unknown cmdlet error');
                        $cmdletTest = [
                            'success' => false,
                            'message' => $errMsg,
                        ];
                        if (preg_match('/access denied|not authorized|permission|denied/i', $errMsg)) {
                            $issues[] = 'The account does not have Exchange management permissions.';
                            $suggestions[] = 'Grant Exchange RBAC roles (e.g., Organization Management) to: ' . $resolution['effective_user'];
                        } elseif (preg_match('/cannot connect|could not connect|timeout|unreachable/i', $errMsg)) {
                            $issues[] = 'Cannot connect to Exchange PowerShell endpoint.';
                            $suggestions[] = 'Verify the server hostname, port, and PS URI. Ensure Exchange PowerShell vDir is configured.';
                        } elseif (preg_match('/kerberos|authentication|credential/i', $errMsg)) {
                            $issues[] = 'Authentication failed.';
                            $suggestions[] = $resolution['credential_mode'] === 'bind_user'
                                ? 'Ensure the LDAP bind user has Exchange access. Try using a custom Exchange account with explicit credentials.'
                                : 'Check the username and password. Ensure the account is a domain user with Exchange RBAC roles.';
                        } else {
                            $suggestions[] = 'Check Exchange server status and PowerShell virtual directory configuration. Error: ' . $errMsg;
                        }
                    }
                } else {
                    $cmdletTest = ['success' => false, 'message' => 'Exchange PowerShell runner not available.'];
                    $issues[] = 'Exchange PowerShell integration not loaded.';
                    $suggestions[] = 'Ensure ExchangePsRunner.php is deployed.';
                }
            } else {
                $cmdletTest = ['success' => false, 'message' => 'Exchange PowerShell runner file not found.'];
                $issues[] = 'Exchange PowerShell runner missing.';
                $suggestions[] = 'Deploy ExchangePsRunner.php.';
            }
        } else {
            $cmdletTest = ['success' => false, 'message' => 'No PS URI to test against.'];
        }

        $overallSuccess = $cmdletTest ? $cmdletTest['success'] : false;

        return [
            'success' => $overallSuccess,
            'message' => $overallSuccess
                ? 'Exchange diagnostic passed. Account "' . $resolution['effective_user'] . '" has Exchange permissions.'
                : 'Exchange diagnostic failed. Check suggestions below.',
            'diagnostic' => [
                'enabled' => true,
                'server' => $resolution['server'],
                'uri' => $resolution['uri'],
                'port' => $resolution['port'],
                'uri_source' => $resolution['uri_source'],
                'credential_mode' => $resolution['credential_mode'],
                'effective_user' => $resolution['effective_user'],
                'bind_dn' => $resolution['bind_dn'],
                'cmdlet_test' => $cmdletTest,
                'issues' => $issues,
                'suggestions' => $suggestions,
            ],
        ];
    });

    exchange_json_response($result);
}

function exchange_json_response(array $payload): void
{
    $json = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    if ($json === false) {
        $json = json_encode([
            'success' => false,
            'message' => 'JSON encoding failed: ' . json_last_error_msg(),
        ]);
    }
    echo $json;
}

function exchange_failure_message(array $result, string $fallback = 'Exchange operation failed.'): string
{
    $decoded = $result['decoded'] ?? null;
    if (is_array($decoded)) {
        foreach (['message', 'error', 'Exception'] as $key) {
            if (!empty($decoded[$key]) && is_scalar($decoded[$key])) {
                return trim((string)$decoded[$key]);
            }
        }
    }

    foreach (['message', 'error', 'output'] as $key) {
        if (!empty($result[$key]) && is_scalar($result[$key])) {
            $value = trim((string)$result[$key]);
            if ($value !== '') {
                return $value;
            }
        }
    }

    if (isset($result['exit_code'])) {
        return $fallback . ' Exit code: ' . (string)$result['exit_code'];
    }

    return $fallback;
}

function handle_discover(): void
{
    $result = ldap_run_with_connection(function ($connection, $config) {
        $baseDn = ldap_search_base_dn($config);
        if ($baseDn === '') {
            return ['success' => false, 'message' => 'LDAP base DN not configured.'];
        }

        $exchangeConfig = ldap_exchange_active_domain_config();

        // Build exchange config payload (always include, password masked)
        $exchangeConfigPayload = $exchangeConfig;
        unset($exchangeConfigPayload['ps_password']);
        $exchangeConfigPayload['has_password'] = !empty($exchangeConfig['ps_password']);

        // Load default policies from vault
        $defaultPolicies = [
            'default_database' => '',
            'default_quota' => '10',
            'warning_quota' => '8',
        ];
        if (function_exists('read_vault_config')) {
            $vault = read_vault_config('app_integrations.php');
            $exSettings = $vault['exchange'] ?? [];
            $defaultPolicies['default_database'] = (string)($exSettings['default_database'] ?? '');
            $defaultPolicies['default_quota'] = (string)($exSettings['default_quota'] ?? '10');
            $defaultPolicies['warning_quota'] = (string)($exSettings['warning_quota'] ?? '8');
        }

        // Try LDAP discovery first
        $servers = ldap_exchange_discover_servers($connection, $baseDn);
        $databases = ldap_exchange_get_databases($connection, $baseDn);

        if (!empty($servers)) {
            $server = $servers[0];
            $payload = [
                'success' => true,
                'server' => $server['name'],
                'fqdn' => $server['fqdn'],
                'version' => $server['version'],
                'databases' => $databases,
                'source' => 'ldap',
                'exchange_config' => $exchangeConfigPayload,
                'default_policies' => $defaultPolicies,
            ];
            return $payload;
        }

        // Fallback: return non-sensitive config info
        $payload = [
            'success' => !empty($exchangeConfig),
            'message' => !empty($exchangeConfig) ? 'Exchange configured (auto-discovery unavailable)' : 'No Exchange server detected. Configure in Settings.',
            'config' => $exchangeConfigPayload,
            'exchange_config' => $exchangeConfigPayload,
            'default_policies' => $defaultPolicies,
            'source' => 'config',
        ];
        return $payload;
    });

    exchange_json_response($result);
}

function handle_mailbox_search(array $input): void
{
    $identity = trim((string) ($input['identity'] ?? ''));
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required.']);
        return;
    }

    $result = ldap_run_with_connection(function ($connection, $config) use ($identity) {
        $baseDn = ldap_search_base_dn($config);
        if ($baseDn === '') {
            return ['success' => false, 'message' => 'LDAP base DN not configured.'];
        }

        $entry = ldap_user_lookup_entry($connection, $baseDn, $identity);
        if ($entry === null) {
            return ['success' => false, 'message' => "User '{$identity}' not found."];
        }

        $userInfo = ldap_adapt_get_ad_user_info($entry);
        $mailbox = $userInfo['exchange_mailbox'] ?? [];

        return [
            'success' => true,
            'user' => $userInfo,
            'mailbox' => $mailbox,
        ];
    });

    exchange_json_response($result);
}

function handle_mailbox_list(array $input): void
{
    $keyword = trim((string)($input['keyword'] ?? ''));
    $limit = max(1, min(500, (int)($input['limit'] ?? 50)));
    if ($keyword === '') {
        exchange_json_response([
            'success' => true,
            'mailboxes' => [],
            'total_returned' => 0,
            'has_more' => false,
            'message' => 'Enter a username, display name, or email to search.',
        ]);
        return;
    }

    $result = ldap_run_with_connection(function ($connection, $config) use ($keyword, $limit) {
        $baseDn = ldap_search_base_dn($config);
        if ($baseDn === '') {
            return ['success' => false, 'message' => 'LDAP base DN not configured.', 'mailboxes' => []];
        }

        $attrs = [
            'samaccountname', 'displayname', 'mail', 'userprincipalname', 'title',
            'physicaldeliveryofficename', 'telephonenumber', 'department',
            'proxyaddresses', 'mailnickname', 'msexchmailboxguid',
            'msexchrecipienttypedetails', 'msexcharchivemailboxguid',
            'msexchuseraccountcontrol', 'msexchrecipientdisplaytype',
            'mdbusedefaults', 'mdbstoragequota', 'mdboverquotalimit', 'mdboverhardquotalimit',
        ];

        $escaped = ldap_escape_filter_value($keyword);
        $filter = '(&(objectCategory=person)(objectClass=user)(|(displayName=*' . $escaped . '*)(sAMAccountName=*' . $escaped . '*)(mail=*' . $escaped . '*)(userPrincipalName=*' . $escaped . '*)))';

        $search = @ldap_search($connection, $baseDn, $filter, $attrs, 0, $limit + 1, 0);
        if ($search === false) {
            return ['success' => false, 'message' => 'LDAP mailbox list failed: ' . ldap_error($connection), 'mailboxes' => []];
        }

        if (function_exists('ldap_sort')) {
            @ldap_sort($connection, $search, 'displayname');
        }
        $raw = ldap_get_entries($connection, $search);
        $count = is_array($raw) ? (int)($raw['count'] ?? 0) : 0;
        $rows = [];
        $max = min($count, $limit);

        for ($i = 0; $i < $max; $i++) {
            $entry = ldap_normalize_entry($raw[$i]);
            $proxy = ldap_parse_proxy_addresses($entry);
            $hasMailbox = ldap_user_has_mailbox($entry);
            $recipientType = (string) ldap_first_attr($entry, 'msexchrecipienttypedetails', '');
            $archiveGuid = ldap_first_attr($entry, 'msexcharchivemailboxguid', '');
            $sam = ldap_first_attr($entry, 'samaccountname', '');
            $display = ldap_first_attr($entry, 'displayname', $sam);
            $primary = $proxy['primary'] ?: ldap_first_attr($entry, 'mail', ldap_first_attr($entry, 'userprincipalname', ''));

            $rows[] = [
                'identity' => $sam,
                'display_name' => $display,
                'has_mailbox' => $hasMailbox,
                'mailbox_type' => $hasMailbox ? ($archiveGuid !== '' ? 'User (Archive)' : 'User') : 'Not enabled',
                'email' => $primary,
                'alias' => ldap_first_attr($entry, 'mailnickname', ''),
                'title' => ldap_first_attr($entry, 'title', ''),
                'office' => ldap_first_attr($entry, 'physicaldeliveryofficename', ''),
                'phone' => ldap_first_attr($entry, 'telephonenumber', ''),
                'department' => ldap_first_attr($entry, 'department', ''),
                'recipient_type_details' => $recipientType,
                'archive_enabled' => $archiveGuid !== '',
                'hidden_from_gal' => (int) ldap_first_attr($entry, 'msexchrecipientdisplaytype', '0') === -2147483642,
                'mailbox_disabled' => ((int) ldap_first_attr($entry, 'msexchuseraccountcontrol', '0') & 2) === 2,
            ];
        }

        return [
            'success' => true,
            'mailboxes' => $rows,
            'total_returned' => count($rows),
            'has_more' => $count > $limit,
            'limit' => $limit,
        ];
    });

    exchange_json_response($result);
}

function handle_mailbox_stats(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    if ($identity === '') {
        exchange_json_response(['success' => false, 'message' => 'Identity is required.']);
        return;
    }
    if (!require_exchange_ps()) return;

    $statsResult = exchange_get_mailbox_statistics($identity);
    $mailboxResult = exchange_get_mailbox($identity);
    $statsDecoded = $statsResult['decoded'] ?? null;
    $mailboxDecoded = $mailboxResult['decoded'] ?? null;
    $success = (!empty($statsDecoded) || !empty($statsResult['success']));

    exchange_json_response([
        'success' => $success,
        'stats' => $statsDecoded,
        'mailbox' => $mailboxDecoded,
        'message' => $success ? 'Mailbox statistics loaded.' : (($statsDecoded['message'] ?? null) ?: ($statsResult['output'] ?? 'Mailbox statistics unavailable.')),
        'ps_output' => $statsResult['output'] ?? '',
    ]);
}

function handle_mailbox_enable(array $input): void
{
    $identity = trim((string) ($input['identity'] ?? ''));
    $database = trim((string) ($input['database'] ?? ''));
    $alias = trim((string)($input['alias'] ?? ''));
    $primarySmtp = trim((string)($input['primary_smtp'] ?? ''));
    $smtpAliasesRaw = trim((string)($input['smtp_aliases'] ?? ''));

    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required.']);
        return;
    }

    $exchangePsPath = __DIR__ . '/../../../Infrastructure/PowerShell/ExchangePsRunner.php';
    if (!file_exists($exchangePsPath)) {
        echo json_encode(['success' => false, 'message' => 'Exchange PowerShell runner not available.']);
        return;
    }
    require_once $exchangePsPath;

    if (!function_exists('exchange_enable_mailbox')) {
        echo json_encode(['success' => false, 'message' => 'Exchange functions not available.']);
        return;
    }

    $result = exchange_enable_mailbox($identity, $database);
    $decoded = $result['decoded'] ?? [];

    if (!empty($decoded['success']) || $result['success']) {
        $postMessages = [];
        if ($alias !== '' && function_exists('exchange_set_mailbox_alias')) {
            $aliasResult = exchange_set_mailbox_alias($identity, $alias);
            if (empty($aliasResult['success']) && empty(($aliasResult['decoded'] ?? [])['success'])) {
                $postMessages[] = 'Alias update failed.';
            }
        }
        if ($primarySmtp !== '' && function_exists('exchange_set_primary_smtp')) {
            $smtpResult = exchange_set_primary_smtp($identity, $primarySmtp);
            if (empty($smtpResult['success']) && empty(($smtpResult['decoded'] ?? [])['success'])) {
                $postMessages[] = 'Primary SMTP update failed.';
            }
        }
        if ($smtpAliasesRaw !== '' && function_exists('exchange_add_email_address')) {
            $aliases = preg_split('/[\s,;]+/', $smtpAliasesRaw, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($aliases as $smtpAlias) {
                $smtpAlias = trim((string)$smtpAlias);
                if ($smtpAlias === '' || strcasecmp($smtpAlias, $primarySmtp) === 0) {
                    continue;
                }
                $addResult = exchange_add_email_address($identity, $smtpAlias);
                if (empty($addResult['success']) && empty(($addResult['decoded'] ?? [])['success'])) {
                    $postMessages[] = "Alias {$smtpAlias} add failed.";
                }
            }
        }
        $message = "Mailbox enabled for '{$identity}'.";
        if ($postMessages) {
            $message .= ' ' . implode(' ', $postMessages);
        }
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        $msg = $decoded['message'] ?? $result['output'] ?? 'Unknown error';
        echo json_encode(['success' => false, 'message' => $msg]);
    }
}

function handle_mailbox_user_create(array $input): void
{
    $firstName = trim((string)($input['firstName'] ?? ''));
    $lastName = trim((string)($input['lastName'] ?? ''));
    $username = trim((string)($input['username'] ?? ''));
    $displayName = trim((string)($input['displayName'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $ouDn = trim((string)($input['ou'] ?? ''));

    if ($firstName === '' || $lastName === '' || $username === '') {
        echo json_encode(['success' => false, 'message' => 'First name, last name, and username are required.']);
        return;
    }

    if ($displayName === '') {
        $displayName = $firstName . ' ' . $lastName;
    }
    if ($email === '') {
        $email = $username . '@' . ($_SERVER['HTTP_HOST'] ?? 'domain.com');
    }

    // Step 1: Create AD user via LDAP
    $ldapResult = ldap_run_with_connection(function ($connection, $config) use ($firstName, $lastName, $username, $displayName, $email, $ouDn) {
        $baseDn = ldap_search_base_dn($config);
        if ($baseDn === '') {
            throw new RuntimeException('LDAP base DN is not configured.');
        }

        $containerDn = $ouDn !== '' ? $ouDn : $baseDn;

        // Check if user already exists
        $existing = @ldap_search($connection, $baseDn, "(sAMAccountName={$username})", ['dn'], 0, 1, 0);
        if ($existing !== false && ldap_count_entries($connection, $existing) > 0) {
            throw new RuntimeException("User '{$username}' already exists in AD.");
        }

        $upn = $email;
        $dn = "CN=" . ldap_escape_dn_value($displayName) . "," . $containerDn;

        $entry = [
            'objectClass' => ['top', 'person', 'organizationalPerson', 'user'],
            'cn' => $displayName,
            'sn' => $lastName,
            'givenName' => $firstName,
            'displayName' => $displayName,
            'name' => $displayName,
            'sAMAccountName' => $username,
            'userPrincipalName' => $upn,
            'mail' => $email,
            'userAccountControl' => 544,
        ];

        if (!@ldap_add($connection, $dn, $entry)) {
            throw new RuntimeException('LDAP user creation failed: ' . ldap_error($connection));
        }

        return ['success' => true, 'dn' => $dn];
    });

    if (empty($ldapResult['success'])) {
        $errMsg = is_string($ldapResult) ? $ldapResult : ($ldapResult['message'] ?? 'LDAP user creation failed.');
        echo json_encode(['success' => false, 'message' => $errMsg]);
        return;
    }

    // Step 2: Enable mailbox via Exchange PS
    $exchangePsPath = __DIR__ . '/../../../Infrastructure/PowerShell/ExchangePsRunner.php';
    if (!file_exists($exchangePsPath)) {
        echo json_encode(['success' => true, 'message' => "User '{$displayName}' created in AD. Exchange PS not available to enable mailbox."]);
        return;
    }
    require_once $exchangePsPath;

    $mbResult = exchange_enable_mailbox($username);
    $mbDecoded = $mbResult['decoded'] ?? [];

    if (!empty($mbDecoded['success']) || $mbResult['success']) {
        if ($email !== '' && function_exists('exchange_set_primary_smtp')) {
            exchange_set_primary_smtp($username, $email);
        }

        // Step 3: Add to selected groups
        $groups = $input['groups'] ?? [];
        if (!empty($groups) && is_array($groups)) {
            $ldapDn = $ldapResult['dn'] ?? '';
            if ($ldapDn !== '') {
                ldap_run_with_connection(function ($connection) use ($groups, $ldapDn) {
                    foreach ($groups as $groupDn) {
                        $groupDn = trim((string)$groupDn);
                        if ($groupDn === '') continue;
                        @ldap_mod_add($connection, $groupDn, ['member' => $ldapDn]);
                    }
                });
            }
        }

        echo json_encode(['success' => true, 'message' => "User '{$displayName}' created and mailbox enabled."]);
    } else {
        $msg = $mbDecoded['message'] ?? $mbResult['output'] ?? 'Mailbox enable failed.';
        echo json_encode(['success' => true, 'message' => "User '{$displayName}' created in AD but mailbox enable failed: {$msg}. Set password manually via User Management."]);
    }
}

function handle_mailbox_disable(array $input): void
{
    $identity = trim((string) ($input['identity'] ?? ''));
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required.']);
        return;
    }

    $exchangePsPath = __DIR__ . '/../../../Infrastructure/PowerShell/ExchangePsRunner.php';
    if (!file_exists($exchangePsPath)) {
        echo json_encode(['success' => false, 'message' => 'Exchange PowerShell runner not available.']);
        return;
    }
    require_once $exchangePsPath;

    if (!function_exists('exchange_disable_mailbox')) {
        echo json_encode(['success' => false, 'message' => 'Exchange functions not available.']);
        return;
    }

    $result = exchange_disable_mailbox($identity);
    $decoded = $result['decoded'] ?? [];

    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Mailbox disabled for '{$identity}'."]);
    } else {
        $msg = $decoded['message'] ?? $result['output'] ?? 'Unknown error';
        echo json_encode(['success' => false, 'message' => $msg]);
    }
}

function handle_group_search(array $input): void
{
    $keyword = trim((string) ($input['keyword'] ?? ''));
    if ($keyword === '') {
        echo json_encode(['success' => false, 'message' => 'Keyword is required.', 'groups' => []]);
        return;
    }

    require_once __DIR__ . '/../../../Ldap/Operations/ldap_group_repository.php';
    $result = ldap_group_repository_search_dl(['Keyword' => $keyword], 'exchange_api');
    echo json_encode($result['decoded'] ?? $result);
}

function handle_group_members(array $input): void
{
    $group = trim((string) ($input['group'] ?? ''));
    if ($group === '') {
        echo json_encode(['success' => false, 'message' => 'Group identity is required.', 'members' => []]);
        return;
    }

    require_once __DIR__ . '/../../../Ldap/Operations/ldap_group_repository.php';
    $result = ldap_group_repository_members(['GroupIdentity' => $group], 'exchange_api');
    echo json_encode($result['decoded'] ?? $result);
}

function handle_monitoring_databases(): void
{
    $result = ldap_run_with_connection(function ($connection, $config) {
        $baseDn = ldap_search_base_dn($config);
        if ($baseDn === '') {
            return ['success' => false, 'databases' => [], 'message' => 'LDAP base DN not configured.'];
        }
        $databases = ldap_exchange_get_databases($connection, $baseDn);

        $servers = [];
        $totalMailboxCount = 0;
        foreach ($databases as $db) {
            $srv = $db['server'] ?? '';
            if ($srv !== '') {
                $servers[$srv] = true;
            }
        }
        // Count mailboxes by counting msExchMailboxRecipient entries per database
        $mailboxCount = ldap_exchange_estimate_mailbox_count($connection, $baseDn);

        return [
            'success' => true,
            'databases' => $databases,
            'total' => count($databases),
            'server_count' => count($servers),
            'mailbox_count' => $mailboxCount,
        ];
    });
    $exchangePsPath = __DIR__ . '/../../../Infrastructure/PowerShell/ExchangePsRunner.php';
    if (!empty($result['success']) && empty($result['databases']) && file_exists($exchangePsPath)) {
        require_once $exchangePsPath;
    }
    if (!empty($result['success']) && empty($result['databases']) && function_exists('exchange_get_databases')) {
        $psResult = exchange_get_databases();
        $decoded = $psResult['decoded'] ?? [];
        $items = [];
        if (is_array($decoded)) {
            $items = array_is_list($decoded) ? $decoded : [$decoded];
        }
        $databases = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = (string)($item['Name'] ?? $item['name'] ?? $item['Identity'] ?? '');
            if ($name === '') {
                continue;
            }
            $databases[] = [
                'name' => $name,
                'server' => (string)($item['Server'] ?? $item['server'] ?? ''),
                'description' => (string)($item['Description'] ?? $item['description'] ?? 'PowerShell'),
            ];
        }
        if ($databases) {
            $servers = [];
            foreach ($databases as $db) {
                if (($db['server'] ?? '') !== '') $servers[$db['server']] = true;
            }
            $result['databases'] = $databases;
            $result['total'] = count($databases);
            $result['server_count'] = count($servers);
            $result['source'] = 'powershell';
        }
    }
    echo json_encode($result);
}

function handle_monitoring_quota(): void
{
    $exchangePsPath = __DIR__ . '/../../../Infrastructure/PowerShell/ExchangePsRunner.php';
    if (!file_exists($exchangePsPath)) {
        echo json_encode(['success' => false, 'message' => 'Exchange PowerShell runner not available.']);
        return;
    }
    require_once $exchangePsPath;

    if (!function_exists('exchange_get_mailbox_statistics')) {
        echo json_encode(['success' => false, 'message' => 'Exchange functions not available.']);
        return;
    }

    $result = exchange_get_mailbox_statistics('*');
    $decoded = $result['decoded'] ?? [];
    $mailboxes = [];
    if (is_array($decoded)) {
        if (isset($decoded['success'])) {
            $mailboxes = $decoded['mailboxes'] ?? [];
        } elseif (array_is_list($decoded)) {
            $mailboxes = $decoded;
        } elseif (isset($decoded['DisplayName']) || isset($decoded['Identity'])) {
            $mailboxes = [$decoded];
        }
    }
    // Fallback: if decoded failed but we have output, try to extract individual items
    if (empty($mailboxes) && !empty($result['output'])) {
        $lines = explode("\n", trim($result['output']));
        foreach ($lines as $line) {
            $item = json_decode($line, true);
            if (is_array($item) && (isset($item['DisplayName']) || isset($item['Identity']))) {
                $mailboxes[] = $item;
            }
        }
    }
    $quotaWarnings = [];
    foreach ($mailboxes as $mbx) {
        if (!empty($mbx['StorageLimitStatus']) && (int)$mbx['StorageLimitStatus'] > 0) {
            $quotaWarnings[] = $mbx;
        }
    }
    echo json_encode([
        'success' => !empty($mailboxes),
        'mailboxes' => $mailboxes,
        'quota_warnings' => $quotaWarnings,
        'message' => empty($quotaWarnings) ? 'No users near quota.' : 'Found ' . count($quotaWarnings) . ' user(s) near quota.',
        'ps_output' => $result['output'] ?? '',
    ]);
}

function require_exchange_ps(): bool
{
    $path = __DIR__ . '/../../../Infrastructure/PowerShell/ExchangePsRunner.php';
    if (!file_exists($path)) {
        echo json_encode(['success' => false, 'message' => 'Exchange PowerShell runner not available.']);
        return false;
    }
    require_once $path;
    return true;
}

function handle_mailbox_set_quota(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    $warn = trim((string)($input['issue_warning_quota'] ?? '5'));
    $send = trim((string)($input['prohibit_send_quota'] ?? '6'));
    $recv = trim((string)($input['prohibit_send_receive_quota'] ?? '8'));
    $unit = strtoupper(trim((string)($input['quota_unit'] ?? 'GB')));
    if (!in_array($unit, ['MB', 'GB', 'TB'], true)) {
        $unit = 'GB';
    }
    $warn = preg_match('/^\d+(?:\.\d+)?$/', $warn) ? $warn . $unit : $warn;
    $send = preg_match('/^\d+(?:\.\d+)?$/', $send) ? $send . $unit : $send;
    $recv = preg_match('/^\d+(?:\.\d+)?$/', $recv) ? $recv . $unit : $recv;
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required.']);
        return;
    }
    if (!require_exchange_ps()) return;
    $result = exchange_set_mailbox_quota($identity, $warn, $send, $recv);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Quota updated for '{$identity}'."]);
    } else {
        $msg = $decoded['message'] ?? $result['output'] ?? 'Unknown error';
        echo json_encode(['success' => false, 'message' => $msg]);
    }
}

function handle_mailbox_set_forward(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    $forwardTo = trim((string)($input['forward_to'] ?? ''));
    $deliverToMailbox = !empty($input['deliver_to_mailbox']);
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required.']);
        return;
    }
    if (!require_exchange_ps()) return;
    $result = exchange_set_forwarding($identity, $forwardTo, $deliverToMailbox);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        $msg = $forwardTo ? "Forwarding set to {$forwardTo} for '{$identity}'." : "Forwarding cleared for '{$identity}'.";
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        $msg = $decoded['message'] ?? $result['output'] ?? 'Unknown error';
        echo json_encode(['success' => false, 'message' => $msg]);
    }
}

function handle_mailbox_set_primary_smtp(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    if ($identity === '' || $email === '') {
        echo json_encode(['success' => false, 'message' => 'Identity and email are required.']);
        return;
    }
    if (!require_exchange_ps()) return;
    $result = exchange_set_primary_smtp($identity, $email);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Primary SMTP set to {$email} for '{$identity}'."]);
    } else {
        $msg = exchange_failure_message($result, 'Primary SMTP update failed.');
        echo json_encode(['success' => false, 'message' => $msg]);
    }
}

function handle_mailbox_add_address(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    if ($identity === '' || $email === '') {
        echo json_encode(['success' => false, 'message' => 'Identity and email are required.']);
        return;
    }
    if (!require_exchange_ps()) return;
    $result = exchange_add_email_address($identity, $email);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Email address {$email} added to '{$identity}'."]);
    } else {
        $msg = exchange_failure_message($result, 'Email address add failed.');

        echo json_encode([
            'success' => false,
            'message' => $msg,
        ]);
    }
}

function handle_mailbox_remove_address(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    if ($identity === '' || $email === '') {
        echo json_encode(['success' => false, 'message' => 'Identity and email are required.']);
        return;
    }
    if (!require_exchange_ps()) return;
    $result = exchange_remove_email_address($identity, $email);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Email address {$email} removed from '{$identity}'."]);
    } else {
        $msg = exchange_failure_message($result, 'Email address remove failed.');
        echo json_encode(['success' => false, 'message' => $msg]);
    }
}

function handle_group_create(array $input): void
{
    $name = trim((string)($input['name'] ?? ''));
    $alias = trim((string)($input['alias'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $ou = trim((string)($input['ou'] ?? ''));
    if ($name === '') {
        echo json_encode(['success' => false, 'message' => 'Group name is required.']);
        return;
    }
    if (!require_exchange_ps()) return;
    $result = exchange_new_distribution_group($name, $alias, $description, [], $ou);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Distribution group '{$name}' created."]);
    } else {
        $msg = $decoded['message'] ?? $result['output'] ?? 'Unknown error';
        echo json_encode(['success' => false, 'message' => $msg]);
    }
}

function handle_group_add_member(array $input): void
{
    $group = trim((string)($input['group'] ?? ''));
    $member = trim((string)($input['member'] ?? ''));
    if ($group === '' || $member === '') {
        echo json_encode(['success' => false, 'message' => 'Group and member are required.']);
        return;
    }
    if (!require_exchange_ps()) return;
    $result = exchange_add_group_member($group, $member);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Member {$member} added to group '{$group}'."]);
    } else {
        $msg = $decoded['message'] ?? $result['output'] ?? 'Unknown error';
        echo json_encode(['success' => false, 'message' => $msg]);
    }
}

function handle_group_remove_member(array $input): void
{
    $group = trim((string)($input['group'] ?? ''));
    $member = trim((string)($input['member'] ?? ''));
    if ($group === '' || $member === '') {
        echo json_encode(['success' => false, 'message' => 'Group and member are required.']);
        return;
    }
    if (!require_exchange_ps()) return;
    $result = exchange_remove_group_member($group, $member);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Member {$member} removed from group '{$group}'."]);
    } else {
        $msg = $decoded['message'] ?? $result['output'] ?? 'Unknown error';
        echo json_encode(['success' => false, 'message' => $msg]);
    }
}

function handle_group_delete(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Group identity is required.']);
        return;
    }
    if (!require_exchange_ps()) return;
    $result = exchange_remove_distribution_group($identity);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Distribution group '{$identity}' removed."]);
    } else {
        $msg = $decoded['message'] ?? $result['output'] ?? 'Unknown error';
        echo json_encode(['success' => false, 'message' => $msg]);
    }
}

function handle_monitoring_queues(array $input): void
{
    $server = trim((string)($input['server'] ?? ''));
    if (!require_exchange_ps()) return;
    $result = exchange_get_queues($server);
    $decoded = $result['decoded'] ?? [];
    $queues = [];
    if (is_array($decoded)) {
        if (isset($decoded['success'])) {
            $queues = $decoded['queues'] ?? [];
        } else {
            $queues = array_is_list($decoded) ? $decoded : [$decoded];
        }
    }
    echo json_encode([
        'success' => !empty($queues) || $result['success'],
        'queues' => $queues,
        'message' => !empty($queues) ? 'Queue data retrieved.' : ($decoded['message'] ?? $result['output'] ?? 'Failed to retrieve queue data.'),
    ]);
}

function handle_monitoring_message_tracking(array $input): void
{
    $sender = trim((string)($input['sender'] ?? ''));
    $recipient = trim((string)($input['recipient'] ?? ''));
    $startDate = trim((string)($input['start_date'] ?? ''));
    $endDate = trim((string)($input['end_date'] ?? ''));
    if (!require_exchange_ps()) return;
    $result = exchange_get_message_tracking($sender, $recipient, $startDate, $endDate);
    $decoded = $result['decoded'] ?? [];
    $messages = [];
    if (is_array($decoded)) {
        if (isset($decoded['success'])) {
            $messages = $decoded['messages'] ?? [];
        } else {
            $messages = array_is_list($decoded) ? $decoded : [$decoded];
        }
    }
    echo json_encode([
        'success' => !empty($messages) || $result['success'],
        'messages' => $messages,
        'message' => !empty($messages) ? 'Message tracking data retrieved.' : ($decoded['message'] ?? $result['output'] ?? 'Failed to retrieve message tracking data.'),
    ]);
}

// ===================== P2 Handlers =====================

function handle_mailbox_add_full_access(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    $user = trim((string)($input['user'] ?? ''));
    if ($identity === '' || $user === '') {
        echo json_encode(['success' => false, 'message' => 'Identity and user are required.']);
        return;
    }
    if (!require_exchange_ps()) return;
    $result = exchange_add_full_access($identity, $user);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Full Access granted to {$user} on '{$identity}'."]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? $result['output'] ?? 'Unknown error']);
    }
}

function handle_mailbox_remove_full_access(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    $user = trim((string)($input['user'] ?? ''));
    if ($identity === '' || $user === '') {
        echo json_encode(['success' => false, 'message' => 'Identity and user are required.']);
        return;
    }
    if (!require_exchange_ps()) return;
    $result = exchange_remove_full_access($identity, $user);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Full Access removed from {$user} on '{$identity}'."]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? $result['output'] ?? 'Unknown error']);
    }
}

function handle_mailbox_add_send_as(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    $user = trim((string)($input['user'] ?? ''));
    if ($identity === '' || $user === '') {
        echo json_encode(['success' => false, 'message' => 'Identity and user are required.']);
        return;
    }
    if (!require_exchange_ps()) return;
    $result = exchange_add_send_as($identity, $user);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Send-As permission granted to {$user} on '{$identity}'."]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? $result['output'] ?? 'Unknown error']);
    }
}

function handle_mailbox_remove_send_as(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    $user = trim((string)($input['user'] ?? ''));
    if ($identity === '' || $user === '') {
        echo json_encode(['success' => false, 'message' => 'Identity and user are required.']);
        return;
    }
    if (!require_exchange_ps()) return;
    $result = exchange_remove_send_as($identity, $user);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Send-As permission removed from {$user} on '{$identity}'."]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? $result['output'] ?? 'Unknown error']);
    }
}

function handle_mailbox_set_litigation_hold(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    $enabled = !empty($input['enabled']);
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required.']);
        return;
    }
    if (!require_exchange_ps()) return;
    $result = exchange_set_litigation_hold($identity, $enabled);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        $msg = $enabled ? "Litigation Hold enabled for '{$identity}'." : "Litigation Hold disabled for '{$identity}'.";
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? $result['output'] ?? 'Unknown error']);
    }
}

function handle_mailbox_set_hidden_gal(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    $hidden = !empty($input['hidden']);
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required.']);
        return;
    }
    if (!require_exchange_ps()) return;
    $result = exchange_set_hidden_from_gal($identity, $hidden);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        $msg = $hidden ? "'{$identity}' hidden from GAL." : "'{$identity}' visible in GAL.";
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? $result['output'] ?? 'Unknown error']);
    }
}

function handle_mailbox_update_profile(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required.']);
        return;
    }

    $fields = is_array($input['fields'] ?? null) ? $input['fields'] : [];
    $allowed = [
        'givenName' => 'givenName',
        'initials' => 'initials',
        'sn' => 'sn',
        'displayName' => 'displayName',
        'physicalDeliveryOfficeName' => 'physicalDeliveryOfficeName',
        'telephoneNumber' => 'telephoneNumber',
        'title' => 'title',
        'department' => 'department',
        'company' => 'company',
        'mail' => 'mail',
    ];

    $ldapResult = ldap_run_with_connection(function ($connection, $config) use ($identity, $fields, $allowed) {
        $baseDn = ldap_search_base_dn($config);
        if ($baseDn === '') {
            return ['success' => false, 'message' => 'LDAP base DN not configured.'];
        }
        $entry = ldap_user_lookup_entry($connection, $baseDn, $identity);
        if ($entry === null) {
            return ['success' => false, 'message' => "User '{$identity}' not found."];
        }
        $dn = ldap_first_attr($entry, 'distinguishedname', $entry['dn'] ?? '');
        if ($dn === '') {
            return ['success' => false, 'message' => 'User DN not found.'];
        }

        $mods = [];
        foreach ($allowed as $inputKey => $ldapAttr) {
            if (!array_key_exists($inputKey, $fields)) {
                continue;
            }
            $value = trim((string)$fields[$inputKey]);
            if ($value === '') {
                continue;
            }
            $mods[] = [
                'attrib' => $ldapAttr,
                'modtype' => LDAP_MODIFY_BATCH_REPLACE,
                'values' => [$value],
            ];
        }

        if ($mods && !@ldap_modify_batch($connection, $dn, $mods)) {
            return ['success' => false, 'message' => 'LDAP update failed: ' . ldap_error($connection)];
        }

        return ['success' => true, 'message' => "Profile updated for '{$identity}'."];
    });

    $alias = trim((string)($fields['alias'] ?? ''));
    if (!empty($ldapResult['success']) && $alias !== '') {
        if (!require_exchange_ps()) return;
        if (function_exists('exchange_set_mailbox_alias')) {
            $aliasResult = exchange_set_mailbox_alias($identity, $alias);
            $decoded = $aliasResult['decoded'] ?? [];
            if (empty($decoded['success']) && empty($aliasResult['success'])) {
                $ldapResult['success'] = false;
                $ldapResult['message'] = exchange_failure_message($aliasResult, 'Alias update failed.');
            }
        }
    }

    exchange_json_response($ldapResult);
}

function handle_mailbox_set_oof(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    $state = trim((string)($input['state'] ?? 'Disabled'));
    $internalMsg = trim((string)($input['internal_message'] ?? ''));
    $externalMsg = trim((string)($input['external_message'] ?? ''));
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required.']);
        return;
    }
    if (!require_exchange_ps()) return;
    $result = exchange_set_oof($identity, $state, $internalMsg, $externalMsg);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "OOF auto-reply set to {$state} for '{$identity}'."]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? $result['output'] ?? 'Unknown error']);
    }
}

function handle_mailbox_move_request(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    $targetDb = trim((string)($input['target_database'] ?? ''));
    if ($identity === '' || $targetDb === '') {
        echo json_encode(['success' => false, 'message' => 'Identity and target database are required.']);
        return;
    }
    if (!require_exchange_ps()) return;
    $result = exchange_new_move_request($identity, $targetDb);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Move request created for '{$identity}' to {$targetDb}."]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? $result['output'] ?? 'Unknown error']);
    }
}

function handle_monitoring_transport_rules(): void
{
    if (!require_exchange_ps()) return;
    $result = exchange_get_transport_rules();
    $decoded = $result['decoded'] ?? [];
    $rules = [];
    if (is_array($decoded)) {
        if (isset($decoded['success'])) {
            $rules = $decoded['rules'] ?? [];
        } else {
            $rules = array_is_list($decoded) ? $decoded : [$decoded];
        }
    }
    echo json_encode([
        'success' => !empty($rules) || $result['success'],
        'rules' => $rules,
        'message' => !empty($rules) ? 'Transport rules retrieved.' : ($decoded['message'] ?? $result['output'] ?? 'Failed to retrieve transport rules.'),
    ]);
}

// ===================== P3 Handlers =====================

function handle_mailbox_create_shared(array $input): void
{
    $name = trim((string)($input['name'] ?? ''));
    $alias = trim((string)($input['alias'] ?? ''));
    $displayName = trim((string)($input['display_name'] ?? ''));
    if ($name === '') { echo json_encode(['success' => false, 'message' => 'Name is required.']); return; }
    if (!require_exchange_ps()) return;
    $result = exchange_new_shared_mailbox($name, $alias, $displayName);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Shared mailbox '{$name}' created."]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? $result['output'] ?? 'Unknown error']);
    }
}

function handle_mailbox_create_room(array $input): void
{
    $name = trim((string)($input['name'] ?? ''));
    $alias = trim((string)($input['alias'] ?? ''));
    $capacity = trim((string)($input['capacity'] ?? ''));
    if ($name === '') { echo json_encode(['success' => false, 'message' => 'Name is required.']); return; }
    if (!require_exchange_ps()) return;
    $result = exchange_new_room_mailbox($name, $alias, $capacity);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Room mailbox '{$name}' created."]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? $result['output'] ?? 'Unknown error']);
    }
}

function handle_mailbox_create_equipment(array $input): void
{
    $name = trim((string)($input['name'] ?? ''));
    $alias = trim((string)($input['alias'] ?? ''));
    if ($name === '') { echo json_encode(['success' => false, 'message' => 'Name is required.']); return; }
    if (!require_exchange_ps()) return;
    $result = exchange_new_equipment_mailbox($name, $alias);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Equipment mailbox '{$name}' created."]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? $result['output'] ?? 'Unknown error']);
    }
}

function handle_mailbox_enable_archive(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    $database = trim((string)($input['database'] ?? ''));
    if ($identity === '') { echo json_encode(['success' => false, 'message' => 'Identity is required.']); return; }
    if (!require_exchange_ps()) return;
    $result = exchange_enable_archive($identity, $database);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Archive enabled for '{$identity}'."]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? $result['output'] ?? 'Unknown error']);
    }
}

function handle_mailbox_disable_archive(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    if ($identity === '') { echo json_encode(['success' => false, 'message' => 'Identity is required.']); return; }
    if (!require_exchange_ps()) return;
    $result = exchange_disable_archive($identity);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Archive disabled for '{$identity}'."]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? $result['output'] ?? 'Unknown error']);
    }
}

function handle_mailbox_get_archive(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    if ($identity === '') { echo json_encode(['success' => false, 'message' => 'Identity is required.']); return; }
    if (!require_exchange_ps()) return;
    $result = exchange_get_archive($identity);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'archive' => $decoded['mailbox'] ?? [], 'message' => 'Archive info retrieved.']);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? $result['output'] ?? 'Unknown error']);
    }
}

function handle_mailbox_set_mail_tip(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    $mailTip = trim((string)($input['mail_tip'] ?? ''));
    if ($identity === '') { echo json_encode(['success' => false, 'message' => 'Identity is required.']); return; }
    if (!require_exchange_ps()) return;
    $result = exchange_set_mail_tip($identity, $mailTip);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        $msg = $mailTip ? "Mail tip set for '{$identity}'." : "Mail tip cleared for '{$identity}'.";
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? $result['output'] ?? 'Unknown error']);
    }
}

function handle_mailbox_set_calendar_permissions(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    $user = trim((string)($input['user'] ?? ''));
    $accessRights = trim((string)($input['access_rights'] ?? 'Reviewer'));
    if ($identity === '' || $user === '') { echo json_encode(['success' => false, 'message' => 'Identity and user are required.']); return; }
    if (!require_exchange_ps()) return;
    $result = exchange_set_calendar_permissions($identity, $user, $accessRights);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Calendar permission '{$accessRights}' granted to {$user} for '{$identity}'."]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? $result['output'] ?? 'Unknown error']);
    }
}

function handle_mailbox_remove_calendar_permissions(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    $user = trim((string)($input['user'] ?? ''));
    if ($identity === '' || $user === '') { echo json_encode(['success' => false, 'message' => 'Identity and user are required.']); return; }
    if (!require_exchange_ps()) return;
    $result = exchange_remove_calendar_permissions($identity, $user);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Calendar permission removed for {$user} on '{$identity}'."]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? $result['output'] ?? 'Unknown error']);
    }
}

function handle_mailbox_restore_request(array $input): void
{
    $source = trim((string)($input['source_mailbox'] ?? ''));
    $target = trim((string)($input['target_mailbox'] ?? ''));
    if ($source === '' || $target === '') { echo json_encode(['success' => false, 'message' => 'Source and target mailbox are required.']); return; }
    if (!require_exchange_ps()) return;
    $result = exchange_new_mailbox_restore_request($source, $target, !empty($input['allow_legacy_dn_mismatch']));
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Restore request created from '{$source}' to '{$target}'."]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? $result['output'] ?? 'Unknown error']);
    }
}

function handle_monitoring_retention_policies(): void
{
    if (!require_exchange_ps()) return;
    $result = exchange_get_retention_policies();
    $decoded = $result['decoded'] ?? [];
    $policies = [];
    if (is_array($decoded)) {
        if (isset($decoded['success'])) {
            $policies = $decoded['policies'] ?? [];
        } elseif (array_is_list($decoded)) {
            $policies = $decoded;
        } elseif (isset($decoded['Name']) || isset($decoded['name'])) {
            $policies = [$decoded];
        }
    }
    echo json_encode([
        'success' => !empty($policies) || $result['success'],
        'policies' => $policies,
        'message' => !empty($policies) ? 'Retention policies retrieved.' : ($decoded['message'] ?? $result['output'] ?? 'Failed to retrieve retention policies.'),
    ]);
}

function handle_settings_save(array $input): void
{
    $defaultDb = trim((string)($input['default_database'] ?? ''));
    $defaultQuota = trim((string)($input['default_quota'] ?? '10'));
    $warningQuota = trim((string)($input['warning_quota'] ?? '8'));

    $settings = function_exists('read_vault_config') ? read_vault_config('app_integrations.php') : [];
    if (!isset($settings['exchange'])) {
        $settings['exchange'] = [];
    }
    $settings['exchange']['default_database'] = $defaultDb;
    $settings['exchange']['default_quota'] = $defaultQuota;
    $settings['exchange']['warning_quota'] = $warningQuota;

    if (function_exists('write_vault_config')) {
        $written = write_vault_config('app_integrations.php', $settings);
        if ($written) {
            echo json_encode(['success' => true, 'message' => 'Default policies saved.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to write configuration.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Config vault not available.']);
    }
}
