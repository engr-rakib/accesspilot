<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Ldap/Router/ad_operation_router.php';
require_once __DIR__ . '/../../../Infrastructure/Persistence/repositories.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    ob_clean();
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.']);
    exit();
}

if (isset($_SESSION['role'])) { load_user_permissions($_SESSION['role']); }

if (!has_permission('action_get_ad_hrms_status') && !has_permission('action_export_hrms_ad_user_id')) {
    ob_clean();
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['success' => false, 'message' => 'Forbidden: No permission.']);
    exit();
}

if (!defined('API_GATEWAY')) { die('Direct access not permitted'); }

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $loggedInUser = $_SESSION['username'] ?? 'UnknownUser';

    // Persist the generated report to a per-user secure file (not just session):
    // - Download no longer depends on session persistence / regeneration races.
    // - Repeated downloads of the latest report work (old approach unset() on first download).
    $storeReport = function (string $csvOutput) use ($loggedInUser): void {
        $_SESSION['hrms_ad_report_csv'] = $csvOutput; // backward-compat fallback

        $safeUser = preg_replace('/[^A-Za-z0-9_.-]/', '_', $loggedInUser);
        if ($safeUser === '') { $safeUser = 'guest'; }
        $reportPath = resolve_secure_path('reports', 'hrms_ad_report_' . $safeUser . '.csv');
        @file_put_contents($reportPath, $csvOutput, LOCK_EX);
    };

    if (empty($username)) {
        $response['message'] = 'Username is required.';
    } else {
        $psResult = ad_dispatch_report_operation('hrms_ad_report', [
            'Usernames' => $username,
            'ExecutedBy' => $loggedInUser,
        ], []);
        $psOutput = $psResult['output'];
        $jsonValid = !empty($psResult['json_valid']);
        $decoded = $psResult['decoded'] ?? null;

        if ($jsonValid && is_array($decoded) && isset($decoded['results'])) {
            // LDAP path
            $csvHeaders = ['HRMS_ID', 'Logon_ID', 'EMP_NAME', 'AD_Name', 'DESIGNATION', 'HRMS_STATUS', 'AD_STATUS', 'Domain', 'Find_Status'];
            $csvLines = [implode(',', array_map(function($h) { return '"' . str_replace('"', '""', $h) . '"'; }, $csvHeaders))];
            foreach ($decoded['results'] as $row) {
                $vals = [$row['HRMS_ID'] ?? '', $row['Logon_ID'] ?? '', $row['EMP_NAME'] ?? '',
                         $row['AD_Name'] ?? '', $row['DESIGNATION'] ?? '', $row['HRMS_STATUS'] ?? '', $row['AD_STATUS'] ?? '', $row['Domain'] ?? '', $row['Find_Status'] ?? ''];
                $csvLines[] = implode(',', array_map(function($v) { return '"' . str_replace('"', '""', $v) . '"'; }, $vals));
            }
            $csvOutput = implode("\n", $csvLines);
            $storeReport($csvOutput);

            $total = count($decoded['results']);
            $found = count(array_filter($decoded['results'], fn($r) => ($r['Find_Status'] ?? '') === 'Found'));
            $notFound = $total - $found;

            $successMsg = "Report generated: {$total} processed, {$found} found, {$notFound} not found.";
            $response['success'] = true;
            $response['message'] = $successMsg;
            $response['report_content'] = $csvOutput;
        } elseif ($psOutput !== null && $psOutput !== '') {
            // PowerShell path
            $lines = explode("\n", trim($psOutput));
            if (empty($lines) || empty($lines[0])) {
                $feedback = ldap_feedback_troubleshoot('hrms_ad_report', $psResult, ['username' => $username]);
                $response = array_merge($response, $feedback);
            } else {
                $storeReport((string) $psOutput);
                $response['success'] = true;
                $response['message'] = 'Report generated successfully.';
                $response['report_content'] = $psOutput;
            }
        } else {
            $feedback = ldap_feedback_troubleshoot('hrms_ad_report', $psResult, ['username' => $username]);
            $response = array_merge($response, $feedback);
        }
    }
} else {
    $response['message'] = 'Invalid request method.';
}
ob_clean();
echo json_encode($response);
