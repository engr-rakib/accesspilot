<?php

if (!function_exists('ldap_adapt_member_payload')) {
    function ldap_adapt_member_payload(array $entry, string $objectClass): array
    {
        $sam = ldap_first_attr($entry, 'samaccountname', '');
        $displayName = $objectClass === 'user'
            ? ldap_first_attr($entry, 'displayname', '')
            : ldap_first_attr($entry, 'name', ldap_first_attr($entry, 'cn', ''));
        $name = trim($displayName) !== '' ? $displayName : $sam;
        if ($name === '') {
            $name = ldap_first_attr($entry, 'cn', '');
        }

        $payload = [
            'Name' => $name,
            'DisplayName' => $displayName,
            'SamAccountName' => $sam,
            'DistinguishedName' => ldap_first_attr($entry, 'distinguishedname', $entry['dn'] ?? ''),
            'ObjectClass' => $objectClass,
            'Identifier' => $sam !== '' ? $sam : $name,
        ];

        $employeeId = ldap_first_attr($entry, 'employeeid', '');
        if ($employeeId !== '') {
            $payload['EmployeeID'] = $employeeId;
        }

        return $payload;
    }
}

if (!function_exists('ldap_adapt_get_ad_user_info')) {
    function ldap_adapt_get_ad_user_info(array $entry): array
    {
        $dn = ldap_first_attr($entry, 'distinguishedname', $entry['dn'] ?? '');
        $memberOf = ldap_attr_all($entry, 'memberof');
        if (empty($memberOf) && isset($entry['memberof']) && is_string($entry['memberof'])) {
            $memberOf = [$entry['memberof']];
        }

        $uac = (int) ldap_first_attr($entry, 'useraccountcontrol', '0');
        $pwdLastSet = ldap_first_attr($entry, 'pwdlastset', '0');
        $lockoutTime = ldap_first_attr($entry, 'lockouttime', '0');
        $badPwdTime = ldap_first_attr($entry, 'badpasswordtime', '0');
        $accountExpires = ldap_first_attr($entry, 'accountexpires', '0');
        $lastLogon = ldap_first_attr($entry, 'lastlogontimestamp', '0');
        $lastLogoff = ldap_first_attr($entry, 'lastlogoff', '0');

        // Extract OU path from DN
        $ouParts = [];
        $dnParts = ldap_explode_dn($dn, 1);
        if (is_array($dnParts)) {
            foreach ($dnParts as $part) {
                if (stripos($part, 'OU=') === 0) {
                    $ouParts[] = substr($part, 3);
                }
            }
        }
        if (empty($ouParts)) {
            // Fallback: extract OUs via regex
            preg_match_all('/OU=([^,]+)/i', $dn, $ouMatches);
            $ouParts = $ouMatches[1] ?? [];
        }
        $ouLocation = $ouParts ? implode(' > ', array_reverse($ouParts)) : '';

        // Format group memberships → privileges
        $privileges = [];
        foreach ($memberOf as $groupDn) {
            $cn = '';
            if (preg_match('/^CN=([^,]+)/i', $groupDn, $m)) {
                $cn = $m[1];
            }
            if ($cn !== '') {
                $privileges[] = $cn;
            }
        }

        // Exchange mailbox parsing
        $proxyAddresses = ldap_attr_all($entry, 'proxyaddresses');
        $primarySmtp = '';
        $secondaryAddresses = [];
        foreach ($proxyAddresses as $addr) {
            if (preg_match('/^(smtp|SMTP|x500):(.+)$/i', $addr, $m)) {
                $isPrimary = $m[1] === 'SMTP';
                $entryArr = ['address' => $m[2], 'type' => $m[1], 'is_primary' => $isPrimary];
                if ($isPrimary) {
                    $primarySmtp = $m[2];
                    array_unshift($secondaryAddresses, $entryArr);
                } else {
                    $secondaryAddresses[] = $entryArr;
                }
            }
        }

        $mailboxGuidRaw = ldap_first_attr($entry, 'msexchmailboxguid', '');
        $hasMailbox = $mailboxGuidRaw !== '';
        // Convert binary GUID to human-readable hex string to avoid JSON UTF-8 issues
        $mailboxGuid = $mailboxGuidRaw;
        if ($mailboxGuidRaw !== '' && strlen($mailboxGuidRaw) === 16) {
            $hex = bin2hex($mailboxGuidRaw);
            $mailboxGuid = substr($hex, 6, 2) . substr($hex, 4, 2) . substr($hex, 2, 2) . substr($hex, 0, 2)
                . '-' . substr($hex, 10, 2) . substr($hex, 8, 2)
                . '-' . substr($hex, 14, 2) . substr($hex, 12, 2)
                . '-' . substr($hex, 16, 4)
                . '-' . substr($hex, 20, 12);
        } elseif ($mailboxGuidRaw !== '') {
            $mailboxGuid = bin2hex($mailboxGuidRaw);
        }
        $recipientTypeDetails = (int) ldap_first_attr($entry, 'msexchrecipienttypedetails', '0');
        $recipientTypeMap = [
            1 => 'UserMailbox', 2 => 'SharedMailbox', 4 => 'DistributionGroup',
            8 => 'MailUniversalSecurityGroup', 16 => 'RoomMailbox', 32 => 'EquipmentMailbox',
            64 => 'PublicFolder', 128 => 'SystemMailbox', 256 => 'LinkedMailbox',
            512 => 'RemoteUserMailbox', 2147483648 => 'DisabledUser',
        ];

        $user = [
            'SamAccountName' => ldap_first_attr($entry, 'samaccountname', ''),
            'DisplayName' => ldap_first_attr($entry, 'displayname', ldap_first_attr($entry, 'cn', 'N/A')),
            'DistinguishedName' => $dn,
            'Description' => ldap_first_attr($entry, 'description', 'N/A'),
            'OU' => ldap_parse_ou_from_dn($dn),
            'ouLocation' => $ouLocation,
            'MemberOf' => implode(';', $memberOf),
            'assignedPrivileges' => $privileges,

            // Identity
            'userPrincipalName' => ldap_first_attr($entry, 'userprincipalname', ''),
            'fullName' => ldap_first_attr($entry, 'cn', ''),
            'employeeID' => ldap_first_attr($entry, 'employeeid', 'N/A'),

            // Account control
            'accountStatus' => $uac & 2 ? 'Disabled' : 'Enabled',
            'accountLockStatus' => ldap_decode_lockout_status($lockoutTime),
            'lockoutTime' => ldap_convert_nt_time($lockoutTime),
            'passwordStatus' => ldap_decode_password_status($uac, $pwdLastSet),
            'lastPasswordReset' => ldap_convert_nt_time($pwdLastSet),
            'passwordExpiryDate' => 'N/A',
            'accountExpirationDate' => ldap_convert_nt_time($accountExpires),
            'securityFlags' => ldap_decode_uac_status($uac),

            // Activity
            'totalWrongPassAttemptCount' => ldap_first_attr($entry, 'badpwdcount', '0'),
            'wrongPassAttemptCountLast12h' => ldap_convert_nt_time($badPwdTime),
            'lastPasswordAttemptDateTime' => $badPwdTime !== '0' ? ldap_convert_nt_time($badPwdTime) : 'N/A',
            'totalLogonCount' => ldap_first_attr($entry, 'logoncount', '0'),
            'lastLogin' => ldap_convert_nt_time($lastLogon),
            'lastLogoffTime' => ldap_convert_nt_time($lastLogoff),
            'logonWorkstation' => ldap_first_attr($entry, 'logonworkstation', 'N/A'),
            'lastLogonWorkstation' => 'N/A',

            // Infrastructure
            'homeDirectory' => ldap_first_attr($entry, 'homedirectory', 'N/A'),
            'homeDrive' => ldap_first_attr($entry, 'homedrive', 'N/A'),
            'profilePath' => ldap_first_attr($entry, 'profilepath', 'N/A'),
            'logonScript' => ldap_first_attr($entry, 'scriptpath', 'N/A'),

            // Profiling
            'accountCreatedOn' => ldap_convert_generalized_time(ldap_first_attr($entry, 'whencreated', 'N/A')),
            'accountCreatedBy' => 'N/A',
            'provisionOperatorName' => 'N/A',

            // Contact
            'jobTitle' => ldap_first_attr($entry, 'title', 'N/A'),
            'manager' => ldap_first_attr($entry, 'manager', 'N/A'),
            'company' => ldap_first_attr($entry, 'company', 'N/A'),
            'phoneNumber' => ldap_first_attr($entry, 'telephonenumber', 'N/A'),
            'emailAddress' => ldap_first_attr($entry, 'mail', 'N/A'),
            'department' => ldap_first_attr($entry, 'department', 'N/A'),

            // Extended User Info
            'firstName' => ldap_first_attr($entry, 'givenname', 'N/A'),
            'initials' => ldap_first_attr($entry, 'initials', ''),
            'lastName' => ldap_first_attr($entry, 'sn', 'N/A'),
            'office' => ldap_first_attr($entry, 'physicaldeliveryofficename', 'N/A'),
            'webPage' => ldap_first_attr($entry, 'wwwhomepage', 'N/A'),
            'streetAddress' => ldap_first_attr($entry, 'streetaddress', 'N/A'),
            'postOfficeBox' => ldap_first_attr($entry, 'postofficebox', 'N/A'),
            'postalCode' => ldap_first_attr($entry, 'postalcode', 'N/A'),
            'city' => ldap_first_attr($entry, 'l', 'N/A'),
            'state' => ldap_first_attr($entry, 'st', 'N/A'),
            'country' => ldap_first_attr($entry, 'co', 'N/A'),

            // UAC flags for form pre-population
            'pwdMustChange' => (int) $pwdLastSet === 0 && !($uac & 1048576) && !($uac & 32),
            'pwdCantChange' => (bool) ($uac & 64),
            'pwdNeverExpires' => (bool) ($uac & 65536),

            // Exchange / Mailbox information
            'exchange_mailbox' => [
                'has_mailbox' => $hasMailbox,
                'mailbox_guid' => $mailboxGuid,
                'alias' => ldap_first_attr($entry, 'mailnickname', ''),
                'primary_smtp' => $primarySmtp,
                'proxy_addresses' => $secondaryAddresses,
                'home_database' => ldap_first_attr($entry, 'msexchhomeservername', ''),
                'recipient_type' => $recipientTypeMap[$recipientTypeDetails] ?? 'Unknown',
                'recipient_type_details' => $recipientTypeDetails,
                'hidden_from_gal' => (int) ldap_first_attr($entry, 'msexchrecipientdisplaytype', '0') === -2147483642,
                'when_created' => ldap_convert_generalized_time(ldap_first_attr($entry, 'msexchwhenmailboxcreated', '')),
                'archive_guid' => ldap_first_attr($entry, 'msexcharchivemailboxguid', ''),
                'archive_name' => ldap_first_attr($entry, 'msexcharchivename', ''),
                'mailbox_disabled' => ((int) ldap_first_attr($entry, 'msexchuseraccountcontrol', '0') & 2) === 2,
                'quota_use_database_defaults' => strtolower(ldap_first_attr($entry, 'mdbusedefaults', 'TRUE')) !== 'false',
                'issue_warning_quota_kb' => ldap_first_attr($entry, 'mdbstoragequota', ''),
                'prohibit_send_quota_kb' => ldap_first_attr($entry, 'mdboverquotalimit', ''),
                'prohibit_send_receive_quota_kb' => ldap_first_attr($entry, 'mdboverhardquotalimit', ''),
                'issue_warning_quota' => ldap_exchange_format_quota_kb(ldap_first_attr($entry, 'mdbstoragequota', '')),
                'prohibit_send_quota' => ldap_exchange_format_quota_kb(ldap_first_attr($entry, 'mdboverquotalimit', '')),
                'prohibit_send_receive_quota' => ldap_exchange_format_quota_kb(ldap_first_attr($entry, 'mdboverhardquotalimit', '')),
            ],
        ];

        return $user;
    }
}

if (!function_exists('ldap_exchange_format_quota_kb')) {
    function ldap_exchange_format_quota_kb(string $value): string
    {
        $value = trim($value);
        if ($value === '' || !is_numeric($value)) {
            return '';
        }
        $kb = (float) $value;
        if ($kb <= 0) {
            return 'Unlimited';
        }
        $mb = $kb / 1024;
        if ($mb >= 1024) {
            $gb = $mb / 1024;
            return rtrim(rtrim(number_format($gb, 2, '.', ''), '0'), '.') . ' GB';
        }
        return rtrim(rtrim(number_format($mb, 2, '.', ''), '0'), '.') . ' MB';
    }
}

if (!function_exists('ldap_adapt_tree_row')) {
    function ldap_adapt_tree_row(string $name, string $dn, string $type, ?string $parent = null, array $extra = []): array
    {
        return array_merge([
            'Name' => $name,
            'DistinguishedName' => $dn,
            'Type' => $type,
            'Parent' => $parent,
        ], $extra);
    }
}
