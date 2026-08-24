<?php

if (!defined('_CORE_ADMIN_') && !defined('API_GATEWAY')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/../../Infrastructure/Persistence/repositories.php';
require_once __DIR__ . '/../../Application/Support/helpers.php';
require_once __DIR__ . '/../Licensing/license_service.php';

function notifications_get_storage_paths(): array
{
    static $paths = null;

    if ($paths !== null) {
        return $paths;
    }

    // Use the dynamic directory from the repository layer (Secure Vault)
    $base_dir = repo_notifications_dir();

    $paths = [
        'base_dir' => $base_dir,
        'manual' => $base_dir . DIRECTORY_SEPARATOR . 'manual_notifications.json',
        'state' => $base_dir . DIRECTORY_SEPARATOR . 'notification_state.json',
        'preferences' => $base_dir . DIRECTORY_SEPARATOR . 'notification_preferences.json',
    ];

    foreach (['manual', 'state', 'preferences'] as $key) {
        if (!file_exists($paths[$key])) {
            file_put_contents($paths[$key], json_encode([]));
        }
    }

    return $paths;
}

function notifications_read_json(string $path, $default = [])
{
    if (!file_exists($path)) {
        return $default;
    }

    $content = file_get_contents($path);
    if ($content === false || trim($content) === '') {
        return $default;
    }

    $decoded = json_decode($content, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
}

function notifications_write_json(string $path, $data): bool
{
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
}

function notifications_default_preferences(): array
{
    return [
        'show_toasts' => true,
        'categories' => [
            'announcement' => true,
            'requests' => true,
            'activity' => true,
            'ad_actions' => true,
            'reports' => true,
            'security' => true,
        ],
    ];
}

function notifications_get_category_permission_map(): array
{
    return [
        'announcement' => ['notif_category_announcement'],
        'requests' => ['notif_category_requests'],
        'activity' => ['notif_category_activity'],
        'ad_actions' => ['notif_category_ad_actions'],
        'reports' => ['notif_category_reports'],
        'security' => ['notif_category_security'],
    ];
}

function notifications_apply_category_permissions(array $required_permissions, string $category): array
{
    $category_map = notifications_get_category_permission_map();
    $category_permissions = $category_map[$category] ?? [];

    return array_values(array_unique(array_filter(array_merge(
        ['card_notification_center'],
        $required_permissions,
        $category_permissions
    ))));
}

function notifications_get_preferences(string $username): array
{
    $all = repo_read_notification_preferences();
    $saved = is_array($all[$username] ?? null) ? $all[$username] : [];
    return array_replace_recursive(notifications_default_preferences(), $saved);
}

function notifications_save_preferences(string $username, array $preferences): bool
{
    $all = repo_read_notification_preferences();
    $all[$username] = array_replace_recursive(notifications_default_preferences(), $preferences);
    return repo_write_notification_preferences($all);
}

function notifications_get_state(string $username): array
{
    $all = repo_read_notification_state();
    $state = is_array($all[$username] ?? null) ? $all[$username] : [];

    return [
        'read_ids' => array_values(array_unique(array_map('strval', $state['read_ids'] ?? []))),
        'dismissed_toast_ids' => array_values(array_unique(array_map('strval', $state['dismissed_toast_ids'] ?? []))),
        'hidden_ids' => array_values(array_unique(array_map('strval', $state['hidden_ids'] ?? []))),
    ];
}

function notifications_get_all_states(): array
{
    $all = repo_read_notification_state();
    return is_array($all) ? $all : [];
}

function notifications_save_state(string $username, array $state): bool
{
    $all = repo_read_notification_state();
    $all[$username] = [
        'read_ids' => array_values(array_unique(array_map('strval', $state['read_ids'] ?? []))),
        'dismissed_toast_ids' => array_values(array_unique(array_map('strval', $state['dismissed_toast_ids'] ?? []))),
        'hidden_ids' => array_values(array_unique(array_map('strval', $state['hidden_ids'] ?? []))),
    ];
    return repo_write_notification_state($all);
}

function notifications_get_roles(): array
{
    return repo_read_roles();
}

function notifications_get_users(): array
{
    require_once __DIR__ . '/../UserManagement/user_management_service.php';
    $users = readUsers();
    return is_array($users) ? $users : [];
}

function notifications_user_has_any_permission(array $required_permissions): bool
{
    if (!$required_permissions) {
        return true;
    }

    foreach ($required_permissions as $permission) {
        if (function_exists('has_permission') && has_permission($permission)) {
            return true;
        }
    }

    return false;
}

function notifications_user_has_all_permissions(array $required_permissions): bool
{
    foreach ($required_permissions as $permission) {
        if (!function_exists('has_permission') || !has_permission($permission)) {
            return false;
        }
    }

    return true;
}

function notifications_is_visible_to_user(array $notification, string $username, string $role): bool
{
    if (!empty($notification['required_permissions']) && !notifications_user_has_any_permission($notification['required_permissions'])) {
        return false;
    }

    if (!empty($notification['required_all_permissions']) && !notifications_user_has_all_permissions($notification['required_all_permissions'])) {
        return false;
    }

    $audience = $notification['audience'] ?? ['type' => 'all'];
    $type = $audience['type'] ?? 'all';

    if ($type === 'all') {
        return true;
    }

    if ($type === 'roles') {
        $roles = array_map('strval', $audience['roles'] ?? []);
        return in_array($role, $roles, true);
    }

    if ($type === 'users') {
        $users = array_map('strval', $audience['users'] ?? []);
        return in_array($username, $users, true);
    }

    return false;
}

function notifications_normalize(array $notification): array
{
    $category = (string)($notification['category'] ?? 'announcement');
    $required_permissions = array_values(array_unique(array_map('strval', $notification['required_permissions'] ?? [])));
    $required_all_permissions = notifications_apply_category_permissions(
        array_values(array_unique(array_map('strval', $notification['required_all_permissions'] ?? []))),
        $category
    );

    return [
        'id' => (string)($notification['id'] ?? uniqid('notif_', true)),
        'source' => (string)($notification['source'] ?? 'system'),
        'category' => $category,
        'title' => (string)($notification['title'] ?? 'Notification'),
        'message' => (string)($notification['message'] ?? ''),
        'severity' => (string)($notification['severity'] ?? 'info'),
        'created_at' => (string)($notification['created_at'] ?? date('Y-m-d H:i:s')),
        'target_url' => (string)($notification['target_url'] ?? ''),
        'is_persistent' => (bool)($notification['is_persistent'] ?? false),
        'audience' => $notification['audience'] ?? ['type' => 'all'],
        'required_permissions' => $required_permissions,
        'required_all_permissions' => $required_all_permissions,
        'meta' => is_array($notification['meta'] ?? null) ? $notification['meta'] : [],
        'created_by' => (string)($notification['created_by'] ?? 'system'),
    ];
}

function notifications_load_manual_notifications(): array
{
    $raw = repo_read_notification_manual();
    if (!is_array($raw)) {
        return [];
    }

    return array_values(array_filter(array_map(function ($item) {
        return is_array($item) ? notifications_normalize($item) : null;
    }, $raw)));
}

function notifications_save_manual_notifications(array $notifications): bool
{
    return repo_write_notification_manual($notifications);
}

function notifications_find_notification_by_id(string $notification_id): ?array
{
    $all = array_merge(
        notifications_load_manual_notifications(),
        notifications_build_request_notifications(),
        notifications_parse_activity_csv(),
        notifications_parse_dashboard_logs()
    );

    foreach ($all as $notification) {
        if ((string)($notification['id'] ?? '') === $notification_id) {
            return $notification;
        }
    }

    return null;
}

function notifications_create_manual_notification(array $payload, string $created_by): array
{
    $notifications = notifications_load_manual_notifications();
    $notification = notifications_normalize([
        'id' => 'manual_' . bin2hex(random_bytes(8)),
        'source' => 'manual',
        'category' => $payload['category'] ?? 'announcement',
        'title' => $payload['title'] ?? 'Announcement',
        'message' => $payload['message'] ?? '',
        'severity' => $payload['severity'] ?? 'info',
        'created_at' => date('Y-m-d H:i:s'),
        'target_url' => $payload['target_url'] ?? '',
        'is_persistent' => !empty($payload['is_persistent']),
        'audience' => [
            'type' => $payload['audience_type'] ?? 'all',
            'roles' => $payload['roles'] ?? [],
            'users' => $payload['users'] ?? [],
        ],
        'required_permissions' => [],
        'meta' => [],
        'created_by' => $created_by,
    ]);

    $notifications[] = $notification;
    notifications_save_manual_notifications($notifications);
    return $notification;
}

function notifications_delete_manual_notification(string $notification_id): bool
{
    $notifications = notifications_load_manual_notifications();
    $filtered = array_values(array_filter($notifications, fn($item) => ($item['id'] ?? '') !== $notification_id));
    return notifications_save_manual_notifications($filtered);
}

function notifications_build_request_notifications(): array
{
    $request_files = [
        [
            'items' => repo_read_registration_requests(),
            'kind' => 'registration_request',
            'title' => 'New Registration Request',
            'required_permissions' => ['page_user_management', 'user_approve_request'],
            'target_url' => admin_page_url('user_management'),
        ],
        [
            'items' => repo_read_password_reset_requests(),
            'kind' => 'password_reset_request',
            'title' => 'Password Reset Request',
            'required_permissions' => ['page_user_management', 'user_password_reset'],
            'target_url' => admin_page_url('user_management'),
        ],
        [
            'items' => repo_read_ad_user_requests(),
            'kind' => 'ad_user_request',
            'title' => 'New AD User Request',
            'required_permissions' => ['card_pending_requests', 'execute_ad_actions'],
            'target_url' => admin_page_url(),
        ],
    ];

    $notifications = [];

    foreach ($request_files as $config) {
        $items = $config['items'] ?? [];
        if (!is_array($items)) {
            continue;
        }

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            if (isset($item['status']) && $item['status'] !== 'pending') {
                continue;
            }

            $username = (string)($item['username'] ?? $item['email'] ?? ('request_' . $index));
            if (($config['kind'] ?? '') === 'ad_user_request') {
                $requester = (string)($item['requester_name'] ?? 'User');
                $target = (string)($item['target_username'] ?? $item['hrms_id'] ?? 'target');
                $requestType = (string)($item['request_type_label'] ?? 'AD operation');
                $username = $requester;
                $message = $requester . ' requested ' . $requestType . ' for ' . $target . '.';
            } else {
                $message = $username . ' submitted a new request that needs review.';
            }
            $created_at = (string)($item['requested_at'] ?? $item['timestamp'] ?? date('Y-m-d H:i:s'));
            $notifications[] = notifications_normalize([
                'id' => $config['kind'] . '_' . md5($username . '|' . $created_at . '|' . $index),
                'source' => 'system',
                'category' => 'requests',
                'title' => $config['title'],
                'message' => $message,
                'severity' => 'warning',
                'created_at' => $created_at,
                'target_url' => $config['target_url'],
                'required_permissions' => $config['required_permissions'],
                'meta' => [
                    'username' => $username,
                    'kind' => $config['kind'],
                ],
            ]);
        }
    }

    return $notifications;
}

function notifications_parse_activity_csv(int $limit = 30): array
{
    $notifications = [];
    $days_to_check = 7; // Look back up to 7 days
    $now = time();

    for ($i = 0; $i < $days_to_check; $i++) {
        $log_date = date('Y-m-d', $now - ($i * 86400));
        $log_file = resolved_log_path('audit.csv', $log_date);
        
        if (!file_exists($log_file)) {
            continue;
        }

        $lines = @file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || count($lines) <= 1) {
            continue;
        }

        // Skip header and reverse to get newest first
        $lines = array_slice($lines, 1);
        $lines = array_reverse($lines);

        foreach ($lines as $line) {
            $row = str_getcsv($line, escape: "\\");
            if (count($row) < 5) {
                continue;
            }

            [$timestamp, $username, $action, $status, $details] = $row;
            if ($action === 'page_view') {
                continue;
            }

            $action = trim((string)$action);
            $action_normalized = strtolower(str_replace(['_', '  '], [' ', ' '], $action));
            $status = strtoupper((string)$status);
            $details = (string)$details;

            if ($action_normalized === 'successful login') {
                $action_normalized = 'login';
            } elseif ($action_normalized === 'failed login') {
                $action_normalized = 'failed login';
            } elseif ($action_normalized === 'user logout') {
                $action_normalized = 'logout';
            } elseif ($action_normalized === 'changed own password') {
                $action_normalized = 'change own password';
            }

            $security_actions = [
                'login',
                'failed login',
                'logout',
                'session_timeout',
                'session_terminated',
                'change own password',
                'login_blocked',
                'unauthorized_access',
            ];

            $is_security_event = (
                in_array($action_normalized, $security_actions, true) ||
                $status === 'FAILURE' ||
                stripos($details, 'wrong password') !== false ||
                stripos($details, 'invalid password') !== false ||
                stripos($details, 'incorrect current password') !== false ||
                stripos($details, 'unauthorized') !== false ||
                stripos($details, 'forbidden') !== false ||
                stripos($details, 'failed login') !== false
            );

            $message = trim($username . ' ' . $action_normalized . ' [' . $status . ']');
            if ($details !== '') {
                $message .= ' - ' . $details;
            }

            $notifications[] = notifications_normalize([
                'id' => 'activity_' . md5($timestamp . '|' . $username . '|' . $action_normalized . '|' . $status),
                'source' => 'activity_log',
                'category' => $is_security_event ? 'security' : 'activity',
                'title' => $is_security_event ? 'Application Security' : 'Application Activity',
                'message' => $message,
                'severity' => $status === 'FAILURE' ? 'danger' : ($is_security_event ? 'warning' : 'info'),
                'created_at' => $timestamp,
                'target_url' => admin_page_url('user_activity'),
                'required_permissions' => ['page_application_events'],
                'meta' => [
                    'details' => $details,
                    'action' => $action_normalized,
                    'status' => $status,
                ],
            ]);

            if (count($notifications) >= $limit) {
                break 2; // Break both loops
            }
        }
    }

    return $notifications;
}

function notifications_parse_dashboard_logs(int $limit = 30): array
{
    $log_base_dir = (string) config_get('log_paths.main_dashboard_logs', '');
    if ($log_base_dir === '' || !is_dir($log_base_dir)) {
        return [];
    }

    $categories = array_values(array_filter(scandir($log_base_dir) ?: [], function ($item) use ($log_base_dir) {
        return $item !== '.' && $item !== '..' && is_dir($log_base_dir . DIRECTORY_SEPARATOR . $item);
    }));
    rsort($categories);
    $notifications = [];

    foreach ($categories as $category) {
        $dir = rtrim($log_base_dir, '/\\') . DIRECTORY_SEPARATOR . $category;
        $files = glob($dir . DIRECTORY_SEPARATOR . 'audit-*.log');
        if (!$files) {
            continue;
        }

        rsort($files);
        foreach ($files as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }

            $lines = array_reverse($lines);
            foreach ($lines as $line) {
                if (strpos($line, '] ') === false) {
                    continue;
                }

                [$timestamp_part, $rest] = explode('] ', $line, 2);
                $timestamp = ltrim($timestamp_part, '[');
                $parts = explode(' | ', $rest);
                $log_data = [];

                foreach ($parts as $part) {
                    if (strpos($part, ': ') === false) {
                        continue;
                    }
                    [$key, $value] = explode(': ', $part, 2);
                    $log_data[trim($key)] = trim($value);
                }

                if (empty($log_data['Action'])) {
                    continue;
                }

                $action = strtoupper(preg_replace('/[^\w& ]/', '', trim((string)$log_data['Action'])));
                $target_user = $log_data['TargetUser'] ?? 'Unknown user';
                $status = $log_data['Status'] ?? 'UNKNOWN';
                $status_upper = strtoupper((string)$status);
                $details = trim((string)($log_data['Message'] ?? ($log_data['Details'] ?? '')));
                $performed_by = $log_data['PerformedBy'] ?? ($log_data['ExecutedBy'] ?? 'N/A');
                $category_normalized = strtolower($category);

                switch ($action) {
                    case 'RESET+UNLOCK':
                    case 'RESETUNLOCK':
                    case 'U & RESET':
                        $action = 'U & RESET';
                        break;
                    case 'ENABLE USER':
                    case 'ENABLE_USER':
                        $action = 'ENABLE';
                        break;
                    case 'CREATEUSER':
                    case 'M_CREATE':
                        $action = 'CREATE';
                        break;
                    case 'UNLOCKUSER':
                        $action = 'UNLOCK';
                        break;
                    case 'DISABLE USER':
                        $action = 'DISABLE';
                        break;
                }

                if ($action === 'INFO' || str_starts_with($details, 'Summary:')) {
                    continue;
                }

                if (in_array($category_normalized, ['userinfo', 'userinfo-disable', 'findlogonid'], true)) {
                    continue;
                }

                $category_key = 'ad_actions';
                $title = 'AD Operation Update';
                $target_url = admin_page_url('dashboard');
                $required_permissions = ['page_dashboard', 'page_ad_administration', 'page_application_events'];

                $action_lower = strtolower($action);
                $details_lower = strtolower($details);
                $is_report_event = (
                    strpos($action_lower, 'export') !== false ||
                    strpos($action_lower, 'report') !== false ||
                    strpos($action_lower, 'health') !== false ||
                    strpos($details_lower, 'report') !== false ||
                    strpos($details_lower, 'export') !== false ||
                    strpos($details_lower, 'csv') !== false ||
                    strpos($details_lower, 'html') !== false ||
                    strpos($category_normalized, 'report') !== false ||
                    in_array($category_normalized, ['empstschk', 'findlogonid', 'user_export'], true)
                );

                if ($is_report_event) {
                    $category_key = 'reports';
                    $title = notifications_format_report_title($action);
                    $required_permissions = ['card_get_report', 'page_dashboard', 'page_ad_administration'];
                }

                $severity = 'info';
                if (
                    stripos($status_upper, 'FAIL') !== false ||
                    stripos($status_upper, 'DENIED') !== false ||
                    stripos($status_upper, 'ERROR') !== false ||
                    stripos($status_upper, 'NOT FOUND') !== false
                ) {
                    $severity = 'danger';
                } elseif (
                    stripos($status_upper, 'WARN') !== false ||
                    stripos($status_upper, 'SKIPPED') !== false
                ) {
                    $severity = 'warning';
                } elseif (stripos($status_upper, 'SUCCESS') !== false) {
                    $severity = 'success';
                }

                $operator_label = ($performed_by !== '' && strtoupper($performed_by) !== 'N/A') ? ucwords(strtolower($performed_by)) : 'System';
                $status_label = notifications_format_status_label($status_upper);
                if ($category_key === 'reports') {
                    $message = notifications_format_ad_action_label($action) . ' triggered by ' . $operator_label . '; ' . $status_label;
                } else {
                    $message = notifications_format_ad_action_label($action) . ' triggered by ' . $operator_label . ' for the user id ' . $target_user . '; ' . $status_label;
                }

                $details_clean = '';
                if ($details !== '') {
                    $details_clean = preg_replace("/User '?". preg_quote((string)$target_user, "/") . "'?\\s*/i", '', $details);
                    $details_clean = preg_replace('/\s+/', ' ', (string)$details_clean);
                    $details_clean = trim((string)$details_clean, " .:-");
                }

                if ($details_clean !== '' && !in_array($status_label, ['Success', 'Skipped'], true)) {
                    $message .= ' - ' . $details_clean;
                }

                $notifications[] = notifications_normalize([
                    'id' => 'adlog_' . md5($timestamp . '|' . $action . '|' . $target_user . '|' . $status . '|' . $category),
                    'source' => 'ad_log',
                    'category' => $category_key,
                    'title' => $title,
                    'message' => $message,
                    'severity' => $severity,
                    'created_at' => $timestamp,
                    'target_url' => $target_url,
                    'required_permissions' => $required_permissions,
                    'meta' => [
                        'category' => $category,
                        'performed_by' => $performed_by,
                        'action' => $action,
                        'status' => $status_upper,
                        'details' => $details,
                    ],
                ]);
            }
        }
    }

    usort($notifications, function ($a, $b) {
        return strtotime($b['created_at']) <=> strtotime($a['created_at']);
    });

    return array_slice($notifications, 0, $limit);
}

function notifications_format_ad_action_label(string $action): string
{
    $action = strtoupper(trim($action));

    return match ($action) {
        'U & RESET' => 'Reset operation',
        'UNLOCK' => 'Unlock operation',
        'ENABLE' => 'Enable operation',
        'DISABLE' => 'Disable operation',
        'CREATE' => 'Create user operation',
        'MODIFY' => 'Modify operation',
        'OU_USERS' => 'Export users operation',
        'GROUP_USERS' => 'Export group users operation',
        'AD HRMS STS' => 'AD and HRMS status check',
        'HEALTH_CHECK' => 'AD health check',
        'C_OU' => 'OU creation',
        'C_GRP' => 'Group creation',
        'D_OU' => 'OU removal',
        'D_GRP' => 'Group removal',
        'G_UPD' => 'Membership update',
        default => ucwords(strtolower(str_replace('_', ' ', $action))) . ' operation',
    };
}

function notifications_format_status_label(string $status): string
{
    $status = strtoupper(trim($status));

    return match (true) {
        str_contains($status, 'SUCCESS') => 'Success',
        str_contains($status, 'FAIL'),
        str_contains($status, 'ERROR'),
        str_contains($status, 'DENIED'),
        str_contains($status, 'NOT FOUND') => 'Failed',
        str_contains($status, 'SKIPPED') => 'Skipped',
        default => ucwords(strtolower($status)),
    };
}

function notifications_format_report_title(string $action): string
{
    $action = strtoupper(trim($action));

    return match ($action) {
        'OU_USERS' => 'User Export Report',
        'GROUP_USERS' => 'Group Export Report',
        'AD HRMS STS' => 'AD and HRMS Status Report',
        'HEALTH_CHECK' => 'AD Health Report',
        default => 'Report Update',
    };
}

function notifications_get_available_categories(): array
{
    return [
        'announcement' => 'Announcements',
        'requests' => 'Requests',
        'activity' => 'Application Activity',
        'ad_actions' => 'AD Actions',
        'reports' => 'Reports',
        'security' => 'Security',
    ];
}

function notifications_get_available_roles(): array
{
    return array_keys(notifications_get_roles());
}

function notifications_get_visible_notifications(string $username, string $role): array
{
    $preferences = notifications_get_preferences($username);
    $all = array_merge(
        notifications_load_manual_notifications(),
        notifications_build_request_notifications(),
        notifications_parse_activity_csv(),
        notifications_parse_dashboard_logs()
    );

    $licenseNotification = license_build_notification();
    if (is_array($licenseNotification)) {
        $all[] = notifications_normalize($licenseNotification);
    }

    $visible = [];
    foreach ($all as $notification) {
        if (!notifications_is_visible_to_user($notification, $username, $role)) {
            continue;
        }

        $category = $notification['category'] ?? 'announcement';
        if (!($preferences['categories'][$category] ?? true)) {
            continue;
        }

        $visible[$notification['id']] = $notification;
    }

    $visible = array_values($visible);
    usort($visible, function ($a, $b) {
        return strtotime($b['created_at']) <=> strtotime($a['created_at']);
    });

    return array_slice($visible, 0, 100);
}

function notifications_enrich_for_user(array $notifications, string $username): array
{
    $state = notifications_get_state($username);
    $all_states = notifications_get_all_states();
    $read_ids = array_flip($state['read_ids']);
    $dismissed_toast_ids = array_flip($state['dismissed_toast_ids']);
    $hidden_ids = array_flip($state['hidden_ids']);

    return array_map(function ($notification) use ($read_ids, $dismissed_toast_ids, $hidden_ids, $all_states) {
        $read_by = [];
        foreach ($all_states as $reader_username => $reader_state) {
            $reader_read_ids = array_map('strval', $reader_state['read_ids'] ?? []);
            if (in_array((string)$notification['id'], $reader_read_ids, true)) {
                $read_by[] = (string)$reader_username;
            }
        }

        sort($read_by, SORT_NATURAL | SORT_FLAG_CASE);
        $notification['is_read'] = isset($read_ids[$notification['id']]);
        $notification['toast_dismissed'] = isset($dismissed_toast_ids[$notification['id']]);
        $notification['is_hidden'] = isset($hidden_ids[$notification['id']]);
        $notification['read_by'] = $read_by;
        $notification['read_count'] = count($read_by);
        return $notification;
    }, $notifications);
}

function notifications_mark_read(string $username, array $notification_ids): bool
{
    $state = notifications_get_state($username);
    $state['read_ids'] = array_values(array_unique(array_merge($state['read_ids'], array_map('strval', $notification_ids))));
    return notifications_save_state($username, $state);
}

function notifications_dismiss_toasts(string $username, array $notification_ids): bool
{
    $state = notifications_get_state($username);
    $state['dismissed_toast_ids'] = array_values(array_unique(array_merge($state['dismissed_toast_ids'], array_map('strval', $notification_ids))));
    return notifications_save_state($username, $state);
}

function notifications_mark_all_read(string $username, array $notifications): bool
{
    $ids = array_map(fn($item) => (string)($item['id'] ?? ''), $notifications);
    $ids = array_values(array_filter($ids));
    return notifications_mark_read($username, $ids) && notifications_dismiss_toasts($username, $ids);
}

function notifications_hide_notifications(string $username, array $notification_ids): bool
{
    $state = notifications_get_state($username);
    $state['hidden_ids'] = array_values(array_unique(array_merge($state['hidden_ids'], array_map('strval', $notification_ids))));
    return notifications_save_state($username, $state);
}

function notifications_clear_all(string $username, array $notifications): bool
{
    $ids = array_map(fn($item) => (string)($item['id'] ?? ''), $notifications);
    $ids = array_values(array_filter($ids));
    return notifications_mark_read($username, $ids)
        && notifications_dismiss_toasts($username, $ids)
        && notifications_hide_notifications($username, $ids);
}
