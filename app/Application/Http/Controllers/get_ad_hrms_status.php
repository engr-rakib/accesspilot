<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    echo "Unauthorized: Please log in.";
    exit();
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

if (!has_permission('action_get_ad_hrms_status')) {
    header("HTTP/1.1 403 Forbidden");
    echo "Forbidden: You do not have permission to view AD and HRMS status reports.";
    exit();
}

if (!defined('API_GATEWAY')) {
    die('Direct access not permitted');
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="AD_HRMS_Status_Report.csv"');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SESSION['ad_hrms_report_csv'])) {
        echo $_SESSION['ad_hrms_report_csv'];
        unset($_SESSION['ad_hrms_report_csv']);
    } else {
        echo "Error: Report data not found. Please generate the report first.";
    }
} else {
    echo "Error: Invalid request method.";
}

exit();
