<?php

require_once __DIR__ . '/powershell_runner.php';

if (!function_exists('exchange_discover_server')) {
    function exchange_discover_server(): string
    {
        $exchangeConfig = function_exists('ldap_exchange_active_domain_config')
            ? ldap_exchange_active_domain_config()
            : [];

        $server = $exchangeConfig['server_override'] ?? '';
        if ($server !== '') {
            return $server;
        }

        if (!function_exists('ldap_exchange_discover_servers')) {
            return '';
        }

        try {
            // 1. Try Config NC search for Exchange servers (needs Config NC read access)
            $servers = ldap_run_with_connection(function ($connection, $config) {
                $baseDn = ldap_search_base_dn($config);
                if ($baseDn === '') return [];
                return ldap_exchange_discover_servers($connection, $baseDn);
            });
            if (!empty($servers) && !empty($servers[0]['name'])) {
                return $servers[0]['name'];
            }

            // 2. Try database discovery via Config NC
            if (function_exists('ldap_exchange_get_databases')) {
                $databases = ldap_run_with_connection(function ($connection, $config) {
                    $baseDn = ldap_search_base_dn($config);
                    if ($baseDn === '') return [];
                    return ldap_exchange_get_databases($connection, $baseDn);
                });
                foreach ($databases as $database) {
                    if (!empty($database['server'])) {
                        return (string) $database['server'];
                    }
                }
            }

            // 3. Fallback: extract server name from any mailbox user's msExchHomeServerName
            // No special permissions needed — reads normal user objects.
            if (function_exists('ldap_exchange_discover_server_from_mailbox')) {
                $server = ldap_run_with_connection(function ($connection, $config) {
                    $baseDn = ldap_search_base_dn($config);
                    if ($baseDn === '') return '';
                    return ldap_exchange_discover_server_from_mailbox($connection, $baseDn);
                });
                if ($server !== '') {
                    return $server;
                }
            }
        } catch (Throwable $e) {
            error_log('Exchange server discovery failed: ' . $e->getMessage());
        }

        // Fallback: extract hostname from ps_uri_override if configured
        $uri = $exchangeConfig['ps_uri_override'] ?? '';
        if ($uri !== '') {
            $parsed = parse_url($uri);
            if (!empty($parsed['host'])) {
                return $parsed['host'];
            }
        }

        return '';
    }
}

if (!function_exists('exchange_get_ps_uri')) {
    function exchange_get_ps_uri(?string $server = null): string
    {
        if ($server === null || $server === '') {
            $server = exchange_discover_server();
        }
        if ($server === '') {
            return '';
        }
        $exchangeConfig = function_exists('ldap_exchange_active_domain_config')
            ? ldap_exchange_active_domain_config()
            : [];
        $uriOverride = $exchangeConfig['ps_uri_override'] ?? '';
        if ($uriOverride !== '') {
            return $uriOverride;
        }

        $useHttps = $exchangeConfig['ps_use_https'] ?? true;
        $port = $useHttps ? '5986' : '5985';
        $protocol = $useHttps ? 'https' : 'http';
        return "{$protocol}://{$server}:{$port}/PowerShell/";
    }
}

if (!function_exists('exchange_get_credential')) {
    function exchange_get_credential(): array
    {
        $exchangeConfig = function_exists('ldap_exchange_active_domain_config')
            ? ldap_exchange_active_domain_config()
            : [];
        $username = $exchangeConfig['ps_username'] ?? '';
        $password = $exchangeConfig['ps_password'] ?? '';

        // Fall back to LDAP bind credentials if Exchange credentials not configured.
        // This allows reusing the same LDAP bind user that already has ECP/Exchange RBAC roles.
        if ($username === '' || $password === '') {
            $ldapConfig = function_exists('ldap_read_config') ? ldap_read_config() : [];
            if ($username === '' && !empty($ldapConfig['bind_dn'])) {
                $username = (string) $ldapConfig['bind_dn'];
            }
            if ($password === '' && function_exists('ldap_read_bind_password')) {
                $bindPwd = ldap_read_bind_password();
                if ($bindPwd !== '') {
                    $password = $bindPwd;
                }
            }
        }

        return ['username' => $username, 'password' => $password];
    }
}

if (!function_exists('exchange_build_inline_script')) {
    function exchange_build_inline_script(string $uri, string $cmdlet, string $paramStr, array $cred = []): string
    {
        $isLinux = powershell_is_linux();
        $useHttps = str_starts_with($uri, 'https');
        $authMode = $cred['username'] !== '' ? 'Basic' : 'Kerberos';

        if ($isLinux || !empty($cred['username'])) {
            $lines = [];
            $lines[] = 'try {';
            if (!empty($cred['username'])) {
                $u = str_replace("'", "''", $cred['username']);
                $p = str_replace("'", "''", $cred['password']);
                $lines[] = "    \$ErrorActionPreference = 'Stop'";
                $lines[] = "    \$sec = ConvertTo-SecureString '{$p}' -AsPlainText -Force";
                $lines[] = "    \$cred = New-Object System.Management.Automation.PSCredential('{$u}', \$sec)";
                $lines[] = "    \$session = New-PSSession -ConfigurationName Microsoft.Exchange -ConnectionUri '{$uri}' -Authentication Basic -Credential \$cred -AllowRedirection -ErrorAction Stop";
            } else {
                $lines[] = "    \$ErrorActionPreference = 'Stop'";
                $lines[] = "    \$session = New-PSSession -ConfigurationName Microsoft.Exchange -ConnectionUri '{$uri}' -Authentication Kerberos -ErrorAction Stop";
            }
            $lines[] = '    Import-PSSession $session -AllowClobber -DisableNameChecking | Out-Null';
            $lines[] = "    \$result = {$cmdlet} {$paramStr}";
            $lines[] = '    Remove-PSSession $session';
            $lines[] = '    if ($result -is [array] -or $result -is [System.Collections.IEnumerable]) {';
            $lines[] = '        $result | ConvertTo-Json -Compress -Depth 3 -WarningAction SilentlyContinue';
            $lines[] = '    } elseif ($result -ne $null) {';
            $lines[] = '        $result | ConvertTo-Json -Compress -Depth 3 -WarningAction SilentlyContinue';
            $lines[] = '    } else {';
            $lines[] = "        '{\"success\":true}'";
            $lines[] = '    }';
            $lines[] = '} catch {';
            $lines[] = '    if ($session -ne $null) { Remove-PSSession $session -ErrorAction SilentlyContinue }';
            $lines[] = '    [pscustomobject]@{success=$false;message=$_.Exception.Message} | ConvertTo-Json -Compress -WarningAction SilentlyContinue';
            $lines[] = '}';
            $lines[] = '';
            return implode("\n", $lines);
        }

        // Windows + Kerberos: no explicit credentials needed (runs as process user)
        return <<<PS
try {
    \$ErrorActionPreference = 'Stop'
    \$session = New-PSSession -ConfigurationName Microsoft.Exchange `
        -ConnectionUri '{$uri}' `
        -Authentication Kerberos -ErrorAction Stop
    Import-PSSession \$session -AllowClobber -DisableNameChecking | Out-Null
    \$result = {$cmdlet} {$paramStr}
    Remove-PSSession \$session
    if (\$result -is [array] -or \$result -is [System.Collections.IEnumerable]) {
        \$result | ConvertTo-Json -Compress -Depth 3 -WarningAction SilentlyContinue
    } elseif (\$result -ne \$null) {
        \$result | ConvertTo-Json -Compress -Depth 3 -WarningAction SilentlyContinue
    } else {
        '{"success":true}'
    }
} catch {
    if (\$session -ne \$null) { Remove-PSSession \$session -ErrorAction SilentlyContinue }
    [pscustomobject]@{success=\$false;message=\$_.Exception.Message} | ConvertTo-Json -Compress -WarningAction SilentlyContinue
}
PS;
    }
}

if (!function_exists('exchange_ps_quote')) {
    function exchange_ps_quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}

if (!function_exists('exchange_raw')) {
    function exchange_raw(string $expression): array
    {
        return ['__exchange_raw' => $expression];
    }
}

if (!function_exists('exchange_ps_param_value')) {
    function exchange_ps_param_value($value): string
    {
        if (is_array($value) && array_key_exists('__exchange_raw', $value)) {
            return (string) $value['__exchange_raw'];
        }
        if (is_bool($value)) {
            return $value ? '$true' : '$false';
        }
        if ($value === null) {
            return '$null';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return exchange_ps_quote((string) $value);
    }
}

if (!function_exists('exchange_ensure_kerberos_ticket')) {
    function exchange_ensure_kerberos_ticket(): bool
    {
        $cred = exchange_get_credential();
        if (empty($cred['username']) || empty($cred['password'])) {
            return false;
        }
        // Check if we already have a valid ticket
        exec('klist -s 2>&1', $klistOut, $klistExit);
        if ($klistExit === 0) {
            return true; // Valid ticket already exists
        }
        // Create keytab and get ticket
        $keytab = '/tmp/exchange_krb5.keytab';
        $user = str_replace("'", "''", $cred['username']);
        $pwd = str_replace("'", "''", $cred['password']);
        $ktutilInput = "add_entry -password -p {$user} -k 1 -e aes256-cts-hmac-sha1-96\n{$pwd}\nwrite_kt {$keytab}\nquit\n";
        $ktutilFile = tempnam(sys_get_temp_dir(), 'kt_') . '.txt';
        @file_put_contents($ktutilFile, $ktutilInput);
        exec('ktutil < ' . escapeshellarg($ktutilFile) . ' 2>/dev/null', $ktutilOut, $ktutilExit);
        @unlink($ktutilFile);
        if ($ktutilExit !== 0) {
            return false;
        }
        exec('kinit -k -t ' . escapeshellarg($keytab) . ' ' . escapeshellarg($user) . ' 2>/dev/null', $kinitOut, $kinitExit);
        @unlink($keytab);
        return $kinitExit === 0;
    }
}

if (!function_exists('exchange_has_kerberos_ticket')) {
    function exchange_has_kerberos_ticket(): bool
    {
        exec('klist -s 2>&1', $out, $exit);
        return $exit === 0;
    }
}

if (!function_exists('exchange_run_cmdlet')) {
    function exchange_run_cmdlet(string $cmdlet, array $params = [], array $configOverride = []): array
    {
        $isLinux = powershell_is_linux();

        // When configOverride is provided, resolve from override values directly.
        // Otherwise, read from stored domain config.
        if (!empty($configOverride)) {
            $exConfig = $configOverride;
        } else {
            $exConfig = function_exists('ldap_exchange_active_domain_config') ? ldap_exchange_active_domain_config() : [];
            if (!empty($exConfig) && isset($exConfig['enabled']) && !$exConfig['enabled']) {
                return ['success' => false, 'message' => 'Exchange is disabled in domain configuration.'];
            }
        }

        // Resolve server
        $server = '';
        if (!empty($configOverride) && !empty($configOverride['server_override'])) {
            $server = $configOverride['server_override'];
        } else {
            $server = exchange_discover_server();
        }
        if ($server === '') {
            return ['success' => false, 'message' => 'No Exchange server discovered.'];
        }

        // Resolve PS URI
        $uri = '';
        if (!empty($configOverride)) {
            // Use override ps_uri_override if set, otherwise build from override ps_use_https + server
            if (!empty($configOverride['ps_uri_override'])) {
                $uri = $configOverride['ps_uri_override'];
            } elseif ($server !== '') {
                $useHttps = !empty($exConfig['ps_use_https']);
                $port = $useHttps ? '5986' : '5985';
                $protocol = $useHttps ? 'https' : 'http';
                $uri = "{$protocol}://{$server}:{$port}/PowerShell/";
            }
        } else {
            $uri = exchange_get_ps_uri($server);
        }
        if ($uri === '') {
            return ['success' => false, 'message' => 'Exchange PS URI not configured.'];
        }

        // Resolve credentials
        $cred = [];
        if (!empty($configOverride)) {
            $username = $configOverride['ps_username'] ?? '';
            $password = $configOverride['ps_password'] ?? '';
            if ($username === '' || $password === '') {
                $ldapConfig = function_exists('ldap_read_config') ? ldap_read_config() : [];
                if ($username === '' && !empty($ldapConfig['bind_dn'])) {
                    $username = (string) $ldapConfig['bind_dn'];
                }
                if ($password === '' && function_exists('ldap_read_bind_password')) {
                    $bindPwd = ldap_read_bind_password();
                    if ($bindPwd !== '') {
                        $password = $bindPwd;
                    }
                }
            }
            $cred = ['username' => $username, 'password' => $password];
        } else {
            $cred = exchange_get_credential();
        }

        // On Linux, try Kerberos first (uses cached ticket if available).
        // Fall back to Basic auth if credentials exist and Kerberos fails.
        $useKerberos = false;
        if ($isLinux) {
            $useKerberos = exchange_ensure_kerberos_ticket();
        }
        if (!$useKerberos && empty($cred['username'])) {
            return ['success' => false, 'message' => 'Failed to obtain Kerberos ticket for Exchange PowerShell connection. No fallback credentials available.'];
        }

        $paramParts = [];
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $paramParts[] = "-{$key}:" . exchange_ps_param_value($value);
            } else {
                $paramParts[] = "-{$key} " . exchange_ps_param_value($value);
            }
        }
        $paramStr = implode(' ', $paramParts);

        $scriptCred = $useKerberos ? [] : $cred;
        $script = exchange_build_inline_script($uri, $cmdlet, $paramStr, $scriptCred);

        $timeout = $configOverride['timeout'] ?? 60;
        $result = powershell_run_inline($script, ['timeout' => $timeout]);

        $output = trim($result['output']);
        $decoded = json_decode($output, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $result['decoded'] = $decoded;
        } else {
            $result['decoded'] = null;
        }

        return $result;
    }
}

if (!function_exists('exchange_enable_mailbox')) {
    function exchange_enable_mailbox(string $identity, string $database = ''): array
    {
        $params = ['Identity' => $identity];
        if ($database !== '') {
            $params['Database'] = $database;
        }
        return exchange_run_cmdlet('Enable-Mailbox', $params);
    }
}

if (!function_exists('exchange_disable_mailbox')) {
    function exchange_disable_mailbox(string $identity): array
    {
        return exchange_run_cmdlet('Disable-Mailbox', [
            'Identity' => $identity,
            'Confirm' => false,
        ]);
    }
}

if (!function_exists('exchange_get_mailbox')) {
    function exchange_get_mailbox(string $identity): array
    {
        return exchange_run_cmdlet('Get-Mailbox', [
            'Identity' => $identity,
        ]);
    }
}

if (!function_exists('exchange_get_mailbox_statistics')) {
    function exchange_get_mailbox_statistics(string $identity): array
    {
        return exchange_run_cmdlet('Get-MailboxStatistics', [
            'Identity' => $identity,
        ]);
    }
}

if (!function_exists('exchange_set_mailbox_quota')) {
    function exchange_set_mailbox_quota(string $identity, string $warnGb = '5', string $sendGb = '6', string $recvGb = '8'): array
    {
        return exchange_run_cmdlet('Set-Mailbox', [
            'Identity' => $identity,
            'IssueWarningQuota' => exchange_normalize_quota_value($warnGb, 'GB'),
            'ProhibitSendQuota' => exchange_normalize_quota_value($sendGb, 'GB'),
            'ProhibitSendReceiveQuota' => exchange_normalize_quota_value($recvGb, 'GB'),
        ]);
    }
}

if (!function_exists('exchange_normalize_quota_value')) {
    function exchange_normalize_quota_value(string $value, string $defaultUnit = 'GB'): string
    {
        $value = trim($value);
        if ($value === '') {
            return '0' . $defaultUnit;
        }
        if (preg_match('/^\d+(?:\.\d+)?\s*(KB|MB|GB|TB)$/i', $value)) {
            return preg_replace('/\s+/', '', strtoupper($value));
        }
        if (is_numeric($value)) {
            return $value . strtoupper($defaultUnit);
        }
        return $value;
    }
}

if (!function_exists('exchange_get_databases')) {
    function exchange_get_databases(): array
    {
        return exchange_run_cmdlet('Get-MailboxDatabase', [
            'Status' => true,
        ]);
    }
}

if (!function_exists('exchange_get_accepted_domains')) {
    function exchange_get_accepted_domains(): array
    {
        return exchange_run_cmdlet('Get-AcceptedDomain');
    }
}

if (!function_exists('exchange_get_distribution_group')) {
    function exchange_get_distribution_group(string $identity): array
    {
        return exchange_run_cmdlet('Get-DistributionGroup', [
            'Identity' => $identity,
        ]);
    }
}

if (!function_exists('exchange_new_distribution_group')) {
    function exchange_new_distribution_group(string $name, string $alias = '', string $description = '', array $members = [], string $ou = ''): array
    {
        $params = ['Name' => $name, 'Type' => 'Distribution'];
        if ($alias !== '') $params['Alias'] = $alias;
        if ($description !== '') $params['Notes'] = $description;
        if ($ou !== '') $params['OrganizationalUnit'] = $ou;
        return exchange_run_cmdlet('New-DistributionGroup', $params);
    }
}

if (!function_exists('exchange_add_group_member')) {
    function exchange_add_group_member(string $group, string $member): array
    {
        return exchange_run_cmdlet('Add-DistributionGroupMember', [
            'Identity' => $group,
            'Member' => $member,
        ]);
    }
}

if (!function_exists('exchange_remove_group_member')) {
    function exchange_remove_group_member(string $group, string $member): array
    {
        return exchange_run_cmdlet('Remove-DistributionGroupMember', [
            'Identity' => $group,
            'Member' => $member,
            'Confirm' => false,
        ]);
    }
}

if (!function_exists('exchange_remove_distribution_group')) {
    function exchange_remove_distribution_group(string $identity): array
    {
        return exchange_run_cmdlet('Remove-DistributionGroup', [
            'Identity' => $identity,
            'Confirm' => false,
        ]);
    }
}

if (!function_exists('exchange_set_forwarding')) {
    function exchange_set_forwarding(string $identity, string $forwardTo = '', bool $deliverToMailbox = true): array
    {
        $params = ['Identity' => $identity, 'Confirm' => false];
        if ($forwardTo !== '') {
            $params['ForwardingSMTPAddress'] = $forwardTo;
            $params['DeliverToMailboxAndForward'] = $deliverToMailbox;
        } else {
            $params['ForwardingSMTPAddress'] = exchange_raw('$null');
            $params['DeliverToMailboxAndForward'] = false;
        }
        return exchange_run_cmdlet('Set-Mailbox', $params);
    }
}

if (!function_exists('exchange_set_primary_smtp')) {
    function exchange_set_primary_smtp(string $identity, string $email): array
    {
        return exchange_run_cmdlet('Set-Mailbox', [
            'Identity' => $identity,
            'EmailAddressPolicyEnabled' => false,
            'PrimarySmtpAddress' => $email,
            'Confirm' => false,
        ]);
    }
}

if (!function_exists('exchange_add_email_address')) {
    function exchange_add_email_address(string $identity, string $email): array
    {
        return exchange_run_cmdlet('Set-Mailbox', [
            'Identity' => $identity,
            'EmailAddresses' => exchange_raw('@{Add=' . exchange_ps_quote("smtp:$email") . '}'),
            'Confirm' => false,
        ]);
    }
}

if (!function_exists('exchange_remove_email_address')) {
    function exchange_remove_email_address(string $identity, string $email): array
    {
        $params = ['Identity' => $identity, 'Confirm' => false];
        $params['EmailAddresses'] = exchange_raw('@{Remove=' . exchange_ps_quote("smtp:$email") . '}');
        return exchange_run_cmdlet('Set-Mailbox', $params);
    }
}

if (!function_exists('exchange_get_queues')) {
    function exchange_get_queues(string $server = ''): array
    {
        $params = [];
        if ($server !== '') $params['Server'] = $server;
        return exchange_run_cmdlet('Get-Queue', $params);
    }
}

if (!function_exists('exchange_get_message_tracking')) {
    function exchange_get_message_tracking(string $sender = '', string $recipient = '', string $startDate = '', string $endDate = '', int $limit = 100): array
    {
        $params = ['ResultSize' => $limit];
        if ($sender !== '') $params['Sender'] = $sender;
        if ($recipient !== '') $params['Recipients'] = $recipient;
        if ($startDate !== '') $params['Start'] = $startDate;
        if ($endDate !== '') $params['End'] = $endDate;
        return exchange_run_cmdlet('Get-MessageTrackingLog', $params);
    }
}

// ===================== P2 — Nice to Have =====================

if (!function_exists('exchange_add_full_access')) {
    function exchange_add_full_access(string $identity, string $user): array
    {
        return exchange_run_cmdlet('Add-MailboxPermission', [
            'Identity' => $identity,
            'User' => $user,
            'AccessRights' => 'FullAccess',
            'Confirm' => false,
        ]);
    }
}

if (!function_exists('exchange_remove_full_access')) {
    function exchange_remove_full_access(string $identity, string $user): array
    {
        return exchange_run_cmdlet('Remove-MailboxPermission', [
            'Identity' => $identity,
            'User' => $user,
            'AccessRights' => 'FullAccess',
            'Confirm' => false,
        ]);
    }
}

if (!function_exists('exchange_add_send_as')) {
    function exchange_add_send_as(string $identity, string $user): array
    {
        return exchange_run_cmdlet('Add-ADPermission', [
            'Identity' => $identity,
            'User' => $user,
            'ExtendedRights' => 'send-as',
        ]);
    }
}

if (!function_exists('exchange_remove_send_as')) {
    function exchange_remove_send_as(string $identity, string $user): array
    {
        return exchange_run_cmdlet('Remove-ADPermission', [
            'Identity' => $identity,
            'User' => $user,
            'ExtendedRights' => 'send-as',
            'Confirm' => false,
        ]);
    }
}

if (!function_exists('exchange_set_litigation_hold')) {
    function exchange_set_litigation_hold(string $identity, bool $enabled): array
    {
        return exchange_run_cmdlet('Set-Mailbox', [
            'Identity' => $identity,
            'LitigationHoldEnabled' => $enabled,
            'Confirm' => false,
        ]);
    }
}

if (!function_exists('exchange_set_hidden_from_gal')) {
    function exchange_set_hidden_from_gal(string $identity, bool $hidden): array
    {
        return exchange_run_cmdlet('Set-Mailbox', [
            'Identity'  => $identity,
            'HiddenFromAddressListsEnabled' => $hidden ? 'true' : 'false',
        ]);
    }
}

if (!function_exists('exchange_set_mailbox_database')) {
    function exchange_set_mailbox_database(string $identity, string $databaseName): array
    {
        return exchange_run_cmdlet('New-MoveRequest', [
            'Identity' => $identity,
            'TargetDatabase' => $databaseName,
        ]);
    }
}

if (!function_exists('exchange_set_mailbox_alias')) {
    function exchange_set_mailbox_alias(string $identity, string $alias): array
    {
        return exchange_run_cmdlet('Set-Mailbox', [
            'Identity' => $identity,
            'Alias' => $alias,
            'Confirm' => false,
        ]);
    }
}

if (!function_exists('exchange_set_oof')) {
    function exchange_set_oof(string $identity, string $state, string $internalMessage = '', string $externalMessage = ''): array
    {
        $params = ['Identity' => $identity, 'AutoReplyState' => $state];
        if ($internalMessage !== '') $params['InternalMessage'] = $internalMessage;
        if ($externalMessage !== '') $params['ExternalMessage'] = $externalMessage;
        return exchange_run_cmdlet('Set-MailboxAutoReplyConfiguration', $params);
    }
}

if (!function_exists('exchange_new_move_request')) {
    function exchange_new_move_request(string $identity, string $targetDatabase): array
    {
        return exchange_run_cmdlet('New-MoveRequest', [
            'Identity' => $identity,
            'TargetDatabase' => $targetDatabase,
        ]);
    }
}

if (!function_exists('exchange_get_move_request')) {
    function exchange_get_move_request(string $identity = ''): array
    {
        $params = [];
        if ($identity !== '') $params['Identity'] = $identity;
        return exchange_run_cmdlet('Get-MoveRequest', $params);
    }
}

if (!function_exists('exchange_remove_move_request')) {
    function exchange_remove_move_request(string $identity): array
    {
        return exchange_run_cmdlet('Remove-MoveRequest', [
            'Identity' => $identity,
            'Confirm' => false,
        ]);
    }
}

if (!function_exists('exchange_get_transport_rules')) {
    function exchange_get_transport_rules(): array
    {
        return exchange_run_cmdlet('Get-TransportRule');
    }
}

// ===================== P3 — Future Features =====================

if (!function_exists('exchange_new_shared_mailbox')) {
    function exchange_new_shared_mailbox(string $name, string $alias = '', string $displayName = ''): array
    {
        $params = ['Name' => $name, 'Shared' => true];
        if ($alias !== '') $params['Alias'] = $alias;
        if ($displayName !== '') $params['DisplayName'] = $displayName;
        return exchange_run_cmdlet('New-Mailbox', $params);
    }
}

if (!function_exists('exchange_new_room_mailbox')) {
    function exchange_new_room_mailbox(string $name, string $alias = '', string $capacity = ''): array
    {
        $params = ['Name' => $name, 'Room' => true];
        if ($alias !== '') $params['Alias'] = $alias;
        if ($capacity !== '') $params['ResourceCapacity'] = $capacity;
        return exchange_run_cmdlet('New-Mailbox', $params);
    }
}

if (!function_exists('exchange_new_equipment_mailbox')) {
    function exchange_new_equipment_mailbox(string $name, string $alias = ''): array
    {
        $params = ['Name' => $name, 'Equipment' => true];
        if ($alias !== '') $params['Alias'] = $alias;
        return exchange_run_cmdlet('New-Mailbox', $params);
    }
}

if (!function_exists('exchange_enable_archive')) {
    function exchange_enable_archive(string $identity, string $database = ''): array
    {
        $params = ['Identity' => $identity, 'Archive' => true];
        if ($database !== '') $params['ArchiveDatabase'] = $database;
        return exchange_run_cmdlet('Enable-Mailbox', $params);
    }
}

if (!function_exists('exchange_disable_archive')) {
    function exchange_disable_archive(string $identity): array
    {
        return exchange_run_cmdlet('Disable-Mailbox', [
            'Identity' => $identity,
            'Archive' => true,
            'Confirm' => false,
        ]);
    }
}

if (!function_exists('exchange_get_archive')) {
    function exchange_get_archive(string $identity): array
    {
        $params = ['Identity' => $identity, 'Archive' => true];
        return exchange_run_cmdlet('Get-Mailbox', $params);
    }
}

if (!function_exists('exchange_get_cas_mailbox')) {
    function exchange_get_cas_mailbox(string $identity): array
    {
        return exchange_run_cmdlet('Get-CASMailbox', [
            'Identity' => $identity,
        ]);
    }
}

if (!function_exists('exchange_get_all_mailbox_data')) {
    function exchange_get_all_mailbox_data(string $identity): array
    {
        $config = function_exists('ldap_exchange_active_domain_config') ? ldap_exchange_active_domain_config() : [];
        $isLinux = powershell_is_linux();
        $server = exchange_discover_server();
        $uri = exchange_get_ps_uri($server);
        if ($uri === '') return ['success' => false, 'message' => 'No PS URI'];

        $cred = exchange_get_credential();
        $useKerberos = false;
        if ($isLinux) {
            $useKerberos = exchange_ensure_kerberos_ticket();
        }
        if ($isLinux && !$useKerberos) {
            return ['success' => false, 'message' => 'No Kerberos ticket'];
        }
        $scriptCred = $useKerberos ? [] : $cred;
        $u = str_replace("'", "''", $cred['username'] ?? '');
        $p = str_replace("'", "''", $cred['password'] ?? '');
        $id = str_replace("'", "''", $identity);

        $lines = [];
        $lines[] = 'try {';
        $lines[] = "    \$ErrorActionPreference = 'Stop'";
        if (!empty($scriptCred['username'])) {
            $lines[] = "    \$sec = ConvertTo-SecureString '{$p}' -AsPlainText -Force";
            $lines[] = "    \$cred = New-Object System.Management.Automation.PSCredential('{$u}', \$sec)";
            $lines[] = "    \$session = New-PSSession -ConfigurationName Microsoft.Exchange -ConnectionUri '{$uri}' -Authentication Basic -Credential \$cred -AllowRedirection -ErrorAction Stop";
        } else {
            $lines[] = "    \$session = New-PSSession -ConfigurationName Microsoft.Exchange -ConnectionUri '{$uri}' -Authentication Kerberos -ErrorAction Stop";
        }
        $lines[] = '    Import-PSSession $session -AllowClobber -DisableNameChecking | Out-Null';
        $lines[] = "    \$mb = Get-Mailbox -Identity '{$id}' -ErrorAction SilentlyContinue";
        $lines[] = "    \$statsRaw = if (\$mb) { \$mb | Get-MailboxStatistics -ErrorAction SilentlyContinue } else { \$null }";
        $lines[] = "    \$cas = Get-CASMailbox -Identity '{$id}' -ErrorAction SilentlyContinue";
        $lines[] = "    \$arch = Get-Mailbox -Identity '{$id}' -Archive -ErrorAction SilentlyContinue";
        $lines[] = '    if ($session) { Remove-PSSession $session }';
        $lines[] = '    $r = @{}';
        $lines[] = '    if ($mb) {';
        $lines[] = '        $r.mb = @([PSCustomObject]@{';
        $lines[] = '            ForwardingSmtpAddress = $mb.ForwardingSmtpAddress';
        $lines[] = '            DeliverToMailboxAndForward = [bool]$mb.DeliverToMailboxAndForward';
        $lines[] = '            LitigationHoldEnabled = [bool]$mb.LitigationHoldEnabled';
        $lines[] = '            HiddenFromAddressListsEnabled = [bool]$mb.HiddenFromAddressListsEnabled';
        $lines[] = '            RecipientTypeDetails = "$($mb.RecipientTypeDetails)"';
        $lines[] = '            ArchiveStatus = "$($mb.ArchiveStatus)"';
        $lines[] = '            ArchiveDatabase = "$($mb.ArchiveDatabase)"';
        $lines[] = '            ArchiveName = $mb.ArchiveName';
        $lines[] = '            Database = "$($mb.Database)"';
        $lines[] = '            IssueWarningQuota = "$($mb.IssueWarningQuota)"';
        $lines[] = '            ProhibitSendQuota = "$($mb.ProhibitSendQuota)"';
        $lines[] = '            ProhibitSendReceiveQuota = "$($mb.ProhibitSendReceiveQuota)"';
        $lines[] = '            UseDatabaseQuotaDefaults = $mb.UseDatabaseQuotaDefaults';
        $lines[] = '        })';
        $lines[] = '    } else { $r.mb = @() }';
        $lines[] = '    if ($statsRaw) {';
        $lines[] = '        $r.stats = @([PSCustomObject]@{';
        $lines[] = '            TotalItemSize = "$($statsRaw.TotalItemSize)"';
        $lines[] = '            ItemCount = $statsRaw.ItemCount';
        $lines[] = '            TotalDeletedItemSize = "$($statsRaw.TotalDeletedItemSize)"';
        $lines[] = '            DatabaseName = "$($statsRaw.DatabaseName)"';
        $lines[] = '            DatabaseIssueWarningQuota = "$($statsRaw.DatabaseIssueWarningQuota)"';
        $lines[] = '            DatabaseProhibitSendQuota = "$($statsRaw.DatabaseProhibitSendQuota)"';
        $lines[] = '            DatabaseProhibitSendReceiveQuota = "$($statsRaw.DatabaseProhibitSendReceiveQuota)"';
        $lines[] = '            IssueWarningQuota = "$($statsRaw.IssueWarningQuota)"';
        $lines[] = '            ProhibitSendQuota = "$($statsRaw.ProhibitSendQuota)"';
        $lines[] = '            ProhibitSendReceiveQuota = "$($statsRaw.ProhibitSendReceiveQuota)"';
        $lines[] = '            UseDatabaseQuotaDefaults = $statsRaw.UseDatabaseQuotaDefaults';
        $lines[] = '        })';
        $lines[] = '    } else { $r.stats = @() }';
        $lines[] = '    if ($cas) {';
        $lines[] = '        $r.cas = @([PSCustomObject]@{';
        $lines[] = '            ActiveSyncEnabled = [bool]$cas.ActiveSyncEnabled';
        $lines[] = '            OWAEnabled = [bool]$cas.OWAEnabled';
        $lines[] = '            OWAforDevicesEnabled = [bool]$cas.OWAforDevicesEnabled';
        $lines[] = '            MAPIEnabled = [bool]$cas.MAPIEnabled';
        $lines[] = '            POPEnabled = [bool]$cas.POPEnabled';
        $lines[] = '            IMAPEnabled = [bool]$cas.IMAPEnabled';
        $lines[] = '            EWSEnabled = [bool]$cas.EWSEnabled';
        $lines[] = '        })';
        $lines[] = '    } else { $r.cas = @() }';
        $lines[] = '    if ($arch) {';
        $lines[] = '        $r.arch = @([PSCustomObject]@{';
        $lines[] = '            ArchiveStatus = "$($arch.ArchiveStatus)"';
        $lines[] = '            ArchiveDatabase = "$($arch.ArchiveDatabase)"';
        $lines[] = '            ArchiveName = $arch.ArchiveName';
        $lines[] = '            ArchiveQuota = "$($arch.ArchiveQuota)"';
        $lines[] = '            ArchiveWarningQuota = "$($arch.ArchiveWarningQuota)"';
        $lines[] = '        })';
        $lines[] = '    } else { $r.arch = @() }';
        $lines[] = '    $r | ConvertTo-Json -Compress -Depth 2';
        $lines[] = '} catch {';
        $lines[] = '    if ($session -ne $null) { Remove-PSSession $session -ErrorAction SilentlyContinue }';
        $lines[] = '    [pscustomobject]@{success=$false;message=$_.Exception.Message} | ConvertTo-Json -Compress';
        $lines[] = '}';
        $script = implode("\n", $lines);

        $result = powershell_run_inline($script);
        $output = trim($result['output']);
        $decoded = json_decode($output, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $result['decoded'] = $decoded;
        } else {
            $result['decoded'] = null;
        }
        return $result;
    }
}

if (!function_exists('exchange_set_cas_mailbox')) {
    function exchange_set_cas_mailbox(string $identity, array $settings): array
    {
        $params = array_merge(['Identity' => $identity, 'Confirm' => false], $settings);
        return exchange_run_cmdlet('Set-CASMailbox', $params);
    }
}

if (!function_exists('exchange_set_mail_tip')) {
    function exchange_set_mail_tip(string $identity, string $mailTip = ''): array
    {
        $params = ['Identity' => $identity, 'Confirm' => false];
        if ($mailTip !== '') {
            $params['MailTip'] = $mailTip;
        } else {
            $params['MailTip'] = exchange_raw('$null');
        }
        return exchange_run_cmdlet('Set-Mailbox', $params);
    }
}

if (!function_exists('exchange_set_calendar_permissions')) {
    function exchange_set_calendar_permissions(string $identity, string $user, string $accessRights = 'Reviewer'): array
    {
        return exchange_run_cmdlet('Set-MailboxFolderPermission', [
            'Identity' => "{$identity}:\\Calendar",
            'User' => $user,
            'AccessRights' => $accessRights,
        ]);
    }
}

if (!function_exists('exchange_remove_calendar_permissions')) {
    function exchange_remove_calendar_permissions(string $identity, string $user): array
    {
        return exchange_run_cmdlet('Remove-MailboxFolderPermission', [
            'Identity' => "{$identity}:\\Calendar",
            'User' => $user,
            'Confirm' => false,
        ]);
    }
}

if (!function_exists('exchange_new_mailbox_restore_request')) {
    function exchange_new_mailbox_restore_request(string $sourceMailbox, string $targetMailbox, bool $allowLegacyDnMismatch = false): array
    {
        $params = [
            'SourceMailbox' => $sourceMailbox,
            'TargetMailbox' => $targetMailbox,
        ];
        if ($allowLegacyDnMismatch) $params['AllowLegacyDNMismatch'] = true;
        return exchange_run_cmdlet('New-MailboxRestoreRequest', $params);
    }
}

if (!function_exists('exchange_get_retention_policies')) {
    function exchange_get_retention_policies(): array
    {
        return exchange_run_cmdlet('Get-RetentionPolicy');
    }
}

if (!function_exists('exchange_set_retention_policy')) {
    function exchange_set_retention_policy(string $identity, string $retentionPolicy): array
    {
        return exchange_run_cmdlet('Set-Mailbox', [
            'Identity' => $identity,
            'RetentionPolicy' => $retentionPolicy,
            'Confirm' => false,
        ]);
    }
}

if (!function_exists('exchange_get_mobile_device_statistics')) {
    function exchange_get_mobile_device_statistics(string $identity): array
    {
        return exchange_run_cmdlet('Get-MobileDeviceStatistics', [
            'Mailbox' => $identity,
        ], ['timeout' => 30]);
    }
}

