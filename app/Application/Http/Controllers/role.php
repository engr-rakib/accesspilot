<?php

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!defined('_CORE_ADMIN_')) {
    define('_CORE_ADMIN_', true);
}

require_once __DIR__ . '/../../Support/helpers.php';
require_once __DIR__ . '/../../../Infrastructure/Persistence/repositories.php';
require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Domain/Audit/audit_service.php';

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

$response = ['success' => false, 'message' => 'Invalid action.'];
$roles_file = repo_roles_path();

if (!has_permission('page_role_management')) {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit;
}

function role_api_require_permission(string $permission, array &$response, string $message): void {
    if (!has_permission($permission)) {
        $response['message'] = $message;
        echo json_encode($response);
        exit;
    }
}

function resolve_role_key(array $roles, string $requested): string {
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

function normalize_users_roles(array $users, array $roles): array {
    $changed = false;

    foreach ($users as $username => &$user) {
        $currentRole = trim((string)($user['role'] ?? ''));

        if (strcasecmp((string)$username, 'admin') === 0) {
            if ($currentRole !== 'core_admin') {
                $user['role'] = 'core_admin';
                $changed = true;
            }
            continue;
        }

        if ($currentRole === '') {
            continue;
        }

        $resolvedRole = resolve_role_key($roles, $currentRole);
        if ($resolvedRole !== $currentRole && isset($roles[$resolvedRole])) {
            $user['role'] = $resolvedRole;
            $changed = true;
        }
    }
    unset($user);

    return [$users, $changed];
}

function build_role_membership_payload(array $users, string $role_name): array {
    $members = [];
    $non_members = [];

    foreach ($users as $username => $u) {
        $user_info = [
            'username' => $username,
            'full_name' => $u['full_name'] ?? $username,
            'email' => $u['email'] ?? ''
        ];

        if (strcasecmp((string)($u['role'] ?? ''), $role_name) === 0) {
            $members[] = $user_info;
        } else {
            $non_members[] = $user_info;
        }
    }

    usort($members, fn($a, $b) => strcasecmp((string)$a['username'], (string)$b['username']));
    usort($non_members, fn($a, $b) => strcasecmp((string)$a['username'], (string)$b['username']));

    return [
        'members' => $members,
        'non_members' => $non_members
    ];
}

function process_ui_elements($elements, $level = 0) {
    $processed_elements = [];
    foreach ($elements as $key => $details) {
        $item = [
            'key' => $key,
            'name' => $details['name'],
            'level' => $level,
            'icon' => $details['icon'] ?? null
        ];

        if (isset($details['cards'])) {
            $item['cards'] = process_ui_elements($details['cards'], $level + 1);
        }
        if (isset($details['buttons'])) {
            $item['buttons'] = process_ui_elements($details['buttons'], $level + 1);
        }
        if (isset($details['sub_actions'])) {
            $item['sub_actions'] = process_ui_elements($details['sub_actions'], $level + 1);
        }
        $processed_elements[] = $item;
    }
    return $processed_elements;
}

$action = $_GET['action'] ?? '' ;

switch ($action) {
    case 'get_all_data':
        try {
            if (!file_exists($roles_file) || !is_readable($roles_file)) {
                throw new Exception("roles.json not found or is not readable.");
            }
            $roles = repo_read_roles();
            if (empty($roles) && file_exists($roles_file)) {
                error_log("API WARNING: roles.json is empty or corrupted.");
            }

            $permission_tree = [];

            $components_config = config_get('components', []);
            if (!is_array($components_config)) {
                $components_config = [];
            }

            foreach ($components_config as $category_key => $category_details) {
                $category_name = $category_details['name'] ?? $category_key;
                $category_icon = $category_details['icon'] ?? null;

                $top_level_item = [
                    'key' => $category_key,
                    'name' => $category_name,
                    'level' => 0,
                    'icon' => $category_icon
                ];

                if (isset($category_details['cards'])) {
                    $top_level_item['cards'] = process_ui_elements($category_details['cards'], 1);
                }
                if (isset($category_details['buttons'])) {
                    $top_level_item['buttons'] = process_ui_elements($category_details['buttons'], 1);
                }
                if (isset($category_details['permissions'])) {
                    $top_level_item['permissions'] = process_ui_elements($category_details['permissions'], 1);
                }

                $permission_tree[$category_key][] = $top_level_item;
            }

            $response['success'] = true;
            $response['roles'] = $roles;
            $response['permissions'] = $permission_tree;
            $response['user_permissions'] = [
                'can_edit' => has_permission('action_role_edit'),
                'can_delete' => has_permission('action_role_delete'),
                'can_add_member' => has_permission('action_role_add_member'),
                'can_remove_member' => has_permission('action_role_remove_member')
            ];

            $role_to_edit = $_GET['role_name'] ?? null;
            if ($role_to_edit) {
                require_once __DIR__ . '/../../../Domain/UserManagement/user_management_service.php';
                $all_users = readUsers();
                [$all_users, $users_changed] = normalize_users_roles($all_users, $roles);
                if ($users_changed) {
                    writeUsers($all_users);
                }
                $membership_payload = build_role_membership_payload($all_users, (string)$role_to_edit);
                $response['members'] = $membership_payload['members'];
                $response['non_members'] = $membership_payload['non_members'];
            }

            $response['message'] = 'Data loaded successfully';

        } catch (Exception $e) {
            $response['message'] = 'Error loading data: ' . $e->getMessage();
        }
        break;

    case 'add_role_member':
        role_api_require_permission('action_role_add_member', $response, 'You do not have permission to add role members.');
        $data = json_decode(file_get_contents('php://input'), true);
        $role_name = $data['role_name'] ?? '';
        $username = $data['username'] ?? '';

        if (empty($role_name) || empty($username)) {
            $response['message'] = 'Role name and username are required.';
        } else {
            try {
                require_once __DIR__ . '/../../../Domain/UserManagement/user_management_service.php';
                $users = readUsers();
                $roles = repo_read_roles();
                [$users, $users_changed] = normalize_users_roles($users, $roles);
                if ($users_changed) {
                    writeUsers($users);
                }
                $resolved_role_name = resolve_role_key($roles, $role_name);
                if (!isset($roles[$resolved_role_name])) {
                    throw new Exception("Role '{$role_name}' not found.");
                }
                if (isset($users[$username])) {
                    if (strcasecmp($username, 'admin') === 0 && $resolved_role_name !== 'core_admin') {
                        throw new Exception("The 'admin' account must remain assigned to 'core_admin'.");
                    }
                    $old_role = (string)($users[$username]['role'] ?? '');
                    $users[$username]['role'] = $resolved_role_name;
                    if (writeUsers($users)) {
                        $response['success'] = true;
                        $response['message'] = "User '" . htmlspecialchars($username) . "' assigned to role '" . htmlspecialchars($resolved_role_name) . "'.";
                        $membership_payload = build_role_membership_payload($users, $resolved_role_name);
                        $response['members'] = $membership_payload['members'];
                        $response['non_members'] = $membership_payload['non_members'];
                        log_activity($_SESSION['username'] ?? 'UnknownUser', 'role_member_added', 'success', "Assigned user '{$username}' to role '{$resolved_role_name}' from role '{$old_role}'.");
                    } else {
                        throw new Exception("Failed to update users.json.");
                    }
                } else {
                    $response['message'] = 'User not found.';
                }
            } catch (Exception $e) {
                $response['message'] = 'Error adding member: ' . $e->getMessage();
            }
        }
        break;

    case 'remove_role_member':
        role_api_require_permission('action_role_remove_member', $response, 'You do not have permission to remove role members.');
        $data = json_decode(file_get_contents('php://input'), true);
        $role_name = $data['role_name'] ?? '';
        $username = $data['username'] ?? '';

        if (empty($username) || empty($role_name)) {
            $response['message'] = 'Role name and username are required.';
        } else {
            try {
                require_once __DIR__ . '/../../../Domain/UserManagement/user_management_service.php';
                $users = readUsers();
                $roles = repo_read_roles();
                [$users, $users_changed] = normalize_users_roles($users, $roles);
                if ($users_changed) {
                    writeUsers($users);
                }
                $resolved_role_name = resolve_role_key($roles, $role_name);
                if (!isset($roles[$resolved_role_name])) {
                    throw new Exception("Role '{$role_name}' not found.");
                }
                if (isset($users[$username])) {
                    if (strcasecmp($username, 'admin') === 0) {
                        throw new Exception("The 'admin' account must remain assigned to 'core_admin'.");
                    }
                    $old_role = (string)($users[$username]['role'] ?? '');
                    $users[$username]['role'] = 'user';
                    if (writeUsers($users)) {
                        $response['success'] = true;
                        $response['message'] = "User '" . htmlspecialchars($username) . "' reverted to default 'user' role.";
                        $membership_payload = build_role_membership_payload($users, $resolved_role_name);
                        $response['members'] = $membership_payload['members'];
                        $response['non_members'] = $membership_payload['non_members'];
                        log_activity($_SESSION['username'] ?? 'UnknownUser', 'role_member_removed', 'success', "Removed user '{$username}' from role '{$old_role}' and reverted to 'user'.");
                    } else {
                        throw new Exception("Failed to update users.json.");
                    }
                } else {
                    $response['message'] = 'User not found.';
                }
            } catch (Exception $e) {
                $response['message'] = 'Error removing member: ' . $e->getMessage();
            }
        }
        break;

    case 'save_role':
        $data = json_decode(file_get_contents('php://input'), true);
        $is_edit_request = !empty(trim((string)($data['original_role_name'] ?? '')));

        role_api_require_permission(
            $is_edit_request ? 'action_role_edit' : 'action_role_create',
            $response,
            $is_edit_request ? 'You do not have permission to edit roles.' : 'You do not have permission to create roles.'
        );

        if (empty($data['role_name'])) {
            $response['message'] = 'Role name cannot be empty.';
        } else {
            try {
                if (!file_exists($roles_file) || !is_readable($roles_file)) {
                    throw new Exception("roles.json not found or is not readable.");
                }
                $roles = repo_read_roles();

                $role_key = trim((string)($data['role_name'] ?? ''));
                $original_role_key = trim((string)($data['original_role_name'] ?? $role_key));
                $resolved_original_role_key = resolve_role_key($roles, $original_role_key);
                $resolved_role_key = resolve_role_key($roles, $role_key);

                if ($resolved_original_role_key !== $role_key && isset($roles[$resolved_original_role_key])) {
                    unset($roles[$resolved_original_role_key]);
                }

                $roles[$role_key] = [
                    'description' => $data['description'] ?? '',
                    'permissions' => $data['permissions'] ?? []
                ];

                if ($resolved_role_key !== $role_key && isset($roles[$resolved_role_key])) {
                    unset($roles[$resolved_role_key]);
                }

                if (!repo_write_roles($roles)) {
                     throw new Exception("Cannot write to roles.json. Check directory or file permissions.");
                }
                $response['success'] = true;

                if (empty($data['original_role_name'])) {
                    $response['message'] = "New role '" . htmlspecialchars($role_key) . "' created successfully.";
                    log_activity($_SESSION['username'] ?? 'UnknownUser', 'role_created', 'success', "Created role '{$role_key}'.");
                } else {
                    $response['message'] = "Role '" . htmlspecialchars($role_key) . "' updated successfully.";
                    log_activity($_SESSION['username'] ?? 'UnknownUser', 'role_updated', 'success', "Updated role '{$role_key}'.");
                }

            } catch (Exception $e) {
                $response['message'] = 'Error saving role: ' . $e->getMessage();
            }
        }
        break;

    case 'delete_role':
        role_api_require_permission('action_role_delete', $response, 'You do not have permission to delete roles.');
        $data = json_decode(file_get_contents('php://input'), true);
        $role_name = $data['role_name'] ?? '';

        if ($role_name === 'core_admin' || $role_name === 'View only') {
            $response['message'] = 'Cannot delete protected system roles.';
        } elseif (!empty($role_name)) {
            try {
                if (!file_exists($roles_file) || !is_readable($roles_file)) {
                    throw new Exception("roles.json not found or is not readable.");
                }
                $roles = repo_read_roles();
                if (isset($roles[$role_name])) {
                    unset($roles[$role_name]);

                    if (!repo_write_roles($roles)) {
                        throw new Exception("Cannot write to roles.json. Check directory or file permissions.");
                    }
                    $response['success'] = true;
                    $response['message'] = "Role '" . htmlspecialchars($role_name) . "' deleted.";
                    log_activity($_SESSION['username'] ?? 'UnknownUser', 'role_deleted', 'success', "Deleted role '{$role_name}'.");
                } else {
                    $response['message'] = 'Role not found.';
                }
            } catch (Exception $e) {
                $response['message'] = 'Error deleting role: ' . $e->getMessage();
            }
        } else {
            $response['message'] = 'Role name not provided.';
        }
        break;
}

echo json_encode($response);
