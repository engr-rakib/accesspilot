<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Infrastructure/PowerShell/powershell_runner.php';
require_once __DIR__ . '/../../../Ldap/Config/ldap_config_repository.php';
require_once __DIR__ . '/../../../Ldap/Operations/ldap_user_writer.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.']);
    exit();
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

if (!has_permission('manual_create_user')) {
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to manually create users.']);
    exit();
}

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $displayName = $_POST['displayName'] ?? '';
        $ou = $_POST['ou'] ?? '';
        $description = $_POST['description'] ?? '';
        $groupMembers = $_POST['manualGroupMembers'] ?? '';
        $isServiceAccount = $_POST['isServiceAccount'] ?? 'false';
        $serverOperation = $_POST['serverOperation'] ?? '';
        $passwordNeverExpires = $_POST['passwordNeverExpires'] ?? 'true';
        $enableMailbox = ($_POST['enable_mailbox'] ?? 'false') === 'true';
        $loggedInUser = $_SESSION['username'] ?? 'UnknownUser';

        if (empty($username) || empty($displayName) || empty($ou)) {
            $response['message'] = 'All fields (username, display name, OU) are required.';
        } else {
            $backend = ldap_read_config()['backend'] ?? 'powershell';

            if ($backend === 'ldap' || $backend === 'auto') {
                $result = ldap_user_writer_create([
                    'Username' => $username,
                    'DisplayName' => $displayName,
                    'OU' => $ou,
                    'Description' => $description,
                    'GroupMembers' => $groupMembers,
                    'IsServiceAccount' => filter_var($isServiceAccount, FILTER_VALIDATE_BOOLEAN),
                    'ServerOperation' => $serverOperation,
                    'PasswordNeverExpires' => filter_var($passwordNeverExpires, FILTER_VALIDATE_BOOLEAN),
                    'EnableMailbox' => $enableMailbox,
                    'ExecutedBy' => $loggedInUser,
                ], $loggedInUser);
                $decoded = $result['decoded'] ?? [];
                ldap_write_script_log('create_user', $username, !empty($decoded['success']), $decoded['message'] ?? '', $loggedInUser);
            } else {
                $result = powershell_run_json_script('manual_create_user', [
                    'Username' => $username,
                    'DisplayName' => $displayName,
                    'OU' => $ou,
                    'Description' => $description,
                    'GroupMembers' => $groupMembers,
                    'IsServiceAccount' => filter_var($isServiceAccount, FILTER_VALIDATE_BOOLEAN),
                    'ServerOperation' => $serverOperation,
                    'PasswordNeverExpires' => filter_var($passwordNeverExpires, FILTER_VALIDATE_BOOLEAN),
                    'EnableMailbox' => $enableMailbox,
                    'ExecutedBy' => $loggedInUser,
                ], [
                    'include_secure_config' => true,
                ]);
            }

            if ($result['json_valid']) {
                $response['success'] = $result['decoded']['success'] ?? false;
                $response['message'] = $result['decoded']['message'] ?? 'Action processed.';
            } elseif ($result['success']) {
                $response['success'] = true;
                $response['message'] = 'Action processed.';
                $response['technical_details'] = $result['output'];
            } else {
                $response['success'] = false;
                $response['message'] = 'Failed to execute creation script or parse response.';
                $response['technical_details'] = $result['output'];
                error_log('manual_create_user.php: Failure. Output: ' . $result['output']);
            }
        }
    } else {
        $response['message'] = 'Invalid request method.';
    }
} catch (Throwable $e) {
    $response['success'] = false;
    $response['message'] = 'Critical System Error: ' . $e->getMessage();
    error_log("manual_create_user.php Fatal: " . $e->getMessage());
}

echo json_encode($response);


