<?php

date_default_timezone_set('Asia/Dhaka');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) { session_start(); }
define('_CORE_ADMIN_', true);

require_once __DIR__ . '/../../Support/helpers.php';
require_once app_root('bootstrap/request_context.php');
require_once __DIR__ . '/../../../Ldap/Config/ldap_config_repository.php';
require_once __DIR__ . '/../../../Infrastructure/Logging/dashboard_log_reader.php';
require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.']);
    exit();
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

$hasLogPermission = has_permission('card_dashboard_today_log')
    || has_permission('card_dashboard_log_table')
    || has_permission('card_recent_activity');
if (!$hasLogPermission) {
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to view logs.']);
    exit();
}

$timePeriod = $_GET['time_period'] ?? 'week';
$domainFilter = $_GET['domain'] ?? '';
$isExport = !empty($_GET['export']);
$cutoffDate = null;
$endCutoffDate = 0;

switch ($timePeriod) {
    case 'today':
        $cutoffDate = strtotime('today midnight');
        break;
    case '72hours':
        $cutoffDate = strtotime('-72 hours');
        break;
    case 'week':
        $cutoffDate = strtotime('-7 days');
        break;
    case 'month':
        $cutoffDate = strtotime('-30 days');
        break;
    case 'custom':
        $startStr = $_GET['start_date'] ?? '';
        $endStr = $_GET['end_date'] ?? '';
        if ($startStr !== '') {
            $cutoffDate = strtotime($startStr . ' 00:00:00');
        }
        if ($endStr !== '') {
            $endCutoffDate = strtotime($endStr . ' 23:59:59');
        }
        break;
    case 'all':
    default:
        $cutoffDate = null;
        break;
}

// Resolve domain option for the reader
$readerDomain = $domainFilter === '' ? '' : $domainFilter;

$detailedLogs = dashboard_read_logs([
    'include_root' => false,
    'cutoff_time' => (int)($cutoffDate ?? 0),
    'end_cutoff_time' => $endCutoffDate,
    'include_errors' => true,
    'skip_categories' => ['userinfo'],
    'domain' => $readerDomain,
]);
$unfilteredLogs = $detailedLogs;

// Available domains from the filesystem
try {
    $availableDomains = dashboard_log_domain_dirs();
} catch (Throwable $e) {
    $availableDomains = [];
    error_log('dashboard_log_domain_dirs() error: ' . $e->getMessage());
}
$activeDomain = dashboard_active_domain_key();

// Build domain key → AD name lookup (ldap config first = source of truth)
$domainKeyToAdName = [];
if (function_exists('ldap_get_domains')) {
    foreach (ldap_get_domains() as $d) {
        $k = $d['key'] ?? '';
        if ($k !== '') {
            $baseDn = $d['base_dn'] ?? '';
            $adName = '';
            if ($baseDn !== '') {
                $parts = [];
                preg_match_all('/DC\s*=\s*([^,]+)/i', $baseDn, $parts);
                if (!empty($parts[1])) {
                    $adName = strtolower(implode('.', $parts[1]));
                }
            }
            $domainKeyToAdName[$k] = $adName ?: $k;
        }
    }
}
// Fallback: filesystem dirs for any keys ldap doesn't know about
foreach ($availableDomains as $d) {
    $k = $d['key'] ?? '';
    if ($k !== '' && !isset($domainKeyToAdName[$k])) {
        $domainKeyToAdName[$k] = $d['ad_name'] ?? $k;
    }
}
// Resolve domain values in unfilteredLogs
foreach ($unfilteredLogs as &$log) {
    $dk = $log['domain'] ?? '';
    if ($dk !== '' && isset($domainKeyToAdName[$dk])) {
        $log['domain'] = $domainKeyToAdName[$dk];
    }
}
unset($log);

// Use AD name for active domain in response (not config key)
$activeDomain = $domainKeyToAdName[$activeDomain] ?? $activeDomain;

// ── CSV Export ─────────────────────────────────────────────────────
if ($isExport) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Timestamp', 'Domain', 'Action', 'Target User', 'Status', 'Performed By', 'Message', 'Category', 'IP']);
    foreach ($unfilteredLogs as $log) {
        fputcsv($out, [
            $log['timestamp'] ?? '',
            $log['domain'] ?? '',
            $log['action'] ?? '',
            $log['targetUser'] ?? '',
            $log['status'] ?? '',
            $log['performedBy'] ?? '',
            $log['message'] ?? '',
            $log['category'] ?? '',
            $log['ip'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

$availableCategories = array_values(array_unique(array_column($unfilteredLogs, 'category')));
$rawStatuses = array_values(array_unique(array_column($unfilteredLogs, 'status')));
$availableStatuses = array_values(array_unique(array_map('dashboard_normalize_status', $rawStatuses)));
sort($availableCategories);
sort($availableStatuses);

$todayActionStatusBreakdown = [];
$todayStatusBreakdown = [];
foreach ($unfilteredLogs as $log) {
    if (strtotime((string)$log['timestamp']) >= strtotime('today midnight')) {
        $action = strtoupper(trim((string)$log['action']));
        $status = dashboard_normalize_status((string)$log['status']);
        $todayActionStatusBreakdown[$action] = ($todayActionStatusBreakdown[$action] ?? 0) + 1;
        $todayStatusBreakdown[$status] = ($todayStatusBreakdown[$status] ?? 0) + 1;
    }
}

$monthlyActions = [];
$monthlyStatusBreakdown = [];
$thisMonth = date('Y-m');
foreach ($unfilteredLogs as $log) {
    if (date('Y-m', strtotime((string)$log['timestamp'])) === $thisMonth) {
        $action = (string)$log['action'];
        $status = dashboard_normalize_status((string)$log['status']);
        $monthlyActions[$action] = ($monthlyActions[$action] ?? 0) + 1;
        $monthlyStatusBreakdown[$status] = ($monthlyStatusBreakdown[$status] ?? 0) + 1;
    }
}

$filteredLogs = $detailedLogs;
$searchTerm = isset($_GET['search']) ? strtolower(trim((string)$_GET['search'])) : null;
$categoryFilter = $_GET['category'] ?? null;
$statusFilter = isset($_GET['status']) ? strtoupper((string)$_GET['status']) : null;

if ($searchTerm || $categoryFilter || $statusFilter || $timePeriod !== 'all') {
    $filteredLogs = array_filter($detailedLogs, function ($log) use ($searchTerm, $categoryFilter, $statusFilter, $timePeriod, $cutoffDate, $endCutoffDate) {
        $matchesSearch = !$searchTerm
            || strpos(strtolower((string)$log['action']), $searchTerm) !== false
            || strpos(strtolower((string)$log['targetUser']), $searchTerm) !== false
            || strpos(strtolower((string)$log['performedBy']), $searchTerm) !== false;
        $matchesCategory = !$categoryFilter || strtolower((string)$log['category']) === strtolower((string)$categoryFilter);
        $logStatus = dashboard_normalize_status((string)$log['status']);
        $matchesStatus = !$statusFilter || $logStatus === $statusFilter;
        $logTimestamp = strtotime((string)$log['timestamp']);
        $matchesTime = ($timePeriod === 'all')
            || ($cutoffDate && $logTimestamp >= $cutoffDate && (!$endCutoffDate || $logTimestamp <= $endCutoffDate))
            || ($timePeriod === 'custom' && !$cutoffDate);
        return $matchesSearch && $matchesCategory && $matchesStatus && $matchesTime;
    });
}

$statusTypes = ['SUCCESS', 'SKIPPED', 'TRIGGERED', 'FAILED', 'NOT FOUND'];
$users = [];
foreach ($filteredLogs as $log) {
    $performedBy = trim((string)$log['performedBy']);
    $users[$performedBy] = ($users[$performedBy] ?? 0) + 1;
}
arsort($users);
$topUsers = array_slice($users, 0, 5, true);

$actionStatusBreakdownData = [];
$activeActionNames = array_keys(array_reduce($filteredLogs, function ($carry, $log) {
    $carry[$log['action']] = 1;
    return $carry;
}, []));
foreach ($activeActionNames as $actionName) {
    $actionStatusBreakdownData[$actionName] = array_fill_keys($statusTypes, 0);
}
foreach ($filteredLogs as $log) {
    $action = (string)$log['action'];
    $status = dashboard_normalize_status((string)$log['status']);
    if (isset($actionStatusBreakdownData[$action]) && in_array($status, $statusTypes, true)) {
        $actionStatusBreakdownData[$action][$status]++;
    }
}

$historicalStatusData = [];
if ($timePeriod === 'today') {
    for ($h = 0; $h < 24; $h++) {
        $historicalStatusData[str_pad((string)$h, 2, '0', STR_PAD_LEFT)] = array_fill_keys($statusTypes, 0);
    }
    foreach ($filteredLogs as $log) {
        $logHour = date('H', strtotime((string)$log['timestamp']));
        $status = dashboard_normalize_status((string)$log['status']);
        if (isset($historicalStatusData[$logHour])) {
            $historicalStatusData[$logHour][$status]++;
        }
    }
} else {
    if (!empty($filteredLogs)) {
        $startDate = $cutoffDate ?? strtotime((string)min(array_column($filteredLogs, 'timestamp')));
    } else {
        $startDate = $cutoffDate ?? time();
    }
    $currentDate = $startDate;
    while ($currentDate <= time()) {
        $date = date('Y-m-d', $currentDate);
        $historicalStatusData[$date] = array_fill_keys($statusTypes, 0);
        $currentDate = strtotime('+1 day', $currentDate);
    }
    foreach ($filteredLogs as $log) {
        $logDate = date('Y-m-d', strtotime((string)$log['timestamp']));
        $status = dashboard_normalize_status((string)$log['status']);
        if (isset($historicalStatusData[$logDate])) {
            $historicalStatusData[$logDate][$status]++;
        }
    }
}

echo json_encode([
    'todayActionStatusBreakdown' => $todayActionStatusBreakdown,
    'todayStatusBreakdown' => $todayStatusBreakdown,
    'detailedLogs' => array_values($filteredLogs),
    'allLogs' => $detailedLogs,
    'monthlyActions' => $monthlyActions,
    'monthlyStatusBreakdown' => $monthlyStatusBreakdown,
    'users' => $topUsers,
    'historicalStatusData' => $historicalStatusData,
    'actionStatusBreakdownData' => $actionStatusBreakdownData,
    'timePeriod' => $timePeriod,
    'debug_received_time_period' => $timePeriod,
    'available_categories' => $availableCategories,
    'available_statuses' => $availableStatuses,
    'available_domains' => $availableDomains,
    'activeDomain' => $activeDomain,
]);
