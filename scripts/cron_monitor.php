#!/usr/bin/env php
<?php
/**
 * scripts/cron_monitor.php
 * 
 * Background 24/7 monitoring pinger — run via cron every 30-60 seconds.
 * Pings ALL monitored nodes and updates the JSON state + log files.
 * No browser or login required.
 * 
 * Usage:
 *   php scripts/cron_monitor.php
 * 
 * Cron (every 30s):
 *   * * * * * php /var/www/html/scripts/cron_monitor.php
 *   * * * * * sleep 30 && php /var/www/html/scripts/cron_monitor.php
 */

// Minimal bootstrap for CLI mode
if (PHP_SAPI !== 'cli') {
    exit("CLI mode only.\n");
}

$_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/..';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';

define('_CORE_ADMIN_', true);
define('APP_ENV', 'cli');

// Bootstrap — load helpers + repo functions + monitoring service
$bootFiles = [
    __DIR__ . '/../app/Application/Support/helpers.php',
    __DIR__ . '/../app/Infrastructure/Persistence/repositories.php',
    __DIR__ . '/../app/Domain/Monitoring/monitor_service.php',
];
foreach ($bootFiles as $f) {
    if (file_exists($f)) require_once $f;
}

echo "[" . date('Y-m-d H:i:s') . "] NOC Cron: Starting sweep...\n";

// Read servers directly (avoid bootstrap init like session_start)
$dataFile = '/data/secure/monitoring/monitored_servers.json';
$servers = [];
if (file_exists($dataFile) && is_readable($dataFile)) {
    $raw = file_get_contents($dataFile);
    if (!empty($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $servers = $decoded;
    }
}
echo "[" . date('Y-m-d H:i:s') . "] NOC Cron: Servers loaded: " . count($servers) . "\n";
if (empty($servers)) {
    echo "[" . date('Y-m-d H:i:s') . "] NOC Cron: No nodes to monitor.\n";
    exit(0);
}

// Migrate legacy nodes: add assigned_at if missing
$dirty = false;
foreach ($servers as &$s) {
    if (empty($s['assigned_at'])) {
        // Use first history entry timestamp, or now
        if (!empty($s['history']) && !empty($s['history'][0]['time'])) {
            // Some legacy entries store only time (H:i:s); prepend today's date
            $t = $s['history'][0]['time'];
            if (strpos($t, '-') === false) {
                $t = date('Y-m-d') . ' ' . $t;
            }
            $s['assigned_at'] = $t;
        } else {
            $s['assigned_at'] = date('Y-m-d H:i:s');
        }
        $dirty = true;
        echo "[" . date('Y-m-d H:i:s') . "] NOC Cron: Migrated {$s['ip']} assigned_at = {$s['assigned_at']}\n";
    }
}
unset($s);
if ($dirty) {
    file_put_contents(
        $dataFile,
        json_encode(array_values($servers), JSON_PRETTY_PRINT)
    );
    echo "[" . date('Y-m-d H:i:s') . "] NOC Cron: Legacy migration saved.\n";
}

$count = count($servers);
$upCount = 0;
$downCount = 0;

foreach ($servers as $s) {
    $ip = $s['ip'];
    $result = monitor_ping_server($ip);
    if ($result['success']) {
        if ($result['status'] === 'up') {
            $upCount++;
        } else {
            $downCount++;
        }
        echo "[" . date('Y-m-d H:i:s') . "] NOC Cron: {$ip} → {$result['status']} ({$result['avg_ttl']}ms)\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] NOC Cron: {$ip} → FAILED\n";
    }
}

echo "[" . date('Y-m-d H:i:s') . "] NOC Cron: Sweep complete — {$count} nodes, {$upCount} up, {$downCount} down.\n";
