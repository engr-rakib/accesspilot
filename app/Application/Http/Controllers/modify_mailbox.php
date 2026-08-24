<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Infrastructure/PowerShell/powershell_runner.php';
require_once __DIR__ . '/../../../Infrastructure/PowerShell/ExchangePsRunner.php';
require_once __DIR__ . '/../../../Ldap/Config/ldap_config_repository.php';
require_once __DIR__ . '/../../../Ldap/Support/ldap_helpers.php';
require_once __DIR__ . '/../../../Ldap/Operations/ldap_user_repository.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Close session early — Exchange PS calls are slow and block other requests
session_write_close();

if (!defined('API_GATEWAY')) {
    die('Direct access not permitted');
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_status':        handle_get_exchange_status();       break;
        case 'save_mailbox':       handle_save_mailbox();             break;
        case 'create_mailbox':     handle_create_mailbox();           break;
        case 'disable_mailbox':    handle_disable_mailbox();          break;
        case 'enable_mailbox':     handle_enable_mailbox();           break;
        case 'save_profile':       handle_save_profile();             break;
        case 'set_quota':          handle_set_quota();                break;
        case 'set_forward':        handle_set_forward();              break;
        case 'set_primary_smtp':   handle_set_primary_smtp();         break;
        case 'add_email':          handle_add_email();                break;
        case 'remove_email':       handle_remove_email();             break;
        case 'set_litigation_hold': handle_set_litigation_hold();     break;
        case 'get_stats':          handle_get_mailbox_stats();        break;
        case 'get_dist_groups':    handle_get_dist_groups();          break;
        case 'add_dist_group_member': handle_add_dist_group_member(); break;
        case 'remove_dist_group_member': handle_remove_dist_group_member(); break;
        case 'get_cas':            handle_get_cas();                  break;
        case 'set_cas':            handle_set_cas();                  break;
        case 'set_hidden_gal':     handle_set_hidden_gal();           break;
        case 'enable_archive':     handle_enable_archive();           break;
        case 'disable_archive':    handle_disable_archive();          break;
        case 'get_mobile_devices': handle_get_mobile_devices();       break;
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    error_log("modify_mailbox.php Fatal: " . $e->getMessage());
}

function handle_save_mailbox(): void
{
    $identity = trim($_POST['identity'] ?? '');
    $alias = trim($_POST['alias'] ?? '');
    $primarySmtp = trim($_POST['primary_smtp'] ?? '');
    $hiddenGal = $_POST['hidden_gal'] ?? '';
    $addProxies = $_POST['add_proxies'] ?? '';
    $removeProxies = $_POST['remove_proxies'] ?? '';

    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required']);
        return;
    }

    $results = [];
    $hasError = false;

    // Update alias
    if ($alias !== '') {
        $r = exchange_set_mailbox_alias($identity, $alias);
        if (!empty($r['decoded']['success']) || !empty($r['success'])) {
            $results[] = "Alias updated";
        } else {
            $results[] = "Alias failed: " . ($r['decoded']['message'] ?? 'unknown');
            $hasError = true;
        }
    }

    // Update primary SMTP
    if ($primarySmtp !== '') {
        $r = exchange_set_primary_smtp($identity, $primarySmtp);
        if (!empty($r['decoded']['success']) || !empty($r['success'])) {
            $results[] = "Primary SMTP updated";
        } else {
            $results[] = "Primary SMTP failed: " . ($r['decoded']['message'] ?? 'unknown');
            $hasError = true;
        }
    }

    // Hidden from GAL
    if ($hiddenGal !== '') {
        $r = exchange_set_hidden_from_gal($identity, $hiddenGal === 'true');
        if (!empty($r['decoded']['success']) || !empty($r['success'])) {
            $results[] = "GAL visibility " . ($hiddenGal === 'true' ? 'hidden' : 'shown');
        } else {
            $results[] = "GAL update failed: " . ($r['decoded']['message'] ?? 'unknown');
            $hasError = true;
        }
    }

    // Remove proxy addresses
    if ($removeProxies !== '') {
        $toRemove = preg_split('/[\s,;]+/', $removeProxies, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($toRemove as $proxy) {
            $r = exchange_remove_email_address($identity, $proxy);
            if (!empty($r['decoded']['success']) || !empty($r['success'])) {
                $results[] = "Removed $proxy";
            } else {
                $results[] = "Failed to remove $proxy: " . ($r['decoded']['message'] ?? 'unknown');
                $hasError = true;
            }
        }
    }

    // Add proxy addresses
    if ($addProxies !== '') {
        $toAdd = preg_split('/[\s,;]+/', $addProxies, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($toAdd as $proxy) {
            $r = exchange_add_email_address($identity, $proxy);
            if (!empty($r['decoded']['success']) || !empty($r['success'])) {
                $results[] = "Added $proxy";
            } else {
                $results[] = "Failed to add $proxy: " . ($r['decoded']['message'] ?? 'unknown');
                $hasError = true;
            }
        }
    }

    echo json_encode([
        'success' => !$hasError,
        'message' => implode('; ', $results),
    ]);
}

function handle_create_mailbox(): void
{
    $identity = trim($_POST['identity'] ?? '');
    $database = trim($_POST['database'] ?? '');
    $alias = trim($_POST['alias'] ?? '');

    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required']);
        return;
    }

    $result = exchange_enable_mailbox($identity, $database);
    $decoded = $result['decoded'] ?? [];

    if (!empty($decoded['success']) || $result['success']) {
        if ($alias !== '') {
            $aliasResult = exchange_set_mailbox_alias($identity, $alias);
            if (empty($aliasResult['success']) && empty(($aliasResult['decoded'] ?? [])['success'])) {
                echo json_encode(['success' => true, 'message' => 'Mailbox created, but alias update failed.']);
                return;
            }
        }
        echo json_encode(['success' => true, 'message' => 'Mailbox created successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? 'Failed to create mailbox.']);
    }
}

function handle_get_dist_groups(): void
{
    $username = trim($_POST['username'] ?? '');
    if ($username === '') {
        echo json_encode(['success' => false, 'message' => 'Username required', 'groups' => []]);
        return;
    }

    try {
        $ldap = ldap_connect_and_bind();
        $conn = $ldap['connection'];
        $config = $ldap['config'];
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage(), 'groups' => []]);
        return;
    }

    $baseDn = $config['base_dn'] ?? '';
    if ($baseDn === '') {
        echo json_encode(['success' => false, 'message' => 'Base DN not configured', 'groups' => []]);
        return;
    }

    $escaped = ldap_escape_filter_value($username);
    $userFilter = "(|(sAMAccountName={$escaped})(userPrincipalName={$escaped}@*))";
    $sr = @ldap_search($conn, $baseDn, $userFilter, ['dn', 'memberOf'], 0, 1);
    if (!$sr) {
        echo json_encode(['success' => false, 'message' => 'User not found', 'groups' => []]);
        return;
    }
    $entries = ldap_get_entries($conn, $sr);
    if ($entries['count'] === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found', 'groups' => []]);
        return;
    }

    $memberOf = [];
    if (!empty($entries[0]['memberof'])) {
        for ($i = 0; $i < $entries[0]['memberof']['count']; $i++) {
            $memberOf[] = $entries[0]['memberof'][$i];
        }
    }

    if (empty($memberOf)) {
        echo json_encode(['success' => true, 'groups' => []]);
        return;
    }

    $distGroups = [];
    foreach ($memberOf as $dn) {
        $gsr = @ldap_read($conn, $dn, '(objectClass=group)', ['dn', 'cn', 'mail', 'groupType', 'msExchRecipientTypeDetails'], 0, 1);
        if (!$gsr) continue;
        $gentries = ldap_get_entries($conn, $gsr);
        if ($gentries['count'] === 0) continue;

        $g = $gentries[0];
        $groupType = isset($g['grouptype'][0]) ? (int)$g['grouptype'][0] : 0;
        $recipType = isset($g['msexchrecipienttypedetails'][0]) ? (int)$g['msexchrecipienttypedetails'][0] : 0;

        $isDistGroup = ($groupType & 2) !== 0 || $recipType === 4 || $recipType === 8;
        if ($isDistGroup) {
            $distGroups[] = [
                'dn' => $dn,
                'name' => $g['cn'][0] ?? $dn,
                'email' => $g['mail'][0] ?? '',
            ];
        }
    }

    @ldap_close($conn);
    echo json_encode(['success' => true, 'groups' => $distGroups]);
}

function handle_add_dist_group_member(): void
{
    $group = trim($_POST['group'] ?? '');
    $member = trim($_POST['member'] ?? '');
    if ($group === '' || $member === '') {
        echo json_encode(['success' => false, 'message' => 'Group and member are required']);
        return;
    }
    $result = exchange_add_group_member($group, $member);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => 'Member added to distribution group.']);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? 'Failed to add member.']);
    }
}

function handle_remove_dist_group_member(): void
{
    $group = trim($_POST['group'] ?? '');
    $member = trim($_POST['member'] ?? '');
    if ($group === '' || $member === '') {
        echo json_encode(['success' => false, 'message' => 'Group and member are required']);
        return;
    }
    $result = exchange_remove_group_member($group, $member);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => 'Member removed from distribution group.']);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? 'Failed to remove member.']);
    }
}

// ===== New Exchange action handlers =====

function handle_get_exchange_status(): void
{
    $config = ldap_read_config();
    $exConfig = $config['exchange'] ?? [];
    // Same logic as nav rail: enabled by default if not explicitly set to false
    $enabled = !isset($exConfig['enabled']) || !empty($exConfig['enabled']);
    $databases = [];
    if ($enabled && function_exists('exchange_get_databases')) {
        $r = exchange_get_databases();
        $d = $r['decoded'] ?? [];
        if (!empty($d) && is_array($d)) {
            foreach ($d as $db) {
                if (is_array($db)) {
                    $databases[] = [
                        'name' => $db['Name'] ?? $db['name'] ?? '',
                        'server' => $db['Server'] ?? $db['server'] ?? '',
                    ];
                }
            }
        }
    }
    echo json_encode([
        'success' => true,
        'exchange_enabled' => $enabled,
        'databases' => $databases,
        'config' => [
            'enabled' => $enabled,
            'server_override' => $exConfig['server_override'] ?? '',
        ],
    ]);
}

function handle_disable_mailbox(): void
{
    $identity = trim($_POST['identity'] ?? '');
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required']);
        return;
    }
    $result = exchange_disable_mailbox($identity);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Mailbox disabled for '{$identity}'."]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? 'Failed to disable mailbox.']);
    }
}

function handle_enable_mailbox(): void
{
    $identity = trim($_POST['identity'] ?? '');
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required']);
        return;
    }
    $database = trim($_POST['database'] ?? '');
    $result = exchange_enable_mailbox($identity, $database);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Mailbox enabled for '{$identity}'."]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? 'Failed to enable mailbox.']);
    }
}

function handle_save_profile(): void
{
    $identity = trim($_POST['identity'] ?? '');
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required']);
        return;
    }

    $allowed = [
        'givenName', 'initials', 'sn', 'displayName',
        'physicalDeliveryOfficeName', 'telephoneNumber',
        'title', 'department', 'company', 'fullName',
    ];
    $fields = [];
    foreach ($allowed as $k) {
        if (isset($_POST[$k])) {
            $v = trim($_POST[$k]);
            if ($v !== '') $fields[$k] = $v;
        }
    }

    $ldapResult = ldap_run_with_connection(function ($connection, $config) use ($identity, &$fields) {
        $baseDn = ldap_search_base_dn($config);
        if ($baseDn === '') {
            return ['success' => false, 'message' => 'LDAP base DN not configured.'];
        }
        $entry = ldap_user_lookup_entry($connection, $baseDn, $identity);
        if ($entry === null) {
            return ['success' => false, 'message' => "User '{$identity}' not found."];
        }
        $dn = $entry['dn'] ?? '';
        if ($dn === '') {
            return ['success' => false, 'message' => 'User DN not found.'];
        }

        // Handle CN update (rename) via fullName
        $newCn = $fields['fullName'] ?? '';
        unset($fields['fullName']);
        if ($newCn !== '') {
            $currentCn = ldap_get_values_len($connection, $dn, 'cn');
            $currentCnVal = ($currentCn && $currentCn['count'] > 0) ? $currentCn[0] : '';
            if ($currentCnVal !== $newCn) {
                $parentDn = ldap_explode_dn($dn, 1);
                $rdns = [];
                foreach ($parentDn as $rdn) {
                    if (stripos($rdn, 'cn=') === 0) continue;
                    $rdns[] = $rdn;
                }
                $newParentDn = implode(',', $rdns);
                $newRdn = 'CN=' . ldap_escape($newCn, '', LDAP_ESCAPE_DN);
                $newDn = $newRdn . ',' . $newParentDn;
                if (!@ldap_rename($connection, $dn, $newRdn, $newParentDn, true)) {
                    return ['success' => false, 'message' => 'Rename (CN) failed: ' . ldap_error($connection)];
                }
                $dn = $newDn;
            }
        }

        // Handle sAMAccountName (logon name) change via LDAP modify
        $newSam = trim($_POST['samAccountName'] ?? '');
        if ($newSam !== '') {
            $fields['samAccountName'] = $newSam;
        }

        $mods = [];
        foreach ($fields as $attr => $val) {
            $mods[] = [
                'attrib' => $attr,
                'modtype' => defined('LDAP_MODIFY_BATCH_REPLACE') ? LDAP_MODIFY_BATCH_REPLACE : 3,
                'values' => [$val],
            ];
        }
        if ($mods && !@ldap_modify_batch($connection, $dn, $mods)) {
            return ['success' => false, 'message' => 'LDAP update failed: ' . ldap_error($connection)];
        }

        // Handle OU move
        $newOU = trim($_POST['ou'] ?? '');
        if ($newOU !== '') {
            $oldParent = ldap_explode_dn($dn, 1);
            array_shift($oldParent);
            $oldParentDn = implode(',', $oldParent);
            if (strcasecmp($oldParentDn, $newOU) !== 0) {
                if (!@ldap_rename($connection, $dn, ldap_explode_dn($dn, 1)[0], $newOU, true)) {
                    return ['success' => false, 'message' => 'OU move failed: ' . ldap_error($connection)];
                }
            }
        }

        return ['success' => true, 'message' => 'Profile updated.'];
    });

    $alias = trim($_POST['alias'] ?? '');
    if (!empty($ldapResult['success']) && $alias !== '') {
        if (function_exists('exchange_set_mailbox_alias')) {
            $aliasResult = exchange_set_mailbox_alias($identity, $alias);
            $ad = $aliasResult['decoded'] ?? [];
            if (empty($ad['success']) && empty($aliasResult['success'])) {
                $ldapResult['success'] = false;
                $ldapResult['message'] = 'Alias update failed.';
            }
        }
    }

    // Mailbox database move
    if (!empty($ldapResult['success'])) {
        $mbDb = trim($_POST['mailbox_database'] ?? '');
        if ($mbDb !== '' && function_exists('exchange_set_mailbox_database')) {
            $dbResult = exchange_set_mailbox_database($identity, $mbDb);
            $dd = $dbResult['decoded'] ?? [];
            if (empty($dd['success']) && empty($dbResult['success'])) {
                $ldapResult['success'] = false;
                $ldapResult['message'] = 'Mailbox database move failed.';
            }
        }
    }

    echo json_encode($ldapResult);
}

function handle_set_quota(): void
{
    $identity = trim($_POST['identity'] ?? '');
    $warn = trim($_POST['issue_warning_quota'] ?? '5');
    $send = trim($_POST['prohibit_send_quota'] ?? '6');
    $recv = trim($_POST['prohibit_send_receive_quota'] ?? '8');
    $unit = strtoupper(trim($_POST['quota_unit'] ?? 'GB'));
    if (!in_array($unit, ['MB', 'GB', 'TB'], true)) $unit = 'GB';

    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required']);
        return;
    }

    $warn = preg_match('/^\d+(?:\.\d+)?$/', $warn) ? $warn . $unit : $warn;
    $send = preg_match('/^\d+(?:\.\d+)?$/', $send) ? $send . $unit : $send;
    $recv = preg_match('/^\d+(?:\.\d+)?$/', $recv) ? $recv . $unit : $recv;

    $result = exchange_set_mailbox_quota($identity, $warn, $send, $recv);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Quota updated for '{$identity}'."]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? 'Quota update failed.']);
    }
}

function handle_set_forward(): void
{
    $identity = trim($_POST['identity'] ?? '');
    $forwardTo = trim($_POST['forward_to'] ?? '');
    $deliverToMailbox = !empty($_POST['deliver_to_mailbox']);

    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required']);
        return;
    }

    $result = exchange_set_forwarding($identity, $forwardTo, $deliverToMailbox);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        $msg = $forwardTo ? "Forwarding set to {$forwardTo}." : 'Forwarding cleared.';
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? 'Forwarding update failed.']);
    }
}

function handle_set_primary_smtp(): void
{
    $identity = trim($_POST['identity'] ?? '');
    $email = trim($_POST['email'] ?? '');
    if ($identity === '' || $email === '') {
        echo json_encode(['success' => false, 'message' => 'Identity and email are required']);
        return;
    }
    $result = exchange_set_primary_smtp($identity, $email);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Primary SMTP set to {$email}."]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? 'Primary SMTP update failed.']);
    }
}

function handle_add_email(): void
{
    $identity = trim($_POST['identity'] ?? '');
    $email = trim($_POST['email'] ?? '');
    if ($identity === '' || $email === '') {
        echo json_encode(['success' => false, 'message' => 'Identity and email are required']);
        return;
    }
    $result = exchange_add_email_address($identity, $email);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Email {$email} added."]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? 'Failed to add email.']);
    }
}

function handle_remove_email(): void
{
    $identity = trim($_POST['identity'] ?? '');
    $email = trim($_POST['email'] ?? '');
    if ($identity === '' || $email === '') {
        echo json_encode(['success' => false, 'message' => 'Identity and email are required']);
        return;
    }
    $result = exchange_remove_email_address($identity, $email);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => "Email {$email} removed."]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? 'Failed to remove email.']);
    }
}

function handle_set_litigation_hold(): void
{
    $identity = trim($_POST['identity'] ?? '');
    $enabled = !empty($_POST['enabled']);
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required']);
        return;
    }
    $result = exchange_set_litigation_hold($identity, $enabled);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        $msg = $enabled ? 'Litigation Hold enabled.' : 'Litigation Hold disabled.';
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? 'Operation failed.']);
    }
}

function handle_get_mailbox_stats(): void
{
    $identity = trim($_POST['identity'] ?? '');
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required', 'stats' => null]);
        return;
    }

    // Single PS call runs all 4 cmdlets — 1 PSSession instead of 4
    $allResult = exchange_get_all_mailbox_data($identity);
    $decoded = $allResult['decoded'] ?? [];
    if (empty($decoded) || isset($decoded['success']) || empty($decoded['mb'])) {
        // Combined call failed — fallback to individual calls
        $sr = exchange_get_mailbox_statistics($identity);
        $statsDecoded = isset($sr['decoded'][0]) ? $sr['decoded'][0] : ($sr['decoded'] ?? []);
        $mr = exchange_get_mailbox($identity);
        $mbDecoded = isset($mr['decoded'][0]) ? $mr['decoded'][0] : ($mr['decoded'] ?? []);
        $cr = exchange_get_cas_mailbox($identity);
        $casDecoded = isset($cr['decoded'][0]) ? $cr['decoded'][0] : ($cr['decoded'] ?? []);
        $ar = exchange_get_archive($identity);
        $archDecoded = isset($ar['decoded'][0]) ? $ar['decoded'][0] : ($ar['decoded'] ?? []);
    } else {
        $statsDecoded = isset($decoded['stats'][0]) ? $decoded['stats'][0] : ($decoded['stats'] ?? []);
        $mbDecoded = isset($decoded['mb'][0]) ? $decoded['mb'][0] : ($decoded['mb'] ?? []);
        $casDecoded = isset($decoded['cas'][0]) ? $decoded['cas'][0] : ($decoded['cas'] ?? []);
        $archDecoded = isset($decoded['arch'][0]) ? $decoded['arch'][0] : ($decoded['arch'] ?? []);
    }

    $stats = null;
    if (!empty($statsDecoded) && is_array($statsDecoded)) {
        if ((!isset($statsDecoded['success']) || !empty($statsDecoded['success'])) && isset($statsDecoded['TotalItemSize'])) {
            $s = $statsDecoded;
            $stats = [
                'total_item_size' => $s['TotalItemSize'] ?? 'N/A',
                'item_count' => $s['ItemCount'] ?? 'N/A',
                'total_deleted_item_size' => $s['TotalDeletedItemSize'] ?? 'N/A',
                'database_name' => $s['DatabaseName'] ?? 'N/A',
                'database_issue_warning_quota' => $s['DatabaseIssueWarningQuota'] ?? 'N/A',
                'database_prohibit_send_quota' => $s['DatabaseProhibitSendQuota'] ?? 'N/A',
                'database_prohibit_send_receive_quota' => $s['DatabaseProhibitSendReceiveQuota'] ?? 'N/A',
                'issue_warning_quota' => $s['IssueWarningQuota'] ?? 'N/A',
                'prohibit_send_quota' => $s['ProhibitSendQuota'] ?? 'N/A',
                'prohibit_send_receive_quota' => $s['ProhibitSendReceiveQuota'] ?? 'N/A',
                'use_database_quota_defaults' => $s['UseDatabaseQuotaDefaults'] ?? null,
            ];
        }
    }

    $mailbox = null;
    if (!empty($mbDecoded) && is_array($mbDecoded)) {
        $m = $mbDecoded;
        $mailbox = [
            'forwarding_smtp' => $m['ForwardingSmtpAddress'] ?? '',
            'forwarding_enabled' => !empty($m['DeliverToMailboxAndForward']),
            'litigation_hold' => !empty($m['LitigationHoldEnabled']),
            'hidden_from_gal' => !empty($m['HiddenFromAddressListsEnabled']),
            'recipient_type' => $m['RecipientTypeDetails'] ?? '',
            'archive_status' => $m['ArchiveStatus'] ?? '',
            'archive_database' => $m['ArchiveDatabase'] ?? '',
            'archive_name' => $m['ArchiveName'] ?? '',
            'database' => $m['Database'] ?? '',
            'issue_warning_quota' => $m['IssueWarningQuota'] ?? '',
            'prohibit_send_quota' => $m['ProhibitSendQuota'] ?? '',
            'prohibit_send_receive_quota' => $m['ProhibitSendReceiveQuota'] ?? '',
            'use_database_quota_defaults' => $m['UseDatabaseQuotaDefaults'] ?? null,
        ];
    }

    $cas = null;
    if (!empty($casDecoded) && is_array($casDecoded)) {
        $c = $casDecoded;
        $cas = [
            'active_sync_enabled' => !empty($c['ActiveSyncEnabled']),
            'owa_enabled' => !empty($c['OWAEnabled']),
            'owa_for_devices_enabled' => !empty($c['OWAforDevicesEnabled']),
            'mapi_enabled' => !empty($c['MAPIEnabled']),
            'pop_enabled' => !empty($c['POPEnabled']),
            'imap_enabled' => !empty($c['IMAPEnabled']),
            'ews_enabled' => !empty($c['EWSEnabled']),
        ];
    }

    $archive = null;
    if (!empty($archDecoded) && is_array($archDecoded)) {
        $a = $archDecoded;
        $archive = [
            'archive_status' => $a['ArchiveStatus'] ?? '',
            'archive_database' => $a['ArchiveDatabase'] ?? '',
            'archive_name' => $a['ArchiveName'] ?? '',
            'archive_quota' => $a['ArchiveQuota'] ?? '',
            'archive_warning_quota' => $a['ArchiveWarningQuota'] ?? '',
        ];
    }

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'mailbox' => $mailbox,
        'cas' => $cas,
        'archive' => $archive,
    ]);
}

// ===== CAS settings (ActiveSync, OWA) =====

function handle_get_cas(): void
{
    $identity = trim($_POST['identity'] ?? '');
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required']);
        return;
    }
    $result = exchange_get_cas_mailbox($identity);
    $decoded = $result['decoded'] ?? [];
    $d = $decoded[0] ?? $decoded;
    echo json_encode([
        'success' => true,
        'cas' => [
            'active_sync_enabled' => !empty($d['ActiveSyncEnabled']),
            'owa_enabled' => !empty($d['OWAEnabled']),
            'owa_for_devices_enabled' => !empty($d['OWAforDevicesEnabled']),
            'mapi_enabled' => !empty($d['MAPIEnabled']),
            'pop_enabled' => !empty($d['POPEnabled']),
            'imap_enabled' => !empty($d['IMAPEnabled']),
            'ews_enabled' => !empty($d['EWSEnabled']),
        ],
        'raw' => $d,
    ]);
}

function handle_set_cas(): void
{
    $identity = trim($_POST['identity'] ?? '');
    $setting = trim($_POST['setting'] ?? '');
    $enabled = !empty($_POST['enabled']);
    if ($identity === '' || $setting === '') {
        echo json_encode(['success' => false, 'message' => 'Identity and setting are required']);
        return;
    }
    // Map snake_case from JS to PascalCase for PowerShell
    $psParamMap = [
        'active_sync_enabled' => 'ActiveSyncEnabled',
        'owa_enabled' => 'OWAEnabled',
        'owa_for_devices_enabled' => 'OWAforDevicesEnabled',
        'mapi_enabled' => 'MAPIEnabled',
        'pop_enabled' => 'POPEnabled',
        'imap_enabled' => 'IMAPEnabled',
        'ews_enabled' => 'EWSEnabled',
    ];
    $psParam = $psParamMap[$setting] ?? '';
    if ($psParam === '') {
        echo json_encode(['success' => false, 'message' => 'Unknown setting: ' . $setting]);
        return;
    }
    $result = exchange_set_cas_mailbox($identity, [$psParam => $enabled]);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => ucfirst($setting) . ' ' . ($enabled ? 'enabled' : 'disabled') . '.']);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? 'Operation failed.']);
    }
}

function handle_set_hidden_gal(): void
{
    $identity = trim($_POST['identity'] ?? '');
    $hidden = !empty($_POST['hidden']);
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required']);
        return;
    }
    $result = exchange_set_hidden_from_gal($identity, $hidden);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => 'GAL visibility ' . ($hidden ? 'hidden' : 'shown') . '.']);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? 'Operation failed.']);
    }
}

// ===== Archive operations =====

function handle_enable_archive(): void
{
    $identity = trim($_POST['identity'] ?? '');
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required']);
        return;
    }
    $result = exchange_enable_archive($identity);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => 'Archive enabled for ' . $identity]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? 'Failed to enable archive.']);
    }
}

function handle_disable_archive(): void
{
    $identity = trim($_POST['identity'] ?? '');
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required']);
        return;
    }
    $result = exchange_disable_archive($identity);
    $decoded = $result['decoded'] ?? [];
    if (!empty($decoded['success']) || $result['success']) {
        echo json_encode(['success' => true, 'message' => 'Archive disabled for ' . $identity]);
    } else {
        echo json_encode(['success' => false, 'message' => $decoded['message'] ?? 'Failed to disable archive.']);
    }
}

function handle_get_mobile_devices(): void
{
    $identity = trim($_POST['identity'] ?? '');
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required', 'devices' => []]);
        return;
    }
    $result = exchange_get_mobile_device_statistics($identity);
    $decoded = $result['decoded'] ?? [];
    $devices = [];
    if (!empty($decoded) && is_array($decoded)) {
        foreach ($decoded as $d) {
            $devices[] = [
                'friendly_name' => $d['FriendlyName'] ?? $d['DeviceType'] ?? 'Unknown',
                'device_type' => $d['DeviceType'] ?? '',
                'device_id' => $d['DeviceId'] ?? '',
                'last_sync_time' => $d['LastSyncTime'] ?? null,
                'first_sync_time' => $d['FirstSyncTime'] ?? null,
                'status' => $d['Status'] ?? $d['DeviceState'] ?? 'Unknown',
                'device_model' => $d['DeviceModel'] ?? $d['DeviceOS'] ?? '',
                'device_os' => $d['DeviceOS'] ?? '',
                'user_agent' => $d['ClientVersion'] ?? $d['UserAgent'] ?? '',
                'last_ping_time' => $d['LastPingTime'] ?? null,
                'last_success_sync' => $d['LastSuccessSync'] ?? null,
                'number_of_syncs' => $d['NumberOfSyncs'] ?? 0,
                'recovery_password' => $d['RecoveryPassword'] ?? '',
            ];
        }
    }
    echo json_encode(['success' => true, 'devices' => $devices]);
}
