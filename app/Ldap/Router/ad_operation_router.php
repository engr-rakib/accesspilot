<?php

require_once __DIR__ . '/../../Application/Support/helpers.php';
require_once __DIR__ . '/../../Infrastructure/PowerShell/powershell_runner.php';
require_once __DIR__ . '/../Config/ldap_config_repository.php';
require_once __DIR__ . '/../Connection/ldap_connection_factory.php';
require_once __DIR__ . '/../Operations/ldap_operation_catalog.php';
require_once __DIR__ . '/../Operations/ldap_hub_reports.php';
require_once __DIR__ . '/../Support/ldap_helpers.php';
require_once __DIR__ . '/../../Domain/ActiveDirectory/ad_action_service.php';

if (!function_exists('ad_resolve_backend')) {
    function ad_resolve_backend(string $operation): string
    {
        $ldapConfig = ldap_read_config();

        if (empty($ldapConfig['enabled'])) {
            return 'powershell';
        }

        $backend = strtolower((string) ($ldapConfig['backend'] ?? 'powershell'));
        if ($backend === 'powershell') {
            return 'powershell';
        }

        $readyMap = (array) config_get('ldap_ready', []);
        if (empty($readyMap[$operation])) {
            return 'powershell';
        }

    if ($backend === 'ldap' || $backend === 'auto') {
        if (!ldap_extension_loaded()) {
            return 'powershell';
        }
        return 'ldap';
    }

        return 'powershell';
    }
}

if (!function_exists('ad_powershell_execute_script')) {
    function ad_powershell_execute_script(string $scriptKey, array $parameters = [], array $options = []): array
    {
        return powershell_run_script($scriptKey, $parameters, $options);
    }
}

if (!function_exists('ad_powershell_execute_json_script')) {
    function ad_powershell_execute_json_script(string $scriptKey, array $parameters = [], array $options = []): array
    {
        return powershell_run_json_script($scriptKey, $parameters, $options);
    }
}

if (!function_exists('ad_ldap_execute')) {
    function ad_ldap_execute(string $operation, array $params, string $executedBy): array
    {
        $meta = ldap_operation_meta($operation);
        $handler = $meta['ldap_handler'] ?? null;

        if ($handler !== null && function_exists($handler)) {
            $handlerResult = $handler($params, $executedBy);
            if (!isset($handlerResult['decoded']) && !empty($handlerResult['output'])) {
                $decoded = json_decode((string) $handlerResult['output'], true);
                if (is_array($decoded)) {
                    $handlerResult['decoded'] = $decoded;
                    $handlerResult['json_valid'] = true;
                }
            }

            // Determine effective operation for logging (handle execute_action dispatch)
            $logOperation = $operation;
            if ($operation === 'execute_action') {
                $actionMap = [
                    'enableUser'   => 'enable_user',
                    'disableUser'  => 'disable_user',
                    'unlockUser'   => 'unlock_user',
                    'resetUnlock'  => 'reset_password',
                    'createUser'   => 'create_user',
                    'modifyuser'   => 'modify_user',
                ];
                $action = $params['action'] ?? '';
                $logOperation = $actionMap[$action] ?? $operation;
            }

            $decoded = $handlerResult['decoded'] ?? [];
            $userResults = $decoded['userResults'] ?? null;
            if (is_array($userResults) && count($userResults) > 0) {
                foreach ($userResults as $ur) {
                    $uTarget = $ur['username'] ?? $params['username'] ?? 'unknown';
                    $uSuccess = !empty($ur['success']);
                    $uMessage = $ur['message'] ?? 'LDAP operation completed.';
                    ldap_write_script_log($logOperation, (string) $uTarget, $uSuccess, $uMessage, $executedBy, '');
                }
            } else {
                $targetUser = $decoded['targetUser'] ?? $params['username'] ?? $params['Username'] ?? '';
                $success = !empty($decoded['success']) || !empty($handlerResult['success']);
                $message = $decoded['message'] ?? 'LDAP operation completed.';
                ldap_write_script_log($logOperation, (string) $targetUser, $success, $message, $executedBy, '');
            }

            return $handlerResult;
        }

        return [
            'success' => false,
            'output' => json_encode([
                'success' => false,
                'message' => 'LDAP backend is not implemented for operation: ' . $operation,
            ]),
            'exit_code' => 1,
            'json_valid' => true,
            'decoded' => [
                'success' => false,
                'message' => 'LDAP backend is not implemented for operation: ' . $operation,
            ],
        ];
    }
}

if (!function_exists('ad_execute_json_script')) {
    function ad_execute_json_script(string $operation, string $scriptKey, array $parameters = [], array $options = []): array
    {
        $result = ad_execute_script($operation, $scriptKey, $parameters, $options);

        if (!empty($result['json_valid'])) {
            return [
                'success' => !empty($result['success']),
                'output' => (string) ($result['output'] ?? ''),
                'decoded' => $result['decoded'] ?? json_decode((string) ($result['output'] ?? ''), true),
                'json_valid' => true,
                'exit_code' => (int) ($result['exit_code'] ?? 0),
            ];
        }

        $cleanOutput = trim(is_array($result['output'] ?? null) ? implode("\n", $result['output']) : (string) ($result['output'] ?? ''));
        $decoded = json_decode($cleanOutput, true);
        $isValid = json_last_error() === JSON_ERROR_NONE;

        return [
            'success' => !empty($result['success']) && $isValid,
            'output' => $cleanOutput,
            'decoded' => $isValid ? $decoded : null,
            'json_valid' => $isValid,
            'exit_code' => (int) ($result['exit_code'] ?? 1),
        ];
    }
}

if (!function_exists('ad_execute_script')) {
    function ad_execute_script(string $operation, string $scriptKey, array $parameters = [], array $options = []): array
    {
        $backend = ad_resolve_backend($operation);
        $executedBy = (string) ($parameters['ExecutedBy'] ?? '');

        if ($backend === 'ldap') {
            $ldapParams = array_merge($parameters, ['ExecutedBy' => $executedBy]);
            $ldapResult = ad_ldap_execute($operation, $ldapParams, $executedBy);
            $ldapConfig = ldap_read_config();
            if (($ldapConfig['backend'] ?? '') === 'auto' && empty($ldapResult['success'])) {
                return ad_powershell_execute_script($scriptKey, $parameters, $options);
            }
            return $ldapResult;
        }

        return ad_powershell_execute_script($scriptKey, $parameters, $options);
    }
}

if (!function_exists('ad_execute_action')) {
    function ad_execute_action(string $operation, string $username, string $action, string $authenticatedUser): array
    {
        $backend = ad_resolve_backend($operation);

        if ($backend === 'ldap') {
            $result = ad_ldap_execute($operation, [
                'username' => $username,
                'action' => $action,
                'ExecutedBy' => $authenticatedUser,
            ], $authenticatedUser);

            // If LDAP handler returned pending/not-implemented, fall back to PowerShell
            if ($operation === 'execute_action' && empty($result['success'])
                && function_exists('executeADAction')) {
                $decoded = $result['decoded'] ?? [];
                $msg = $decoded['message'] ?? '';
                if (strpos($msg, 'pending implementation') !== false
                    || strpos($msg, 'not yet implemented') !== false
                    || strpos($msg, 'Unknown or unsupported action') !== false) {
                    return executeADAction($username, $action, $authenticatedUser);
                }
            }

            // For resetUnlock via LDAP, also clear PASSWD_CANT_CHANGE ACE from
            // security descriptor via PowerShell (LDAP UAC flag clear alone
            // may not remove the DENY ACE). Silently ignore failures.
            if ($action === 'resetUnlock' && !empty($result['success'])
                && function_exists('powershell_run_script')) {
                $usernames = array_map('trim', preg_split('/[\s,;]+/', $username, -1, PREG_SPLIT_NO_EMPTY));
                foreach ($usernames as $u) {
                    @powershell_run_script('clearUacFlags', [
                        'Username' => $u,
                    ], ['include_secure_config' => true]);
                }
            }

            return $result;
        }

        return executeADAction($username, $action, $authenticatedUser);
    }
}

if (!function_exists('ad_dispatch_report_operation')) {
    function ad_dispatch_report_operation(string $operation, array $params = [], array $options = []): array
    {
        $catalog = ldap_operation_catalog();
        $meta = $catalog[$operation] ?? null;

        if ($meta === null || empty($meta['ps_script_key'])) {
            return [
                'success' => false,
                'output' => '',
                'exit_code' => 1,
                'json_valid' => false,
                'decoded' => null,
            ];
        }

        $scriptKey = $meta['ps_script_key'];
        $backend = ad_resolve_backend($operation);

        if ($backend === 'ldap') {
            $executedBy = (string) ($params['ExecutedBy'] ?? '');
            $ldapResult = ad_ldap_execute($operation, $params, $executedBy);
            $ldapConfig = ldap_read_config();
            if (($ldapConfig['backend'] ?? '') === 'auto' && empty($ldapResult['success'])) {
                return ad_powershell_execute_script($scriptKey, $params, $options);
            }
            return $ldapResult;
        }

        return ad_powershell_execute_script($scriptKey, $params, $options);
    }
}

if (!function_exists('ad_execute_json_script')) {
    function ad_execute_json_script(string $operation, string $scriptKey, array $parameters = [], array $options = []): array
    {
        $result = ad_execute_script($operation, $scriptKey, $parameters, $options);

        $cleanOutput = trim((string) ($result['output'] ?? ''));
        if ($cleanOutput === '' && !empty($result['decoded']) && is_array($result['decoded'])) {
            $cleanOutput = json_encode($result['decoded'], JSON_UNESCAPED_SLASHES);
        }

        $decoded = $result['decoded'] ?? null;
        if ($decoded === null && $cleanOutput !== '') {
            $decoded = json_decode($cleanOutput, true);
        }

        $isValid = is_array($decoded);

        return [
            'success' => $isValid && (!empty($result['success']) || !empty($decoded['success'])),
            'output' => $cleanOutput,
            'decoded' => $decoded,
            'json_valid' => $isValid,
            'exit_code' => (int) ($result['exit_code'] ?? 1),
        ];
    }
}
