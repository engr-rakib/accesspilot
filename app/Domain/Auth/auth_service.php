<?php

require_once __DIR__ . '/../../Infrastructure/Persistence/repositories.php';
require_once __DIR__ . '/auth_session_service.php';
require_once __DIR__ . '/../Audit/audit_service.php';
require_once __DIR__ . '/../UserManagement/user_management_service.php';
require_once __DIR__ . '/../Licensing/license_service.php';

function auth_handle_request(array $data): array
{
    $action = $data['action'] ?? '';
    if ($action === '') {
        return ['success' => false, 'message' => 'Invalid request.'];
    }

    $users = readUsers();

    switch ($action) {
        case 'login':
            return auth_handle_login($data, $users);
        case 'register':
            return auth_handle_register($data);
        case 'forgot_password':
            return auth_handle_forgot_password($data, $users);
        case 'update_password_mandatory':
            return auth_handle_mandatory_password_update($data, $users);
        default:
            return ['success' => false, 'message' => 'Invalid request.'];
    }
}

function auth_handle_login(array $data, array $users): array
{
    $username = trim((string) ($data['username'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $licenseStatus = license_get_status();

    // Rate limiting: 5 failed attempts = 3 min lockout per IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $lockoutKey = 'login_locked_' . $ip;
    $attemptKey = 'login_attempts_' . $ip;
    $remember = !empty($data['remember']);

    if (isset($_SESSION[$lockoutKey]) && $_SESSION[$lockoutKey] > time()) {
        $remainingSeconds = (int) ceil($_SESSION[$lockoutKey] - time());
        $remaining = ceil($remainingSeconds / 60);
        log_activity($username !== '' ? $username : 'unknown_user', 'Failed Login (Rate Limited)', 'failure', "Rate-limited IP {$ip}.");
        return [
            'success' => false,
            'message' => "Too many login attempts. Please try again in {$remaining} minute(s).",
            'retry_after' => $remainingSeconds,
        ];
    }

    if (isset($_SESSION[$attemptKey]) && $_SESSION[$attemptKey] >= 5) {
        $_SESSION[$lockoutKey] = time() + 180; // 3 min lockout
        unset($_SESSION[$attemptKey]);
        log_activity($username !== '' ? $username : 'unknown_user', 'Failed Login (Locked Out)', 'failure', "IP {$ip} locked out for 3 minutes.");
        return [
            'success' => false,
            'message' => 'Too many login attempts. Account locked for 3 minutes.',
            'retry_after' => 180,
        ];
    }

    if (isset($users[$username])) {
        $user = $users[$username];
        if (password_verify($password, $user['password']) && ($user['system_access'] ?? false) === true) {
            // Successful login — reset rate limit counters
            unset($_SESSION[$attemptKey], $_SESSION[$lockoutKey]);
            if ($user['must_change_password'] ?? false) {
                return [
                    'success' => true,
                    'must_change' => true,
                    'username' => $username,
                    'message' => 'Mandatory password change required.',
                ];
            }

            if (!empty($licenseStatus['is_restricted'])) {
                auth_start_user_session($username, (string) $user['role'], $user, $remember);
                auth_touch_authenticated_user($username);

                log_activity($username, 'Successful Login', 'success', 'Login allowed, but license restriction redirected the session.');

                return [
                    'success' => true,
                    'redirect' => license_status_page_url(),
                ];
            }

            auth_start_user_session($username, (string) $user['role'], $user, $remember);
            auth_touch_authenticated_user($username);

            log_activity($username, 'Successful Login', 'success');

            return [
                'success' => true,
                'redirect' => route_url('index.php'),
            ];
        }

        $_SESSION[$attemptKey] = ($_SESSION[$attemptKey] ?? 0) + 1;
        log_activity($username, 'Failed Login', 'failure', 'Invalid username or password.');
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    $_SESSION[$attemptKey] = ($_SESSION[$attemptKey] ?? 0) + 1;
    log_activity($username !== '' ? $username : 'unknown_user', 'Failed Login', 'failure', 'Invalid username or password.');
    return ['success' => false, 'message' => 'Invalid username or password.'];
}

function auth_handle_register(array $data): array
{
    if (license_is_restricted()) {
        return license_denied_response();
    }

    $hrmsId = trim((string) ($data['hrms_id'] ?? ''));
    $name = trim((string) ($data['username'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));

    if ($hrmsId === '' || $name === '' || $email === '') {
        return ['success' => false, 'message' => 'Please fill all fields.'];
    }

    $requests = repo_read_registration_requests();
    foreach ($requests as $req) {
        if (($req['hrms_id'] ?? '') === $hrmsId) {
            return ['success' => false, 'message' => 'A request for this ID is already pending.'];
        }
    }

    $requests[] = [
        'hrms_id' => $hrmsId,
        'username' => $name,
        'email' => $email,
        'status' => 'pending',
        'timestamp' => date('Y-m-d h:i:s A'),
    ];

    if (!repo_write_registration_requests($requests)) {
        return ['success' => false, 'message' => 'Failed to save request.'];
    }

    return ['success' => true, 'message' => 'Registration request submitted! Please wait for admin approval.'];
}

function auth_handle_forgot_password(array $data, array $users): array
{
    if (license_is_restricted()) {
        return license_denied_response();
    }

    $id = trim((string) ($data['identifier'] ?? ''));
    $reason = trim((string) ($data['reason'] ?? ''));

    if ($id === '') {
        return ['success' => false, 'message' => 'Please enter username or email.'];
    }

    $targetUsername = '';
    foreach ($users as $uname => $user) {
        if (strtolower($uname) === strtolower($id) || strtolower((string) ($user['email'] ?? '')) === strtolower($id)) {
            $targetUsername = $uname;
            break;
        }
    }

    if ($targetUsername !== '') {
        $resets = repo_read_password_reset_requests();
        $resets[] = [
            'username' => $targetUsername,
            'timestamp' => date('Y-m-d h:i:s A'),
            'reason' => $reason,
            'status' => 'pending',
        ];
        repo_write_password_reset_requests($resets);
    }

    return ['success' => true, 'message' => 'If the account exists, your request has been sent to the admin.'];
}

function auth_validate_password_strength(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must contain at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one digit.';
    }
    if (!preg_match('/[^a-zA-Z\d]/', $password)) {
        return 'Password must contain at least one special character.';
    }
    return null;
}

function auth_handle_mandatory_password_update(array $data, array $users): array
{
    $username = (string) ($data['username'] ?? '');
    $current = (string) ($data['current_password'] ?? '');
    $new = (string) ($data['new_password'] ?? '');

    $strengthError = auth_validate_password_strength($new);
    if ($strengthError !== null) {
        return ['success' => false, 'message' => $strengthError];
    }

    if (isset($users[$username]) && password_verify($current, $users[$username]['password'])) {
        $users[$username]['password'] = password_hash($new, PASSWORD_DEFAULT);
        $users[$username]['must_change_password'] = false;

        if (writeUsers($users)) {
            log_activity($username, 'Changed Own Password', 'success', 'Mandatory password change completed.');
            return ['success' => true, 'message' => 'Password updated! You can now login.'];
        }
    } else {
        log_activity($username !== '' ? $username : 'unknown_user', 'Changed Own Password', 'failure', 'Incorrect current password during mandatory password update.');
        return ['success' => false, 'message' => 'Incorrect current password.'];
    }

    return ['success' => false, 'message' => 'Failed to update password.'];
}
