<?php

require_once __DIR__ . '/../Connection/ldap_connection_factory.php';

if (!function_exists('ldap_run_with_connection')) {
    function ldap_run_with_connection(callable $callback)
    {
        $bound = ldap_connect_and_bind();

        try {
            return $callback($bound['connection'], $bound['config']);
        } finally {
            if (is_resource($bound['connection'])) {
                @ldap_unbind($bound['connection']);
            }
        }
    }
}

if (!function_exists('ldap_search_base_dn')) {
    function ldap_search_base_dn(array $config): string
    {
        $scoped = trim((string) ($config['user_search_base'] ?? ''));
        if ($scoped !== '') {
            return $scoped;
        }

        return trim((string) ($config['base_dn'] ?? ''));
    }
}

if (!function_exists('ldap_escape_filter_value')) {
    function ldap_escape_filter_value(string $value): string
    {
        if (function_exists('ldap_escape')) {
            return ldap_escape($value, '', LDAP_ESCAPE_FILTER);
        }

        return str_replace(
            ['\\', '*', '(', ')', "\0"],
            ['\\5c', '\\2a', '\\28', '\\29', '\\00'],
            $value
        );
    }
}

if (!function_exists('ldap_escape_dn_value')) {
    function ldap_escape_dn_value(string $value): string
    {
        $special = ['\\' => '\\5c', ',' => '\\2c', '+' => '\\2b', '"' => '\\22', ';' => '\\3b', '<' => '\\3c', '>' => '\\3e', '=' => '\\3d', "\r" => '\\0d', "\n" => '\\0a'];
        $result = '';
        for ($i = 0; $i < strlen($value); $i++) {
            $ch = $value[$i];
            $result .= $special[$ch] ?? $ch;
        }
        return $result;
    }
}

if (!function_exists('ldap_normalize_entry')) {
    function ldap_normalize_entry(array $entry): array
    {
        $normalized = [];
        foreach ($entry as $key => $value) {
            if (is_int($key)) {
                continue;
            }
            $lower = strtolower((string) $key);
            if ($lower === 'count' || $lower === 'dn') {
                if ($lower === 'dn') {
                    $normalized['dn'] = $value;
                }
                continue;
            }
            if (is_array($value) && isset($value['count'])) {
                $items = [];
                for ($i = 0; $i < (int) $value['count']; $i++) {
                    if (isset($value[$i])) {
                        $items[] = $value[$i];
                    }
                }
                $normalized[$lower] = $items;
            } else {
                $normalized[$lower] = $value;
            }
        }

        return $normalized;
    }
}

if (!function_exists('ldap_resolve_user_for_handler')) {
    function ldap_resolve_user_for_handler($connection, string $baseDn, string &$username, array $attributes): array
    {
        $escaped = ldap_escape_filter_value($username);
        $filter = "(&(objectCategory=person)(objectClass=user)(sAMAccountName={$escaped}))";
        $search = @ldap_search($connection, $baseDn, $filter, $attributes);
        if ($search === false) {
            throw new RuntimeException('LDAP search failed: ' . ldap_error($connection));
        }
        $raw = ldap_get_entries($connection, $search);
        if (is_array($raw) && (int) ($raw['count'] ?? 0) > 0) {
            return $raw;
        }

        // Fallback: prefix wildcard with full input (e.g. "C-13088" -> "*C-13088" matches "fokirC-13088")
        $preEscaped = ldap_escape_filter_value($username);
        $preFilter = "(&(objectCategory=person)(objectClass=user)(sAMAccountName=*{$preEscaped}))";
        $preAttrs = array_values(array_unique(array_merge($attributes, ['samaccountname'])));
        $preSearch = @ldap_search($connection, $baseDn, $preFilter, $preAttrs, 0, 5);
        if ($preSearch !== false) {
            $preRaw = ldap_get_entries($connection, $preSearch);
            if (is_array($preRaw) && (int) ($preRaw['count'] ?? 0) > 0) {
                if ((int) ($preRaw['count']) === 1) {
                    $resolved = $preRaw[0]['samaccountname'][0] ?? '';
                    if ($resolved !== '') {
                        $username = $resolved;
                    }
                    return $preRaw;
                }
                // Multiple matches — pick the one where the number appears as suffix after '-' (only when input has a dash)
                if (str_contains($username, '-') && preg_match('/\d+$/', $username, $preNum)) {
                    $suffix = '-' . $preNum[0];
                    for ($i = 0; $i < (int) ($preRaw['count']); $i++) {
                        $sam = $preRaw[$i]['samaccountname'][0] ?? '';
                        if ($suffix !== '' && substr($sam, -strlen($suffix)) === $suffix) {
                            $preRaw[0] = $preRaw[$i];
                            if ($sam !== '') {
                                $username = $sam;
                            }
                            $preRaw['count'] = '1';
                            unset($preRaw[1]);
                            if (isset($preRaw[2])) unset($preRaw[2], $preRaw[3], $preRaw[4]);
                            return $preRaw;
                        }
                    }
                }
            }
        }

        // Last fallback: numeric-only wildcard (e.g. "13088" -> "*13088*")
        if (preg_match('/\d+$/', $username, $numMatch)) {
            $num = $numMatch[0];
            $wcEscaped = ldap_escape_filter_value($num);
            $wcFilter = "(&(objectCategory=person)(objectClass=user)(sAMAccountName=*{$wcEscaped}*))";
            $wcAttrs = array_values(array_unique(array_merge($attributes, ['samaccountname'])));
            $wcSearch = @ldap_search($connection, $baseDn, $wcFilter, $wcAttrs, 0, 5);
            if ($wcSearch !== false) {
                $wcRaw = ldap_get_entries($connection, $wcSearch);
                if (is_array($wcRaw) && (int) ($wcRaw['count'] ?? 0) > 0) {
                    if ((int) ($wcRaw['count']) === 1) {
                        $resolved = $wcRaw[0]['samaccountname'][0] ?? '';
                        if ($resolved !== '') {
                            $username = $resolved;
                        }
                        return $wcRaw;
                    }
                    // Multiple matches — pick the one ending with -{number} (only when input has a dash)
                    if (str_contains($username, '-')) {
                        $suffix = '-' . $num;
                        for ($i = 0; $i < (int) ($wcRaw['count']); $i++) {
                            $sam = $wcRaw[$i]['samaccountname'][0] ?? '';
                            if (substr($sam, -strlen($suffix)) === $suffix) {
                                $wcRaw[0] = $wcRaw[$i];
                                if ($sam !== '') {
                                    $username = $sam;
                                }
                                $wcRaw['count'] = '1';
                                unset($wcRaw[1]);
                                if (isset($wcRaw[2])) unset($wcRaw[2], $wcRaw[3], $wcRaw[4]);
                                return $wcRaw;
                            }
                        }
                    }
                }
            }
        }

        return [];
    }
}

if (!function_exists('ldap_extract_name_part')) {
    function ldap_extract_name_part(array $filtered, array $allParts, string $mode, string $default = '', string $empCode = ''): string
    {
        if ($mode === 'emp_code') return $empCode ?: $default;
        if ($mode === 'emp_code_idx0_idx1') return trim(($empCode ?: $default) . ' ' . ($filtered[0] ?? '') . ' ' . ($filtered[1] ?? ''));
        $source = str_contains($mode, 'index:') ? $allParts : $filtered;
        return match (true) {
            $mode === 'first_non_prefix' => $filtered[0] ?? $allParts[0] ?? $default,
            $mode === 'first_part'       => $allParts[0] ?? $default,
            $mode === 'last_non_prefix'  => $filtered[array_key_last($filtered)] ?? $allParts[array_key_last($allParts)] ?? $default,
            $mode === 'last_part'        => $allParts[array_key_last($allParts)] ?? $default,
            $mode === 'after_given_name' => implode(' ', array_slice($filtered, 1)),
            default                      => (preg_match('/^index:(\d+)$/', $mode, $m))
                ? ($source[(int) $m[1]] ?? $source[0] ?? $default)
                : ($filtered[0] ?? $allParts[0] ?? $default),
        };
    }
}

if (!function_exists('ldap_parse_full_name')) {
    function ldap_parse_full_name(string $fullName, array $namingConfig = [], string $empCode = ''): array
    {
        $exclude = array_map('trim', $namingConfig['exclude_prefixes'] ?? []);
        $exclude = array_map('strtolower', $exclude);

        $parts = preg_split('/[\s.]+/', $fullName);
        $filtered = [];
        $prefixSkipped = false;

        foreach ($parts as $p) {
            $clean = trim($p);
            if ($clean === '') continue;
            if (!empty($exclude) && in_array(strtolower($clean), $exclude, true)) {
                $prefixSkipped = true;
                continue;
            }
            $filtered[] = $clean;
        }
        $filtered = array_values($filtered);

        if (empty($filtered)) {
            $filtered = array_values(array_filter(array_map('trim', $parts), function ($v) { return $v !== ''; }));
        }

        $givenNameMode = $namingConfig['given_name_mode'] ?? 'first_non_prefix';
        $surnameMode = $namingConfig['surname_mode'] ?? 'last_part';
        $displayNameFormat = $namingConfig['display_name_format'] ?? 'original';

        $givenName = ldap_extract_name_part($filtered, $parts, $givenNameMode, $fullName, $empCode);
        $surname = ldap_extract_name_part($filtered, $parts, $surnameMode, '', $empCode);

        $displayName = match ($displayNameFormat) {
            'first_last' => trim($givenName . ' ' . $surname),
            'last_first' => trim($surname . ', ' . $givenName),
            default      => $fullName,
        };

        return [
            'given_name' => $givenName,
            'surname' => $surname,
            'display_name' => $displayName,
            'all_parts' => $filtered,
            'prefix_skipped' => $prefixSkipped,
        ];
    }
}

if (!function_exists('ldap_generate_username_from_name')) {
    function ldap_generate_username_from_name(string $fullName, string $empCode, array $namingConfig): string
    {
        $mode = $namingConfig['mode'] ?? 'emp_code';
        if ($mode === 'emp_code') {
            return ldap_clamp_sam_account_name($empCode, $empCode);
        }

        $parsed = ldap_parse_full_name($fullName, $namingConfig, $empCode);
        $filtered = $parsed['all_parts'];

        if (empty($filtered)) {
            return ldap_clamp_sam_account_name($empCode, $empCode);
        }

        $case = $namingConfig['case'] ?? 'lowercase';
        $separator = $namingConfig['separator'] ?? '';

        switch ($mode) {
            case 'first_non_prefix_id':
                $base = $filtered[0];
                break;
            case 'last_name_id':
                $base = end($filtered);
                break;
            case 'full_name_slug_id':
                $base = implode($separator, $filtered);
                break;
            default:
                if (preg_match('/^index:(\d+)_id$/', $mode, $m)) {
                    $idx = (int) $m[1];
                    $base = $filtered[$idx] ?? $filtered[0];
                } else {
                    $base = $empCode;
                }
        }

        $username = match ($case) {
            'uppercase' => strtoupper($base),
            'as_is'     => $base,
            default     => strtolower($base),
        };

        // AD limits sAMAccountName to 20 characters. Always keep the empCode
        // suffix intact (uniqueness anchor) and trim the name-derived prefix
        // down to fit; a >20-char SAM is rejected by AD with a generic
        // "Other (implementation specific)" / 0x523 Invalid argument error.
        return ldap_clamp_sam_account_name($username . $empCode, $empCode);
    }
}

if (!function_exists('ldap_clamp_sam_account_name')) {
    function ldap_clamp_sam_account_name(string $value, string $empCode = ''): string
    {
        // AD limits sAMAccountName to 20 characters. A longer value is rejected
        // by AD with a generic "Other (e.g., implementation specific) error"
        // (extended 00000523 / problem 22 Invalid argument).
        $max = 20;
        if (strlen($value) <= $max) {
            return $value;
        }

        // Keep the empCode suffix intact where provided, trim the leading slug.
        $code = (string) $empCode;
        if ($code !== '' && strlen($code) < $max) {
            $remaining = $max - strlen($code);
            if (strlen($value) > $remaining) {
                $value = substr($value, 0, $remaining);
            }
            return $value . $code;
        }

        return substr($value, 0, $max);
    }
}

if (!function_exists('ldap_first_attr')) {
    function ldap_first_attr(array $entry, string $attribute, $default = '')
    {
        $key = strtolower($attribute);
        if (!isset($entry[$key])) {
            return $default;
        }
        $value = $entry[$key];
        if (is_array($value)) {
            return $value[0] ?? $default;
        }

        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('ldap_attr_all')) {
    function ldap_attr_all(array $entry, string $attribute): array
    {
        $key = strtolower($attribute);
        if (!isset($entry[$key]) || !is_array($entry[$key])) {
            return [];
        }

        return array_values($entry[$key]);
    }
}

if (!function_exists('ldap_parse_ou_from_dn')) {
    function ldap_parse_ou_from_dn(string $dn): string
    {
        if ($dn === '') {
            return '';
        }
        // Extract the first (closest to leaf) OU value from DN
        if (preg_match('/^CN=[^,]*,OU=([^,]+)/i', $dn, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }
}

if (!function_exists('ldap_parent_dn')) {
    function ldap_parent_dn(string $dn): ?string
    {
        if (!preg_match('/^[^,]+,(.+)$/i', $dn, $matches)) {
            return null;
        }

        return $matches[1];
    }
}

if (!function_exists('ldap_convert_nt_time')) {
    function ldap_convert_nt_time(string|int $ntTime): string
    {
        $val = (int) $ntTime;
        if ($val === 0 || $val === 9223372036854775807) {
            return 'Never';
        }

        $unix = ($val / 10000000) - 11644473600;
        if ($unix <= 0) {
            return 'Never';
        }

        return date('d-m-Y h:i A', (int) $unix);
    }
}

if (!function_exists('ldap_convert_generalized_time')) {
    function ldap_convert_generalized_time(string $time): string
    {
        // LDAP GeneralizedTime format: YYYYMMDDHHMMSS.0Z
        if (preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $time, $m)) {
            $timestamp = mktime((int)$m[4], (int)$m[5], (int)$m[6], (int)$m[2], (int)$m[3], (int)$m[1]);
            if ($timestamp > 0) {
                return date('d-m-Y h:i A', $timestamp);
            }
        }
        return $time;
    }
}

if (!function_exists('ldap_decode_uac_status')) {
    function ldap_decode_uac_status(int $uac): string
    {
        $flags = [];
        if ($uac & 2) $flags[] = 'ACCOUNTDISABLE';
        if (($uac & 2) === 0) $flags[] = 'ENABLED';
        if ($uac & 32) $flags[] = 'PASSWD_NOTREQD';
        if ($uac & 64) $flags[] = 'PASSWD_CANT_CHANGE';
        if ($uac & 65536) $flags[] = 'DONT_EXPIRE_PASSWD';
        if ($uac & 1048576) $flags[] = 'PASSWD_EXPIRED';
        if ($uac & 4194304) $flags[] = 'SMARTCARD_REQUIRED';
        return implode(', ', $flags) ?: 'Normal';
    }
}

if (!function_exists('ldap_decode_lockout_status')) {
    function ldap_decode_lockout_status(string|int $lockoutTime): string
    {
        return ((int) $lockoutTime) === 0 ? 'Unlocked' : 'Locked';
    }
}

if (!function_exists('ldap_decode_password_status')) {
    function ldap_decode_password_status(int $uac, string|int $pwdLastSet): string
    {
        if ($uac & 1048576) return 'Expired';
        if ($uac & 32) return 'Not Required';
        if ((int) $pwdLastSet === 0) return 'Must Change at Next Logon';
        return 'Valid';
    }
}

if (!function_exists('ldap_json_script_result')) {
    function ldap_json_script_result(array $payload, bool $success = true, int $exitCode = 0): array
    {
        return [
            'success' => $success,
            'output' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            'exit_code' => $exitCode,
            'json_valid' => true,
            'decoded' => $payload,
        ];
    }
}

if (!function_exists('ldap_ad_action_summary')) {
    function ldap_ad_action_summary(int $processed = 1, int $success = 1, int $skipped = 0, int $failed = 0): string
    {
        return '>> Processed: ' . $processed . ' | Success: ' . $success . ' | Skipped: ' . $skipped . ' | Failed: ' . $failed . ' <<';
    }
}

if (!function_exists('ldap_ad_action_message')) {
    function ldap_ad_action_message(string $detail, bool $isSuccess = true, int $processed = 1, int $success = 1, int $skipped = 0, int $failed = 0): string
    {
        $prefix = $isSuccess ? 'SUCCESS: ' : 'ERROR: ';
        return $prefix . $detail . "\n\n" . ldap_ad_action_summary($processed, $success, $skipped, $failed);
    }
}

if (!function_exists('ldap_feedback_message')) {
    function ldap_feedback_message(string $badge, int $processed, int $successCount, int $failedCount, int $skippedCount = 0): string
    {
        $summary = 'Processed: ' . $processed . ' | Success: ' . $successCount . ' | Skipped: ' . $skippedCount . ' | Failed: ' . $failedCount;
        return $badge . "\n\n" . $summary;
    }
}

if (!function_exists('ldap_paged_search')) {
    function ldap_paged_search($connection, string $baseDn, string $filter, array $attributes, int $pageSize = 500): array
    {
        $entries = [];
        $cookie = '';

        if (!defined('LDAP_CONTROL_PAGEDRESULTS')) {
            $search = @ldap_search($connection, $baseDn, $filter, $attributes);
            if ($search === false) {
                throw new RuntimeException('LDAP search failed: ' . ldap_error($connection));
            }
            $raw = ldap_get_entries($connection, $search);
            if (!is_array($raw)) {
                return [];
            }
            for ($i = 0; $i < (int) ($raw['count'] ?? 0); $i++) {
                $entries[] = ldap_normalize_entry($raw[$i]);
            }
            return $entries;
        }

        do {
            $controls = [['oid' => LDAP_CONTROL_PAGEDRESULTS, 'value' => ['size' => $pageSize, 'cookie' => $cookie]]];
            $search = @ldap_search($connection, $baseDn, $filter, $attributes, 0, 0, 0, LDAP_DEREF_NEVER, $controls);
            if ($search === false) {
                throw new RuntimeException('LDAP search failed: ' . ldap_error($connection));
            }

            ldap_parse_result($connection, $search, $errcode, $matched, $errmsg, $referrals, $responseControls);

            $raw = ldap_get_entries($connection, $search);
            if (is_array($raw)) {
                for ($i = 0; $i < (int) ($raw['count'] ?? 0); $i++) {
                    $entries[] = ldap_normalize_entry($raw[$i]);
                }
            }

            $cookie = '';
            if (isset($responseControls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'])) {
                $cookie = (string) $responseControls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'];
            }
        } while ($cookie !== '');

        return $entries;
    }
}

if (!function_exists('ldap_script_log_category')) {
    function ldap_script_log_category(string $operation): string
    {
        $map = [
            'reset_password'   => 'PassReset',
            'unlock_user'      => 'unlock',
            'enable_user'      => 'UserEnable',
            'disable_user'     => 'UserDisable',
            'modify_user'      => 'UserModify',
            'create_user'      => 'NewUser',
            'set_group_members' => 'GroupMgmt',
            'create_directory_object' => 'Ou&Grp_mgt',
            'delete_directory_object' => 'Ou&Grp_mgt',
            'get_user_info'    => 'UserInfo',
            'export_hrms_ad_user_id' => 'FindLogonID',
            'get_ad_hrms_status'    => 'EmpStsChk',
            'ou_group_user_report'  => 'user_export',
            'user_report'           => 'UserReport',
            'ad_health_check'       => 'HealthCheck',
            'settings_save'         => 'ExchangeSettings',
        ];
        if (isset($map[$operation])) {
            return $map[$operation];
        }
        if (str_starts_with($operation, 'mailbox_')) {
            return 'ExchangeMailbox';
        }
        if (str_starts_with($operation, 'group_')) {
            return 'ExchangeGroup';
        }
        return 'General';
    }
}

if (!function_exists('ldap_script_log_action')) {
    function ldap_script_log_action(string $operation, string $action = ''): string
    {
        if ($action !== '') {
            return $action;
        }
        $map = [
            'reset_password'   => 'U&RESET',
            'unlock_user'      => 'UNLOCK',
            'enable_user'      => 'ENABLE',
            'disable_user'     => 'DISABLE',
            'modify_user'      => 'MODIFY',
            'create_user'      => 'CREATE',
            'set_group_members' => 'grp_m.mgt',
            'delete_directory_object' => 'DELETE_OBJECT',
            'export_hrms_ad_user_id' => 'LOGONID',
            'get_ad_hrms_status'    => 'STS_CHK',
            'user_report'           => 'USER_REPORT',
            'ad_health_check'       => 'HEALTH',
            'mailbox_enable'                => 'MBX_ENABLE',
            'mailbox_disable'               => 'MBX_DISABLE',
            'mailbox_user_create'           => 'MBX_USER_CREATE',
            'mailbox_create_shared'         => 'MBX_SHARED',
            'mailbox_create_room'           => 'MBX_ROOM',
            'mailbox_create_equipment'      => 'MBX_EQUIP',
            'mailbox_set_quota'             => 'MBX_QUOTA',
            'mailbox_set_forward'           => 'MBX_FWD',
            'mailbox_set_primary_smtp'      => 'MBX_PRI_SMTP',
            'mailbox_add_address'           => 'MBX_ADD_ADDR',
            'mailbox_remove_address'        => 'MBX_REM_ADDR',
            'mailbox_add_full_access'       => 'MBX_FULL_ACCESS',
            'mailbox_remove_full_access'    => 'MBX_REM_FULL_ACCESS',
            'mailbox_add_send_as'           => 'MBX_SEND_AS',
            'mailbox_remove_send_as'        => 'MBX_REM_SEND_AS',
            'mailbox_set_litigation_hold'   => 'MBX_LIT_HOLD',
            'mailbox_set_hidden_gal'        => 'MBX_HID_GAL',
            'mailbox_update_profile'        => 'MBX_UPD_PROFILE',
            'mailbox_set_oof'               => 'MBX_OOF',
            'mailbox_move_request'          => 'MBX_MOVE',
            'mailbox_enable_archive'        => 'MBX_ARCH_ON',
            'mailbox_disable_archive'       => 'MBX_ARCH_OFF',
            'mailbox_get_archive'           => 'MBX_ARCH_GET',
            'mailbox_set_mail_tip'          => 'MBX_MAIL_TIP',
            'mailbox_set_calendar_permissions'   => 'MBX_CAL_PERM',
            'mailbox_remove_calendar_permissions' => 'MBX_REM_CAL_PERM',
            'mailbox_restore_request'       => 'MBX_RESTORE',
            'group_create'                  => 'GRP_CREATE',
            'group_add_member'              => 'GRP_ADD_MEM',
            'group_remove_member'           => 'GRP_REM_MEM',
            'group_delete'                  => 'GRP_DELETE',
            'group_search'                  => 'GRP_SEARCH',
            'group_members'                 => 'GRP_MEMBERS',
            'settings_save'                 => 'SETTINGS',
        ];
        return $map[$operation] ?? strtoupper($operation);
    }
}

if (!function_exists('ldap_feedback_troubleshoot')) {
    function ldap_feedback_troubleshoot(string $operation, ?array $result, array $context = []): array
    {
        $output = trim((string) ($result['output'] ?? ''));
        $exitCode = (int) ($result['exit_code'] ?? -1);
        $cat = ldap_operation_catalog();
        $meta = $cat[$operation] ?? [];
        $scriptKey = $meta['ps_script_key'] ?? 'unknown';
        $username = (string) ($context['username'] ?? $context['Usernames'] ?? '');

        $lines = $output !== '' ? explode("\n", $output) : [];
        $firstLine = $lines[0] ?? '';
        $hasHeaderCsv = preg_match('/EMP_ID|Username|SamAccountName|Name/i', $firstLine) > 0;
        $hasDataRows = count($lines) > 1 && trim($lines[1] ?? '') !== '';
        $isJson = $firstLine !== '' && ($firstLine[0] === '{' || $firstLine[0] === '[');

        $parsedError = '';
        if ($isJson) {
            $decoded = json_decode($output, true);
            $parsedError = $decoded['message'] ?? $decoded['error'] ?? '';
        }

        $issue = '';
        $reason = '';
        $suggestion = '';
        $rootCause = '';

        if ($exitCode !== 0) {
            $errorDetail = !empty($parsedError) ? $parsedError : $output;
            $issue = "Script failed with exit code {$exitCode}";
            $reason = !empty($parsedError)
                ? "The script returned: {$parsedError}"
                : 'The script exited with a non-zero code but produced no stdout output. This usually means it failed before any output command.';
            if ($output === '') {
                $suggestion = 'The script produced no stdout output. Possible causes:'
                    . ' (1) PowerShell.exe not in the IIS AppPool PATH — try using the full path to powershell.exe in config/powershell.php,'
                    . ' (2) Secure config XML was encrypted under a different Windows user — re-encrypt it under the IIS AppPool identity,'
                    . ' (3) The script file lacks read permissions for the IIS AppPool user,'
                    . ' (4) PowerShell execution policy blocks the script despite -ExecutionPolicy Bypass.'
                    . ' Check IIS Application event logs and run the script manually from a Command Prompt as the AppPool identity to see the actual error.';
            } else {
                $suggestion = 'Check the Raw Output below. Verify: (1) PowerShell script path exists in config/powershell.php, (2) Secure config XML is valid at the configured path, (3) AD and HRMS API are reachable.';
            }
            $rootCause = $errorDetail;
        } elseif ($output === '' || $output === null) {
            $issue = 'No output received from the operation.';
            $reason = 'The script produced no stdout output. Possible causes: script not found, empty parameter set, or silent failure.';
            $suggestion = "Verify the script path for '{$scriptKey}' exists in config/powershell.php. Verify the input parameter '{$username}' reaches the PowerShell script. Enable debug logging to trace execution.";
            $rootCause = "Script key: {$scriptKey}, Exit code: {$exitCode}, Output length: 0";
        } elseif ($isJson) {
            $errMsg = $decoded['message'] ?? $decoded['error'] ?? '';
            $issue = !empty($errMsg) ? $errMsg : 'Operation returned a JSON response with errors.';
            $reason = 'The script trapped an error and returned it as structured JSON.';
            $suggestion = 'Review the JSON output below for specific error messages. If the issue is related to secure config, verify the configuration XML is valid.';
            $rootCause = "JSON output: " . substr($output, 0, 500);
        } elseif ($hasHeaderCsv && !$hasDataRows) {
            $issue = 'No data rows found in the report.';
            $reason = 'The operation completed successfully but produced only column headers with zero data rows. The search criteria returned no matching records.';
            $suggestion = "Verify the input value '{$username}' is correct and matches existing records. Check HRMS API availability and AD connectivity. Try a different employee ID or group name.";
            $rootCause = "CSV header present, 0 data rows. Exit code: {$exitCode}";
        } elseif (!$hasHeaderCsv && count($lines) > 0) {
            $issue = 'Unexpected output format.';
            $reason = 'The script output does not match the expected CSV or JSON structure.';
            $suggestion = 'Review the raw output below. This may indicate a script error, version mismatch, or missing PowerShell modules.';
            $rootCause = "Output format unexpected. First line: " . substr($firstLine, 0, 200);
        } else {
            $issue = 'Operation completed with an unknown result.';
            $reason = 'The result does not match any known success or failure pattern.';
            $suggestion = 'Inspect the raw output below and consult the script logs for details.';
            $rootCause = "Exit code: {$exitCode}, Output length: " . strlen($output);
        }

        $parts = [
            "Issue: {$issue}",
            "Reason: {$reason}",
            "Suggestion: {$suggestion}",
        ];
        $messageHtml = '<div style="font-weight:600;font-size:1.05em;margin-bottom:4px;">' . htmlspecialchars($issue) . '</div>'
            . '<div style="margin-bottom:2px;"><strong>Reason:</strong> ' . htmlspecialchars($reason) . '</div>'
            . '<div style="margin-bottom:2px;"><strong>Suggestion:</strong> ' . htmlspecialchars($suggestion) . '</div>';

        $errorDetails = '';
        if (!empty($rootCause)) {
            $errorDetails .= '<div style="margin-top:4px;"><strong>Root Cause:</strong> ' . htmlspecialchars($rootCause) . "</div>";
        }
        if (!empty($username)) {
            $errorDetails .= '<div><strong>Input:</strong> ' . htmlspecialchars($username) . "</div>";
        }
        $errorDetails .= '<div><strong>Script:</strong> ' . $scriptKey . ' | <strong>Exit Code:</strong> ' . $exitCode . "</div>";
        if ($output !== '') {
            $truncated = strlen($output) > 600 ? substr($output, 0, 600) . '...' : $output;
            $errorDetails .= '<div><strong>Raw Output:</strong></div>'
                . '<pre style="background:#f5f5f5;padding:6px;border-radius:4px;font-size:0.85em;max-height:200px;overflow:auto;white-space:pre-wrap;word-break:break-all;margin:4px 0 0 0;">'
                . htmlspecialchars($truncated) . '</pre>';
        } else {
            $errorDetails .= '<div><em>No stdout output captured. The script likely failed before any output (permissions, script not found, or execution policy). Check PowerShell stderr via IIS event logs or run the script manually.</em></div>';
        }
        $messageHtml .= '<hr style="margin:8px 0;border:0;border-top:1px solid rgba(0,0,0,0.1);"><div style="font-weight:600;margin-bottom:4px;">Debug Info</div>' . $errorDetails;

        return [
            'success' => false,
            'issue' => $issue,
            'reason' => $reason,
            'suggestion' => $suggestion,
            'root_cause' => $rootCause,
            'error_details' => $errorDetails,
            'message' => $messageHtml,
            'raw_output' => $output,
            'exit_code' => $exitCode,
        ];
    }
}

if (!function_exists('ldap_write_script_log')) {
    function ldap_write_script_log(string $operation, string $targetUser, bool $success, string $message, string $executedBy, string $action = ''): void
    {
        $tz = 'Asia/Dhaka';
        $now = new DateTime('now', new DateTimeZone($tz));
        $baseLogPath = function_exists('get_external_log_base') ? get_external_log_base() : 'C:/access_pilot_logs';
        $category = ldap_script_log_category($operation);
        $pathMap = [
            'NewUser'       => 'User_Management/NewUser',
            'ManualCreate'  => 'User_Management/ManualCreate',
            'PassReset'     => 'User_Management/PassReset',
            'unlock'        => 'User_Management/unlock',
            'UserDisable'   => 'User_Management/UserDisable',
            'UserEnable'    => 'User_Management/UserEnable',
            'UserModify'    => 'User_Management/UserModify',
            'UserInfo'      => 'User_Management/UserInfo',
            'Ou&Grp_mgt'    => 'Directory_Services/Ou_Group_Mgt',
            'GroupMgmt'     => 'Directory_Services/GroupMgmt',
            'EmpStsChk'     => 'Integration/EmpStsChk',
            'FindLogonID'   => 'Integration/FindLogonID',
            'user_export'   => 'Integration/user_export',
            'HealthCheck'   => 'HealthCheck',
            'ExchangeMailbox'   => 'Exchange/Mailbox',
            'ExchangeGroup'     => 'Exchange/Group',
            'ExchangeSettings'  => 'Exchange/Settings',
        ];
        $relativePath = $pathMap[$category] ?? $category;
        $adName = function_exists('ldap_active_domain_ad_name') ? ldap_active_domain_ad_name() : '';
        $activeDomain = $adName !== '' ? $adName : (function_exists('ldap_active_domain_key') ? ldap_active_domain_key() : 'default');
        $logDir = rtrim($baseLogPath, '/\\') . DIRECTORY_SEPARATOR . $activeDomain . DIRECTORY_SEPARATOR . 'scripts_logs' . DIRECTORY_SEPARATOR . $relativePath;
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $logFile = $logDir . DIRECTORY_SEPARATOR . 'audit-' . $now->format('Y-m-d') . '.log';
        $timestamp = $now->format('Y-m-d h:i:s A');
        $status = $success ? 'SUCCESS' : 'FAILED';
        $logAction = ldap_script_log_action($operation, $action);
        $cleanMessage = explode("\n\n", $message)[0];
        $cleanMessage = preg_replace('/^(SUCCESS|ERROR|FAILED|WARN):\s*/i', '', $cleanMessage);
        $cleanMessage = str_replace(["\r\n", "\n", "\r"], ' | ', $cleanMessage);
        $logEntry = "[{$timestamp}] Action: {$logAction} | TargetUser: {$targetUser} | Status: {$status} | Message: {$cleanMessage} | ExecutedBy: {$executedBy}" . PHP_EOL;
        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('ldap_write_transcript_log')) {
    function ldap_write_transcript_log(string $message, string $targetUser = ''): void
    {
        $tz = 'Asia/Dhaka';
        $now = new DateTime('now', new DateTimeZone($tz));
        $baseLogPath = function_exists('get_external_log_base') ? get_external_log_base() : 'C:/access_pilot_logs';
        $adName = function_exists('ldap_active_domain_ad_name') ? ldap_active_domain_ad_name() : '';
        $activeDomain = $adName !== '' ? $adName : (function_exists('ldap_active_domain_key') ? ldap_active_domain_key() : 'default');
        $transcriptDir = rtrim($baseLogPath, '/\\') . DIRECTORY_SEPARATOR . $activeDomain
            . DIRECTORY_SEPARATOR . 'scripts_logs' . DIRECTORY_SEPARATOR . 'User_Management' . DIRECTORY_SEPARATOR . 'NewUser'
            . DIRECTORY_SEPARATOR . 'New_user_transcript_logs';
        if (!is_dir($transcriptDir)) {
            @mkdir($transcriptDir, 0775, true);
        }
        $transcriptFile = $transcriptDir . DIRECTORY_SEPARATOR . 'audit-' . $now->format('Y-m-d') . '.log';
        $timestamp = $now->format('Y-m-d h:i:s A');
        $target = $targetUser !== '' ? " [User: {$targetUser}]" : '';
        $cleanMsg = str_replace(["\r\n", "\n", "\r"], ' | ', $message);
        $entry = "[{$timestamp}] {$cleanMsg}{$target}" . PHP_EOL;
        @file_put_contents($transcriptFile, $entry, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('ldap_get_upn_suffixes')) {
    function ldap_get_upn_suffixes($connection, array $config = []): array
    {
        $suffixes = [];
        $configNc = '';

        // Extract domain NC (DC= parts) from base_dn
        $baseDn = $config['base_dn'] ?? '';
        $dcParts = array_filter(explode(',', $baseDn), function ($p) {
            return stripos(trim($p), 'DC=') === 0;
        });
        $domainDns = !empty($dcParts)
            ? implode('.', array_map(function ($p) { return substr(trim($p), 3); }, $dcParts))
            : '';

        if ($domainDns !== '') {
            $suffixes[] = $domainDns;
            // AD always uses CN=Configuration,<domainNC>
            $domainNc = implode(',', $dcParts);
            $configNc = 'CN=Configuration,' . $domainNc;
        }

        // Try root DSE as fallback to find configurationNamingContext
        if ($configNc === '') {
            $search = @ldap_read($connection, '', '(objectClass=*)', ['configurationNamingContext'], 0, 0, 0);
            if ($search === false) {
                $search = @ldap_read($connection, ' ', '(objectClass=*)', ['configurationNamingContext'], 0, 0, 0);
            }
            if ($search !== false) {
                $entries = ldap_get_entries($connection, $search);
                $configNc = $entries[0]['configurationnamingcontext'][0] ?? '';
            }
        }

        // Read uPNSuffixes from CN=Partitions in Configuration NC
        if ($configNc !== '') {
            $partitionsDn = 'CN=Partitions,' . $configNc;
            $partSearch = @ldap_read($connection, $partitionsDn, '(objectClass=*)', ['uPNSuffixes'], 0, 0, 0);
            if ($partSearch !== false) {
                $partEntries = ldap_get_entries($connection, $partSearch);
                if (isset($partEntries[0]['upnsuffixes'])) {
                    $count = (int) ($partEntries[0]['upnsuffixes']['count'] ?? 0);
                    for ($i = 0; $i < $count; $i++) {
                        $suffix = trim($partEntries[0]['upnsuffixes'][$i]);
                        if ($suffix !== '' && !in_array($suffix, $suffixes, true)) {
                            $suffixes[] = $suffix;
                        }
                    }
                }
            }
        }

        return array_values($suffixes);
    }
}

if (!function_exists('ldap_resolve_principal_display')) {
    function ldap_resolve_principal_display($connection, string $dn): string
    {
        $read = @ldap_read($connection, $dn, '(objectClass=*)', ['cn', 'displayname', 'employeeid', 'name', 'objectclass'], 0, 0, 0);
        if ($read === false) {
            if (preg_match('/^CN=([^,]+)/i', $dn, $m)) {
                return "'{$m[1]}'";
            }
            return "'{$dn}'";
        }
        $entries = ldap_get_entries($connection, $read);
        if (!is_array($entries) || (int) ($entries['count'] ?? 0) === 0) {
            if (preg_match('/^CN=([^,]+)/i', $dn, $m)) {
                return "'{$m[1]}'";
            }
            return "'{$dn}'";
        }
        $entry = $entries[0];
        $cn = '';
        if (isset($entry['cn'][0])) {
            $cn = $entry['cn'][0];
        }
        $isUser = false;
        if (isset($entry['objectclass'])) {
            $count = (int) ($entry['objectclass']['count'] ?? 0);
            for ($i = 0; $i < $count; $i++) {
                if (strtolower($entry['objectclass'][$i]) === 'user') {
                    $isUser = true;
                    break;
                }
            }
        }
        if ($isUser) {
            $eid = $entry['employeeid'][0] ?? '';
            $displayName = $entry['displayname'][0] ?? $entry['name'][0] ?? $cn;
            if ($eid !== '') {
                return "User id '{$eid}' name '{$displayName}'";
            }
            return "'{$displayName}'";
        }
        $groupName = $entry['name'][0] ?? $entry['cn'][0] ?? $cn;
        return "'{$groupName}'";
    }
}

if (!function_exists('ldap_exchange_discover_servers')) {
    function ldap_exchange_discover_servers($connection, string $baseDn): array
    {
        $configNc = 'CN=Configuration,' . $baseDn;
        $filter = '(objectClass=msExchExchangeServer)';
        $attrs = ['name', 'networkaddress', 'msexchexchangeversion', 'msexchcurrentserverroles'];
        $search = @ldap_search($connection, $configNc, $filter, $attrs, 0, 0, 0);
        if ($search === false) {
            return [];
        }
        $entries = ldap_get_entries($connection, $search);
        $servers = [];
        $count = (int) ($entries['count'] ?? 0);
        for ($i = 0; $i < $count; $i++) {
            $fqdn = '';
            $networkCount = (int)($entries[$i]['networkaddress']['count'] ?? 0);
            for ($n = 0; $n < $networkCount; $n++) {
                $network = (string)($entries[$i]['networkaddress'][$n] ?? '');
                if (preg_match('/ncacn_ip_tcp:([^\\]]+)/i', $network, $m)) {
                    $fqdn = $m[1];
                    break;
                }
            }
            $servers[] = [
                'name' => $entries[$i]['name'][0] ?? '',
                'fqdn' => $fqdn,
                'version' => $entries[$i]['msexchexchangeversion'][0] ?? '',
                'role' => $entries[$i]['msexchcurrentserverroles'][0] ?? '',
            ];
        }
        return $servers;
    }
}

if (!function_exists('ldap_exchange_active_domain_config')) {
    function ldap_exchange_active_domain_config(): array
    {
        if (!function_exists('ldap_read_config')) {
            return [];
        }
        $ldapConfig = ldap_read_config();
        if (!is_array($ldapConfig)) {
            return [];
        }
        $exchange = $ldapConfig['exchange'] ?? [];
        if (!is_array($exchange)) {
            return [];
        }
        return $exchange;
    }
}

if (!function_exists('ldap_exchange_get_databases')) {
    function ldap_exchange_get_databases($connection, string $baseDn): array
    {
        $configNc = 'CN=Configuration,' . $baseDn;
        $filter = '(objectClass=msExchMDB)';
        $attrs = ['cn', 'name', 'description', 'msexchprivateserver'];
        $search = @ldap_search($connection, $configNc, $filter, $attrs, 0, 0, 0);
        if ($search === false) {
            return [];
        }
        $entries = ldap_get_entries($connection, $search);
        $databases = [];
        $count = (int) ($entries['count'] ?? 0);
        for ($i = 0; $i < $count; $i++) {
            $serverDn = $entries[$i]['msexchprivateserver'][0] ?? '';
            $serverName = $serverDn;
            if (preg_match('/CN=([^,]+)/i', $serverDn, $m)) {
                $serverName = $m[1];
            }
            $databases[] = [
                'name' => $entries[$i]['cn'][0] ?? $entries[$i]['name'][0] ?? '',
                'server' => $serverName,
                'server_dn' => $serverDn,
                'description' => $entries[$i]['description'][0] ?? '',
            ];
        }
        return $databases;
    }
}

if (!function_exists('ldap_exchange_estimate_mailbox_count')) {
    function ldap_exchange_estimate_mailbox_count($connection, string $baseDn): int
    {
        $filter = '(&(objectClass=user)(objectClass=organizationalPerson)(msExchMailboxGuid=*))';
        $search = @ldap_search($connection, $baseDn, $filter, ['dn'], 0, 0, 0);
        if ($search === false) return 0;
        $entries = ldap_get_entries($connection, $search);
        return (int) ($entries['count'] ?? 0);
    }
}

if (!function_exists('ldap_user_has_mailbox')) {
    function ldap_user_has_mailbox(array $entry): bool
    {
        $guid = ldap_first_attr($entry, 'msexchmailboxguid', '');
        return $guid !== '';
    }
}

if (!function_exists('ldap_parse_proxy_addresses')) {
    function ldap_parse_proxy_addresses(array $entry): array
    {
        $proxyAddresses = ldap_attr_all($entry, 'proxyaddresses');
        $primary = '';
        $secondary = [];
        foreach ($proxyAddresses as $addr) {
            if (preg_match('/^(smtp|SMTP):(.+)$/i', $addr, $m)) {
                $isPrimary = $m[1] === 'SMTP';
                $entryArr = ['address' => $m[2], 'type' => $m[1], 'is_primary' => $isPrimary];
                if ($isPrimary) {
                    $primary = $m[2];
                    array_unshift($secondary, $entryArr);
                } else {
                    $secondary[] = $entryArr;
                }
            }
        }
        return ['primary' => $primary, 'all' => $secondary];
    }
}

if (!function_exists('ldap_exchange_discover_server_from_mailbox')) {
    function ldap_exchange_discover_server_from_mailbox($connection, string $baseDn): string
    {
        $filter = '(&(objectClass=user)(objectClass=organizationalPerson)(msExchMailboxGuid=*)(msExchHomeServerName=*))';
        $search = @ldap_search($connection, $baseDn, $filter, ['msexchhomeservername'], 0, 1, 0);
        if ($search === false) return '';
        $entries = ldap_get_entries($connection, $search);
        if (($entries['count'] ?? 0) === 0) return '';
        $homeServer = (string) ($entries[0]['msexchhomeservername'][0] ?? '');
        if ($homeServer === '') return '';
        // msExchHomeServerName format: /o=Org/ou=Group/cn=Configuration/cn=Servers/cn=EX01/cn=MDB
        if (preg_match('#/cn=Servers/cn=([^/]+)#i', $homeServer, $m)) {
            return $m[1]; // Just the hostname — DNS on AD network resolves it
        }
        return $homeServer;
    }
}
