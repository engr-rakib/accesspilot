# Current Codebase Blueprint (Updated 2026-06-22)

## Directory Structure (IIS + Docker)

The same codebase deploys on both Windows (IIS) and Linux (Docker). Docker-specific files are under `docker/`.

```
/opt/accesspilot/              # Linux Docker (or C:\inetpub\wwwroot\UM-portal\ on IIS)
├── docker/                    # Docker deployment (Linux only)
│   ├── Dockerfile             # PHP 8.2-FPM with LDAP + GD + mbstring + zip
│   ├── docker-compose.yml     # Nginx + PHP-FPM, per-directory bind mounts
│   ├── nginx/default.conf     # Nginx config (FastCGI, deny rules, cache)
│   ├── deploy/
│   │   ├── install.sh         # Fresh Ubuntu auto-installer
│   │   ├── backup.sh          # Timestamped code + data backups
│   │   ├── rollback.sh        # Restore from backup
│   │   └── post_upload_cleanup.sh  # After WinCP transfer
│   └── README.md              # Docker deployment guide
├── app/                          # Core logic (Application, Domain, Infrastructure, Ldap)
│   ├── Application/
│   │   ├── Http/
│   │   │   ├── Router/front_controller.php  # Route map + resolve/dispatch (13 routes)
│   │   │   ├── Controllers/      # 51 snake_case controller files (inc. domain_api.php for multi-AD, vendor_license_api.php for vendor console)
│   │   │   └── admin_portal.php  # Shell controller → page_registry + master.php
│   │   ├── Middleware/            # session_guard.php (15-min idle timeout, forced logout, session regen every 5min)
│   │   ├── Routing/              # page_registry.php (includes php files for each page), spa_response.php
│   │   └── Support/              # helpers.php, diagnostics_guide.php
│   ├── Domain/
│   │   ├── ActiveDirectory/      # ad_action_service.php, action_executor.php
│   │   ├── AdUserRequest/        # User request workflows
│   │   ├── Audit/                # audit_service.php
│   │   ├── Auth/                 # auth_service.php (rate limiting, password strength),
│   │   │                         # auth_session_service.php (CSRF tokens, session regen, cookie params)
│   │   ├── HRMS/                 # directory_info_service.php (SSL verify enabled)
│   │   ├── Ldap/                 # LDAP-specific domain logic
│   │   ├── Licensing/            # RSA-2048 license enforcement
│   │   └── RBAC/                 # rbac_service.php, repositories.php
│   ├── Infrastructure/
│   │   ├── Logging/              # dashboard_log_reader.php (recursive scan + category path map)
│   │   ├── Mail/
│   │   ├── Persistence/          # repositories (read_users, users_path, secure_base_path), storage state
│   │   ├── PowerShell/           # powershell_runner.php (password redaction, command builder)
│   │   └── Security/
│   └── Ldap/
│       ├── Config/               # ldap_config_repository.php (multi-AD: domains.json CRUD, per-domain secrets)
│       ├── Connection/           # ldap_connection_factory.php (ldap_connect_and_bind)
│       ├── Diagnostics/          # LDAP health checks
│       ├── Operations/           # ldap_user_writer.php (group auto-create),
│       │                         # ldap_user_repository.php, ldap_group_repository.php,
│       │                         # ldap_operation_catalog.php
│       ├── Router/               # ad_operation_router.php (backend resolution + dispatch)
│       ├── Services/
│       └── Support/              # ldap_helpers.php (ldap_json_script_result)
├── App_Data/                     # Internal lock files, sessions, setup_complete.lock
├── App_Config/                   # Runtime configuration overrides
├── bootstrap/                    # app.php (cookie params), request_context.php
├── config/                       # powershell.php (script path registry), ldap/, storage.php, ui.php, etc.
├── public/                       # Web root — single entry point
│   ├── index.php                 # FRONT CONTROLLER — routes all page requests
│   ├── web.config                # Default document + 365-day static cache headers (IIS)
│   ├── .htaccess                 # Apache rewrite rules (for future Linux migration)
│   ├── login.php → stub         # 13 PHP stubs (2 lines each) for backward compat
│   ├── logout.php → stub         # Sets $_GET['route'] → includes index.php
│   ├── register.php → stub
│   ├── ... (13 stubs total)
│   ├── api/index.php             # API gateway (CSRF validation, session startup, routing, 50+ endpoints)
│   ├── resources/frontend/js/
│   │   ├── dashboard/            # dash_pro_scripts.js, chart_renderer.js
│   │   ├── modules/              # 18 JS modules (action_handler, create_user_actions, vendor_actions, etc.)
│   │   └── admin/                # 17 admin scripts (dashboard_logic, menu_handler, etc.)
│   └── robots.txt
├── resources/views/
│   ├── layouts/master.php        # 3-pane shell, CSRF token injection, fetch() wrapper
│   └── pages/                    # 27 view files across auth/, dashboard/, audit/, license/, etc.
├── scripts/powershell/           # 30 PowerShell scripts (see details below)
├── analysis/                     # Audit reports, blueprints, migration notes
├── .htaccess                     # Apache rewrite rule (inert on IIS, ready for Linux)
├── AGENTS.md                     # Session context for future agents
└── README.md
```

## Log Directory Structure (on disk: `C:\access_pilot_logs\scripts_logs\`)
```
logs\
├── NewUser\                  # create-user-core.ps1
│   └── New_user_transcript_logs\  # Transcript of all NewUser operations
├── ManualCreate\             # manual-create-ad-user.ps1
├── PassReset\                # reset-unlock-user.ps1
├── unlock\                   # unlock-user.ps1
├── UserDisable\              # disable-user.ps1
├── UserEnable\               # enable-user.ps1
├── UserModify\               # modify-ad-user.ps1
├── UserInfo\                 # get-user-info.ps1
├── Ou_Group_Mgt\             # create/delete-ad-directory-object.ps1
├── GroupMgmt\                # set-ad-group-members.ps1
├── GroupMembership\          # get-ad-group-members.ps1
├── EmpStsChk\                # check-ad-hrms-status.ps1
├── FindLogonID\              # export-hrms-ad-login-id.ps1
├── user_export\              # export-group-user-list.ps1
├── HealthCheck\              # get-ad-health.ps1
└── General\                  # app audit logs (CSV)
```

Controllers now follow snake_case convension.
Log directories now follow a nested, category-based structure with `dashboard_category_path_map()` in `dashboard_log_reader.php` providing the lookup.

## Entry Points
- **Page requests** → `public/index.php` (single entry point via front controller)
- **SPA page requests** → `public/index.php?page=xxx` → `admin_portal.php` → `page_registry.php`
- **API requests** → `public/api/index.php` (direct access, CSRF-protected, 50+ endpoints)
- **Backward compat** → 13 PHP stubs in `public/` (2 lines each, relay to front controller)

## Routing — Front Controller
`app/Application/Http/Router/front_controller.php`
- `ROUTE_MAP` — 13 routes (7 views, 6 controllers)
- `resolve_route()` — checks `?route=` → `PATH_INFO` → `REQUEST_URI`
- `dispatch_route()` — view: includes page; controller: helpers + license + includes controller
- `.htaccess` — `mod_rewrite` sends non-file URIs to `index.php?route=...` (Apache)
- 13 stubs — same effect without URL Rewrite module (IIS)

## Page Registry (SPA Shell)
`app/Application/Routing/page_registry.php`
- A function `core_admin_resolve_page_config()` maps `?page=` parameter to views
- Currently supports: `dashboard`, `audit`, `security_events`, `notification`, `role`, `search-user`, `portal-setting`, `ad_user_request_admin`, `ad_user_request_public`, `log_data`, `multi_ad`, `system_config`, `vendor_console`
- Each case includes a `.php` view from `resources/views/pages/`
- Some pages also inject JS modules via `$js_modules[]`
- Page switch happens via SPA: `fetch` → JSON response → `spa_response.php` renders new view → `spaContentUpdated` event triggers re-init

## Key Files

### Authentication & Session Security
- `app/Domain/Auth/auth_session_service.php` — CSRF token generation (`bin2hex(random_bytes(32))`), session regen, secure cookie params
- `app/Domain/Auth/auth_service.php` — Login rate limiting (5 failed → 30-min lockout), password strength validation (8+ chars, upper, lower, digit, special)
- `app/Application/Middleware/session_guard.php` — 15-min idle timeout; session regen every 5 min (not per-request, prevents AJAX race conditions)
- `bootstrap/app.php` — Sets `session_set_cookie_params()` with `httponly`, `SameSite=Lax`, conditional `secure`
- `public/api/index.php` — Session startup, CSRF validation for non-GET non-auth requests
- `resources/views/layouts/master.php` — `window._csrfToken`, global `fetch()` wrapper auto-adds `X-CSRF-Token` header, injects `--font-*` CSS variables from ui.php typography config
- `config/ui.php` — UI configuration (button colors, theme definitions, `typography.font_sizes`: 16 tokens controlling all app font sizes)
- `app/Application/Http/Controllers/auth.php` — Login/register, password change with secure cookie params

### LDAP Operations (PHP In-Process)
- `app/Ldap/Operations/ldap_user_writer.php` — Create, update, modify, enable, disable, unlock users; **auto-creates groups** if not found on user creation
- `app/Ldap/Operations/ldap_user_repository.php` — User search, fetch, attribute query
- `app/Ldap/Operations/ldap_group_repository.php` — Group CRUD
- `app/Ldap/Operations/ldap_operation_catalog.php` — Maps operations → PHP handlers + PS script keys
- `app/Ldap/Router/ad_operation_router.php` — Backend resolution (powershell/ldap/auto) + dispatch
- `app/Ldap/Connection/ldap_connection_factory.php` — `ldap_connect_and_bind()`, `ldap_test_connection()`
- `app/Ldap/Support/ldap_helpers.php` — `ldap_json_script_result()`, `ldap_feedback_message()`, `ldap_write_script_log()` (path map + message cleaning for both backends)

### Domain Services
- `app/Domain/ActiveDirectory/ad_action_service.php` — AD action dispatch, `ldap_escape_dn_component()` for OU fields
- `app/Domain/HRMS/directory_info_service.php` — `getADUserInfo()`, `getHRMSInfo()` with SSL verify enabled (`CURLOPT_SSL_VERIFYPEER`, `CURLOPT_SSL_VERIFYHOST`)
- `app/Domain/Audit/audit_service.php` — Activity logging

### Infrastructure & Logging
- `app/Infrastructure/PowerShell/powershell_runner.php` — `powershell_run_script()`, `powershell_run_json_script()`, `powershell_build_command()`; password redaction regex for 5 parameter patterns
- `config/powershell.php` — Script registry: maps action keys to PS script filenames
- `app/Infrastructure/Logging/dashboard_log_reader.php` — `dashboard_category_path_map()` with recursive file scanning, time filtering

### Controllers (51 files, all snake_case, some retain _api.php suffix)
All renamed from `*_api.php`, `*_controller.php`, `*_action.php` suffixes — see `analysis/controller_rename_log.md`.

**New addition (2026-06):**
- `vendor_license_api.php` — Retains `_api.php` suffix because it is included directly from `api/index.php` endpoint routing (not through front controller). Provides 14 actions for the Vendor Console page (CRUD, signing key, release pack, credential verify).

## PHP Dependencies
- `ext-ldap` — In-process LDAP operations (PHP LDAP backend mode)
- `ext-gd` (php_gd2.dll on IIS, compiled-in on Docker) — Avatar upload re-encoding (whitelist: jpg, jpeg, png, gif, webp)
- `ext-json` — JSON encoding/decoding
- `ext-session` — Session management (secure cookie flags)
- `ext-curl` — HRMS API calls (SSL verification enabled)
- `ext-mbstring` — String operations
- **`ext-zip`** (php_zip.dll on IIS) — ZipArchive for client release pack build (2026-06)

## Performance Optimizations
| Setting | Value | Effect |
|---------|-------|--------|
| `opcache.jit` | tracing | CPU-bound code 3-8x faster |
| `opcache.memory_consumption` | 256M | More cached scripts |
| `opcache.max_accelerated_files` | 20000 | More files cached |
| `opcache.revalidate_freq` | 300 | Reduce file stat calls (5 min) |
| `opcache.file_cache` | `C:\php8.5.4_nts\opcache_file_cache` | Disk fallback cache |
| `realpath_cache_size` | 4096k | Reduce filesystem stat calls |
| `zlib.output_compression` | On | gzip compression (disabled per-request in vendor_license_api.php via @ini_set) |
| `web.config staticContent` | 365-day `Cache-Control` | Browser caches CSS/JS/images |
| Asset versioning | `?v=5.07` (app version) | Cache busting on update, cached between versions |

## Infrastructure Paths

| Resource | Windows (IIS) | Linux (Docker) |
|----------|---------------|----------------|
| PHP binary | `C:\php8.5.4_nts\php.exe` | Container: `php:8.2-fpm-bookworm` |
| PHP config | `C:\php8.5.4_nts\php.ini` | Container default + Dockerfile overrides |
| Error log | `C:\php8.5.4_nts\logs\phperror8.5.4_nts.log` | `docker compose logs php` |
| Session storage | `C:\php8.5.4_nts\sessions\` | Container memory (session file per PHP process) |
| Opcache | `C:\php8.5.4_nts\opcache_file_cache\` | In-memory (256M), `opcache_reset()` after changes |
| Secure data | `C:\inetpub\Desk_secure_files\` | `/data/secure` (host bind mount) |
| Audit logs | `C:\access_pilot_logs\` | `/data/logs` (host bind mount) |
| Web root | `C:\inetpub\wwwroot\UM-portal\public\` | `/var/www/html/public` (container) |

## Security Boundaries (Implemented)
| Layer | Mechanism | Location |
|-------|-----------|----------|
| CSRF | `bin2hex(random_bytes(32))` token per session, validated on non-GET non-auth requests | `auth_session_service.php`, `api/index.php`, `master.php` |
| Session cookie | `HttpOnly`, `SameSite=Lax`, conditional `Secure` | `bootstrap/app.php` |
| Session regen | Every 5 min (not per-request, prevents AJAX race condition) | `session_guard.php` |
| Idle timeout | 15 min from `last_activity` (2h remember-me) | `session_guard.php` |
| Rate limiting | 5 failed attempts → 30-min lockout (per `$_SESSION`) | `auth_service.php` |
| Password policy | ≥8 chars, ≥1 upper, ≥1 lower, ≥1 digit, ≥1 special | `auth_service.php` + `login.php` (frontend) |
| AVATAR upload | GD re-encoding strips EXIF/metadata; extension whitelist | `profile.php` |
| LDAP injection | `ldap_escape_dn_component()` on all 5 OU fields | `ad_action_service.php` |
| command injection | `powershell_build_command()` replaces raw `exec()`+`sprintf()` | `powershell_runner.php` |
| SSL verification | `VERIFYPEER=true`, `VERIFYHOST=2` for HRMS API | `directory_info_service.php` |
| Display errors | `display_errors = 0`, `log_errors = 1` in all production files | All controllers + entry points |
| Password redaction | Regex catches 5 parameter patterns in PowerShell commands | `powershell_runner.php` |
| `extract()` | Replaced with explicit variable assignments | `admin_portal.php`, `spa_response.php` |
| **Credential gate** | **Separate password re-verify for sensitive pages** | **`vendor_license_api.php` → `vendor_verify_creds`** |

## Important State Files (Not in Repo)

| File | Windows (IIS) | Linux (Docker) |
|------|---------------|----------------|
| LDAP config | `C:\inetpub\Desk_secure_files\ldap\config.json` | `/data/secure/ldap/config.json` |
| Bind secret (legacy) | `C:\inetpub\Desk_secure_files\ldap\bind_secret.json` | `/data/secure/ldap/bind_secret.json` |
| Last LDAP test | `C:\inetpub\Desk_secure_files\ldap\last_test.json` | `/data/secure/ldap/last_test.json` |
| Domain configs | `C:\inetpub\Desk_secure_files\ldap\domains.json` | `/data/secure/ldap/domains.json` |
| Per-domain secrets | `C:\inetpub\Desk_secure_files\ldap\secrets\*.json` | `/data/secure/ldap/secrets/*.json` |
| RBAC roles | `C:\inetpub\Desk_secure_files\appusers\roles.json` | `/data/secure/appusers/roles.json` |
| Registered users | `C:\inetpub\Desk_secure_files\appusers\users.json` | `/data/secure/appusers/users.json` |
| AD user requests | `C:\inetpub\Desk_secure_files\requests\ad_user_requests.json` | `/data/secure/requests/ad_user_requests.json` |
| Vendor licenses | `C:\inetpub\Desk_secure_files\vendor_licenses\licenses.json` | `/data/secure/vendor_licenses/licenses.json` |
| Deployment identity | `C:\inetpub\Desk_secure_files\accesspilot_deployment_identity.xml` (does not exist) | `/data/secure/accesspilot_deployment_identity.xml` (does not exist) |

## Known Discrepancies (Docs vs Reality)
- `accesspilot_deployment_identity.xml` referenced in 88+ `include_secure_config => true` call sites — **file does not exist**
- 48px header width claimed in some docs — actual CSS uses `--shell-header-height: 52px`
- `health-check.ps1` referenced in LDAP README — actual file is `get-ad-health.ps1`
- Pre-2026-06 docs referenced hardcoded font sizes (0.85rem body, 0.65rem badges) — now all centralized in `config/ui.php → typography.font_sizes` (15px HTML base, `--font-base: 0.95rem`)
- Some old CSS comments reference `14px` base font — now uses `15px`
- `vendor_license_api.php` retains `_api.php` suffix (unlike other renamed controllers) because it loads directly via `api/index.php` endpoint routing, not through the front controller
- `output_buffering=4096` + `zlib.output_compression=On` can swallow JSON responses on timeout/crash — vendor_license_api.php disables both per-request with `@ini_set` + `ob_end_clean()`

## Deployment Checklist

### For Both Platforms
- [ ] `roles.json` — Must be UTF-8 **without BOM**
- [ ] `accesspilot_deployment_identity.xml` — Must exist if `include_secure_config => true` is used (currently does not exist; scripts fall through)
- [ ] CLI XML credential files — Generated via `create-credential-config.ps1`
- [ ] LDAP admin bind account — Must be active

### Windows (IIS)
- [ ] `php_gd2.dll` — Enable in php.ini for avatar upload
- [ ] `php_zip.dll` — Enable for client release pack build
- [ ] Log directories — Auto-created by PowerShell scripts
- [ ] IIS URL Rewrite module — Not installed; 13 PHP stubs serve as fallback
- [ ] Error log path — Updated in php.ini to `C:\php8.5.4_nts\logs\`
- [ ] Session path — Updated in php.ini to `C:\php8.5.4_nts\sessions\`

### Linux (Docker)
- [ ] Storage dirs exist: `mkdir -p /data/secure /data/logs && chown 33:33 /data/secure /data/logs`
- [ ] Run `docker compose build` after code changes
- [ ] Clear OPcache after PHP changes: `docker exec accesspilot_php php -r 'opcache_reset();'`
- [ ] Hard refresh (Ctrl+F5) after JS/CSS changes
- [ ] Restart containers after WinCP transfer (inode change): `docker compose restart`
