<?php

require_once __DIR__ . '/../Support/ldap_helpers.php';
require_once __DIR__ . '/ldap_response_adapter.php';
require_once __DIR__ . '/ldap_user_repository.php';

if (!function_exists('ldap_stub_result')) {
    function ldap_stub_result(string $operation): array
    {
        return ldap_json_script_result([
            'success' => false,
            'message' => 'LDAP operation pending implementation: ' . $operation,
        ], false, 1);
    }
}

if (!function_exists('ldap_group_lookup_entry')) {
    function ldap_group_lookup_entry($connection, string $baseDn, string $identity): ?array
    {
        $trimmed = trim($identity);
        if ($trimmed === '') {
            return null;
        }

        $attributes = ['samaccountname', 'name', 'cn', 'distinguishedname', 'description', 'member',
            'mail', 'proxyaddresses', 'mailnickname', 'grouptype', 'managedby', 'info',
            'msexchrecipienttypedetails', 'msexchhomeservername', 'whencreated',
        ];

        if (stripos($trimmed, '=') !== false) {
            $read = @ldap_read($connection, $trimmed, '(objectClass=group)', $attributes);
            if ($read !== false) {
                $raw = ldap_get_entries($connection, $read);
                if (is_array($raw) && (int) ($raw['count'] ?? 0) > 0) {
                    return ldap_normalize_entry($raw[0]);
                }
            }
        }

        $escaped = ldap_escape_filter_value($trimmed);
        $filter = "(&(objectClass=group)(|(sAMAccountName={$escaped})(cn={$escaped})(name={$escaped})))";
        $search = @ldap_search($connection, $baseDn, $filter, $attributes);
        if ($search === false) {
            throw new RuntimeException('LDAP group search failed: ' . ldap_error($connection));
        }

        $raw = ldap_get_entries($connection, $search);
        if (!is_array($raw) || (int) ($raw['count'] ?? 0) < 1) {
            return null;
        }

        return ldap_normalize_entry($raw[0]);
    }
}

if (!function_exists('ldap_fetch_principal_by_dn')) {
    function ldap_fetch_principal_by_dn($connection, string $dn): ?array
    {
        $read = @ldap_read($connection, $dn, '(|(objectClass=user)(objectClass=group))', [
            'samaccountname', 'displayname', 'name', 'cn', 'distinguishedname', 'employeeid', 'objectclass',
        ]);
        if ($read === false) {
            return null;
        }

        $raw = ldap_get_entries($connection, $read);
        if (!is_array($raw) || (int) ($raw['count'] ?? 0) < 1) {
            return null;
        }

        $entry = ldap_normalize_entry($raw[0]);
        $classes = array_map('strtolower', ldap_attr_all($entry, 'objectclass'));
        $objectClass = in_array('group', $classes, true) ? 'group' : 'user';

        return ldap_adapt_member_payload($entry, $objectClass);
    }
}

if (!function_exists('ldap_resolve_principal')) {
    function ldap_resolve_principal(array $params, string $executedBy): array
    {
        $identity = trim((string) ($params['Identity'] ?? $params['identity'] ?? ''));
        if ($identity === '') {
            return ldap_json_script_result([
                'success' => false,
                'message' => 'A user or group identity is required.',
                'member' => null,
                'suggestions' => [],
            ], false, 1);
        }

        try {
            return ldap_run_with_connection(function ($connection, $config) use ($identity) {
                $baseDn = ldap_search_base_dn($config);
                if ($baseDn === '') {
                    throw new RuntimeException('LDAP base DN is not configured.');
                }

                try {
                    $userEntry = ldap_user_lookup_entry($connection, $baseDn, $identity);
                    if ($userEntry !== null) {
                        return ldap_json_script_result([
                            'success' => true,
                            'message' => "Resolved '{$identity}' successfully.",
                            'member' => ldap_adapt_member_payload($userEntry, 'user'),
                            'suggestions' => [],
                        ]);
                    }

                    $groupEntry = ldap_group_lookup_entry($connection, $baseDn, $identity);
                    if ($groupEntry !== null) {
                        return ldap_json_script_result([
                            'success' => true,
                            'message' => "Resolved '{$identity}' successfully.",
                            'member' => ldap_adapt_member_payload($groupEntry, 'group'),
                            'suggestions' => [],
                        ]);
                    }
                } catch (RuntimeException $e) {
                    if (stripos($e->getMessage(), 'Multiple') !== false) {
                        throw $e;
                    }
                }

                $like = ldap_escape_filter_value('*' . $identity . '*');
                $suggestions = [];

                $userFilter = "(&(objectCategory=person)(objectClass=user)(|(sAMAccountName={$like})(name={$like})(employeeID={$like})))";
                $userSearch = @ldap_search($connection, $baseDn, $userFilter, ['samaccountname', 'displayname', 'distinguishedname', 'employeeid', 'cn'], 0, 6);
                if ($userSearch !== false) {
                    $raw = ldap_get_entries($connection, $userSearch);
                    if (is_array($raw)) {
                        for ($i = 0; $i < min(6, (int) ($raw['count'] ?? 0)); $i++) {
                            $suggestions[] = ldap_adapt_member_payload(ldap_normalize_entry($raw[$i]), 'user');
                        }
                    }
                }

                $groupFilter = "(&(objectClass=group)(|(sAMAccountName={$like})(cn={$like})(name={$like})))";
                $groupSearch = @ldap_search($connection, $baseDn, $groupFilter, ['samaccountname', 'name', 'cn', 'distinguishedname'], 0, 6);
                if ($groupSearch !== false) {
                    $raw = ldap_get_entries($connection, $groupSearch);
                    if (is_array($raw)) {
                        for ($i = 0; $i < min(6, (int) ($raw['count'] ?? 0)); $i++) {
                            $suggestions[] = ldap_adapt_member_payload(ldap_normalize_entry($raw[$i]), 'group');
                        }
                    }
                }

                $message = "No exact directory principal matched '{$identity}'.";
                if (!empty($suggestions)) {
                    $message .= ' Similar matches were found.';
                }

                return ldap_json_script_result([
                    'success' => false,
                    'message' => $message,
                    'member' => null,
                    'suggestions' => $suggestions,
                ], false, 1);
            });
        } catch (Throwable $e) {
            return ldap_json_script_result([
                'success' => false,
                'message' => 'ERROR: Failed to resolve directory principal. ' . $e->getMessage(),
                'member' => null,
                'suggestions' => [],
            ], false, 1);
        }
    }
}

if (!function_exists('ldap_group_repository_members')) {
    function ldap_group_repository_members(array $params, string $executedBy): array
    {
        $groupIdentity = trim((string) ($params['GroupIdentity'] ?? $params['group'] ?? ''));
        if ($groupIdentity === '') {
            return ldap_json_script_result([
                'success' => false,
                'message' => 'Group name is required.',
                'group' => null,
                'members' => [],
                'suggestions' => [],
            ], false, 1);
        }

        try {
            return ldap_run_with_connection(function ($connection, $config) use ($groupIdentity) {
                $baseDn = ldap_search_base_dn($config);
                $groupEntry = ldap_group_lookup_entry($connection, $baseDn, $groupIdentity);

                if ($groupEntry === null) {
                    $like = ldap_escape_filter_value('*' . $groupIdentity . '*');
                    $filter = "(&(objectClass=group)(|(sAMAccountName={$like})(cn={$like})(name={$like})))";
                    $search = @ldap_search($connection, $baseDn, $filter, ['samaccountname', 'name', 'cn', 'distinguishedname', 'description', 'mail', 'grouptype'], 0, 6);
                    $suggestions = [];
                    if ($search !== false) {
                        $raw = ldap_get_entries($connection, $search);
                        if (is_array($raw)) {
                            for ($i = 0; $i < min(6, (int) ($raw['count'] ?? 0)); $i++) {
                                $row = ldap_normalize_entry($raw[$i]);
                                $suggestions[] = [
                                    'Name' => ldap_first_attr($row, 'name', ldap_first_attr($row, 'cn', '')),
                                    'SamAccountName' => ldap_first_attr($row, 'samaccountname', ''),
                                    'DistinguishedName' => ldap_first_attr($row, 'distinguishedname', $row['dn'] ?? ''),
                                    'Description' => ldap_first_attr($row, 'description', ''),
                                ];
                            }
                        }
                    }

                    $message = "Group '{$groupIdentity}' was not found in Active Directory.";
                    if (!empty($suggestions)) {
                        $message .= ' Similar groups were found. Please choose the correct one.';
                    }

                    return ldap_json_script_result([
                        'success' => false,
                        'message' => $message,
                        'group' => null,
                        'members' => [],
                        'suggestions' => $suggestions,
                    ], false, 1);
                }

                $groupDn = ldap_first_attr($groupEntry, 'distinguishedname', $groupEntry['dn'] ?? '');
                $groupPayload = [
                    'Name' => ldap_first_attr($groupEntry, 'name', ldap_first_attr($groupEntry, 'cn', '')),
                    'SamAccountName' => ldap_first_attr($groupEntry, 'samaccountname', ''),
                    'DistinguishedName' => $groupDn,
                    'Description' => ldap_first_attr($groupEntry, 'description', ''),
                ];

                $memberDns = ldap_attr_all($groupEntry, 'member');
                $members = [];

                foreach ($memberDns as $memberDn) {
                    $principal = ldap_fetch_principal_by_dn($connection, $memberDn);
                    if ($principal !== null) {
                        $members[] = $principal;
                    }
                }

                usort($members, function ($a, $b) {
                    $classCmp = strcmp((string) ($a['ObjectClass'] ?? ''), (string) ($b['ObjectClass'] ?? ''));
                    if ($classCmp !== 0) {
                        return $classCmp;
                    }
                    return strcmp((string) ($a['Name'] ?? ''), (string) ($b['Name'] ?? ''));
                });

                return ldap_json_script_result([
                    'success' => true,
                    'message' => "Loaded direct members for group '{$groupPayload['Name']}'.",
                    'group' => $groupPayload,
                    'members' => $members,
                    'suggestions' => [],
                ]);
            });
        } catch (Throwable $e) {
            return ldap_json_script_result([
                'success' => false,
                'message' => 'ERROR: Failed to load group members. ' . $e->getMessage(),
                'group' => null,
                'members' => [],
                'suggestions' => [],
            ], false, 1);
        }
    }
}

if (!function_exists('ldap_group_is_distribution')) {
    function ldap_group_is_distribution(array $entry): bool
    {
        $groupType = (int) ldap_first_attr($entry, 'grouptype', '0');
        // Distribution groups have bit 31 (0x80000000) set = 2147483648
        // Universal Distribution: -2147483646 (0x80000002 signed)
        // Universal Security + mail: check msExchRecipientTypeDetails
        $isDist = ($groupType & 2147483648) === 2147483648;

        // Also check msExchRecipientTypeDetails: 4=DistributionGroup, 8=MailUniversalSecurityGroup
        $recipType = (int) ldap_first_attr($entry, 'msexchrecipienttypedetails', '0');
        if (in_array($recipType, [4, 8], true)) {
            return true;
        }

        // Also consider groups with mail attribute as distribution
        $mail = ldap_first_attr($entry, 'mail', '');
        if ($mail !== '' && $isDist) {
            return true;
        }

        return $isDist;
    }
}

if (!function_exists('ldap_group_repository_search_dl')) {
    function ldap_group_repository_search_dl(array $params, string $executedBy): array
    {
        $keyword = trim((string) ($params['Keyword'] ?? $params['keyword'] ?? ''));
        if ($keyword === '') {
            return ldap_json_script_result([
                'success' => false,
                'message' => 'Search keyword is required.',
                'groups' => [],
            ], false, 1);
        }

        try {
            return ldap_run_with_connection(function ($connection, $config) use ($keyword) {
                $baseDn = ldap_search_base_dn($config);
                if ($baseDn === '') {
                    throw new RuntimeException('LDAP base DN is not configured.');
                }

                $escaped = ldap_escape_filter_value($keyword);
                // Search for groups that are mail-enabled (have mail attribute or are distribution groups)
                $filter = "(&(objectClass=group)(|(cn=*{$escaped}*)(name=*{$escaped}*)(sAMAccountName=*{$escaped}*)(mail=*{$escaped}*)))";
                $attrs = ['name', 'cn', 'samaccountname', 'distinguishedname', 'description', 'mail',
                    'proxyaddresses', 'mailnickname', 'grouptype', 'managedby', 'member', 'info',
                    'msexchrecipienttypedetails', 'whencreated'];

                $search = @ldap_search($connection, $baseDn, $filter, $attrs, 0, 50);
                if ($search === false) {
                    return ldap_json_script_result([
                        'success' => false,
                        'message' => 'LDAP search failed.',
                        'groups' => [],
                    ], false, 1);
                }

                $raw = ldap_get_entries($connection, $search);
                $count = (int) ($raw['count'] ?? 0);
                $groups = [];

                for ($i = 0; $i < $count; $i++) {
                    $entry = ldap_normalize_entry($raw[$i]);
                    $isDl = ldap_group_is_distribution($entry);
                    $mail = ldap_first_attr($entry, 'mail', '');
                    $proxyAll = ldap_attr_all($entry, 'proxyaddresses');
                    $primarySmtp = '';
                    foreach ($proxyAll as $addr) {
                        if (preg_match('/^SMTP:(.+)$/i', $addr, $m)) {
                            $primarySmtp = $m[1];
                            break;
                        }
                    }

                    $memberDns = ldap_attr_all($entry, 'member');
                    $memberCount = count($memberDns);

                    // Resolve managedBy to display name
                    $managedByDn = ldap_first_attr($entry, 'managedby', '');
                    $managedByName = '';
                    if ($managedByDn !== '') {
                        $principal = ldap_fetch_principal_by_dn($connection, $managedByDn);
                        if ($principal !== null) {
                            $managedByName = $principal['Name'] ?? $managedByDn;
                        } else {
                            if (preg_match('/^CN=([^,]+)/i', $managedByDn, $m)) {
                                $managedByName = $m[1];
                            } else {
                                $managedByName = $managedByDn;
                            }
                        }
                    }

                    $groups[] = [
                        'name' => ldap_first_attr($entry, 'name', ldap_first_attr($entry, 'cn', '')),
                        'samAccountName' => ldap_first_attr($entry, 'samaccountname', ''),
                        'distinguishedName' => ldap_first_attr($entry, 'distinguishedname', $entry['dn'] ?? ''),
                        'description' => ldap_first_attr($entry, 'description', ''),
                        'mail' => $mail,
                        'primary_smtp' => $primarySmtp ?: $mail,
                        'alias' => ldap_first_attr($entry, 'mailnickname', ''),
                        'member_count' => $memberCount,
                        'managed_by' => $managedByName,
                        'is_distribution' => $isDl,
                        'recipient_type_details' => (int) ldap_first_attr($entry, 'msexchrecipienttypedetails', '0'),
                        'when_created' => ldap_convert_generalized_time(ldap_first_attr($entry, 'whencreated', '')),
                        'info' => ldap_first_attr($entry, 'info', ''),
                    ];
                }

                // Sort: mail-enabled groups first, then by name
                usort($groups, function ($a, $b) {
                    if ($a['is_distribution'] !== $b['is_distribution']) {
                        return $b['is_distribution'] ? 1 : -1;
                    }
                    return strcasecmp($a['name'], $b['name']);
                });

                return ldap_json_script_result([
                    'success' => true,
                    'groups' => $groups,
                    'total' => count($groups),
                    'message' => "Found " . count($groups) . " group(s) matching '{$keyword}'.",
                ]);
            });
        } catch (Throwable $e) {
            return ldap_json_script_result([
                'success' => false,
                'message' => 'ERROR: ' . $e->getMessage(),
                'groups' => [],
            ], false, 1);
        }
    }
}

if (!function_exists('ldap_group_repository_list')) {
    function ldap_group_repository_list(array $params, string $executedBy): array
    {
        try {
            return ldap_run_with_connection(function ($connection, $config) {
                $baseDn = ldap_search_base_dn($config);
                if ($baseDn === '') {
                    throw new RuntimeException('LDAP base DN is not configured.');
                }

                $pageSize = (int) ($config['page_size'] ?? 500);
                $rows = [];

                $domainName = '';
                if (preg_match('/DC=([^,]+)/i', $baseDn, $m)) {
                    $domainName = $m[1];
                }
                $rows[] = ldap_adapt_tree_row($domainName !== '' ? $domainName : 'Domain', $baseDn, 'Domain', null);

                $containers = ldap_paged_search(
                    $connection,
                    $baseDn,
                    '(|(objectClass=organizationalUnit)(objectClass=container)(objectClass=builtinDomain))',
                    ['name', 'distinguishedname', 'objectclass'],
                    $pageSize
                );

                foreach ($containers as $entry) {
                    $dn = ldap_first_attr($entry, 'distinguishedname', $entry['dn'] ?? '');
                    $classes = array_map('strtolower', ldap_attr_all($entry, 'objectclass'));
                    $type = in_array('organizationalunit', $classes, true) ? 'OU' : 'Container';
                    $rows[] = ldap_adapt_tree_row(
                        ldap_first_attr($entry, 'name', ldap_first_attr($entry, 'cn', '')),
                        $dn,
                        $type,
                        ldap_parent_dn($dn)
                    );
                }

                $groups = ldap_paged_search(
                    $connection,
                    $baseDn,
                    '(objectClass=group)',
                    ['name', 'samaccountname', 'distinguishedname', 'cn'],
                    $pageSize
                );

                foreach ($groups as $entry) {
                    $dn = ldap_first_attr($entry, 'distinguishedname', $entry['dn'] ?? '');
                    $rows[] = ldap_adapt_tree_row(
                        ldap_first_attr($entry, 'name', ldap_first_attr($entry, 'cn', '')),
                        $dn,
                        'Group',
                        ldap_parent_dn($dn)
                    );
                }

                usort($rows, function ($a, $b) {
                    return strcmp((string) ($a['Name'] ?? ''), (string) ($b['Name'] ?? ''));
                });

                return ldap_json_script_result([
                    'success' => true,
                    'data' => array_values($rows),
                ]);
            });
        } catch (Throwable $e) {
            return ldap_json_script_result([
                'success' => false,
                'message' => 'ERROR: Failed to retrieve Active Directory groups. Details: ' . $e->getMessage(),
            ], false, 1);
        }
    }
}
