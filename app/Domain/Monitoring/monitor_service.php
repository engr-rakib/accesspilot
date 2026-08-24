<?php
/**
 * app/Domain/Monitoring/monitor_service.php
 * 
 * Domain logic for high-speed infrastructure monitoring and Network Intelligence.
 * Optimized for technical accuracy, persistent state tracking, and node-wise logging.
 * Fully integrated with centralized mailer and notification center.
 */

require_once __DIR__ . '/../../Infrastructure/Persistence/repositories.php';
require_once __DIR__ . '/../../Infrastructure/Mail/mailer.php';

/**
 * Returns the ping count flag and timeout value for the current OS.
 */
function os_ping_args(int $count = 1, int $timeoutMs = 500): string {
    if (stripos(PHP_OS, 'WIN') === 0) {
        return '-n ' . $count . ' -w ' . $timeoutMs;
    }
    return '-c ' . $count . ' -W ' . max(1, intval(ceil($timeoutMs / 1000)));
}

/**
 * Retrieves cached node state WITHOUT pinging (instant response).
 */
function monitor_get_servers(): array {
    return repo_read_monitored_servers();
}

/**
 * Pings stale nodes (last check > 15s, max 2 per call) and returns updated state.
 */
function monitor_refresh_stale(): array {
    $servers = repo_read_monitored_servers();
    
    foreach ($servers as $s) {
        $lastCheck = $s['last_check'] ? strtotime($s['last_check']) : 0;
        if (time() - $lastCheck > 10) {
            monitor_ping_server($s['ip']);
        }
    }
    
    return repo_read_monitored_servers();
}

function monitor_upsert_server(array $data): array {
    $ip = trim($data['ip'] ?? '');
    if (empty($ip)) return ['success' => false, 'message' => 'Node IPv4 is mandatory.'];

    $servers = repo_read_monitored_servers();
    $foundIdx = -1;
    
    foreach ($servers as $idx => $s) {
        if ($s['ip'] === $ip) {
            $foundIdx = $idx;
            break;
        }
    }

    $node = ($foundIdx !== -1) ? $servers[$foundIdx] : [
        'ip' => $ip,
        'owner_name' => trim($data['owner_name'] ?? 'Infrastructure Admin'),
        'owner_email' => trim($data['owner_email'] ?? ''),
        'status' => 'unknown',
        'consecutive_failures' => 0,
        'last_check' => null,
        'assigned_at' => date('Y-m-d H:i:s'),
        'downtime_history' => [],
        'dns_name' => 'Resolving...',
        'min_ttl' => 0, 'max_ttl' => 0, 'avg_ttl' => 0,
        'packet_loss' => 0,
        'history' => [],
        'traceroute' => [],
        'hop_count' => 0,
        'gateway_hop' => 'N/A'
    ];

    if (isset($data['owner_name'])) $node['owner_name'] = trim($data['owner_name']);
    if (isset($data['owner_email'])) $node['owner_email'] = trim($data['owner_email']);

    if ($foundIdx !== -1) {
        $servers[$foundIdx] = $node;
    } else {
        $servers[] = $node;
    }

    if (!repo_write_monitored_servers($servers)) {
        return ['success' => false, 'message' => "Technical database write failure."];
    }

    monitor_ping_server($ip);
    return ['success' => true, 'message' => "Node $ip registered."];
}

function monitor_delete_server(string $ip): bool {
    $servers = repo_read_monitored_servers();
    $newList = array_values(array_filter($servers, fn($s) => $s['ip'] !== $ip));
    return repo_write_monitored_servers($newList);
}

/**
 * Technical Heartbeat: High-speed ping and telemetry update.
 */
function monitor_ping_server(string $ip): array {
    $servers = repo_read_monitored_servers();
    $foundIdx = -1;
    foreach ($servers as $idx => $s) { if ($s['ip'] === $ip) { $foundIdx = $idx; break; } }
    if ($foundIdx === -1) return ['success' => false, 'message' => 'Node mapping lost.'];

    $node = $servers[$foundIdx];
    $oldStatus = $node['status'];
    
    // DNS resolution
    $dnsName = gethostbyaddr($ip);
    $node['dns_name'] = ($dnsName === $ip) ? 'Unresolved' : $dnsName;

    // PING (1 packet, 200ms timeout per packet)
    $output = [];
    $resultCode = 0;
    exec("ping " . os_ping_args(1, 200) . " " . escapeshellarg($ip), $output, $resultCode);
    
    $ttl = 0;
    $packetLoss = ($resultCode === 0) ? 0 : 100;
    foreach ($output as $line) {
        if (preg_match('/time[=<]([\d.]+)\s?ms/', $line, $matches)) {
            $ttl = round((float)$matches[1], 1);
            break;
        }
    }
    $isUp = ($resultCode === 0);
    // Consecutive failure smoothing: require 3 failures in a row to declare DOWN
    if ($isUp) {
        $node['consecutive_failures'] = 0;
    } else {
        $node['consecutive_failures'] = ($node['consecutive_failures'] ?? 0) + 1;
    }
    $newStatus = ($isUp || $node['consecutive_failures'] < 3) ? 'up' : 'down';

    // Update Node State
    $node['status'] = $newStatus;
    $node['avg_ttl'] = $ttl;
    $node['min_ttl'] = ($node['min_ttl'] == 0 || $ttl < $node['min_ttl']) ? $ttl : $node['min_ttl'];
    $node['max_ttl'] = ($ttl > $node['max_ttl']) ? $ttl : $node['max_ttl'];
    $node['packet_loss'] = $packetLoss;
    $node['last_check'] = date('Y-m-d H:i:s');
    
    // History Tracking (Last 200 points)
    $history = $node['history'] ?? [];
    $history[] = ['time' => date('H:i:s'), 'up' => $isUp, 'ttl' => $ttl, 'loss' => $packetLoss];
    if (count($history) > 200) $history = array_slice($history, -200);
    $node['history'] = $history;

    // Traceroute (Only on transition)
    if (empty($node['traceroute']) || $oldStatus !== $newStatus) {
        $traceOutput = [];
        if ($isUp) {
            exec((stripos(PHP_OS, 'WIN') === 0 ? 'tracert -h 5 -d' : 'traceroute -m 5 -n') . " " . escapeshellarg($ip), $traceOutput);
            $hops = array_values(array_filter(array_map('trim', $traceOutput), fn($l) => preg_match('/^\d+/', $l)));
            $node['traceroute'] = $hops;
            $node['hop_count'] = count($hops);
            if (!empty($hops)) {
                $lastHop = end($hops);
                $node['gateway_hop'] = trim(preg_replace('/^\d+\s+\d+\s+ms\s+\d+\s+ms\s+\d+\s+ms\s+/', '', $lastHop));
            }
        }
    }

    $servers[$foundIdx] = $node;
    repo_write_monitored_servers($servers);

    // PERSIST TECHNICAL LOG
    monitor_log_technical_event($ip, $newStatus, $ttl, $packetLoss);

    // TRACK DOWNTIME
    if ($newStatus === 'down' && $oldStatus !== 'down') {
        $downHistory = $node['downtime_history'] ?? [];
        $downHistory[] = ['down_at' => date('Y-m-d H:i:s'), 'up_at' => null, 'duration_seconds' => 0];
        $node['downtime_history'] = $downHistory;
    } elseif ($newStatus === 'up' && $oldStatus === 'down') {
        $downHistory = $node['downtime_history'] ?? [];
        for ($di = count($downHistory) - 1; $di >= 0; $di--) {
            if ($downHistory[$di]['up_at'] === null) {
                $downHistory[$di]['up_at'] = date('Y-m-d H:i:s');
                $downHistory[$di]['duration_seconds'] = strtotime($downHistory[$di]['up_at']) - strtotime($downHistory[$di]['down_at']);
                break;
            }
        }
        $node['downtime_history'] = $downHistory;
    }

    // ALERTS ON TRANSITION
    if ($oldStatus !== $newStatus) {
        monitor_trigger_notification($ip, $newStatus, "RTT: {$ttl}ms. PL: {$packetLoss}%");
        monitor_send_alert($node);
    }

    return ['success' => true, 'status' => $newStatus, 'avg_ttl' => $ttl];
}

/**
 * Node-Wise Standard Technical Logging.
 * Path: C:/access_pilot_logs/monitoring/[IP]/[YYYY-MM-DD].log
 */
function monitor_log_technical_event(string $ip, string $status, int $rtt, int $loss) {
    $baseLogDir = get_external_log_base();
    $safeIp = str_replace('.', '_', $ip);
    $nodeDir = rtrim($baseLogDir, '/\\') . DIRECTORY_SEPARATOR . 'monitoring' . DIRECTORY_SEPARATOR . $safeIp;
    
    if (!is_dir($nodeDir)) {
        @mkdir($nodeDir, 0775, true);
    }

    $logFile = $nodeDir . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
    $timestamp = date('H:i:s');
    $statusUpper = strtoupper($status);
    $logLine = "[$timestamp] STATUS: $statusUpper | RTT: {$rtt}ms | LOSS: $loss%\n";
    
    @file_put_contents($logFile, $logLine, FILE_APPEND);
}

/**
 * Integrated Global Notification Engine.
 */
function monitor_trigger_notification(string $ip, string $status, string $details) {
    require_once __DIR__ . '/../Notifications/notification_service.php';
    if (function_exists('notifications_create_manual_notification')) {
        notifications_create_manual_notification([
            'category' => 'security',
            'title' => 'NOC ALARM: ' . $ip,
            'message' => "Node $ip is now $status. $details",
            'severity' => ($status === 'up' ? 'info' : 'danger'),
            'target_url' => admin_page_url('monitoring'),
            'is_persistent' => ($status === 'down')
        ], 'System Monitor');
    }
}

/**
 * Industrial Alert Mailer (Uses config/mailer_config.php).
 */
function monitor_send_alert(array $node) {
    $mailer_config = config_get('mailer', []);
    if (!($mailer_config['alerts_enabled'] ?? false) || !($mailer_config['monitoring_alerts_enabled'] ?? false)) {
        return;
    }

    if (empty($node['owner_email'])) return;
    $ip = $node['ip']; $status = strtoupper($node['status']);
    
    $subject = "INFRASTRUCTURE ALERT: $ip is $status";
    
    $body = "AccessPilot Technical Alert\n";
    $body .= "--------------------------------------\n";
    $body .= "Node Endpoint:  $ip\n";
    $body .= "DNS Identity:   {$node['dns_name']}\n";
    $body .= "Current State:  $status\n";
    $body .= "Avg Latency:    {$node['avg_ttl']}ms\n";
    $body .= "Packet Loss:    {$node['packet_loss']}%\n";
    $body .= "Owner assigned: {$node['owner_name']}\n";
    $body .= "Incident Time:  " . date('Y-m-d H:i:s') . "\n";
    $body .= "--------------------------------------\n\n";
    $body .= "This is an automated diagnostic message from the AccessPilot NOC engine.";

    sendEmail($node['owner_email'], $subject, $body);
}

function monitor_run_sweep(): array {
    $servers = repo_read_monitored_servers();
    $results = [];
    foreach ($servers as $s) { $results[$s['ip']] = monitor_ping_server($s['ip']); }
    return $results;
}

/**
 * --- NETWORK INTELLIGENCE ---
 */

function monitor_calculate_network(string $cidr): array {
    // Better auto-detection of mask if missing
    if (strpos($cidr, '/') === false) {
        $ip = trim($cidr);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return [];
        
        $firstOctet = (int)explode('.', $ip)[0];
        if ($firstOctet >= 1 && $firstOctet <= 126) $mask = 8;
        elseif ($firstOctet >= 128 && $firstOctet <= 191) $mask = 16;
        elseif ($firstOctet >= 192 && $firstOctet <= 223) $mask = 24;
        else $mask = 32;
        $cidr = "$ip/$mask";
    } else {
        list($ip, $mask) = explode('/', $cidr);
        $mask = (int)$mask;
    }
    
    $ipLong = ip2long($ip);
    $maskBin = ($mask === 0) ? 0 : (~(pow(2, 32 - $mask) - 1) & 0xFFFFFFFF);
    
    $netLong = $ipLong & $maskBin;
    $bcLong = $ipLong | (~$maskBin & 0xFFFFFFFF);
    
    return [
        'ip' => $ip,
        'cidr' => $cidr,
        'mask_bits' => $mask,
        'network' => long2ip($netLong),
        'mask' => long2ip($maskBin),
        'broadcast' => long2ip($bcLong),
        'first_ip' => long2ip($netLong + 1),
        'last_ip' => long2ip($bcLong - 1),
        'total_hosts' => max(0, pow(2, (32 - $mask)) - 2),
        'gateway' => long2ip($netLong + 1)
    ];
}

function monitor_scan_single_ip(string $ip): array {
    $dns = gethostbyaddr($ip);
    exec("ping " . os_ping_args(1, 150) . " " . escapeshellarg($ip), $out, $ret);
    
    $rtt = 0;
    if ($ret === 0) {
        foreach ($out as $line) {
            if (preg_match('/time[=<](\d+)\s?ms/', $line, $matches)) { $rtt = (int)$matches[1]; break; }
        }
    }

    return [
        'ip' => $ip,
        'status' => ($ret === 0 ? 'used' : 'free'),
        'latency' => $rtt,
        'dns' => ($dns === $ip ? 'Unresolved' : $dns)
    ];
}

function monitor_get_logs(string $ip = '', string $date = ''): array {
    $baseLogDir = get_external_log_base() . DIRECTORY_SEPARATOR . 'monitoring';
    if (!is_dir($baseLogDir)) return ['success' => true, 'logs' => []];
    $servers = repo_read_monitored_servers();
    $result = [];
    foreach ($servers as $s) {
        if ($ip && $s['ip'] !== $ip) continue;
        $safeIp = str_replace('.', '_', $s['ip']);
        $nodeDir = $baseLogDir . DIRECTORY_SEPARATOR . $safeIp;
        $logFile = $nodeDir . DIRECTORY_SEPARATOR . ($date ?: date('Y-m-d')) . '.log';
        $entries = [];
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach (array_slice($lines, -100) as $line) {
                if (preg_match('/\[([^\]]+)\]\s+STATUS:\s+(\S+)\s+\|\s+RTT:\s+(\d+)ms\s+\|\s+LOSS:\s+(\d+)%/', $line, $m)) {
                    $entries[] = ['time' => $m[1], 'status' => strtolower($m[2]), 'rtt' => (int)$m[3], 'loss' => (int)$m[4]];
                }
            }
        }
        $result[] = ['ip' => $s['ip'], 'dns' => $s['dns_name'] ?? '', 'owner' => $s['owner_name'] ?? '', 'entries' => $entries];
    }
    return ['success' => true, 'logs' => $result, 'date' => $date ?: date('Y-m-d')];
}

function monitor_get_history_summary(): array {
    $servers = repo_read_monitored_servers();
    $summary = [];
    foreach ($servers as $s) {
        $history = $s['history'] ?? [];
        $upCount = 0; $downCount = 0;
        foreach (array_slice($history, -30) as $h) {
            if ($h['up']) $upCount++; else $downCount++;
        }
        $summary[] = [
            'ip' => $s['ip'],
            'status' => $s['status'],
            'uptime' => ($upCount + $downCount) > 0 ? round(($upCount / ($upCount + $downCount)) * 100) : 100,
            'avg_rtt' => $s['avg_ttl'],
            'last_check' => $s['last_check'],
            'history_count' => count($history)
        ];
    }
    return ['success' => true, 'summary' => $summary];
}

function monitor_get_node_summary(string $ip): array {
    $servers = repo_read_monitored_servers();
    $node = null;
    foreach ($servers as $s) {
        if ($s['ip'] === $ip) { $node = $s; break; }
    }
    if (!$node) return ['success' => false, 'message' => 'Node not found'];

    $downCount = 0;
    $totalDownSeconds = 0;
    $downHistory = $node['downtime_history'] ?? [];
    $recentDowns = [];
    foreach ($downHistory as $dh) {
        if ($dh['up_at'] !== null) {
            $downCount++;
            $totalDownSeconds += $dh['duration_seconds'];
            $recentDowns[] = $dh;
        }
    }
    $recentDowns = array_slice($recentDowns, -10);

    // Calculate uptime since assigned_at
    $assignedAt = $node['assigned_at'] ?? $node['last_check'] ?? date('Y-m-d H:i:s');
    $assignedTs = strtotime($assignedAt);
    $elapsedSeconds = max(1, time() - $assignedTs);
    $uptimePercent = $elapsedSeconds > 0 ? round(($elapsedSeconds - $totalDownSeconds) / $elapsedSeconds * 100, 1) : 100;

    // Read today's logs for hourly breakdown
    $baseLogDir = get_external_log_base() . DIRECTORY_SEPARATOR . 'monitoring';
    $safeIp = str_replace('.', '_', $ip);
    $logFile = $baseLogDir . DIRECTORY_SEPARATOR . $safeIp . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
    $hourly = array_fill(0, 24, ['total' => 0, 'up' => 0, 'down' => 0]);
    if (file_exists($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        // Also check yesterday for completeness
        foreach ($lines as $line) {
            if (preg_match('/\[(\d+):(\d+):\d+\]\s+STATUS:\s+(\S+)/', $line, $m)) {
                $h = (int)$m[1];
                $isUp = strtolower($m[3]) === 'up';
                if ($h >= 0 && $h <= 23) {
                    $hourly[$h]['total']++;
                    if ($isUp) $hourly[$h]['up']++;
                    else $hourly[$h]['down']++;
                }
            }
        }
    }

    // Daily summary from log files (last 365 days for monthly aggregation)
    $daily = [];
    $monthlyBuckets = [];
    for ($d = 0; $d < 365; $d++) {
        $dateStr = date('Y-m-d', strtotime("-{$d} days"));
        $df = $baseLogDir . DIRECTORY_SEPARATOR . $safeIp . DIRECTORY_SEPARATOR . $dateStr . '.log';
        $dayUp = 0; $dayDown = 0;
        if (file_exists($df)) {
            $dlines = file($df, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($dlines as $dl) {
                if (preg_match('/STATUS:\s+(\S+)/', $dl, $dm)) {
                    if (strtolower($dm[1]) === 'up') $dayUp++;
                    else $dayDown++;
                }
            }
        }
        $daily[] = ['date' => $dateStr, 'up' => $dayUp, 'down' => $dayDown];
    }

    // Aggregate daily into monthly buckets
    $monthly = [];
    foreach ($daily as $day) {
        $mk = substr($day['date'], 0, 7);
        if (!isset($monthlyBuckets[$mk])) {
            $monthlyBuckets[$mk] = ['month' => $mk, 'up' => 0, 'down' => 0, 'days' => []];
        }
        $monthlyBuckets[$mk]['up'] += $day['up'];
        $monthlyBuckets[$mk]['down'] += $day['down'];
        $monthlyBuckets[$mk]['days'][$day['date']] = $day;
    }
    ksort($monthlyBuckets);
    foreach ($monthlyBuckets as $mb) {
        $monthly[] = $mb;
    }

    return [
        'success' => true,
        'ip' => $ip,
        'assigned_at' => $assignedAt,
        'uptime_seconds' => $elapsedSeconds,
        'uptime_percent' => $uptimePercent,
        'down_count' => $downCount,
        'total_downtime_seconds' => $totalDownSeconds,
        'recent_downs' => $recentDowns,
        'hourly' => $hourly,
        'daily' => $daily,
        'monthly' => $monthly,
    ];
}

function monitor_manual_ping_test(string $ip, int $count = 4): array {
    $output = [];
    $ret = 0;
    $start = microtime(true);
    exec("ping " . os_ping_args($count, 2000) . " " . escapeshellarg($ip), $output, $ret);
    $elapsed = round((microtime(true) - $start) * 1000);
    $latency = 'timeout';
    if ($ret === 0) {
        if ($count === 1) {
            $latency = $elapsed . 'ms';
        } else {
            $latency = $elapsed . 'ms';
        }
    }
    return [
        'success' => ($ret === 0),
        'ip' => $ip,
        'raw' => implode("\n", $output),
        'latency' => $latency
    ];
}
