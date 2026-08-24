<?php

require_once __DIR__ . '/../../Application/Support/helpers.php';

date_default_timezone_set('Asia/Dhaka');

/**
 * Centralized service for logging user activity.
 */

/**
 * Logs a specific user action to the audit trail.
 *
 * @param string $username The username associated with the action. Can be empty for system events.
 * @param string $action The type of action being performed (e.g., 'login', 'logout', 'create_user').
 * @param string $status The status of the action (e.g., 'success', 'failure').
 * @param string $details Additional details, like IP address or error messages.
 */
function log_activity($username, $action, $status, $details = '') {
    $log_file = resolved_log_path('audit.csv');
    $timestamp = date('Y-m-d H:i:s');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $log_details = $details ? "IP: $ip_address, Details: $details" : "IP: $ip_address";

    $log_entry = [
        $timestamp,
        $username,
        $action,
        $status,
        $log_details
    ];

    $log_directory = dirname($log_file);
    if (!is_dir($log_directory)) {
        mkdir($log_directory, 0777, true);
    }

    if (!file_exists($log_file)) {
        $headers = ['Timestamp', 'Username', 'Action', 'Status', 'Details'];
        $header_row = implode(',', $headers) . "\n";
        file_put_contents($log_file, $header_row, FILE_APPEND);
    }

    $file_handle = fopen($log_file, 'a');
    if ($file_handle) {
        fputcsv($file_handle, $log_entry, ',', '"', '\\');
        fclose($file_handle);
    } else {
        error_log('CRITICAL: Could not open log file for writing: ' . $log_file);
    }
}

