<?php

require_once __DIR__ . '/../../Infrastructure/Persistence/repositories.php';
require_once __DIR__ . '/../../Domain/Auth/auth_session_service.php';
require_once __DIR__ . '/../../Domain/Audit/audit_service.php';
require_once __DIR__ . '/../../Domain/RBAC/rbac_service.php';

function core_admin_require_authenticated_session(): void
{
    if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
        header('Location: ' . route_url('login.php'));
        exit();
    }

    load_user_permissions($_SESSION['role']);
    auth_touch_authenticated_user($_SESSION['username']);
    core_admin_handle_idle_timeout($_SESSION['username']);
    core_admin_handle_forced_logout($_SESSION['username']);

    if (!isset($_SESSION['last_regenerated']) || (time() - $_SESSION['last_regenerated'] > 300)) {
        session_regenerate_id(false);
        $_SESSION['last_regenerated'] = time();

        // session_regenerate_id() re-issues the session cookie with the default
        // browser-session lifetime, wiping out the remember-me expiry. Re-assert it.
        if (!empty($_SESSION['remember_me'])) {
            $params = session_get_cookie_params();
            setcookie(session_name(), session_id(), time() + 7200, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
    }
    $_SESSION['last_activity'] = time();

    $requestedPage = $_GET['page'] ?? 'default';
    log_activity($_SESSION['username'], 'page_view', 'success', 'Viewed the \'' . $requestedPage . '\' page.');
}

function core_admin_handle_idle_timeout(string $username): void
{
    $maxIdle = !empty($_SESSION['remember_me']) ? 7200 : 900;

    if (!isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity'] <= $maxIdle)) {
        return;
    }

    log_activity($username, 'session_timeout', 'success', 'User session for \'' . $username . '\' timed out after ' . ($maxIdle / 60) . ' minutes of inactivity.');

    auth_remove_authenticated_user($username);
    auth_destroy_current_session();
    header('Location: ' . route_url('login.php?message=session_expired'));
    exit();
}

function core_admin_handle_forced_logout(string $username): void
{
    $forcedLogouts = repo_read_forced_logouts();
    if (!isset($forcedLogouts[$username])) {
        return;
    }

    log_activity($username, 'session_terminated', 'success', 'User session for \'' . $username . '\' was terminated by an administrator.');
    unset($forcedLogouts[$username]);
    repo_write_forced_logouts($forcedLogouts);

    auth_destroy_current_session();
    header('Location: ' . route_url('login.php?message=session_terminated'));
    exit();
}
