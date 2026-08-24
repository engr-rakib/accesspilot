<?php

require_once __DIR__ . '/../Support/ldap_helpers.php';
require_once __DIR__ . '/ldap_response_adapter.php';

if (!function_exists('ldap_user_lookup_entry')) {
    function ldap_user_lookup_entry($connection, string $baseDn, string $identity): ?array
    {
        $trimmed = trim($identity);
        if ($trimmed === '') {
            return null;
        }

        $attributes = [
            'samaccountname', 'displayname', 'distinguishedname', 'description', 'memberof', 'cn', 'employeeid', 'initials',
            'userprincipalname', 'useraccountcontrol', 'lockouttime', 'pwdlastset', 'accountexpires',
            'badpwdcount', 'badpasswordtime', 'logoncount', 'lastlogontimestamp', 'lastlogoff',
            'homedirectory', 'homedrive', 'profilepath', 'scriptpath',
            'whencreated', 'title', 'manager', 'company', 'telephonenumber', 'mail', 'department',
            'logonworkstation',
            'givenname', 'sn', 'physicaldeliveryofficename', 'wwwhomepage',
            'streetaddress', 'postofficebox', 'postalcode', 'l', 'st', 'co',
            // Exchange / Mailbox attributes
            'proxyaddresses', 'mailnickname', 'msexchmailboxguid',
            'msexchhomeservername', 'msexchrecipienttypedetails',
            'msexchuseraccountcontrol', 'msexchprovisioningflags',
            'msexchwhenmailboxcreated', 'msexcharchivemailboxguid', 'msexcharchivename',
            'msexchmailboxdeliveryinfo', 'msexchmailboxsecuritytimestamp',
            'msexchelcmboxflags', 'msexchrecipientdisplaytype', 'legacyexchangedn',
            'mdbusedefaults', 'mdbstoragequota', 'mdboverquotalimit', 'mdboverhardquotalimit',
        ];

        if (stripos($trimmed, '=') !== false) {
            $read = @ldap_read($connection, $trimmed, '(objectClass=user)', $attributes);
            if ($read !== false) {
                $raw = ldap_get_entries($connection, $read);
                if (is_array($raw) && (int) ($raw['count'] ?? 0) > 0) {
                    return ldap_normalize_entry($raw[0]);
                }
            }
        }

        $escaped = ldap_escape_filter_value($trimmed);
        $filter = "(&(objectCategory=person)(objectClass=user)(|(sAMAccountName={$escaped})(userPrincipalName={$escaped})(mail={$escaped})(name={$escaped})))";
        $search = @ldap_search($connection, $baseDn, $filter, $attributes);
        if ($search === false) {
            throw new RuntimeException('LDAP user search failed: ' . ldap_error($connection));
        }

        $raw = ldap_get_entries($connection, $search);
        if (!is_array($raw) || (int) ($raw['count'] ?? 0) < 1) {
            return null;
        }

        if ((int) $raw['count'] > 1) {
            foreach ($raw as $idx => $row) {
                if (!is_int($idx)) {
                    continue;
                }
                $entry = ldap_normalize_entry($row);
                if (strcasecmp(ldap_first_attr($entry, 'samaccountname', ''), $trimmed) === 0) {
                    return $entry;
                }
            }
        }

        return ldap_normalize_entry($raw[0]);
    }
}

if (!function_exists('ldap_get_last_logon_workstation')) {
    function ldap_get_last_logon_workstation(string $samAccountName, $connection = null): array
    {
        if ($samAccountName === '') { return []; }

        // Try local event log via PowerShell (5s timeout built into script)
        try {
            $psScript = __DIR__ . '/../../../scripts/powershell/get-last-logon-workstation.ps1';
            if (file_exists($psScript)) {
                $escaped = escapeshellarg($samAccountName);
                $nullDevice = stripos(PHP_OS, 'WIN') === 0 ? 'NUL' : '/dev/null';
                $cmd = "powershell -ExecutionPolicy Bypass -File " . escapeshellarg($psScript) . " -Username {$escaped} 2>{$nullDevice}";
                $output = trim((string) shell_exec($cmd));
                if ($output !== '' && $output !== '[]') {
                    $decoded = json_decode($output, true);
                    if (is_array($decoded) && !empty($decoded)) {
                        return $decoded;
                    }
                }
            }
        } catch (Throwable $e) { /* ignore */ }

        // Fallback: AD computer search by numeric ID
        if ($connection === null) { return []; }
        try {
            preg_match('/(\d+)$/', $samAccountName, $m);
            $numPart = $m[1] ?? '';
            if ($numPart === '') { return []; }
            $configPath = __DIR__ . '/../../../config/ldap/ldap_config.php';
            if (!file_exists($configPath)) { return []; }
            $ldapConfig = include $configPath;
            $baseDn = is_array($ldapConfig) ? ($ldapConfig['base_dn'] ?? '') : '';
            if ($baseDn === '') { $baseDn = ldap_search_base_dn([]); }
            if ($baseDn === '') { return []; }
            $escaped = ldap_escape_filter_value($numPart);
            $filter = "(&(objectCategory=computer)(name=*{$escaped}*))";
            $search = @ldap_search($connection, $baseDn, $filter, ['name'], 0, 20);
            if ($search === false) { return []; }
            $raw = ldap_get_entries($connection, $search);
            $count = (int) ($raw['count'] ?? 0);
            $computers = [];
            for ($i = 0; $i < $count; $i++) {
                $cn = $raw[$i]['name'][0] ?? '';
                if ($cn !== '') {
                    $computers[] = ['workstation' => $cn, 'time' => ''];
                }
            }
            return $computers;
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('ldap_user_repository_find')) {
    function ldap_user_repository_find(array $params, string $executedBy): array
    {
        $username = trim((string) ($params['Username'] ?? $params['username'] ?? ''));
        if ($username === '') {
            return ldap_json_script_result(['success' => false, 'message' => ldap_feedback_message('Username is required.', 1, 0, 1)], false, 1);
        }

        try {
            return ldap_run_with_connection(function ($connection, $config) use ($username) {
                $baseDn = ldap_search_base_dn($config);
                if ($baseDn === '') {
                    throw new RuntimeException('LDAP base DN is not configured.');
                }

                $entry = ldap_user_lookup_entry($connection, $baseDn, $username);
                if ($entry === null) {
                    preg_match('/(\d+)$/', $username, $m);
                    $numPart = $m[1] ?? '';
                    if ($numPart !== '') {
                        // First try prefix wildcard with full input (e.g. "C-13088" -> "*C-13088" matches "fokirC-13088")
                        $preEscaped = ldap_escape_filter_value($username);
                        $preFilter = "(&(objectCategory=person)(objectClass=user)(samAccountName=*{$preEscaped}))";
                        $wcAttrs = ['samaccountname', 'displayname', 'distinguishedname', 'description', 'memberof', 'cn', 'employeeid', 'initials',
                            'userprincipalname', 'useraccountcontrol', 'lockouttime', 'pwdlastset', 'accountexpires',
                            'badpwdcount', 'badpasswordtime', 'logoncount', 'lastlogontimestamp', 'lastlogoff',
                            'homedirectory', 'homedrive', 'profilepath', 'scriptpath',
                            'whencreated', 'title', 'manager', 'company', 'telephonenumber', 'mail', 'department',
                            'logonworkstation',
                            'givenname', 'sn', 'physicaldeliveryofficename', 'wwwhomepage',
                            'streetaddress', 'postofficebox', 'postalcode', 'l', 'st', 'co',
                            'proxyaddresses', 'mailnickname', 'msexchmailboxguid',
                            'msexchhomeservername', 'msexchrecipienttypedetails',
                            'msexchuseraccountcontrol', 'msexchprovisioningflags',
                            'msexchwhenmailboxcreated', 'msexcharchivemailboxguid', 'msexcharchivename',
                            'msexchmailboxdeliveryinfo', 'msexchmailboxsecuritytimestamp',
                            'msexchelcmboxflags', 'msexchrecipientdisplaytype', 'legacyexchangedn',
                            'mdbusedefaults', 'mdbstoragequota', 'mdboverquotalimit', 'mdboverhardquotalimit'];
                        $preSearch = @ldap_search($connection, $baseDn, $preFilter, $wcAttrs, 0, 5);
                        if ($preSearch !== false) {
                            $preRaw = ldap_get_entries($connection, $preSearch);
                            $preCount = is_array($preRaw) ? (int) ($preRaw['count'] ?? 0) : 0;
                            if ($preCount === 1) {
                                $entry = ldap_normalize_entry($preRaw[0]);
                            } elseif ($preCount > 1 && str_contains($username, '-')) {
                                // Only try suffix matching when input has a dash prefix (e.g. "C-13088")
                                $suffix = '-' . $numPart;
                                for ($j = 0; $j < $preCount; $j++) {
                                    $sid = $preRaw[$j]['samaccountname'][0] ?? '';
                                    if ($sid !== '' && substr($sid, -strlen($suffix)) === $suffix) {
                                        $entry = ldap_normalize_entry($preRaw[$j]);
                                        break;
                                    }
                                }
                            }
                        }

                        // Fallback to broad numeric wildcard if prefix search didn't resolve
                        if ($entry === null) {
                        $escaped = ldap_escape_filter_value($numPart);
                        $wcfilter = "(&(objectCategory=person)(objectClass=user)(samAccountName=*{$escaped}*))";
                        $wcsearch = @ldap_search($connection, $baseDn, $wcfilter, $wcAttrs, 0, 5);
                        if ($wcsearch !== false) {
                            $wcraw = ldap_get_entries($connection, $wcsearch);
                            $wcCount = is_array($wcraw) ? (int) ($wcraw['count'] ?? 0) : 0;
                            if ($wcCount === 1) {
                                $entry = ldap_normalize_entry($wcraw[0]);
                            } elseif ($wcCount > 1) {
                                $ids = [];
                                for ($j = 0; $j < $wcCount; $j++) {
                                    $sid = $wcraw[$j]['samaccountname'][0] ?? '';
                                    if ($sid !== '' && $sid !== $username) {
                                        $ids[] = $sid;
                                    }
                                }
                                if (!empty($ids)) {
                                    return ldap_json_script_result([
                                        'success' => false,
                                        'message' => ldap_ad_action_message("User '{$username}' not found.", false, 1, 0, 0, 1)
                                            . "\n\n💡 Multiple matching IDs: " . implode(', ', array_slice($ids, 0, 5)),
                                        'suggestions' => [$username => array_slice($ids, 0, 5)],
                                    ], false, 1);
                                }
                            }
                        }
                        }
                    }
                    if ($entry === null) {
                        return ldap_json_script_result([
                            'success' => false,
                            'message' => ldap_ad_action_message("User '{$username}' not found.", false, 1, 0, 0, 1),
                        ], false, 1);
                    }
                }

                $userInfo = ldap_adapt_get_ad_user_info($entry);
                $sam = $userInfo['SamAccountName'] ?? '';
                if ($sam !== '') {
                    $userInfo['lastLogonWorkstation'] = ldap_get_last_logon_workstation($sam, $connection);
                }
                $displayName = $userInfo['DisplayName'] ?? $username;
                $adName = $userInfo['SamAccountName'] ?? ($entry['samaccountname'] ?? '');
                $foundMsg = "User '{$displayName}' found successfully.";
                if ($adName !== '' && $adName !== $username) {
                    $foundMsg .= " (AD account: {$adName})";
                }
                return ldap_json_script_result([
                    'success' => true,
                    'user' => $userInfo,
                    'message' => ldap_ad_action_message($foundMsg),
                ]);
            });
        } catch (Throwable $e) {
            $badge = 'ERROR: Error retrieving user information: ' . $e->getMessage();
            return ldap_json_script_result([
                'success' => false,
                'message' => ldap_feedback_message($badge, 1, 0, 1),
            ], false, 1);
        }
    }
}

if (!function_exists('ldap_user_repository_find_many')) {
    function ldap_user_repository_find_many(array $params, string $executedBy): array
    {
        $raw = trim((string) ($params['Username'] ?? $params['username'] ?? ''));
        if ($raw === '') {
            return ldap_json_script_result([
                'success' => false,
                'message' => ldap_ad_action_message('Username is required.', false, 1, 0, 0, 1),
            ], false, 1);
        }

        $usernames = array_map('trim', preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY));
        $userResults = [];
        $allSuccess = true;

        foreach ($usernames as $uname) {
            $singleResult = ldap_user_repository_find([
                'Username' => $uname,
                'ExecutedBy' => $executedBy,
            ], $executedBy);

            $decoded = $singleResult['decoded'] ?? null;
            $userSuccess = !empty($decoded['success']);
            $userData = $decoded['user'] ?? null;

            $userResults[] = [
                'username' => $uname,
                'success' => $userSuccess,
                'data' => $userData,
                'message' => $decoded['message'] ?? ($userSuccess ? 'OK' : 'Not found'),
            ];

            if (!$userSuccess) {
                $allSuccess = false;
            }
        }

        $successCount = count(array_filter($userResults, function ($r) { return $r['success']; }));
        $failedCount = count($usernames) - $successCount;
        $summaryMessage = '>> Processed: ' . count($usernames) . ' | Success: ' . $successCount . ' | Skipped: 0 | Failed: ' . $failedCount . ' <<';

        $detailMessages = [];
        foreach ($userResults as $ur) {
            $msg = $ur['message'] ?? '';
            // Avoid double prefix — find() already adds "ERROR: "/"SUCCESS: " via ldap_ad_action_message
            if (preg_match('/^(SUCCESS|ERROR):\s*/', $msg) !== 1) {
                $msg = ($ur['success'] ? 'SUCCESS: ' : 'ERROR: ') . $msg;
            }
            $detailMessages[] = $msg;
        }

        // Suggest nearby user IDs when exact user not found
        $suggestions = [];
        if ($failedCount > 0) {
            $suggestions = ldap_user_repository_suggest_nearby($usernames, $userResults);
        }

        $payload = [
            'success' => $allSuccess,
            'message' => implode("\n\n", $detailMessages) . "\n\n" . $summaryMessage,
            'userResults' => $userResults,
        ];
        if (!empty($suggestions)) {
            $payload['suggestions'] = $suggestions;
        }
        return ldap_json_script_result($payload, $allSuccess, $allSuccess ? 0 : 1);
    }
}

if (!function_exists('ldap_user_repository_suggest_nearby')) {
    function ldap_user_repository_suggest_nearby(array $usernames, array $userResults): array
    {
        $suggestions = [];
        try {
            ldap_run_with_connection(function ($connection, $config) use ($usernames, $userResults, &$suggestions) {
                $baseDn = ldap_search_base_dn($config);
                if ($baseDn === '') return;

                foreach ($userResults as $i => $ur) {
                    if ($ur['success']) continue;
                    $uname = $usernames[$i] ?? $ur['username'];
                    if ($uname === '') continue;

                    // Extract the trailing numeric portion for prefix matching
                    preg_match('/(\d+)$/', $uname, $m);
                    $numPart = $m[1] ?? '';
                    if ($numPart === '') continue;

                    // Search for IDs containing the full numeric portion (no fallback to avoid irrelevant results)
                    $escaped = ldap_escape_filter_value($numPart);
                    $filter = "(&(objectCategory=person)(objectClass=user)(sAMAccountName=*{$escaped}*))";
                    $search = @ldap_search($connection, $baseDn, $filter, ['sAMAccountName'], 0, 6);
                    if ($search === false) continue;
                    $entries = ldap_get_entries($connection, $search);
                    if (!is_array($entries) || ($entries['count'] ?? 0) === 0) continue;
                    $nearby = [];
                    for ($j = 0; $j < $entries['count']; $j++) {
                        $sid = $entries[$j]['samaccountname'][0] ?? '';
                        if ($sid !== '' && $sid !== $uname) {
                            $nearby[] = $sid;
                        }
                    }
                    if (empty($nearby)) continue;
                    $suggestions[$uname] = array_slice($nearby, 0, 5);
                    break;
                }
            });
        } catch (Throwable $e) {
            // Silently ignore — suggestions are best-effort
        }
        return $suggestions;
    }
}
