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

if (!has_permission('view_ad_groups')) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to view AD groups.']);
    exit();
}

if (!defined('API_GATEWAY')) {
    die('Direct access not permitted');
}

header('Content-Type: application/json');

$response = ['success' => false, 'group' => null, 'members' => [], 'suggestions' => [], 'message' => 'An unknown error occurred.'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        $response['message'] = 'Invalid request method.';
        echo json_encode($response);
        exit();
    }

    $groupIdentity = trim((string) ($_GET['group'] ?? ''));
    if ($groupIdentity === '') {
        $response['message'] = 'Group name is required.';
        echo json_encode($response);
        exit();
    }

    $executedBy = $_SESSION['username'] ?? 'UnknownUser';
    $result = ad_execute_json_script('get_group_members', 'getADGroupMembers', [
        'GroupIdentity' => $groupIdentity,
        'ExecutedBy' => $executedBy,
    ], [
        'include_secure_config' => true,
    ]);

    if ($result['json_valid']) {
        $decoded = $result['decoded'];
        $response['success'] = $decoded['success'] ?? false;
        $response['message'] = $decoded['message'] ?? 'PowerShell executed but returned no message.';
        $response['group'] = $decoded['group'] ?? null;
        $response['members'] = $decoded['members'] ?? [];
        $response['suggestions'] = $decoded['suggestions'] ?? [];
    } elseif ($result['success']) {
        $response['success'] = true;
        $response['message'] = 'Group data returned without JSON payload.';
        $response['technical_details'] = $result['output'];
    } else {
        $response['message'] = 'Failed to fetch group members or parse response.';
        $response['technical_details'] = $result['output'];
        error_log('get_group_members.php: Failure. Output: ' . $result['output']);
    }
} catch (Throwable $e) {
    $response['message'] = 'Critical System Error: ' . $e->getMessage();
    error_log('get_group_members.php Fatal: ' . $e->getMessage());
}

echo json_encode($response);
