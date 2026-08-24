<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Infrastructure/PowerShell/powershell_runner.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    ob_clean();
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.']);
    exit();
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

if (!has_permission('action_export_ad_users')) {
    ob_clean();
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to export AD user lists.']);
    exit();
}

if (!defined('API_GATEWAY')) {
    die('Direct access not permitted');
}

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loggedInUser = $_SESSION['username'] ?? 'UnknownUser';

    $command = powershell_build_command('Export_AD_User_list', [
        'ExecutedBy' => $loggedInUser,
    ], [
        'include_secure_config' => true,
    ]);

    $psResult = powershell_exec_command($command);
    $psOutput = $psResult['output'];
    $return_var = $psResult['exit_code'];

    $_SESSION['ad_user_list_csv'] = $psOutput;

    if ($return_var !== 0) {
        $response['message'] = "PowerShell script execution failed (Exit Code: {$return_var}). Output: " . htmlspecialchars($psOutput);
    } else if (strpos($psOutput, 'ERROR:') !== false) {
        $response['message'] = 'Error from PowerShell script: ' . htmlspecialchars($psOutput);
    } else {
        $response['success'] = true;
        $response['message'] = 'AD User List generated successfully. Check your downloads.';
    }
} else {
    $response['message'] = 'Invalid request method.';
}

ob_clean();
echo json_encode($response);

