<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!defined('_CORE_ADMIN_')) {
    define('_CORE_ADMIN_', true);
}

require_once __DIR__ . '/../../Support/helpers.php';
require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Domain/Licensing/license_service.php';
require_once __DIR__ . '/../../../Domain/Audit/audit_service.php';
require_once __DIR__ . '/../../../Domain/Notifications/notification_service.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$username = (string) ($_SESSION['username'] ?? 'unknown');

if ($method === 'GET') {
    echo json_encode([
        'success' => true,
        'status' => license_get_status(),
        'can_manage' => license_can_manage(),
    ]);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!license_can_manage()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only authorized operators can update the license.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$licenseInput = trim((string) ($data['license_input'] ?? ''));
$result = license_apply_input($licenseInput);

if (!empty($result['success'])) {
    log_activity($username, 'license_update', 'success', 'Applied new license material through the license center.');
    $result['status'] = license_get_status();
    $result['can_manage'] = license_can_manage();
    if (function_exists('notifications_create_manual_notification')) {
        notifications_create_manual_notification([
            'title' => 'License Synchronized',
            'message' => 'A new license certificate was successfully applied by ' . $username . '.',
            'severity' => 'success',
            'category' => 'announcement',
            'target_url' => '/index.php?page=license',
            'is_persistent' => false,
        ], 'system');
    }
} else {
    log_activity($username, 'license_update', 'failure', (string) ($result['message'] ?? 'License update failed.'));
}

echo json_encode($result);
