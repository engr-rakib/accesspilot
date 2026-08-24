<?php

require_once __DIR__ . '/../../Application/Support/helpers.php';

/**
 * Resolves a sub-path within the secure vault and ensures the directory exists.
 */
if (!function_exists('resolve_secure_path')) {
    function resolve_secure_path(string $subDir, string $filename = ''): string
    {
        $base = get_secure_base_path();
        $targetDir = $base . DIRECTORY_SEPARATOR . trim($subDir, '/\\');

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        if ($filename === '') {
            return $targetDir;
        }

        return $targetDir . DIRECTORY_SEPARATOR . ltrim($filename, '/\\');
    }
}

if (!function_exists('repo_users_path')) {
    function repo_users_path(): string
    {
        return resolve_secure_path('appusers', 'users.json');
    }
}

if (!function_exists('repo_authenticated_users_path')) {
    function repo_authenticated_users_path(): string
    {
        return resolve_secure_path('appusers', 'authenticated_users.json');
    }
}

if (!function_exists('repo_registration_requests_path')) {
    function repo_registration_requests_path(): string
    {
        return resolve_secure_path('requests', 'registration_requests.json');
    }
}

if (!function_exists('repo_password_reset_requests_path')) {
    function repo_password_reset_requests_path(): string
    {
        return resolve_secure_path('requests', 'password_reset_requests.json');
    }
}

if (!function_exists('repo_roles_path')) {
    function repo_roles_path(): string
    {
        return resolve_secure_path('appusers', 'roles.json');
    }
}

if (!function_exists('repo_forced_logouts_path')) {
    function repo_forced_logouts_path(): string
    {
        return resolve_secure_path('appusers', 'forced_logouts.json');
    }
}

if (!function_exists('repo_ad_user_requests_path')) {
    function repo_ad_user_requests_path(): string
    {
        return resolve_secure_path('requests', 'ad_user_requests.json');
    }
}

if (!function_exists('repo_documentation_guide_path')) {
    function repo_documentation_guide_path(): string
    {
        return resolve_secure_path('appusers', 'documentation_guide.json');
    }
}

if (!function_exists('repo_monitored_servers_path')) {
    function repo_monitored_servers_path(): string
    {
        return resolve_secure_path('monitoring', 'monitored_servers.json');
    }
}

if (!function_exists('repo_profile_img_path')) {
    function repo_profile_img_path(string $filename = ''): string
    {
        return resolve_secure_path('profile_img', $filename);
    }
}

if (!function_exists('repo_monitoring_logs_path')) {
    function repo_monitoring_logs_path(): string
    {
        $base = get_external_log_base();
        $targetDir = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'monitoring_logs';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }
        return $targetDir . DIRECTORY_SEPARATOR . 'monitoring_events.json';
    }
}

if (!function_exists('repo_notifications_dir')) {
    function repo_notifications_dir(): string
    {
        return resolve_secure_path('app_notifications');
    }
}

if (!function_exists('repo_notification_manual_path')) {
    function repo_notification_manual_path(): string
    {
        return repo_notifications_dir() . '/manual_notifications.json';
    }
}

if (!function_exists('repo_notification_state_path')) {
    function repo_notification_state_path(): string
    {
        return repo_notifications_dir() . '/notification_state.json';
    }
}

if (!function_exists('repo_notification_preferences_path')) {
    function repo_notification_preferences_path(): string
    {
        return repo_notifications_dir() . '/notification_preferences.json';
    }
}

if (!function_exists('repo_passwords_dir')) {
    function repo_passwords_dir(): string
    {
        return resolve_secure_path('passwd');
    }
}

if (!function_exists('repo_passwords_path')) {
    function repo_passwords_path(string $scope, ?string $username = null): ?string
    {
        $baseDir = repo_passwords_dir();

        if ($scope === 'personal') {
            $effectiveUsername = trim((string) $username);
            if ($effectiveUsername === '') {
                return null;
            }
            return $baseDir . '/passwords_' . $effectiveUsername . '.json';
        }

        if ($scope === 'global') {
            return $baseDir . '/global_passwords.json';
        }

        return null;
    }
}

if (!function_exists('repo_read_json')) {
    function repo_read_json(string $path, array $default = []): array
    {
        if (!file_exists($path) || !is_readable($path)) {
            return $default;
        }

        $content = file_get_contents($path);
        if ($content === false || trim($content) === '') {
            return $default;
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : $default;
    }
}

if (!function_exists('repo_write_json')) {
    function repo_write_json(string $path, array $data): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }
}

if (!function_exists('repo_ensure_json_file')) {
    function repo_ensure_json_file(string $path, array $default = []): string
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (!file_exists($path)) {
            file_put_contents($path, json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return $path;
    }
}

if (!function_exists('repo_read_users')) {
    function repo_read_users(): array
    {
        return repo_read_json(repo_users_path(), []);
    }
}

if (!function_exists('repo_write_users')) {
    function repo_write_users(array $users): bool
    {
        return repo_write_json(repo_users_path(), $users);
    }
}

if (!function_exists('repo_read_roles')) {
    function repo_read_roles(): array
    {
        return repo_read_json(repo_roles_path(), []);
    }
}

if (!function_exists('repo_write_roles')) {
    function repo_write_roles(array $roles): bool
    {
        return repo_write_json(repo_roles_path(), $roles);
    }
}

if (!function_exists('repo_read_registration_requests')) {
    function repo_read_registration_requests(): array
    {
        return repo_read_json(repo_registration_requests_path(), []);
    }
}

if (!function_exists('repo_write_registration_requests')) {
    function repo_write_registration_requests(array $requests): bool
    {
        return repo_write_json(repo_registration_requests_path(), array_values($requests));
    }
}

if (!function_exists('repo_read_password_reset_requests')) {
    function repo_read_password_reset_requests(): array
    {
        return repo_read_json(repo_password_reset_requests_path(), []);
    }
}

if (!function_exists('repo_write_password_reset_requests')) {
    function repo_write_password_reset_requests(array $requests): bool
    {
        return repo_write_json(repo_password_reset_requests_path(), array_values($requests));
    }
}

if (!function_exists('repo_read_authenticated_users')) {
    function repo_read_authenticated_users(): array
    {
        return repo_read_json(repo_authenticated_users_path(), []);
    }
}

if (!function_exists('repo_write_authenticated_users')) {
    function repo_write_authenticated_users(array $users): bool
    {
        return repo_write_json(repo_authenticated_users_path(), $users);
    }
}

if (!function_exists('repo_read_forced_logouts')) {
    function repo_read_forced_logouts(): array
    {
        return repo_read_json(repo_forced_logouts_path(), []);
    }
}

if (!function_exists('repo_write_forced_logouts')) {
    function repo_write_forced_logouts(array $users): bool
    {
        return repo_write_json(repo_forced_logouts_path(), $users);
    }
}

if (!function_exists('repo_read_ad_user_requests')) {
    function repo_read_ad_user_requests(): array
    {
        return repo_read_json(repo_ad_user_requests_path(), []);
    }
}

if (!function_exists('repo_write_ad_user_requests')) {
    function repo_write_ad_user_requests(array $requests): bool
    {
        return repo_write_json(repo_ad_user_requests_path(), array_values($requests));
    }
}

if (!function_exists('repo_read_notification_manual')) {
    function repo_read_notification_manual(): array
    {
        repo_ensure_json_file(repo_notification_manual_path(), []);
        return repo_read_json(repo_notification_manual_path(), []);
    }
}

if (!function_exists('repo_write_notification_manual')) {
    function repo_write_notification_manual(array $notifications): bool
    {
        repo_ensure_json_file(repo_notification_manual_path(), []);
        return file_put_contents(
            repo_notification_manual_path(),
            json_encode(array_values($notifications), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        ) !== false;
    }
}

if (!function_exists('repo_read_notification_state')) {
    function repo_read_notification_state(): array
    {
        repo_ensure_json_file(repo_notification_state_path(), []);
        return repo_read_json(repo_notification_state_path(), []);
    }
}

if (!function_exists('repo_write_notification_state')) {
    function repo_write_notification_state(array $state): bool
    {
        repo_ensure_json_file(repo_notification_state_path(), []);
        return file_put_contents(
            repo_notification_state_path(),
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        ) !== false;
    }
}

if (!function_exists('repo_read_notification_preferences')) {
    function repo_read_notification_preferences(): array
    {
        repo_ensure_json_file(repo_notification_preferences_path(), []);
        return repo_read_json(repo_notification_preferences_path(), []);
    }
}

if (!function_exists('repo_write_notification_preferences')) {
    function repo_write_notification_preferences(array $preferences): bool
    {
        repo_ensure_json_file(repo_notification_preferences_path(), []);
        return file_put_contents(
            repo_notification_preferences_path(),
            json_encode($preferences, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        ) !== false;
    }
}

if (!function_exists('repo_read_password_entries')) {
    function repo_read_password_entries(string $scope, ?string $username = null): array
    {
        $path = repo_passwords_path($scope, $username);
        if ($path === null) {
            return [];
        }

        repo_ensure_json_file($path, []);
        return repo_read_json($path, []);
    }
}

if (!function_exists('repo_read_monitored_servers')) {
    function repo_read_monitored_servers(): array
    {
        repo_ensure_json_file(repo_monitored_servers_path(), []);
        return repo_read_json(repo_monitored_servers_path(), []);
    }
}

if (!function_exists('repo_write_monitored_servers')) {
    function repo_write_monitored_servers(array $servers): bool
    {
        repo_ensure_json_file(repo_monitored_servers_path(), []);
        return repo_write_json(repo_monitored_servers_path(), $servers);
    }
}

if (!function_exists('repo_read_monitoring_logs')) {
    function repo_read_monitoring_logs(int $limit = 0): array
    {
        repo_ensure_json_file(repo_monitoring_logs_path(), []);
        $logs = repo_read_json(repo_monitoring_logs_path(), []);
        if ($limit > 0 && count($logs) > $limit) {
            return array_slice($logs, -$limit);
        }
        return $logs;
    }
}

if (!function_exists('repo_write_monitoring_logs')) {
    function repo_write_monitoring_logs(array $logs): bool
    {
        repo_ensure_json_file(repo_monitoring_logs_path(), []);
        // Keep only last 1000 logs to prevent bloat
        if (count($logs) > 1000) {
            $logs = array_slice($logs, -1000);
        }
        return repo_write_json(repo_monitoring_logs_path(), $logs);
    }
}

if (!function_exists('repo_write_password_entries')) {
    function repo_write_password_entries(array $entries, string $scope, ?string $username = null): bool
    {
        $path = repo_passwords_path($scope, $username);
        if ($path === null) {
            return false;
        }

        repo_ensure_json_file($path, []);
        return file_put_contents(
            $path,
            json_encode(is_array($entries) ? array_values($entries) : [], JSON_PRETTY_PRINT)
        ) !== false;
    }
}
