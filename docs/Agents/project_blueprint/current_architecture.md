# Current Architecture (Updated 2026-06-22)

## 1. High-Level Layering
- **Application Layer (`app/Application/`)**: HTTP request handling, SPA JSON responses, admin portal shell, session guarding (15-min idle timeout, regen every 5 min), CSRF validation.
- **Domain Layer (`app/Domain/`)**: Core business logic — AD actions, User Request services, HRMS integration, Licensing (RSA-2048), RBAC, Audit trails, Auth (rate limiting + password strength).
- **LDAP Layer (`app/Ldap/`)**: In-process PHP AD automation via `ext-ldap` (user, group, directory operations). New: group auto-creation on user create.
- **Infrastructure Layer (`app/Infrastructure/`)**: PowerShell execution engine (`powershell_runner.php`), mail, JSON persistence, logging (`dashboard_log_reader.php` with recursive category path map).

### Cross-Cutting
- `helpers.php` — Path resolution (`resolved_secure_config_path()`, `secure_path()`, `storage_path()`)
- `diagnostics_guide.php` — Error humanization (4 functions)
- `front_controller.php` — Route map + resolve/dispatch for all page requests

## 2. Front Controller Routing
All page requests flow through `public/index.php` (single entry point):

```
Request → /login.php
  → PHP stub sets $_GET['route'] = 'login'
  → index.php → front_controller.php
  → resolve_route() returns 'login'
  → dispatch_route('login') → view type → includes resources/views/pages/auth/login.php

Request → /audit.php?start=...&end=...
  → PHP stub sets $_GET['route'] = 'audit'
  → index.php → front_controller.php
  → resolve_route() returns 'audit'
  → dispatch_route('audit') → controller type → includes app/Http/Controllers/audit.php

Request → / or /index.php?page=xxx
  → resolve_route() returns ''
  → dispatch_route('') returns false (not found)
  → Falls through to admin_portal.php (SPA)
```

13 routes in `ROUTE_MAP` (7 views, 6 controllers). 13 PHP stubs in `public/` for IIS backward compatibility (URL Rewrite module not installed).

## 3. The 3-Pane Shell Model
- **Rail (68px):** Module switching + theme control (`--shell-rail-width: 68px`)
- **Assistant (280px):** Search + quick operations (`--shell-context-width: 280px`)
- **Workspace (Fluid):** Data rendering with 52px header (`--shell-header-height: 52px`)

### Typography System
All font sizes are centralized in `config/ui.php → typography.font_sizes` (16 tokens). Injected as CSS custom properties via `master.php:root`:
- `--font-xs` (0.7rem) → badges, status labels
- `--font-sm` (0.8rem) → table headers, sidecard buttons
- `--font-base` (0.95rem) → body, inputs, buttons
- `--font-md` (1rem) → card titles
- `--font-table` (0.8rem) → table body cells
- `--font-info` (0.85rem) → server/employee info cards
- `--font-feedback` (0.85rem) → feedback message card

HTML base: **15px**, body line-height: **1.6**. Responsive breakpoints: 15px → 13px (≤992px) → 11px (≤768px).

CSRF token injected via `window._csrfToken` in `master.php`; global `fetch()` wrapper auto-adds `X-CSRF-Token` header.

## 4. Authentication & Session Flow
```
Login Form → auth.php → auth_login() 
  → auth_validate_password_strength()    # ≥8 chars, upper+lower+digit+special
  → auth_rate_limit_check()              # 5 fails → 30-min lockout
  → auth_start_user_session()            # session_regenerate_id(true)
     → CSRF token: bin2hex(random_bytes(32))
     → session cookie: HttpOnly, SameSite=Lax, Secure if HTTPS+443
     → last_activity timestamp
  → session_guard.php (on every request)
     → checks 15-min idle timeout
     → regenerates session ID every 5 min (not per-request)
     → prevents AJAX race conditions
  → forced logout if idle >15 min
```

### Session Regen Strategy
`session_guard.php:20` — Changed from `session_regenerate_id(true)` every request to every 5 minutes:
- Tracked via `$_SESSION['last_regenerated']`
- Prevents race conditions when multiple AJAX calls fire concurrently
- Still provides session fixation protection via periodic regeneration

### Password Change Flow
- Both login form and profile page enforce `minlength="8"` + regex pattern on frontend
- Backend `auth_validate_password_strength()` returns error string or null

## 5. CSRF Protection Flow
```
1. Login: auth_start_user_session() generates token → stored in $_SESSION['csrf_token']
2. Page load: master.php injects window._csrfToken = token from PHP
3. API calls: global fetch() wrapper auto-adds header X-CSRF-Token
4. Validation: api/index.php checks header === $_SESSION['csrf_token']
   - Applied to all non-GET, non-auth endpoints
   - Login/register/reset-password endpoints are exempt
```

API calls to `/audit.php`, `/notification.php`, `/ad_user_request_admin.php` (stub-files, not API gateway) do NOT go through CSRF validation — they rely on session authentication check inside the controller instead.

## 6. Dual-Backend Routing & Execution
`ad_operation_router.php` resolves actions in 3 modes:
- **`powershell`** — runs `.ps1` via `powershell_runner.php` (`exec()` for IIS compat)
- **`ldap`** — in-process PHP LDAP via `ext-ldap`
- **`auto`** — LDAP first, fallback to PowerShell

### PowerShell Execution Path
```
Controller → ad_action_service.php → action_executor.php 
  → powershell_run_script()/powershell_run_json_script()
  → powershell_build_command() (password redaction via regex)
  → exec() → stdout JSON → diagnostics_guide.php (on error)
  → integrated Write-Log function writes structured log to nested path
```

### LDAP Execution Path (New: Group Auto-Create)
```
Controller → ad_ldap_execute() → ldap_operation_catalog.php
  → ldap_user_writer_create() or ldap_user_writer_modify() etc.
  → On create: auto-creates missing groups via ldap_group_repository_create()
  → ldap_json_script_result() normalizes output
  → ad_ldap_execute() calls ldap_write_script_log() with cleaned message
```

## 7. Logging Architecture
Both backends (PowerShell + PHP LDAP) write logs under `$BaseLogPath\scripts_logs\` with nested category paths, using the same path map defined in `ldap_helpers.php:460-475` and `dashboard_log_reader.php`.

Logs are now organized per-domain: `$BaseLogPath\{active_domain}\scripts_logs\{Category}\{action}`. The active domain is read from `ldap_active_domain_key()` (PHP) or `shared_config.json` (PowerShell). All 29 PowerShell scripts consistently read `active_domain` from `shared_config.json`.

| Category | Path | PowerShell Script | PHP LDAP |
|----------|------|-------------------|----------|
| NewUser | `User_Management/NewUser/` | create-user-core.ps1 | `ldap_user_writer_create()` |
| ManualCreate | `User_Management/ManualCreate/` | manual-create-ad-user.ps1 | — |
| PassReset | `User_Management/PassReset/` | reset-unlock-user.ps1 | `ldap_user_writer_reset_password()` |
| unlock | `User_Management/unlock/` | unlock-user.ps1 | `ldap_user_writer_unlock()` |
| UserDisable | `User_Management/UserDisable/` | disable-user.ps1 | `ldap_user_writer_set_enabled()` |
| UserEnable | `User_Management/UserEnable/` | enable-user.ps1 | `ldap_user_writer_set_enabled()` |
| UserModify | `User_Management/UserModify/` | modify-ad-user.ps1 | `ldap_user_writer_modify()` |
| UserInfo | `User_Management/UserInfo/` | get-user-info.ps1 | `ldap_user_repository_find()` |
| Ou_Group_Mgt | `Directory_Services/Ou_Group_Mgt/` | create/delete-ad-directory-object.ps1 | `ldap_directory_writer*()` |
| GroupMgmt | `Directory_Services/GroupMgmt/` | set-ad-group-members.ps1 | `ldap_group_repository_set_members()` |
| GroupMembership | `Directory_Services/GroupMembership/` | get-ad-group-members.ps1 | `ldap_group_repository_get_members()` |
| EmpStsChk | `Integration/EmpStsChk/` | check-ad-hrms-status.ps1 | — |
| FindLogonID | `Integration/FindLogonID/` | export-hrms-ad-login-id.ps1 | — |
| user_export | `Integration/user_export/` | export-group-user-list.ps1 | — |
| HealthCheck | `HealthCheck/` | get-ad-health.ps1 | — |

Log entries follow the same format regardless of backend:
```
[YYYY-MM-DD hh:mm:ss AM/PM] Action: <ACTION> | TargetUser: <user> | Status: <SUCCESS|FAILED|SKIPPED> | Message: <message> | ExecutedBy: <operator>
```
The Message field contains no status prefix (`SUCCESS:`/`ERROR:`) and no summary line — the Status column is the sole indicator.

Dashboard reader (`dashboard_log_reader.php`) uses `dashboard_category_path_map()` for path resolution and recursive `DirectoryIterator` for file scanning.

## 8. Credential Config Workflow
`create-credential-config.ps1` generates Clixml credential files for health check:
```
Called from get-ad-health-check-deep.php?
  → powershell_run_json_script('CreateCredentialConfig', ...)
  → Script saves encrypted credential to configured path
  → Then get-ad-health.ps1 loads it via ldap_ad_helpers.ps1
```

## 9. Performance Optimizations
| Setting | Value | Location |
|---------|-------|----------|
| `opcache.jit` | tracing | `php.ini` |
| `opcache.memory_consumption` | 256M | `php.ini` |
| `opcache.max_accelerated_files` | 20000 | `php.ini` |
| `opcache.revalidate_freq` | 300 | `php.ini` |
| `opcache.file_cache` | `C:\php8.5.4_nts\opcache_file_cache` | `php.ini` |
| `realpath_cache_size` | 4096k | `php.ini` |
| `realpath_cache_ttl` | 600 | `php.ini` |
| `zlib.output_compression` | On | `php.ini` |
| Cache headers | 365-day `Cache-Control` | `public/web.config` |
| Asset versioning | `?v=5.07` (app version) | `config/app.php` → `app_info.version` |
| Duplicate JS load | Removed chart_renderer.js from 8 per-page entries | `page_registry.php` |

## 10. Infrastructure Paths
| Resource | Path |
|----------|------|
| PHP binary | `C:\php8.5.4_nts\php.exe` |
| PHP config | `C:\php8.5.4_nts\php.ini` |
| Error log | `C:\php8.5.4_nts\logs\phperror8.5.4_nts.log` |
| Session storage | `C:\php8.5.4_nts\sessions\` |
| Opcache file cache | `C:\php8.5.4_nts\opcache_file_cache\` |
| Secure data | `C:\inetpub\Desk_secure_files\` |
| Web root | `C:\inetpub\wwwroot\UM-portal\public\` |

## 11. Key State Files
- `{secure_base}/ldap/config.json` — LDAP runtime config (includes `active_domain` key)
- `{secure_base}/ldap/bind_secret.json` — Encrypted bind password (legacy, fallback for domain-less mode)
- `{secure_base}/ldap/domains.json` — Array of domain configs (key, label, host, IP, port, TLS, base_dn, bind_dn, etc.)
- `{secure_base}/ldap/secrets/{key}.json` — Per-domain encrypted bind passwords
- `{secure_base}/appusers/roles.json` — RBAC (must be UTF-8 without BOM)
- `{secure_base}/appusers/users.json` — Registered users
- `{secure_base}/requests/ad_user_requests.json` — AD user requests (pending + completed)
- `{secure_base}/accesspilot_deployment_identity.xml` — **does not exist** (scripts fall through to env vars/direct params)
- `config/shared_config.json` — JSON mirror for PowerShell consumption (includes `active_domain`)

## 12. API Gateway
AJAX requests are routed through `public/api/index.php` (NOT the front controller). The `endpoint` query parameter selects a handler from `$allowed_endpoints` (50+ entries). CSRF protection: non-GET requests require `X-CSRF-Token` header matching `$_SESSION['csrf_token']`.

### Registered API Handlers
| Endpoint | Handler Controller File | Purpose |
|----------|------------------------|---------|
| `domain_api` | `app/Application/Http/Controllers/domain_api.php` | Multi-AD domain CRUD + switch + test/resolve |
| `system_config_api` | `app/Application/Http/Controllers/system_config.php` | Org setup, credentials, storage |
| `get_infrastructure_diagnostics` | `app/Application/Http/Controllers/get_infrastructure_diagnostics.php` | Health guide diagnostics |
| `auth_api` | `public/api/auth.php` | Authentication |
| `license_api` | `public/api/license.php` | License management |

Full list in `public/api/index.php:9-52`.

### Vendor Console API Endpoints (`vendor_license_api`)
The controller `app/Application/Http/Controllers/vendor_license_api.php` retains the `_api.php` suffix and is loaded via `api/index.php` endpoint routing (`vendor_license_api` → `vendor_license_api.php`). It handles the Vendor Console page (`?page=vendor_console`):

| Action | Method | Purpose |
|--------|--------|---------|
| `vendor_list` | GET | List all saved licenses |
| `vendor_get` | GET | Get single license by ID |
| `vendor_save` | POST | Generate new license + save |
| `vendor_update` | POST | Update license fields |
| `vendor_delete` | POST | Delete license |
| `vendor_download` | GET | Download license as JSON/PEM |
| `vendor_verify` | POST | Run integrity checks |
| `vendor_key_status` | GET | Check RSA signing key presence |
| `vendor_save_key` | POST | Upload RSA private key |
| `vendor_delete_key` | POST | Remove signing key |
| `vendor_decode_deploy` | GET | Decode deployment ID |
| `vendor_build_release` | POST | Build client release zip |
| `vendor_download_release` | GET | Download built zip |
| `vendor_verify_creds` | POST | Verify password for page access |

**Key patterns:**
- **Credential gate**: Page access requires `vendor_verify_creds` POST first. Session stores `vendor_console_verified` flag.
- **Release pack**: Pure PHP `ZipArchive` build (no PowerShell), temp file in `sys_get_temp_dir()`, deleted after download.
- **Signing key**: RSA private key stored as PEM in vault under `scripts/license_admin_templates/vault/`.
- **Auth check**: All actions guarded by `$_SESSION['authenticated']` check at controller top.
- **gzip fix**: `@ini_set('zlib.output_compression', '0')` + `ob_end_clean()` at controller start prevents gzip from swallowing JSON.

### Domain API Endpoints (`domain_api.php`)
| Action | Method | Function |
|--------|--------|----------|
| `list_domains` | GET | Returns all domains with `bind_password_stored`, active key, license limits |
| `switch_domain` | POST | Switches active domain, syncs `shared_config.json`, logs activity |
| `add_domain` | POST | Adds domain with validation (key format, host, base_dn, password; enforces `max_domains`) |
| `update_domain` | POST | Partial update (merges with existing fields) |
| `delete_domain` | POST | Deletes domain (blocks if active, requires switch first) |
| `test_connection` | POST | DNS resolve + LDAP connect/bind test, returns latency + resolved IP |
| `resolve_host` | POST | `gethostbynamel()` DNS resolution, returns first IP |

All POST endpoints require `page_ad_administration` RBAC permission and are CSRF-protected.

## 13. Containerized Hosting (Docker)

### Architecture
Nginx 1.25-alpine + PHP 8.2-FPM with compiled `ext-ldap` + `ext-gd`. Two containers connected via `accesspilot_net` bridge network.

```
accesspilot_web (Nginx:80) → FastCGI TCP 9000 → accesspilot_php (PHP-FPM)
```

Host port 8080 maps to Nginx port 80. Data paths overridden via `ACCESSPILOT_SECURE_BASE_PATH` and `ACCESSPILOT_LOG_BASE_PATH` env vars.

### Directory Structure (`/opt/accesspilot/docker/`)
| Path | Purpose |
|------|---------|
| `Dockerfile` | PHP 8.2-FPM with LDAP, GD, mbstring, zip |
| `docker-compose.yml` | Service definitions, env vars, per-directory ro bind mounts |
| `nginx/default.conf` | Nginx config (FastCGI, deny rules, caching) |
| `deploy/install.sh` | Fresh Ubuntu auto-installer (Docker, Nginx reverse proxy, systemd service, optional SSL) |
| `deploy/backup.sh` | Timestamped backups (code + data volumes, 7-day retention) |
| `deploy/rollback.sh` | Restore from backup, verifies HTTP response |
| `deploy/post_upload_cleanup.sh` | Remove Windows-specific runtime files after WinCP transfer |

### Volume Mapping (per-directory read-only mounts)
| Host | Container | Mode |
|------|-----------|------|
| `/data/secure` | `/data/secure` | rw |
| `/data/logs` | `/data/logs` | rw |
| `../app` | `/var/www/html/app` | ro |
| `../bootstrap` | `/var/www/html/bootstrap` | ro |
| `../config` | `/var/www/html/config` | rw (for org registration) |
| `../public` | `/var/www/html/public` | ro |
| `../resources` | `/var/www/html/resources` | ro |
| `../App_Data` | `/var/www/html/App_Data` | rw |

### Nginx Deny Rules
```
location ~ ^/(app|bootstrap|config|scripts|App_Data)/  { deny all; }
location ~ /\.                                          { deny all; }
```

### IIS Coexistence
The same codebase runs on IIS with PHP 8.5.4 NTS (Windows). 13 PHP stubs in `public/` serve as IIS URL Rewrite fallbacks (module not installed). Configuration differences:

| Aspect | Linux Docker | Windows IIS |
|--------|-------------|-------------|
| Web server | Nginx 1.25 | IIS 10 |
| PHP | 8.2-FPM (mod_ldap) | 8.5.4 NTS (php_ldap.dll) |
| Secure path | `/data/secure` | `C:\inetpub\Desk_secure_files\` |
| Log path | `/data/logs` | `C:\access_pilot_logs\` |
| Entry | `docker compose up -d` | IIS site bind + PHP-CGI |

### OPcache (Docker)
Set via `php.ini` in the container:
- `opcache.jit = tracing`
- `opcache.memory_consumption = 256M`
- `opcache.revalidate_freq = 300`

Clear after PHP changes: `docker exec accesspilot_php php -r 'opcache_reset();'`
