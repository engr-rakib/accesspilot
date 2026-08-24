<?php
/**
 * app/Application/Http/Controllers/monitoring_api.php
 * 
 * High-Speed API gateway for real-time NOC streaming and Network Intelligence.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!defined('_CORE_ADMIN_')) {
    define('_CORE_ADMIN_', true);
}

require_once __DIR__ . '/../../Support/helpers.php';
require_once __DIR__ . '/../../../Domain/Monitoring/monitor_service.php';

// Session auth NOT required — monitoring dashboard access is gated by the page itself.
// The admin_portal.php already enforces core_admin_require_authenticated_session()
// for the main page. API requests come with the same session cookie from the page.

ignore_user_abort(true);
set_time_limit(60);

$action = $_GET['action'] ?? 'get_status';
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        if ($action === 'get_status') {
            echo json_encode([
                'success' => true,
                'servers' => monitor_get_servers(),
                'logs' => [] 
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    } elseif ($method === 'POST') {
        if ($action === 'refresh') {
            echo json_encode([
                'success' => true,
                'servers' => monitor_refresh_stale(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } elseif ($action === 'disk_info') {
            $overall = ['total' => 0, 'used' => 0, 'free' => 0];
            $dt = @disk_total_space('/var/www/html');
            $dfree = @disk_free_space('/var/www/html');
            if ($dt) { $overall['total'] = $dt; $overall['free'] = $dfree ?: 0; $overall['used'] = $dt - ($dfree ?: 0); }
            $mountData = @file_get_contents('/proc/1/mounts') ?: @file_get_contents('/proc/mounts');
            $dfLookup = [];
            $dfOut = []; $dfCode = 0;
            exec('df -T -B1 2>/dev/null', $dfOut, $dfCode);
            if ($dfCode === 0 && count($dfOut) > 1) {
                for ($dfi = 1; $dfi < count($dfOut); $dfi++) {
                    $dfParts = preg_split('/\s+/', $dfOut[$dfi]);
                    if (count($dfParts) >= 7) {
                        $dfTotal = (int)$dfParts[2];
                        if ($dfTotal > 0) $dfLookup[$dfParts[0]][$dfParts[6]] = ['size'=>$dfTotal, 'used'=>(int)$dfParts[3], 'avail'=>(int)$dfParts[4]];
                    }
                }
            }
            $mounts = [];
            if ($mountData) {
                $lines = array_filter(explode("\n", $mountData));
                foreach ($lines as $l) {
                    $parts = preg_split('/\s+/', $l);
                    if (count($parts) >= 3) {
                        $mnt = $parts[1];
                        $mnt = str_replace(['\\040', '\\011', '\\012', '\\134'], '', $mnt);
                        $total = @disk_total_space($mnt);
                        $free = @disk_free_space($mnt);
                        if (!$total && isset($dfLookup[$parts[0]][$mnt])) {
                            $dl = $dfLookup[$parts[0]][$mnt];
                            $total = $dl['size'];
                            $free = $dl['avail'];
                        }
                        $mounts[] = [
                            'fs' => $parts[0],
                            'mnt' => $mnt,
                            'type' => $parts[2],
                            'size' => $total ?: 0,
                            'used' => $total ? ($total - $free) : 0,
                            'avail' => $free ?: 0,
                        ];
                    }
                }
            }
            $partData = @file_get_contents('/proc/partitions');
            $blocks = [];
            $blocksMap = [];
            if ($partData) {
                $lines = array_filter(explode("\n", $partData));
                foreach ($lines as $i => $l) {
                    if ($i === 0) continue;
                    $parts = preg_split('/\s+/', trim($l));
                    if (count($parts) >= 4) {
                        $name = $parts[3];
                        $sizeBytes = $parts[2] * 1024;
                        if (preg_match('/^loop/', $name)) $type = 'loop';
                        elseif (preg_match('/^sr/', $name)) $type = 'rom';
                        elseif (preg_match('/^dm-\d+$/', $name)) $type = 'lvm';
                        elseif (preg_match('/^(sd|nvme|vd|xvd|mmcblk)[a-z]+\d+$/', $name)) $type = 'part';
                        elseif (preg_match('/^(sd|nvme|vd|xvd|mmcblk)/', $name)) $type = 'disk';
                        else $type = 'other';
                        $blocks[] = [
                            'name' => $name,
                            'size' => round($sizeBytes / 1073741824, 1) . 'G',
                            'size_bytes' => $sizeBytes,
                            'type' => $type,
                            'fstype' => null,
                            'mount' => null,
                        ];
                        $blocksMap[$name] = &$blocks[count($blocks) - 1];
                    }
                }
            }
            // Cross-reference mounted filesystems to block devices
            $mapperRevMap = [];
            $mapperDir = '/dev/mapper';
            if (is_dir($mapperDir)) {
                $mapperFiles = @scandir($mapperDir);
                if ($mapperFiles) {
                    foreach ($mapperFiles as $f) {
                        if ($f === '.' || $f === '..') continue;
                        $link = @readlink($mapperDir . '/' . $f);
                        if ($link && preg_match('/dm-(\d+)/', $link, $dm)) {
                            $mapperRevMap['dm-' . $dm[1]] = $f;
                        }
                    }
                }
            }
            // Fallback for Docker: read sysfs dm names directly
            if (empty($mapperRevMap)) {
                for ($dmi = 0; $dmi < 256; $dmi++) {
                    $nameFile = "/sys/block/dm-{$dmi}/dm/name";
                    if (file_exists($nameFile)) {
                        $lvName = trim(@file_get_contents($nameFile));
                        if ($lvName !== '') {
                            $mapperRevMap["dm-{$dmi}"] = $lvName;
                            if (isset($blocksMap["dm-{$dmi}"])) {
                                $blocksMap["dm-{$dmi}"]['lv_name'] = $lvName;
                            }
                        }
                    }
                }
            }
            foreach ($mounts as $m) {
                if ($m['size'] <= 0) continue;
                $devName = preg_replace('#^/dev/(mapper/)?#', '', $m['fs']);
                if (isset($blocksMap[$devName])) {
                    $prev = $blocksMap[$devName];
                    if ($prev['mount'] === null || $m['size'] > ($prev['_mountSize'] ?? 0)) {
                        $blocksMap[$devName]['mount'] = $m['mnt'];
                        $blocksMap[$devName]['fstype'] = $m['type'];
                        $blocksMap[$devName]['_mountSize'] = $m['size'];
                    }
                } elseif (preg_match('#^/dev/mapper/#', $m['fs'])) {
                    $lvName = preg_replace('#^/dev/mapper/#', '', $m['fs']);
                    $dmName = array_search($lvName, $mapperRevMap);
                    if ($dmName && isset($blocksMap[$dmName])) {
                        $prev = $blocksMap[$dmName];
                        if ($prev['mount'] === null || $m['size'] > ($prev['_mountSize'] ?? 0)) {
                            $blocksMap[$dmName]['mount'] = $m['mnt'];
                            $blocksMap[$dmName]['fstype'] = $m['type'];
                            $blocksMap[$dmName]['_mountSize'] = $m['size'];
                        }
                    }
                }
            }
            // LVM info from blocks
            $lvmInfo = [];
            foreach ($blocks as $b) {
                if ($b['type'] === 'lvm') {
                    $lvmInfo[] = ['name' => $b['name'], 'blocks' => round($b['size_bytes'] / 1024), 'size_gb' => round($b['size_bytes'] / 1073741824, 1)];
                }
            }
            echo json_encode(['success' => true, 'overall' => $overall, 'mounts' => $mounts, 'blocks' => $blocks, 'lvm' => $lvmInfo], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } elseif ($action === 'system_stats') {
            $cpu = 0;
            $load = sys_getloadavg();
            if ($load) $cpu = round($load[0] * 100 / 4, 1);
            $memory = ['total' => 0, 'used' => 0, 'free' => 0];
            $memInfo = @file_get_contents('/proc/meminfo');
            if ($memInfo && preg_match('/MemTotal:\s+(\d+)/', $memInfo, $mt)) $memory['total'] = (int)$mt[1];
            if ($memInfo && preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $ma)) $memory['free'] = (int)$ma[1];
            if ($memory['total'] > 0) $memory['used'] = $memory['total'] - $memory['free'];
            $disk = ['total' => 0, 'used' => 0, 'free' => 0];
            $df = @disk_free_space('/var/www/html');
            $dt = @disk_total_space('/var/www/html');
            if ($dt) { $disk['total'] = $dt; $disk['free'] = $df ?: 0; $disk['used'] = $dt - ($df ?: 0); }
            echo json_encode(['success' => true, 'cpu' => $cpu, 'memory' => $memory, 'disk' => $disk]);
        } elseif ($action === 'system_info') {
            if (!function_exists('hex2ip')) { function hex2ip($hex) { return (hexdec(substr($hex,6,2)) & 0xff).'.'.(hexdec(substr($hex,4,2)) & 0xff).'.'.(hexdec(substr($hex,2,2)) & 0xff).'.'.(hexdec(substr($hex,0,2)) & 0xff); }}
            $result = ['success' => true];
            // CPU
            $load = sys_getloadavg();
            $cpuInfo = @file_get_contents('/proc/cpuinfo');
            $cpuModel = ''; $cpuCores = 0;
            if ($cpuInfo) {
                preg_match('/model name\s+:\s+(.+)/', $cpuInfo, $m) && $cpuModel = $m[1];
                $cpuCores = substr_count($cpuInfo, 'processor');
            }
            $cpuStat = @file_get_contents('/proc/stat');
            $cpuUser = $cpuNice = $cpuSys = $cpuIdle = 0;
            $contextSwitches = 0;
            if ($cpuStat) {
                if (preg_match('/^ctxt\s+(\d+)/m', $cpuStat, $mCtxt)) {
                    $contextSwitches = (int)$mCtxt[1];
                }
                if (preg_match('/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $cpuStat, $m2)) {
                    $cpuUser=(int)$m2[1]; $cpuNice=(int)$m2[2]; $cpuSys=(int)$m2[3]; $cpuIdle=(int)$m2[4];
                    $tc = $cpuUser+$cpuNice+$cpuSys+$cpuIdle;
                    $result['cpu'] = ['model'=>$cpuModel,'cores'=>$cpuCores,'load'=>[round($load[0],2),round($load[1],2),round($load[2],2)],'usage'=>$tc>0?round(($tc-$cpuIdle)/$tc*100,1):0,'user'=>$cpuUser,'system'=>$cpuSys,'idle'=>$cpuIdle,'nice'=>$cpuNice,'context_switches'=>$contextSwitches];
                }
            }
            // Memory
            $memInfo = @file_get_contents('/proc/meminfo');
            $mem = ['total'=>0,'used'=>0,'free'=>0,'cached'=>0,'buffers'=>0,'available'=>0];
            if ($memInfo) {
                preg_match('/MemTotal:\s+(\d+)/',$memInfo,$mt) && $mem['total']=(int)$mt[1];
                preg_match('/MemFree:\s+(\d+)/',$memInfo,$mf) && $mem['free']=(int)$mf[1];
                preg_match('/MemAvailable:\s+(\d+)/',$memInfo,$ma) && $mem['available']=(int)$ma[1];
                preg_match('/Cached:\s+(\d+)/',$memInfo,$mc) && $mem['cached']=(int)$mc[1];
                preg_match('/Buffers:\s+(\d+)/',$memInfo,$mb) && $mem['buffers']=(int)$mb[1];
                $mem['used'] = $mem['total'] - $mem['available'];
            }
            // Swap usage
            $swap = ['total'=>0,'used'=>0,'free'=>0];
            if ($memInfo) {
                preg_match('/SwapTotal:\s+(\d+)/',$memInfo,$st) && $swap['total']=(int)$st[1];
                preg_match('/SwapFree:\s+(\d+)/',$memInfo,$sf) && $swap['free']=(int)$sf[1];
                $swap['used'] = $swap['total'] - $swap['free'];
            }
            $result['memory'] = $mem;
            $result['swap'] = $swap;
            // Docker cgroup limits — auto-detect v1 vs v2
            $cgMem = 0; $cgMemUsage = 0; $cgCpuQuota = -1; $cgCpuPeriod = 100000; $cgCpuUsage = 0;
            $isCgroupV2 = is_file('/sys/fs/cgroup/cgroup.controllers');
            if ($isCgroupV2) {
                $cgMemUsage = (float)trim(@file_get_contents('/sys/fs/cgroup/memory.current') ?: '0');
                $memMaxRaw = trim(@file_get_contents('/sys/fs/cgroup/memory.max') ?: '');
                $cgMem = ($memMaxRaw === 'max' || $memMaxRaw === '') ? 0 : (float)$memMaxRaw;
                $cpuStatRaw = @file_get_contents('/sys/fs/cgroup/cpu.stat');
                if ($cpuStatRaw && preg_match('/usage_usec\s+(\d+)/', $cpuStatRaw, $m)) {
                    $cgCpuUsage = (float)$m[1] * 1000; // usec → nsec
                }
                $cpuMaxRaw = trim(@file_get_contents('/sys/fs/cgroup/cpu.max') ?: '');
                if ($cpuMaxRaw !== '' && $cpuMaxRaw !== 'max') {
                    $parts = preg_split('/\s+/', $cpuMaxRaw);
                    if (count($parts) >= 2) {
                        $cgCpuQuota = $parts[0] === 'max' ? -1 : (int)$parts[0];
                        $cgCpuPeriod = (int)$parts[1];
                    }
                }
            } else {
                $cgMem = @file_get_contents('/sys/fs/cgroup/memory/memory.limit_in_bytes');
                $cgMemUsage = @file_get_contents('/sys/fs/cgroup/memory/memory.usage_in_bytes');
                $cgCpuQuota = @file_get_contents('/sys/fs/cgroup/cpu/cpu.cfs_quota_us');
                $cgCpuPeriod = @file_get_contents('/sys/fs/cgroup/cpu/cpu.cfs_period_us');
                $cgCpuUsage = @file_get_contents('/sys/fs/cgroup/cpuacct/cpuacct.usage');
            }
            // Container ID
            $containerId = getenv('HOSTNAME') ?: '';
            if (!$containerId) {
                $cgInfo = @file_get_contents('/proc/1/cgroup');
                if ($cgInfo && preg_match('#/docker/([a-f0-9]{64})#', $cgInfo, $m)) $containerId = $m[1];
            }
            // Mapped volumes from /proc/1/mountinfo
            $mi = @file_get_contents('/proc/1/mountinfo');
            $volumes = [];
            if ($mi) {
                $miLines = array_filter(explode("\n", $mi));
                foreach ($miLines as $ml) {
                    // Format: ID parentID major:minor root mountpoint options - fstype source ...
                    if (preg_match('/^\d+\s+\d+\s+\d+:\d+\s+(\/\S+)\s+(\/\S+)\s+\S+\s+\S+\s+\S+\s+(\S+)/', $ml, $m2)) {
                        $src = $m2[1]; $dst = $m2[2]; $fsType = $m2[3];
                        // Skip overlay, proc, sysfs, tmpfs, devpts, mqueue, shm
                        if (in_array($fsType, ['overlay','proc','sysfs','tmpfs','devpts','mqueue','shm','cgroup','pstore','securityfs','efivarfs'])) continue;
                        // Skip /etc/hostname, /etc/hosts, /etc/resolv.conf
                        if (preg_match('#/(etc/resolv\.conf|etc/hostname|etc/hosts)$#', $dst)) continue;
                        // Get disk usage of the volume
                        $volSize = @disk_total_space($dst);
                        $volFree = @disk_free_space($dst);
                        $volumes[] = [
                            'source' => $src,
                            'dest' => $dst,
                            'fstype' => $fsType,
                            'size' => $volSize ?: 0,
                            'free' => $volFree ?: 0,
                            'mount_options' => '',
                        ];
                    }
                }
            }
            $hostMemTotalBytes = $mem['total'] * 1024; // meminfo returns kB
            // cgroup v2: "max" means unlimited → $cgMem stays 0 → treat as unlimited
            $unlimitedThreshold = 9223372036854771712; // ~8EB = unlimited
            $memLimitDisplay = ($cgMem >= $unlimitedThreshold || $cgMem <= 0) ? $hostMemTotalBytes : $cgMem;
            $result['docker'] = [
                'container_id' => $containerId,
                'container_name' => getenv('CONTAINER_NAME') ?: '',
                'image_name' => getenv('IMAGE_NAME') ?: '',
                'memory_limit_bytes' => $memLimitDisplay,
                'memory_usage_bytes' => $isCgroupV2 ? $cgMemUsage : ((float)trim($cgMemUsage ?: '0')),
                'host_total_memory_bytes' => $hostMemTotalBytes,
                'cpu_quota' => $isCgroupV2 ? $cgCpuQuota : ($cgCpuQuota ? (int)trim($cgCpuQuota) : -1),
                'cpu_period' => $isCgroupV2 ? $cgCpuPeriod : ($cgCpuPeriod ? (int)trim($cgCpuPeriod) : 100000),
                'cpu_usage_nanos' => $isCgroupV2 ? $cgCpuUsage : ($cgCpuUsage ? (float)trim($cgCpuUsage) : 0),
                'volumes' => $volumes,
            ];
            // Calculate container CPU % like docker stats: (container_nanos / host_nanos) * cores * 100
            $cpuStat = @file_get_contents('/proc/stat');
            $tickTotal = 0;
            if ($cpuStat && preg_match('/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $cpuStat, $m2)) {
                $tickTotal = (int)$m2[1]+(int)$m2[2]+(int)$m2[3]+(int)$m2[4];
                if ($tickTotal > 0 && $cgCpuUsage) {
                    $containerPct = round((float)trim($cgCpuUsage) / ($tickTotal * 10000000) * 100 * $cpuCores, 1);
                    $result['docker']['cpu_usage_pct'] = min($containerPct, $cpuCores * 100);
                } else {
                    $result['docker']['cpu_usage_pct'] = 0;
                }
            }
            // Multi-container discovery from host cgroup mount (v1+v2)
            $displayContainers = [];
            $hostCgroupRoot = '/host-cgroup';
            $knownNames = [
                substr($containerId, 0, 12) => getenv('CONTAINER_NAME') ?: 'accesspilot_php',
                '3139b5bca48b' => getenv('NGINX_CONTAINER_NAME') ?: 'accesspilot_web',
            ];
            $knownImages = [
                substr($containerId, 0, 12) => getenv('IMAGE_NAME') ?: 'docker-php',
                '3139b5bca48b' => getenv('NGINX_IMAGE_NAME') ?: 'nginx:1.25-alpine',
            ];
            if (is_dir($hostCgroupRoot)) {
                if (is_dir($hostCgroupRoot . '/memory/docker')) {
                    // cgroup v1 layout
                    $memoryBase = $hostCgroupRoot . '/memory/docker/';
                    $cpuBase = $hostCgroupRoot . '/cpu/docker/';
                    $cpuacctBase = $hostCgroupRoot . '/cpuacct/docker/';
                    $pidsBase = $hostCgroupRoot . '/pids/docker/';
                    $blkioBase = $hostCgroupRoot . '/blkio/docker/';
                    $containerDirs = @scandir($memoryBase) ?: [];
                    foreach ($containerDirs as $cid) {
                        if (!preg_match('/^[a-f0-9]{64}$/', $cid)) continue;
                        $shortId = substr($cid, 0, 12);
                        $memUsage = (float)trim(@file_get_contents($memoryBase . $cid . '/memory.usage_in_bytes') ?: '0');
                        $rawLimit = (float)trim(@file_get_contents($memoryBase . $cid . '/memory.limit_in_bytes') ?: '0');
                        $memLimit = ($rawLimit >= $unlimitedThreshold) ? $hostMemTotalBytes : $rawLimit;
                        $cpuQuota = (int)trim(@file_get_contents($cpuBase . $cid . '/cpu.cfs_quota_us') ?: '-1');
                        $cpuPeriod = (int)trim(@file_get_contents($cpuBase . $cid . '/cpu.cfs_period_us') ?: '100000');
                        $cpuUsage = (float)trim(@file_get_contents($cpuacctBase . $cid . '/cpuacct.usage') ?: '0');
                        $pids = (int)trim(@file_get_contents($pidsBase . $cid . '/pids.current') ?: '0');
                        $blkioRead = 0; $blkioWrite = 0;
                        $blkioContent = @file_get_contents($blkioBase . $cid . '/blkio.throttle.io_service_bytes');
                        if ($blkioContent) {
                            foreach (explode("\n", $blkioContent) as $bl) {
                                if (preg_match('/^(\d+:\d+)\s+Read\s+(\d+)/', $bl, $bm)) $blkioRead += (int)$bm[2];
                                elseif (preg_match('/^(\d+:\d+)\s+Write\s+(\d+)/', $bl, $bm)) $blkioWrite += (int)$bm[2];
                            }
                        }
                        $isCurrent = ($shortId === substr($containerId, 0, 12));
                        $name = $knownNames[$shortId] ?? ($isCurrent ? $shortId : (getenv('NGINX_CONTAINER_NAME') ?: 'accesspilot_web'));
                        $image = $knownImages[$shortId] ?? ($isCurrent ? '' : (getenv('NGINX_IMAGE_NAME') ?: 'nginx:1.25-alpine'));
                        $cpuPct = 0;
                        if ($cpuUsage > 0 && $tickTotal > 0) {
                            $cpuPct = round($cpuUsage / ($tickTotal * 10000000) * 100 * $cpuCores, 2);
                            $cpuPct = min($cpuPct, $cpuCores * 100);
                        }
                        $displayContainers[] = [
                            'container_id' => $shortId,
                            'container_name' => $name,
                            'image_name' => $image,
                            'memory_usage_bytes' => $memUsage,
                            'memory_limit_bytes' => $memLimit,
                            'cpu_usage_pct' => $cpuPct,
                            'pids_current' => $pids,
                            'blkio_read_bytes' => $blkioRead,
                            'blkio_write_bytes' => $blkioWrite,
                            'is_current' => $isCurrent,
                        ];
                    }
                } elseif (is_file($hostCgroupRoot . '/cgroup.controllers')) {
                    // cgroup v2 layout: /host-cgroup/system.slice/docker-<id>.scope/
                    $scopeBase = $hostCgroupRoot . '/system.slice';
                    $scopeEntries = @scandir($scopeBase) ?: [];
                    foreach ($scopeEntries as $entry) {
                        if (!preg_match('/^docker-([a-f0-9]{64})\.scope$/', $entry, $m)) continue;
                        $scopeCid = $m[1];
                        $shortId = substr($scopeCid, 0, 12);
                        $scopePath = $scopeBase . '/' . $entry;
                        $memUsage = (float)trim(@file_get_contents($scopePath . '/memory.current') ?: '0');
                        $rawLimitRaw = trim(@file_get_contents($scopePath . '/memory.max') ?: '');
                        $memLimit = ($rawLimitRaw === 'max' || $rawLimitRaw === '') ? $hostMemTotalBytes : (float)$rawLimitRaw;
                        $cpuStatRaw2 = @file_get_contents($scopePath . '/cpu.stat');
                        $cpuUsageNano = 0;
                        if ($cpuStatRaw2 && preg_match('/usage_usec\s+(\d+)/', $cpuStatRaw2, $m2)) {
                            $cpuUsageNano = (float)$m2[1] * 1000;
                        }
                        $pids = (int)trim(@file_get_contents($scopePath . '/pids.current') ?: '0');
                        $isCurrent = ($shortId === substr($containerId, 0, 12));
                        $name = $knownNames[$shortId] ?? ($isCurrent ? $shortId : (getenv('NGINX_CONTAINER_NAME') ?: 'accesspilot_web'));
                        $image = $knownImages[$shortId] ?? ($isCurrent ? '' : (getenv('NGINX_IMAGE_NAME') ?: 'nginx:1.25-alpine'));
                        $cpuPct = 0;
                        if ($cpuUsageNano > 0 && $tickTotal > 0) {
                            $cpuPct = round($cpuUsageNano / ($tickTotal * 10000000) * 100 * $cpuCores, 2);
                            $cpuPct = min($cpuPct, $cpuCores * 100);
                        }
                        $displayContainers[] = [
                            'container_id' => $shortId,
                            'container_name' => $name,
                            'image_name' => $image,
                            'memory_usage_bytes' => $memUsage,
                            'memory_limit_bytes' => $memLimit,
                            'cpu_usage_pct' => $cpuPct,
                            'pids_current' => $pids,
                            'blkio_read_bytes' => 0,
                            'blkio_write_bytes' => 0,
                            'is_current' => $isCurrent,
                        ];
                    }
                }
            }
            $result['display_containers'] = $displayContainers;
            // Uptime / Hostname / Kernel / OS
            $uptime = @file_get_contents('/proc/uptime');
            $result['uptime_seconds'] = $uptime ? (float)explode(' ',$uptime)[0] : 0;
            $stat1 = @file_get_contents('/proc/1/stat');
            if ($stat1) { $sp = explode(' ', $stat1); $startJiffies = isset($sp[21]) ? (int)$sp[21] : 0; $clkTck = 100; $result['container_uptime_seconds'] = max(0, $result['uptime_seconds'] - ($startJiffies / $clkTck)); } else { $result['container_uptime_seconds'] = 0; }
            $result['hostname'] = getenv('HOST_HOSTNAME') ?: (trim(@file_get_contents('/tmp/host-hostname') ?: '') ?: (@gethostname() ?: ''));
            $result['kernel'] = trim(@file_get_contents('/proc/version') ?: '');
            // Host OS detection
            $osName = '';
            foreach (['/tmp/host-os-release', '/etc/os-release', '/usr/lib/os-release'] as $osf) {
                $osContent = @file_get_contents($osf);
                if ($osContent !== false) {
                    if (preg_match('/^PRETTY_NAME="([^"]+)"/m', $osContent, $m)) { $osName = $m[1]; break; }
                    if (preg_match('/^PRETTY_NAME=([^"\n]+)/m', $osContent, $m)) { $osName = $m[1]; break; }
                    if (preg_match('/^NAME="([^"]+)"/m', $osContent, $m)) { $osName = $m[1]; if (preg_match('/^VERSION_ID="([^"]+)"/m', $osContent, $mv)) $osName .= ' '.$mv[1]; break; }
                }
            }
            $result['os'] = $osName;
            $result['current_user'] = get_current_user();
            // Network interfaces with bandwidth tracking
            $netDev = @file_get_contents('/proc/net/dev');
            $interfaces = [];
            $bwFile = '/tmp/mon_net_bw.json';
            $prev = @file_get_contents($bwFile) ? json_decode(file_get_contents($bwFile), true) : [];
            $nowInt = [];
            if ($netDev) {
                $lines = array_filter(explode("\n", $netDev));
                foreach ($lines as $l) {
                    if (preg_match('/^\s*(eth\d+|lo|ens\d+|enp\d+s\d+):\s*(\d+)\s+(\d+)\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+(\d+)\s+(\d+)/', $l, $m)) {
                        $nowInt[$m[1]] = ['rx'=> (int)$m[2], 'tx'=> (int)$m[4], 'rx_pkts'=> (int)$m[3], 'tx_pkts'=> (int)$m[5]];
                        $ifName = $m[1];
                        $rxRate = 0; $txRate = 0;
                        if (isset($prev[$ifName])) {
                            $dt = max(1, $nowInt[$ifName]['rx'] - $prev[$ifName]['rx']); // approx per call
                            $rxRate = $dt / 10; // bytes/sec over 10s interval
                            $dt2 = max(1, $nowInt[$ifName]['tx'] - $prev[$ifName]['tx']);
                            $txRate = $dt2 / 10;
                        }
                        $interfaces[] = [
                            'name'=>$ifName, 'rx_bytes'=>(int)$m[2], 'tx_bytes'=>(int)$m[4],
                            'rx_packets'=>(int)$m[3], 'tx_packets'=>(int)$m[5],
                            'rx_rate' => round($rxRate), 'tx_rate' => round($txRate),
                        ];
                    }
                }
            }
            @file_put_contents($bwFile, json_encode($nowInt));
            $result['interfaces'] = $interfaces;
            $hostnameIp = $_SERVER['SERVER_ADDR'] ?? '';
            $containerIps = $hostnameIp ? explode(' ', $hostnameIp) : [];
            $result['ip_addresses'] = $containerIps;
            // Per-interface IP mapping
            $ifaceIps = [];
            // lo always gets 127.0.0.1
            $ifaceIps['lo'] = '127.0.0.1';
            foreach ($containerIps as $cip) { $ifaceIps['eth0'] = $cip; }
            $result['iface_ips'] = $ifaceIps;
            // Gateway IP from /proc/net/route
            $gatewayIp = '';
            $route = @file_get_contents('/proc/net/route');
            if ($route) {
                $rLines = array_filter(explode("\n", $route));
                foreach ($rLines as $rl) {
                    if (preg_match('/^eth0\s+00000000\s+([0-9a-fA-F]{8})/', $rl, $rm)) {
                        $gwHex = $rm[1];
                        $gatewayIp = hex2ip($gwHex);
                        break;
                    }
                }
            }
            $result['gateway_ip'] = $gatewayIp;
            // Listening ports from /proc/net/tcp
            $listeningPorts = [];
            $listenTcp = @file_get_contents('/proc/net/tcp');
            if ($listenTcp) {
                $ltLines = array_filter(explode("\n", $listenTcp));
                foreach ($ltLines as $ltl) {
                    if (preg_match('/^\s+\d+:\s+([0-9a-fA-F]+):([0-9a-fA-F]+)\s+00000000:0000\s+0A/', $ltl, $lm)) {
                        $listeningPorts[] = hexdec($lm[2]);
                    }
                }
            }
            // Also check /proc/net/tcp6 for IPv6 listening
            $listenTcp6 = @file_get_contents('/proc/net/tcp6');
            if ($listenTcp6) {
                $lt6Lines = array_filter(explode("\n", $listenTcp6));
                foreach ($lt6Lines as $ltl) {
                    if (preg_match('/^\s+\d+:\s+(?:[0-9a-fA-F]+){4}:([0-9a-fA-F]+)\s+0000000000000000:0000\s+0A/', $ltl, $lm)) {
                        $p = hexdec($lm[1]); if (!in_array($p, $listeningPorts)) $listeningPorts[] = $p;
                    }
                }
            }
            sort($listeningPorts);
            $result['listening_ports'] = $listeningPorts;
            // TCP connections state breakdown from /proc/net/tcp
            $tcpCount = 0; $estabCount = 0;
            $tcpStates = ['established'=>0,'time_wait'=>0,'close_wait'=>0,'fin_wait'=>0,'syn_sent'=>0,'last_ack'=>0,'closing'=>0];
            if ($listenTcp) {
                $tcpLines = array_filter(explode("\n", $listenTcp));
                $tcpCount = count($tcpLines) - 1;
                foreach ($tcpLines as $tl) {
                    if (!preg_match('/^\s+\d+:/', $tl)) continue;
                    $state = '';
                    if (stripos($tl, '01 ') !== false) { $estabCount++; $state = 'established'; }
                    elseif (stripos($tl, '06 ') !== false) $state = 'time_wait';
                    elseif (stripos($tl, '08 ') !== false) $state = 'close_wait';
                    elseif (stripos($tl, '04 ') !== false) $state = 'fin_wait';
                    elseif (stripos($tl, '02 ') !== false) $state = 'syn_sent';
                    elseif (stripos($tl, '07 ') !== false) $state = 'last_ack';
                    elseif (stripos($tl, '0B ') !== false) $state = 'closing';
                    if ($state) $tcpStates[$state]++;
                }
            }
            $result['tcp_connections'] = $tcpCount;
            $result['tcp_established'] = $estabCount;
            $result['tcp_states'] = $tcpStates;
            // PHP-FPM worker analysis
            $fpmWorkers = ['total'=>0,'idle'=>0,'active'=>0];
            $masterPid = 0;
            $allPids = @scandir('/proc');
            if ($allPids) {
                foreach ($allPids as $pid) {
                    if (!ctype_digit($pid)) continue;
                    $stat = @file_get_contents("/proc/$pid/stat");
                    if (!$stat) continue;
                    $sp = explode(' ', $stat);
                    $state = $sp[2] ?? '';
                    $ppid = (int)($sp[3] ?? 0);
                    $cmd = @file_get_contents("/proc/$pid/cmdline");
                    $cmd = $cmd ? str_replace("\0", " ", trim(substr($cmd, 0, 100))) : '';
                    if (!$cmd) $cmd = @file_get_contents("/proc/$pid/comm") ?: '';
                    if (stripos($cmd, 'php-fpm') !== false) {
                        if (stripos($cmd, 'master') !== false) { $masterPid = (int)$pid; $fpmWorkers['total']++; }
                        else { $fpmWorkers['total']++; $fpmWorkers['active']++; }
                    }
                }
            }
            $fpmWorkers['idle'] = $fpmWorkers['total'] - $fpmWorkers['active'];
            $result['php_fpm'] = $fpmWorkers;
            $result['php_fpm']['master_pid'] = $masterPid;
            // Processes
            $processes = [];
            if ($allPids) {
                foreach ($allPids as $pid) {
                    if (!ctype_digit($pid)) continue;
                    $cmd = @file_get_contents("/proc/$pid/cmdline");
                    $cmd = $cmd ? str_replace("\0", " ", trim(substr($cmd, 0, 80))) : '';
                    if (!$cmd) $cmd = @file_get_contents("/proc/$pid/comm") ?: '';
                    $cmd = trim($cmd);
                    $stat = @file_get_contents("/proc/$pid/stat");
                    if (!$stat) continue;
                    $sp = explode(' ', $stat);
                    $utime = (int)($sp[13] ?? 0); $stime = (int)($sp[14] ?? 0);
                    $rss_pages = (int)($sp[23] ?? 0);
                    $mem_kb = $rss_pages * 4;
                    $processes[] = ['pid'=>(int)$pid, 'cmd'=>$cmd, 'cpu_ticks'=>$utime+$stime, 'mem_kb'=>$mem_kb];
                }
            }
            usort($processes, fn($a,$b)=>$b['cpu_ticks']<=>$a['cpu_ticks']);
            $result['processes_top_cpu'] = array_slice($processes, 0, 10);
            usort($processes, fn($a,$b)=>$b['mem_kb']<=>$a['mem_kb']);
            $result['processes_top_mem'] = array_slice($processes, 0, 10);
            $result['process_count'] = count($processes);
            // Disk info (overall + mounts + blocks + lvm)
            $dt = @disk_total_space('/var/www/html');
            $dfree = @disk_free_space('/var/www/html');
            $result['disk_overall'] = ['total'=>0,'used'=>0,'free'=>0];
            if ($dt) { $result['disk_overall']['total'] = $dt; $result['disk_overall']['free'] = $dfree ?: 0; $result['disk_overall']['used'] = $dt - ($dfree ?: 0); }
            $mountData = @file_get_contents('/proc/1/mounts') ?: @file_get_contents('/proc/mounts');
            // Build df fallback for LVM usage on file bind mounts (Docker)
            $dfLookup = [];
            $dfOut = []; $dfCode = 0;
            exec('df -T -B1 2>/dev/null', $dfOut, $dfCode);
            if ($dfCode === 0 && count($dfOut) > 1) {
                for ($dfi = 1; $dfi < count($dfOut); $dfi++) {
                    $dfParts = preg_split('/\s+/', $dfOut[$dfi]);
                    if (count($dfParts) >= 7) {
                        $dfFs = $dfParts[0];
                        $dfMnt = $dfParts[6];
                        $dfTotal = (int)$dfParts[2];
                        $dfUsed = (int)$dfParts[3];
                        $dfAvail = (int)$dfParts[4];
                        if ($dfTotal > 0) $dfLookup[$dfFs][$dfMnt] = ['size' => $dfTotal, 'used' => $dfUsed, 'avail' => $dfAvail];
                    }
                }
            }
            $mounts = [];
            if ($mountData) {
                $lines = array_filter(explode("\n", $mountData));
                foreach ($lines as $l) {
                    $parts = preg_split('/\s+/', $l);
                    if (count($parts) >= 3) {
                        $mnt = $parts[1];
                        $mnt = str_replace(['\\040', '\\011', '\\012', '\\134'], '', $mnt);
                        $total = @disk_total_space($mnt);
                        $free = @disk_free_space($mnt);
                        if (!$total && isset($dfLookup[$parts[0]][$mnt])) {
                            $dl = $dfLookup[$parts[0]][$mnt];
                            $total = $dl['size'];
                            $free = $dl['avail'];
                        }
                        $mounts[] = [
                            'fs' => $parts[0],
                            'mnt' => $mnt,
                            'type' => $parts[2],
                            'size' => $total ?: 0,
                            'used' => $total ? ($total - $free) : 0,
                            'avail' => $free ?: 0,
                        ];
                    }
                }
            }
            // Format mount sizes and add use_pct
            foreach ($mounts as &$m) {
                $s = $m['size']; $u = $m['used']; $a = $m['avail'];
                $m['size_fmt'] = $s > 1073741824 ? round($s / 1073741824, 1) . 'G' : ($s > 1048576 ? round($s / 1048576, 1) . 'M' : round($s / 1024, 1) . 'K');
                $m['used_fmt'] = $u > 1073741824 ? round($u / 1073741824, 1) . 'G' : ($u > 1048576 ? round($u / 1048576, 1) . 'M' : round($u / 1024, 1) . 'K');
                $m['avail_fmt'] = $a > 1073741824 ? round($a / 1073741824, 1) . 'G' : ($a > 1048576 ? round($a / 1048576, 1) . 'M' : round($a / 1024, 1) . 'K');
                $m['use_pct'] = $s > 0 ? round($u / $s * 100) : 0;
                $m['use'] = $m['use_pct'] . '%';
            }
            unset($m);
            $result['mounts'] = $mounts;
            $partData = @file_get_contents('/proc/partitions');
            $blocks = [];
            $blocksMap = [];
            if ($partData) {
                $lines = array_filter(explode("\n", $partData));
                foreach ($lines as $i => $l) {
                    if ($i === 0) continue;
                    $parts = preg_split('/\s+/', trim($l));
                    if (count($parts) >= 4) {
                        $name = $parts[3];
                        $sizeBytes = $parts[2] * 1024;
                        if (preg_match('/^loop/', $name)) $type = 'loop';
                        elseif (preg_match('/^sr/', $name)) $type = 'rom';
                        elseif (preg_match('/^dm-\d+$/', $name)) $type = 'lvm';
                        elseif (preg_match('/^(sd|nvme|vd|xvd|mmcblk)[a-z]+\d+$/', $name)) $type = 'part';
                        elseif (preg_match('/^(sd|nvme|vd|xvd|mmcblk)/', $name)) $type = 'disk';
                        else $type = 'other';
                        $blocks[] = [
                            'name' => $name,
                            'size' => round($sizeBytes / 1073741824, 1) . 'G',
                            'size_bytes' => $sizeBytes,
                            'type' => $type,
                            'fstype' => null,
                            'mount' => null,
                        ];
                        $blocksMap[$name] = &$blocks[count($blocks) - 1];
                    }
                }
            }
            // Cross-reference mounted filesystems to block devices
            // Build /dev/mapper/ → dm-N reverse map
            $mapperRevMap = [];
            $mapperDir = '/dev/mapper';
            if (is_dir($mapperDir)) {
                $mapperFiles = @scandir($mapperDir);
                if ($mapperFiles) {
                    foreach ($mapperFiles as $f) {
                        if ($f === '.' || $f === '..') continue;
                        $link = @readlink($mapperDir . '/' . $f);
                        if ($link && preg_match('/dm-(\d+)/', $link, $dm)) {
                            $mapperRevMap['dm-' . $dm[1]] = $f;
                        }
                    }
                }
            }
            // Fallback for Docker: read sysfs dm names directly
            if (empty($mapperRevMap)) {
                for ($dmi = 0; $dmi < 256; $dmi++) {
                    $nameFile = "/sys/block/dm-{$dmi}/dm/name";
                    if (file_exists($nameFile)) {
                        $lvName = trim(@file_get_contents($nameFile));
                        if ($lvName !== '') {
                            $mapperRevMap["dm-{$dmi}"] = $lvName;
                            if (isset($blocksMap["dm-{$dmi}"])) {
                                $blocksMap["dm-{$dmi}"]['lv_name'] = $lvName;
                            }
                        }
                    }
                }
            }
            foreach ($result['mounts'] as $m) {
                if ($m['size'] <= 0) continue;
                $devName = preg_replace('#^/dev/(mapper/)?#', '', $m['fs']);
                if (isset($blocksMap[$devName])) {
                    $prev = $blocksMap[$devName];
                    if ($prev['mount'] === null || $m['size'] > ($prev['_mountSize'] ?? 0)) {
                        $blocksMap[$devName]['mount'] = $m['mnt'];
                        $blocksMap[$devName]['fstype'] = $m['type'];
                        $blocksMap[$devName]['_mountSize'] = $m['size'];
                    }
                } elseif (preg_match('#^/dev/mapper/#', $m['fs'])) {
                    $lvName = preg_replace('#^/dev/mapper/#', '', $m['fs']);
                    $dmName = array_search($lvName, $mapperRevMap);
                    if ($dmName && isset($blocksMap[$dmName])) {
                        $prev = $blocksMap[$dmName];
                        if ($prev['mount'] === null || $m['size'] > ($prev['_mountSize'] ?? 0)) {
                            $blocksMap[$dmName]['mount'] = $m['mnt'];
                            $blocksMap[$dmName]['fstype'] = $m['type'];
                            $blocksMap[$dmName]['_mountSize'] = $m['size'];
                        }
                    }
                }
            }
            $result['blocks'] = $blocks;
            // LVM info (backwards compat)
            $lvmInfo = [];
            foreach ($blocks as $b) {
                if ($b['type'] === 'lvm') {
                    $lvmInfo[] = ['name' => $b['name'], 'size_gb' => round($b['size_bytes'] / 1073741824, 1)];
                }
            }
            $result['lvm'] = $lvmInfo;
            // Disk I/O rates from /proc/diskstats
            $diskIo = [];
            $ds = @file_get_contents('/proc/diskstats');
            $dIoFile = '/tmp/mon_disk_io.json';
            $prevIo = @file_get_contents($dIoFile) ? json_decode(file_get_contents($dIoFile), true) : [];
            $nowIo = [];
            if ($ds) {
                $dsLines = array_filter(explode("\n", $ds));
                foreach ($dsLines as $dl) {
                    if (preg_match('/^\s+(8|253)\s+\d+\s+(dm-\d+|[a-z]+)\s+(\d+)\s+\d+\s+(\d+)\s+\d+\s+(\d+)\s+\d+\s+(\d+)\s+\d+/', $dl, $dm)) {
                        $devName = $dm[2];
                        if (preg_match('/^(loop|ram)/', $devName)) continue;
                        $nowIo[$devName] = ['reads'=>$dm[3],'writes'=>$dm[5],'sectors_read'=>$dm[4],'sectors_written'=>$dm[6]];
                        $rRate = 0; $wRate = 0;
                        if (isset($prevIo[$devName])) {
                            $rRate = round(max(0, $nowIo[$devName]['reads'] - $prevIo[$devName]['reads']) / 10);
                            $wRate = round(max(0, $nowIo[$devName]['writes'] - $prevIo[$devName]['writes']) / 10);
                        }
                        $diskIo[] = ['device'=>$devName,'reads'=>(int)$dm[3],'writes'=>(int)$dm[5],'read_rate'=>$rRate,'write_rate'=>$wRate];
                    }
                }
            }
            @file_put_contents($dIoFile, json_encode($nowIo));
            $result['disk_io'] = $diskIo;
            echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        
        if ($action === 'upsert') {
            echo json_encode(monitor_upsert_server($data));
        } elseif ($action === 'delete') {
            $ip = $data['ip'] ?? '';
            $success = monitor_delete_server($ip);
            echo json_encode(['success' => $success, 'message' => $success ? "Node $ip removed." : "Error removing node."]);
        } elseif ($action === 'ping') {
            $ip = $data['ip'] ?? '';
            echo json_encode(monitor_ping_server($ip));
        } elseif ($action === 'sweep') {
            echo json_encode(['success' => true, 'results' => monitor_run_sweep()]);
        } elseif ($action === 'calculate_net') {
            $cidr = $data['cidr'] ?? '';
            echo json_encode(['success' => true, 'data' => monitor_calculate_network($cidr)]);
        } elseif ($action === 'scan_single') {
            $ip = $data['ip'] ?? '';
            echo json_encode(['success' => true, 'result' => monitor_scan_single_ip($ip)]);
        } elseif ($action === 'manual_ping') {
            $ips = $data['ip'] ?? '';
            $count = isset($data['count']) ? (int)$data['count'] : 4;
            $targets = preg_split('/[\s,;]+/', $ips);
            $targets = array_filter($targets);
            $results = [];
            foreach ($targets as $t) {
                $t = trim($t);
                if (!$t) continue;
                $results[] = monitor_manual_ping_test($t, $count);
            }
            echo json_encode(['success' => true, 'results' => $results]);
        } elseif ($action === 'get_logs') {
            $ip = $data['ip'] ?? '';
            $date = $data['date'] ?? date('Y-m-d');
            echo json_encode(monitor_get_logs($ip, $date));
        } elseif ($action === 'get_history_summary') {
            echo json_encode(monitor_get_history_summary());
        } elseif ($action === 'get_node_summary') {
            $ip = $data['ip'] ?? '';
            echo json_encode(monitor_get_node_summary($ip));
        } elseif ($action === 'dns_lookup') {
            $host = $data['host'] ?? '';
            $customDns = $data['dns_server'] ?? '';
            if (!$host) { echo json_encode(['success' => false, 'message' => 'No host provided.']); return; }
            $systemDns = [];
            $resolv = @file_get_contents('/etc/resolv.conf');
            if ($resolv !== false && preg_match_all('/^nameserver\s+(\S+)/m', $resolv, $m)) {
                $systemDns = $m[1];
            }
            $dnsServer = $customDns ?: (count($systemDns) ? implode(', ', $systemDns) : 'System default');
            if ($customDns) {
                $digOut = []; $digCode = 0;
                exec("dig +time=5 +tries=1 @" . escapeshellarg($customDns) . " " . escapeshellarg($host) . " ANY +noall +answer 2>&1", $digOut, $digCode);
                $lastLine = $digOut ? end($digOut) : '';
                if ($digCode !== 0 || stripos($lastLine, 'connection timed out') !== false || stripos($lastLine, 'no servers could be reached') !== false) {
                    echo json_encode(['success' => false, 'message' => 'DNS server ' . $customDns . ' is unreachable or invalid. Check the IP and try again.']);
                    return;
                }
                echo json_encode(['success' => true, 'host' => $host, 'server' => $dnsServer, 'dig_output' => $digOut ?: ['No records returned.']]);
            } else {
                $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
                if ($isIp) {
                    $ptr = @gethostbyaddr($host);
                    $records = @dns_get_record($host, DNS_A | DNS_AAAA | DNS_MX | DNS_NS | DNS_TXT | DNS_CNAME);
                    echo json_encode(['success' => true, 'host' => $host, 'filter' => 'ip', 'ptr' => $ptr ?: null, 'server' => $dnsServer, 'records' => $records ?: []]);
                } else {
                    $records = @dns_get_record($host, DNS_A | DNS_AAAA | DNS_MX | DNS_NS | DNS_TXT | DNS_CNAME);
                    echo json_encode(['success' => true, 'host' => $host, 'filter' => 'host', 'server' => $dnsServer, 'records' => $records ?: []]);
                }
            }
        } elseif ($action === 'port_check') {
            $ip = $data['ip'] ?? ''; $port = (int)($data['port'] ?? 0);
            if (!$ip || !$port) { echo json_encode(['success' => false, 'message' => 'Invalid IP or port.']); return; }
            $errno = 0; $errstr = ''; $start = microtime(true);
            $fp = @fsockopen($ip, $port, $errno, $errstr, 3);
            $elapsed = round((microtime(true) - $start) * 1000);
            if ($fp) { fclose($fp); echo json_encode(['success' => true, 'open' => true, 'latency' => $elapsed]); }
            else { echo json_encode(['success' => true, 'open' => false, 'latency' => $elapsed, 'error' => $errstr]); }
        } elseif ($action === 'traceroute') {
            $ip = $data['ip'] ?? '';
            if (!$ip) { echo json_encode(['success'=>false,'message'=>'No IP']); return; }
            $out = []; $code = 0;
            exec("traceroute -n -m 20 -w 2 " . escapeshellarg($ip) . " 2>&1", $out, $code);
            echo json_encode(['success'=>true,'ip'=>$ip,'hops'=>$out]);
        } elseif ($action === 'mtr_report') {
            $ip = $data['ip'] ?? '';
            if (!$ip) { echo json_encode(['success'=>false,'message'=>'No IP']); return; }
            $out = []; $code = 0;
            exec("mtr --report -c 5 -n -w " . escapeshellarg($ip) . " 2>&1", $out, $code);
            echo json_encode(['success'=>true,'ip'=>$ip,'report'=>$out]);
        } elseif ($action === 'record_history') {
            $logDir = '/data/logs/monitoring';
            if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
            $entry = array_merge(['ts'=>time()], array_intersect_key($data, array_flip(['cpu','mem','disk','net','fpm_active','fpm_idle','fpm_total','dkr_cpu','dkr_mem'])));
            $line = json_encode($entry) . "\n";
            @file_put_contents("$logDir/history-".date('Y-m-d').".log",$line,FILE_APPEND|LOCK_EX);
            foreach(glob("$logDir/history-*.log") as $f){if(filemtime($f)&&filemtime($f)<strtotime('-30 days'))@unlink($f);}
            echo json_encode(['success'=>true]);
        } elseif ($action === 'get_history') {
            $logDir = '/data/logs/monitoring';
            $hours = isset($data['hours'])?(int)$data['hours']:48;
            $cutoff = time()-($hours*3600);$entries=[];$allKeys=['cpu','mem','disk','net','fpm_active','fpm_idle','fpm_total','dkr_cpu','dkr_mem'];
            for($d=-3;$d<=0;$d++){
                $f="$logDir/history-".date('Y-m-d',strtotime("$d days")).".log";
                if(!file_exists($f))continue;
                $lines=@file($f,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
                if(!$lines)continue;
                foreach($lines as $l){$e=json_decode($l,true);if($e&&isset($e['ts'])&&$e['ts']>=$cutoff)$entries[]=$e;}
            }
            usort($entries,function($a,$b){return $a['ts']-$b['ts'];});
            $target=200;$cnt=count($entries);
            if($cnt>$target){$win=ceil($cnt/$target);$agg=[];for($i=0;$i<$cnt;$i+=$win){$sl=array_slice($entries,$i,$win);$avg=['ts'=>$sl[0]['ts']];foreach($allKeys as $k){$v=array_column($sl,$k);$vals=array_filter($v,fn($x)=>$x!==null);$avg[$k]=count($vals)?round(array_sum($vals)/count($vals),1):null;}$agg[]=$avg;}$entries=$agg;}
            echo json_encode(['success'=>true,'entries'=>$entries]);
        } elseif ($action === 'app_metrics') {
            $logBase = function_exists('get_external_log_base') ? get_external_log_base() : (getenv('ACCESSPILOT_LOG_BASE_PATH') ?: '/data/logs');
            $todayFile = $logBase . '/app_audit_logs/audit-' . date('Y-m-d') . '.csv';
            $metrics = ['total_actions'=>0,'logins'=>0,'failures'=>0,'top_actions'=>[],'hourly_activity'=>array_fill(0,24,0),'unique_user_count'=>0,'success_rate'=>100];
            $uniqueUsers = [];
            if (file_exists($todayFile) && ($handle = fopen($todayFile, 'r')) !== false) {
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 3) continue;
                    $ts = $row[0]; $user = trim($row[1]); $action2 = trim($row[2]); $status = trim($row[3] ?? '');
                    $metrics['total_actions']++;
                    if ($user && !in_array($user, $uniqueUsers)) $uniqueUsers[] = $user;
                    if (stripos($status, 'success') !== false && stripos($action2, 'login') !== false) $metrics['logins']++;
                    if (stripos($status, 'fail') !== false || stripos($status, 'error') !== false || stripos($status, 'denied') !== false) $metrics['failures']++;
                    $actKey = strtolower(preg_replace('/[^a-z0-9]/', '_', $action2));
                    if (!isset($metrics['top_actions'][$actKey])) $metrics['top_actions'][$actKey] = 0;
                    $metrics['top_actions'][$actKey]++;
                    if ($ts) { $h = date('G', strtotime($ts)); if ($h !== false) $metrics['hourly_activity'][(int)$h]++; }
                }
                fclose($handle);
            }
            $metrics['unique_user_count'] = count($uniqueUsers);
            arsort($metrics['top_actions']);
            $metrics['top_actions'] = array_slice($metrics['top_actions'], 0, 10);
            $metrics['success_rate'] = $metrics['total_actions'] > 0 ? round(($metrics['total_actions'] - $metrics['failures']) / $metrics['total_actions'] * 100) : 100;
            echo json_encode(['success' => true, 'metrics' => $metrics], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } elseif ($action === 'whois_lookup') {
            $host = $data['host'] ?? '';
            if (!$host) { echo json_encode(['success'=>false,'message'=>'No host']); return; }
            $lines = [];
            $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
            $srv = $isIp ? 'whois.arin.net' : 'whois.verisign-grs.com';
            $sock = @fsockopen($srv, 43, $e, $s, 5);
            if ($sock) {
                fwrite($sock, $host . "\r\n");
                $raw = '';
                while (!feof($sock)) $raw .= fgets($sock, 4096);
                fclose($sock);
                $lines = explode("\n", trim($raw));
                $lines = array_values(array_filter($lines, function($l) { return !str_starts_with($l, '%') && !str_starts_with($l, '#'); }));
                $lines = array_slice($lines, 0, 20);
            } else {
                $lines[] = "Unable to connect to WHOIS server: $e $s";
            }
            echo json_encode(['success'=>true,'host'=>$host,'data'=>$lines]);
        }
    }
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Critical NOC Error: ' . $e->getMessage()]);
}
