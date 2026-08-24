# Application A to Z: Operational Lifecycle (Updated 2026-06-22)

## 1. Request Transition (Client to Server)
1. **User Action:** User clicks button in Assistant Pane or Workspace or hits a URL directly.
2. **Front Controller Resolution:** `public/index.php` → `front_controller.php` resolves route:
   - Direct `.php` URLs: stub file (e.g., `login.php`) sets `$_GET['route']` → `index.php`
   - Clean URLs (Apache): `.htaccess` rewrite → `?route=...` → `index.php`
   - SPA page requests: `/index.php?page=xxx` → falls through to `admin_portal.php`
3. **SPA Page Load (page=xxx):** `admin_portal.php` → includes `page_registry.php` → matches `case 'xxx'` → includes view from `resources/views/pages/` → `spa_response.php` renders view into JSON → client swaps content → `spaContentUpdated` event fires → JS module initializes
4. **SPA Interception (for AJAX):** The appropriate JS module intercepts clicks, collects form data, builds POST payload.
5. **CSRF Injection:** Global `fetch()` wrapper adds `X-CSRF-Token` header from `window._csrfToken` (only for `/api/` endpoints).
6. **Transition Animation:** `swipeDown` animation shows loading state in `actionTakenCardContainer`.
7. **API Request:** AJAX POST to either:
   - `public/api/index.php?endpoint=<action>` — CSRF-protected API gateway
   - Direct stub endpoint (e.g., `/audit.php`, `/notification.php`) — session-check only

## 2. Server Processing & Execution Paths
1. **Session Guard:** `session_guard.php` checks `$_SESSION['last_activity']` — if >15 min idle, session destroyed, user redirected. Session ID regenerated every 5 min (not per-request to avoid AJAX race conditions).
2. **CSRF Validation (API only):** `api/index.php` checks `X-CSRF-Token` header against `$_SESSION['csrf_token']` (skipped for GET, login, register, reset-password). Direct stub endpoints check session authentication directly instead.
3. **Routing:** Endpoint validated and routed to:
   - `ad_operation_router.php` — AD actions (create, modify, unlock, enable, disable, etc.)
   - Dedicated controller — for specialized actions (Security Events, health check, log data, etc.)
4. **Permissions:** `rbac_service.php` verifies action permission in `roles.json`.
5. **Backend Resolution:** For AD actions, router checks `{secure_base}/ldap/config.json`:
   - **`powershell`** — `powershell_runner.php` builds command (password redacted), calls `exec()`
   - **`ldap`** — calls registered PHP LDAP handler via `ext-ldap`
   - **`auto`** — tries LDAP first, falls back to PowerShell
6. **Credential Sources (PowerShell):** Priority order:
   - Direct `AdminUsername`/`AdminPassword` params
   - `SecureConfigPath` → Clixml vault (`accesspilot_deployment_identity.xml` — **does not exist**)
   - `AD_ADMIN_USERNAME`/`AD_ADMIN_PASSWORD` env vars
   - `config/ldap/admin_config.json` fallback
7. **Execution:**
   - **LDAP Path:** Handler function → native `ext-ldap` calls → `ldap_json_script_result()` normalizes output
   - **PowerShell Path:** `powershell_build_command()` → `exec()` → JSON stdout → parsed
8. **Logging:**
   - Action result logged via `log_activity()` to `General/` CSV
   - Both backends (PowerShell and PHP LDAP) write structured logs to the same nested paths via `ldap_write_script_log()` / `Write-Log` (see `LOG_ORGANIZATION.md` for path map)
   - Message field is cleaned: no `SUCCESS:`/`ERROR:` prefix (Status column suffices), no `Processed:` summary line
   - Passwords redacted from logged command strings via regex in `powershell_runner.php`
9. **Error Handling:**
   - Passes through `diagnostics_guide.php` for human-readable messages
   - PowerShell output: `diagnostics_humanize_message()` + `diagnostics_suggest_for_message()`

## 3. User Creation Flow (Detailed)
```
create_user.php → ldap_user_writer_create()
  1. Validate input (username, name, password, OU, groups)
  2. ldap_escape_dn_component() on all OU fields
  3. Check if group exists via ldap_group_repository_find_by_name()
  4. If group not found → auto-create via ldap_group_repository_create() [NEW]
  5. Create user via ldap_add()
  6. Add user to all groups via ldap_add() to group member attribute
  7. Return success with user DN
```
If PowerShell backend: `manual-create-ad-user.ps1` or `create-user-core.ps1` handles creation.

## 4. Log Display Flow
```
Dashboard request → log_data.php (via api/index.php)
  1. Reads time period (all/today/week/month/custom)
  2. Calls dashboard_log_reader.php
  3. dashboard_category_path_map() resolves category → disk path
  4. Recursive DirectoryIterator scans for .log/.csv files
  5. Filters by date range from filenames/content
  6. Returns JSON → dash_pro_scripts.js renders charts/tables
```

## 5. Server Feedback (Server to Client)
1. **Response Packaging:** JSON `{success, message, data}`.
2. **UI Update:** `action_processor.js` or specific module handles response.
3. **Feedback Rendering:**
   - Action Taken card shows success/error message (includes diagnostic hints on failure)
   - Result messages persist 45 seconds (`utils.js` + `MutationObserver`)
4. **Hierarchy of Focus:**
   - P1: Active Feedback (Result Card / Events Table)
   - P2: Active Task (Operation Form)
   - P3: Reference Data (Server/Employee Info Cards)

## 6. Security Events Flow
1. **Trigger:** User clicks "Events" button (permission: `action_security_events`).
2. **Card Rendering:** `report_actions.js` builds filter card (username, date range, event IDs, credentials).
3. **API:** POST to `api/index.php?endpoint=get_user_security_events` (with CSRF header).
4. **Server:** `get_user_security_events.php` validates input, calls `powershell_run_json_script()` with direct credentials (no `include_secure_config`).
5. **PowerShell:** `get-user-security-events.ps1` queries Security + TerminalServices + ForwardedEvents via XPath.
6. **Client:** Renders color-coded results table.

## 7. Health Check Flow
1. Admin clicks health check → `get_ad_health_check_deep.php`
2. Server runs `get-ad-health.ps1` via PowerShell
3. Credentials loaded from Clixml file created by `create-credential-config.ps1`
4. Results include DC connectivity, LDAP bind test, DNS resolution, replication status

## 8. Vendor Console Flow
The Vendor Console is an SPA page registered in `page_registry.php` as `vendor_console` (serves `resources/views/pages/license/vendor_view.php`). Its JS module is `vendor_actions.js`.

### Page Load
```
User clicks "Vendor Console" nav item
  → SPA fetch: index.php?page=vendor_console
  → page_registry.php: case 'vendor_console'
    → includes vendor_view.php + injects vendor_actions.js
  → spa_response.php returns HTML + JS
  → spaContentUpdated event → initVendorConsole()
```

### Credential Gate Flow
```
vendor_actions.js init
  → Check $_SESSION['vendor_console_verified']
  → If not set, show #vendorCredentialModal (z-index 1115)
  → User enters User ID + Password
  → POST to api/index.php?endpoint=vendor_license_api&action=vendor_verify_creds
  → vendor_license_api.php:
    1. Read php://input → json_decode
    2. repo_read_users() → password_verify()
    3. On success: $_SESSION['vendor_console_verified'] = true
    4. Returns JSON → modal closes → page reveals
  → On failure: error message in modal
```

### License CRUD Flow
```
Generate & Save:
  Fill form (Client, Domain, Deploy ID, Expiry, Max Domains, Type)
  → POST vendor_save → vault write to {secure}/vendor_licenses/licenses.json
  → log_activity() + notification broadcast
  → Table refreshes via vendor_list GET

Edit/Verify/Delete:
  Action buttons in table row (text-align: right on <td>)
  → Modals use SPA relocation (§14 rules: append to body, z-index 1115)
  → POST to vendor_update / vendor_delete / vendor_verify

Download JSON/PEM:
  GET vendor_download?id=xxx&format=json|pem → file download
```

### Signing Key Flow
```
Key Status (GET vendor_key_status):
  Checks {secure}/scripts/license_admin_templates/vault/private_key.pem
  Returns: has_private_key (bool), key_info (bits, type) or null

Upload Key (POST vendor_save_key):
  Reads PEM from php://input
  Validates RSA key via openssl_pkey_get_private()
  Saves to vault path
  Encrypts with license public key for backup

Remove Key (POST vendor_delete_key):
  Deletes private key file from vault
  Also removes encrypted backup
```

### Client Release Pack Flow
```
User enters Organization Name in text field
  → Clicks "Build & Download"
  → JS: POST vendor_build_release { org_name: "Acme Corp" }
  → PHP:
    1. set_time_limit(300), check class_exists('ZipArchive')
    2. Create zip in sys_get_temp_dir()
    3. RecursiveDirectoryIterator over source root
    4. Skip excluded: dist_release/, .git/, node_modules/, etc.
    5. Skip sensitive: scripts/license_admin_templates/, analysis/codebase_upgrade_plan/
    6. ZipArchive::addFile() for each matched file
    7. Close zip, store path in $_SESSION['vendor_download_zip']
    8. Return JSON { success, zip_name, files }
  → JS redirects to vendor_download_release (GET)
  → PHP: readfile() streams zip, Content-Disposition: attachment
  → Zip deleted via @unlink() after download, session key cleared
```

### Console Feedback Flow
```
Each API call sets vendorLog() in JS:
  vendorLog(message, type) → prepends timestamped entry to log panel
  Types: info (blue), ok (green), err (red), warn (amber)
  Log panel is a scrollable <pre> with max-height, auto-scrolls
  Refresh/Clear buttons available
```

## 9. Docker Deployment Context

All flows above are platform-agnostic. In the Docker deployment:

- **Nginx** serves as the web server (same role as IIS on Windows)
- **PHP 8.2-FPM** handles PHP execution (FastCGI via TCP 9000)
- **Host bind mounts** replace IIS virtual directories for `/data/secure` and `/data/logs`
- **No PowerShell backend** on Linux — AD operations use `ext-ldap` exclusively (set backend to `ldap` or `auto`)
- **13 PHP stubs in `public/`** are inert under Nginx (Apache-style routing via `try_files`); they exist for IIS compatibility
- **OPcache clear** required after PHP changes: `docker exec accesspilot_php php -r 'opcache_reset();'`
- **Container restart** required after file transfer (WinCP changes inodes)
- Refer to `docker/README.md` for full deployment details
