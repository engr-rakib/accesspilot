<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../Support/helpers.php';
require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.']);
    exit();
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

if (!has_permission('view_user_info')) {
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to view user information.']);
    exit();
}

require_once __DIR__ . '/../../../Domain/ActiveDirectory/action_executor.php';
require_once __DIR__ . '/../../../Ldap/ldap_module.php';

if (!defined('API_GATEWAY')) {
    die('Direct access not permitted');
}

header('Content-Type: application/json');

$response = ['success' => false, 'user' => null, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $username = $_GET['username'] ?? '';

    if (empty($username)) {
        $response['message'] = 'Username is required.';
    } else {
        $executedBy = $_SESSION['username'] ?? 'UnknownUser';
        $psResult = ad_execute_json_script('get_user_info', 'getADUserInfo', [
            'Username' => $username,
            'ExecutedBy' => $executedBy,
        ], [
            'include_secure_config' => true,
        ]);
        $jsonOutput = $psResult['output'];
        $decoded = is_array($psResult['decoded'] ?? null)
            ? $psResult['decoded']
            : json_decode($jsonOutput, true);

        if (is_array($decoded) && array_key_exists('success', $decoded)) {
            $response['success'] = !empty($decoded['success']);
            $response['message'] = $decoded['message'] ?? 'User information retrieved successfully.';

            if (!empty($decoded['success']) && isset($decoded['user']) && is_array($decoded['user'])) {
                $response['user'] = $decoded['user'];

                $hrms_id = $decoded['user']['EmployeeID'] ?? null;
                if ($hrms_id) {
                    $hrms_result = getHRMSInfo($hrms_id);
                    if ($hrms_result['success']) {
                        $response['user']['HRMSData'] = $hrms_result['apiData'];
                    }
                }
            }
        } else {
            $response['message'] = 'Failed to get a valid JSON response from the directory backend.';
            $response['raw_ps_output'] = $jsonOutput;
            error_log('Invalid JSON from get_user_info backend: ' . $jsonOutput);
        }
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
