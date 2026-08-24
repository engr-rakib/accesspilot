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

if (!has_permission('modify_ad_user')) {
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to modify AD users.']);
    exit();
}

if (!defined('API_GATEWAY')) {
    die('Direct access not permitted');
}

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $originalUsername = $_POST['originalUsername'] ?? '';
        $newUsername = $_POST['newUsername'] ?? '';
        $displayName = $_POST['displayName'] ?? '';
        $ou = $_POST['ou'] ?? '';
        $description = $_POST['description'] ?? '';
        $groupMembers = $_POST['manualGroupMembers'] ?? '';
        $resetPassword = $_POST['resetPassword'] ?? 'false';
        $forcePasswordChange = $_POST['forcePasswordChange'] ?? 'true';
        $temporaryPassword = $_POST['temporaryPassword'] ?? '';
        $useDefaultPassword = $_POST['useDefaultPassword'] ?? 'true';
        $loggedInUser = $_SESSION['username'] ?? 'UnknownUser';
        $enableMailbox = $_POST['enable_mailbox'] ?? 'false';
        $mailboxAlias = trim($_POST['mailboxAlias'] ?? '');
        $title = $_POST['title'] ?? '';
        $department = $_POST['department'] ?? '';
        $company = $_POST['company'] ?? '';
        $physicalDeliveryOfficeName = $_POST['physicalDeliveryOfficeName'] ?? '';
        $telephoneNumber = $_POST['telephoneNumber'] ?? '';

        if (empty($originalUsername) || empty($newUsername)) {
            $response['message'] = 'Original and new usernames are required for update.';
        } else {
            $ldapConfig = ldap_read_config();
            $backend = $ldapConfig['backend'] ?? 'powershell';

            if ($backend === 'ldap' || $backend === 'auto') {
                $result = ldap_user_writer_update([
                    'OriginalSamAccountName' => $originalUsername,
                    'NewSamAccountName' => $newUsername,
                    'DisplayName' => $displayName,
                    'OU' => $ou,
                    'Description' => $description,
                    'GroupMembers' => $groupMembers,
                    'ResetPassword' => filter_var($resetPassword, FILTER_VALIDATE_BOOLEAN),
                    'ForcePasswordChange' => filter_var($forcePasswordChange, FILTER_VALIDATE_BOOLEAN),
                    'TemporaryPassword' => $temporaryPassword,
                    'UseDefaultPassword' => filter_var($useDefaultPassword, FILTER_VALIDATE_BOOLEAN),
                    'EnableMailbox' => filter_var($enableMailbox, FILTER_VALIDATE_BOOLEAN),
                    'MailboxAlias' => $mailboxAlias,
                    'ExecutedBy' => $loggedInUser,
                    'Title' => $title,
                    'Department' => $department,
                    'Company' => $company,
                    'PhysicalDeliveryOfficeName' => $physicalDeliveryOfficeName,
                    'TelephoneNumber' => $telephoneNumber,
                ], $loggedInUser);
                $decoded = $result['decoded'] ?? [];
                ldap_write_script_log('modify_user', $originalUsername, !empty($decoded['success']), $decoded['message'] ?? '', $loggedInUser);
            } else {
                $result = powershell_run_json_script('modifyuser', [
                    'OriginalSamAccountName' => $originalUsername,
                    'NewSamAccountName' => $newUsername,
                    'DisplayName' => $displayName,
                    'OU' => $ou,
                    'Description' => $description,
                    'GroupMembers' => $groupMembers,
                    'ExecutedBy' => $loggedInUser,
                    'ResetPassword' => filter_var($resetPassword, FILTER_VALIDATE_BOOLEAN),
                    'ForcePasswordChange' => filter_var($forcePasswordChange, FILTER_VALIDATE_BOOLEAN),
                    'EnableMailbox' => filter_var($enableMailbox, FILTER_VALIDATE_BOOLEAN),
                    'MailboxAlias' => $mailboxAlias,
                    'Title' => $title,
                    'Department' => $department,
                    'Company' => $company,
                    'PhysicalDeliveryOfficeName' => $physicalDeliveryOfficeName,
                    'TelephoneNumber' => $telephoneNumber,
                ], [
                    'include_secure_config' => true,
                ]);
            }

            // Handle mailbox enable via Exchange API if requested
            $mbEnabled = false;
            $mbMessage = '';
            if (filter_var($enableMailbox, FILTER_VALIDATE_BOOLEAN) && !empty($newUsername)) {
                require_once __DIR__ . '/../Controllers/exchange.php';
                ob_start();
                $exchangeInput = ['identity' => $newUsername, 'alias' => $mailboxAlias];
                handle_mailbox_enable($exchangeInput);
                $mbOutput = ob_get_clean();
                $mbResult = json_decode($mbOutput, true);
                if ($mbResult && !empty($mbResult['success'])) {
                    $mbEnabled = true;
                    $mbMessage = ' Mailbox enabled successfully.';
                } else {
                    $mbMessage = ' Mailbox enable failed: ' . ($mbResult['message'] ?? 'Unknown error.');
                }
            }

            if ($result['json_valid']) {
                $response['success'] = $result['decoded']['success'] ?? false;
                $response['message'] = ($result['decoded']['message'] ?? 'Action processed.') . $mbMessage;
            } elseif ($result['success']) {
                $response['success'] = true;
                $response['message'] = 'Action processed.' . $mbMessage;
                $response['technical_details'] = $result['output'];
            } else {
                $response['success'] = false;
                $response['message'] = 'Failed to execute modification script or parse response.' . $mbMessage;
                $response['technical_details'] = $result['output'];
                error_log('modify_ad_user.php: Failure. Output: ' . $result['output']);
            }
        }
    } else {
        $response['message'] = 'Invalid request method.';
    }
} catch (Throwable $e) {
    $response['success'] = false;
    $response['message'] = 'Critical System Error: ' . $e->getMessage();
    error_log("modify_ad_user.php Fatal: " . $e->getMessage());
}

echo json_encode($response);

