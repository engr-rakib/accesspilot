<?php

require_once __DIR__ . '/../../Application/Support/helpers.php';
require_once __DIR__ . '/../../Infrastructure/Persistence/repositories.php';

if (!function_exists('ldap_config_dir')) {
    function ldap_config_dir(): string
    {
        return resolve_secure_path('ldap');
    }
}

if (!function_exists('ldap_config_file_path')) {
    function ldap_config_file_path(): string
    {
        return ldap_config_dir() . DIRECTORY_SEPARATOR . 'config.json';
    }
}

if (!function_exists('ldap_bind_secret_path')) {
    function ldap_bind_secret_path(): string
    {
        return ldap_config_dir() . DIRECTORY_SEPARATOR . 'bind_secret.json';
    }
}

if (!function_exists('ldap_last_test_path')) {
    function ldap_last_test_path(): string
    {
        return ldap_config_dir() . DIRECTORY_SEPARATOR . 'last_test.json';
    }
}

if (!function_exists('ldap_default_config')) {
    function ldap_default_config(): array
    {
        return (array) config_get('ldap', []);
    }
}

if (!function_exists('ldap_read_config')) {
    function ldap_read_config(): array
    {
        $defaults = ldap_default_config();
        $path = ldap_config_file_path();

        $baseConfig = [];
        if (file_exists($path) && is_readable($path)) {
            $content = (string) file_get_contents($path);
            if (str_starts_with($content, "\xEF\xBB\xBF")) {
                $content = substr($content, 3);
            }
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $baseConfig = $decoded;
            }
        }

        // Ensure active_domain_ad_name is populated for legacy configs
        if (isset($baseConfig['active_domain']) && !isset($baseConfig['active_domain_ad_name'])) {
            $baseConfig['active_domain_ad_name'] = function_exists('ldap_domain_ad_name') ? ldap_domain_ad_name((string)$baseConfig['active_domain']) : (string)$baseConfig['active_domain'];
            static $adNameWritten = false;
            if (!$adNameWritten) {
                $adNameWritten = true;
                @ldap_write_config($baseConfig);
            }
        }

        $merged = array_replace($defaults, $baseConfig);

        $activeKey = (string) ($merged['active_domain'] ?? 'default');
        $domains = ldap_get_domains();

        $activeDomain = null;
        foreach ($domains as $d) {
            if (($d['key'] ?? '') === $activeKey) { $activeDomain = $d; break; }
        }

        if ($activeDomain !== null) {
            $exchangeConfig = $activeDomain['exchange'] ?? [];
            if (!is_array($exchangeConfig)) {
                $exchangeConfig = [];
            }
            $exchangeSecret = ldap_read_exchange_secret($activeKey);
            if ($exchangeSecret !== '') {
                $exchangeConfig['ps_password'] = $exchangeSecret;
            } elseif (!empty($exchangeConfig['ps_password'])) {
                // Backward compatibility for any legacy domains.json entries.
                $exchangeConfig['ps_password'] = (string) $exchangeConfig['ps_password'];
            }

            $merged = array_replace($merged, [
                'enabled' => !empty($activeDomain['enabled']),
                'backend' => (string) ($activeDomain['backend'] ?? $merged['backend'] ?? 'powershell'),
                'host' => (string) ($activeDomain['host'] ?? $merged['host'] ?? ''),
                'port' => (int) ($activeDomain['port'] ?? $merged['port'] ?? 389),
                'use_tls' => !empty($activeDomain['use_tls']),
                'base_dn' => (string) ($activeDomain['base_dn'] ?? $merged['base_dn'] ?? ''),
                'bind_dn' => (string) ($activeDomain['bind_dn'] ?? $merged['bind_dn'] ?? ''),
                'user_search_base' => (string) ($activeDomain['user_search_base'] ?? $merged['user_search_base'] ?? ''),
                'naming' => $activeDomain['naming'] ?? [],
                'connect_timeout' => (int) ($activeDomain['connect_timeout'] ?? $merged['connect_timeout'] ?? 5),
                'exchange' => $exchangeConfig,
            ]);
        }

        return $merged;
    }
}

if (!function_exists('ldap_write_config')) {
    function ldap_write_config(array $config): bool
    {
        ldap_config_dir();
        $payload = array_replace(ldap_default_config(), $config);
        unset($payload['bind_password']);

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        return file_put_contents(ldap_config_file_path(), $json) !== false;
    }
}

if (!function_exists('ldap_domain_secret_path')) {
    function ldap_domain_secret_path(string $key): string {
        return ldap_config_dir() . DIRECTORY_SEPARATOR . 'secrets' . DIRECTORY_SEPARATOR . $key . '.json';
    }
}

if (!function_exists('ldap_exchange_secret_path')) {
    function ldap_exchange_secret_path(string $key): string {
        return ldap_config_dir() . DIRECTORY_SEPARATOR . 'exchange_secrets' . DIRECTORY_SEPARATOR . $key . '.json';
    }
}

if (!function_exists('ldap_encrypt_password')) {
    function ldap_encrypt_password(string $plaintext): string {
        $key = function_exists('deployment_encryption_key') ? deployment_encryption_key() : '';
        if (strlen($key) < 32) return $plaintext;
        $iv = random_bytes(16);
        $ciphertext = @openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) return $plaintext;
        return 'enc:' . bin2hex($iv) . ':' . bin2hex($ciphertext);
    }
}

if (!function_exists('ldap_decrypt_password')) {
    function ldap_decrypt_password(string $stored): string {
        if (!str_starts_with($stored, 'enc:')) return $stored;
        $parts = explode(':', substr($stored, 4), 2);
        if (count($parts) !== 2) return $stored;
        $iv = @hex2bin($parts[0]);
        $ciphertext = @hex2bin($parts[1]);
        if ($iv === false || $ciphertext === false || strlen($iv) !== 16) return $stored;
        $key = function_exists('deployment_encryption_key') ? deployment_encryption_key() : '';
        if (strlen($key) < 32) return $stored;
        $decrypted = @openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : $stored;
    }
}

if (!function_exists('ldap_read_domain_secret')) {
    function ldap_read_domain_secret(string $key): string {
        $path = ldap_domain_secret_path($key);
        if (!file_exists($path) || !is_readable($path)) return '';
        $decoded = json_decode((string) file_get_contents($path), true);
        $stored = (string) ($decoded['password'] ?? '');
        return ldap_decrypt_password($stored);
    }
}

if (!function_exists('ldap_read_exchange_secret')) {
    function ldap_read_exchange_secret(string $key): string {
        $path = ldap_exchange_secret_path($key);
        if (!file_exists($path) || !is_readable($path)) return '';
        $decoded = json_decode((string) file_get_contents($path), true);
        $stored = (string) ($decoded['password'] ?? '');
        return ldap_decrypt_password($stored);
    }
}

if (!function_exists('ldap_write_domain_secret')) {
    function ldap_write_domain_secret(string $key, string $password): bool {
        $path = ldap_domain_secret_path($key);
        $dir = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        $encrypted = ldap_encrypt_password($password);
        $payload = json_encode(['password' => $encrypted], JSON_PRETTY_PRINT);
        $result = file_put_contents($path, $payload) !== false;
        if ($result) {
            @chmod($path, 0640);
            @chmod($dir, 0750);
        }
        return $result;
    }
}

if (!function_exists('ldap_write_exchange_secret')) {
    function ldap_write_exchange_secret(string $key, string $password): bool {
        $path = ldap_exchange_secret_path($key);
        $dir = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        $encrypted = ldap_encrypt_password($password);
        $payload = json_encode(['password' => $encrypted], JSON_PRETTY_PRINT);
        $result = file_put_contents($path, $payload) !== false;
        if ($result) {
            @chmod($path, 0640);
            @chmod($dir, 0750);
        }
        return $result;
    }
}

if (!function_exists('ldap_has_exchange_password')) {
    function ldap_has_exchange_password(string $key): bool
    {
        return ldap_read_exchange_secret($key) !== '';
    }
}

if (!function_exists('ldap_health_admin_secret_path')) {
    function ldap_health_admin_secret_path(string $key): string {
        return ldap_config_dir() . DIRECTORY_SEPARATOR . 'health_secrets' . DIRECTORY_SEPARATOR . $key . '.json';
    }
}

if (!function_exists('ldap_read_health_admin_secret')) {
    function ldap_read_health_admin_secret(string $key): string {
        $path = ldap_health_admin_secret_path($key);
        if (!file_exists($path) || !is_readable($path)) return '';
        $decoded = json_decode((string) file_get_contents($path), true);
        $stored = (string) ($decoded['password'] ?? '');
        return ldap_decrypt_password($stored);
    }
}

if (!function_exists('ldap_write_health_admin_secret')) {
    function ldap_write_health_admin_secret(string $key, string $password): bool {
        $path = ldap_health_admin_secret_path($key);
        $dir = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        $encrypted = ldap_encrypt_password($password);
        $payload = json_encode(['password' => $encrypted], JSON_PRETTY_PRINT);
        $result = file_put_contents($path, $payload) !== false;
        if ($result) {
            @chmod($path, 0640);
            @chmod($dir, 0750);
        }
        return $result;
    }
}

if (!function_exists('ldap_has_health_admin_password')) {
    function ldap_has_health_admin_password(string $key): bool {
        return ldap_read_health_admin_secret($key) !== '';
    }
}

if (!function_exists('ldap_health_admin_username_path')) {
    function ldap_health_admin_username_path(string $key): string {
        return ldap_config_dir() . DIRECTORY_SEPARATOR . 'health_secrets' . DIRECTORY_SEPARATOR . $key . '_username.json';
    }
}

if (!function_exists('ldap_read_health_admin_username')) {
    function ldap_read_health_admin_username(string $key): string {
        $path = ldap_health_admin_username_path($key);
        if (!file_exists($path) || !is_readable($path)) return '';
        $decoded = json_decode((string) file_get_contents($path), true);
        return (string) ($decoded['username'] ?? '');
    }
}

if (!function_exists('ldap_write_health_admin_username')) {
    function ldap_write_health_admin_username(string $key, string $username): bool {
        $path = ldap_health_admin_username_path($key);
        $dir = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        $payload = json_encode(['username' => $username], JSON_PRETTY_PRINT);
        $result = file_put_contents($path, $payload) !== false;
        if ($result) {
            @chmod($path, 0640);
            @chmod($dir, 0750);
        }
        return $result;
    }
}

if (!function_exists('ldap_has_bind_password')) {
    function ldap_has_bind_password(): bool
    {
        $activeKey = function_exists('ldap_active_domain_key') ? ldap_active_domain_key() : 'default';
        $secret = ldap_read_domain_secret($activeKey);
        if ($secret !== '') return true;
        $path = ldap_bind_secret_path();
        if (!file_exists($path) || !is_readable($path)) return false;
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) && isset($decoded['password']) && $decoded['password'] !== '';
    }
}

if (!function_exists('ldap_read_bind_password')) {
    function ldap_read_bind_password(): string
    {
        $activeKey = function_exists('ldap_active_domain_key') ? ldap_active_domain_key() : 'default';
        $secret = ldap_read_domain_secret($activeKey);
        if ($secret !== '') return $secret;
        $path = ldap_bind_secret_path();
        if (!file_exists($path) || !is_readable($path)) return '';
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded) || !isset($decoded['password'])) return '';
        return (string) $decoded['password'];
    }
}

if (!function_exists('ldap_write_bind_password')) {
    function ldap_write_bind_password(string $password): bool
    {
        $activeKey = function_exists('ldap_active_domain_key') ? ldap_active_domain_key() : 'default';
        return ldap_write_domain_secret($activeKey, $password);
    }
}

if (!function_exists('ldap_read_last_test')) {
    function ldap_read_last_test(): array
    {
        $path = ldap_last_test_path();
        if (!file_exists($path) || !is_readable($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('ldap_write_last_test')) {
    function ldap_write_last_test(array $result): bool
    {
        ldap_config_dir();
        $payload = array_replace([
            'at' => gmdate('c'),
            'success' => false,
            'message' => '',
        ], $result);
        $payload['at'] = gmdate('c');

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        return file_put_contents(ldap_last_test_path(), $json) !== false;
    }
}

if (!function_exists('ldap_public_config')) {
    function ldap_public_config(): array
    {
        $config = ldap_read_config();

        return [
            'enabled' => (bool) ($config['enabled'] ?? false),
            'backend' => (string) ($config['backend'] ?? 'powershell'),
            'acknowledged' => !empty($config['acknowledged']),
            'host' => (string) ($config['host'] ?? ''),
            'port' => (int) ($config['port'] ?? 389),
            'use_tls' => (bool) ($config['use_tls'] ?? false),
            'base_dn' => (string) ($config['base_dn'] ?? ''),
            'bind_dn' => (string) ($config['bind_dn'] ?? ''),
            'user_search_base' => (string) ($config['user_search_base'] ?? ''),
            'has_bind_password' => ldap_has_bind_password(),
        ];
    }
}

if (!function_exists('ldap_domains_file_path')) {
    function ldap_domains_file_path(): string {
        return ldap_config_dir() . DIRECTORY_SEPARATOR . 'domains.json';
    }
}

if (!function_exists('ldap_get_domains')) {
    function ldap_get_domains(): array {
        $path = ldap_domains_file_path();
        if (!file_exists($path) || !is_readable($path)) return [];
        $content = (string) file_get_contents($path);
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('ldap_write_domains')) {
    function ldap_write_domains(array $domains): bool {
        $path = ldap_domains_file_path();
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $json = json_encode($domains, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return $json !== false && file_put_contents($path, $json) !== false;
    }
}

if (!function_exists('ldap_upsert_domain')) {
    function ldap_upsert_domain(array $domain): bool {
        $key = $domain['key'] ?? '';
        if ($key === '') return false;
        $domains = ldap_get_domains();

        $existingIdx = null;
        foreach ($domains as $i => $d) {
            if (($d['key'] ?? '') === $key) { $existingIdx = $i; break; }
        }

        if ($existingIdx === null) {
            $maxDomains = 1;
            if (function_exists('license_get_status')) {
                $licStatus = license_get_status();
                $maxDomains = (int) ($licStatus['max_domains'] ?? 1);
            }
            if ($maxDomains > 0 && count($domains) >= $maxDomains) {
                return false;
            }
            $domains[] = $domain;
        } else {
            $domains[$existingIdx] = $domain;
        }

        return ldap_write_domains($domains);
    }
}

if (!function_exists('ldap_get_domain')) {
    function ldap_get_domain(string $key): ?array {
        $domains = ldap_get_domains();
        foreach ($domains as $d) {
            if (($d['key'] ?? '') === $key) return $d;
        }
        return null;
    }
}

if (!function_exists('ldap_delete_domain')) {
    function ldap_delete_domain(string $key): bool {
        $activeKey = ldap_active_domain_key();
        if ($key === $activeKey) return false;
        $domains = ldap_get_domains();
        $filtered = [];
        foreach ($domains as $d) {
            if (($d['key'] ?? '') !== $key) $filtered[] = $d;
        }
        if (count($filtered) === count($domains)) return false;
        return ldap_write_domains($filtered);
    }
}

if (!function_exists('ldap_active_domain_key')) {
    function ldap_active_domain_key(): string {
        $config = ldap_read_config();
        return (string) ($config['active_domain'] ?? 'default');
    }
}

if (!function_exists('ldap_domain_ad_name')) {
    function ldap_domain_ad_name(string $key): string {
        $domain = ldap_get_domain($key);
        if ($domain === null) return $key;
        $baseDn = $domain['base_dn'] ?? '';
        if ($baseDn === '') return $key;
        $parts = [];
        preg_match_all('/DC\s*=\s*([^,]+)/i', $baseDn, $parts);
        if (empty($parts[1])) return $key;
        return strtolower(implode('.', $parts[1]));
    }
}

if (!function_exists('ldap_active_domain_ad_name')) {
    function ldap_active_domain_ad_name(): string {
        return ldap_domain_ad_name(ldap_active_domain_key());
    }
}

if (!function_exists('ldap_set_active_domain')) {
    function ldap_set_active_domain(string $key): bool {
        $config = ldap_read_config();
        $config['active_domain'] = $key;
        $config['active_domain_ad_name'] = ldap_domain_ad_name($key);
        return ldap_write_config($config);
    }
}

if (!function_exists('ldap_domain_limit_message')) {
    function ldap_domain_limit_message(): string {
        $max = 1;
        $used = count(ldap_get_domains());
        if (function_exists('license_get_status')) {
            $status = license_get_status();
            $max = (int) ($status['max_domains'] ?? 1);
        }
        if ($max === 0) return 'Unlimited domains (licensed)';
        $remaining = max(0, $max - $used);
        return "Domains: {$used} / {$max} used, {$remaining} remaining";
    }
}

if (!function_exists('ldap_migrate_legacy_config')) {
    function ldap_migrate_legacy_config(): void {
        $domainsFile = ldap_domains_file_path();
        if (file_exists($domainsFile)) return;

        $legacyFile = ldap_config_file_path();
        if (!file_exists($legacyFile)) {
            ldap_write_domains([]);
            return;
        }

        $legacy = json_decode((string) file_get_contents($legacyFile), true);
        if (!is_array($legacy)) { ldap_write_domains([]); return; }

        $domain = [
            'key' => 'default',
            'label' => $legacy['base_dn'] ?? 'Default AD',
            'host' => $legacy['host'] ?? '',
            'port' => (int) ($legacy['port'] ?? 389),
            'use_tls' => !empty($legacy['use_tls']),
            'base_dn' => $legacy['base_dn'] ?? '',
            'user_search_base' => $legacy['user_search_base'] ?? '',
            'bind_dn' => $legacy['bind_dn'] ?? '',
            'enabled' => !empty($legacy['enabled']),
            'backend' => $legacy['backend'] ?? 'powershell',
        ];
        ldap_upsert_domain($domain);

        $oldPw = '';
        $oldSecretPath = ldap_bind_secret_path();
        if (file_exists($oldSecretPath)) {
            $oldDecoded = json_decode((string) file_get_contents($oldSecretPath), true);
            if (is_array($oldDecoded) && !empty($oldDecoded['password'])) {
                $oldPw = (string) $oldDecoded['password'];
            }
        }
        if ($oldPw !== '') ldap_write_domain_secret('default', $oldPw);
    }
}
ldap_migrate_legacy_config();
