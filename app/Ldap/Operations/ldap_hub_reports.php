<?php

require_once __DIR__ . '/../Support/ldap_helpers.php';

if (!function_exists('ldap_hub_map_hrms_user_id')) {
    function ldap_hub_map_hrms_user_id(array $params, string $executedBy): array
    {
        $rawIds = trim((string) ($params['Usernames'] ?? ''));
        if ($rawIds === '') {
            return ldap_json_script_result([
                'success' => false,
                'message' => 'No HRMS IDs provided.',
                'results' => [],
                'processed' => 0, 'successCount' => 0, 'notFoundCount' => 0, 'errorCount' => 0,
            ], false, 1);
        }

        $hrmsIds = array_filter(array_map('trim', preg_split('/[\s,;]+/', $rawIds)));
        $isSingleUser = count($hrmsIds) === 1;

        return ldap_run_with_connection(function ($connection, $config) use ($hrmsIds, $executedBy, $isSingleUser) {
            $baseDn = ldap_search_base_dn($config);
            if ($baseDn === '') {
                return ldap_json_script_result([
                    'success' => false,
                    'message' => 'LDAP base DN is not configured.',
                    'results' => [],
                    'processed' => count($hrmsIds), 'successCount' => 0, 'notFoundCount' => 0, 'errorCount' => 0,
                ], false, 1);
            }

            $results = [];
            $exactMatchCount = 0;
            $substringMatchCount = 0;
            $totalNotFound = 0;
            $totalErrors = 0;
            $totalProcessed = 0;

            foreach ($hrmsIds as $hrmsId) {
                $totalProcessed++;
                $hrmsId = trim($hrmsId);
                if ($hrmsId === '') { continue; }

                try {
                    $escaped = ldap_escape_filter_value($hrmsId);
                    $filter = "(&(objectCategory=person)(objectClass=user)(samAccountName=*{$escaped}*))";
                    $search = @ldap_search($connection, $baseDn, $filter, ['samaccountname', 'displayname']);
                    if ($search === false) {
                        throw new RuntimeException('LDAP search failed: ' . ldap_error($connection));
                    }

                    $raw = ldap_get_entries($connection, $search);
                    $adUserList = [];
                    $count = (int) ($raw['count'] ?? 0);
                    for ($i = 0; $i < $count; $i++) {
                        $adUserList[] = [
                            'SamAccountName' => $raw[$i]['samaccountname'][0] ?? '',
                            'DisplayName'    => $raw[$i]['displayname'][0] ?? 'N/A',
                        ];
                    }

                    $exactUser = null;
                    foreach ($adUserList as $u) {
                        if ($u['SamAccountName'] === $hrmsId) {
                            $exactUser = $u;
                            break;
                        }
                    }

                    if ($exactUser) {
                        $exactMatchCount++;
                        $results[] = [
                            'HRMS_ID'    => $hrmsId,
                            'DisplayName' => $exactUser['DisplayName'],
                            'LogonID'    => $exactUser['SamAccountName'],
                            'Status'     => 'SUCCESS',
                            'Message'    => "Exact match found for HRMS ID {$hrmsId}.",
                            'CheckedBy'  => $executedBy,
                        ];
                    } else {
                        $substringUsers = [];
                        foreach ($adUserList as $u) {
                            if (strpos($u['SamAccountName'], $hrmsId) !== false) {
                                $substringUsers[] = $u;
                            }
                        }

                        if (count($substringUsers) === 1) {
                            $substringMatchCount++;
                            $results[] = [
                                'HRMS_ID'    => $hrmsId,
                                'DisplayName' => $substringUsers[0]['DisplayName'],
                                'LogonID'    => $substringUsers[0]['SamAccountName'],
                                'Status'     => 'SUCCESS',
                                'Message'    => "Substring match found for HRMS ID {$hrmsId}.",
                                'CheckedBy'  => $executedBy,
                            ];
                        } elseif (count($substringUsers) > 1) {
                            $totalErrors++;
                            $results[] = [
                                'HRMS_ID'    => $hrmsId,
                                'DisplayName' => 'Multiple matches found',
                                'LogonID'    => 'Multiple matches found',
                                'Status'     => 'ERROR',
                                'Message'    => "Multiple users found containing HRMS ID {$hrmsId}. Please verify manually.",
                                'CheckedBy'  => $executedBy,
                            ];
                        } else {
                            $totalNotFound++;
                            $results[] = [
                                'HRMS_ID'    => $hrmsId,
                                'DisplayName' => 'Not found',
                                'LogonID'    => 'Not found',
                                'Status'     => 'NOT_FOUND',
                                'Message'    => "No match found for HRMS ID {$hrmsId}.",
                                'CheckedBy'  => $executedBy,
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    $totalErrors++;
                    $results[] = [
                        'HRMS_ID'    => $hrmsId,
                        'DisplayName' => 'Error',
                        'LogonID'    => 'Error',
                        'Status'     => 'ERROR',
                        'Message'    => "Error processing HRMS ID {$hrmsId}: " . $e->getMessage(),
                        'CheckedBy'  => $executedBy,
                    ];
                }
            }

            $totalSuccess = $exactMatchCount + $substringMatchCount;
            $failed = $totalErrors + $totalNotFound;
            $isSuccess = $totalErrors === 0;

            $parts = ["Processed: {$totalProcessed}"];
            if ($exactMatchCount > 0) { $parts[] = "Exact match: {$exactMatchCount}"; }
            if ($substringMatchCount > 0) { $parts[] = "Substring match: {$substringMatchCount}"; }
            if ($totalNotFound > 0) { $parts[] = "No match: {$totalNotFound}"; }
            if ($totalErrors > 0) { $parts[] = "Errors: {$totalErrors}"; }
            $summaryMessage = "Summary: " . implode(', ', $parts);

            $badge = $isSuccess
                ? "SUCCESS: HRMS to AD ID mapping completed."
                : "ERROR: Mapping completed with {$totalErrors} error(s) and {$totalNotFound} not found.";
            $fullMessage = $badge . "\n\n" . $summaryMessage;

            $targetUser = $isSingleUser ? $hrmsIds[0] : 'Multiple';
            $logStatus = $isSuccess ? 'SUCCESS' : 'FAILED';
            ldap_write_script_log('export_hrms_ad_user_id', $targetUser, $isSuccess, "Report: {$summaryMessage}", $executedBy);

            return ldap_json_script_result([
                'success' => $isSuccess,
                'message' => $fullMessage,
                'results' => $results,
                'processed' => $totalProcessed,
                'successCount' => $totalSuccess,
                'notFoundCount' => $totalNotFound,
                'errorCount' => $totalErrors,
                'exactMatchCount' => $exactMatchCount,
                'substringMatchCount' => $substringMatchCount,
            ], $isSuccess, $isSuccess ? 0 : 1);
        });
    }
}

if (!function_exists('ldap_hub_check_hrms_status')) {
    function ldap_hub_check_hrms_status(array $params, string $executedBy): array
    {
        $rawIds = trim((string) ($params['Usernames'] ?? ''));
        if ($rawIds === '') {
            return ldap_json_script_result([
                'success' => false,
                'message' => 'No employee IDs provided.',
                'results' => [],
                'processed' => 0, 'hrmsActiveCount' => 0, 'hrmsInactiveCount' => 0,
                'hrmsApiNotFoundCount' => 0, 'adEnabledCount' => 0, 'adDisabledCount' => 0,
                'adNotCreatedCount' => 0, 'errorCount' => 0,
            ], false, 1);
        }

        $empIds = array_filter(array_map('trim', preg_split('/[\s,;]+/', $rawIds)));
        $isSingleUser = count($empIds) === 1;

        return ldap_run_with_connection(function ($connection, $config) use ($empIds, $executedBy, $isSingleUser) {
            $baseDn = ldap_search_base_dn($config);
            if ($baseDn === '') {
                return ldap_json_script_result([
                    'success' => false,
                    'message' => 'LDAP base DN is not configured.',
                    'results' => [],
                    'processed' => count($empIds), 'hrmsActiveCount' => 0, 'hrmsInactiveCount' => 0,
                    'hrmsApiNotFoundCount' => 0, 'adEnabledCount' => 0, 'adDisabledCount' => 0,
                    'adNotCreatedCount' => 0, 'errorCount' => 0,
                ], false, 1);
            }

            $results = [];
            $hrmsActiveCount = 0;
            $hrmsInactiveCount = 0;
            $hrmsApiNotFoundCount = 0;
            $adEnabledCount = 0;
            $adDisabledCount = 0;
            $adNotCreatedCount = 0;
            $totalErrors = 0;
            $totalProcessed = 0;

            $hrmsApiBase = 'https://whrmsapi.waltonbd.com/info/emp_info.php';

            foreach ($empIds as $empID) {
                $totalProcessed++;
                $empID = trim($empID);
                if ($empID === '') { continue; }

                $result = [
                    'EMP_ID'     => $empID,
                    'EMP_NAME'   => 'N/A',
                    'HRMS_STATUS' => 'N/A',
                    'AD_STATUS'  => 'N/A',
                    'CheckedBy'  => $executedBy,
                ];

                try {
                    // --- HRMS API Lookup ---
                    $apiUrl = $hrmsApiBase . '?emp_id=' . urlencode($empID);
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $apiUrl,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 5,
                        CURLOPT_CONNECTTIMEOUT => 3,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => 0,
                        CURLOPT_SSLVERSION => 6,
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                        CURLOPT_USERAGENT => 'AccessPilot-HRMS/4.0',
                    ]);
                    $apiResponse = curl_exec($ch);
                    curl_close($ch);

                    $empName = 'N/A';
                    $hrmsStatus = 'N/A';
                    $empSamForAD = $empID;

                    if ($apiResponse !== false) {
                        $apiData = json_decode($apiResponse, true);
                        if (is_array($apiData) && !empty($apiData['EMP_ID'])) {
                            $empName = $apiData['EMP_NAME'] ?? 'N/A';
                            $hrmsStatus = $apiData['EMP_STS'] ?? 'N/A';
                            $empSamForAD = $apiData['EMP_CODE'] ?? $empID;
                            if (strtoupper($hrmsStatus) === 'ACTIVE') {
                                $hrmsActiveCount++;
                            } else {
                                $hrmsInactiveCount++;
                            }
                        } else {
                            $hrmsApiNotFoundCount++;
                            $hrmsStatus = 'Not Found';
                        }
                    } else {
                        $hrmsApiNotFoundCount++;
                        $hrmsStatus = 'Not Found';
                    }

                    $result['EMP_NAME'] = $empName;
                    $result['HRMS_STATUS'] = $hrmsStatus;

                    // --- Active Directory Lookup ---
                    $escaped = ldap_escape_filter_value($empSamForAD);
                    $filter = "(&(objectCategory=person)(objectClass=user)(samAccountName={$escaped}))";
                    $search = @ldap_search($connection, $baseDn, $filter, ['dn', 'useraccountcontrol']);
                    if ($search === false) {
                        throw new RuntimeException('LDAP search failed: ' . ldap_error($connection));
                    }

                    $raw = ldap_get_entries($connection, $search);
                    $count = (int) ($raw['count'] ?? 0);

                    if ($count > 0) {
                        $uac = (int) ($raw[0]['useraccountcontrol'][0] ?? 0);
                        $isEnabled = ($uac & 2) === 0;
                        $result['AD_STATUS'] = $isEnabled ? 'Enabled' : 'Disabled';
                        if ($isEnabled) { $adEnabledCount++; } else { $adDisabledCount++; }
                    } else {
                        $result['AD_STATUS'] = 'Not Created';
                        $adNotCreatedCount++;
                    }

                } catch (\Throwable $e) {
                    $totalErrors++;
                    $result['EMP_NAME'] = 'Error';
                    $result['HRMS_STATUS'] = 'Error';
                    $result['AD_STATUS'] = 'Error';
                }

                $results[] = $result;
            }

            $isSuccess = $totalErrors === 0;

            $parts = ["Processed: {$totalProcessed}"];
            if ($hrmsActiveCount > 0) { $parts[] = "HRMS Active: {$hrmsActiveCount}"; }
            if ($hrmsInactiveCount > 0) { $parts[] = "HRMS Inactive: {$hrmsInactiveCount}"; }
            if ($hrmsApiNotFoundCount > 0) { $parts[] = "HRMS API Not Found: {$hrmsApiNotFoundCount}"; }
            if ($adEnabledCount > 0) { $parts[] = "AD Enabled: {$adEnabledCount}"; }
            if ($adDisabledCount > 0) { $parts[] = "AD Disabled: {$adDisabledCount}"; }
            if ($adNotCreatedCount > 0) { $parts[] = "AD Not Created: {$adNotCreatedCount}"; }
            if ($totalErrors > 0) { $parts[] = "Errors: {$totalErrors}"; }
            $summaryMessage = "Summary: " . implode(', ', $parts);

            $targetUser = $isSingleUser ? $empIds[0] : 'Multiple';
            ldap_write_script_log('get_ad_hrms_status', $targetUser, $isSuccess, "Report: {$summaryMessage}", $executedBy);

            return ldap_json_script_result([
                'success' => $isSuccess,
                'message' => ($isSuccess ? 'SUCCESS' : 'ERROR') . ": HRMS status check completed.\n\n{$summaryMessage}",
                'results' => $results,
                'processed' => $totalProcessed,
                'hrmsActiveCount' => $hrmsActiveCount,
                'hrmsInactiveCount' => $hrmsInactiveCount,
                'hrmsApiNotFoundCount' => $hrmsApiNotFoundCount,
                'adEnabledCount' => $adEnabledCount,
                'adDisabledCount' => $adDisabledCount,
                'adNotCreatedCount' => $adNotCreatedCount,
                'errorCount' => $totalErrors,
            ], $isSuccess, $isSuccess ? 0 : 1);
        });
    }
}

if (!function_exists('ldap_hub_export_users')) {
    function ldap_hub_export_users(array $params, string $executedBy): array
    {
        $ouName = trim((string) ($params['OUName'] ?? ''));
        $groupName = trim((string) ($params['GroupName'] ?? ''));
        $allUsers = !empty($params['AllUsers']);
        $isOuExport = $ouName !== '';
        $isGroupExport = $groupName !== '';
        $targetUserLog = 'All AD Users';
        $sourceOuName = null;
        $sourceGroupName = null;

        return ldap_run_with_connection(function ($connection, $config) use ($ouName, $groupName, $allUsers, $isOuExport, $isGroupExport, $executedBy, &$targetUserLog, &$sourceOuName, &$sourceGroupName) {
            $baseDn = ldap_search_base_dn($config);
            if ($baseDn === '') {
                return ldap_json_script_result([
                    'success' => false, 'message' => 'LDAP base DN is not configured.',
                    'totalUsers' => 0, 'selectedOU' => $ouName, 'selectedGroup' => $groupName, 'csvContent' => [],
                ], false, 1);
            }

            $totalUsers = 0; $enabledUsers = 0; $disabledUsers = 0;
            $enterpriseAdminsCount = 0; $domainAdminsCount = 0; $administratorsCount = 0;
            $activeUsers60Days = 0; $inactiveUsers60Days = 0;

            try {
                $searchBase = $baseDn;
                $filter = '(&(objectCategory=person)(objectClass=user))';

                if ($isOuExport) {
                    $escapedOu = ldap_escape_filter_value($ouName);
                    $ouSearch = @ldap_search($connection, $baseDn, "(distinguishedName={$escapedOu})", ['dn', 'name'], 0, 1);
                    if ($ouSearch !== false) {
                        $ouRaw = ldap_get_entries($connection, $ouSearch);
                        if ((int) ($ouRaw['count'] ?? 0) > 0) {
                            $searchBase = $ouRaw[0]['dn'];
                            $sourceOuName = $ouRaw[0]['name'][0] ?? $ouName;
                            if (stripos($ouName, 'DC=') === 0) { $sourceOuName = 'Domain Root'; }
                        } else { $sourceOuName = $ouName; }
                    } else { $sourceOuName = $ouName; }
                    $targetUserLog = $sourceOuName ?? $ouName;
                }

                if ($isGroupExport) {
                    $groupDnToFind = null;
                    $groupNameClean = $groupName;

                    // If the input looks like a DN (contains '='), extract the CN
                    if (strpos($groupName, '=') !== false) {
                        if (preg_match('/^CN=([^,]+)/i', $groupName, $m)) {
                            $groupNameClean = $m[1];
                        }
                        // Search by distinguishedName directly (faster, exact match)
                        $escapedGrpDn = ldap_escape_filter_value($groupName);
                        $grpFilter = "(&(objectClass=group)(distinguishedName={$escapedGrpDn}))";
                    } else {
                        $escapedGrp = ldap_escape_filter_value($groupName);
                        $grpFilter = "(&(objectClass=group)(|(name={$escapedGrp})(samAccountName={$escapedGrp})))";
                    }

                    $grpSearch = @ldap_search($connection, $baseDn, $grpFilter, ['dn', 'name'], 0, 1);
                    if ($grpSearch !== false) {
                        $grpRaw = ldap_get_entries($connection, $grpSearch);
                        if ((int) ($grpRaw['count'] ?? 0) > 0) {
                            $groupDnToFind = $grpRaw[0]['dn'];
                            $sourceGroupName = $grpRaw[0]['name'][0] ?? $groupNameClean;
                        }
                    }

                    // Fallback: search by name/CN if DN search failed
                    if (!$groupDnToFind && strpos($groupName, '=') !== false) {
                        $escapedName = ldap_escape_filter_value($groupNameClean);
                        $grpFilter = "(&(objectClass=group)(|(name={$escapedName})(samAccountName={$escapedName})))";
                        $grpSearch = @ldap_search($connection, $baseDn, $grpFilter, ['dn', 'name'], 0, 1);
                        if ($grpSearch !== false) {
                            $grpRaw = ldap_get_entries($connection, $grpSearch);
                            if ((int) ($grpRaw['count'] ?? 0) > 0) {
                                $groupDnToFind = $grpRaw[0]['dn'];
                                $sourceGroupName = $grpRaw[0]['name'][0] ?? $groupNameClean;
                            }
                        }
                    }

                    if ($groupDnToFind) {
                        $escapedGroupDn = ldap_escape_filter_value($groupDnToFind);
                        // Use LDAP_MATCHING_RULE_IN_CHAIN for recursive (nested) group membership
                        $filter = "(&(objectCategory=person)(objectClass=user)(memberOf:1.2.840.113556.1.4.1941:={$escapedGroupDn}))";
                    } else {
                        return ldap_json_script_result([
                            'success' => false,
                            'message' => "ERROR: Group '{$groupNameClean}' not found in Active Directory.",
                            'totalUsers' => 0,
                            'selectedOU' => $ouName,
                            'selectedGroup' => $groupName,
                            'csvContent' => [],
                        ], false, 1);
                    }
                    $targetUserLog = $sourceGroupName ?? $groupNameClean;
                }

                // Pre-fetch admin group DNs
                $eaGroupDn = null; $daGroupDn = null; $admGroupDn = null;
                foreach (['Enterprise Admins', 'Domain Admins', 'Administrators'] as $gName) {
                    $s = @ldap_search($connection, $baseDn, "(&(objectClass=group)(name={$gName}))", ['dn'], 0, 1);
                    if ($s !== false) {
                        $r = ldap_get_entries($connection, $s);
                        if ((int) ($r['count'] ?? 0) > 0) {
                            if ($gName === 'Enterprise Admins') { $eaGroupDn = $r[0]['dn']; }
                            elseif ($gName === 'Domain Admins') { $daGroupDn = $r[0]['dn']; }
                            else { $admGroupDn = $r[0]['dn']; }
                        }
                    }
                }

                // Query users
                $attributes = ['dn', 'samaccountname', 'displayname', 'useraccountcontrol',
                               'memberof', 'whencreated', 'lastlogontimestamp', 'description'];
                $allEntries = ldap_paged_search($connection, $searchBase, $filter, $attributes, 500);

                $csvRows = [];
                $csvHeaders = ['Source OU', 'Source Group', 'User ID', 'Display Name',
                               'AD User STS', 'Last 60 Days', 'User Created Date',
                               'Enterprise Admin', 'Domain Admin', 'Local Admin',
                               'Description', 'Member Of', 'OU Location'];

                foreach ($allEntries as $entry) {
                    $totalUsers++;
                    $dn = $entry['dn'] ?? '';
                    $sam = $entry['samaccountname'][0] ?? '';
                    $disp = $entry['displayname'][0] ?? $sam;
                    $uac = (int) ($entry['useraccountcontrol'][0] ?? 0);
                    $isEnabled = ($uac & 2) === 0;
                    $memberOfRaw = $entry['memberof'] ?? [];

                    $adUserStatus = $isEnabled ? 'Enabled' : 'Disabled';
                    if ($isEnabled) { $enabledUsers++; } else { $disabledUsers++; }

                    // Admin group checks
                    $isEA = false; $isDA = false; $isAdm = false;
                    foreach ((array) $memberOfRaw as $m) {
                        if ($eaGroupDn && stripos($m, $eaGroupDn) !== false) { $isEA = true; }
                        if ($daGroupDn && stripos($m, $daGroupDn) !== false) { $isDA = true; }
                        if ($admGroupDn && stripos($m, $admGroupDn) !== false) { $isAdm = true; }
                    }
                    if ($isEA) { $enterpriseAdminsCount++; }
                    if ($isDA) { $domainAdminsCount++; }
                    if ($isAdm) { $administratorsCount++; }

                    // Last logon
                    $userActivityStatus = 'N/A';
                    if (!empty($entry['lastlogontimestamp'][0])) {
                        $fileTime = (int) $entry['lastlogontimestamp'][0];
                        $windowsEpoch = new DateTime('1601-01-01 00:00:00');
                        $unixTs = $windowsEpoch->getTimestamp() + (int)($fileTime / 10000000);
                        if ($unixTs > 0) {
                            $logonDate = new DateTime("@{$unixTs}");
                            $daysSince = (int) $logonDate->diff(new DateTime())->days;
                            if ($daysSince <= 60) { $userActivityStatus = 'Active'; $activeUsers60Days++; }
                            else { $userActivityStatus = 'Inactive'; $inactiveUsers60Days++; }
                        }
                    }

                    // WhenCreated
                    $whenCreated = '';
                    if (!empty($entry['whencreated'][0])) {
                        try { $whenCreated = (new DateTime($entry['whencreated'][0]))->format('Y-m-d H:i:s'); } catch (\Throwable $e) {}
                    }

                    // OU path from DN
                    $ouPath = '';
                    $ouParts = [];
                    foreach (explode(',', $dn) as $p) {
                        if (stripos($p, 'OU=') === 0) { $ouParts[] = substr($p, 3); }
                    }
                    if (!empty($ouParts)) { $ouPath = implode('/', array_reverse($ouParts)); }

                    // MemberOf (friendly names)
                    $memberOfFriendly = [];
                    foreach ((array) $memberOfRaw as $m) {
                        if (preg_match('/^CN=([^,]+)/i', $m, $cnMatch)) { $memberOfFriendly[] = $cnMatch[1]; }
                    }
                    $memberOfStr = implode('; ', $memberOfFriendly);
                    $desc = $entry['description'][0] ?? '';

                    $row = [$sourceOuName ?? '', $sourceGroupName ?? '', $sam, $disp,
                            $adUserStatus, $userActivityStatus, $whenCreated,
                            $isEA ? 'True' : 'False', $isDA ? 'True' : 'False', $isAdm ? 'True' : 'False',
                            $desc, $memberOfStr, $ouPath];
                    $escapedRow = array_map(function($v) { return '"' . str_replace('"', '""', (string) $v) . '"'; }, $row);
                    $csvRows[] = implode(',', $escapedRow);
                }

                $escapedHeaders = array_map(function($h) { return '"' . str_replace('"', '""', $h) . '"'; }, $csvHeaders);
                $csvContent = array_merge([implode(',', $escapedHeaders)], $csvRows);

                $logOperation = $isGroupExport ? 'export_group_users' : 'export_ad_users';
                $summaryParts = ["Total Processed: {$totalUsers}",
                    "Enabled: {$enabledUsers}", "Disabled: {$disabledUsers}",
                    "Enterprise Admins: {$enterpriseAdminsCount}",
                    "Domain Admins: {$domainAdminsCount}",
                    "Administrators: {$administratorsCount}",
                    "Active (60 days): {$activeUsers60Days}",
                    "Inactive (60+ days): {$inactiveUsers60Days}"];
                $summaryMessage = "Summary: " . implode(', ', $summaryParts);
                ldap_write_script_log($logOperation, $targetUserLog, true, $summaryMessage, $executedBy);

                return ldap_json_script_result([
                    'success' => true,
                    'message' => "SUCCESS: User export data generated.\n\n{$summaryMessage}",
                    'totalUsers' => $totalUsers,
                    'selectedOU' => $ouName,
                    'selectedGroup' => $groupName,
                    'csvContent' => $csvContent,
                ], true, 0);

            } catch (\Throwable $e) {
                $errorMsg = "Detailed error: " . $e->getMessage();
                $logOp = $isGroupExport ? 'export_group_users' : 'export_ad_users';
                ldap_write_script_log($logOp, $targetUserLog, false, $errorMsg, $executedBy);

                return ldap_json_script_result([
                    'success' => false,
                    'message' => "ERROR: {$errorMsg}",
                    'totalUsers' => 0,
                    'selectedOU' => $ouName,
                    'selectedGroup' => $groupName,
                    'csvContent' => [],
                ], false, 1);
            }
        });
    }
}

if (!function_exists('ldap_hub_user_report')) {
    function ldap_hub_user_report(array $params, string $executedBy): array
    {
        $status = trim((string) ($params['Status'] ?? 'inactive'));
        $days = max(1, (int) ($params['Days'] ?? 30));
        $daysInt = $days;

        return ldap_run_with_connection(function ($connection, $config) use ($status, $daysInt, $executedBy) {
            $baseDn = ldap_search_base_dn($config);
            if ($baseDn === '') {
                return ldap_json_script_result([
                    'success' => false, 'message' => 'LDAP base DN is not configured.',
                    'users' => [],
                ], false, 1);
            }

            try {
                $dateThreshold = (new DateTime())->sub(new DateInterval("P{$daysInt}D"));
                $windowsEpoch = new DateTime('1601-01-01 00:00:00');
                $thresholdFileTime = (int) (($dateThreshold->getTimestamp() - $windowsEpoch->getTimestamp()) * 10000000);

                // Build filter based on status
                $filter = '';
                if ($status === 'disabled') {
                    $filter = '(&(objectCategory=person)(objectClass=user)(userAccountControl:1.2.840.113556.1.4.803:=2))';
                } elseif ($status === 'inactive') {
                    $filter = "(&(objectCategory=person)(objectClass=user)(!(userAccountControl:1.2.840.113556.1.4.803:=2))(lastLogonTimestamp<={$thresholdFileTime}))";
                } else {
                    $filter = '(&(objectCategory=person)(objectClass=user)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))';
                }

                $attributes = ['samaccountname', 'displayname', 'distinguishedname', 'lastlogontimestamp'];
                $allEntries = ldap_paged_search($connection, $baseDn, $filter, $attributes, 1000);

                $users = [];
                foreach ($allEntries as $entry) {
                    $sam = $entry['samaccountname'][0] ?? '';
                    $disp = $entry['displayname'][0] ?? '';
                    $dn = $entry['dn'] ?? '';

                    // Last logon
                    $logonDate = null;
                    if (!empty($entry['lastlogontimestamp'][0])) {
                        $fileTime = (int) $entry['lastlogontimestamp'][0];
                        $unixTs = $windowsEpoch->getTimestamp() + (int)($fileTime / 10000000);
                        if ($unixTs > 0) {
                            $logonDate = new DateTime("@{$unixTs}");
                        }
                    }

                    // Filter for 'active' status
                    if ($status === 'active') {
                        if ($logonDate === null || $logonDate < $dateThreshold) {
                            continue;
                        }
                    }

                    // OU path from DN
                    $ouPath = 'N/A';
                    $ouParts = [];
                    foreach (explode(',', $dn) as $p) {
                        if (stripos($p, 'OU=') === 0) { $ouParts[] = substr($p, 3); }
                    }
                    if (!empty($ouParts)) { $ouPath = implode(' > ', array_reverse($ouParts)); }

                    $users[] = [
                        'SamAccountName' => $sam,
                        'DisplayName'    => $disp,
                        'LastLogonDate'  => $logonDate ? $logonDate->format('Y-m-d H:i') : 'Never',
                        'Enabled'        => ($status !== 'disabled'),
                        'OU'             => $ouPath,
                    ];
                }

                return ldap_json_script_result([
                    'success' => true,
                    'users' => $users,
                ], true, 0);

            } catch (\Throwable $e) {
                return ldap_json_script_result([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'users' => [],
                ], false, 1);
            }
        });
    }
}

if (!function_exists('ldap_hub_hrms_ad_report')) {
    function ldap_hub_hrms_ad_report(array $params, string $executedBy): array
    {
        $rawIds = trim((string) ($params['Usernames'] ?? ''));
        if ($rawIds === '') {
            return ldap_json_script_result([
                'success' => false,
                'message' => 'No employee IDs provided.',
                'results' => [],
            ], false, 1);
        }

        $empIds = array_filter(array_map('trim', preg_split('/[\s,;]+/', $rawIds)));
        $isSingleUser = count($empIds) === 1;

        $hrmsApiBase = 'https://whrmsapi.waltonbd.com/info/emp_info.php';

        $callHrmsApi = function($id) use ($hrmsApiBase) {
            $apiUrl = $hrmsApiBase . '?emp_id=' . urlencode($id);
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSLVERSION => 6,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_USERAGENT => 'AccessPilot-HRMS/4.0',
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);
            if ($resp === false || $resp === '') { return null; }
            $data = json_decode($resp, true);
            if (is_array($data) && !empty($data['EMP_ID'])) { return $data; }
            return null;
        };

        return ldap_run_with_connection(function ($connection, $config) use ($empIds, $executedBy, $isSingleUser, $callHrmsApi) {
            $baseDn = ldap_search_base_dn($config);
            if ($baseDn === '') {
                return ldap_json_script_result([
                    'success' => false,
                    'message' => 'Base DN not configured.',
                    'results' => [],
                ], false, 1);
            }

            $domainName = '';
            $baseDnParts = array_filter(explode(',', $config['base_dn'] ?? ''), function ($p) {
                return stripos(trim($p), 'DC=') === 0;
            });
            if (!empty($baseDnParts)) {
                $domainName = implode('.', array_map(function ($p) { return substr(trim($p), 3); }, $baseDnParts));
            }

            $results = [];
            $foundBoth = 0; $foundAdOnly = 0; $foundHrmsOnly = 0; $totalErrors = 0;

            foreach ($empIds as $input) {
                $input = trim($input);
                if ($input === '') { continue; }

                $row = [
                    'HRMS_ID'     => $input,
                    'Logon_ID'    => 'N/A',
                    'EMP_NAME'    => 'N/A',
                    'AD_Name'     => 'N/A',
                    'DESIGNATION' => 'N/A',
                    'HRMS_STATUS' => 'N/A',
                    'AD_STATUS'   => 'N/A',
                    'Domain'      => $domainName,
                    'Find_Status' => 'Not Found',
                ];
                $empName = 'N/A'; $hrmsStatus = 'N/A'; $hrmsCode = ''; $designation = 'N/A';
                $adSam = ''; $adDisplay = ''; $adEnabled = false; $adFound = false;

                try {
                    // --- Step 1: HRMS API with input ---
                    $hrmsData = $callHrmsApi($input);
                    if ($hrmsData) {
                        $empName = $hrmsData['EMP_NAME'] ?? 'N/A';
                        $hrmsStatus = $hrmsData['EMP_STS'] ?? 'N/A';
                        $designation = $hrmsData['DESIGNATION'] ?? 'N/A';
                        $hrmsCode = $hrmsData['EMP_CODE'] ?? $hrmsData['EMP_ID'] ?? '';
                    }

                    // --- Step 2: Extract numeric part from input ---
                    preg_match('/(\d+)$/', $input, $m);
                    $inputNum = $m[1] ?? '';

                    // --- Step 3: Try HRMS with numeric part if input failed ---
                    if (!$hrmsData && $inputNum !== '' && $inputNum !== $input) {
                        $hrmsData2 = $callHrmsApi($inputNum);
                        if ($hrmsData2) {
                            $empName = $hrmsData2['EMP_NAME'] ?? 'N/A';
                            $hrmsStatus = $hrmsData2['EMP_STS'] ?? 'N/A';
                            $designation = $hrmsData2['DESIGNATION'] ?? 'N/A';
                            $hrmsCode = $hrmsData2['EMP_CODE'] ?? $hrmsData2['EMP_ID'] ?? '';
                            $hrmsData = $hrmsData2;
                        }
                    }

                    // --- Step 4: AD exact lookup ---
                    $empCode = $hrmsData ? ($hrmsData['EMP_CODE'] ?? $input) : $input;
                    $searchIds = array_unique(array_filter([$input, $empCode, $inputNum]));

                    foreach ($searchIds as $sid) {
                        if ($sid === '') { continue; }
                        $escaped = ldap_escape_filter_value($sid);
                        $filter = "(&(objectCategory=person)(objectClass=user)(samAccountName={$escaped}))";
                        $search = @ldap_search($connection, $baseDn, $filter, ['samaccountname', 'displayname', 'useraccountcontrol', 'employeEid']);
                        if ($search === false) { continue; }
                        $raw = ldap_get_entries($connection, $search);
                        $count = (int) ($raw['count'] ?? 0);
                        if ($count > 0) {
                            $adSam = $raw[0]['samaccountname'][0] ?? $sid;
                            $adDisplay = $raw[0]['displayname'][0] ?? 'N/A';
                            $uac = (int) ($raw[0]['useraccountcontrol'][0] ?? 0);
                            $adEnabled = ($uac & 2) === 0;
                            $adFound = true;

                            // If HRMS still not found, try employeeId from AD
                            if (!$hrmsData) {
                                $adEmpId = $raw[0]['employeEid'][0] ?? '';
                                if ($adEmpId !== '') {
                                    $hrmsData3 = $callHrmsApi($adEmpId);
                                    if ($hrmsData3) {
                                        $empName = $hrmsData3['EMP_NAME'] ?? 'N/A';
                                        $hrmsStatus = $hrmsData3['EMP_STS'] ?? 'N/A';
                                        $designation = $hrmsData3['DESIGNATION'] ?? 'N/A';
                                        $hrmsCode = $hrmsData3['EMP_CODE'] ?? $hrmsData3['EMP_ID'] ?? '';
                                    }
                                }
                            }
                            break;
                        }
                    }

                    // --- Step 5: Wildcard fallback ---
                    if (!$adFound && $inputNum !== '') {
                        $escaped = ldap_escape_filter_value($inputNum);
                        $filter = "(&(objectCategory=person)(objectClass=user)(samAccountName=*{$escaped}*))";
                        $wsearch = @ldap_search($connection, $baseDn, $filter, ['samaccountname', 'displayname', 'useraccountcontrol'], 0, 5);
                        if ($wsearch !== false) {
                            $wraw = ldap_get_entries($connection, $wsearch);
                            $wcount = (int) ($wraw['count'] ?? 0);
                            if ($wcount > 0) {
                                $adSam = $wraw[0]['samaccountname'][0] ?? $input;
                                $adDisplay = $wraw[0]['displayname'][0] ?? 'N/A';
                                $uac = (int) ($wraw[0]['useraccountcontrol'][0] ?? 0);
                                $adEnabled = ($uac & 2) === 0;
                                $adFound = true;
                            }
                        }
                    }

                    // --- Populate row & set Find_Status ---
                    if ($hrmsCode !== '') { $row['HRMS_ID'] = $hrmsCode; }
                    $row['EMP_NAME'] = $empName;
                    $row['DESIGNATION'] = $designation;
                    $row['HRMS_STATUS'] = $hrmsStatus;
                    $hrmsFound = $empName !== 'N/A';

                    if ($adFound) {
                        $row['Logon_ID'] = $adSam;
                        $row['AD_Name'] = $adDisplay;
                        $row['AD_STATUS'] = $adEnabled ? 'Enabled' : 'Disabled';
                        if ($hrmsFound) {
                            $row['Find_Status'] = 'Found';
                            $foundBoth++;
                        } else {
                            $row['Find_Status'] = 'AD Only';
                            $foundAdOnly++;
                        }
                    } elseif ($hrmsFound) {
                        $row['AD_STATUS'] = 'Not Created';
                        $row['Find_Status'] = 'HRMS Only';
                        $foundHrmsOnly++;
                    } else {
                        $row['Find_Status'] = 'Not Found';
                        $totalErrors++;
                    }

                } catch (\Throwable $e) {
                    $totalErrors++;
                    $row['Find_Status'] = 'Error';
                }

                $results[] = $row;
            }

            $totalProcessed = count($results);
            $parts = ["Processed: {$totalProcessed}"];
            if ($foundBoth > 0) { $parts[] = "Found: {$foundBoth}"; }
            if ($foundAdOnly > 0) { $parts[] = "AD Only: {$foundAdOnly}"; }
            if ($foundHrmsOnly > 0) { $parts[] = "HRMS Only: {$foundHrmsOnly}"; }
            if ($totalErrors > 0) { $parts[] = "Errors: {$totalErrors}"; }
            $summary = implode(', ', $parts);

            $isSuccess = $totalErrors === 0;
            $targetUser = $isSingleUser ? $empIds[0] : 'Multiple';
            ldap_write_script_log('hrms_ad_report', $targetUser, $isSuccess, "Report: {$summary}", $executedBy);

            return ldap_json_script_result([
                'success' => $isSuccess,
                'message' => ($isSuccess ? 'SUCCESS' : 'ERROR') . ": HRMS AD report completed.\n\n{$summary}",
                'results' => $results,
            ], $isSuccess, $isSuccess ? 0 : 1);
        });
    }
}

if (!function_exists('ldap_hub_health_check')) {
    function ldap_hub_health_check(array $params, string $executedBy): array
    {
        $results = [];
        $overallHealthy = true;

        $runCmd = function (string $cmd, int $timeout = 30): array {
            if (!function_exists('exec')) {
                return ['output' => ['exec() is disabled — shell commands unavailable'], 'exit_code' => -1];
            }
            $out = [];
            $code = -1;
            $old = set_time_limit($timeout + 5);
            exec($cmd . ' 2>&1', $out, $code);
            set_time_limit($old);
            return ['output' => $out, 'exit_code' => $code];
        };

        $add = function (string $test, string $status, string $detail) use (&$results, &$overallHealthy) {
            $results[] = ['test' => $test, 'status' => $status, 'detail' => $detail];
            if (in_array($status, ['FAIL', 'WARN'], true)) { $overallHealthy = false; }
        };

        $lines = function (array $out, int $n = 5): string {
            return implode(' | ', array_slice($out, 0, $n));
        };

        // --- 1. PHP ext-ldap ---
        $loaded = extension_loaded('ldap');
        $add('PHP ext-ldap', $loaded ? 'PASS' : 'FAIL', $loaded ? 'Loaded' : 'Not loaded');

        // --- 2. LDAP config ---
        $config = @ldap_read_config();
        $host = trim((string) ($config['host'] ?? ''));
        $port = (int) ($config['port'] ?? 389);
        $bindDn = trim((string) ($config['bind_dn'] ?? ''));
        $baseDn = trim((string) ($config['base_dn'] ?? ''));
        $hasPassword = function_exists('ldap_has_bind_password') ? ldap_has_bind_password() : !empty($config['bind_password']);

        $add('LDAP host', !empty($host) ? 'PASS' : 'FAIL', !empty($host) ? "{$host}:{$port}" : 'Not configured');
        $add('Base DN', !empty($baseDn) ? 'PASS' : 'FAIL', !empty($baseDn) ? $baseDn : 'Not configured');
        $add('Bind DN', !empty($bindDn) ? 'PASS' : 'FAIL', !empty($bindDn) ? $bindDn : 'Not configured');
        $add('Bind password', $hasPassword ? 'PASS' : 'FAIL', $hasPassword ? 'Stored in vault' : 'Not stored');

        // --- 3. Host reachability ---
        if (!empty($host)) {
            $sock = @fsockopen($host, $port, $eno, $estr, 5);
            if (is_resource($sock)) { fclose($sock); $add('Port reachable', 'PASS', "{$host}:{$port} open"); }
            else { $add('Port reachable', 'FAIL', "{$host}:{$port} — {$estr}"); }
        }

        // --- 4. LDAP bind + root DSE ---
        $domainFuncLevel = 'unknown';
        $namingContext = '';
        if ($loaded && !empty($host) && !empty($bindDn) && $hasPassword) {
            try {
                $b = @ldap_connect_and_bind();
                if (!empty($b['connection'])) {
                    $c = $b['connection'];
                    $dse = @ldap_read($c, '', 'objectClass=*', ['defaultNamingContext', 'domainFunctionality', 'currentTime'], 0, 1);
                    if ($dse !== false) {
                        $e = @ldap_get_entries($c, $dse);
                        $nc = $e[0]['defaultnamingcontext'][0] ?? 'unknown';
                        $fl = $e[0]['domainfunctionality'][0] ?? 'unknown';
                        $domainFuncLevel = $fl;
                        $namingContext = $nc;
                        $add('LDAP bind + root DSE', 'PASS', "Bound to {$nc} (func level: {$fl})");
                    } else {
                        $add('LDAP bind', 'WARN', 'Bind OK but root DSE query failed');
                    }
                    @ldap_unbind($c);
                } else {
                    $add('LDAP bind', 'FAIL', $b['message'] ?? 'Bind rejected');
                }
            } catch (\Throwable $e) {
                $add('LDAP bind', 'FAIL', $e->getMessage());
            }
        } else {
            $add('LDAP bind', 'SKIP', 'Missing LDAP config');
        }

        // --- 5. Directory search ---
        $totalUsers = 0;
        if ($loaded && !empty($host) && !empty($bindDn) && $hasPassword && !empty($baseDn)) {
            try {
                $b = @ldap_connect_and_bind();
                if (!empty($b['connection'])) {
                    $c = $b['connection'];
                    $s = @ldap_read($c, $baseDn, 'objectClass=*', ['dn'], 0, 1);
                    if ($s !== false && @ldap_count_entries($c, $s) > 0) {
                        $add('Directory read', 'PASS', "Base DN {$baseDn} is accessible");
                        $usr = @ldap_search($c, $baseDn, '(&(objectCategory=person)(objectClass=user))', ['samaccountname'], 0, 0, 0);
                        if ($usr !== false) { $totalUsers = @ldap_count_entries($c, $usr); }
                    } else {
                        $add('Directory read', 'WARN', "Base DN {$baseDn} returned no entries");
                    }
                    @ldap_unbind($c);
                }
            } catch (\Throwable $e) {
                $add('Directory read', 'WARN', $e->getMessage());
            }
        }

        // --- 6. nltest — DC discovery ---
        $domain = $config['domain'] ?? '';
        if (empty($domain)) {
            $parts = array_filter(explode(',', str_replace('.', ',', $baseDn)));
            foreach ($parts as $p) {
                if (stripos($p, 'DC=') === 0) { $domain = substr($p, 3); break; }
            }
        }
        $dcList = [];
        $dcCount = 0;
        if (!empty($domain)) {
            $r = $runCmd("nltest /dclist:{$domain}");
            $ok = $r['exit_code'] === 0 && !empty($r['output']);
            foreach ($r['output'] as $l) {
                if (preg_match('/\\\\\\(\S+)/', $l, $m)) { $dcList[] = strtolower($m[1]); }
            }
            $dcCount = count($dcList);
            $add('DC discovery (nltest)', $ok && $dcCount > 0 ? 'PASS' : 'FAIL', $ok ? "{$dcCount} DC(s) found" : 'No DCs found');
        }

        // --- 7. dcdiag — basic connectivity ---
        $r = $runCmd('dcdiag /test:connectivity /q', 60);
        $hasDcdiagErrors = false;
        foreach ($r['output'] as $l) {
            if (stripos($l, 'failed') !== false || stripos($l, 'error') !== false) { $hasDcdiagErrors = true; break; }
        }
        $add('dcdiag connectivity', (!$hasDcdiagErrors && $r['exit_code'] === 0) ? 'PASS' : 'WARN',
            $hasDcdiagErrors ? 'Some tests reported issues' : 'All DCs passed basic connectivity');

        // --- 8. repadmin — replication summary ---
        $r = $runCmd('repadmin /replsummary', 120);
        $replFails = 0;
        foreach ($r['output'] as $l) {
            if (preg_match('/^\s+(\S+)\s+.*?(\d+)\s*\/\s*(\d+)\s+(\S+)/', $l, $m)) {
                $replFails += (int) $m[2];
            }
        }
        $add('Replication (repadmin)', $replFails === 0 ? 'PASS' : ($replFails < 5 ? 'WARN' : 'FAIL'),
            $replFails === 0 ? 'All DCs replication healthy' : "{$replFails} replication failure(s)");

        // --- 9. Time sync (w32tm) ---
        $r = $runCmd('w32tm /query /status');
        $timeOk = false;
        $timeDetail = '';
        foreach ($r['output'] as $l) {
            if (stripos($l, 'source') !== false) { $timeDetail = trim($l); }
            if (stripos($l, 'NTP') !== false || stripos($l, 'VM IC') !== false) { $timeOk = true; }
        }
        if (empty($timeDetail)) { $timeDetail = $lines($r['output'], 2); }
        $add('Time sync (w32tm)', $timeOk ? 'PASS' : 'WARN', $timeOk ? $timeDetail : 'No NTP source detected');

        // --- 10. DNS resolution ---
        if (!empty($host)) {
            $r = $runCmd("nslookup {$host} 2>&1", 15);
            $dnsOk = $r['exit_code'] === 0 && stripos(implode("\n", $r['output'] ?? []), 'Address') !== false;
            $add('DNS resolution', $dnsOk ? 'PASS' : 'FAIL', $dnsOk ? "{$host} resolves" : "{$host} did not resolve");
        }

        // --- Count ---
        $successCount = 0; $failCount = 0; $skipCount = 0;
        foreach ($results as $r) {
            if ($r['status'] === 'PASS') { $successCount++; }
            elseif (in_array($r['status'], ['FAIL', 'WARN'], true)) { $failCount++; }
            elseif ($r['status'] === 'SKIP') { $skipCount++; }
        }

        $badge = $overallHealthy
            ? "SUCCESS: Domain health check passed — all tests OK."
            : "WARNING: {$failCount} check(s) need attention.";

        $reportHtml = '';
        $outputReportPath = (string) ($params['OutputReportPath'] ?? '');
        if ($outputReportPath !== '') {
            $reportHtml = ldap_build_health_html_report([
                'results' => $results,
                'overallHealthy' => $overallHealthy,
                'successCount' => $successCount,
                'failCount' => $failCount,
                'skipCount' => $skipCount,
                'dcList' => $dcList,
                'dcCount' => $dcCount,
                'totalUsers' => $totalUsers,
                'domain' => $domain,
                'namingContext' => $namingContext,
                'domainFuncLevel' => $domainFuncLevel,
                'host' => $host,
                'port' => $port,
                'baseDn' => $baseDn,
                'executedBy' => $executedBy,
            ]);
            @file_put_contents($outputReportPath, $reportHtml);
        }

        return ldap_json_script_result([
            'success' => $overallHealthy,
            'message' => ldap_feedback_message($badge, count($results), $successCount, $failCount, $skipCount),
            'results' => $results,
            'overallHealthy' => $overallHealthy,
            'successCount' => $successCount,
            'failCount' => $failCount,
            'skipCount' => $skipCount,
        ], $overallHealthy, $overallHealthy ? 0 : 1);
    }
}

if (!function_exists('ldap_health_status_label')) {
    function ldap_health_status_label(string $status): string
    {
        $map = ['PASS' => 'Passed', 'FAIL' => 'Failed', 'WARN' => 'Warning', 'SKIP' => 'Skipped', 'INFO' => 'Info'];
        return $map[$status] ?? $status;
    }
}

if (!function_exists('ldap_health_status_badge')) {
    function ldap_health_status_badge(string $status): string
    {
        if ($status === 'PASS') return 'bg-success text-white';
        if ($status === 'FAIL') return 'bg-danger text-white';
        if ($status === 'WARN') return 'bg-warning text-dark';
        return 'bg-secondary text-white';
    }
}

if (!function_exists('ldap_build_health_html_report')) {
    function ldap_build_health_html_report(array $data): string
    {
        $results = $data['results'] ?? [];
        $overallHealthy = ($data['overallHealthy'] ?? false) === true;
        $successCount = (int) ($data['successCount'] ?? 0);
        $failCount = (int) ($data['failCount'] ?? 0);
        $skipCount = (int) ($data['skipCount'] ?? 0);
        $dcList = $data['dcList'] ?? [];
        $dcCount = (int) ($data['dcCount'] ?? 0);
        $totalUsers = (int) ($data['totalUsers'] ?? 0);
        $domain = $data['domain'] ?? 'N/A';
        $namingContext = $data['namingContext'] ?? 'N/A';
        $domainFuncLevel = $data['domainFuncLevel'] ?? 'N/A';
        $host = $data['host'] ?? 'N/A';
        $port = $data['port'] ?? 'N/A';
        $baseDn = $data['baseDn'] ?? 'N/A';
        $executedBy = $data['executedBy'] ?? 'System';
        $now = date('Y-m-d h:i:s A');
        $generatedBy = $executedBy ?: 'System';

        $hasFail = false;
        foreach ($results as $r) { if ($r['status'] === 'FAIL') { $hasFail = true; break; } }
        $overallClass = $overallHealthy ? 'bg-success text-white' : ($hasFail ? 'bg-danger text-white' : 'bg-warning text-dark');
        $overallBadge = $overallHealthy ? 'Healthy' : ($hasFail ? 'Failed' : 'Warning');
        $summaryText = $overallHealthy
            ? "Executive Summary: HEALTHY — All {$successCount} checks passed. The domain infrastructure is operating normally."
            : "Executive Summary: {$failCount} check(s) need attention out of " . count($results) . " total checks. Review the details below.";

        $findings = [];
        foreach ($results as $r) {
            if ($r['status'] === 'PASS') continue;
            $findings[] = "{$r['test']}: {$r['status']} — {$r['detail']}";
        }
        if (empty($findings)) {
            $findings[] = 'No critical issues found. All systems operational.';
        }

        $rows = '';
        foreach ($results as $r) {
            $label = ldap_health_status_label($r['status']);
            $badge = ldap_health_status_badge($r['status']);
            $test = htmlspecialchars($r['test'] ?? '');
            $detail = htmlspecialchars($r['detail'] ?? '');
            $rows .= "<tr><td style='padding:8px 12px;border:1px solid #dee2e6;font-weight:600;'>{$test}</td>"
                   . "<td style='padding:8px 12px;border:1px solid #dee2e6;text-align:center;'><span class='badge {$badge}' style='padding:4px 10px;border-radius:4px;font-size:11px;'>{$label}</span></td>"
                   . "<td style='padding:8px 12px;border:1px solid #dee2e6;color:#555;'>{$detail}</td></tr>";
        }

        $dcInfoRows = '';
        if (!empty($dcList)) {
            foreach ($dcList as $dc) {
                $dcInfoRows .= "<span class='badge bg-info text-white me-1' style='font-size:11px;'>{$dc}</span>";
            }
        } else {
            $dcInfoRows = '<span class="text-muted">N/A (LDAP mode)</span>';
        }

        $keyFindingsHtml = '';
        foreach ($findings as $f) {
            $keyFindingsHtml .= '<li>' . htmlspecialchars($f) . '</li>';
        }

        $bootstrapCss = @file_get_contents(__DIR__ . '/../../../public/vendor/bootstrap/bootstrap.min.css');
        if ($bootstrapCss === false) { $bootstrapCss = ''; }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Domain Health Check Report</title>
<style>
{$bootstrapCss}

body { font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; font-size:10pt; background:#f8f9fa; padding:20px; }
h1 { font-size:20px; } h2 { font-size:16px; } h3 { font-size:14px; }
table { border-collapse:collapse; font-size:10pt; width:100%; }
th { border:1px solid #dee2e6; background:#f2f2f2; padding:10px; color:#000; font-weight:600; }
td { border:1px solid #dee2e6; padding:10px; }
.container-fluid { max-width:1400px; margin:0 auto; }
.watermark { position:fixed; bottom:20px; right:20px; opacity:0.1; z-index:9999; pointer-events:none; width:150px; height:auto; }
@media print { body { -webkit-print-color-adjust:exact; print-color-adjust:exact; } }
</style>
</head>
<body>
<div class="container-fluid">
<div class="row align-items-center mb-3 border-bottom pb-3">
<div class="col"><h1 class="mb-0">Domain Controller Health Check Report</h1>
<p class="text-muted mb-0">Organization: {$domain} | Generated: {$now} | By: {$generatedBy}</p></div>
</div>

<div class="card mb-4 border-0 shadow-sm">
<div class="card-header {$overallClass}"><h5 class="mb-0"><i class="bi bi-shield-fill-check"></i> Executive Summary</h5></div>
<div class="card-body">
<h2>{$summaryText}</h2>
<ul>{$keyFindingsHtml}</ul>
</div>
</div>

<div class="row g-2 mb-4">
<div class="col"><div class="card text-white bg-success text-center h-100"><div class="card-body"><h6><i class="bi bi-check-circle-fill"></i> Passed</h6><p class="display-6 mb-0">{$successCount}</p></div></div></div>
<div class="col"><div class="card text-white bg-warning text-center h-100"><div class="card-body"><h6><i class="bi bi-exclamation-triangle-fill"></i> Warning</h6><p class="display-6 mb-0">{$failCount}</p></div></div></div>
<div class="col"><div class="card text-white bg-danger text-center h-100"><div class="card-body"><h6><i class="bi bi-x-circle-fill"></i> Failed</h6><p class="display-6 mb-0">{$failCount}</p></div></div></div>
<div class="col"><div class="card text-white bg-info text-center h-100"><div class="card-body"><h6><i class="bi bi-server"></i> DCs Found</h6><p class="display-6 mb-0">{$dcCount}</p></div></div></div>
<div class="col"><div class="card text-white bg-primary text-center h-100"><div class="card-body"><h6><i class="bi bi-people-fill"></i> Total Users</h6><p class="display-6 mb-0">{$totalUsers}</p></div></div></div>
</div>

<div class="card mb-4 shadow-sm">
<div class="card-header bg-secondary text-white"><h5 class="mb-0"><i class="bi bi-info-circle-fill"></i> Domain Information</h5></div>
<div class="card-body">
<div class="row">
<div class="col-md-4"><p class="mb-1"><strong>Domain:</strong> {$domain}</p></div>
<div class="col-md-4"><p class="mb-1"><strong>Naming Context:</strong> {$namingContext}</p></div>
<div class="col-md-4"><p class="mb-1"><strong>Func Level:</strong> {$domainFuncLevel}</p></div>
<div class="col-md-4"><p class="mb-1"><strong>LDAP Server:</strong> {$host}:{$port}</p></div>
<div class="col-md-4"><p class="mb-1"><strong>Base DN:</strong> {$baseDn}</p></div>
<div class="col-md-4"><p class="mb-1"><strong>DCs:</strong> {$dcInfoRows}</p></div>
</div>
</div>
</div>

<div class="card mb-4 shadow-sm">
<div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="bi bi-list-check"></i> Test Results</h5></div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-striped table-bordered table-hover mb-0">
<thead class="table-dark"><tr><th style='width:30%;'>Test Name</th><th style='width:12%;'>Status</th><th>Detail</th></tr></thead>
<tbody>{$rows}</tbody>
</table>
</div>
</div>
</div>

<div class="card shadow-sm">
<div class="card-header bg-light"><h5 class="mb-0"><i class="bi bi-exclamation-diamond-fill text-warning"></i> Recommendations</h5></div>
<div class="card-body">
<p class="mb-1">Review the test results above. Any <span class="badge bg-danger">Failed</span> or <span class="badge bg-warning text-dark">Warning</span> items should be investigated promptly.</p>
<p class="mb-0 text-muted" style="font-size:11px;">Note: Some checks requiring Active Directory module (user counts, group memberships, GPOs, services, backups) are unavailable in LDAP mode. Switch to PowerShell backend for full diagnostics.</p>
</div>
</div>

<div class="text-center text-muted mt-4 pt-3 border-top" style="font-size:11px;">
Generated by {$generatedBy} on {$now} | LDAP Health Check
</div>
</div>
</body>
</html>
HTML;
    }
}
