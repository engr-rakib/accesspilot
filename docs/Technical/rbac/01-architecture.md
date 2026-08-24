# 01 — Architecture

> **Document ID:** TP-RBAC-001 · **Version:** 1.0 · **Status:** ACTIVE

## Overview

RBAC controls every page, card, button, and API action in the UM Portal. Permissions are defined once in a PHP catalog, stored per-role in a JSON vault, loaded into the session at login, and enforced at four layers (page → menu → view → API). There is **no database** — the entire RBAC engine is file-based.

## System Components

```
┌────────────────────────────────────────────────────────────────────────────┐
│                       SOURCE OF TRUTH (config/)                          │
│                                                                            │
│  config/components_config.php   config/menu_config.php                    │
│  • 19 page categories           • Menu items + their required permission   │
│  • cards / buttons / sub-actions • Pipe = OR fallback                      │
└───────────────────────────┬────────────────────────────────────────────────┘
                            │ config_get('components') / config_get('menu')
                            ▼
┌────────────────────────────────────────────────────────────────────────────┐
│                      PERSISTENCE (secure vault, JSON)                     │
│                                                                            │
│  {secure}/appusers/roles.json      {secure}/appusers/users.json           │
│  role → description + permissions  username → role binding                │
│  e.g. 'Admin' => ['permissions'=>[...]]  'admin' => ['role'=>'core_admin']│
└───────────────────────────┬────────────────────────────────────────────────┘
                            │ repo_read_roles() / repo_read_users()
                            ▼
┌────────────────────────────────────────────────────────────────────────────┐
│                    SESSION LOAD (at login / each page)                    │
│                                                                            │
│  app/Domain/RBAC/rbac_service.php                                          │
│  load_user_permissions($role) → $_SESSION['user_permissions']              │
│  has_permission($key) → '*' bypass OR in_array($key)                       │
└──────────┬───────────────────┬───────────────────────┬─────────────────────┘
           │                   │                       │
           ▼                   ▼                       ▼
┌──────────────────┐ ┌───────────────────┐ ┌──────────────────────────────┐
│  LAYER 1: PAGE   │ │ LAYER 2: MENU     │ │ LAYER 3 + 4: VIEW + API     │
│  page_registry   │ │ vertical_rail.php │ │ views call has_permission()  │
│  .php            │ │ + menu_config.php │ │ controllers call             │
│  ?page= → perm   │ │ hides items       │ │ has_permission()             │
│  map, OR-lists   │ │ without perms     │ │ (email_tools, exchange,      │
│  → 403/unauth    │ │                   │ │  system_config, vendor,      │
│  page            │ │                   │ │  domain, execute_action...)  │
└──────────────────┘ └───────────────────┘ └──────────────────────────────┘
```

## Key Files

| Area | Path | Role |
|------|------|------|
| Permission catalog | `config/components_config.php` | Defines every grantable permission key (19 pages) |
| Menu mapping | `config/menu_config.php` | Menu items + required permissions (pipe = OR) |
| RBAC service | `app/Domain/RBAC/rbac_service.php` | `has_permission()`, `load_user_permissions()` |
| Repository | `app/Infrastructure/Persistence/repositories.php` | `repo_read_roles()`, `repo_write_roles()`, `repo_read_users()` |
| Role CRUD API | `app/Application/Http/Controllers/role.php` | `get_all_data`, `save_role`, `delete_role`, add/remove member |
| Page router | `app/Application/Routing/page_registry.php` | Central page-level RBAC gate |
| Rail filter | `resources/views/partials/vertical_rail.php` | Menu visibility (OR-list aware) |
| Role form JS | `public/resources/frontend/js/modules/role_management_actions.js` | Renders permission tree, save/delete/members |
| Role form view | `resources/views/pages/auth/role_form_view.php` | Create/edit role page |
| Role list view | `resources/views/pages/auth/user_management_view.php` | Role table + member management |
| Unauthorized view | `resources/views/pages/auth/unauthorized_view.php` | 403 panel |

## Data Model

### roles.json

```json
{
  "core_admin": {
    "description": "Full system access.",
    "permissions": ["*"]
  },
  "user": {
    "description": "Default read-only dashboard user.",
    "permissions": ["page_dashboard", "page_password_manager", "card_my_passwords"]
  },
  "Admin": {
    "description": "...",
    "permissions": ["global_components", "action_get_info", "...", "action_dashboard"]
  }
}
```

### users.json

```json
{
  "admin":  { "role": "core_admin" },
  "66684":  { "role": "core_admin" },
  "18644":  { "role": "user" }
}
```

## Session Loading

`rbac_service.php` reads `roles.json`, looks up `$_SESSION['role']`, and stores the permission array into `$_SESSION['user_permissions']`. `has_permission()` returns `true` immediately if `'*'` is present (the `core_admin` bypass), otherwise does a strict `in_array()`.

```php
function has_permission($permission_key) {
    if (!isset($_SESSION['user_permissions']) || !is_array($_SESSION['user_permissions'])) return false;
    if (in_array('*', $_SESSION['user_permissions'])) return true;
    return in_array($permission_key, $_SESSION['user_permissions']);
}
```

## Layer 1 — Page-Level Enforcement (page_registry.php)

`core_admin_resolve_page_config($page, ...)` runs a `page_permission_map` before the switch. Each value may be a single permission or a **pipe-separated OR-list** (any match allows access). If none match → returns the `unauthorized_view` content (Access Denied).

```php
$page_permission_map = [
    'dashboard'           => 'page_dashboard',
    'user_management'     => 'page_user_management',
    'create_user'         => 'page_user_management|user_create',
    'edit_user'           => 'page_user_management|user_edit',
    'monitoring'          => 'page_monitoring|page_ad_administration',
    'system_config'       => 'page_system_config|page_ad_administration',
    'email_tools'         => 'page_email_tools',
    'exchange'            => 'page_exchange',
    'vendor_console'      => 'page_vendor_console',
    'home'                => '',
    'default'             => '',
];
```

- OR-lists preserve **backward compatibility** — roles that still hold the legacy `page_ad_administration` key keep access.
- `''` (empty) = accessible to any authenticated user.
- `create_role` / `edit_role` are intentionally absent here — they are gated *inside* `role_form_view.php` via `page_role_management` + `action_role_create` / `action_role_edit`.

## Layer 2 — Menu Visibility (menu_config.php + vertical_rail.php)

`menu_config.php` declares each menu item with its required permission. `vertical_rail.php` parses the value with `explode('|')` (OR semantics) and skips the item if the user holds none of the listed permissions.

```php
['name' => 'Infrastructure Monitor', 'url' => '/index.php?page=monitoring',
 'icon' => 'fa-satellite-dish', 'permission' => 'page_monitoring|page_ad_administration'],
```

## Layer 3 — View-Level Guards

Views gate their own content:

```php
// role_form_view.php
if (!has_permission('page_role_management')) {
    include include_path('resources/views/pages/auth/unauthorized_view.php');
    ...exit;
}
$can_manage_role = $is_edit ? has_permission('action_role_edit') : has_permission('action_role_create');
```

## Layer 4 — Controller/API-Level Guards

Each API controller authenticates the session, loads permissions, then enforces page + action permissions before doing any work.

```php
// email_tools.php — pattern applied across controllers
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) { 401; exit; }
load_user_permissions($_SESSION['role']);

$emailPermissionMap = [
    'dns_lookup'      => 'action_email_dns_lookup',
    'smtp_test'       => 'action_email_smtp_test',
    'port_scan'       => 'action_email_port_scan',
    // ... every action → its own permission
];
$requiredPermission = $emailPermissionMap[$action] ?? 'page_email_tools';
if (!has_permission('page_email_tools') || !has_permission($requiredPermission)) { 403; exit; }
```

Controllers that follow this pattern: `email_tools.php`, `exchange.php`, `system_config.php`, `domain_api.php`, `vendor_license_api.php`, `execute_action.php`, `role.php`, `password_manager_api.php`.

## Role Form UI Flow

1. `initRoleForm()` fetches `role.php?action=get_all_data` (GET, CSRF-exempt is NOT required — GET).
2. Backend builds the **permission tree** by walking `components_config.php` with `process_ui_elements()` (pages → cards → buttons/sub_actions), preserving hierarchy.
3. `renderPermissionTree()` sorts categories by `preferredCategoryOrder`, then renders each node as a checkbox. A checked `*` (core_admin) makes every checkbox checked + disabled.
4. Save → `save_role` action (requires `action_role_create` for new / `action_role_edit` for existing). Renames + writes `roles.json`.
5. Member management → `add_role_member` / `remove_role_member` (require `action_role_add_member` / `action_role_remove_member`); writes `users.json`.

## Design Decisions

### ADR-1: File-based JSON store (no database)

Roles and user bindings live in the secure vault as JSON. Simpler ops, full portability between Docker (Linux) and IIS (Windows), trivially backup-able with the rest of the vault.

### ADR-2: Config file as the single source of truth

Permissions are declared in `components_config.php`, not duplicated in code. Adding a feature = adding a config entry + one `has_permission()` call. The role form automatically picks up new keys — no frontend change required beyond `preferredCategoryOrder`.

### ADR-3: OR-lists for backward compatibility

Legacy roles hold `page_ad_administration`. New pages use `page_monitoring`, `page_system_config`, etc. OR-lists (`page_monitoring|page_ad_administration`) let old roles keep access until they are migrated via the Edit Role page.

### ADR-4: `*` wildcard bypass

`core_admin` is the only role with `*`. `has_permission()` short-circuits on it, so `core_admin` never needs explicit per-key grants.

### ADR-5: Four enforcement layers (defense in depth)

Page registry, menu, view, and API each enforce independently. Even if one layer is bypassed (e.g., direct API call), the controller still rejects unauthorized actions with 401/403.

## Security Notes

- Roles `core_admin` and `View only` are **protected** — `delete_role` rejects them.
- The `admin` user cannot be assigned to a non-`core_admin` role (enforced in `add_role_member`).
- CSRF tokens required on all non-GET API calls (gateway `public/api/index.php`).
- Session idle timeout (15m / 2h remember-me) enforced at the gateway.
- `has_permission()` returns `false` for unknown keys — fail-closed.

## Related

- `docs/INDEX.md` — reading order for the full document set
- `app/Application/Routing/page_registry.php` — page-level gate implementation
- `config/components_config.php` — permission catalog (see `02-permission-catalog.md`)
- `resources/views/pages/auth/role_form_view.php` — role create/edit UI
- `resources/views/pages/auth/user_management_view.php` — role table + member management
