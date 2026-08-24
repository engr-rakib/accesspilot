<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.']);
    exit();
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

if (!has_permission('view_hrms_status')) {
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to view HRMS status.']);
    exit();
}

require_once __DIR__ . '/../../../Domain/ActiveDirectory/action_executor.php';

$response = [
    'success' => false,
    'status' => 'Not available'
];

$identifier = $_GET['hrms_id'] ?? ($_GET['username'] ?? '');

if (!empty($identifier)) {
    $hrms_info = getHRMSInfo($identifier);

    if ($hrms_info['success'] && !empty($hrms_info['apiData'])) {
        $response['success'] = true;
        $response['status'] = $hrms_info['apiData']['EMP_STS'] ?? 'Not available';
    }
}

echo json_encode($response);
