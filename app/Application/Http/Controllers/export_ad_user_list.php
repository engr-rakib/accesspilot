<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    ob_clean();
    header("HTTP/1.1 401 Unauthorized");
    echo "Unauthorized: Please log in.";
    exit();
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

if (!has_permission('action_export_ad_users')) {
    ob_clean();
    header("HTTP/1.1 403 Forbidden");
    echo "Forbidden: You do not have permission to export AD user lists.";
    exit();
}

if (!defined('API_GATEWAY')) {
    die('Direct access not permitted');
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="AD_User_List_Report.csv"');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SESSION['ad_user_list_csv'])) {
        $csvOutput = $_SESSION['ad_user_list_csv'];
        unset($_SESSION['ad_user_list_csv']);

        ob_clean();
        header('Content-Length: ' . strlen($csvOutput));
        echo $csvOutput;
    } else {
        ob_clean();
        echo "Error: Report data not found. Please generate the report first.";
    }
} else {
    ob_clean();
    echo "Error: Invalid request method.";
}

exit();
