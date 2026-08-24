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

if (!has_permission('action_ad_health_check')) {
    header("HTTP/1.1 403 Forbidden");
    echo "Forbidden: You do not have permission to view AD Health Check reports.";
    exit();
}

if (!defined('API_GATEWAY')) {
    die('Direct access not permitted');
}

header('Content-Type: text/html');
header('Content-Disposition: attachment; filename="AD_Health_Report.html"');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SESSION['ad_health_report_html'])) {
        echo $_SESSION['ad_health_report_html'];
        unset($_SESSION['ad_health_report_html']);
    } else {
        echo "Error: AD Health Report data not found. Please generate the report first.";
    }
} else {
    echo "Error: Invalid request method.";
}

exit();
