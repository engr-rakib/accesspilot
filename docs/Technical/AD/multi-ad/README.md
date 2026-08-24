# Multi-AD Management — Implementation Project

## Overview

Enable the UM Portal to manage **multiple Active Directory domains** from a single administrative interface. Users switch between domains via a badge/dropdown in the Assistant pane; all operations target the currently active domain.

## Architecture Decision

**Active Domain Switching** — Not per-operation routing. One active AD at a time. All operations target the active domain. Switching domains updates config + shared_config.json (for PowerShell).

## Directory Map

```
plan/multi-ad/
├── README.md                  # This file
├── 01-architecture.md         # Architecture decisions & data flow
├── 02-implementation-plan.md  # Full 8-phase implementation plan
├── 03-phase-checklist.md      # Phase-by-phase progress tracking
├── 04-migration-analysis.md   # Migration analysis from single-AD
└── 05-changelog.md            # Change log
```

## Phases at a Glance

| Phase | Description | Status |
|-------|-------------|--------|
| 1 | Config Storage — Domain Registry (ldap_config_repository.php) | **DONE** |
| 2 | Connection — Domain-Aware LDAP Connect | **DONE** |
| 3 | Log Path — Domain Subdirectory for Script Logs | **DONE** (PHP + PS both complete) |
| 4 | Shared Config — PowerShell shared_config.json Domain-Aware | **DONE** |
| 5 | UI — Domain Switcher in Assistant Pane (master.php) | **DONE** |
| 6 | System Config — Domain CRUD Section | **DONE** |
| 7 | API — Domain List/Switch/CRUD Endpoints | **DONE** |
| 8 | License Bundling — max_domains Enforcement | **DONE** |

## Post-Phase Work (2026-06-12)

### AD Domain Name Display & Log Path Migration

**Problem**: Dashboard badge and domain table showed config keys (`default`, `whildc`) instead of actual AD domain names (`wgbd.com`, `whildc.com`). Logs were written to key-based directories.

**Changes**:

| # | File | Change |
|---|------|--------|
| 1 | `app/Ldap/Config/ldap_config_repository.php` | Added `ldap_domain_ad_name()` and `ldap_active_domain_ad_name()` — extract AD name from `base_dn` via `preg_match_all('/DC\s*=\s*([^,]+)/i')`. Updated `ldap_set_active_domain()` to persist `active_domain_ad_name`. Updated `ldap_read_config()` to auto-seed AD name. |
| 2 | `app/Ldap/Support/ldap_helpers.php` | `ldap_write_script_log()` now uses `ldap_active_domain_ad_name()` for log path |
| 3 | `app/Infrastructure/Logging/dashboard_log_reader.php` | Added `dashboard_active_domain_ad_name()`. `dashboard_log_base_dir()` prefers AD-named dir, falls back to key-based. `dashboard_log_domain_dirs()` skips old key-named dirs when AD-named dir exists. |
| 4 | `resources/views/layouts/master.php` | Domain badge shows AD name extracted from `base_dn` instead of config key |
| 5 | `resources/views/pages/tools/system_config_view.php` | Domain table first column shows AD name as pill tag + status dot (green=active) + LICENSED badge. Column renamed "Domain Name" → "Label". Fixed orphaned `else` (SyntaxError). |
| 6 | `public/resources/frontend/js/admin/system_config_domains.js` | Guarded `DomainManager.init()` against missing `#domainModal`. Edit button now calls `openEditModal()` correctly. |
| 7 | `Desk_secure_files/ldap/config.json` | Seeded `active_domain_ad_name: "wgbd.com"` |
| 8 | `scripts/powershell/*.ps1` (15 files) | All read `active_domain_ad_name` from config.json for log folder path |
| 9 | config/app.php | Fixed favicon_path 404 (`testlogo_icon.png` → `logo_icon.png`) |

**Log Migration**: `robocopy` used to migrate:
- `C:\access_pilot_logs\default\scripts_logs\` → `wgbd.com\scripts_logs\` (877 files)
- `C:\access_pilot_logs\whildc\scripts_logs\` → `whildc.com\scripts_logs\`

**OPcache Tool**: Created `public/oc.php` — visit `https://accesspilot.wgbd.com/oc.php` when PHP changes don't reflect (clears OPcache). Documented in `analysis/tools/README.md`.

## Files Changed (Full History)

| # | File | Phase |
|---|------|-------|
| 1 | `app/Ldap/Config/ldap_config_repository.php` | 1, 2, 8, Post |
| 2 | `app/Ldap/Connection/ldap_connection_factory.php` | 2 (no change needed) |
| 3 | `app/Domain/Licensing/license_service.php` | 8 |
| 4 | `resources/views/pages/license/license_status_view.php` | 8 |
| 5 | `scripts/php/generator.php` | 8 |
| 6 | `scripts/license_admin_templates/Issue-License.ps1` | 8 |
| 7 | `scripts/license_admin_templates/Renew-License.ps1` | 8 |
| 8 | `app/Application/Http/Controllers/domain_api.php` | 7 |
| 9 | `public/api/index.php` | 7 |
| 10 | `resources/views/layouts/master.php` | 5, Post |
| 11 | `app/Ldap/Support/ldap_helpers.php` | 3, Post |
| 12 | `app/Infrastructure/Logging/dashboard_log_reader.php` | 3, Post |
| 13 | PowerShell scripts (log path) | 3, Post |
| 14 | `config/shared_config.json` | 4 |
| 15 | `app/Application/Http/Controllers/system_config.php` | 4 |
| 16 | `resources/views/pages/tools/system_config_view.php` | 6, Post |
| 17 | `public/resources/frontend/js/modules/system_config_actions.js` | 6 |
| 18 | `public/resources/frontend/css/system_config.css` | 6 |
| 19 | `public/resources/frontend/js/admin/system_config_domains.js` | Post |
| 20 | `Desk_secure_files/ldap/config.json` | Post |
| 21 | `config/app.php` | Post |
| 22 | `public/oc.php` | Post (new — OPcache utility) |
| 23 | `analysis/tools/README.md` | Post (new — tool documentation) |
