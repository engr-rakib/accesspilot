<?php

if (!function_exists('diagnostics_humanize_message')) {
    function diagnostics_humanize_message(string $raw, string $context = 'general'): string
    {
        $m = trim($raw);
        if ($m === '') {
            return 'No details available.';
        }

        if (stripos($m, 'user name or password is incorrect') !== false
            || stripos($m, 'Logon failure') !== false
            || stripos($m, 'logon failure') !== false) {
            return 'Stored administrator password is incorrect or expired.';
        }

        if (stripos($m, 'Exception setting') !== false && stripos($m, 'Password') !== false) {
            return 'PowerShell could not apply the stored admin password (invalid or missing secret).';
        }

        if (stripos($m, 'Secure config file missing') !== false) {
            return 'Deployment identity XML file was not found in the secure vault.';
        }

        if (stripos($m, 'Bind password not stored') !== false) {
            return 'LDAP bind password is not saved in the secure vault yet.';
        }

        if (stripos($m, 'extension is not loaded') !== false) {
            return 'PHP LDAP extension (ext-ldap) is not enabled on this IIS server.';
        }

        if (stripos($m, 'LDAP bind failed') !== false) {
            return 'LDAP bind was rejected — check bind DN, password, and TLS/port settings.';
        }

        if (stripos($m, 'Unreachable') !== false || stripos($m, 'unreachable') !== false) {
            return 'Directory host did not respond on the network.';
        }

        if (strlen($m) > 160) {
            $m = preg_replace('/\s+/', ' ', $m);
            return substr($m, 0, 157) . '…';
        }

        return $m;
    }
}

if (!function_exists('diagnostics_suggest_for_message')) {
    function diagnostics_suggest_for_message(string $humanMessage, string $category): string
    {
        $lower = strtolower($humanMessage);

        if ($category === 'powershell' && (strpos($lower, 'password') !== false || strpos($lower, 'logon') !== false)) {
            return 'Open PowerShell Credentials below → enter the correct domain admin password → acknowledge → Save. Or switch backend to LDAP if LDAP is your primary path.';
        }

        if ($category === 'ldap' && strpos($lower, 'bind') !== false) {
            return 'Verify LDAP host, port, TLS, bind DN, and bind password → click Test → Save LDAP when bind succeeds.';
        }

        if ($category === 'ldap' && strpos($lower, 'extension') !== false) {
            return 'Enable extension=ldap in php.ini on the IIS server, then restart the application pool.';
        }

        if ($category === 'license') {
            return 'Complete Organization Setup and apply a valid license from the License Center.';
        }

        if ($category === 'org') {
            return 'Fill Organization, Domain, and Base DN in Organization Setup, then register the deployment.';
        }

        if ($category === 'storage') {
            return 'Check that vault and log paths exist and the IIS app pool identity has read/write permission.';
        }

        if (strpos($lower, 'unreachable') !== false || strpos($lower, 'network') !== false) {
            return 'Confirm the DC/LDAP host name resolves from the IIS server and firewall allows the LDAP port (389 or 636).';
        }

        return 'Review the related configuration card below and use Test before saving changes.';
    }
}

if (!function_exists('diagnostics_build_issues')) {
    /**
     * @param array<string, mixed> $ctx
     * @return list<array<string, string>>
     */
    function diagnostics_build_issues(array $ctx): array
    {
        $issues = [];
        $backend = (string) ($ctx['backend'] ?? 'powershell');
        $cred = (array) ($ctx['credentials'] ?? []);
        $ldap = (array) ($ctx['ldap'] ?? []);
        $ps = (array) ($ctx['powershell'] ?? []);
        $license = (array) ($ctx['license'] ?? []);
        $storage = (array) ($ctx['storage'] ?? []);

        $add = static function (string $severity, string $category, string $title, string $message, string $suggestion = '') use (&$issues): void {
            $issues[] = [
                'severity' => $severity,
                'category' => $category,
                'title' => $title,
                'message' => $message,
                'suggestion' => $suggestion !== '' ? $suggestion : diagnostics_suggest_for_message($message, $category),
            ];
        };

        // Organization & license
        if (empty($ctx['org_registered'])) {
            $add('critical', 'org', 'Organization not registered', 'Deployment identity is incomplete.', 'Register organization, domain, and base DN first.');
        }

        if (!empty($license['is_restricted'])) {
            $add('critical', 'license', 'License restricted', diagnostics_humanize_message((string) ($license['message'] ?? 'License is not active.')), 'Apply or renew the signed license certificate.');
        } elseif (!empty($license['is_active'])) {
            $add('ok', 'license', 'License active', 'Deployment is fully licensed.', '');
        }

        // Storage
        if (isset($storage['secure_vault']) && empty($storage['secure_vault']['connected'])) {
            $add('critical', 'storage', 'Secure vault path', diagnostics_humanize_message((string) ($storage['secure_vault']['message'] ?? 'Vault not writable')), '');
        }
        if (isset($storage['log_storage']) && empty($storage['log_storage']['connected'])) {
            $add('warning', 'storage', 'Log storage path', diagnostics_humanize_message((string) ($storage['log_storage']['message'] ?? 'Log path issue')), '');
        }

        // LDAP path
        if (!empty($cred['ldap_enabled'])) {
            if (empty($cred['ldap_host_set'])) {
                $add('critical', 'ldap', 'LDAP host missing', 'LDAP host (DC) is not configured.', '');
            }
            if (empty($cred['ldap_bind_dn_set'])) {
                $add('critical', 'ldap', 'LDAP bind DN missing', 'Service account bind DN is not set.', '');
            }
            if (empty($cred['ldap_bind_password_stored'])) {
                $add('critical', 'ldap', 'LDAP password not in vault', 'Bind password has not been stored.', '');
            }

            if (!empty($ldap['success'])) {
                $latency = (int) ($ldap['latency_ms'] ?? 0);
                $src = ($ldap['source'] ?? '') === 'live' ? 'live test' : 'last successful test';
                $add('ok', 'ldap', 'LDAP bind healthy', "Connection and bind succeeded ({$src}" . ($latency ? ", {$latency}ms" : '') . ').', '');
            } elseif (($ldap['reachable'] ?? null) === false) {
                $add('critical', 'ldap', 'LDAP host unreachable', diagnostics_humanize_message((string) ($ldap['reach_message'] ?? 'Cannot reach LDAP host.')), '');
            } elseif (!empty($ldap['message']) && ($ldap['message'] ?? '') !== 'LDAP module disabled') {
                $add('critical', 'ldap', 'LDAP bind issue', diagnostics_humanize_message((string) $ldap['message']), '');
            }

            if (in_array($backend, ['ldap', 'auto'], true) && !empty($ldap['success'])) {
                $add('ok', 'backend', 'Active backend: ' . strtoupper($backend), 'Directory operations use LDAP for certified endpoints.', '');
            }
        }

        // PowerShell path
        $psRequired = $backend === 'powershell' || ($backend === 'auto' && empty($ldap['success']));
        $psOptional = $backend === 'ldap' || ($backend === 'auto' && !empty($ldap['success']));

        if (!$cred['ps_password_stored']) {
            if ($backend === 'powershell') {
                $add('critical', 'powershell', 'PowerShell password missing', 'Admin password is not stored in the deployment XML vault.', '');
            } elseif ($backend === 'auto') {
                $add('warning', 'powershell', 'PowerShell fallback not ready', 'No admin password stored — Auto mode cannot fall back to PowerShell.', 'Save PowerShell credentials or fix LDAP so fallback is not needed.');
            } else {
                $add('info', 'powershell', 'PowerShell optional', 'LDAP is the active backend; PowerShell credentials are not required unless you use hybrid features.', '');
            }
        } elseif (!empty($ps['auth']['success'])) {
            $add('ok', 'powershell', 'PowerShell auth healthy', 'Stored admin credentials authenticated successfully.', '');
        } elseif (!empty($ps['auth']['message']) && !in_array($ps['auth']['message'] ?? '', ['Not tested', 'No PS credentials', 'Skipped'], true)) {
            $severity = $psRequired ? 'critical' : ($psOptional ? 'warning' : 'info');
            $title = $psOptional ? 'PowerShell fallback issue' : 'PowerShell authentication failed';
            $msg = diagnostics_humanize_message((string) $ps['auth']['message']);
            if ($psOptional && !empty($ldap['success'])) {
                $severity = 'info';
                $title = 'PowerShell not used (LDAP active)';
                $msg = 'Stored PowerShell password appears invalid, but LDAP is healthy — safe to ignore unless you need PS scripts.';
            }
            $add($severity, 'powershell', $title, $msg, '');
        }

        if ($backend === 'powershell' && !empty($ps['ping']['success'])) {
            $add('ok', 'backend', 'Active backend: POWERSHELL', 'Directory operations use PowerShell scripts.', '');
        }

        if (empty($issues)) {
            $add('ok', 'general', 'All checks passed', 'No configuration issues detected.', '');
        }

        return $issues;
    }
}

if (!function_exists('diagnostics_overall_health')) {
    /**
     * @param list<array<string, string>> $issues
     * @return string healthy|warning|critical
     */
    function diagnostics_overall_health(array $issues): string
    {
        foreach ($issues as $issue) {
            if (($issue['severity'] ?? '') === 'critical') {
                return 'critical';
            }
        }
        foreach ($issues as $issue) {
            if (($issue['severity'] ?? '') === 'warning') {
                return 'warning';
            }
        }
        return 'healthy';
    }
}
