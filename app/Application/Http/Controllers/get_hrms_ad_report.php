<?php
require_once __DIR__ . '/../../../Infrastructure/Persistence/repositories.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    echo "Unauthorized: Please log in.";
    exit();
}

if (isset($_SESSION['role'])) { load_user_permissions($_SESSION['role']); }

if (!has_permission('action_get_ad_hrms_status') && !has_permission('action_export_hrms_ad_user_id')) {
    header("HTTP/1.1 403 Forbidden");
    echo "Forbidden.";
    exit();
}

if (!defined('API_GATEWAY')) { die('Direct access not permitted'); }

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="HRMS_AD_Report.csv"');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Prefer the per-user secure report file (survives session regeneration and
    // supports repeated downloads). Fall back to the session copy if unavailable.
    $reportData = '';
    $safeUser = preg_replace('/[^A-Za-z0-9_.-]/', '_', $_SESSION['username'] ?? '');
    if ($safeUser === '') { $safeUser = 'guest'; }
    $reportPath = resolve_secure_path('reports', 'hrms_ad_report_' . $safeUser . '.csv');
    if (is_file($reportPath)) {
        $fileData = file_get_contents($reportPath);
        if ($fileData !== false && trim($fileData) !== '') {
            $reportData = $fileData;
        }
    }
    if ($reportData === '' && isset($_SESSION['hrms_ad_report_csv'])) {
        $reportData = $_SESSION['hrms_ad_report_csv'];
    }

    if ($reportData !== '') {
        echo $reportData;
    } else {
        echo "Error: Report data not found. Please generate the report first.";
    }
} else {
    echo "Error: Invalid request method.";
}
exit();
