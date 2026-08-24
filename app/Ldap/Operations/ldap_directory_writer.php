<?php

require_once __DIR__ . '/../Support/ldap_helpers.php';
require_once __DIR__ . '/ldap_response_adapter.php';
require_once __DIR__ . '/ldap_group_repository.php';

if (!function_exists('ldap_directory_list_ous')) {
    function ldap_directory_list_ous(array $params, string $executedBy): array
    {
        $systemPatterns = [
            'CN=Builtin',
            'CN=Computers',
            'CN=Users',
            'OU=Domain Controllers',
            'CN=ForeignSecurityPrincipals',
            'CN=Managed Service Accounts',
            'CN=Program Data',
            'CN=System',
            'CN=LostAndFound',
        ];

        try {
            return ldap_run_with_connection(function ($connection, $config) use ($systemPatterns) {
                $baseDn = ldap_search_base_dn($config);
                if ($baseDn === '') {
                    throw new RuntimeException('LDAP base DN is not configured.');
                }

                $domainName = '';
                if (preg_match('/DC=([^,]+)/i', $baseDn, $m)) {
                    $domainName = $m[1];
                }

                $tree = [
                    ldap_adapt_tree_row($domainName !== '' ? $domainName : 'Domain', $baseDn, 'Domain', null),
                ];

                $pageSize = (int) ($config['page_size'] ?? 500);
                $ous = ldap_paged_search(
                    $connection,
                    $baseDn,
                    '(objectClass=organizationalUnit)',
                    ['ou', 'name', 'distinguishedname', 'whencreated', 'whenchanged'],
                    $pageSize
                );

                foreach ($ous as $entry) {
                    $dn = ldap_first_attr($entry, 'distinguishedname', $entry['dn'] ?? '');
                    $skip = false;
                    foreach ($systemPatterns as $pattern) {
                        if (stripos($dn, ',' . $pattern . ',') !== false
                            || stripos($dn, $pattern . ',') === 0
                            || stripos($dn, ',' . $pattern) !== false) {
                            $skip = true;
                            break;
                        }
                    }
                    if ($skip) {
                        continue;
                    }

                    $name = ldap_first_attr($entry, 'ou', ldap_first_attr($entry, 'name', ''));
                    $tree[] = ldap_adapt_tree_row($name, $dn, 'OU', ldap_parent_dn($dn));
                }

                return ldap_json_script_result([
                    'success' => true,
                    'data' => array_values($tree),
                ]);
            });
        } catch (Throwable $e) {
            return ldap_json_script_result([
                'success' => false,
                'message' => 'ERROR: Failed to retrieve Organizational Units. Details: ' . $e->getMessage(),
            ], false, 1);
        }
    }
}

if (!function_exists('ldap_directory_writer_create')) {
    function ldap_directory_writer_create(array $params, string $executedBy): array
    {
        $objectType = trim((string) ($params['ObjectType'] ?? ''));
        $objectName = trim((string) ($params['ObjectName'] ?? ''));
        $parentOU = trim((string) ($params['ParentOU'] ?? ''));
        $description = trim((string) ($params['Description'] ?? ''));

        if ($objectType === '' || $objectName === '' || $parentOU === '') {
            $errMsg = 'ERROR: Object type, object name, and parent OU are required.';
            return ldap_json_script_result([
                'success' => false,
                'message' => $errMsg,
            ], false, 1);
        }

        try {
            return ldap_run_with_connection(function ($connection, $config) use ($objectType, $objectName, $parentOU, $description, $executedBy) {
                $targetDn = ($objectType === 'OU' ? 'OU=' : 'CN=') . ldap_escape_dn_value($objectName) . ',' . $parentOU;

                // Validate the parent container actually exists before add — an
                // invalid/truncated parent DN surfaces as a generic AD
                // "Operations error" (extended 000020D6 / problem 5012 DIR_ERROR).
                $parentCheck = @ldap_read($connection, $parentOU, '(objectClass=*)', ['dn'], 0, 0, 0);
                if ($parentCheck === false) {
                    $msg = "ERROR: Parent OU was not found in Active Directory: '{$parentOU}'. Please re-select the parent OU from the tree.";
                    return ldap_json_script_result([
                        'success' => false,
                        'message' => $msg,
                    ], false, 1);
                }

                if ($objectType === 'OU') {
                    $entry = [
                        'objectClass' => ['top', 'organizationalUnit'],
                        'ou' => $objectName,
                        'name' => $objectName,
                    ];
                    if ($description !== '') {
                        $entry['description'] = $description;
                    }
                } elseif ($objectType === 'Group') {
                    $entry = [
                        'objectClass' => ['top', 'group'],
                        'cn' => $objectName,
                        'name' => $objectName,
                        // AD limits sAMAccountName to 20 chars; a longer value is
                        // rejected with a generic "Operations error" (extended
                        // 00000523 / Invalid argument). Mirror New-ADGroup, which
                        // auto-truncates the SAM name to 20 chars.
                        'sAMAccountName' => ldap_clamp_sam_account_name($objectName),
                        'groupType' => '-2147483646',
                    ];
                    if ($description !== '') {
                        $entry['description'] = $description;
                    }
                } else {
                    return ldap_json_script_result([
                        'success' => false,
                        'message' => "ERROR: Unsupported object type '{$objectType}'. Supported: OU, Group.",
                    ], false, 1);
                }

                if (!@ldap_add($connection, $targetDn, $entry)) {
                    $err = ldap_error($connection);
                    $diag = '';
                    @ldap_get_option($connection, defined('LDAP_OPT_DIAGNOSTIC_MESSAGE') ? LDAP_OPT_DIAGNOSTIC_MESSAGE : 0x0032, $diag);
                    $detail = $diag !== '' ? " ({$diag})" : '';
                    ldap_write_script_log('create_directory_object', $objectName, false, "Failed to create {$objectType}: {$err}{$detail}", $executedBy, ($objectType === 'OU') ? 'create_ou' : 'create_grp');
                    $msg = "ERROR: Failed to create {$objectType} '{$objectName}'. {$err}{$detail}";
                    return ldap_json_script_result([
                        'success' => false,
                        'message' => $msg,
                    ], false, 1);
                }

                $msg = ($objectType === 'OU')
                    ? "Organizational Unit '{$objectName}' created successfully."
                    : "Security Group '{$objectName}' created successfully.";

                return ldap_json_script_result([
                    'success' => true,
                    'message' => $msg,
                ]);
            });
        } catch (Throwable $e) {
            return ldap_json_script_result([
                'success' => false,
                'message' => 'ERROR: ' . $e->getMessage(),
            ], false, 1);
        }
    }
}

if (!function_exists('ldap_directory_writer_delete')) {
    function ldap_directory_writer_delete(array $params, string $executedBy): array
    {
        $objectDN = trim((string) ($params['ObjectDN'] ?? ''));
        $objectType = trim((string) ($params['ObjectType'] ?? ''));

        if ($objectDN === '' || $objectType === '') {
            $errMsg = 'ERROR: Object DN and type are required.';
            return ldap_json_script_result([
                'success' => false,
                'message' => $errMsg,
            ], false, 1);
        }

        try {
            return ldap_run_with_connection(function ($connection, $config) use ($objectDN, $objectType) {
                if (!@ldap_delete($connection, $objectDN)) {
                    $err = ldap_error($connection);
                    $label = ($objectType === 'OU') ? 'Organizational Unit' : ($objectType === 'Group' ? 'Security Group' : 'Object');
                    $msg = "ERROR: Failed to delete {$label}. {$err}";
                    return ldap_json_script_result([
                        'success' => false,
                        'message' => $msg,
                    ], false, 1);
                }

                $label = ($objectType === 'OU') ? 'Organizational Unit' : ($objectType === 'Group' ? 'Security Group' : 'Object');
                $msg = ($objectType === 'OU')
                    ? "Successfully deleted Organizational Unit: {$objectDN}."
                    : "Successfully deleted Security Group: {$objectDN}.";

                return ldap_json_script_result([
                    'success' => true,
                    'message' => $msg,
                ]);
            });
        } catch (Throwable $e) {
            return ldap_json_script_result([
                'success' => false,
                'message' => 'ERROR: ' . $e->getMessage(),
            ], false, 1);
        }
    }
}

if (!function_exists('ldap_group_writer_sync_members')) {
    function ldap_group_writer_sync_members(array $params, string $executedBy): array
    {
        $groupIdentity = trim((string) ($params['GroupIdentity'] ?? ''));
        $membersToAdd = trim((string) ($params['MembersToAdd'] ?? ''));
        $membersToRemove = trim((string) ($params['MembersToRemove'] ?? ''));
        $desiredMembers = trim((string) ($params['DesiredMembers'] ?? ''));

        if ($groupIdentity === '') {
            $errMsg = 'ERROR: Group identity is required.';
            return ldap_json_script_result([
                'success' => false,
                'message' => $errMsg,
            ], false, 1);
        }

        try {
            return ldap_run_with_connection(function ($connection, $config) use ($groupIdentity, $membersToAdd, $membersToRemove, $desiredMembers) {
                $baseDn = ldap_search_base_dn($config);
                if ($baseDn === '') {
                    throw new RuntimeException('LDAP base DN is not configured.');
                }

                $groupEntry = ldap_group_lookup_entry($connection, $baseDn, $groupIdentity);
                if ($groupEntry === null) {
                    $errMsg = "ERROR: Group '{$groupIdentity}' not found in Active Directory.";
                    return ldap_json_script_result([
                        'success' => false,
                        'message' => $errMsg,
                    ], false, 1);
                }

                $groupDN = ldap_first_attr($groupEntry, 'distinguishedname', $groupEntry['dn'] ?? '');
                $groupName = ldap_first_attr($groupEntry, 'name', ldap_first_attr($groupEntry, 'cn', $groupIdentity));

                // Resolve add/remove lists
                $toAddDns = [];
                if ($membersToAdd !== '') {
                    $parts = explode(';', $membersToAdd);
                    foreach ($parts as $p) {
                        $p = trim($p);
                        if ($p !== '') {
                            $toAddDns[] = $p;
                        }
                    }
                }

                $toRemoveDns = [];
                if ($membersToRemove !== '') {
                    $parts = explode(';', $membersToRemove);
                    foreach ($parts as $p) {
                        $p = trim($p);
                        if ($p !== '') {
                            $toRemoveDns[] = $p;
                        }
                    }
                }

                // If DesiredMembers provided, compute diff against current members
                if ($desiredMembers !== '' && empty($toAddDns) && empty($toRemoveDns)) {
                    $currentMemberAttr = ldap_first_attr($groupEntry, 'member', '');
                    $currentMemberDns = [];
                    if ($currentMemberAttr !== '') {
                        $read = @ldap_read($connection, $groupDN, '(objectClass=*)', ['member'], 0, 0, 0);
                        if ($read !== false) {
                            $raw = ldap_get_entries($connection, $read);
                            if (is_array($raw) && (int) ($raw['count'] ?? 0) > 0) {
                                for ($i = 0; $i < (int) ($raw[0]['member']['count'] ?? 0); $i++) {
                                    $currentMemberDns[] = $raw[0]['member'][$i];
                                }
                            }
                        }
                    }

                    $currentLower = array_map('strtolower', $currentMemberDns);
                    $desiredList = explode(';', $desiredMembers);
                    $desiredDns = [];
                    foreach ($desiredList as $d) {
                        $d = trim($d);
                        if ($d !== '') {
                            $desiredDns[] = $d;
                        }
                    }
                    $desiredLower = array_map('strtolower', $desiredDns);

                    // Add: in desired but not in current
                    foreach ($desiredDns as $idx => $ddn) {
                        if (!in_array($desiredLower[$idx], $currentLower, true)) {
                            $toAddDns[] = $ddn;
                        }
                    }

                    // Remove: in current but not in desired
                    foreach ($currentMemberDns as $idx => $cdn) {
                        if (!in_array($currentLower[$idx], $desiredLower, true)) {
                            $toRemoveDns[] = $cdn;
                        }
                    }
                }

                // Filter out group's own DN from add list
                $groupDnLower = strtolower($groupDN);
                $toAddDns = array_filter($toAddDns, function ($dn) use ($groupDnLower) {
                    return strtolower($dn) !== $groupDnLower;
                });

                $actualAdded = [];
                $actualRemoved = [];
                $hadError = false;

                foreach ($toAddDns as $memberDn) {
                    $mod = [['attrib' => 'member', 'modtype' => LDAP_MODIFY_BATCH_ADD, 'values' => [$memberDn]]];
                    if (@ldap_modify_batch($connection, $groupDN, $mod)) {
                        $actualAdded[] = ldap_resolve_principal_display($connection, $memberDn);
                    }
                }

                foreach ($toRemoveDns as $memberDn) {
                    $mod = [['attrib' => 'member', 'modtype' => LDAP_MODIFY_BATCH_REMOVE, 'values' => [$memberDn]]];
                    if (@ldap_modify_batch($connection, $groupDN, $mod)) {
                        $actualRemoved[] = ldap_resolve_principal_display($connection, $memberDn);
                    }
                }

                $parts = [];
                foreach ($actualAdded as $display) {
                    $parts[] = "{$display} has been added on the group '{$groupName}'";
                }
                foreach ($actualRemoved as $display) {
                    $parts[] = "{$display} has been removed from the group '{$groupName}'";
                }
                $msg = !empty($parts) ? implode('. ', $parts) . '.' : "No changes made to group '{$groupName}'.";

                return ldap_json_script_result([
                    'success' => true,
                    'message' => $msg,
                ]);
            });
        } catch (Throwable $e) {
            return ldap_json_script_result([
                'success' => false,
                'message' => 'ERROR: ' . $e->getMessage(),
            ], false, 1);
        }
    }
}

if (!function_exists('ldap_dispatch_execute_action')) {
    function ldap_dispatch_execute_action(array $params, string $executedBy): array
    {
        $action = $params['action'] ?? '';
        $username = trim((string) ($params['username'] ?? $params['Username'] ?? ''));

        $actionMap = [
            'info'         => ['operation' => 'get_user_info',  'handler' => 'ldap_user_repository_find'],
            'enableUser'   => ['operation' => 'enable_user',    'handler' => 'ldap_user_writer_set_enabled'],
            'disableUser'  => ['operation' => 'disable_user',   'handler' => 'ldap_user_writer_set_enabled'],
            'unlockUser'   => ['operation' => 'unlock_user',    'handler' => 'ldap_user_writer_unlock'],
            'resetUnlock'  => ['operation' => 'reset_password', 'handler' => 'ldap_user_writer_reset_password'],
            'createUser'   => ['operation' => 'create_user',    'handler' => 'ldap_user_writer_create'],
        ];

        $mapped = $actionMap[$action] ?? null;
        if ($mapped === null) {
            return ldap_json_script_result([
                'success' => false,
                'message' => "Unknown or unsupported action: {$action}",
            ], false, 1);
        }

        $handlerName = $mapped['handler'];
        if ($handlerName === null || !function_exists($handlerName)) {
            return ldap_json_script_result([
                'success' => false,
                'message' => "Action '{$action}' is not yet implemented via LDAP. Falling to PowerShell is recommended.",
            ], false, 1);
        }

        // Support multiple comma/space-separated IDs — process each one
        $usernames = array_map('trim', preg_split('/[\s,;]+/', $username, -1, PREG_SPLIT_NO_EMPTY));
        if (count($usernames) > 1) {
            $allResults = [];
            $overallSuccess = true;
            $successCount = 0;
            $skippedCount = 0;
            $failedCount = 0;
            foreach ($usernames as $singleUser) {
                $handlerParams = array_merge($params, [
                    'Username' => $singleUser,
                    'ExecutedBy' => $executedBy,
                ]);
                $result = $handlerName($handlerParams, $executedBy);
                $decoded = $result['decoded'] ?? json_decode((string) ($result['output'] ?? ''), true);
                $userSuccess = !empty($decoded['success']) || !empty($result['success']);
                if (!$userSuccess) { $overallSuccess = false; }
                $userMsg = $decoded['message'] ?? 'Unknown';
                $isSkipped = !empty($decoded['userResults'][0]['skipped']);
                if ($isSkipped) { $skippedCount++; } elseif ($userSuccess) { $successCount++; } else { $failedCount++; }
                $allResults[] = [
                    'username' => $singleUser,
                    'success' => $userSuccess,
                    'skipped' => $isSkipped,
                    'message' => $userMsg,
                ];
            }
            $userMessages = [];
            foreach ($allResults as $r) {
                // Individual LDAP handlers already prefix messages with "SUCCESS:"/"ERROR:"
                // AND append inline "Processed: 1 | Success: 1 |..." via ldap_feedback_message.
                // Strip that inline per-user summary — we emit one final summary below.
                $cleanMsg = preg_replace('/\n\nProcessed:.*$/s', '', $r['message']);
                $userMessages[] = $cleanMsg;
            }
            $summaryMessage = "Processed: " . count($usernames) . " | Success: $successCount | Skipped: $skippedCount | Failed: $failedCount";
            $fullMessage = implode("\n\n", $userMessages) . "\n\n>> $summaryMessage <<";
            return [
                'success' => $overallSuccess,
                'message' => $fullMessage,
                'output' => json_encode([
                    'success' => $overallSuccess,
                    'message' => $summaryMessage,
                    'processed' => count($usernames),
                    'successCount' => $successCount,
                    'skippedCount' => $skippedCount,
                    'failedCount' => $failedCount,
                    'userResults' => $allResults,
                ]),
                'exit_code' => $overallSuccess ? 0 : 1,
                'json_valid' => true,
                'decoded' => [
                    'success' => $overallSuccess,
                    'message' => $summaryMessage,
                    'processed' => count($usernames),
                    'successCount' => $successCount,
                    'skippedCount' => $skippedCount,
                    'failedCount' => $failedCount,
                    'userResults' => $allResults,
                ],
            ];
        }

        $handlerParams = array_merge($params, [
            'Username' => $username,
            'ExecutedBy' => $executedBy,
        ]);

        return $handlerName($handlerParams, $executedBy);
    }
}
