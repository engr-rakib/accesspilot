<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Infrastructure/PowerShell/powershell_runner.php';
require_once __DIR__ . '/../../../Ldap/Config/ldap_config_repository.php';
require_once __DIR__ . '/../../../Ldap/Operations/ldap_directory_writer.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.']);
    exit();
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

if (!(has_permission('modify_ad_user') || has_permission('action_submit_manual_create'))) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to modify AD groups.']);
    exit();
}

if (!defined('API_GATEWAY')) {
    die('Direct access not permitted');
}

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['message'] = 'Invalid request method.';
        echo json_encode($response);
        exit();
    }

    $groupIdentity = trim((string) ($_POST['groupIdentity'] ?? ''));
    $groupName = $groupIdentity;
    if ($groupIdentity !== '' && preg_match('/^CN=([^,]+)/i', $groupIdentity, $m)) {
        $groupName = $m[1];
    }
    $desiredMembers = trim((string) ($_POST['desiredMembers'] ?? ''));
    $membersToAdd = trim((string) ($_POST['membersToAdd'] ?? ''));
    $membersToRemove = trim((string) ($_POST['membersToRemove'] ?? ''));
    $executedBy = $_SESSION['username'] ?? 'UnknownUser';

    if ($groupIdentity === '') {
        $response['message'] = 'Group identity is required.';
        echo json_encode($response);
        exit();
    }

    $backend = ldap_read_config()['backend'] ?? 'powershell';

    if ($backend === 'ldap' || $backend === 'auto') {
        $result = ldap_group_writer_sync_members([
            'GroupIdentity' => $groupIdentity,
            'DesiredMembers' => $desiredMembers,
            'MembersToAdd' => $membersToAdd,
            'MembersToRemove' => $membersToRemove,
            'ExecutedBy' => $executedBy,
        ], $executedBy);
        $decoded = $result['decoded'] ?? [];
        ldap_write_script_log('set_group_members', $groupName, !empty($decoded['success']), $decoded['message'] ?? '', $executedBy);
    } else {
        $result = powershell_run_json_script('setADGroupMembers', [
            'GroupIdentity' => $groupIdentity,
            'DesiredMembers' => $desiredMembers,
            'MembersToAdd'   => $membersToAdd,
            'MembersToRemove' => $membersToRemove,
            'ExecutedBy' => $executedBy,
        ], [
            'include_secure_config' => true,
        ]);
    }

    if ($result['json_valid']) {
        $decoded = $result['decoded'];
        $response['success'] = $decoded['success'] ?? false;
        $response['message'] = $decoded['message'] ?? 'Action processed.';
    } elseif ($result['success']) {
        $response['success'] = true;
        $response['message'] = 'Action processed.';
        $response['technical_details'] = $result['output'];
    } else {
        $response['message'] = 'Failed to update group members or parse response.';
        $response['technical_details'] = $result['output'];
        error_log('update_group_members.php: Failure. Output: ' . $result['output']);
    }
} catch (Throwable $e) {
    $response['message'] = 'Critical System Error: ' . $e->getMessage();
    error_log('update_group_members.php Fatal: ' . $e->getMessage());
}

echo json_encode($response);
