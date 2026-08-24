<?php

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

define('_CORE_ADMIN_', true);

require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Infrastructure/Security/encryption_service.php';
require_once __DIR__ . '/../../../Infrastructure/Persistence/repositories.php';
require_once __DIR__ . '/../../Support/helpers.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
load_user_permissions($_SESSION['role']);

$encryption_key = hex2bin((string) config_get('encryption_key', ''));
function get_db_path($scope) {
    $current_username = $_SESSION['username'] ?? 'anonymous';
    return repo_passwords_path($scope, $current_username);
}

function read_passwords_db($scope) {
    $current_username = $_SESSION['username'] ?? 'anonymous';
    return repo_read_password_entries($scope, $current_username);
}

function write_passwords_db($data, $scope) {
    $current_username = $_SESSION['username'] ?? 'anonymous';
    return repo_write_password_entries((array) $data, $scope, $current_username);
}

$response = ['success' => false, 'message' => 'Invalid action.'];
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$scope = $data['scope'] ?? 'personal';
$current_user = $_SESSION['username'];

switch ($action) {
    case 'get_passwords':
        if (!has_permission('page_password_manager')) {
            http_response_code(403);
            $response['message'] = 'Forbidden';
            break;
        }
        $all_passwords = read_passwords_db('personal');
        $can_view_all = has_permission('action_password_view_all');
        $passwords_to_return = [];

        foreach ($all_passwords as $entry) {
            if ($can_view_all || $entry['creator'] === $current_user) {
                $decrypted_password = decrypt_password($entry['password'], $encryption_key);
                if ($decrypted_password !== false) {
                    $entry['password'] = $decrypted_password;
                    $passwords_to_return[] = $entry;
                }
            }
        }
        $response = ['success' => true, 'passwords' => $passwords_to_return];
        break;

    case 'get_global_passwords':
        if (!has_permission('page_global_passwords')) {
            http_response_code(403);
            $response['message'] = 'Forbidden';
            break;
        }
        $all_passwords = read_passwords_db('global');
        $passwords_to_return = [];
        foreach ($all_passwords as $entry) {
            $decrypted_password = decrypt_password($entry['password'], $encryption_key);
            if ($decrypted_password !== false) {
                $entry['password'] = $decrypted_password;
                $passwords_to_return[] = $entry;
            }
        }
        $response = ['success' => true, 'passwords' => $passwords_to_return];
        break;

    case 'toggle_share_password':
        if (!has_permission('action_password_share')) {
            $response['message'] = 'You do not have permission to toggle sharing passwords.';
            break;
        }
        $entry_id = $data['id'] ?? null;
        if (!$entry_id) {
            $response['message'] = 'Entry ID not provided.';
            break;
        }

        $personal_passwords = read_passwords_db('personal');
        $global_passwords = read_passwords_db('global');

        $entry_to_toggle = null;
        foreach ($personal_passwords as $entry) {
            if ($entry['id'] === $entry_id) {
                $entry_to_toggle = $entry;
                break;
            }
        }

        if (!$entry_to_toggle) {
            $response['message'] = 'Entry not found in personal passwords.';
            break;
        }

        $global_found_index = -1;
        foreach ($global_passwords as $index => $gp) {
            if (($gp['id'] ?? null) === $entry_id) {
                $global_found_index = $index;
                break;
            }
        }

        if ($global_found_index !== -1) {
            array_splice($global_passwords, $global_found_index, 1);
            if (write_passwords_db($global_passwords, 'global')) {
                $response = ['success' => true, 'message' => 'Password unshared successfully from global.', 'is_shared' => false];
            } else {
                $response['message'] = 'Failed to remove from global passwords file.';
            }
        } else {
            $global_passwords[] = $entry_to_toggle;
            if (write_passwords_db($global_passwords, 'global')) {
                $response = ['success' => true, 'message' => 'Password shared successfully to global.', 'is_shared' => true];
            } else {
                $response['message'] = 'Failed to add to global passwords file.';
            }
        }
        break;

    case 'save_password':
        $is_global_scope = ($scope === 'global');
        $can_edit = has_permission('action_password_edit');
        $can_create = has_permission('action_password_create');
        $can_edit_global = has_permission('action_global_password_edit');

        if (($is_global_scope && !$can_edit_global) || (!$is_global_scope && !$can_edit && !$can_create)) {
            $response['message'] = 'You do not have permission to save password entries.';
            break;
        }

        $entry_id = $data['id'] ?? null;
        $all_passwords = read_passwords_db($scope);

        $new_entry_data = [
            'id' => $entry_id ?: uniqid('pw_', true),
            'creator' => $current_user,
            'owner' => $data['owner'] ?? $current_user,
            'system_name' => $data['system_name'] ?? '',
            'user_id' => $data['user_id'] ?? '',
            'password' => encrypt_password($data['password'] ?? '', $encryption_key),
            'ip' => $data['ip'] ?? '',
            'url' => $data['url'] ?? '',
            'remarks' => $data['remarks'] ?? '',
            'parent_id' => $data['parent_id'] ?? null,
            'entry_type' => $data['entry_type'] ?? 'credential'
        ];

        if ($new_entry_data['password'] === false) {
            $response['message'] = 'Failed to encrypt password.';
            break;
        }

        $found_index = -1;
        if ($entry_id) {
            foreach ($all_passwords as $index => $entry) {
                if ($entry['id'] === $entry_id) {
                    if (!$is_global_scope && $entry['creator'] !== $current_user && !has_permission('action_password_view_all')) {
                        $response['message'] = 'You do not have permission to modify this entry.';
                        break 2;
                    }
                    $found_index = $index;
                    break;
                }
            }
        }

        if (isset($response['message']) && $response['message'] === 'You do not have permission to modify this entry.') {
            break;
        }

        if ($found_index !== -1) {
            $all_passwords[$found_index] = $new_entry_data;
        } else {
            $all_passwords[] = $new_entry_data;
        }

        if (write_passwords_db($all_passwords, $scope)) {
            if ($scope === 'personal') {
                $global_passwords = read_passwords_db('global');
                $global_found_index = -1;
                foreach ($global_passwords as $index => $gp) {
                    if (($gp['id'] ?? null) === $new_entry_data['id']) {
                        $global_found_index = $index;
                        break;
                    }
                }
                if ($global_found_index !== -1) {
                    $global_passwords[$global_found_index] = $new_entry_data;
                    write_passwords_db($global_passwords, 'global');
                }
            }
            $response = ['success' => true, 'message' => 'Password entry saved successfully.'];
        } else {
            $response['message'] = 'Failed to write to the database file.';
        }
        break;

    case 'delete_password':
        $is_global_scope = ($scope === 'global');
        $can_delete_global = has_permission('action_global_password_delete');

        if (($is_global_scope && !$can_delete_global) || (!$is_global_scope && !has_permission('action_password_delete'))) {
             $response['message'] = 'You do not have permission to delete password entries.';
            break;
        }

        $entry_id = $data['id'] ?? null;
        if (!$entry_id) {
            $response['message'] = 'Entry ID not provided.';
            break;
        }

        $all_passwords = read_passwords_db($scope);
        $found_index = -1;

        foreach ($all_passwords as $index => $entry) {
            if ($entry['id'] === $entry_id) {
                if (!$is_global_scope && $entry['creator'] !== $current_user && !has_permission('action_password_view_all')) {
                    $response['message'] = 'You do not have permission to delete this entry.';
                    break 2;
                }
                $found_index = $index;
                break;
            }
        }

        if (isset($response['message']) && $response['message'] === 'You do not have permission to delete this entry.') {
            break;
        }

        if ($found_index !== -1) {
            array_splice($all_passwords, $found_index, 1);
            if (write_passwords_db($all_passwords, $scope)) {
                $response = ['success' => true, 'message' => 'Password entry deleted successfully.'];
            } else {
                $response['message'] = 'Failed to write to the database file.';
            }
        } else {
            $response['message'] = 'Entry not found.';
        }
        break;
}

echo json_encode($response);
