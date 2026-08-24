<?php

ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
define('_CORE_ADMIN_', true);
header('Content-Type: application/json');

require_once __DIR__ . '/../../Support/helpers.php';
require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.']);
    exit();
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

$app_config = app_config();

require_once __DIR__ . '/../../../Domain/UserManagement/user_management_service.php';
require_once __DIR__ . '/../../../Domain/ActiveDirectory/action_executor.php';
require_once __DIR__ . '/../../../Domain/Audit/audit_service.php';
require_once __DIR__ . '/../../../Infrastructure/Persistence/repositories.php';

function read_available_roles(): array {
    return repo_read_roles();
}

function resolve_available_role(array $roles, string $requested): string {
    $requested = trim($requested);
    if ($requested === '') {
        return '';
    }
    foreach (array_keys($roles) as $roleKey) {
        if (strcasecmp($roleKey, $requested) === 0) {
            return $roleKey;
        }
    }
    return $requested;
}

$response = [
    'success' => false,
    'message' => 'Invalid request.'
];

$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $response['message'] = 'Invalid JSON payload.';
    echo json_encode($response);
    exit();
}

$action = $data['action'] ?? '';
$authenticatedUser = $_SESSION['username'] ?? 'UnknownUser';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($action)) {
    // Touch authenticated user to keep last_activity current even on API calls
    if (function_exists('auth_touch_authenticated_user')) {
        auth_touch_authenticated_user($authenticatedUser);
    }
    $users = readUsers();
    $available_roles = read_available_roles();

    // Missing function: readActivityLog is used by get_user_activity but was only defined in profile_action.php
    if (!function_exists('readActivityLog')) {
        function readActivityLog(): array {
            $file = resolved_log_path('audit.csv');
            if (!file_exists($file)) return [];
            $logs = []; $fh = @fopen($file, 'r');
            if (!$fh) return [];
            $headers = fgetcsv($fh, 0, ',', '"', '\\');
            if (!$headers) { fclose($fh); return []; }
            while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
                $entry = [];
                foreach ($headers as $i => $h) {
                    $entry[trim($h)] = $row[$i] ?? '';
                }
                $logs[] = $entry;
            }
            fclose($fh);
            return $logs;
        }
    }

    switch ($action) {
        case 'get_pending_requests':
            $reg_requests = repo_read_registration_requests();
            $reset_requests = repo_read_password_reset_requests();

            $response['success'] = true;
            $response['message'] = 'Requests fetched successfully.';
            $response['registration_requests'] = $reg_requests ?: [];
            $response['password_reset_requests'] = $reset_requests ?: [];
            break;

        case 'get_users':
            $response['success'] = true;
            $response['users'] = $users;
            break;

        case 'approve':
            if (!has_permission('user_approve_request')) {
                $response['message'] = 'You do not have permission to approve users.';
                break;
            }
            $request_index = $data['request_index'] ?? null;
            $requests = repo_read_registration_requests();

            if ($request_index !== null && isset($requests[$request_index])) {
                $request = $requests[$request_index];
                $username_to_approve = trim((string)($request['username'] ?? ''));
                if ($username_to_approve === '') {
                    $username_to_approve = trim((string)($request['hrms_id'] ?? ''));
                }
                $ignore_hrms = $data['ignore_hrms'] ?? false;

                if (!empty($request['hrms_id'])) {
                    $hrms_info = getHRMSInfo($request['hrms_id']);

                    if ($hrms_info['success'] && !empty($hrms_info['apiData'])) {
                        $apiData = $hrms_info['apiData'];
                        $password = $app_config['default_password'];
                        if (isset($data['new_password']) && !empty($data['new_password'])) {
                            $password = $data['new_password'];
                        }

                        $newUser = [
                            'password' => password_hash($password, PASSWORD_DEFAULT),
                            'email' => $apiData['EMAIL'] ?? $request['email'],
                            'role' => 'user',
                            'system_access' => true,
                            'hrms_id' => $request['hrms_id'],
                            'full_name' => $apiData['EMP_NAME'] ?? $request['username'],
                            'mobile' => $apiData['MOBILE'] ?? '',
                            'designation' => $apiData['DESIGNATION'] ?? '',
                            'operating_unit' => $apiData['OPERATING_UNIT_TITLE'] ?? '',
                            'location' => $apiData['LOCATION_TITLE'] ?? '',
                            'department' => $apiData['DEPARTMENT_TITLE'] ?? '',
                            'section' => $apiData['SECTION_TITLE'] ?? '',
                            'sub_section' => $apiData['SUB_SECTION_TITLE'] ?? '',
                            'joining_date' => $apiData['JOINING_DATE'] ?? '',
                            'dob' => $apiData['DOB'] ?? '',
                            'gender' => $apiData['GENDER'] ?? '',
                            'age' => $apiData['AGE'] ?? '',
                            'hrms_status' => $apiData['EMP_STS'] ?? 'ACTIVE'
                        ];

                        $users[$username_to_approve] = $newUser;

                        if (writeUsers($users)) {
                            unset($requests[$request_index]);
                            repo_write_registration_requests($requests);

                            $response['success'] = true;
                            $response['message'] = "User <strong>" . htmlspecialchars($username_to_approve) . "</strong> has been approved and created successfully.";
                            $response['temporary_password'] = $password;
                            $response['newUser'] = array_merge(['username' => $username_to_approve], $newUser);
                            log_activity($authenticatedUser, 'Approved Registration', 'success', "Approved registration for user '{$username_to_approve}' using HRMS ID '{$request['hrms_id']}'.");
                        } else {
                            $response['message'] = 'Failed to write to users database.';
                            log_activity($authenticatedUser, 'Approved Registration', 'failure', "Failed to write to users database for '{$username_to_approve}'.");
                        }

                    } else if ($ignore_hrms) {
                        $password = $app_config['default_password'];
                        if (isset($data['new_password']) && !empty($data['new_password'])) {
                            $password = $data['new_password'];
                        }

                        $newUser = [
                            'password' => password_hash($password, PASSWORD_DEFAULT),
                            'email' => $request['email'],
                            'role' => 'user',
                            'system_access' => true,
                            'hrms_id' => $request['hrms_id'],
                            'full_name' => $request['username'] . ' (Custom)',
                            'hrms_status' => 'CUSTOM'
                        ];

                        $users[$username_to_approve] = $newUser;

                        if (writeUsers($users)) {
                            unset($requests[$request_index]);
                            repo_write_registration_requests($requests);

                            $response['success'] = true;
                            $response['message'] = "<strong>Warning:</strong> User '{$username_to_approve}' created as a Custom User because HRMS data was not found.";
                            log_activity($authenticatedUser, 'Approved Registration (Bypass HRMS)', 'success', "Created custom user '{$username_to_approve}' because HRMS lookup failed.");
                        } else {
                            $response['message'] = 'Failed to write to users database.';
                        }
                    } else {
                        $response['success'] = false;
                        $response['hrms_failed'] = true;
                        $response['message'] = 'User ID not found on HRMS API. Do you want to create this as a Custom User anyway?';
                    }
                } else {
                    $response['message'] = 'Cannot approve: HRMS ID is missing for this request.';
                    log_activity($authenticatedUser, 'Approved Registration', 'failure', "HRMS ID was missing for request index '{$request_index}'.");
                }
            } else {
                $response['message'] = 'Invalid registration request.';
                log_activity($authenticatedUser, 'Approved Registration', 'failure', "Invalid request index '{$request_index}' provided.");
            }
            break;

        case 'deny':
            if (!has_permission('user_approve_request')) {
                $response['message'] = 'You do not have permission to deny users.';
                break;
            }
            $request_index = $data['request_index'] ?? null;
            $requests = repo_read_registration_requests();
            if (!empty($requests)) {
                if (isset($requests[$request_index])) {
                    $denied_user_info = $requests[$request_index]['username'] ?? 'N/A';
                    unset($requests[$request_index]);
                    repo_write_registration_requests($requests);

                    $response['success'] = true;
                    $response['message'] = 'Registration request has been denied.';
                    log_activity($authenticatedUser, 'Denied Registration', 'success', "Denied registration request for '{$denied_user_info}'.");
                } else {
                    $response['message'] = 'Invalid request index.';
                }
            } else {
                $response['message'] = 'No pending requests found.';
            }
            break;

        case 'delete_user':
            if (!has_permission('user_delete')) {
                $response['message'] = 'You do not have permission to delete users.';
                break;
            }
            $username_to_delete = $data['username'] ?? '';
            if (isset($users[$username_to_delete])) {
                unset($users[$username_to_delete]);
                if (writeUsers($users)) {
                    $response['success'] = true;
                    $response['message'] = 'User ' . htmlspecialchars($username_to_delete) . ' has been deleted.';
                    log_activity($authenticatedUser, 'Deleted Web User', 'success', "Deleted web application user '{$username_to_delete}'.");
                } else {
                    $response['message'] = 'Failed to delete user: could not write to file.';
                    log_activity($authenticatedUser, 'Deleted Web User', 'failure', "Could not write to user file after deleting '{$username_to_delete}'.");
                }
            } else {
                $response['message'] = 'User not found.';
                log_activity($authenticatedUser, 'Deleted Web User', 'failure', "Attempted to delete non-existent user '{$username_to_delete}'.");
            }
            break;

        case 'reset_password':
            if (!has_permission('user_password_reset')) {
                $response['message'] = 'You do not have permission to reset passwords.';
                break;
            }
            $username_to_reset = $data['username'] ?? null;
            $use_default_password = $data['use_default_password'] ?? false;
            $new_password = $data['new_password'] ?? '';
            $force_password_change = $data['force_password_change'] ?? false;
            $request_index = $data['request_index'] ?? '';

            if (isset($users[$username_to_reset])) {
                $password_to_set = '';

                if ($use_default_password) {
                    $password_to_set = $app_config['default_password'];
                } else {
                    $password_to_set = $new_password;
                }

                if (empty($password_to_set)) {
                    $response['message'] = 'Error: New password cannot be empty.';
                } else {
                    $users[$username_to_reset]['password'] = password_hash($password_to_set, PASSWORD_DEFAULT);
                    $users[$username_to_reset]['must_change_password'] = (bool)$force_password_change;

                    if (writeUsers($users)) {
                        if ($request_index !== '') {
                            $requests = repo_read_password_reset_requests();
                            unset($requests[$request_index]);
                            repo_write_password_reset_requests($requests);
                        }

                        $response['success'] = true;
                        $response['message'] = "Password for user <strong>" . htmlspecialchars($username_to_reset) . "</strong> has been reset successfully.";
                        $response['temporary_password'] = $password_to_set;
                        log_activity($authenticatedUser, 'Reset Web User Password', 'success', "Reset password for web user '{$username_to_reset}'.");
                    } else {
                        $response['message'] = 'Failed to update user data file.';
                        log_activity($authenticatedUser, 'Reset Web User Password', 'failure', "Failed to write to users file while resetting password for '{$username_to_reset}'.");
                    }
                }
            } else {
                $response['message'] = 'User not found.';
                log_activity($authenticatedUser, 'Reset Web User Password', 'failure', "Attempted to reset password for non-existent user '{$username_to_reset}'.");
            }
            break;

        case 'reset_password_bulk':
            if (!has_permission('user_password_reset')) {
                $response['message'] = 'You do not have permission to reset passwords.';
                break;
            }
            $usernames = $data['usernames'] ?? [];
            $request_indices = $data['request_indices'] ?? [];
            $use_default = $data['use_default_password'] ?? false;
            $new_password = $data['new_password'] ?? '';
            $force_change = $data['force_password_change'] ?? false;

            $success_count = 0;
            $total_count = count($usernames);

            foreach ($usernames as $idx => $user_to_reset) {
                if (isset($users[$user_to_reset])) {
                    $password_to_set = $use_default ? $app_config['default_password'] : $new_password;

                    if (!empty($password_to_set)) {
                        $users[$user_to_reset]['password'] = password_hash($password_to_set, PASSWORD_DEFAULT);
                        $users[$user_to_reset]['must_change_password'] = (bool)$force_change;
                        $success_count++;
                    }
                }
            }

            if ($success_count > 0) {
                if (writeUsers($users)) {
                    $all_requests = repo_read_password_reset_requests();
                    rsort($request_indices);
                    foreach($request_indices as $rIdx) {
                        if (isset($all_requests[$rIdx])) unset($all_requests[$rIdx]);
                    }
                    repo_write_password_reset_requests($all_requests);

                    $response['success'] = true;
                    $response['message'] = "Successfully reset passwords for <strong>$success_count</strong> of $total_count selected users.";
                    log_activity($authenticatedUser, 'Bulk Reset Passwords', 'success', "Reset passwords for $success_count users.");
                } else {
                    $response['message'] = 'Failed to update users database.';
                }
            } else {
                $response['message'] = 'No valid users were selected or passwords were empty.';
            }
            break;

        case 'deny_password_reset':
            if (!has_permission('user_password_reset')) {
                $response['message'] = 'You do not have permission to manage resets.';
                break;
            }
            $request_index = $data['request_index'] ?? null;
            $requests = repo_read_password_reset_requests();
            if (!empty($requests)) {
                if (isset($requests[$request_index])) {
                    $denied_user = $requests[$request_index]['username'];
                    unset($requests[$request_index]);
                    repo_write_password_reset_requests($requests);

                    $response['success'] = true;
                    $response['message'] = 'Password reset request denied.';
                    log_activity($authenticatedUser, 'Denied Password Reset', 'success', "Denied password reset request for '{$denied_user}'.");
                } else {
                    $response['message'] = 'Invalid request index.';
                }
            }
            break;

        case 'change_own_password':
            $current_password = $data['current_password'] ?? '';
            $new_password = $data['new_password'] ?? '';

            if (isset($users[$authenticatedUser])) {
                if (password_verify($current_password, $users[$authenticatedUser]['password'])) {
                    if (empty($new_password)) {
                        $response['message'] = 'New password cannot be empty.';
                    } else {
                        $users[$authenticatedUser]['password'] = password_hash($new_password, PASSWORD_DEFAULT);
                        $users[$authenticatedUser]['must_change_password'] = false;

                        if (writeUsers($users)) {
                            $response['success'] = true;
                            $response['message'] = 'Your password has been updated successfully.';
                            log_activity($authenticatedUser, 'Changed Own Password', 'success', "User '{$authenticatedUser}' changed their own web portal password.");
                        } else {
                            $response['message'] = 'Failed to save new password to database.';
                            log_activity($authenticatedUser, 'Changed Own Password', 'failure', "Failed to write to users file while '{$authenticatedUser}' was changing their own password.");
                        }
                    }
                } else {
                    $response['message'] = 'Incorrect current password.';
                    log_activity($authenticatedUser, 'Changed Own Password', 'failure', "User '{$authenticatedUser}' failed to change password: Incorrect current password.");
                }
            } else {
                $response['message'] = 'Authenticated user not found in database.';
            }
            break;

        case 'update_user':
            if (!has_permission('user_edit')) {
                $response['message'] = 'You do not have permission to edit users.';
                break;
            }
            $old_username = $_POST['old_username'] ?? ($data['old_username'] ?? '');
            $new_username = trim($_POST['new_username'] ?? ($data['new_username'] ?? ''));

            if (empty($old_username) || empty($new_username)) {
                $response['message'] = 'Error: Username identifiers are missing.';
                break;
            }

            if (isset($users[$old_username])) {
                $userData = $users[$old_username];

                if ($new_username !== $old_username) {
                    if (isset($users[$new_username])) {
                        $response['message'] = "Error: The Logon ID '{$new_username}' is already in use by another account.";
                        break;
                    }
                    unset($users[$old_username]);
                }

                $source = !empty($_POST) ? $_POST : $data;

                $userData['full_name'] = $source['full_name'] ?? $userData['full_name'];
                $userData['email'] = $source['email'] ?? $userData['email'];
                $requestedRole = (string)($source['role'] ?? $userData['role']);
                if (strcasecmp($old_username, 'admin') === 0 || strcasecmp($new_username, 'admin') === 0) {
                    $requestedRole = 'core_admin';
                }
                $resolvedRole = resolve_available_role($available_roles, $requestedRole);
                if ($resolvedRole !== '' && !isset($available_roles[$resolvedRole])) {
                    $response['message'] = "Error: Role '{$requestedRole}' does not exist.";
                    break;
                }
                $old_role = (string)($userData['role'] ?? '');
                $userData['role'] = $resolvedRole !== '' ? $resolvedRole : $old_role;
                $userData['system_access'] = isset($source['system_access']) ? (bool)$source['system_access'] : $userData['system_access'];
                $userData['mobile'] = $source['mobile'] ?? ($userData['mobile'] ?? '');
                $userData['hrms_id'] = $source['hrms_id'] ?? ($userData['hrms_id'] ?? '');
                $userData['designation'] = $source['designation'] ?? ($userData['designation'] ?? '');
                $userData['operating_unit'] = $source['operating_unit'] ?? ($userData['operating_unit'] ?? '');
                $userData['location'] = $source['location'] ?? ($userData['location'] ?? '');
                $userData['department'] = $source['department'] ?? ($userData['department'] ?? '');
                $userData['section'] = $source['section'] ?? ($userData['section'] ?? '');
                $userData['sub_section'] = $source['sub_section'] ?? ($userData['sub_section'] ?? '');
                $userData['joining_date'] = $source['joining_date'] ?? ($userData['joining_date'] ?? '');
                $userData['dob'] = $source['dob'] ?? ($userData['dob'] ?? '');
                $userData['gender'] = $source['gender'] ?? ($userData['gender'] ?? '');
                $userData['age'] = $source['age'] ?? ($userData['age'] ?? '');
                $userData['hrms_status'] = $source['hrms_status_display'] ?? ($source['hrms_status'] ?? ($userData['hrms_status'] ?? 'ACTIVE'));

                $users[$new_username] = $userData;

                if (writeUsers($users)) {
                    $response['success'] = true;
                    $response['message'] = "User '{$new_username}' updated successfully.";
                    log_activity($authenticatedUser, 'Updated Web User', 'success', "Updated details for user '{$old_username}' (New ID: '{$new_username}').");
                    if (strcasecmp($old_role, (string)$userData['role']) !== 0) {
                        log_activity($authenticatedUser, 'user_role_changed', 'success', "Changed role for user '{$new_username}' from '{$old_role}' to '{$userData['role']}'.");
                    }
                } else {
                    $response['message'] = 'Error: Failed to write updated user data.';
                    log_activity($authenticatedUser, 'Updated Web User', 'failure', "Could not write to user file while updating '{$old_username}'.");
                }
            } else {
                $response['message'] = 'User not found in database.';
            }
            break;

        case 'terminate_session':
            if (!has_permission('terminate_user_session')) {
                $response['message'] = 'You do not have permission to terminate user sessions.';
                break;
            }
            $username_to_terminate = $data['username'] ?? '';
            if (!empty($username_to_terminate)) {
                $forced_logouts = repo_read_forced_logouts();
                $forced_logouts[$username_to_terminate] = true;
                repo_write_forced_logouts($forced_logouts);

                $auth_users = repo_read_authenticated_users();
                if (isset($auth_users[$username_to_terminate])) {
                    unset($auth_users[$username_to_terminate]);
                    repo_write_authenticated_users($auth_users);
                }

                $response['success'] = true;
                $response['message'] = 'User ' . htmlspecialchars($username_to_terminate) . ' session terminated.';
                log_activity($authenticatedUser, 'Terminated Session', 'success', "Terminated session for user '{$username_to_terminate}' and marked for forced logout.");
            } else {
                $response['message'] = 'Error: Username not provided for termination.';
            }
            break;

        case 'get_user_activity':
            $targetUser = $data['username'] ?? '';
            $limit = min((int)($data['limit'] ?? 20), 50);
            $allLogs = readActivityLog();
            $userLogs = []; $count = 0;
            for ($i = count($allLogs) - 1; $i >= 0 && $count < $limit; $i--) {
                if (($allLogs[$i]['user'] ?? '') === $targetUser) {
                    $userLogs[] = $allLogs[$i]; $count++;
                }
            }
            // If nothing today, scan ALL audit CSV files for recent entries (up to $limit)
            if (empty($userLogs)) {
                $auditDir = dirname(resolved_log_path('audit.csv'));
                $csvFiles = glob($auditDir . DIRECTORY_SEPARATOR . 'audit-*.csv');
                rsort($csvFiles);
                foreach ($csvFiles as $csvFile) {
                    if ($count >= $limit) break;
                    $fh = @fopen($csvFile, 'r');
                    if (!$fh) continue;
                    $headers = fgetcsv($fh, 0, ',', '"', '\\');
                    if (!$headers) { fclose($fh); continue; }
                    $fileEntries = [];
                    while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
                        $entry = [];
                        foreach ($headers as $i => $h) { $entry[trim($h)] = $row[$i] ?? ''; }
                        if (($entry['user'] ?? ($entry['Username'] ?? '')) === $targetUser) {
                            $fileEntries[] = $entry;
                        }
                    }
                    fclose($fh);
                    // Take most recent entries from this file (lines at the end)
                    $fileEntries = array_slice($fileEntries, -($limit - $count));
                    foreach ($fileEntries as $e) {
                        $userLogs[] = $e; $count++;
                    }
                }
                if (empty($userLogs)) {
                    // Last resort: check authenticated_users.json
                    $authUsers = repo_read_authenticated_users();
                    if (isset($authUsers[$targetUser]['last_activity'])) {
                        $lastActive = (int)$authUsers[$targetUser]['last_activity'];
                        $elapsed = time() - $lastActive;
                        if ($elapsed < 900) {
                            $userLogs[] = [
                                'timestamp' => date('Y-m-d H:i:s', $lastActive),
                                'action' => 'active_now',
                                'status' => 'success',
                                'details' => 'Currently active on the portal'
                            ];
                        }
                    }
                }
            }
            $response['success'] = true;
            $response['activity'] = $userLogs;
            break;

        case 'get_online_users':
            $authUsers = repo_read_authenticated_users();
            $onlineUsers = [];
            if (is_array($authUsers)) {
                foreach ($authUsers as $user => $data) {
                    $onlineUsers[] = [
                        'username' => $user,
                        'ip' => $data['ip_address'] ?? '',
                        'login_time' => isset($data['login_time']) ? date('Y-m-d H:i:s', $data['login_time']) : '',
                        'last_activity' => $data['last_activity'] ?? 0
                    ];
                }
            }
            $response['success'] = true;
            $response['users'] = $onlineUsers;
            break;

        default:
            $response['message'] = 'Unknown action.';
            log_activity($authenticatedUser, 'Unknown Web User Action', 'failure', 'An unknown action was specified: ' . ($action ?? 'N/A'));
            break;
    }
} else {
    $response['message'] = 'Invalid request method or missing action.';
}

ob_clean();
echo json_encode($response);
