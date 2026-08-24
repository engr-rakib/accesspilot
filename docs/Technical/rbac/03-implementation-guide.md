# 03 — Implementation Guide

> **Document ID:** TP-RBAC-001 · **Version:** 1.0 · **Status:** ACTIVE
> Step-by-step procedure for adding a new page/feature to the portal AND wiring it into the RBAC system so it can be granted per-role from the create/edit role page.

---

## Prerequisites

- Read `01-architecture.md` (RBAC layers).
- Read `02-permission-catalog.md` (key naming conventions).
- Hard refresh (Ctrl+F5) after JS/CSS changes; clear OPcache after PHP changes:
  `docker exec accesspilot_php php -r 'opcache_reset();'`
- PHP lint check (PHP is only inside the container):
  `docker exec accesspilot_php php -l /var/www/html/<path>.php`

---

## Method A — Add a new feature to an EXISTING page

Example: add a "Whois" button to the existing **Email Analysis** page.

### 1. Add permission keys in `config/components_config.php`

Open the target page's category and add a button under the relevant card:

```php
'page_email_tools' => [
    'name' => 'Email Analysis',
    'icon' => 'fa-envelope-open-text',
    'cards' => [
        // existing cards...
        'card_email_whois' => [
            'name' => 'Whois Lookup', 'icon' => 'fas fa-globe',
            'buttons' => [
                'action_email_whois' => ['name' => 'Run Whois', 'icon' => 'fas fa-search']
            ]
        ]
    ]
],
```

> The role form automatically renders new keys — **no frontend change needed** for the checkbox tree. If the new category should sort higher/lower, update `preferredCategoryOrder` in `role_management_actions.js`.

### 2. Guard the backend handler

In the controller that serves the new action (e.g. `email_tools.php`), add an entry to its permission map:

```php
$emailPermissionMap = [
    // ...existing
    'whois' => 'action_email_whois',
];
```

### 3. Guard the view

If the feature is a button in the UI, wrap it:

```php
<?php if (has_permission('action_email_whois')): ?>
    <button id="whoisBtn">Run Whois</button>
<?php endif; ?>
```

### 4. Verify

- `docker exec accesspilot_php php -l` on the edited PHP.
- Login as `core_admin` (or a role granted the new key) → the button appears and the API responds.
- Login as a role WITHOUT the key → button hidden and API returns 403.

---

## Method B — Add a brand NEW page

Example: add a "Compliance Center" page.

### 1. Create the view

`resources/views/pages/compliance/view.php` — build the page UI, gating sensitive parts with `has_permission()`.

### 2. Register the page route in `app/Application/Routing/page_registry.php`

Add a `case` in the switch:

```php
case 'compliance':
    $pageTitle = 'Compliance Center';
    $pageDescription = 'Audit, policy, and compliance controls.';
    $content_for_layout = include_path('resources/views/pages/compliance/view.php');
    $page_scripts[] = $baseURL . '/resources/frontend/js/modules/compliance_actions.js?v=' . $app_config['app_info']['version'];
    break;
```

### 3. Add a page-level gate in the same file

Add to `$page_permission_map`:

```php
'compliance' => 'page_compliance',
```

### 4. Create the permission category in `config/components_config.php`

```php
'page_compliance' => [
    'name' => 'Compliance Center',
    'icon' => 'fa-shield-alt',
    'cards' => [
        'card_compliance_reports' => [
            'name' => 'Compliance Reports', 'icon' => 'fas fa-file-alt',
            'buttons' => [
                'action_compliance_export' => ['name' => 'Export Compliance Report', 'icon' => 'fas fa-file-export']
            ]
        ]
    ]
],
```

### 5. Add a menu item in `config/menu_config.php`

```php
[
    'name' => 'Compliance',
    'url' => '/index.php?page=compliance',
    'icon' => 'fa-shield-alt',
    'permission' => 'page_compliance'
],
```

> The rail (`vertical_rail.php`) reads the menu automatically. For an OR-fallback menu entry, use `'page_compliance|page_ad_administration'`.

### 6. Add the API endpoint (if the page has backend actions)

In `public/api/index.php`, add to `$allowed_endpoints`:

```php
'compliance_api' => 'compliance.php',
```

Create `app/Application/Http/Controllers/compliance.php` following the controller guard pattern from `01-architecture.md`:

```php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
load_user_permissions($_SESSION['role']);

$compliancePermissionMap = ['export' => 'action_compliance_export'];
$required = $compliancePermissionMap[$action] ?? 'page_compliance';
if (!has_permission('page_compliance') || !has_permission($required)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }
```

### 7. Update the frontend JS

Create `compliance_actions.js` (or extend an existing module). It must read the CSRF token via the shared fetch wrapper / `getCsrfToken()`.

### 8. (Optional) Ordering in the role form

Add `'page_compliance'` into `preferredCategoryOrder` in `role_management_actions.js:330`.

### 9. Verify end-to-end

```
Login as core_admin           → page visible, menu item visible, API allowed
Role without page_compliance  → page gate → Access Denied page
Role with page_compliance     → page visible; menu visible only if menu permission matches
API without action key        → 403 Forbidden
```

---

## Permission Map / Gate Cheat-Sheet

| Enforcement point | Where | What to add |
|-------------------|-------|-------------|
| Page gate | `page_registry.php` → `$page_permission_map` | `'<page>' => '<perm>'` (or `a|b` OR-list) |
| Menu item | `menu_config.php` → `permission` | `'<perm>'` or `'a|b'` |
| Rail filter | `vertical_rail.php` (already implemented) | nothing — reads menu automatically |
| View guard | view PHP file | `if (!has_permission('<perm>')) ...` |
| Controller guard | controller file | session check + `load_user_permissions` + per-action map + 403 |
| API endpoint | `public/api/index.php` → `$allowed_endpoints` | `'<endpoint>' => '<file>.php'` |
| Role form category | `config/components_config.php` | new category/card/button keys |
| Role form ordering | `role_management_actions.js` → `preferredCategoryOrder` | add category key |

## Naming Conventions

| Kind | Pattern | Example |
|------|---------|---------|
| Page | `page_<feature>` | `page_compliance` |
| Card | `card_<page>_<area>` | `card_compliance_reports` |
| Button / action | `action_<page>_<verb>` | `action_compliance_export` |
| Page capability | `view_<feature>_<scope>` | `view_monitoring_network` |
| Employee/DB ops | `action_<verb>_<entity>` | `action_add_employee` |
| Exchange ops | `action_exchange_<object>_<verb>` | `action_exchange_mailbox_enable` |
| Email tools | `action_email_<verb>` | `action_email_dns_lookup` |
| Notifications | `notif_category_<name>` | `notif_category_security` |

## Common Pitfalls

1. **Forgetting the controller guard** — the page is visible but the API is open. Always guard controllers (Layer 4).
2. **Hard-coding `page_ad_administration`** — new pages should use their own `page_<feature>` key; use OR-lists only for backward-compat fallback.
3. **Editing roles.json by hand** — prefer the Edit Role page; the API normalizes keys and protects `core_admin` / `View only`.
4. **Browser cache / OPcache** — hard refresh + `opcache_reset()` after changes.
5. **Orphan permissions** — keys not present in `components_config.php` never appear in the role form. Keep config and code in sync.
6. **`create_role` / `edit_role`** — do NOT add them to `page_registry.php`'s map; they're gated inside `role_form_view.php`.

## Testing Checklist

- [ ] `docker exec accesspilot_php php -l <file>` — all edited PHP clean
- [ ] Role form shows the new category/card/button with correct icons
- [ ] Save a role with the new key → `roles.json` updated
- [ ] Reload permissions (re-login) → granted role sees feature; denied role does not
- [ ] API 401 for unauthenticated, 403 for authenticated-without-key
- [ ] `core_admin` (`*`) sees everything; protected roles cannot be deleted
- [ ] Menu item visibility matches `menu_config.php` permission
