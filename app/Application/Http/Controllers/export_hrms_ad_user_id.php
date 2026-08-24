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

if (!has_permission('action_export_hrms_ad_user_id')) {
    header("HTTP/1.1 403 Forbidden");
    echo "Forbidden: You do not have permission to export HRMS to AD login IDs.";
    exit();
}

if (!defined('API_GATEWAY')) {
    die('Direct access not permitted');
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="HRMS_AD_User_ID_Report.csv"');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SESSION['export_hrms_ad_user_id_csv'])) {
        echo $_SESSION['export_hrms_ad_user_id_csv'];
        unset($_SESSION['export_hrms_ad_user_id_csv']);
    } else {
        echo "Error: Report data not found. Please generate the report first.";
    }
} else {
    echo "Error: Invalid request method.";
}

exit();
