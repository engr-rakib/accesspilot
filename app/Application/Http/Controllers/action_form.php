<?php

require_once __DIR__ . '/../../../Domain/ActiveDirectory/action_executor.php';

function core_admin_handle_action_form_request(): array
{
    $state = [
        'message' => '',
        'infoOutput' => '',
        'apiData' => null,
        'actionTaken' => '',
        'showUserInfoSection' => false,
    ];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return $state;
    }

    $action = $_POST['action'] ?? '';
    $userManagementActions = [
        'approve',
        'deny',
        'delete_user',
        'reset_password',
        'update_user',
        'manual_create_user',
    ];

    if (in_array($action, $userManagementActions, true)) {
        return $state;
    }

    $username = preg_replace('/\s+/', '', trim($_POST['username'] ?? ''));
    if ($username === '') {
        $state['message'] = 'Error: Username cannot be empty.';
        return $state;
    }

    $authenticatedUser = $_SERVER['AUTH_USER'] ?? 'UnknownUser';
    return executeADAction($username, $action, $authenticatedUser);
}
