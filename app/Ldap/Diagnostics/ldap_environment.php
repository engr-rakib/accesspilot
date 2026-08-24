<?php

require_once __DIR__ . '/../../Application/Support/helpers.php';

if (!function_exists('ldap_environment_report')) {
    function ldap_environment_report(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'ldap_extension_loaded' => extension_loaded('ldap'),
            'ldap_module_root' => dirname(__DIR__),
            'config_defaults' => app_root('config/ldap.php'),
            'config_operations' => app_root('config/ldap/ldap_operations.php'),
            'secure_vault_subdir' => 'ldap/',
        ];
    }
}
