<?php
/**
 * CANONICAL SINGLE ENTRY POINT — Front Controller
 *
 * All web requests flow through this file.
 * Route resolution: ?route= > PATH_INFO > REQUEST_URI parsing
 * Unknown routes fall back to: admin portal (SPA)
 *
 * Server support:
 * - Apache (.htaccess rewrite → ?route=)
 * - IIS (default document + direct file fallback)
 * - Nginx (requires config rewrite → ?route=)
 */

require_once __DIR__ . '/../app/Application/Http/Router/front_controller.php';

// IP Blocking — respond as unreachable for blocked source IPs before any routing.
require_once __DIR__ . '/../app/Domain/Security/ip_block_service.php';
ip_block_enforce();

$route = resolve_route();

if (dispatch_route($route)) {
    exit;
}

// Default: Admin SPA (handles '' and unknown routes)
require_once __DIR__ . '/../app/Application/Http/admin_portal.php';
