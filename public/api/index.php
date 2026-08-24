<?php
// Canonical public API gateway.
define('API_GATEWAY', true);
require_once __DIR__ . '/../../app/Application/Support/helpers.php';
require_once __DIR__ . '/../../app/Domain/Licensing/license_service.php';

// IP Blocking — respond as unreachable for blocked source IPs before anything else.
require_once __DIR__ . '/../../app/Domain/Security/ip_block_service.php';
ip_block_enforce();

$endpoint = $_GET['endpoint'] ?? '';

$allowed_endpoints = [
    'execute_action' => 'execute_action.php',
    'get_user_info' => 'get_user_info.php',
    'get_group_members' => 'get_group_members.php',
    'resolve_directory_principal' => 'resolve_directory_principal.php',
    'create_directory_object' => 'create_directory_object.php',
    'delete_directory_object' => 'delete_directory_object.php',
    'auth_api' => 'auth.php',
    'license_api' => 'license.php',
    'system_config_api' => 'system_config.php',
    'get_active_users' => 'get_active_users.php',
    'get_hrms_status' => 'get_hrms_status.php',
    'get_groups' => 'get_groups.php',
    'get_ous' => 'get_ous.php',
    'manual_create_user' => 'manual_create_user.php',
    'modify_ad_user' => 'modify_ad_user.php',
    'update_ad_user' => 'modify_ad_user.php',
    'update_group_members' => 'update_group_members.php',
    'reset_password_api' => 'reset_password.php',
    'create_user_action' => 'create_user.php',
    'user_management_action' => 'user_management_actions.php',
    'get_user_report' => 'get_user_report.php',
    'get_ad_health_check_message' => 'get_ad_health_check_message.php',
    'get_ad_health_check_deep' => 'get_ad_health_check_deep.php',
    'get_infrastructure_diagnostics' => 'get_infrastructure_diagnostics.php',
    'get_ad_health_check_report' => 'get_ad_health_check_report.php',
    'monitoring_api' => 'monitoring.php',
    'exchange' => 'exchange.php',
    'email_tools' => 'email_tools.php',
    'get_ad_hrms_status_message' => 'get_ad_hrms_status_message.php',
    'get_ad_hrms_status' => 'get_ad_hrms_status.php',
    'export_hrms_ad_user_id_message' => 'export_hrms_ad_user_id_message.php',
    'export_hrms_ad_user_id' => 'export_hrms_ad_user_id.php',
    'export_ad_user_list_message' => 'export_ad_user_list_message.php',
    'export_ad_user_list' => 'export_ad_user_list.php',
    'get_hrms_ad_report_message' => 'get_hrms_ad_report_message.php',
    'get_hrms_ad_report' => 'get_hrms_ad_report.php',
    'get_ou_group_user_report' => 'get_ou_group_user_report.php',
    'domain_api' => 'domain_api.php',
    'log_data' => 'log_data.php',
    'profile_action' => 'profile.php',
    'get_avatar' => 'get_avatar.php',
    'get_user_security_events' => 'get_user_security_events.php',
    'lookup_user_workstations' => 'lookup_user_workstations.php',
    'vendor_license_api' => 'vendor_license_api.php',
    'modify_mailbox' => 'modify_mailbox.php',
    'ip_block' => 'ip_block.php',
];

$is_restricted = license_is_restricted();
$is_read_request = ($_SERVER['REQUEST_METHOD'] === 'GET');

// Allow all GET (read) requests so data is visible, but block non-GET (write) actions.
// Recovery exceptions must remain writable even in restricted mode.
if (!in_array($endpoint, ['auth_api', 'license_api', 'system_config_api'], true) && $is_restricted && !$is_read_request) {
    header('Content-Type: application/json');
    http_response_code(423);
    $response = license_denied_response();
    $response['message'] = 'Operation restricted. This is a read-only environment. Please purchase and provide a legal license to perform this action.';
    echo json_encode($response);
    exit;
}

// --- CSRF Protection + Secure Session Cookies ---
// Start session for state-changing requests and ensure CSRF token exists.
$csrfExemptEndpoints = ['auth_api', 'get_ad_hrms_status', 'export_hrms_ad_user_id', 'get_ad_health_check_report', 'export_ad_user_list', 'get_hrms_ad_report', 'monitoring_api'];
if (!in_array($endpoint, $csrfExemptEndpoints, true)) {
    if (session_status() === PHP_SESSION_NONE) {
        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        $isHttps = $forwardedProto === 'https'
            || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    // Ensure CSRF token exists in session (generated on login, but safeguard for edge cases).
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// Validate CSRF token for non-GET, non-exempt requests.
if (!in_array($endpoint, $csrfExemptEndpoints, true) && !$is_read_request) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        session_destroy();
        $_SESSION = [];
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        header('Content-Type: application/json');
        http_response_code(419);
        echo json_encode([
            'success' => false,
            'message' => 'Session expired or invalid request. Please refresh the page.',
            'session_expired' => true,
            'redirect' => route_url('login.php'),
        ]);
        exit;
    }
    // Check session idle timeout on state-changing API calls
    $maxIdle = !empty($_SESSION['remember_me']) ? 7200 : 900;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $maxIdle)) {
        session_destroy();
        $_SESSION = [];
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        header('Content-Type: application/json');
        http_response_code(419);
        echo json_encode([
            'success' => false,
            'message' => 'Session expired due to inactivity. Please login again.',
            'session_expired' => true,
            'redirect' => route_url('login.php'),
        ]);
        exit;
    }
    if (isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }
}

// Release session lock early so concurrent requests from the same user don't block
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

if (array_key_exists($endpoint, $allowed_endpoints)) {
    $handler_file = __DIR__ . '/../../app/Application/Http/Controllers/' . $allowed_endpoints[$endpoint];
    if (file_exists($handler_file)) {
        require_once $handler_file;
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['success' => false, 'message' => 'API handler file not found.']);
    }
} else {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Invalid API endpoint.']);
}
