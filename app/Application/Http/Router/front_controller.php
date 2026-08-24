<?php
/**
 * Front Controller — Route Map & Dispatcher
 *
 * All requests flow through public/index.php → resolve_route() → dispatch_route().
 * Route types:
 *   'view'       → renders a self-bootstrapping page view (HTML)
 *   'controller' → API-like controller with license middleware
 *   'admin'      → full admin SPA bootstrap
 */

define('ROUTE_MAP', [
    // ── Public Views (no session required, self-bootstrapping) ──
    'login'              => ['type' => 'view', 'path' => 'auth/login.php'],
    'logout'             => ['type' => 'view', 'path' => 'auth/logout.php'],
    'register'           => ['type' => 'view', 'path' => 'auth/register.php'],
    'forgot_password'    => ['type' => 'view', 'path' => 'auth/forgot_password.php'],
    'reset_password'     => ['type' => 'view', 'path' => 'auth/reset_password.php'],
    'verify'             => ['type' => 'view', 'path' => 'auth/verify.php'],
    'request_portal'     => ['type' => 'view', 'path' => 'ad_user_request/request_portal_standalone.php'],

    // ── Controller Routes (API-like, with license enforcement) ──
    'role'               => ['type' => 'controller', 'path' => 'role.php'],
    'notification'       => ['type' => 'controller', 'path' => 'notification.php'],
    'audit'              => ['type' => 'controller', 'path' => 'audit.php'],
    'password_api'       => ['type' => 'controller', 'path' => 'password_manager_api.php'],
    'ad_user_request_public' => ['type' => 'controller', 'path' => 'ad_user_request_public.php'],
    'ad_user_request_admin'  => ['type' => 'controller', 'path' => 'ad_user_request_admin.php'],
]);

/**
 * Resolve the current route from the HTTP request.
 * Priority: 1) ?route= 2) PATH_INFO 3) REQUEST_URI parsing
 *
 * Supports:
 * - Apache: mod_rewrite passes /login as ?route=login
 * - IIS: default document + direct file fallback
 * - Nginx: rewrite sends /login as ?route=login
 * - CLI: direct ?route= parameter
 */
function resolve_route(): string
{
    // 1. Explicit ?route= query parameter (set by .htaccess or server rewrite)
    if (!empty($_GET['route'])) {
        // Strip .php extension for backward compat (/login.php → route: login)
        return preg_replace('/\.php$/i', '', $_GET['route']);
    }

    // 2. PATH_INFO from index.php/{path} style URLs
    if (!empty($_SERVER['PATH_INFO'])) {
        return trim($_SERVER['PATH_INFO'], '/');
    }

    // 3. Parse from REQUEST_URI (generic fallback)
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $uri = '/' . trim($uri, '/');

    // Root or /index.php → empty (default: admin)
    if ($uri === '/' || $uri === '/index.php') {
        return '';
    }

    // Strip .php extension for backward compat (/login.php → route: login)
    $route = ltrim(preg_replace('/\.php$/i', '', $uri), '/');

    return $route;
}

/**
 * Dispatch to the appropriate handler for the given route.
 * Returns true if the route was handled, false to let the caller use the default.
 */
function dispatch_route(string $route): bool
{
    $map = ROUTE_MAP;

    // Empty or unknown route → let caller handle default
    if ($route === '' || !isset($map[$route])) {
        return false;
    }

    $config = $map[$route];

    switch ($config['type']) {
        case 'view':
            // Views are self-bootstrapping (session_start, helpers, license within)
            require_once __DIR__ . '/../../../../resources/views/pages/' . $config['path'];
            return true;

        case 'controller':
            // License middleware (same guard as old public/*.php entry points)
            require_once __DIR__ . '/../../Support/helpers.php';
            require_once __DIR__ . '/../../../Domain/Licensing/license_service.php';

            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

            if (license_is_restricted() && $method !== 'GET') {
                header('Content-Type: application/json');
                http_response_code(423);
                echo json_encode(license_denied_response());
                exit;
            }

            require_once __DIR__ . '/../Controllers/' . $config['path'];
            return true;
    }

    return false;
}
