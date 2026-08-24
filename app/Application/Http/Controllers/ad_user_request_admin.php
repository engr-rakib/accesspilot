<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
define('_CORE_ADMIN_', true);
header('Content-Type: application/json');

require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Domain/Audit/audit_service.php';
require_once __DIR__ . '/../../../Domain/AdUserRequest/ad_user_request_service.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

$payload = json_decode(file_get_contents('php://input'), true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_array($payload)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

$action = (string)($payload['action'] ?? '');
$operator = $_SESSION['username'] ?? 'UnknownUser';
$response = ['success' => false, 'message' => 'Invalid request.'];

switch ($action) {
    case 'get_pending_ad_user_requests':
        if (!has_permission('card_pending_requests')) {
            $response['message'] = 'You do not have permission to view user requests.';
            break;
        }
        $response = [
            'success' => true,
            'requests' => ad_user_request_get_pending(),
        ];
        break;

    case 'approve_ad_user_request':
        if (!has_permission('execute_ad_actions')) {
            $response['message'] = 'You do not have permission to process AD requests.';
            break;
        }
        $requestId = (string)($payload['request_id'] ?? '');
        $response = ad_user_request_approve($requestId, $operator);
        log_activity($operator, 'ad_user_request_approved', $response['success'] ? 'success' : 'failure', 'Processed AD user request ID ' . $requestId . '.');
        break;

    case 'prepare_ad_user_request':
        if (!has_permission('execute_ad_actions')) {
            $response['message'] = 'You do not have permission to process AD requests.';
            break;
        }
        $requestId = (string)($payload['request_id'] ?? '');
        $response = ad_user_request_prepare_execution($requestId);
        break;

    case 'finalize_ad_user_request':
        if (!has_permission('execute_ad_actions')) {
            $response['message'] = 'You do not have permission to process AD requests.';
            break;
        }
        $requestId = (string)($payload['request_id'] ?? '');
        $success = (bool)($payload['success'] ?? false);
        $message = (string)($payload['message'] ?? '');
        $response = ad_user_request_finalize_execution($requestId, $operator, $success, $message);
        log_activity($operator, 'ad_user_request_approved', $response['success'] ? 'success' : 'failure', 'Processed AD user request ID ' . $requestId . ' via quick action.');
        break;

    case 'deny_ad_user_request':
        if (!has_permission('card_pending_requests')) {
            $response['message'] = 'You do not have permission to deny requests.';
            break;
        }
        $requestId = (string)($payload['request_id'] ?? '');
        $note = trim((string)($payload['note'] ?? ''));
        $response = ad_user_request_deny($requestId, $operator, $note);
        log_activity($operator, 'ad_user_request_denied', $response['success'] ? 'success' : 'failure', 'Denied AD user request ID ' . $requestId . '.');
        break;
}

echo json_encode($response);
