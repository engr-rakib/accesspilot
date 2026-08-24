<?php

require_once __DIR__ . '/../../Infrastructure/Persistence/repositories.php';

function auth_start_user_session(string $username, string $role, array $userData = [], bool $remember = false): void
{
    session_regenerate_id(false);
    $_SESSION['authenticated'] = true;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;
    $_SESSION['login_time'] = time();
    $_SESSION['login_time_formatted'] = date('Y-m-d H:i:s');
    $_SESSION['avatar'] = $userData['avatar'] ?? 'assets/images/logo.png';
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['last_activity'] = time();

    if ($remember) {
        $_SESSION['remember_me'] = true;
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), time() + 7200, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
}

function auth_touch_authenticated_user(string $username): void
{
    $authUsers = repo_read_authenticated_users();

    if (!isset($authUsers[$username])) {
        $authUsers[$username] = [
            'login_time' => $_SESSION['login_time'] ?? time(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'last_activity' => time(),
        ];
    } else {
        $authUsers[$username]['last_activity'] = time();
        // A fresh login must reset login_time; otherwise a stale value from an
        // older logged-out session inflates the reported session duration.
        $authUsers[$username]['login_time'] = (int)($_SESSION['login_time'] ?? $authUsers[$username]['login_time'] ?? time());
        $authUsers[$username]['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? ($authUsers[$username]['ip_address'] ?? '');
    }

    repo_write_authenticated_users($authUsers);
}

function auth_remove_authenticated_user(string $username): void
{
    $authenticatedUsers = repo_read_authenticated_users();
    if (isset($authenticatedUsers[$username])) {
        unset($authenticatedUsers[$username]);
        repo_write_authenticated_users($authenticatedUsers);
    }
}

function auth_destroy_current_session(): void
{
    session_unset();
    session_destroy();
}
