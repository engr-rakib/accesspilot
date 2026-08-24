<?php

require_once __DIR__ . '/../Config/ldap_config_repository.php';
require_once __DIR__ . '/../Connection/ldap_connection_factory.php';

if (!function_exists('ldap_save_settings')) {
    function ldap_save_settings(array $data): array
    {
        $current = ldap_read_config();

        $config = [
            'enabled' => !empty($data['ldap_enabled']),
            'backend' => ldap_normalize_backend((string) ($data['ldap_backend_mode'] ?? $current['backend'] ?? 'powershell')),
            'acknowledged' => !empty($data['ldap_acknowledged']),
            'host' => trim((string) ($data['ldap_host'] ?? '')),
            'port' => (int) ($data['ldap_port'] ?? 389),
            'use_tls' => !empty($data['ldap_use_tls']),
            'base_dn' => trim((string) ($data['ldap_base_dn'] ?? '')),
            'bind_dn' => trim((string) ($data['ldap_bind_dn'] ?? '')),
            'user_search_base' => trim((string) ($data['ldap_user_search_base'] ?? '')),
            'connect_timeout' => (int) ($current['connect_timeout'] ?? 5),
            'page_size' => (int) ($current['page_size'] ?? 500),
        ];

        if ($config['port'] < 1 || $config['port'] > 65535) {
            return ['success' => false, 'message' => 'Invalid LDAP port.'];
        }

        if ($config['enabled'] && $config['backend'] === 'ldap' && !ldap_extension_loaded()) {
            return ['success' => false, 'message' => 'Cannot enable LDAP backend: PHP ldap extension is not loaded.'];
        }

        if (!ldap_write_config($config)) {
            return ['success' => false, 'message' => 'Failed to write LDAP configuration to secure vault.'];
        }

        // Sync settings to the active domain so ldap_read_config() picks them up
        // (domain overrides base config — without this, backend etc. get lost)
        $activeKey = function_exists('ldap_active_domain_key') ? ldap_active_domain_key() : 'default';
        $activeDomain = function_exists('ldap_get_domain') ? ldap_get_domain($activeKey) : null;
        if ($activeDomain !== null) {
            $activeDomain['backend'] = $config['backend'];
            $activeDomain['host'] = $config['host'];
            $activeDomain['port'] = $config['port'];
            $activeDomain['use_tls'] = $config['use_tls'];
            $activeDomain['base_dn'] = $config['base_dn'];
            $activeDomain['bind_dn'] = $config['bind_dn'];
            $activeDomain['user_search_base'] = $config['user_search_base'];
            ldap_upsert_domain($activeDomain);
        }

        $newPassword = (string) ($data['ldap_bind_password'] ?? '');
        if ($newPassword !== '') {
            if (!ldap_write_bind_password($newPassword)) {
                return ['success' => false, 'message' => 'LDAP settings saved but bind password could not be stored.'];
            }
        }

        return ['success' => true, 'message' => 'LDAP settings saved successfully.', 'config' => ldap_public_config()];
    }
}

if (!function_exists('ldap_normalize_backend')) {
    function ldap_normalize_backend(string $backend): string
    {
        $backend = strtolower(trim($backend));
        $allowed = ['powershell', 'ldap', 'auto'];

        return in_array($backend, $allowed, true) ? $backend : 'powershell';
    }
}

if (!function_exists('ldap_status_for_api')) {
    function ldap_status_for_api(): array
    {
        $lastTest = ldap_read_last_test();
        $env = ldap_environment_report();

        return [
            'ldap_extension' => [
                'loaded' => ldap_extension_loaded(),
                'message' => ldap_extension_loaded() ? 'OK' : 'MISSING — enable extension=ldap in php.ini',
            ],
            'ldap_environment' => $env,
            'ldap_last_test' => [
                'at' => $lastTest['at'] ?? null,
                'success' => !empty($lastTest['success']),
                'message' => (string) ($lastTest['message'] ?? ''),
                'latency_ms' => (int) ($lastTest['latency_ms'] ?? 0),
            ],
            'ldap_backend_active' => (string) (ldap_read_config()['backend'] ?? 'powershell'),
            'ldap_module_path' => app_root('app/Ldap'),
        ];
    }
}

if (!function_exists('ldap_run_test_and_persist')) {
    function ldap_run_test_and_persist(?array $configOverride = null, ?string $passwordOverride = null): array
    {
        $result = ldap_test_connection($configOverride, $passwordOverride);

        ldap_write_last_test([
            'success' => !empty($result['success']),
            'message' => (string) ($result['message'] ?? ''),
            'latency_ms' => (int) ($result['latency_ms'] ?? 0),
            'server_naming_context' => (string) ($result['server_naming_context'] ?? ''),
        ]);

        return $result;
    }
}
