#!/usr/bin/env php
<?php
if (PHP_SAPI !== 'cli') exit("CLI only\n");

$logDir = '/data/logs/monitoring';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

$stateFile = '/tmp/sys_hist_recorder_state.json';

// --- collect samples ---
function getCpuacctUsage() { $v = @file_get_contents('/host-cgroup/cpuacct/docker/' . trim(file_get_contents('/proc/1/cgroup')) . '/cpuacct.usage'); preg_match('!/docker/([a-f0-9]{64})!', file_get_contents('/proc/1/cgroup'), $m); if (empty($m[1])) return 0; $v = @file_get_contents("/host-cgroup/cpuacct/docker/{$m[1]}/cpuacct.usage"); return $v ? (float)trim($v) : 0; }
function getCpuacctUsage2($cid) { $v = @file_get_contents("/host-cgroup/cpuacct/docker/$cid/cpuacct.usage"); return $v ? (float)trim($v) : 0; }
function getHostJiffies() {
    $s = @file_get_contents('/proc/stat');
    if (!$s || !preg_match('/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/m', $s, $m)) return 0;
    return (int)$m[1] + (int)$m[2] + (int)$m[3] + (int)$m[4] + (int)$m[5] + (int)$m[6] + (int)$m[7] + (int)$m[8];
}
function getCpuCores() { $n = (int)@shell_exec('nproc 2>/dev/null'); return $n > 0 ? $n : 1; }

// Get container ID from cgroup
$cg = @file_get_contents('/proc/1/cgroup');
preg_match('!/docker/([a-f0-9]{64})!', $cg ?: '', $m);
$containerId = $m[1] ?? '';
$shortId = substr($containerId, 0, 12);

// First sample
$s1_cpuacct = getCpuacctUsage2($containerId);
$s1_jiffies = getHostJiffies();
$cores = getCpuCores();
sleep(1);
$s2_cpuacct = getCpuacctUsage2($containerId);
$s2_jiffies = getHostJiffies();

$cpuPct = 0;
$dCpu = $s2_cpuacct - $s1_cpuacct;
$dJif = $s2_jiffies - $s1_jiffies;
if ($dCpu > 0 && $dJif > 0) {
    $cpuPct = round($dCpu / ($dJif * 10000000) * 100 * $cores, 1);
    $cpuPct = min($cpuPct, $cores * 100);
}

// Memory
$memInfo = @file_get_contents('/proc/meminfo');
$memTotal = 0; $memAvail = 0;
if ($memInfo) {
    if (preg_match('/^MemTotal:\s+(\d+)/m', $memInfo, $m)) $memTotal = (int)$m[1] * 1024;
    if (preg_match('/^MemAvailable:\s+(\d+)/m', $memInfo, $m)) $memAvail = (int)$m[1] * 1024;
}
$memPct = $memTotal > 0 ? round(($memTotal - $memAvail) / $memTotal * 100) : 0;

// Disk
$dt = @disk_total_space('/var/www/html');
$df = @disk_free_space('/var/www/html');
$diskPct = $dt > 0 ? round(($dt - $df) / $dt * 100) : 0;

// Network rate
$netDev = @file_get_contents('/proc/net/dev');
$nowRx = 0;
if ($netDev) {
    foreach (explode("\n", $netDev) as $l) {
        if (preg_match('/^\s*(eth\d+|enp\d+s\d+):\s*(\d+)/', $l, $m)) $nowRx += (int)$m[2];
    }
}
$prev = [];
if (file_exists($stateFile)) $prev = json_decode(file_get_contents($stateFile), true) ?: [];
$prevRx = $prev['rx'] ?? $nowRx;
$prevTs = $prev['ts'] ?? time();
$netRate = 0;
$dtNet = time() - $prevTs;
if ($dtNet > 0 && $nowRx >= $prevRx) $netRate = round(($nowRx - $prevRx) / $dtNet);
@file_put_contents($stateFile, json_encode(['ts'=>time(), 'rx'=>$nowRx]));

// FPM workers
$fpmActive = 0; $fpmTotal = 0;
$pids = @scandir('/proc');
if ($pids) {
    foreach ($pids as $pid) {
        if (!ctype_digit($pid)) continue;
        $cmd = @file_get_contents("/proc/$pid/cmdline");
        if ($cmd && str_contains(str_replace("\0", ' ', $cmd), 'php-fpm')) $fpmTotal++;
    }
}
$fpmActive = $fpmTotal > 1 ? $fpmTotal - 1 : 0; // one master, rest active
$fpmIdle = 0; // can't easily distinguish idle vs active from cmdline alone

// Docker stats (own container from cgroup)
$unlimitedThreshold = 1 << 50; // ~1PB
$dkrCpuPct = 0; $dkrMemPct = 0;
if ($containerId) {
    $memUsage = (float)trim(@file_get_contents("/host-cgroup/memory/docker/$containerId/memory.usage_in_bytes") ?: '0');
    $memLimitRaw = (float)trim(@file_get_contents("/host-cgroup/memory/docker/$containerId/memory.limit_in_bytes") ?: '0');
    if ($memLimitRaw >= $unlimitedThreshold) $memLimitRaw = $memTotal ?: PHP_INT_MAX;
    $dkrMemPct = $memLimitRaw > 0 ? round($memUsage / $memLimitRaw * 100) : 0;
    // Reuse cpuacct already sampled
    if ($dCpu > 0 && $dJif > 0) $dkrCpuPct = $cpuPct;
}

// Write history entry
$entry = json_encode([
    'ts' => time(),
    'cpu' => $cpuPct,
    'mem' => $memPct,
    'disk' => $diskPct,
    'net' => $netRate,
    'fpm_active' => $fpmActive,
    'fpm_idle' => $fpmIdle,
    'fpm_total' => $fpmTotal,
    'dkr_cpu' => $dkrCpuPct,
    'dkr_mem' => $dkrMemPct,
]) . "\n";

@file_put_contents("$logDir/history-" . date('Y-m-d') . ".log", $entry, FILE_APPEND | LOCK_EX);

// Cleanup files >30 days
foreach (glob("$logDir/history-*.log") as $f) {
    if (filemtime($f) && filemtime($f) < strtotime('-30 days')) @unlink($f);
}

echo "[" . date('Y-m-d H:i:s') . "] CPU:{$cpuPct}% MEM:{$memPct}% DISK:{$diskPct}% NET:{$netRate}B/s FPM:{$fpmTotal} DKR_CPU:{$dkrCpuPct}% DKR_MEM:{$dkrMemPct}%\n";
