<?php

date_default_timezone_set('Asia/Dhaka');
error_reporting(0);
ini_set('display_errors', 0);

if (!defined('_CORE_ADMIN_')) {
    define('_CORE_ADMIN_', true);
}

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../Support/helpers.php';
require_once __DIR__ . '/../../../Ldap/Config/ldap_config_repository.php';
require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Infrastructure/Persistence/repositories.php';
require_once __DIR__ . '/../../../Infrastructure/Logging/dashboard_log_reader.php';

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

$app_config = app_config();

if (!function_exists('has_permission') || !has_permission('page_application_events')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit();
}

$start_date_str = $_GET['start'] ?? date('Y-m-d');
$end_date_str = $_GET['end'] ?? date('Y-m-d');
// Accept both date-only (Y-m-d) and full datetime strings.
$start_time = strtotime($start_date_str);
if ($start_time !== false && strpos($start_date_str, ':') === false) {
    $start_time = strtotime($start_date_str . ' 00:00:00');
}
$end_time = strtotime($end_date_str);
if ($end_time !== false && strpos($end_date_str, ':') === false) {
    $end_time = strtotime($end_date_str . ' 23:59:59');
}

if (!$start_time || !$end_time) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format.']);
    exit();
}

// ── Guest monitoring chart range (independent of the page-wide event range) ──
$guest_range = $_GET['guest_range'] ?? 'last24h';
if (!in_array($guest_range, ['last12h', 'last24h', 'last72h', 'yesterday', 'lastweek', 'month', 'custom', 'today', 'day', 'week', 'year'], true)) {
    $guest_range = 'last24h';
}
$guest_start_str = $_GET['guest_start'] ?? $start_date_str;
$guest_end_str = $_GET['guest_end'] ?? $end_date_str;
$guest_start_time = strtotime($guest_start_str);
$guest_end_time = strtotime($guest_end_str);
if ($guest_start_time !== false) {
    $guest_start_time = (int) $guest_start_time;
} else {
    $guest_start_time = $start_time;
}
if ($guest_end_time !== false) {
    $guest_end_time = (int) $guest_end_time;
} else {
    $guest_end_time = $end_time;
}
if (!$guest_start_time || !$guest_end_time || $guest_start_time > $guest_end_time) {
    $guest_start_time = $start_time;
    $guest_end_time = $end_time;
}

define('ACTIVITY_WINDOW', 15 * 60);

$response = [
    'stats' => [
        'online_users' => 0, 'enabled_users' => 0, 'disabled_users' => 0,
        'logins_in_range' => 0, 'failures_in_range' => 0,
        'activity_by_hour_by_user' => [], 'top_actions' => [],
    ],
    'online_users_list' => [], 'successful_logins_list' => [],
    'failed_logins_list' => [], 'enabled_users_list' => [], 'logs' => [],
    'guest_failed_attempts' => [], 'guest_failures_by_hour' => [], 'guest_failures_by_user_hour' => [],
    'guest_failures_by_ip_hour' => [],
    'guest_labels' => [], 'guest_granularity' => 'hour', 'guest_user_activity_tracking' => [],
];

function process_user_data(&$stats, &$enabled_users_list) {
    $users_data = repo_read_users();
    if (!is_array($users_data)) return;
    foreach ($users_data as $username => $user) {
        if (isset($user['system_access']) && $user['system_access'] === true) {
            $stats['enabled_users']++;
            $enabled_users_list[] = $username;
        } else {
            $stats['disabled_users']++;
        }
    }
}

// Build guest-chart bucket labels based on the selected range + granularity.
// Returns: array{0:granularity, 1:labels[], 2:bucketCount, 3:startTime}
function build_guest_buckets(string $range, int $start_time, int $end_time): array
{
    $granularity = 'hour';
    $labels = [];
    $bucketCount = 0;

    switch ($range) {
        case 'yesterday':
        case 'today':
            $granularity = 'hour';
            $bucketCount = 24;
            for ($h = 0; $h < 24; $h++) {
                $labels[] = sprintf('%02d:00', $h);
            }
            break;

        case 'last12h':
            $granularity = 'hour';
            $bucketCount = 12;
            for ($h = 0; $h < 12; $h++) {
                $labels[] = sprintf('%02d:00', (int) date('G', $start_time + ($h * 3600)));
            }
            break;

        case 'last72h':
            $granularity = 'hour';
            $bucketCount = 72;
            for ($h = 0; $h < 72; $h++) {
                $labels[] = sprintf('%02d:00', (int) date('G', $start_time + ($h * 3600)));
            }
            break;

        case 'last24h':
        case 'day':
            $granularity = 'hour';
            $bucketCount = 24;
            for ($h = 0; $h < 24; $h++) {
                $labels[] = sprintf('%02d:00', (int) date('G', $start_time + ($h * 3600)));
            }
            break;

        case 'lastweek':
        case 'week':
            $granularity = 'day';
            $bucketCount = 7;
            for ($d = 0; $d < 7; $d++) {
                $labels[] = date('M j', $start_time + ($d * 86400));
            }
            break;

        case 'month':
            $granularity = 'day';
            $span_days = (int) ceil(($end_time - $start_time) / 86400) + 1;
            $bucketCount = min($span_days, 31);
            for ($d = 0; $d < $bucketCount; $d++) {
                $labels[] = date('M j', $start_time + ($d * 86400));
            }
            break;

        case 'year':
            $granularity = 'month';
            $bucketCount = 12;
            for ($m = 0; $m < 12; $m++) {
                $labels[] = date('M Y', strtotime('+' . $m . ' months', mktime(0, 0, 0, 1, 1, (int) date('Y', $start_time))));
            }
            break;

        default: // custom — derive granularity from span
            $span_days = (int) ceil(($end_time - $start_time) / 86400) + 1;
            if ($span_days <= 2) {
                $granularity = 'hour';
                $bucketCount = 24;
                for ($h = 0; $h < 24; $h++) {
                    $labels[] = sprintf('%02d:00', $h);
                }
            } elseif ($span_days <= 62) {
                $granularity = 'day';
                $bucketCount = $span_days;
                for ($d = 0; $d < $bucketCount; $d++) {
                    $labels[] = date('M j', $start_time + ($d * 86400));
                }
            } else {
                $granularity = 'month';
                $bucketCount = min(12, max(1, (int) ceil($span_days / 30)));
                for ($m = 0; $m < $bucketCount; $m++) {
                    $labels[] = date('M Y', strtotime('+' . $m . ' months', $start_time));
                }
            }
            break;
    }

    return [$granularity, $labels, $bucketCount, $start_time];
}

// Map a log timestamp to a bucket index for the guest chart.
function guest_bucket_index(string $granularity, int $log_time, int $start_time, int $bucketCount, string $range = 'today'): int
{
    if ($granularity === 'hour') {
        if ($range === 'last12h' || $range === 'last24h' || $range === 'last72h' || $range === 'day') {
            return max(0, min($bucketCount - 1, (int) floor(($log_time - $start_time) / 3600)));
        }
        return (int) date('G', $log_time);
    }
    if ($granularity === 'month') {
        $startY = (int) date('Y', $start_time);
        $startM = (int) date('n', $start_time);
        $logY = (int) date('Y', $log_time);
        $logM = (int) date('n', $log_time);
        $idx = (($logY - $startY) * 12) + ($logM - $startM);
        return max(0, min($bucketCount - 1, $idx));
    }
    // day
    $idx = (int) floor(($log_time - $start_time) / 86400);
    return max(0, min($bucketCount - 1, $idx));
}

function process_audit_log($start_time, $end_time, &$stats, &$logs, &$online_users_list, &$successful_logins_list, &$failed_logins_list, &$guest_failed_attempts, &$guest_failures_by_hour, &$guest_failures_by_user_hour, &$guest_failures_by_ip_hour, $guest_range, $guest_start_time, $guest_end_time, &$guest_labels, &$guest_granularity, &$guest_user_activity_tracking) {
    global $app_config;
    
    // Leverage the high-performance global log reader
    $all_logs = dashboard_read_logs([
        'cutoff_time' => $start_time,
        'end_cutoff_time' => $end_time,
        'include_root' => true, // Includes the base audit.csv (Portal Events)
        'categories' => [],      // Excludes AD script logs
    ]);

    if (empty($all_logs)) return;

    $action_counts = [];
    $activity_by_hour_by_user = [];
    $user_activity_profiles = [];
    $guest_user_activity_tracking = [];
    $guest_user_activity_data = [];

    // Build guest chart labels + bucket map BEFORE aggregating logs
    [$guest_granularity, $guest_labels, $guest_bucket_count, $guest_bucket_start] = build_guest_buckets($guest_range, $guest_start_time, $guest_end_time);

    // ── First pass: identify authenticated users (those with a successful login) ──
    // Guest / unsuccessful login attempts must NOT appear in session/user activity charts.
    $authenticated_users = [];
    foreach ($all_logs as $entry) {
        $action = strtolower((string) $entry['action']);
        $status = strtolower((string) $entry['status']);
        if (strpos($action, 'login') !== false && ($status === 'success' || $status === 'successful')) {
            $actor = $entry['performedBy'] !== 'N/A' ? $entry['performedBy'] : $entry['targetUser'];
            if ($actor !== '' && $actor !== 'N/A' && strtolower($actor) !== 'unknown_user') {
                $authenticated_users[$actor] = true;
            }
        }
    }

    foreach ($all_logs as $entry) {
        $log_time = strtotime($entry['timestamp']);
        if (!$log_time) continue;

        $username = $entry['performedBy'] !== 'N/A' ? $entry['performedBy'] : $entry['targetUser'];
        $action = $entry['action'];
        $status = $entry['status'];
        $details = $entry['message'];
        $ip = $entry['ip'];
        $action_lower = strtolower($action);
        $status_lower = strtolower($status);
        $is_login_action = strpos($action_lower, 'login') !== false;
        $is_login_success = $is_login_action && ($status_lower === 'success' || $status_lower === 'successful');
        $is_login_failure = $is_login_action && (strpos($status_lower, 'fail') !== false || $status_lower === 'failed');
        $is_authenticated = isset($authenticated_users[$username]);

        if (!isset($user_activity_profiles[$username])) {
            $user_activity_profiles[$username] = [
                'username' => $username, 'last_activity_time' => 0, 'last_login_time' => 0,
                'login_count' => 0, 'last_ip' => 'N/A', 'session_start_time' => 0
            ];
        }
        if ($log_time > $user_activity_profiles[$username]['last_activity_time']) {
            $user_activity_profiles[$username]['last_activity_time'] = $log_time;
            $user_activity_profiles[$username]['last_ip'] = $ip;
        }
        
        // Count logins for stats
        if ($is_login_success) {
            $user_activity_profiles[$username]['login_count']++;
            if ($log_time > $user_activity_profiles[$username]['last_login_time']) {
                $user_activity_profiles[$username]['last_login_time'] = $log_time;
            }
            $stats['logins_in_range']++;
            $successful_logins_list[] = ['username' => $username, 'timestamp' => $entry['timestamp'], 'ip' => $ip];
        }
        
        if ($is_login_failure) {
            $stats['failures_in_range']++;
            $failed_logins_list[] = ['username' => $username, 'timestamp' => $entry['timestamp'], 'ip' => $ip, 'details' => $details];

            // ── Unsuccessful Guest User Monitoring ──
            // Collect every failed login attempt inside the selected window (guest
            // AND authenticated accounts) so any single wrong attempt shows a spike.
            // Old failures from the wide fetch window are excluded (window guard).
            if ($log_time >= $guest_start_time && $log_time <= $guest_end_time) {
                if ($username !== 'N/A' && $username !== '') {
                    $guest_failed_attempts[] = [
                        'username' => $username,
                        'timestamp' => $entry['timestamp'],
                        'ip' => $ip !== 'N/A' ? $ip : 'N/A',
                        'details' => $details !== '' ? $details : 'Invalid credentials',
                    ];
                }
                // Hourly failure distribution for the guest chart
                $guest_bucket = guest_bucket_index($guest_granularity, $log_time, $guest_start_time, count($guest_labels), $guest_range);
                $guest_failures_by_hour[$guest_bucket] = ($guest_failures_by_hour[$guest_bucket] ?? 0) + 1;
                // Per-user distribution (one line per username in the chart)
                if ($username !== 'N/A' && $username !== '') {
                    if (!isset($guest_failures_by_user_hour[$username])) {
                        $guest_failures_by_user_hour[$username] = array_fill(0, count($guest_labels), 0);
                    }
                    $guest_failures_by_user_hour[$username][$guest_bucket]++;
                    // Tag the attempt with its bucket so the tooltip can show only
                    // the attempts belonging to the hovered hour/day.
                    $guest_failed_attempts[count($guest_failed_attempts) - 1]['_bucket'] = $guest_bucket;
                }
                // Per-source-IP distribution (one line per attacker IP) — usernames
                // are random in brute-force attacks; the IP is what can be blocked.
                $guest_ip_key = ($ip !== 'N/A' && $ip !== '') ? $ip : 'Unknown IP';
                if (!isset($guest_failures_by_ip_hour[$guest_ip_key])) {
                    $guest_failures_by_ip_hour[$guest_ip_key] = array_fill(0, count($guest_labels), 0);
                }
                $guest_failures_by_ip_hour[$guest_ip_key][$guest_bucket]++;
            }
        }

        // Aggregate hourly activity ONLY for authenticated users (guest IDs must not appear)
        if ($is_authenticated) {
            $hour = (int)date('G', $log_time);
            if (!isset($activity_by_hour_by_user[$username])) {
                $activity_by_hour_by_user[$username] = array_fill(0, 24, 0);
            }
            $activity_by_hour_by_user[$username][$hour]++;
        }

        // Authenticated activity scoped to the guest (rolling) window — powers the
        // User Activity Tracking chart so it mirrors the guest chart's live 24H range.
        if ($is_authenticated && $log_time >= $guest_start_time && $log_time <= $guest_end_time) {
            if (!isset($guest_user_activity_data[$username])) {
                $guest_user_activity_data[$username] = [];
            }
            $guest_user_activity_data[$username][$action] = ($guest_user_activity_data[$username][$action] ?? 0) + 1;
        }

        $action_counts[$action] = ($action_counts[$action] ?? 0) + 1;
        
        // Final log entry for the table
        $logs[] = [
            'timestamp' => $entry['timestamp'],
            'username' => $entry['targetUser'],
            'action' => $action,
            'status' => $status,
            'ip' => $ip,
            'details' => $details,
            'performedBy' => $entry['performedBy']
        ];
    }

    arsort($action_counts);
    $stats['top_actions'] = $action_counts;

    // Heatmap range calculation
    $min_hour = 23;
    $max_hour = 0;
    $has_activity = false;
    foreach ($activity_by_hour_by_user as $user => $hours) {
        foreach ($hours as $hour => $count) {
            if ($count > 0) {
                $has_activity = true;
                if ($hour < $min_hour) $min_hour = $hour;
                if ($hour > $max_hour) $max_hour = $hour;
            }
        }
    }

    if ($has_activity) {
        $active_hour_labels = [];
        for ($h = $min_hour; $h <= $max_hour; $h++) {
            $active_hour_labels[] = date('g A', mktime($h, 0));
        }
        $sliced_activity = [];
        foreach ($activity_by_hour_by_user as $user => $hours) {
            $sliced_activity[$user] = array_slice($hours, $min_hour, $max_hour - $min_hour + 1);
        }
        $stats['activity_by_hour_by_user'] = $sliced_activity;
        $stats['active_hour_labels'] = $active_hour_labels;
    }

    // Top users for tracking charts (authenticated users only)
    $user_counts = [];
    foreach ($logs as $log) {
        if (!empty($log['username']) && isset($authenticated_users[$log['username']])) {
            $user_counts[$log['username']] = ($user_counts[$log['username']] ?? 0) + 1;
        }
    }
    arsort($user_counts);
    $top_users = array_slice(array_keys($user_counts), 0, 5);

    $user_activity_by_action = [];
    $all_actions = [];
    foreach ($logs as $log) {
        if (in_array($log['username'], $top_users)) {
            if (!isset($user_activity_by_action[$log['username']])) {
                $user_activity_by_action[$log['username']] = [];
            }
            $user_activity_by_action[$log['username']][$log['action']] = ($user_activity_by_action[$log['username']][$log['action']] ?? 0) + 1;
            if (!in_array($log['action'], $all_actions)) $all_actions[] = $log['action'];
        }
    }
    $stats['user_activity_tracking'] = ['users' => $top_users, 'actions' => $all_actions, 'data' => $user_activity_by_action];

    // Guest-window activity tracking (rolling last-24H etc.) — mirrors the main chart
    $guest_user_counts = [];
    foreach ($guest_user_activity_data as $u => $acts) {
        $guest_user_counts[$u] = array_sum($acts);
    }
    arsort($guest_user_counts);
    $guest_top_users = array_slice(array_keys($guest_user_counts), 0, 5);
    $guest_user_activity_tracking = ['users' => $guest_top_users, 'actions' => [], 'data' => []];
    foreach ($guest_top_users as $u) {
        $guest_user_activity_tracking['data'][$u] = $guest_user_activity_data[$u] ?? [];
        foreach (array_keys($guest_user_activity_data[$u] ?? []) as $act) {
            if (!in_array($act, $guest_user_activity_tracking['actions'], true)) {
                $guest_user_activity_tracking['actions'][] = $act;
            }
        }
    }

    // Handle session data
    $authenticated_users_repo = repo_read_authenticated_users();
    if (!empty($authenticated_users_repo)) {
        $valid_online_count = 0;
        $now = time();
        $stale_threshold = 15 * 60;

        foreach ($authenticated_users_repo as $uname => $session_data) {
            if (empty(trim((string)$uname))) continue;

            $last_activity = $session_data['last_activity'] ?? 0;
            if (($now - $last_activity) > $stale_threshold) continue;

            $valid_online_count++;
            $profile = $user_activity_profiles[$uname] ?? null;
            $online_users_list[] = [
                'username' => (string)$uname,
                'ip' => $session_data['ip_address'] ?? $profile['last_ip'] ?? 'N/A',
                'current_session_time' => (isset($session_data['login_time'])) ? $now - $session_data['login_time'] : 0,
                'last_session' => ($profile) ? date('Y-m-d H:i:s', $profile['last_activity_time']) : 'N/A',
                'login_count' => $profile['login_count'] ?? 0
            ];
        }
        $stats['online_users'] = $valid_online_count;
    }

    // Sort guest attempts newest-first, cap for UI
    usort($guest_failed_attempts, fn($a, $b) => strtotime((string)$b['timestamp']) - strtotime((string)$a['timestamp']));
    $guest_failed_attempts = array_slice($guest_failed_attempts, 0, 100);

    // Ensure totals + per-user arrays match the bucket count exactly
    // (resize by bucket index — array_slice would reindex and misalign the labels)
    $resized_totals = array_fill(0, $guest_bucket_count, 0);
    foreach ($guest_failures_by_hour as $bucket => $count) {
        if ($bucket >= 0 && $bucket < $guest_bucket_count) $resized_totals[$bucket] = $count;
    }
    $guest_failures_by_hour = $resized_totals;
    foreach ($guest_failures_by_user_hour as $user => $hours) {
        $resized = array_fill(0, $guest_bucket_count, 0);
        foreach ($hours as $bucket => $count) {
            if ($bucket >= 0 && $bucket < $guest_bucket_count) $resized[$bucket] = $count;
        }
        $guest_failures_by_user_hour[$user] = $resized;
    }
    foreach ($guest_failures_by_ip_hour as $ip => $hours) {
        $resized = array_fill(0, $guest_bucket_count, 0);
        foreach ($hours as $bucket => $count) {
            if ($bucket >= 0 && $bucket < $guest_bucket_count) $resized[$bucket] = $count;
        }
        $guest_failures_by_ip_hour[$ip] = $resized;
    }
}

process_user_data($response['stats'], $response['enabled_users_list']);
process_audit_log($start_time, $end_time, $response['stats'], $response['logs'], $response['online_users_list'], $response['successful_logins_list'], $response['failed_logins_list'], $response['guest_failed_attempts'], $response['guest_failures_by_hour'], $response['guest_failures_by_user_hour'], $response['guest_failures_by_ip_hour'], $guest_range, $guest_start_time, $guest_end_time, $response['guest_labels'], $response['guest_granularity'], $response['guest_user_activity_tracking']);
echo json_encode($response);
