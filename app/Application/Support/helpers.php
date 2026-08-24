<?php

if (!function_exists('vault_ensure_all_dirs')) {
    function vault_ensure_all_dirs(): void {
        $base = rtrim(str_replace('\\', '/', get_secure_base_path()), '/');
        $dirs = [
            'config', 'api',
            'appusers', 'ldap', 'ldap/secrets',
            'requests', 'passwd', 'profile_img',
            'monitoring', 'app_notifications',
            'deployment_active_license', 'vendor_issued_licenses', 'vendor_signing_keys',
        ];
        foreach ($dirs as $dir) {
            $path = $base . '/' . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
        }
    }
}

if (!function_exists('vault_migrate_existing_config')) {
    function vault_migrate_existing_config(): void {
        $overrideFile = vault_config_path() . '/app_overrides.php';
        if (file_exists($overrideFile)) return;

        $codebaseFile = app_root('config/app.php');
        if (!file_exists($codebaseFile)) return;
        $codebaseCfg = include $codebaseFile;
        if (!is_array($codebaseCfg)) return;

        $overrides = [];
        foreach (['domain_name', 'org_name', 'base_dn', 'deployment_id', 'default_password', 'active_domain'] as $key) {
            if (isset($codebaseCfg[$key]) && $codebaseCfg[$key] !== '') {
                $overrides[$key] = $codebaseCfg[$key];
            }
        }
        if (isset($codebaseCfg['pwd_reset_use_random'])) {
            $overrides['pwd_reset_use_random'] = $codebaseCfg['pwd_reset_use_random'];
        }
        if (!empty($overrides)) {
            vault_ensure_all_dirs();
            write_vault_config('app_overrides.php', $overrides);
        }
    }
}

if (!function_exists('app_config')) {
    function app_config(): array
    {
        static $config = null;
        if ($config === null) {
            $config = include __DIR__ . '/../../../config/app_config.php';

            // Vault init after config is loaded — prevents recursion
            // (get_secure_base_path → config_get → app_config returns cached config)
            vault_ensure_all_dirs();
            vault_migrate_existing_config();

            // Merge runtime overrides from secure vault (external storage).
            // These override codebase defaults and survive codebase upgrades.
            foreach (['app_overrides.php', 'app_storage.php', 'app_integrations.php'] as $overrideFile) {
                $override = read_vault_config($overrideFile);
                if (!empty($override)) {
                    $config = array_replace_recursive($config, $override);
                }
            }

            // Merge API configuration from vault/api/ (dedicated API config directory).
            // This is the authoritative source — codebase integrations.php is fallback only.
            $apiIntegrations = vault_api_config('integrations.php');
            if (!empty($apiIntegrations)) {
                $config = array_replace_recursive($config, $apiIntegrations);
            }
        }
        return $config;
    }
}

if (!function_exists('config_get')) {
    function config_get(string $key, $default = null)
    {
        $value = app_config();
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}

if (!function_exists('app_root')) {
    function app_root(string $path = ''): string
    {
        $root = rtrim(str_replace('\\', '/', dirname(__DIR__, 3)), '/');
        $path = ltrim(str_replace('\\', '/', $path), '/');
        return $path === '' ? $root : $root . '/' . $path;
    }
}

if (!function_exists('detect_base_path')) {
    function detect_base_path(): string
    {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $markers = [
            '/coreAdmin/',
            '/application_auth/',
            '/api/',
            '/assets/',
            '/password_manager/',
            '/includes/',
        ];

        foreach ($markers as $marker) {
            $pos = strpos($scriptName, $marker);
            if ($pos !== false) {
                $basePath = substr($scriptName, 0, $pos);
                return $basePath === '/' ? '' : rtrim($basePath, '/');
            }
        }

        $scriptDir = str_replace('\\', '/', dirname($scriptName));
        return $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $basePath = detect_base_path();
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') {
            return $basePath;
        }
        return rtrim($basePath, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        // Check X-Forwarded-Proto first (when behind reverse proxy)
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $protocol = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']);
        } else {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        }
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $url = $protocol . '://' . $host . base_path($path);
        return rtrim($url, '/');
    }
}

if (!function_exists('route_url')) {
    function route_url(string $path = ''): string
    {
        return base_url($path);
    }
}

if (!function_exists('admin_page_url')) {
    function admin_page_url(string $page = '', array $params = []): string
    {
        $query = [];
        if ($page !== '') {
            $query['page'] = $page;
        }
        if (!empty($params)) {
            $query = array_merge($query, $params);
        }

        $path = 'index.php';
        if (!empty($query)) {
            $path .= '?' . http_build_query($query);
        }

        return route_url($path);
    }
}

if (!function_exists('asset_url')) {
    function asset_url(string $path = ''): string
    {
        $relative = ltrim($path, '/');
        if ($relative === '') {
            return base_url('assets');
        }
        if (str_starts_with($relative, 'assets/')) {
            return base_url($relative);
        }
        return base_url('assets/' . $relative);
    }
}

if (!function_exists('api_url')) {
    function api_url(string $path = ''): string
    {
        $relative = ltrim($path, '/');
        if ($relative === '') {
            return base_url('api/index.php');
        }
        return base_url($relative);
    }
}

if (!function_exists('get_app_name')) {
    function get_app_name(): string
    {
        return 'AccessPilot';
    }
}

if (!function_exists('get_domain_name')) {
    function get_domain_name(): string
    {
        $cfg = (string) config_get('domain_name', '');
        if ($cfg !== '') {
            return $cfg;
        }
        if (function_exists('license_parse_secure_config_metadata')) {
            $metadata = license_parse_secure_config_metadata();
            if (!empty($metadata['Domain'])) {
                return (string) $metadata['Domain'];
            }
        }
        return '';
    }
}
if (!function_exists('get_deployment_id')) {
    function get_deployment_id(): string
    {
        return (string) config_get('deployment_id', '');
    }
}

if (!function_exists('deployment_encryption_key')) {
    function deployment_encryption_key(): string
    {
        $key = (string) config_get('encryption_key', '');
        if (strlen($key) < 32) {
            $key = hash('sha256', $key ?: 'AccessPilotDeploySecretKey2026');
        }
        return substr($key, 0, 32);
    }
}

if (!function_exists('encrypt_deployment_data')) {
    function encrypt_deployment_data(string $plaintext): string
    {
        $key = deployment_encryption_key();
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return bin2hex($iv) . ':' . bin2hex($ciphertext);
    }
}

if (!function_exists('decrypt_deployment_data')) {
    function decrypt_deployment_data(string $encoded): ?string
    {
        $parts = explode(':', $encoded, 2);
        if (count($parts) !== 2) return null;
        $iv = @hex2bin($parts[0]);
        $ciphertext = @hex2bin($parts[1]);
        if ($iv === false || $ciphertext === false || strlen($iv) !== 16) return null;
        $key = deployment_encryption_key();
        $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : null;
    }
}

if (!function_exists('get_external_log_base')) {
    function get_external_log_base(): string
    {
        // 1. Priority: Secure XML metadata
        if (function_exists('license_parse_secure_config_metadata')) {
            $metadata = license_parse_secure_config_metadata();
            if (!empty($metadata['BaseLogPath'])) {
                return (string) $metadata['BaseLogPath'];
            }
        }

        // 2. Fallback: Centralized mapping config
        return (string) config_get('storage.log_base_path', 'C:/access_pilot_logs');
    }
}

if (!function_exists('configured_secure_config_path')) {
    function configured_secure_config_path(): string
    {
        return (string) config_get('storage.secure_xml_config', 'C:\inetpub\Desk_secure_files\accesspilot_deployment_identity.xml');
    }
}

if (!function_exists('legacy_secure_config_path')) {
    function legacy_secure_config_path(): string
    {
        $configured = configured_secure_config_path();
        $configuredDir = dirname($configured);
        return $configuredDir . '/umkeyconfig.xml';
    }
}

if (!function_exists('resolved_secure_config_path')) {
    function resolved_secure_config_path(): string
    {
        $configured = configured_secure_config_path();
        if ($configured !== '' && file_exists($configured)) {
            return $configured;
        }

        $legacy = legacy_secure_config_path();
        if ($legacy !== '' && file_exists($legacy)) {
            $targetDir = dirname($configured);
            if (($configured !== '' && is_dir($targetDir)) || @mkdir($targetDir, 0775, true)) {
                @copy($legacy, $configured);
                if (file_exists($configured)) {
                    return $configured;
                }
            }

            return $legacy;
        }

        return $configured;
    }
}

if (!function_exists('resolved_log_path')) {
    function resolved_log_path(string $filename = '', ?string $date = null): string
    {
        $baseLogPath = get_external_log_base();

        // 3. Append the specific audit directory
        $auditDir = rtrim($baseLogPath, '/\\') . DIRECTORY_SEPARATOR . 'app_audit_logs';
        
        // 4. Ensure directory exists
        if (!is_dir($auditDir)) {
            @mkdir($auditDir, 0775, true);
        }

        if ($filename === '') {
            return $auditDir;
        }

        // 5. Handle day-wise filename if requested
        if ($filename === 'audit.csv') {
            $logDate = $date ?: date('Y-m-d');
            $filename = "audit-{$logDate}.csv";
        }

        return $auditDir . DIRECTORY_SEPARATOR . ltrim($filename, '/\\');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $storageRoot = config_get('paths.app_data_root', app_root('App_Data'));
        $relative = ltrim(str_replace('\\', '/', $path), '/');
        return $relative === '' ? $storageRoot : rtrim($storageRoot, '/') . '/' . $relative;
    }
}

if (!function_exists('secure_path')) {
    function secure_path(string $key): ?string
    {
        return config_get('secure_paths.' . $key);
    }
}

if (!function_exists('script_path')) {
    function script_path(string $key): ?string
    {
        return config_get('script_paths.' . $key);
    }
}

if (!function_exists('include_path')) {
    function include_path(string $path): string
    {
        return app_root(ltrim($path, '/'));
    }
}

if (!function_exists('redirect_with_flash')) {
    function redirect_with_flash(string $page, string $message, bool $is_success, array $params = [])
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_is_success'] = $is_success;

        $query_string = http_build_query(array_merge(['page' => $page], $params));
        $location = 'index.php?' . $query_string;

        error_log("Redirecting to: " . $location . " with message: " . $message);
        header('Location: ' . $location);
        exit();
    }
}

if (!function_exists('get_secure_base_path')) {
    function get_secure_base_path(): string {
        return (string) config_get('storage.secure_base_path', 'C:/inetpub/Desk_secure_files');
    }
}

if (!function_exists('vault_config_path')) {
    function vault_config_path(): string {
        return get_secure_base_path() . '/config';
    }
}

if (!function_exists('vault_config_file')) {
    function vault_config_file(string $name): string {
        return vault_config_path() . '/' . ltrim($name, '/');
    }
}

if (!function_exists('read_vault_config')) {
    function read_vault_config(string $name, array $default = []): array {
        $path = vault_config_file($name);
        if (!file_exists($path) || !is_readable($path)) return $default;
        $data = include $path;
        return is_array($data) ? $data : $default;
    }
}

if (!function_exists('write_vault_config')) {
    function write_vault_config(string $name, array $config): bool {
        $dir = vault_config_path();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = vault_config_file($name);
        $content = '<?php return ' . var_export($config, true) . ';' . PHP_EOL;
        $written = file_put_contents($path, $content) !== false;
        if ($written) {
            @opcache_invalidate($path, true);
        }
        return $written;
    }
}

if (!function_exists('vault_shared_config_path')) {
    function vault_shared_config_path(): string {
        return vault_config_file('shared_config.json');
    }
}

if (!function_exists('vault_api_path')) {
    function vault_api_path(): string {
        return get_secure_base_path() . '/api';
    }
}

if (!function_exists('vault_api_config')) {
    function vault_api_config(string $name): array {
        $path = vault_api_path() . '/' . ltrim($name, '/');
        if (!file_exists($path) || !is_readable($path)) return [];
        $data = include $path;
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('write_vault_api_config')) {
    function write_vault_api_config(string $name, array $config): bool {
        $dir = vault_api_path();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . '/' . ltrim($name, '/');
        $content = '<?php return ' . var_export($config, true) . ';' . PHP_EOL;
        $written = file_put_contents($path, $content) !== false;
        if ($written) {
            @opcache_invalidate($path, true);
        }
        return $written;
    }
}
