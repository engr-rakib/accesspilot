<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Ldap/Router/ad_operation_router.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    ob_clean();
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.']);
    exit();
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

if (!has_permission('action_get_ad_hrms_status')) {
    ob_clean();
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to view AD and HRMS status reports.']);
    exit();
}

if (!defined('API_GATEWAY')) {
    die('Direct access not permitted');
}

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $loggedInUser = $_SESSION['username'] ?? 'UnknownUser';

    if (empty($username)) {
        $response['message'] = 'Username is required.';
    } else {
        $psResult = ad_dispatch_report_operation('get_ad_hrms_status', [
            'Usernames' => $username,
            'ExecutedBy' => $loggedInUser,
        ], []);
        $psOutput = $psResult['output'];
        $jsonValid = !empty($psResult['json_valid']);
        $decoded = $psResult['decoded'] ?? null;

        if ($jsonValid && is_array($decoded) && isset($decoded['results'])) {
            // LDAP path — JSON output from handler
            $csvHeaders = ['EMP_ID', 'EMP_NAME', 'HRMS_STATUS', 'AD_STATUS', 'CheckedBy'];
            $csvLines = [implode(',', array_map(function($h) { return '"' . str_replace('"', '""', $h) . '"'; }, $csvHeaders))];
            foreach ($decoded['results'] as $row) {
                $vals = [$row['EMP_ID'] ?? '', $row['EMP_NAME'] ?? '', $row['HRMS_STATUS'] ?? '',
                         $row['AD_STATUS'] ?? '', $row['CheckedBy'] ?? ''];
                $csvLines[] = implode(',', array_map(function($v) { return '"' . str_replace('"', '""', $v) . '"'; }, $vals));
            }
            $psOutput = implode("\n", $csvLines);
            $_SESSION['ad_hrms_report_csv'] = $psOutput;
        } else {
            // PowerShell path — raw CSV output
            $_SESSION['ad_hrms_report_csv'] = $psOutput;
        }

        if ($psOutput === null || $psOutput === '' || (!$jsonValid && (strpos($psOutput, 'ERROR:') !== false || strpos($psOutput, 'PowerShell script execution failed') !== false))) {
            $feedback = ldap_feedback_troubleshoot('get_ad_hrms_status', $psResult, ['username' => $username]);
            $response = array_merge($response, $feedback);
        } else {
            $lines = explode("\n", trim($psOutput));
            if (empty($lines) || empty($lines[0])) {
                $feedback = ldap_feedback_troubleshoot('get_ad_hrms_status', $psResult, ['username' => $username]);
                $response = array_merge($response, $feedback);
            } else {
                $response['success'] = true;
                $response['message'] = "Report generated successfully. Click 'Download Report' to save.";
                $response['report_content'] = $psOutput;
            }
        }
    }
} else {
    $response['message'] = 'Invalid request method.';
}
ob_clean();
echo json_encode($response);

