<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

require_once __DIR__ . '/../../../Domain/AdUserRequest/ad_user_request_service.php';
require_once __DIR__ . '/../../../Domain/Audit/audit_service.php';

$payload = json_decode(file_get_contents('php://input'), true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_array($payload)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

if (($payload['action'] ?? '') === 'track_requests') {
    $lookupType = (string)($payload['lookup_type'] ?? 'email');
    $lookupValue = (string)($payload['lookup_value'] ?? '');
    echo json_encode(ad_user_request_get_requester_history($lookupType, $lookupValue));
    exit();
}

if (($payload['action'] ?? '') === 'get_broadcast') {
    echo json_encode([
        'success' => true,
        'broadcasts' => ad_user_request_get_recently_resolved(5)
    ]);
    exit();
}

$result = ad_user_request_submit($payload);

if ($result['success']) {
    $request = $result['request'] ?? [];
    $requester = (string)($request['requester_name'] ?? 'guest_user');
    $typeLabel = (string)($request['request_type_label'] ?? 'AD request');
    $targetUser = (string)($request['target_display_username'] ?? ($request['target_username'] ?? ''));
    log_activity($requester, 'ad_user_request_submitted', 'success', trim($typeLabel . ' submitted for target ' . $targetUser . '.'));
}

echo json_encode($result);
