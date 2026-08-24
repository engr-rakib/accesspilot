<?php

require_once __DIR__ . '/../../../Domain/UserManagement/user_management_service.php';
require_once __DIR__ . '/../../../Domain/ActiveDirectory/action_executor.php';
require_once __DIR__ . '/../../../Infrastructure/Persistence/repositories.php';

function core_admin_is_user_management_page(string $page): bool
{
    return in_array($page, ['user_management', 'edit_user', 'create_user'], true);
}

function core_admin_handle_user_management_page(array $app_config): array
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        core_admin_handle_user_management_post($app_config);
    }

    return [
        'registration_requests' => repo_read_registration_requests(),
        'users' => readUsers(),
    ];
}

function core_admin_handle_user_management_post(array $app_config): void
{
    if (license_is_restricted()) {
        redirect_with_flash($_GET['page'] ?? 'user_management', 'Operation restricted. Please purchase and provide a legal license to perform this action.', false);
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        core_admin_handle_user_approval($app_config);
        return;
    }

    if ($action === 'deny') {
        $requestIndex = $_POST['request_index'] ?? null;
        $requests = repo_read_registration_requests();

        if ($requestIndex !== null && isset($requests[$requestIndex])) {
            unset($requests[$requestIndex]);
            repo_write_registration_requests($requests);
            redirect_with_flash('user_management', 'Registration request has been denied.', true);
        }

        redirect_with_flash('user_management', 'Invalid registration request.', false);
    }

    if ($action === 'delete_user') {
        $usernameToDelete = $_POST['username'] ?? '';
        $users = readUsers();

        if ($usernameToDelete !== '' && isset($users[$usernameToDelete])) {
            unset($users[$usernameToDelete]);
            writeUsers($users);
            redirect_with_flash('user_management', 'User ' . htmlspecialchars($usernameToDelete) . ' has been deleted.', true);
        }

        redirect_with_flash('user_management', 'User not found.', false);
    }
}

function core_admin_handle_user_approval(array $app_config): void
{
    $requestIndex = $_POST['request_index'] ?? null;
    $requests = repo_read_registration_requests();

    if ($requestIndex === null || !isset($requests[$requestIndex])) {
        redirect_with_flash('user_management', 'Invalid registration request.', false);
    }

    $request = $requests[$requestIndex];
    if (empty($request['hrms_id'])) {
        redirect_with_flash('user_management', 'Cannot approve: HRMS ID is missing for this request.', false);
    }

    $hrmsInfo = getHRMSInfo($request['hrms_id']);
    if (!$hrmsInfo['success'] || empty($hrmsInfo['apiData'])) {
        redirect_with_flash(
            'user_management',
            'Failed to fetch HRMS data for user ' . $request['username'] . '. User not approved. HRMS API response was unsuccessful or empty.',
            false
        );
    }

    $users = readUsers();
    $defaultTempPassword = $app_config['default_password'];
    $users[$request['username']] = [
        'password' => password_hash($defaultTempPassword, PASSWORD_DEFAULT),
        'email' => $hrmsInfo['apiData']['EMAIL'] ?? '',
        'role' => 'user',
        'system_access' => true,
        'hrms_id' => $request['hrms_id'],
        'full_name' => $hrmsInfo['apiData']['EMP_NAME'] ?? '',
    ];

    writeUsers($users);
    unset($requests[$requestIndex]);
    repo_write_registration_requests($requests);

    redirect_with_flash(
        'user_management',
        'User ' . $request['username'] . ' has been approved and created with data from HRMS. Temporary password: <strong>' . $defaultTempPassword . '</strong>',
        true
    );
}
