<?php

require_once __DIR__ . '/../../Application/Support/helpers.php';
if (!function_exists('dashboard_log_default_categories')) {
    function dashboard_log_default_categories(): array
    {
        return [
            'unlock',
            'UserInfo',
            'UserInfo-disable',
            'NewUser',
            'createUser',
            'ManualCreate',
            'PassReset',
            'UserDisable',
            'UserEnable',
            'UserModify',
            'GroupMgmt',
            'GroupMembership',
            'Ou&Grp_mgt',
            'EmpStsChk',
            'FindLogonID',
            'user_export',
            'HealthCheck',
            'ExchangeMailbox',
            'ExchangeGroup',
            'ExchangeSettings',
        ];
    }
}

if (!function_exists('dashboard_category_path_map')) {
    function dashboard_category_path_map(): array
    {
        return [
            'NewUser' => 'User_Management/NewUser',
            'createUser' => 'User_Management/NewUser',
            'ManualCreate' => 'User_Management/ManualCreate',
            'ManulCreate' => 'User_Management/ManualCreate',
            'PassReset' => 'User_Management/PassReset',
            'unlock' => 'User_Management/unlock',
            'UserDisable' => 'User_Management/UserDisable',
            'UserEnable' => 'User_Management/UserEnable',
            'UserModify' => 'User_Management/UserModify',
            'UserInfo' => 'User_Management/UserInfo',
            'UserInfo-disable' => 'User_Management/UserInfo_disable',
            'Ou&Grp_mgt' => 'Directory_Services/Ou_Group_Mgt',
            'DirBuilder' => 'Directory_Services/Ou_Group_Mgt',
            'GroupMgmt' => 'Directory_Services/GroupMgmt',
            'GroupMembership' => 'Directory_Services/GroupMembership',
            'EmpStsChk' => 'Integration/EmpStsChk',
            'FindLogonID' => 'Integration/FindLogonID',
            'user_export' => 'Integration/user_export',
            'HealthCheck' => 'HealthCheck',
            'General' => 'General',
            'ExchangeMailbox' => 'Exchange/Mailbox',
            'ExchangeGroup' => 'Exchange/Group',
            'ExchangeSettings' => 'Exchange/Settings',
        ];
    }
}

if (!function_exists('dashboard_log_domain_dirs')) {
    function dashboard_log_domain_dirs(): array
    {
        $base = get_external_log_base();
        $domains = [];
        $baseNormalized = rtrim($base, '/\\');
        if (!is_dir($baseNormalized)) return $domains;

        // Build lookup from domain configs if available
        $configDomains = [];
        if (function_exists('ldap_get_domains')) {
            foreach (ldap_get_domains() as $d) {
                $key = $d['key'] ?? '';
                if ($key !== '') {
                    $label = $d['label'] ?? '';
                    $baseDn = $d['base_dn'] ?? '';
                    // Extract AD domain name from base_dn e.g. "DC=example,DC=COM" → "example.com"
                    $adName = '';
                    if ($baseDn !== '') {
                        $parts = [];
                        preg_match_all('/DC\s*=\s*([^,]+)/i', $baseDn, $parts);
                        if (!empty($parts[1])) {
                            $adName = strtolower(implode('.', $parts[1]));
                        }
                    }
                    $configDomains[$key] = [
                        'label' => $label ?: $adName ?: $key,
                        'ad_name' => $adName ?: $key,
                    ];
                }
            }
        }

        $items = scandir($baseNormalized);
        if ($items === false) return $domains;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $scriptsDir = $baseNormalized . DIRECTORY_SEPARATOR . $item . DIRECTORY_SEPARATOR . 'scripts_logs';
            if (!is_dir($scriptsDir)) continue;

            // Determine the display info
            $info = $configDomains[$item] ?? [];
            $adName = $info['ad_name'] ?? $item;

            // Skip old key-based directory if the AD-named directory already exists
            if (isset($configDomains[$item]) && $adName !== $item) {
                $adNamedDir = $baseNormalized . DIRECTORY_SEPARATOR . $adName . DIRECTORY_SEPARATOR . 'scripts_logs';
                if (is_dir($adNamedDir)) {
                    continue; // skip old key-named dir, new AD-named one will be picked up
                }
            }

            $domains[] = [
                'key' => $item,
                'path' => $scriptsDir,
                'label' => $info['label'] ?? $item,
                'ad_name' => $adName,
            ];
        }

        return $domains;
    }
}

if (!function_exists('dashboard_active_domain_key')) {
    function dashboard_active_domain_key(): string
    {
        return function_exists('ldap_active_domain_key') ? ldap_active_domain_key() : 'default';
    }
}

if (!function_exists('dashboard_active_domain_ad_name')) {
    function dashboard_active_domain_ad_name(): string
    {
        return function_exists('ldap_active_domain_ad_name') ? ldap_active_domain_ad_name() : dashboard_active_domain_key();
    }
}

if (!function_exists('dashboard_log_base_dir')) {
    function dashboard_log_base_dir(): string
    {
        $base = get_external_log_base();
        
        $adName = dashboard_active_domain_ad_name();
        $keyName = dashboard_active_domain_key();
        
        // Try AD name directory first (new naming)
        $scriptsLogsDir = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $adName . DIRECTORY_SEPARATOR . 'scripts_logs';
        
        // Fall back to key-based directory if AD name dir is empty or missing
        if (!is_dir($scriptsLogsDir)) {
            $fallbackDir = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $keyName . DIRECTORY_SEPARATOR . 'scripts_logs';
            if (is_dir($fallbackDir)) {
                $scriptsLogsDir = $fallbackDir;
            } else {
                @mkdir($scriptsLogsDir, 0775, true);
                @mkdir(rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $keyName . DIRECTORY_SEPARATOR . 'scripts_logs', 0775, true);
            }
        }
        
        return $scriptsLogsDir;
    }
}

if (!function_exists('dashboard_normalize_category_name')) {
    function dashboard_normalize_category_name(string $rawCategory): string
    {
        $lookup = [
            'createuser' => 'NewUser',
            'newuser' => 'NewUser',
            'tnewuser' => 'NewUser',
            'manulcreate' => 'ManualCreate',
            'passreset' => 'PassReset',
            'unlock' => 'unlock',
            'userdisable' => 'UserDisable',
            'userenable' => 'UserEnable',
            'usermodify' => 'UserModify',
            'userinfo' => 'UserInfo',
            'userinfo-disable' => 'UserInfo-disable',
            'empstschk' => 'EmpStsChk',
            'findlogonid' => 'FindLogonID',
            'user_export' => 'user_export',
            'dirbuilder' => 'DirBuilder',
            'groupmgmt' => 'GroupMgmt',
            'ou&grp_mgt' => 'Ou&Grp_mgt',
        ];

        $normalizedKey = strtolower(trim($rawCategory));
        return $lookup[$normalizedKey] ?? $rawCategory;
    }
}

if (!function_exists('dashboard_normalize_action_name')) {
    function dashboard_normalize_action_name(string $action): string
    {
        $action = strtoupper(trim($action));
        return match ($action) {
            'RESET+UNLOCK', 'RESETUNLOCK', 'U & RESET' => 'U & RESET',
            'ENABLE USER', 'ENABLE_USER' => 'ENABLE',
            'CREATEUSER' => 'CREATE',
            'UNLOCKUSER' => 'UNLOCK',
            'DISABLE USER' => 'DISABLE',
            'CREATE OU' => 'CREATE OU',
            'CREATE GRP' => 'CREATE GRP',
            'DELETE OU' => 'DELETE OU',
            'DELETE GRP' => 'DELETE GRP',
            'GRP UPDATE' => 'GRP UPDATE',
            'C_OU' => 'CREATE OU',
            'C_GRP' => 'CREATE GRP',
            'D_OU' => 'DELETE OU',
            'D_GRP' => 'DELETE GRP',
            'G_UPD' => 'GRP UPDATE',
            'CREATE_OU' => 'CREATE OU',
            'CREATE_GRP' => 'CREATE GRP',
            'DLT_OU' => 'DELETE OU',
            'DLT_GRP' => 'DELETE GRP',
            'GRP_M.MGT' => 'GRP UPDATE',
            default => $action,
        };
    }
}

if (!function_exists('dashboard_normalize_status')) {
    function dashboard_normalize_status(string $status): string
    {
        $status = strtoupper($status);
        if (strpos($status, 'NOT FOUND') !== false) {
            return 'NOT FOUND';
        }
        if (strpos($status, 'EXISTS') !== false) {
            return 'SKIPPED';
        }
        if (strpos($status, 'TRIGGERED') !== false) {
            return 'TRIGGERED';
        }
        return $status;
    }
}

if (!function_exists('dashboard_push_log_entry')) {
    function dashboard_push_log_entry(
        array &$detailedLogs,
        string $timestamp,
        string $action,
        string $targetUser,
        string $status,
        string $performedBy,
        string $message,
        string $category,
        string $ip = 'N/A',
        string $domain = ''
    ): void {
        $detailedLogs[] = [
            'timestamp' => $timestamp,
            'action' => dashboard_normalize_action_name($action),
            'targetUser' => $targetUser,
            'status' => $status,
            'performedBy' => $performedBy !== '' ? $performedBy : 'N/A',
            'message' => $message,
            'category' => $category,
            'ip' => $ip !== '' ? $ip : 'N/A',
            'domain' => $domain !== '' ? $domain : 'N/A',
        ];
    }
}

if (!function_exists('dashboard_process_structured_log_line')) {
    function dashboard_process_structured_log_line(
        array &$detailedLogs,
        string $line,
        string $category,
        int $cutoffTime = 0,
        int $endCutoffTime = 0,
        string $domain = ''
    ): bool {
        $lineParts = explode('] ', $line, 2);
        if (count($lineParts) < 2) {
            return false;
        }

        [$timestamp, $rest] = $lineParts;
        $timestamp = ltrim($timestamp, '[');
        $logEntryTime = strtotime($timestamp);
        if (!$logEntryTime) {
            return false;
        }

        if ($cutoffTime > 0 && $logEntryTime < $cutoffTime) {
            return true;
        }
        if ($endCutoffTime > 0 && $logEntryTime > $endCutoffTime) {
            return true;
        }

        $parts = explode(' | ', $rest);
        $logData = [];

        foreach ($parts as $part) {
            if (strpos($part, ': ') === false) {
                continue;
            }

            [$key, $value] = explode(': ', $part, 2);
            switch (trim($key)) {
                case 'Action':
                    $logData['action'] = preg_replace('/[^\w& ]/', '', trim($value));
                    break;
                case 'TargetUser':
                    $logData['targetUser'] = trim($value);
                    break;
                case 'Status':
                    $logData['status'] = trim($value);
                    break;
                case 'Message':
                    $logData['message'] = trim($value);
                    break;
                case 'PerformedBy':
                case 'ExecutedBy':
                    $logData['performedBy'] = trim($value);
                    break;
                case 'IP':
                    $logData['ip'] = trim($value);
                    break;
            }
        }

        if (isset($logData['action'], $logData['targetUser'], $logData['status'])) {
            dashboard_push_log_entry(
                $detailedLogs,
                $timestamp,
                $logData['action'],
                $logData['targetUser'],
                $logData['status'],
                $logData['performedBy'] ?? 'N/A',
                $logData['message'] ?? '',
                $category,
                $logData['ip'] ?? 'N/A',
                $domain
            );
        }

        return true;
    }
}

if (!function_exists('dashboard_process_legacy_transcript_lines')) {
    function dashboard_process_legacy_transcript_lines(
        array &$detailedLogs,
        array $lines,
        string $fileDate,
        string $category,
        int $cutoffTime = 0,
        int $endCutoffTime = 0
    ): void {
        $baseTimestamp = $fileDate . ' 12:00:00 PM';
        $logEntryTime = strtotime($baseTimestamp);
        if (!$logEntryTime) {
            return;
        }
        if ($cutoffTime > 0 && $logEntryTime < $cutoffTime) {
            return;
        }
        if ($endCutoffTime > 0 && $logEntryTime > $endCutoffTime) {
            return;
        }

        $performedBy = 'N/A';
        $employeeId = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^RunAs User:\s*(.+)$/i', $trimmed, $matches)) {
                $performedBy = trim($matches[1]);
                continue;
            }
            if (preg_match('/^### Starting user provisioning for Employee ID:\s*(.+)$/i', $trimmed, $matches)) {
                $employeeId = trim($matches[1]);
                continue;
            }
            if (preg_match('/^>Created OU:\s*(.+)$/i', $trimmed, $matches)) {
                dashboard_push_log_entry($detailedLogs, $baseTimestamp, 'CREATE_OU', trim($matches[1]), 'SUCCESS', $performedBy, 'Created OU: ' . trim($matches[1]), $category);
                continue;
            }
            if (preg_match('/^\+Created Group:\s*(.+)$/i', $trimmed, $matches)) {
                dashboard_push_log_entry($detailedLogs, $baseTimestamp, 'CREATE_GROUP', trim($matches[1]), 'SUCCESS', $performedBy, 'Created Group: ' . trim($matches[1]), $category);
                continue;
            }
            if (preg_match('/^\+\+\s*Added group \'(.+)\' as a member of \'(.+)\'$/i', $trimmed, $matches)) {
                dashboard_push_log_entry($detailedLogs, $baseTimestamp, 'ADD_GRP_MMBR', trim($matches[1]), 'SUCCESS', $performedBy, "Added group '{$matches[1]}' as a member of '{$matches[2]}'", $category);
                continue;
            }
            if (preg_match('/^==>\s*Added user\s+(.+)\s+to group\s+(.+)$/i', $trimmed, $matches)) {
                dashboard_push_log_entry($detailedLogs, $baseTimestamp, 'ADDED GRP', trim($matches[1]), 'SUCCESS', $performedBy, "Added user '{$matches[1]}' to group '{$matches[2]}'", $category);
                continue;
            }
            if (preg_match('/>>\s*Action Taken:\s*User\s+\'(.+)\'\s+\((.+)\)\s+created/i', $trimmed, $matches)) {
                $targetUser = trim($matches[2]) ?: $employeeId;
                dashboard_push_log_entry($detailedLogs, $baseTimestamp, 'CREATE', $targetUser, 'SUCCESS', $performedBy, $trimmed, $category);
            }
        }
    }
}

if (!function_exists('dashboard_read_logs')) {
    function dashboard_read_logs(array $options = []): array
    {
        $categories = $options['categories'] ?? dashboard_log_default_categories();
        $cutoffTime = (int)($options['cutoff_time'] ?? 0);
        $endCutoffTime = (int)($options['end_cutoff_time'] ?? 0);
        $todayOnly = !empty($options['today_only']);
        $monthPrefix = (string)($options['month_prefix'] ?? '');
        $includeRoot = !empty($options['include_root']);
        $skipCategories = array_map('strtolower', $options['skip_categories'] ?? []);
        $domainOption = $options['domain'] ?? '';
        $logs = [];

        // ── Resolve domain base directories ──────────────────────────
        $domainDirs = [];
        if ($domainOption === 'all') {
            $domainDirs = dashboard_log_domain_dirs();
        } elseif ($domainOption !== '') {
            $base = get_external_log_base();
            // Resolve config key to AD name if needed
            $resolvedOption = $domainOption;
            $adName = function_exists('ldap_domain_ad_name') ? ldap_domain_ad_name($domainOption) : '';
            if ($adName !== '') {
                $resolvedOption = $adName;
            }
            $path = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $resolvedOption . DIRECTORY_SEPARATOR . 'scripts_logs';
            if (is_dir($path)) {
                $domainDirs[] = ['key' => $resolvedOption, 'path' => $path];
            }
        } else {
            // Default: active domain only
            $baseDir = dashboard_log_base_dir();
            if ($baseDir === '' || !is_dir($baseDir)) {
                return [];
            }
            $domainDirs[] = ['key' => dashboard_active_domain_ad_name(), 'path' => $baseDir];
        }

        if (empty($domainDirs)) {
            return [];
        }

        // ── Root audit logs (included once, no domain association) ──
        if ($includeRoot) {
            $baseLogPath = get_external_log_base();
            $auditDir = rtrim($baseLogPath, '/\\') . DIRECTORY_SEPARATOR . 'app_audit_logs';
            
            $rootFiles = glob(rtrim($auditDir, '/\\') . DIRECTORY_SEPARATOR . 'audit-*.csv') ?: [];
            foreach ($rootFiles as $rootLogFile) {
                if (!preg_match('/audit-(\d{4}-\d{2}-\d{2})\.csv$/', basename($rootLogFile), $matches)) {
                    continue;
                }
                $fileDate = $matches[1];
                $fileTimeStart = strtotime($fileDate . ' 00:00:00');
                $fileTimeEnd = strtotime($fileDate . ' 23:59:59');
                if ($cutoffTime > 0 && $fileTimeEnd < $cutoffTime) {
                    continue;
                }
                if ($endCutoffTime > 0 && $fileTimeStart > $endCutoffTime) {
                    continue;
                }

                $lines = file($rootLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines === false) {
                    continue;
                }

                foreach ($lines as $line) {
                    $data = str_getcsv($line, escape: "\\");
                    if (count($data) < 5) continue;

                    $log_timestamp_str = $data[0] ?? '';
                    $logEntryTime = strtotime($log_timestamp_str);
                    if (!$logEntryTime) continue;

                    if ($cutoffTime > 0 && $logEntryTime < $cutoffTime) continue;
                    if ($endCutoffTime > 0 && $logEntryTime > $endCutoffTime) continue;

                    $username = $data[1] ?? '';
                    $action = $data[2] ?? '';
                    $status = $data[3] ?? '';
                    $details_raw = $data[4] ?? '';
                    
                    preg_match('/IP: (.*?)(, Details: (.*))?$/', $details_raw, $m);
                    $ip = $m[1] ?? 'N/A';
                    $details = $m[3] ?? $details_raw;

                    dashboard_push_log_entry(
                        $logs,
                        $log_timestamp_str,
                        $action,
                        $username,
                        $status,
                        $username, 
                        $details,
                        'General',
                        $ip
                    );
                }
            }
        }

        // ── Script logs per domain ────────────────────────────────────
        $pathMap = dashboard_category_path_map();
        foreach ($domainDirs as $domainInfo) {
            $baseDir = $domainInfo['path'];
            $domainKey = $domainInfo['key'];
            $processedDirs = [];

            foreach ($categories as $cat) {
                $relativePath = $pathMap[$cat] ?? $cat;
                $logDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath) . DIRECTORY_SEPARATOR;
                if (!is_dir($logDir)) {
                    continue;
                }

                $normalizedDir = realpath($logDir);
                if ($normalizedDir === false) {
                    continue;
                }
                if (in_array($normalizedDir, $processedDirs, true)) {
                    continue;
                }
                $processedDirs[] = $normalizedDir;

                $category = dashboard_normalize_category_name($cat);
                if (in_array(strtolower($cat), $skipCategories, true) || in_array(strtolower($category), $skipCategories, true)) {
                    continue;
                }

                $todayFileName = 'audit-' . date('Y-m-d') . '.log';
                $logFiles = [];
                $dirIterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($logDir, RecursiveDirectoryIterator::SKIP_DOTS)
                );
                foreach ($dirIterator as $fileInfo) {
                    if (!$fileInfo->isFile()) continue;
                    $logFiles[] = $fileInfo->getPathname();
                }
                sort($logFiles);

                foreach ($logFiles as $logFile) {
                    $filename = basename($logFile);
                    if ($todayOnly && $filename !== $todayFileName) {
                        continue;
                    }
                    if ($monthPrefix !== '' && !preg_match('/^audit-' . preg_quote($monthPrefix, '/') . '-\d{2}\.log$/', $filename)) {
                        continue;
                    }
                    if (!preg_match('/^audit-(\d{4}-\d{2}-\d{2})\.log$/', $filename, $matches)) {
                        continue;
                    }

                    $fileDate = $matches[1];
                    $fileTimeStart = strtotime($fileDate . ' 00:00:00');
                    $fileTimeEnd = strtotime($fileDate . ' 23:59:59');
                    if ($cutoffTime > 0 && $fileTimeEnd < $cutoffTime) {
                        continue;
                    }
                    if ($endCutoffTime > 0 && $fileTimeStart > $endCutoffTime) {
                        continue;
                    }

                    try {
                        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                        if ($lines === false) {
                            continue;
                        }

                        $structuredLineFound = false;
                        foreach ($lines as $line) {
                            $processed = dashboard_process_structured_log_line($logs, $line, $category, $cutoffTime, $endCutoffTime, $domainKey);
                            if ($processed) {
                                $structuredLineFound = true;
                            }
                        }

                        if (!$structuredLineFound && empty($options['skip_legacy_transcript'])) {
                            dashboard_process_legacy_transcript_lines($logs, $lines, $fileDate, $category, $cutoffTime, $endCutoffTime);
                        }
                    } catch (Throwable $e) {
                        if (!empty($options['include_errors'])) {
                            $logs[] = [
                                'timestamp' => date('Y-m-d H:i:s'),
                                'action' => 'SCRIPT_ERROR',
                                'targetUser' => basename($logFile),
                                'status' => 'FATAL',
                                'performedBy' => 'SYSTEM',
                                'message' => 'Error: ' . $e->getMessage() . ' on line ' . $e->getLine(),
                                'category' => $category,
                                'ip' => 'N/A',
                                'domain' => $domainKey,
                            ];
                        }
                    }
                }
            }
        }

        usort($logs, fn($a, $b) => strtotime((string)$b['timestamp']) - strtotime((string)$a['timestamp']));
        return $logs;
    }
}
