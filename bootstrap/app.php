<?php

require_once __DIR__ . '/../app/Application/Support/helpers.php';
require_once __DIR__ . '/../app/Infrastructure/Persistence/repositories.php';
require_once __DIR__ . '/../app/Domain/Audit/audit_service.php';
require_once __DIR__ . '/../app/Domain/RBAC/rbac_service.php';

function core_admin_bootstrap(): array
{
    global $app_config;

    $app_config = include __DIR__ . '/../config/app_config.php';

    if (session_status() !== PHP_SESSION_ACTIVE) {
        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        $isHttps = $forwardedProto === 'https'
            || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Dynamically resolve and ensure the base secure directory exists
    $secureAppusersDir = resolve_secure_path('appusers');
    
    if ($secureAppusersDir === '') {
        exit('Critical Error: Secure directory path missing.');
    }

    core_admin_ensure_runtime_files();

    return [
        'app_config' => $app_config,
        'secure_appusers_dir' => $secureAppusersDir,
    ];
}

function core_admin_ensure_runtime_files(): void
{
    // Default: accesspilot@123
    $defaultAdminPasswordHash = '$2y$12$MPbJH.1uNxFcAiIUuheFJeItKiTSjY8t087IcF2n3uUfJufseEf0.';
    $setupLock = __DIR__ . '/../App_Data/setup_complete.lock';

    // ONLY initialize default admin if this is the very first run (Lock missing)
    if (!file_exists($setupLock)) {
        repo_ensure_json_file(
            repo_users_path(),
            [
                'admin' => [
                    'password' => $defaultAdminPasswordHash,
                    'email' => 'admin@accesspilot.com',
                    'role' => 'core_admin',
                    'system_access' => true,
                    'full_name' => 'Default Administrator',
                    'must_change_password' => true,
                ],
            ]
        );
        
        // Create the lock immediately
        file_put_contents($setupLock, date('Y-m-d H:i:s'));
    }

    repo_ensure_json_file(
        repo_roles_path(),
        [
            'core_admin' => [
                'description' => 'Full access to all system features.',
                'permissions' => ['*'],
            ],
            'user' => [
                'description' => 'Standard user, can view dashboard and manage personal passwords.',
                'permissions' => ['page_dashboard', 'page_password_manager', 'card_my_passwords'],
            ],
        ]
    );

    repo_ensure_json_file(repo_authenticated_users_path(), []);
    repo_ensure_json_file(repo_registration_requests_path(), []);
    repo_ensure_json_file(repo_forced_logouts_path(), []);
    repo_ensure_json_file((string) repo_passwords_path('global'), []);
}
