# RBAC — Technical Reference

> **Document ID:** TP-RBAC-001 · **Version:** 1.0 · **Status:** ACTIVE
> Covers the full Role-Based Access Control subsystem: permission catalog, role store, session loading, page-level routing enforcement, API enforcement, and the role create/edit UI.

---

## Document Index

| File | Purpose |
|------|---------|
| `00-COVER.md` | Document cover page (this doc's ID: **TP-RBAC-001**) |
| `01-architecture.md` | System architecture, data flow, and key design decisions |
| `02-permission-catalog.md` | Full catalog of all 19 page categories and permission keys |
| `03-implementation-guide.md` | How to add a new page/feature and wire it into RBAC |

---

## Quick Facts

- **Permission source of truth:** `config/components_config.php` (19 page categories, 277 tree items)
- **Role persistence:** `{secure_base_path}/appusers/roles.json` (`/data/secure/appusers/roles.json` on Linux, `C:/inetpub/Desk_secure_files/appusers/roles.json` on Windows)
- **User → role binding:** `{secure_base_path}/appusers/users.json`
- **Enforcement points (4 layers):**
  1. `app/Application/Routing/page_registry.php` — page-level access control
  2. `resources/views/partials/vertical_rail.php` + `config/menu_config.php` — menu visibility
  3. View-level guards (`has_permission()` in each view)
  4. Controller/API-level guards (`has_permission()` in each handler)
- **Wildcard:** `core_admin` role has `['*']` → bypasses every check
- **Special roles:** `core_admin` and `View only` are protected (cannot be deleted)

## Reading Order

1. Start with `01-architecture.md` for the big picture.
2. Review `02-permission-catalog.md` to see every grantable permission key.
3. Follow `03-implementation-guide.md` when adding a new feature.

---

*End of cover/index. Continue to `01-architecture.md`.*
