<?php
include_once __DIR__ . '/../../../Domain/ActiveDirectory/action_executor.php';
include_once __DIR__ . '/../../../Ldap/ldap_module.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Domain/Audit/audit_service.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.']);
    exit();
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

$requestedPart = $_POST['part'] ?? '';
$canExecuteActions = has_permission('execute_ad_actions');
$canLookupHrmsOnly = $requestedPart === 'hrms_info'
    && ($canExecuteActions || has_permission('user_edit') || has_permission('action_new_user_form'));

if (!$canExecuteActions && !$canLookupHrmsOnly) {
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to perform AD actions.']);
    exit();
}

header('Content-Type: application/json');

// Increase limits for potentially long AD actions
set_time_limit(120);
ini_set('memory_limit', '256M');

$response = [
    'success' => false,
    'message' => 'Invalid request.',
    'data' => []
];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $action = $_POST['action'] ?? '';
        $part = $_POST['part'] ?? 'all';
        $authenticatedUser = $_SESSION['username'] ?? 'UnknownUser';

        session_write_close();

        if (empty($username) && $part !== 'hrms_info') {
            $response['message'] = 'Error: Username cannot be empty.';
        } elseif (empty($action) && $part === 'action_result') {
            $response['message'] = 'Error: Action cannot be empty for action_result part.';
        } else {
            switch ($part) {
                case 'action_result':
                    error_log("execute_action.php: Starting action '$action' for user '$username'");
                    $result = ad_execute_action('execute_action', $username, $action, $authenticatedUser);
                    error_log("execute_action.php: Action '$action' completed. Success: " . ($result['success'] ? 'YES' : 'NO'));
                    
                    $response['success'] = $result['success'];
                    $response['message'] = $result['message']
                        ?? ($result['decoded']['message'] ?? ($result['success'] ? 'Action completed.' : 'Action failed.'));
                    $response['data']['actionTaken'] = $action;


                    $action_map = [
                        'unlockUser' => 'unlock user',
                        'resetUnlock' => 'unlock and reset password for user',
                        'enableUser' => 'enable user',
                        'disableUser' => 'disable user',
                        'createUser' => 'create user (from HRMS)',
                        'modifyuser' => 'modify user',
                        'info' => 'get information for user'
                    ];
                    $human_readable_action = $action_map[$action] ?? $action;

                    $log_status = $result['success'] ? 'success' : 'failure';

                    // Per-user audit logging for multi-ID submissions
                    $userResults = $result['decoded']['userResults'] ?? null;
                    if (is_array($userResults) && count($userResults) > 0) {
                        foreach ($userResults as $ur) {
                            $uStatus = !empty($ur['success']) ? 'success' : 'failure';
                            $uDetails = "Operator '{$authenticatedUser}' attempted to {$human_readable_action} '{$ur['username']}'. Result: " . strip_tags($ur['message'] ?? '');
                            log_activity($authenticatedUser, $action, $uStatus, $uDetails);
                        }
                    } else {
                        $log_details = "Operator '{$authenticatedUser}' attempted to {$human_readable_action} '{$username}'. Result: " . strip_tags(str_replace(['<br>', '<br />', "\n"], ' ', $response['message']));
                        log_activity($authenticatedUser, $action, $log_status, $log_details);
                    }
                    break;

                case 'server_info':
                    $result = getADUserInfo($username, $authenticatedUser);
                    $response['success'] = $result['success'];
                    $response['message'] = $result['success'] ? 'Server info fetched successfully.' : 'Failed to fetch server info.';
                    $response['data']['infoOutput'] = $result['infoOutput'];
                    $response['data']['adData'] = $result['adData'] ?? null;
                    $response['data']['suggestions'] = $result['suggestions'] ?? null;
                    break;

                case 'hrms_info':
                    $result = getHRMSInfo($username);
                    $response['success'] = $result['success'];
                    $response['message'] = $result['message'] ?? ($result['success'] ? 'HRMS info fetched successfully.' : 'Failed to fetch HRMS info.');
                    $response['data']['apiData'] = $result['apiData'];
                    break;

                case 'all_info':
                    $adResult = getADUserInfo($username, $authenticatedUser);
                    $hrmsResult = getHRMSInfo($username);

                    $response['success'] = $adResult['success'] || $hrmsResult['success'];
                    $response['message'] = 'Information fetched.';
                    $response['data']['infoOutput'] = $adResult['infoOutput'];
                    $response['data']['adData'] = $adResult['adData'] ?? null;
                    $response['data']['apiData'] = $hrmsResult['apiData'];
                    $response['data']['hrmsSuccess'] = $hrmsResult['success'];
                    $response['data']['hrmsMessage'] = $hrmsResult['message'] ?? '';
                    break;

                default:
                    $response['message'] = 'Error: Invalid part specified.';
                    break;
            }
        }
    }
} catch (Throwable $e) {
    $response['success'] = false;
    $response['message'] = 'Critical System Error: ' . $e->getMessage();
    error_log("execute_action.php Fatal Error: " . $e->getMessage());
}

if (($action === 'AD_Helth_Check' || $action === 'get_ad_health_check_report') && $response['success']) {
    header('Content-Type: text/html');
    echo $response['message'];
    exit();
}

$jsonResponse = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($jsonResponse === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Internal Serialization Error: ' . json_last_error_msg()
    ]);
} else {
    echo $jsonResponse;
}

