<?php
/**
 * Phase 2–3 — User writes (enable, disable, unlock, password, modify, create).
 */

require_once __DIR__ . '/ldap_group_repository.php';

if (!function_exists('ldap_user_writer_set_enabled')) {
    function ldap_user_writer_set_enabled(array $params, string $executedBy): array
    {
        $username = trim((string) ($params['Username'] ?? $params['username'] ?? ''));
        $enable = stripos($params['action'] ?? '', 'enable') === 0;

        if ($username === '') {
            return ldap_json_script_result(['success' => false, 'message' => ldap_feedback_message('Username is required.', 1, 0, 1)], false, 1);
        }

        try {
            return ldap_run_with_connection(function ($connection, $config) use ($username, $enable) {
                $baseDn = ldap_search_base_dn($config);
                if ($baseDn === '') {
                    throw new RuntimeException('LDAP base DN is not configured.');
                }

                $raw = ldap_resolve_user_for_handler($connection, $baseDn, $username, ['dn', 'userAccountControl']);
                if (empty($raw) || (int) ($raw['count'] ?? 0) < 1) {
                    $badge = "ERROR: User '{$username}' not found in Active Directory.";
                    return ldap_json_script_result([
                        'success' => false,
                        'message' => ldap_feedback_message($badge, 1, 0, 1, 0),
                        'userResults' => [
                            ['username' => $username, 'success' => false, 'message' => $badge],
                        ],
                        'processed' => 1, 'successCount' => 0, 'skippedCount' => 0, 'failedCount' => 1,
                    ], false, 1);
                }

                $dn = $raw[0]['dn'];
                $currentUac = (int) ($raw[0]['useraccountcontrol'][0] ?? 0);
                $flag = 2;

                if ($enable) {
                    $newUac = $currentUac & ~$flag;
                } else {
                    $newUac = $currentUac | $flag;
                }

                if ($newUac === $currentUac) {
                    $badge = $enable
                        ? "SUCCESS: User '{$username}' is already enabled."
                        : "SUCCESS: User '{$username}' is already disabled.";
                    return ldap_json_script_result([
                        'success' => true,
                        'message' => ldap_feedback_message($badge, 1, 0, 0, 1),
                        'userResults' => [
                            ['username' => $username, 'success' => true, 'message' => $badge, 'skipped' => true],
                        ],
                        'processed' => 1, 'successCount' => 0, 'skippedCount' => 1, 'failedCount' => 0,
                    ]);
                }

                $modify = [['attrib' => 'userAccountControl', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [(string) $newUac]]];
                if (!@ldap_modify_batch($connection, $dn, $modify)) {
                    throw new RuntimeException('Failed to update userAccountControl: ' . ldap_error($connection));
                }

                $badge = $enable
                    ? "SUCCESS: User '{$username}' account has been enabled."
                    : "SUCCESS: User '{$username}' account disabled.";
                return ldap_json_script_result([
                    'success' => true,
                    'message' => ldap_feedback_message($badge, 1, 1, 0, 0),
                    'userResults' => [
                        ['username' => $username, 'success' => true, 'message' => $badge],
                    ],
                    'processed' => 1, 'successCount' => 1, 'skippedCount' => 0, 'failedCount' => 0,
                ]);
            });
        } catch (Throwable $e) {
            $badge = "ERROR: " . ($enable ? 'enabling' : 'disabling') . " user '{$username}': " . $e->getMessage();
            return ldap_json_script_result([
                'success' => false,
                'message' => ldap_feedback_message($badge, 1, 0, 1, 0),
                'userResults' => [
                    ['username' => $username, 'success' => false, 'message' => $badge],
                ],
                'processed' => 1, 'successCount' => 0, 'skippedCount' => 0, 'failedCount' => 1,
            ], false, 1);
        }
    }
}

if (!function_exists('ldap_user_writer_unlock')) {
    function ldap_user_writer_unlock(array $params, string $executedBy): array
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

                $raw = ldap_resolve_user_for_handler($connection, $baseDn, $username, ['dn', 'lockoutTime']);
                if (empty($raw) || (int) ($raw['count'] ?? 0) < 1) {
                    $badge = "ERROR: User '{$username}' not found in Active Directory.";
                    return ldap_json_script_result([
                        'success' => false,
                        'message' => ldap_feedback_message($badge, 1, 0, 1, 0),
                        'userResults' => [
                            ['username' => $username, 'success' => false, 'message' => $badge],
                        ],
                        'processed' => 1, 'successCount' => 0, 'skippedCount' => 0, 'failedCount' => 1,
                    ], false, 1);
                }

                $dn = $raw[0]['dn'];
                $lockoutTime = (int) ($raw[0]['lockouttime'][0] ?? 0);

                if ($lockoutTime === 0) {
                    $badge = "SUCCESS: User '{$username}' already in unlocked state.";
                    return ldap_json_script_result([
                        'success' => true,
                        'message' => ldap_feedback_message($badge, 1, 0, 0, 1),
                        'userResults' => [
                            ['username' => $username, 'success' => true, 'message' => $badge, 'skipped' => true],
                        ],
                        'processed' => 1, 'successCount' => 0, 'skippedCount' => 1, 'failedCount' => 0,
                    ]);
                }

                $modify = [['attrib' => 'lockoutTime', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => ['0']]];
                if (!@ldap_modify_batch($connection, $dn, $modify)) {
                    throw new RuntimeException('Failed to clear lockoutTime: ' . ldap_error($connection));
                }

                $badge = "SUCCESS: User '{$username}' unlocked successfully.";
                return ldap_json_script_result([
                    'success' => true,
                    'message' => ldap_feedback_message($badge, 1, 1, 0, 0),
                    'userResults' => [
                        ['username' => $username, 'success' => true, 'message' => $badge],
                    ],
                    'processed' => 1, 'successCount' => 1, 'skippedCount' => 0, 'failedCount' => 0,
                ]);
            });
        } catch (Throwable $e) {
            $badge = "ERROR: Error unlocking user '{$username}': " . $e->getMessage();
            return ldap_json_script_result([
                'success' => false,
                'message' => ldap_feedback_message($badge, 1, 0, 1, 0),
                'userResults' => [
                    ['username' => $username, 'success' => false, 'message' => $badge],
                ],
                'processed' => 1, 'successCount' => 0, 'skippedCount' => 0, 'failedCount' => 1,
            ], false, 1);
        }
    }
}

if (!function_exists('ldap_user_writer_reset_password')) {
    function ldap_user_writer_reset_password(array $params, string $executedBy): array
    {
        $username = trim((string) ($params['Username'] ?? $params['username'] ?? ''));
        $newPassword = (string) ($params['newPassword'] ?? '');

        if ($username === '') {
            return ldap_json_script_result([
                'success' => false, 'message' => ldap_feedback_message('Username is required.', 1, 0, 1),
            ], false, 1);
        }

        try {
            return ldap_run_with_connection(function ($connection, $config) use ($username, $newPassword) {
                $baseDn = ldap_search_base_dn($config);
                if ($baseDn === '') {
                    throw new RuntimeException('LDAP base DN is not configured.');
                }

                $raw = ldap_resolve_user_for_handler($connection, $baseDn, $username, ['dn', 'useraccountcontrol']);
                if (empty($raw) || (int) ($raw['count'] ?? 0) < 1) {
                    $badge = "ERROR: User '{$username}' not found in Active Directory.";
                    return ldap_json_script_result([
                        'success' => false,
                        'message' => ldap_feedback_message($badge, 1, 0, 1),
                        'userResults' => [
                            ['username' => $username, 'success' => false, 'message' => $badge],
                        ],
                        'processed' => 1, 'successCount' => 0, 'failedCount' => 1,
                    ], false, 1);
                }

                $dn = $raw[0]['dn'];

                if ($newPassword === '') {
                    $useRandom = (bool) config_get('pwd_reset_use_random', false);
                    $newPassword = $useRandom ? ldap_random_password(12) : config_get('default_password', 'CRESET@1234');
                }

                $utf16Password = iconv('UTF-8', 'UTF-16LE', '"' . $newPassword . '"');
                if ($utf16Password === false) {
                    throw new RuntimeException('Failed to encode password to UTF-16LE.');
                }

                $modify = [['attrib' => 'unicodePwd', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$utf16Password]]];
                if (!@ldap_modify_batch($connection, $dn, $modify)) {
                    throw new RuntimeException('Failed to reset password: ' . ldap_error($connection));
                }

                $unlockModify = [['attrib' => 'lockoutTime', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => ['0']]];
                @ldap_modify_batch($connection, $dn, $unlockModify);

                // Force password change on next login
                $pwdLastSetMod = [['attrib' => 'pwdLastSet', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => ['0']]];
                @ldap_modify_batch($connection, $dn, $pwdLastSetMod);

                // Clear PASSWD_CANT_CHANGE (0x40) and DONT_EXPIRE_PASSWORD (0x10000) flags
                $currentUac = (int) ($raw[0]['useraccountcontrol'][0] ?? 0);
                if ($currentUac !== 0) {
                    $newUac = $currentUac & ~64 & ~65536;
                    if ($newUac !== $currentUac) {
                        $uacModify = [['attrib' => 'userAccountControl', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [(string) $newUac]]];
                        if (!@ldap_modify_batch($connection, $dn, $uacModify)) {
                            error_log("reset_password: Failed to clear UAC flags for {$username}: " . ldap_error($connection));
                        }
                    }
                }

                $badge = "SUCCESS: User '{$username}' password reset successful. Please try to change password with: '{$newPassword}'";
                return ldap_json_script_result([
                    'success' => true,
                    'message' => ldap_feedback_message($badge, 1, 1, 0),
                    'userResults' => [
                        ['username' => $username, 'success' => true, 'message' => $badge],
                    ],
                    'processed' => 1, 'successCount' => 1, 'failedCount' => 0,
                ]);
            });
        } catch (Throwable $e) {
            $badge = "ERROR: Error resetting password for '{$username}': " . $e->getMessage();
            return ldap_json_script_result([
                'success' => false,
                'message' => ldap_feedback_message($badge, 1, 0, 1),
                'userResults' => [
                    ['username' => $username, 'success' => false, 'message' => $badge],
                ],
                'processed' => 1, 'successCount' => 0, 'failedCount' => 1,
            ], false, 1);
        }
    }
}

if (!function_exists('ldap_random_password')) {
    function ldap_random_password(int $length = 12): string
    {
        $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lower = 'abcdefghijklmnopqrstuvwxyz';
        $digits = '0123456789';
        $special = '!@#$%&=+?';
        $all = $upper . $lower . $digits . $special;

        $password = '';
        $password .= $upper[random_int(0, strlen($upper) - 1)];
        $password .= $lower[random_int(0, strlen($lower) - 1)];
        $password .= $digits[random_int(0, strlen($digits) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
    }
}

if (!function_exists('ldap_user_writer_update')) {
    function ldap_user_writer_update(array $params, string $executedBy): array
    {
        $originalUsername = trim((string) ($params['originalUsername'] ?? $params['OriginalSamAccountName'] ?? ''));
        $newUsername = trim((string) ($params['newUsername'] ?? $params['NewSamAccountName'] ?? $originalUsername));
        $displayName = trim((string) ($params['displayName'] ?? $params['DisplayName'] ?? ''));
        $ou = trim((string) ($params['ou'] ?? $params['OU'] ?? ''));
        $description = trim((string) ($params['description'] ?? $params['Description'] ?? ''));
        $groupMembers = (string) ($params['groupMembers'] ?? $params['GroupMembers'] ?? '');
        $resetPassword = !empty($params['resetPassword'] ?? $params['ResetPassword'] ?? false);
        $forcePasswordChange = !empty($params['forcePasswordChange'] ?? $params['ForcePasswordChange'] ?? true);
        $pwdMustChange = !empty($params['pwdMustChange'] ?? false);
        $pwdCantChange = !empty($params['pwdCantChange'] ?? false);
        $pwdNeverExpires = !empty($params['pwdNeverExpires'] ?? false);
        $title = trim((string) ($params['title'] ?? $params['Title'] ?? ''));
        $department = trim((string) ($params['department'] ?? $params['Department'] ?? ''));
        $company = trim((string) ($params['company'] ?? $params['Company'] ?? ''));
        $office = trim((string) ($params['physicalDeliveryOfficeName'] ?? $params['PhysicalDeliveryOfficeName'] ?? ''));
        $phone = trim((string) ($params['telephoneNumber'] ?? $params['TelephoneNumber'] ?? ''));

        if ($originalUsername === '') {
            return ldap_json_script_result([
                'success' => false, 'message' => ldap_feedback_message('Original username is required.', 1, 0, 1),
            ], false, 1);
        }

        try {
            return ldap_run_with_connection(function ($connection, $config) use (
                $originalUsername, $newUsername, $displayName, $ou, $description, $groupMembers, $resetPassword, $forcePasswordChange, $executedBy,
                $title, $department, $company, $office, $phone
            ) {
                $baseDn = ldap_search_base_dn($config);
                if ($baseDn === '') {
                    throw new RuntimeException('LDAP base DN is not configured.');
                }

                $raw = ldap_resolve_user_for_handler($connection, $baseDn, $originalUsername, ['dn', 'sAMAccountName', 'displayName', 'description', 'cn', 'distinguishedname', 'userAccountControl', 'title', 'department', 'company', 'physicalDeliveryOfficeName', 'telephoneNumber']);
                if (empty($raw) || (int) ($raw['count'] ?? 0) < 1) {
                    $badge = "ERROR: User '{$originalUsername}' not found.";
                    return ldap_json_script_result([
                        'success' => false,
                        'message' => ldap_feedback_message($badge, 1, 0, 1),
                    ], false, 1);
                }

                $dn = $raw[0]['dn'];
                $currentDisplayName = trim((string) ($raw[0]['displayname'][0] ?? ''));
                $currentDescription = trim((string) ($raw[0]['description'][0] ?? ''));
                $changeMessages = [];

                if ($displayName !== '' && strcasecmp($displayName, $currentDisplayName) !== 0) {
                    $modify = [['attrib' => 'displayName', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$displayName]]];
                    if (@ldap_modify_batch($connection, $dn, $modify)) {
                        $changeMessages[] = "DisplayName changed from '{$currentDisplayName}' to '{$displayName}'.";
                    }
                }

                if ($description !== '' && strcasecmp($description, $currentDescription) !== 0) {
                    $modify = [['attrib' => 'description', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$description]]];
                    if (@ldap_modify_batch($connection, $dn, $modify)) {
                        $changeMessages[] = "Description changed from '{$currentDescription}' to '{$description}'.";
                    }
                }

                $currentTitle = trim((string) ($raw[0]['title'][0] ?? ''));
                $currentDepartment = trim((string) ($raw[0]['department'][0] ?? ''));
                $currentCompany = trim((string) ($raw[0]['company'][0] ?? ''));
                $currentOffice = trim((string) ($raw[0]['physicaldeliveryofficename'][0] ?? ''));
                $currentPhone = trim((string) ($raw[0]['telephonenumber'][0] ?? ''));

                if ($title !== '' && $title !== $currentTitle) {
                    $modify = [['attrib' => 'title', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$title]]];
                    if (@ldap_modify_batch($connection, $dn, $modify)) {
                        $changeMessages[] = ($currentTitle === '' ? "Title set to '{$title}'." : "Title changed from '{$currentTitle}' to '{$title}'.");
                    }
                }

                if ($department !== '' && $department !== $currentDepartment) {
                    $modify = [['attrib' => 'department', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$department]]];
                    if (@ldap_modify_batch($connection, $dn, $modify)) {
                        $changeMessages[] = ($currentDepartment === '' ? "Department set to '{$department}'." : "Department changed from '{$currentDepartment}' to '{$department}'.");
                    }
                }

                if ($company !== '' && $company !== $currentCompany) {
                    $modify = [['attrib' => 'company', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$company]]];
                    if (@ldap_modify_batch($connection, $dn, $modify)) {
                        $changeMessages[] = ($currentCompany === '' ? "Company set to '{$company}'." : "Company changed from '{$currentCompany}' to '{$company}'.");
                    }
                }

                if ($office !== '' && $office !== $currentOffice) {
                    $modify = [['attrib' => 'physicalDeliveryOfficeName', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$office]]];
                    if (@ldap_modify_batch($connection, $dn, $modify)) {
                        $changeMessages[] = ($currentOffice === '' ? "Office set to '{$office}'." : "Office changed from '{$currentOffice}' to '{$office}'.");
                    }
                }

                if ($phone !== '' && $phone !== $currentPhone) {
                    $modify = [['attrib' => 'telephoneNumber', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$phone]]];
                    if (@ldap_modify_batch($connection, $dn, $modify)) {
                        $changeMessages[] = ($currentPhone === '' ? "Phone set to '{$phone}'." : "Phone changed from '{$currentPhone}' to '{$phone}'.");
                    }
                }

                $currentParentDn = ldap_parent_dn($dn);
                $usernameChanged = $newUsername !== '' && strcasecmp($newUsername, $originalUsername) !== 0;
                $moveOu = $ou !== '' && strcasecmp($ou, $currentParentDn) !== 0;
                $finalDn = $dn;

                if ($usernameChanged || $moveOu) {
                    $newRdn = 'CN=' . ldap_escape_dn_value($displayName !== '' ? $displayName : $newUsername);
                    $newParent = $moveOu ? $ou : $currentParentDn;
                    $newDn = $newRdn . ',' . $newParent;

                    if ($usernameChanged) {
                        $samMod = [['attrib' => 'sAMAccountName', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$newUsername]]];
                        if (@ldap_modify_batch($connection, $dn, $samMod)) {
                            $changeMessages[] = "SamAccountName changed to '{$newUsername}'.";
                        }
                    }

                    if (@ldap_rename($connection, $dn, $newRdn, $newParent, true)) {
                        $finalDn = $newDn;
                        if ($moveOu) {
                            $oldOuPath = ldap_parse_ou_from_dn($dn);
                            $newOuPath = ldap_parse_ou_from_dn($newDn);
                            $changeMessages[] = "OU moved from '{$oldOuPath}' to '{$newOuPath}'.";
                        }
                    }

                    if ($usernameChanged) {
                        $domainDns = ldap_writer_domain_dns($connection);
                        if ($domainDns !== '') {
                            $upnMod = [['attrib' => 'userPrincipalName', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$newUsername . '@' . $domainDns]]];
                            @ldap_modify_batch($connection, $finalDn, $upnMod);
                        }
                    }
                }

                if ($resetPassword) {
                    $useRandom = (bool) config_get('pwd_reset_use_random', false);
                    $pwd = $useRandom ? ldap_random_password(12) : config_get('default_password', 'CRESET@1234');
                    $utf16Pwd = iconv('UTF-8', 'UTF-16LE', '"' . $pwd . '"');
                    if ($utf16Pwd !== false) {
                        $pwdMod = [['attrib' => 'unicodePwd', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$utf16Pwd]]];
                        if (@ldap_modify_batch($connection, $finalDn, $pwdMod)) {
                            $changeMessages[] = "Password changed.";
                        }
                        if ($forcePasswordChange) {
                            $resetMod = [['attrib' => 'pwdLastSet', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => ['0']]];
                            @ldap_modify_batch($connection, $finalDn, $resetMod);
                        }
                        // Clear PASSWD_CANT_CHANGE (0x40) and DONT_EXPIRE_PASSWORD (0x10000) flags
                        $currentUac = (int) ($raw[0]['useraccountcontrol'][0] ?? 0);
                        if ($currentUac !== 0) {
                            $newUac = $currentUac & ~64 & ~65536;
                            if ($newUac !== $currentUac) {
                                $uacMod = [['attrib' => 'userAccountControl', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [(string) $newUac]]];
                                @ldap_modify_batch($connection, $finalDn, $uacMod);
                            }
                        }
                    }
                }

                if ($groupMembers !== '') {
                    $desiredDns = array_filter(array_map('trim', explode(';', $groupMembers)));
                    $desiredLower = array_map('strtolower', $desiredDns);

                    // Get current group memberships
                    $memberOfSearch = @ldap_read($connection, $finalDn, '(objectClass=*)', ['memberOf'], 0, 0, 0);
                    $currentDns = [];
                    if ($memberOfSearch !== false) {
                        $mRaw = ldap_get_entries($connection, $memberOfSearch);
                        if (is_array($mRaw) && (int) ($mRaw['count'] ?? 0) > 0) {
                            $currentDns = ldap_attr_all(ldap_normalize_entry($mRaw[0]), 'memberof');
                        }
                    }
                    $currentLower = array_map('strtolower', $currentDns);

                    // Add to new groups
                    foreach ($desiredDns as $idx => $gDn) {
                        if (!in_array($desiredLower[$idx], $currentLower, true)) {
                            $memberAdd = [['attrib' => 'member', 'modtype' => LDAP_MODIFY_BATCH_ADD, 'values' => [$finalDn]]];
                            if (@ldap_modify_batch($connection, $gDn, $memberAdd)) {
                                $cnMatch = [];
                                $name = (preg_match('/^CN=([^,]+)/i', $gDn, $cnMatch)) ? $cnMatch[1] : $gDn;
                                $changeMessages[] = "Added to group '{$name}'.";
                            }
                        }
                    }

                    // Remove from deselected groups
                    foreach ($currentDns as $idx => $gDn) {
                        if (!in_array($currentLower[$idx], $desiredLower, true)) {
                            $memberRemove = [['attrib' => 'member', 'modtype' => LDAP_MODIFY_BATCH_REMOVE, 'values' => [$finalDn]]];
                            if (@ldap_modify_batch($connection, $gDn, $memberRemove)) {
                                $cnMatch = [];
                                $name = (preg_match('/^CN=([^,]+)/i', $gDn, $cnMatch)) ? $cnMatch[1] : $gDn;
                                $changeMessages[] = "Removed from group '{$name}'.";
                            }
                        }
                    }
                }

                $currentUac = (int) ($raw[0]['useraccountcontrol'][0] ?? 0);
                $uacChanged = false;

                if ($pwdMustChange) {
                    $pwdMod = [['attrib' => 'pwdLastSet', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => ['0']]];
                    if (@ldap_modify_batch($connection, $finalDn, $pwdMod)) {
                        $changeMessages[] = 'User must change password at next login.';
                    }
                }

                if ($pwdNeverExpires) {
                    $newUac = $currentUac | 65536;
                    if ($newUac !== $currentUac) {
                        $uacMod = [['attrib' => 'userAccountControl', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [(string) $newUac]]];
                        if (@ldap_modify_batch($connection, $finalDn, $uacMod)) {
                            $changeMessages[] = 'Password never expires enabled.';
                            $uacChanged = true;
                        }
                    }
                }

                if ($pwdCantChange) {
                    $newUac = $currentUac | 64;
                    if ($newUac !== $currentUac) {
                        $uacMod = [['attrib' => 'userAccountControl', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [(string) $newUac]]];
                        if (@ldap_modify_batch($connection, $finalDn, $uacMod)) {
                            $changeMessages[] = 'User cannot change password enabled.';
                            $uacChanged = true;
                        }
                    }
                }

                $successCount = !empty($changeMessages) ? 1 : 0;
                if (empty($changeMessages)) {
                    $badge = "SUCCESS: User '{$originalUsername}' checked successfully (no changes detected).";
                } else {
                    $badge = "SUCCESS: User '{$originalUsername}' updated successfully: " . implode(' ', $changeMessages);
                }

                return ldap_json_script_result([
                    'success' => true,
                    'message' => ldap_feedback_message($badge, 1, $successCount, 0, $successCount > 0 ? 0 : 1),
                    'processed' => 1, 'successCount' => $successCount, 'failedCount' => 0, 'skippedCount' => $successCount > 0 ? 0 : 1,
                ]);
            });
        } catch (Throwable $e) {
            $badge = "ERROR: Error updating user '{$originalUsername}': " . $e->getMessage();
            return ldap_json_script_result([
                'success' => false,
                'message' => ldap_feedback_message($badge, 1, 0, 1),
            ], false, 1);
        }
    }
}

if (!function_exists('ldap_writer_hrms_api')) {
    function ldap_writer_hrms_api(string $empId): ?array
    {
        if (!function_exists('getHRMSInfo')) {
            return null;
        }
        $result = getHRMSInfo($empId);
        return $result['success'] ? ($result['apiData'] ?? null) : null;
    }
}

if (!function_exists('ldap_writer_hrms_employees_by_status')) {
    function ldap_writer_hrms_employees_by_status(string $status): array
    {
        if (!function_exists('getHRMSEmployeesByStatus')) {
            return ['success' => false, 'employees' => [], 'message' => 'HRMS module not available.'];
        }
        return getHRMSEmployeesByStatus($status);
    }
}

if (!function_exists('ldap_writer_domain_dns')) {
    function ldap_writer_domain_dns($connection): string
    {
        // Priority 1: configured upn_suffix from domain naming config
        $config = function_exists('ldap_read_config') ? ldap_read_config() : [];
        $naming = $config['naming'] ?? [];
        $upnSuffix = trim($naming['upn_suffix'] ?? '');
        if ($upnSuffix !== '') {
            return $upnSuffix;
        }

        // Priority 2: extract from AD root DSE
        $search = @ldap_read($connection, '', '(objectClass=*)', ['dn'], 0, 0, 0);
        if ($search !== false) {
            $entries = ldap_get_entries($connection, $search);
            if (is_array($entries) && (int) ($entries['count'] ?? 0) > 0) {
                $rootDn = $entries[0]['dn'] ?? '';
                $parts = array_filter(explode(',', $rootDn), function ($p) {
                    return stripos(trim($p), 'DC=') === 0;
                });
                return implode('.', array_map(function ($p) {
                    return substr(trim($p), 3);
                }, $parts));
            }
        }

        // Priority 3: extract from domain config base_dn
        $baseDn = $config['base_dn'] ?? '';
        if ($baseDn !== '') {
            $parts = array_filter(explode(',', $baseDn), function ($p) {
                return stripos(trim($p), 'DC=') === 0;
            });
            if (!empty($parts)) {
                return implode('.', array_map(function ($p) {
                    return substr(trim($p), 3);
                }, $parts));
            }
        }

        return '';
    }
}

if (!function_exists('ldap_user_writer_create')) {
    function ldap_user_writer_create(array $params, string $executedBy): array
    {
        $username = trim((string) ($params['Username'] ?? $params['username'] ?? ''));
        if ($username === '') {
            $errMsg = 'ERROR: Username is required.';
            return ldap_json_script_result([
                'success' => false,
                'message' => ldap_feedback_message($errMsg, 1, 0, 0, 1),
                'userResults' => [
                    ['username' => '', 'success' => false, 'message' => $errMsg, 'skipped' => true],
                ],
                'processed' => 1, 'successCount' => 0, 'skippedCount' => 1, 'failedCount' => 0,
            ], false, 1);
        }

        $isServiceAccount = !empty($params['IsServiceAccount']);
        $serverOperation = trim((string) ($params['ServerOperation'] ?? ''));
        $passwordNeverExpires = !empty($params['PasswordNeverExpires']);
        $enableMailbox = !empty($params['EnableMailbox']);

        ldap_write_transcript_log("", '');
        ldap_write_transcript_log("==========================================================================================", '');
        ldap_write_transcript_log(">>> START: User Creation — '{$username}'", $username);
        ldap_write_transcript_log("==========================================================================================", '');

        if ($isServiceAccount) {
            $empCode = $username;
            $fullName = trim((string) ($params['DisplayName'] ?? $username));
            $email = trim((string) ($params['Email'] ?? ''));
            $mobile = '';
            $designation = '';
            $department = '';
            $office = '';
            $rank = '';
            $company = '';
            $section = trim((string) ($params['OU'] ?? ''));
            $product = '';
            $subSection = '';
            $hrmsData = null;
        } else {
            $hrmsData = ldap_writer_hrms_api($username);
            ldap_write_transcript_log("HRMS data " . ($hrmsData !== null ? "fetched successfully" : "not found, using form fields") . " for user", $username);

            $hasFormFields = !empty($params['DisplayName']) || !empty($params['OU']);

            if ($hrmsData === null && !$hasFormFields) {
                $errMsg = "ERROR: Failed to retrieve HRMS data for employee '{$username}'.";
                return ldap_json_script_result([
                    'success' => false,
                    'message' => ldap_feedback_message($errMsg, 1, 0, 0, 1),
                    'userResults' => [
                        ['username' => $username, 'success' => false, 'message' => $errMsg, 'skipped' => true],
                    ],
                    'processed' => 1, 'successCount' => 0, 'skippedCount' => 1, 'failedCount' => 0,
                ], false, 1);
            }

            if ($hrmsData !== null) {
                $empCode = $hrmsData['EMP_CODE'] ?? $username;
                $fullName = trim($hrmsData['EMP_NAME'] ?? '');
                $email = $hrmsData['EMAIL'] ?? '';
                $mobile = $hrmsData['MOBILE'] ?? '';
                $designation = $hrmsData['DESIGNATION'] ?? '';
                $department = $hrmsData['DEPARTMENT_TITLE'] ?? '';
                $office = $hrmsData['LOCATION_TITLE'] ?? '';
                $rank = $hrmsData['RANK'] ?? '';
                $company = $hrmsData['OPERATING_UNIT_TITLE'] ?? '';
                $section = $hrmsData['SECTION_TITLE'] ?? '';
                $product = $hrmsData['PRODUCT_TITLE'] ?? '';
                $subSection = $hrmsData['SUB_SECTION_TITLE'] ?? '';
            } else {
                $empCode = $username;
                $fullName = trim((string) ($params['DisplayName'] ?? $username));
                $email = trim((string) ($params['Email'] ?? ''));
                $mobile = '';
                $designation = '';
                $department = '';
                $office = '';
                $rank = '';
                $company = '';
                $section = trim((string) ($params['OU'] ?? ''));
                $product = '';
                $subSection = '';
            }

            $generatedUsername = $empCode;
        }

        try {
            return ldap_run_with_connection(function ($connection, $config) use (
                $username, $empCode, $generatedUsername, $fullName, $email, $mobile,
                $designation, $department, $office, $rank, $company, $section, $product, $subSection, $executedBy, $params,
                $isServiceAccount, $serverOperation, $passwordNeverExpires
            ) {
                $namingConfig = $config['naming'] ?? [];
                // Service accounts: honor the Admin-typed logon ID as-is (mirrors the
                // PowerShell New-ADUser path). Regenerating from DisplayName appends
                // the empCode and can exceed AD's 20-char sAMAccountName limit.
                $generatedUsername = $isServiceAccount
                    ? ldap_clamp_sam_account_name($username, $username)
                    : ldap_generate_username_from_name($fullName, $empCode, $namingConfig);

                $baseDn = ldap_search_base_dn($config);
                if ($baseDn === '') {
                    throw new RuntimeException('LDAP base DN is not configured.');
                }
                $baseDn = preg_replace('/,\s+/', ',', trim($baseDn));

                // Resolve OU path
                $explicitOu = trim((string) ($params['OU'] ?? ''));
                $hasExplicitOu = $explicitOu !== '' && stripos($explicitOu, '=') !== false;

                if ($hasExplicitOu) {
                    $expectedOuPath = $explicitOu;
                    $ouHierarchy = explode(',', $explicitOu);
                    $ouHierarchy = array_map(function ($rdn) {
                                        $parts = explode('=', $rdn, 2);
                                        return trim($parts[1] ?? $rdn);
                                    }, array_filter($ouHierarchy, function ($rdn) {
                                        return stripos(trim($rdn), 'OU=') === 0;
                                    }));
                    $ouHierarchy = array_reverse($ouHierarchy);
                } else {
                    $ouHierarchy = array_filter([$company, $department, $section, $product, $subSection], function ($v) {
                        return $v !== '' && $v !== 'N/A' && $v !== null;
                    });

                    $expectedOuPath = $baseDn;
                    foreach ($ouHierarchy as $ouName) {
                        $safeName = preg_replace('/[\/\\\[\]:;|=,+*?<>@]/', '', $ouName);
                        $safeName = str_replace('&', 'and', $safeName);
                        $ouDn = 'OU=' . ldap_escape_dn_value($safeName) . ',' . $expectedOuPath;

                        $ouSearch = @ldap_read($connection, $ouDn, '(objectClass=*)', ['dn'], 0, 0, 0);
                        if ($ouSearch === false) {
                            ldap_write_transcript_log("OU '{$safeName}' not found. Creating it...", $empCode);
                            $ouEntry = [
                                'objectClass' => ['top', 'organizationalUnit'],
                                'ou' => $safeName,
                                'name' => $safeName,
                            ];
                            if (!@ldap_add($connection, $ouDn, $ouEntry)) {
                                ldap_write_transcript_log("Failed to create OU '{$safeName}'", $empCode);
                                continue;
                            }
                            ldap_write_transcript_log("OU '{$safeName}' created.", $empCode);
                        }
                        $expectedOuPath = $ouDn;
                    }
                }

                ldap_write_transcript_log("OU path resolved: " . implode(' > ', $ouHierarchy), $empCode);

                // Check if user already exists
                $escaped = ldap_escape_filter_value($generatedUsername);
                $filter = "(&(objectCategory=person)(objectClass=user)(sAMAccountName={$escaped}))";
                $search = @ldap_search($connection, $baseDn, $filter, ['dn', 'userAccountControl', 'distinguishedname', 'displayName', 'mail', 'telephoneNumber', 'title', 'physicalDeliveryOfficeName']);
                if ($search === false) {
                    throw new RuntimeException('LDAP search failed: ' . ldap_error($connection));
                }
                $raw = ldap_get_entries($connection, $search);
                $userExists = is_array($raw) && (int) ($raw['count'] ?? 0) > 0;

                // Fallback: if not found by generated username, search by the raw entered username (AD logon ID)
                $foundByRawFallback = false;
                if (!$userExists && $generatedUsername !== $username) {
                    $rawEscaped = ldap_escape_filter_value($username);
                    $rawFilter = "(&(objectCategory=person)(objectClass=user)(sAMAccountName={$rawEscaped}))";
                    $rawSearch = @ldap_search($connection, $baseDn, $rawFilter, ['dn', 'userAccountControl', 'distinguishedname', 'displayName', 'mail', 'telephoneNumber', 'title', 'physicalDeliveryOfficeName']);
                    if ($rawSearch !== false) {
                        $rawEntries = ldap_get_entries($connection, $rawSearch);
                        if (is_array($rawEntries) && (int) ($rawEntries['count'] ?? 0) > 0) {
                            $userExists = true;
                            $raw = $rawEntries;
                            $generatedUsername = $username;
                            $foundByRawFallback = true;
                            ldap_write_transcript_log("User found by raw username '{$username}' fallback.", $empCode);
                        }
                    }
                }

                if ($userExists) {
                    ldap_write_transcript_log("User '{$empCode}' already exists in AD. Checking OU and applying corrections.", $empCode);
                    $existingDn = $raw[0]['dn'];
                    $currentUac = (int) ($raw[0]['useraccountcontrol'][0] ?? 0);
                    $isDisabled = $currentUac & 2;
                    $actionsTaken = [];
                    $isMoved = false;

                    // Skip HRMS-dependent operations when found by raw username (HRMS data may be unreliable)
                    if (!$foundByRawFallback) {
                    // --- OU move: compare current OU with expected OU path (normalize spaces and case) ---
                    $currentOu = preg_replace('/^CN=[^,]+,/i', '', $existingDn);
                    $normCurrent = preg_replace('/,\s*/', ',', trim($currentOu));
                    $normExpected = preg_replace('/,\s*/', ',', trim($expectedOuPath));
                    if (strcasecmp($normCurrent, $normExpected) !== 0) {
                        ldap_write_transcript_log("OU mismatch — Normalized Current: '{$normCurrent}' vs Expected: '{$normExpected}'. Moving user...", $empCode);
                        $newDn = 'CN=' . ldap_escape_dn_value(trim($fullName ?: $empCode)) . ',' . $expectedOuPath;
                        $rdn = 'CN=' . ldap_escape_dn_value(trim($fullName ?: $empCode));
                        if (@ldap_rename($connection, $existingDn, $rdn, $expectedOuPath, true)) {
                            $moveMessage = 'Moved to correct OU Path: ' . implode(' > ', $ouHierarchy);
                            $actionsTaken[] = 'ACTION: ' . $moveMessage;
                            $isMoved = true;
                            $existingDn = $newDn;
                            ldap_write_transcript_log("OU move successful: '{$moveMessage}'", $empCode);
                        } else {
                            ldap_write_transcript_log("OU move FAILED for user", $empCode);
                        }
                    }

                    // --- Update info from HRMS if available ---
                    if ($hrmsData !== null) {
                        $infoUpdates = [];
                        $currentDisplayName = trim((string) ($raw[0]['displayname'][0] ?? ''));
                        $currentEmail = trim((string) ($raw[0]['mail'][0] ?? ''));
                        $currentMobile = trim((string) ($raw[0]['telephonenumber'][0] ?? ''));
                        $currentTitle = trim((string) ($raw[0]['title'][0] ?? ''));
                        $currentOffice = trim((string) ($raw[0]['physicaldeliveryofficename'][0] ?? ''));

                        $hrmsDisplayName = trim($fullName);
                        $hrmsEmail = trim($email);
                        $hrmsMobile = trim($mobile);
                        $hrmsTitle = trim($designation);
                        $hrmsOffice = trim($office);

                        if ($currentDisplayName !== $hrmsDisplayName && $hrmsDisplayName !== '') {
                            $infoUpdates[] = ['attrib' => 'displayName', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$hrmsDisplayName]];
                        }
                        if (strcasecmp($currentEmail, $hrmsEmail) !== 0 && $hrmsEmail !== '') {
                            $infoUpdates[] = ['attrib' => 'mail', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$hrmsEmail]];
                        }
                        if ($currentMobile !== $hrmsMobile && $hrmsMobile !== '') {
                            $infoUpdates[] = ['attrib' => 'telephoneNumber', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$hrmsMobile]];
                        }
                        if ($currentTitle !== $hrmsTitle && $hrmsTitle !== '') {
                            $infoUpdates[] = ['attrib' => 'title', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$hrmsTitle]];
                        }
                        if ($currentOffice !== $hrmsOffice && $hrmsOffice !== '') {
                            $infoUpdates[] = ['attrib' => 'physicalDeliveryOfficeName', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$hrmsOffice]];
                        }

                        if (!empty($infoUpdates)) {
                            if (@ldap_modify_batch($connection, $existingDn, $infoUpdates)) {
                                $actionsTaken[] = 'Updated user info from HRMS';
                                ldap_write_transcript_log("Updated user info from HRMS", $empCode);
                            }
                        }
                    }
                    } else {
                        ldap_write_transcript_log("Skipping OU check and HRMS update — found by raw username with potentially unreliable HRMS data.", $empCode);
                    }

                    // --- Enable if disabled ---
                    if ($isDisabled) {
                        ldap_write_transcript_log("Account is disabled. Enabling...", $empCode);
                        $modify = [['attrib' => 'userAccountControl', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [(string) ($currentUac & ~2)]]];
                        if (@ldap_modify_batch($connection, $existingDn, $modify)) {
                            $actionsTaken[] = 'Enabled previously disabled account.';
                            ldap_write_transcript_log("Account enabled successfully.", $empCode);
                        }
                    }

                    // --- Reset password ---
                    $newPassword = config_get('default_password', 'CRESET@1234');
                    $utf16Pwd = iconv('UTF-8', 'UTF-16LE', '"' . $newPassword . '"');
                    if ($utf16Pwd !== false) {
                        ldap_write_transcript_log("Resetting password...", $empCode);
                        $pwdModify = [['attrib' => 'unicodePwd', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$utf16Pwd]]];
                        if (@ldap_modify_batch($connection, $existingDn, $pwdModify)) {
                            $tempPassword = $newPassword;
                            $actionsTaken[] = 'Quick Action:> Password reset triggered. ' . ($empCode !== $generatedUsername ? "HRMS ID '{$empCode}' — AD ID '{$generatedUsername}'" : "User '{$empCode}'") . ' has been reset and Unlocked With the Temporary Password \'' . $tempPassword . '\'';
                            ldap_write_transcript_log("Password reset successful.", $empCode);
                            // Force password change on next login
                            $pwdLastSetMod = [['attrib' => 'pwdLastSet', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => ['0']]];
                            @ldap_modify_batch($connection, $existingDn, $pwdLastSetMod);
                            ldap_write_transcript_log("Force password change on next login enabled.", $empCode);
                        }
                    }

                    // --- Add to security group(s) ---
                    $manualGroups = trim((string) ($params['GroupMembers'] ?? ''));
                    if ($manualGroups !== '') {
                        ldap_write_transcript_log("Adding user to specified groups...", $empCode);
                        $groupDns = explode(';', $manualGroups);
                        foreach ($groupDns as $gd) {
                            $gd = trim($gd);
                            if ($gd === '') continue;
                            $addMember = [['attrib' => 'member', 'modtype' => LDAP_MODIFY_BATCH_ADD, 'values' => [$existingDn]]];
                            if (@ldap_modify_batch($connection, $gd, $addMember)) {
                                $cnMatch = [];
                                $name = preg_match('/^CN=([^,]+)/i', $gd, $cnMatch) ? $cnMatch[1] : $gd;
                                $actionsTaken[] = 'Added to group: ' . $name;
                                ldap_write_transcript_log("Added to group: '{$name}'", $empCode);
                            }
                        }
                    } else {
                        $assignedOu = $ouHierarchy ? end($ouHierarchy) : '';
                        if ($assignedOu !== '') {
                            $safeOu = preg_replace('/[\/\\\[\]:;|=,+*?<>@]/', '', $assignedOu);
                            $safeOu = str_replace('&', 'and', $safeOu);
                            $groupName = $safeOu . ' Group';
                            ldap_write_transcript_log("Looking up OU group '{$groupName}'...", $empCode);
                            $groupDn = null;
                            $groupFilter = "(&(objectClass=group)(name={$groupName}))";
                            $groupSearch = @ldap_search($connection, $baseDn, $groupFilter, ['dn'], 0, 1);
                            if ($groupSearch !== false) {
                                $groupRaw = ldap_get_entries($connection, $groupSearch);
                                if (is_array($groupRaw) && (int) ($groupRaw['count'] ?? 0) > 0) {
                                    $groupDn = $groupRaw[0]['dn'];
                                }
                            }
                            if ($groupDn === null) {
                                ldap_write_transcript_log("Group '{$groupName}' not found. Creating it...", $empCode);
                                $groupDn = 'CN=' . ldap_escape_dn_value($groupName) . ',' . $expectedOuPath;
                                $groupEntry = [
                                    'objectClass' => ['top', 'group'],
                                    'cn' => $groupName,
                                    'sAMAccountName' => $groupName,
                                    'groupType' => '-2147483646',
                                ];
                                if (!@ldap_add($connection, $groupDn, $groupEntry)) {
                                    $groupDn = null;
                                    ldap_write_transcript_log("Failed to create group '{$groupName}'", $empCode);
                                } else {
                                    ldap_write_transcript_log("Group '{$groupName}' created.", $empCode);
                                }
                            }
                            if ($groupDn !== null) {
                                $addMember = [['attrib' => 'member', 'modtype' => LDAP_MODIFY_BATCH_ADD, 'values' => [$existingDn]]];
                                if (@ldap_modify_batch($connection, $groupDn, $addMember)) {
                                    $actionsTaken[] = 'Added to OU group: ' . $groupName;
                                    ldap_write_transcript_log("Added to OU group '{$groupName}'", $empCode);
                                }
                            }
                        }
                    }

                    // --- Build final message ---
                    ldap_write_transcript_log(">>> COMPLETE: Existing user actions finished.", $empCode);
                    ldap_write_transcript_log("==========================================================================================", '');
                    if ($isMoved) {
                        $overallMsg = "Warning!!: " . ($empCode !== $generatedUsername ? "HRMS ID '{$empCode}' — AD ID '{$generatedUsername}'" : "User '{$empCode}'") . " already exists With older object location.";
                    } else {
                        $overallMsg = "Warning!!: " . ($empCode !== $generatedUsername ? "HRMS ID '{$empCode}' — AD ID '{$generatedUsername}'" : "User '{$empCode}'") . " already exists With preferred object location.";
                    }
                    if (!empty($actionsTaken)) {
                        $overallMsg .= "\n" . implode("\n", $actionsTaken);
                    } else {
                        $overallMsg .= "\nNo further actions required.";
                    }

                    return ldap_json_script_result([
                        'success' => true,
                        'message' => ldap_feedback_message($overallMsg, 1, 0, 0, 1),
                        'userResults' => [
                            ['username' => $empCode, 'success' => true, 'message' => $overallMsg, 'skipped' => true],
                        ],
                        'processed' => 1, 'successCount' => 0, 'skippedCount' => 1, 'failedCount' => 0,
                    ]);
                }

                // --- Create new user ---
                $parsedName = ldap_parse_full_name($fullName, $namingConfig, $empCode);
                $firstName = $parsedName['given_name'];
                $lastName = $parsedName['surname'];
                $displayName = $parsedName['display_name'];

                $domainDns = ldap_writer_domain_dns($connection);
                $upn = $generatedUsername . '@' . ($domainDns ?: 'domain.local');

                $cnValue = trim($displayName ?: $fullName ?: $empCode);
                $userDn = 'CN=' . ldap_escape_dn_value($cnValue) . ',' . $expectedOuPath;
                $defaultPassword = config_get('default_password', 'CRESET@1234');
                $utf16Pwd = iconv('UTF-8', 'UTF-16LE', '"' . $defaultPassword . '"');

                $userEntry = [
                    'objectClass' => ['top', 'person', 'organizationalPerson', 'user'],
                    'sAMAccountName' => $generatedUsername,
                    'userPrincipalName' => $upn,
                    'cn' => $cnValue,
                    'givenName' => $firstName,
                    'sn' => $lastName ?: $firstName,
                    'displayName' => $displayName ?: $fullName ?: $empCode,
                    // Create disabled (514) first: AD rejects an ENABLED account (512)
                    // created without a unicodePwd in the same operation — it surfaces as
                    // a generic "Other (e.g., implementation specific) error". The account
                    // is enabled (and UAC finalized) right after the password is set below.
                    'userAccountControl' => '514',
                ];

                if ($email !== '') {
                    $userEntry['mail'] = $email;
                }
                if ($mobile !== '') {
                    $userEntry['telephoneNumber'] = $mobile;
                }
                if ($designation !== '') {
                    $userEntry['title'] = $designation;
                }
                if ($department !== '') {
                    $userEntry['department'] = $department;
                }
                if ($company !== '') {
                    $userEntry['company'] = $company;
                }
                if ($office !== '') {
                    $userEntry['physicalDeliveryOfficeName'] = $office;
                }

                ldap_write_transcript_log("Creating user entry in AD at OU: " . implode(' > ', $ouHierarchy), $empCode);
                if (!@ldap_add($connection, $userDn, $userEntry)) {
                    $errDetail = ldap_error($connection);
                    $diag = '';
                    @ldap_get_option($connection, defined('LDAP_OPT_DIAGNOSTIC_MESSAGE') ? LDAP_OPT_DIAGNOSTIC_MESSAGE : 0x0032, $diag);
                    ldap_write_transcript_log("FAILED to create user entry: {$errDetail}" . ($diag !== '' ? " (diag: {$diag})" : ''), $empCode);
                    throw new RuntimeException('Failed to create user: ' . $errDetail . ($diag !== '' ? " ({$diag})" : ''));
                }
                ldap_write_transcript_log("User entry created successfully in AD.", $empCode);

                // Set password
                if ($utf16Pwd !== false) {
                    ldap_write_transcript_log("Setting password...", $empCode);
                    $pwdModify = [['attrib' => 'unicodePwd', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$utf16Pwd]]];
                    @ldap_modify_batch($connection, $userDn, $pwdModify);
                    ldap_write_transcript_log("Password set.", $empCode);
                }

                // Enable account (and set DONT_EXPIRE_PASSWORD for service accounts)
                ldap_write_transcript_log("Enabling account...", $empCode);
                if ($isServiceAccount) {
                    $uacValue = $passwordNeverExpires ? '66048' : '512';
                    $enableModify = [['attrib' => 'userAccountControl', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$uacValue]]];
                    @ldap_modify_batch($connection, $userDn, $enableModify);
                } else {
                    $enableModify = [['attrib' => 'userAccountControl', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => ['512']]];
                    @ldap_modify_batch($connection, $userDn, $enableModify);

                    // Force password change on next login (not for service accounts)
                    $pwdLastSetMod = [['attrib' => 'pwdLastSet', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => ['0']]];
                    @ldap_modify_batch($connection, $userDn, $pwdLastSetMod);
                }
                ldap_write_transcript_log("Account enabled successfully.", $empCode);

                // Enable Exchange mailbox if requested
                if ($enableMailbox && $generatedUsername !== '') {
                    ldap_write_transcript_log("Enable-Mailbox requested for '{$generatedUsername}'", $empCode);
                    $exchangePsPath = __DIR__ . '/../../Infrastructure/PowerShell/ExchangePsRunner.php';
                    if (file_exists($exchangePsPath)) {
                        require_once $exchangePsPath;
                        if (function_exists('exchange_enable_mailbox')) {
                            try {
                                $mbResult = exchange_enable_mailbox($generatedUsername);
                                $decoded = $mbResult['decoded'] ?? [];
                                if (!empty($decoded['success']) || $mbResult['success']) {
                                    ldap_write_transcript_log("Mailbox enabled successfully for '{$generatedUsername}'", $empCode);
                                } else {
                                    $errMsg = $decoded['message'] ?? 'Unknown error';
                                    ldap_write_transcript_log("FAILED to enable mailbox for '{$generatedUsername}': {$errMsg}", $empCode);
                                }
                            } catch (Throwable $mbEx) {
                                ldap_write_transcript_log("Exception enabling mailbox: " . $mbEx->getMessage(), $empCode);
                            }
                        } else {
                            ldap_write_transcript_log("exchange_enable_mailbox() not available.", $empCode);
                        }
                    } else {
                        ldap_write_transcript_log("ExchangePsRunner.php not found — mailbox creation deferred.", $empCode);
                    }
                }

                // Set description
                $formDesc = trim((string) ($params['Description'] ?? ''));
                if ($formDesc !== '') {
                    $descModify = [['attrib' => 'description', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$formDesc]]];
                    @ldap_modify_batch($connection, $userDn, $descModify);
                } elseif ($isServiceAccount && $serverOperation !== '') {
                    $descValue = 'Service Account for ' . $serverOperation;
                    $descModify = [['attrib' => 'description', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$descValue]]];
                    @ldap_modify_batch($connection, $userDn, $descModify);
                } else {
                    $rankStr = $rank !== '' ? "Rank: {$rank} | " : '';
                    $descValue = $rankStr . 'OU: ' . implode(' > ', $ouHierarchy);
                    $descModify = [['attrib' => 'description', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$descValue]]];
                    @ldap_modify_batch($connection, $userDn, $descModify);
                }

                // --- Add user to group(s) ---
                $addedGroupNames = [];
                $assignedOu = $ouHierarchy ? end($ouHierarchy) : '';
                $manualGroups = trim((string) ($params['GroupMembers'] ?? ''));
                if ($manualGroups !== '') {
                    ldap_write_transcript_log("Adding user to specified groups...", $empCode);
                    $groupDns = explode(';', $manualGroups);
                    foreach ($groupDns as $gd) {
                        $gd = trim($gd);
                        if ($gd === '') continue;
                        $addMember = [['attrib' => 'member', 'modtype' => LDAP_MODIFY_BATCH_ADD, 'values' => [$userDn]]];
                        if (@ldap_modify_batch($connection, $gd, $addMember)) {
                            $cnMatch = [];
                            $name = (preg_match('/^CN=([^,]+)/i', $gd, $cnMatch)) ? $cnMatch[1] : $gd;
                            $addedGroupNames[] = $name;
                            ldap_write_transcript_log("Added to group: '{$name}'", $empCode);
                        }
                    }
                } else {
                    if ($assignedOu !== '') {
                            $safeOu = preg_replace('/[\/\\\[\]:;|=,+*?<>@]/', '', $assignedOu);
                            $safeOu = str_replace('&', 'and', $safeOu);
                            $groupName = $safeOu . ' Group';
                            ldap_write_transcript_log("Looking up OU group '{$groupName}'...", $empCode);
                            $groupDn = null;
                            $groupFilter = "(&(objectClass=group)(name={$groupName}))";
                            $groupSearch = @ldap_search($connection, $baseDn, $groupFilter, ['dn'], 0, 1);
                            if ($groupSearch !== false) {
                                $groupRaw = ldap_get_entries($connection, $groupSearch);
                                if (is_array($groupRaw) && (int) ($groupRaw['count'] ?? 0) > 0) {
                                    $groupDn = $groupRaw[0]['dn'];
                                }
                            }
                            if ($groupDn === null) {
                                ldap_write_transcript_log("Group '{$groupName}' not found. Creating it...", $empCode);
                                $groupDn = 'CN=' . ldap_escape_dn_value($groupName) . ',' . $expectedOuPath;
                                $groupEntry = [
                                    'objectClass' => ['top', 'group'],
                                    'cn' => $groupName,
                                    'sAMAccountName' => $groupName,
                                    'groupType' => '-2147483646',
                                ];
                                if (!@ldap_add($connection, $groupDn, $groupEntry)) {
                                    $groupDn = null;
                                    ldap_write_transcript_log("Failed to create group '{$groupName}'", $empCode);
                                } else {
                                    ldap_write_transcript_log("Group '{$groupName}' created.", $empCode);
                                }
                            }
                            if ($groupDn !== null) {
                                $addMember = [['attrib' => 'member', 'modtype' => LDAP_MODIFY_BATCH_ADD, 'values' => [$userDn]]];
                                if (@ldap_modify_batch($connection, $groupDn, $addMember)) {
                                    $addedGroupNames[] = $groupName;
                                    ldap_write_transcript_log("Added to OU group '{$groupName}'", $empCode);
                                }
                            }
                        }
                }

                $groupLabel = !empty($addedGroupNames) ? implode(', ', $addedGroupNames) : 'N/A';
                $idLabel = $empCode !== $generatedUsername ? "HRMS ID '{$empCode}' — AD ID '{$generatedUsername}'" : "User ID '{$empCode}'";
                $successMsg = "Success: {$idLabel} Display Name '{$fullName}' created successfully. Temporary Pass: '{$defaultPassword}' \n in OU: '{$assignedOu}'. Group: '{$groupLabel}'.";
                ldap_write_transcript_log(">>> COMPLETE: User '{$empCode}' creation finished. Group: '{$groupLabel}'", $empCode);
                ldap_write_transcript_log("==========================================================================================", '');

                return ldap_json_script_result([
                    'success' => true,
                    'message' => ldap_feedback_message($successMsg, 1, 1, 0, 0),
                    'userResults' => [
                        ['username' => $empCode, 'success' => true, 'message' => $successMsg],
                    ],
                    'processed' => 1, 'successCount' => 1, 'skippedCount' => 0, 'failedCount' => 0,
                ]);
            });
        } catch (Throwable $e) {
            ldap_write_transcript_log(">>> FAILED: " . $e->getMessage(), $username ?? '');
            ldap_write_transcript_log("==========================================================================================", '');
            $badge = 'ERROR: Error creating user: ' . $e->getMessage();
            return ldap_json_script_result([
                'success' => false,
                'message' => ldap_feedback_message($badge, 1, 0, 1),
            ], false, 1);
        }
    }
}
