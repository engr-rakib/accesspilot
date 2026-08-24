<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!defined('_CORE_ADMIN_')) {
    define('_CORE_ADMIN_', true);
}

require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Domain/Notifications/notification_service.php';
require_once __DIR__ . '/../../../Domain/Audit/audit_service.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

$username = (string)($_SESSION['username'] ?? '');
$role = (string)($_SESSION['role'] ?? '');

if (!function_exists('has_permission') || !has_permission('card_notification_center')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($method === 'POST' ? ($_POST['action'] ?? '') : '');
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);
if (!is_array($data)) {
    $data = $_POST;
}

$notifications = array_values(array_filter(
    notifications_enrich_for_user(notifications_get_visible_notifications($username, $role), $username),
    fn($item) => empty($item['is_hidden'])
));
$all_users = notifications_get_users();
$users_payload = [];
foreach ($all_users as $user_key => $user_info) {
    $users_payload[] = [
        'username' => (string)$user_key,
        'full_name' => (string)($user_info['full_name'] ?? $user_key),
        'role' => (string)($user_info['role'] ?? ''),
    ];
}

switch ($action) {
    case 'fetch':
        $unread_count = count(array_filter($notifications, fn($item) => empty($item['is_read'])));
        $toast_candidates = array_values(array_filter($notifications, function ($item) {
            return empty($item['is_read']) && empty($item['toast_dismissed']);
        }));

        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unread_count,
            'toast_notifications' => array_slice($toast_candidates, 0, 5),
            'preferences' => notifications_get_preferences($username),
            'categories' => notifications_get_available_categories(),
            'roles' => notifications_get_available_roles(),
            'users' => $users_payload,
            'capabilities' => [
                'can_send' => has_permission('action_notification_send'),
                'can_manage' => has_permission('action_notification_manage'),
                'can_preferences' => has_permission('action_notification_preferences'),
            ],
        ]);
        exit;

    case 'get_preferences':
        if (!has_permission('action_notification_preferences')) {
            echo json_encode(['success' => false, 'message' => 'No permission.']);
            exit;
        }
        echo json_encode(['success' => true, 'preferences' => notifications_get_preferences($username)]);
        exit;

    case 'mark_read':
        $ids = array_values(array_filter(array_map('strval', $data['ids'] ?? [])));
        $existing_state = notifications_get_state($username);
        $already_read = array_flip(array_map('strval', $existing_state['read_ids'] ?? []));
        notifications_mark_read($username, $ids);
        foreach ($ids as $notification_id) {
            if (isset($already_read[$notification_id]) || strpos($notification_id, 'manual_') !== 0) {
                continue;
            }

            $notification = notifications_find_notification_by_id($notification_id);
            if ($notification) {
                log_activity(
                    $username,
                    'notification_read',
                    'success',
                    "Read notification '" . (string)($notification['title'] ?? $notification_id) . "' created by '" . (string)($notification['created_by'] ?? 'system') . "'."
                );
            }
        }
        echo json_encode(['success' => true]);
        exit;

    case 'dismiss_toasts':
        $ids = array_values(array_filter(array_map('strval', $data['ids'] ?? [])));
        notifications_dismiss_toasts($username, $ids);
        echo json_encode(['success' => true]);
        exit;

    case 'mark_all_read':
        notifications_mark_all_read($username, $notifications);
        echo json_encode(['success' => true]);
        exit;

    case 'clear_all':
        notifications_clear_all($username, $notifications);
        echo json_encode(['success' => true, 'message' => 'All visible notifications cleared.']);
        exit;

    case 'save_preferences':
        if (!has_permission('action_notification_preferences')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to update notification preferences.']);
            exit;
        }

        $show_toasts = !empty($data['show_toasts']);
        $categories = is_array($data['categories'] ?? null) ? $data['categories'] : [];
        notifications_save_preferences($username, ['show_toasts' => $show_toasts, 'categories' => $categories]);
        echo json_encode(['success' => true, 'message' => 'Preferences saved.']);
        exit;

    case 'create':
    case 'send':
        if (!has_permission('action_notification_send')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to send notifications.']);
            exit;
        }

        $result = notifications_create_manual_notification($data, $username);
        if ($result['success']) {
            log_activity($username, 'notification_sent', 'success', "Sent notification '" . (string)($data['title'] ?? 'Notification') . "'.");
        }
        echo json_encode($result);
        exit;

    case 'delete':
        if (!has_permission('action_notification_manage')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to manage notifications.']);
            exit;
        }

        $id = (string)($data['id'] ?? '');
        echo json_encode(notifications_delete_manual_notification($id));
        exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action.']);
