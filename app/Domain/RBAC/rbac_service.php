<?php

if (!defined('API_GATEWAY') && !defined('_CORE_ADMIN_')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/../../Infrastructure/Persistence/repositories.php';

/**
 * Checks if the currently logged-in user has a specific permission.
 *
 * @param string $permission_key The permission key to check.
 * @return bool True if the user has the permission, false otherwise.
 */
function has_permission($permission_key) {
    if (!isset($_SESSION['user_permissions']) || !is_array($_SESSION['user_permissions'])) {
        return false;
    }

    if (in_array('*', $_SESSION['user_permissions'])) {
        return true;
    }

    return in_array($permission_key, $_SESSION['user_permissions']);
}

/**
 * Loads the permissions for a given role into the session.
 *
 * @param string $role The role of the user.
 */
function load_user_permissions($role) {
    $roles_data = repo_read_roles();
    if (empty($roles_data)) {
        $_SESSION['user_permissions'] = [];
        error_log('RBAC ERROR: roles.json could not be loaded from repository path: ' . repo_roles_path());
        return;
    }

    if (isset($roles_data[$role])) {
        $_SESSION['user_permissions'] = $roles_data[$role]['permissions'];
    } else {
        $_SESSION['user_permissions'] = [];
    }

    $_SESSION['user_role'] = $role;
}

