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

if (!has_permission('action_export_hrms_ad_user_id')) {
    ob_clean();
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to export HRMS to AD login IDs.']);
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
        $psResult = ad_dispatch_report_operation('export_hrms_ad_user_id', [
            'Usernames' => $username,
            'ExecutedBy' => $loggedInUser,
        ], []);
        $psOutput = $psResult['output'];
        $return_var = $psResult['exit_code'];
        $jsonValid = !empty($psResult['json_valid']);
        $decoded = $psResult['decoded'] ?? null;

        $successCount = 0;
        $notFoundCount = 0;
        $errorCount = 0;

        if ($jsonValid && is_array($decoded) && isset($decoded['results'])) {
            // LDAP path — JSON output from handler
            foreach ($decoded['results'] as $row) {
                switch ($row['Status'] ?? 'UNKNOWN') {
                    case 'SUCCESS': $successCount++; break;
                    case 'NOT_FOUND': $notFoundCount++; break;
                    default: $errorCount++; break;
                }
            }
            $csvHeaders = ['HRMS_ID', 'DisplayName', 'LogonID', 'Status', 'Message', 'CheckedBy'];
            $csvLines = [implode(',', array_map(function($h) { return '"' . str_replace('"', '""', $h) . '"'; }, $csvHeaders))];
            foreach ($decoded['results'] as $row) {
                $vals = [$row['HRMS_ID'] ?? '', $row['DisplayName'] ?? '', $row['LogonID'] ?? '',
                         $row['Status'] ?? '', $row['Message'] ?? '', $row['CheckedBy'] ?? ''];
                $csvLines[] = implode(',', array_map(function($v) { return '"' . str_replace('"', '""', $v) . '"'; }, $vals));
            }
            $psOutput = implode("\n", $csvLines);
            $_SESSION['export_hrms_ad_user_id_csv'] = $psOutput;
        } else {
            // PowerShell path — raw CSV output
            $_SESSION['export_hrms_ad_user_id_csv'] = $psOutput;
            $lines = explode("\n", trim($psOutput));
            $header = !empty($lines) ? str_getcsv(array_shift($lines), escape: "\\") : [];
            if (!empty($header)) {
                foreach ($lines as $line) {
                    if (empty(trim($line))) continue;
                    $rowData = str_getcsv($line, escape: "\\");
                    if (count($rowData) === count($header)) {
                        $row = array_combine($header, $rowData);
                        switch ($row['Status'] ?? 'UNKNOWN') {
                            case 'SUCCESS': $successCount++; break;
                            case 'NOT_FOUND': $notFoundCount++; break;
                            default: $errorCount++; break;
                        }
                    }
                }
            }
        }

        if ($return_var !== 0 && !$jsonValid) {
            $feedback = ldap_feedback_troubleshoot('export_hrms_ad_user_id', $psResult, ['username' => $username]);
            $response = array_merge($response, $feedback);
        } elseif ($successCount === 0 && $notFoundCount === 0 && $errorCount === 0) {
            $feedback = ldap_feedback_troubleshoot('export_hrms_ad_user_id', $psResult, ['username' => $username]);
            $response = array_merge($response, $feedback);
        } else {
            $response['success'] = true;
            $response['message'] = "Report generated: Success: {$successCount}, Not Found: {$notFoundCount}, Errors: {$errorCount}. Check your downloads.";
            $response['report_content'] = $psOutput;
        }
    }
} else {
    $response['message'] = 'Invalid request method.';
}
ob_clean();
echo json_encode($response);

