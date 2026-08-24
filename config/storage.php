<?php
/**
 * Consolidated storage configuration.
 */

$appRoot = dirname(__DIR__);

// Support environment overrides for containerization (Linux/Docker friendly)
$secureBasePath = getenv('ACCESSPILOT_SECURE_BASE_PATH') ?: 'C:/inetpub/Desk_secure_files';
$logBasePath = getenv('ACCESSPILOT_LOG_BASE_PATH') ?: 'C:/access_pilot_logs';

return [
    'paths' => [
        'app_root' => $appRoot,
        'config_root' => __DIR__,
        'scripts_root' => $appRoot . '/scripts',
        'powershell_root' => $appRoot . '/scripts/powershell',
        'app_data_root' => $appRoot . '/App_Data',
    ],
    'storage' => [
        'secure_base_path' => $secureBasePath,
        'log_base_path' => $logBasePath,
        'secure_xml_config' => rtrim($secureBasePath, '/\\') . '/accesspilot_deployment_identity.xml',
    ],
    'fail_safe' => [
        'enabled' => true,
        'path' => $appRoot . '/App_Data/internal_admin.json',
    ]
];
