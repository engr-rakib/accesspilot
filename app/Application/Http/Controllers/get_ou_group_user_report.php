<?php
if (!defined('API_GATEWAY')) {
    die('Direct access not permitted');
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Ldap/Router/ad_operation_router.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

set_time_limit(0);

if (!has_permission('action_export_ad_users')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$ouName = $_POST['ouName'] ?? '';
$groupName = $_POST['groupName'] ?? '';

$psResult = ad_dispatch_report_operation('ou_group_user_report', [
    'OUName' => $ouName,
    'GroupName' => $groupName,
    'ExecutedBy' => $_SESSION['username'] ?? 'System',
]);
$psOutput = $psResult['output'];

if ($psOutput === null) {
    $feedback = ldap_feedback_troubleshoot('ou_group_user_report', $psResult, ['username' => $_SESSION['username'] ?? 'System']);
    echo json_encode($feedback);
} else {
    $result = json_decode($psOutput, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo json_encode($result);
    } else {
        $feedback = ldap_feedback_troubleshoot('ou_group_user_report', $psResult, ['username' => $_SESSION['username'] ?? 'System']);
        $feedback['message'] = 'Invalid script output. ' . $feedback['message'];
        echo json_encode($feedback);
    }
}
