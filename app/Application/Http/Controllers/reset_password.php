<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

require_once __DIR__ . '/../../Support/helpers.php';
require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Domain/UserManagement/user_management_service.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.']);
    exit();
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

if (!has_permission('reset_user_password')) {
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to reset user passwords.']);
    exit();
}

$response = [
    'success' => false,
    'message' => 'Invalid request.'
];

$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $response['message'] = 'Invalid JSON payload.';
    error_log('RESET_API: JSON decode error: ' . json_last_error_msg());
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_to_reset = $data['username'] ?? null;
    $new_password = $data['new_password'] ?? '';
    $use_default_password = $data['use_default_password'] ?? false;

    $users = readUsers();

    if (!isset($users[$username_to_reset])) {
        $response['message'] = 'User not found.';
    } else {
        $password_to_set = '';
        $message_suffix = '';

        if ($use_default_password) {
            $password_to_set = config_get('app.default_password');
            $message_suffix = ' to the default password.';
        } else {
            $password_to_set = $new_password;
            $message_suffix = '.';
        }

        if (empty($password_to_set)) {
            $response['message'] = 'Error: New password cannot be empty.';
        } else {
            $users[$username_to_reset]['password'] = password_hash($password_to_set, PASSWORD_DEFAULT);
            if (writeUsers($users)) {
                $response['success'] = true;
                $response['message'] = 'Password for user ' . htmlspecialchars($username_to_reset) . ' has been reset' . $message_suffix;
            } else {
                $last_error = error_get_last();
                $error_message = isset($last_error['message']) ? $last_error['message'] : 'Unknown error.';
                $response['message'] = 'Failed to write user data: ' . $error_message;
            }
        }
    }
}
echo json_encode($response);
