<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Ldap/ldap_module.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.']);
    exit();
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

if (!(has_permission('view_ad_groups') || has_permission('view_user_info'))) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to resolve directory members.']);
    exit();
}

if (!defined('API_GATEWAY')) {
    die('Direct access not permitted');
}

header('Content-Type: application/json');

$response = ['success' => false, 'member' => null, 'suggestions' => [], 'message' => 'An unknown error occurred.'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        $response['message'] = 'Invalid request method.';
        echo json_encode($response);
        exit();
    }

    $identity = trim((string) ($_GET['identity'] ?? ''));
    if ($identity === '') {
        $response['message'] = 'A user or group identity is required.';
        echo json_encode($response);
        exit();
    }

    $executedBy = $_SESSION['username'] ?? 'UnknownUser';
    $result = ad_execute_json_script('resolve_principal', 'resolveADPrincipal', [
        'Identity' => $identity,
        'ExecutedBy' => $executedBy,
    ], [
        'include_secure_config' => true,
    ]);

    if ($result['json_valid']) {
        $decoded = $result['decoded'];
        $response['success'] = $decoded['success'] ?? false;
        $response['message'] = $decoded['message'] ?? 'PowerShell executed but returned no message.';
        $response['member'] = $decoded['member'] ?? null;
        $response['suggestions'] = $decoded['suggestions'] ?? [];
    } else {
        $response['message'] = 'Failed to resolve the provided directory principal.';
        $response['technical_details'] = $result['output'];
    }
} catch (Throwable $e) {
    $response['message'] = 'Critical System Error: ' . $e->getMessage();
    error_log('resolve_directory_principal.php Fatal: ' . $e->getMessage());
}

echo json_encode($response);
