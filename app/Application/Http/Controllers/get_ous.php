<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Ldap/ldap_module.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.']);
    exit();
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

if (!has_permission('view_ad_ous')) {
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to view AD OUs.']);
    exit();
}

if (!defined('API_GATEWAY')) {
    die('Direct access not permitted');
}

header('Content-Type: application/json');

$response = ['success' => false, 'ous' => [], 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $psResult = ad_execute_json_script('get_ous', 'getOU_Dropdwon', [
        'ExecutedBy' => $_SESSION['username'] ?? 'UnknownUser',
    ], [
        'include_secure_config' => true,
    ]);
    $jsonOutput = $psResult['output'];
    $psResponse = $psResult['json_valid'] ? $psResult['decoded'] : json_decode($jsonOutput, true);

    if (is_array($psResponse) && isset($psResponse['success'])) {
        if (!empty($psResponse['success'])) {
            $response['success'] = true;
            $response['ous'] = $psResponse['data'] ?? [];
            $response['message'] = 'Successfully retrieved OUs.';
        } else {
            $response['message'] = $psResponse['message'] ?? 'Failed to retrieve OUs.';
        }
    } else {
        $response['message'] = 'Failed to get a valid JSON response from the directory backend.';
        $response['raw_ps_output'] = $jsonOutput;
        error_log('Invalid JSON from get_ous LDAP/PS backend: ' . $jsonOutput);
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
