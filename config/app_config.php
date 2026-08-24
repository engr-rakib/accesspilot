<?php
/**
 * config/app_config.php
 * 
 * Main configuration aggregator.
 */

$config = [];

foreach ([
    'app.php',
    'license.php',
    'storage.php',      // Handles all paths and mapping
    'ldap.php',
    'ldap/ldap_operations.php',
    'powershell.php',
    'ui.php',
    'features.php',
    'mailer_config.php',
] as $configFile) {
    $loaded = include __DIR__ . '/' . $configFile;
    if (is_array($loaded)) {
        $config = array_replace_recursive($config, $loaded);
    }
}

$config['menu'] = include __DIR__ . '/menu_config.php';
$config['components'] = include __DIR__ . '/components_config.php';

return $config;
