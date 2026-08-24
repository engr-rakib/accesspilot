# Multi-AD Management — Migration Analysis (Revised)

## 1. Current Architecture (Single AD)

| Component | File | Limitation |
|-----------|------|------------|
| Config Storage | `{secure_base}/ldap/config.json` | Flat single config — one host, one bind DN, one base DN |
| Bind Secret | `{secure_base}/ldap/bind_secret.json` | Single password |
| Config Repository | `app/Ldap/Config/ldap_config_repository.php` | `ldap_read_config()` returns one config |
| Connection Factory | `app/Ldap/Connection/ldap_connection_factory.php` | `ldap_connect_and_bind()` connects to one AD |
| System Config UI | `resources/views/pages/tools/system_config_view.php` | Single set of LDAP fields |
| App Config | `config/app.php` | Single `domain_name` + `base_dn` |
| Log Path | `{base_log_path}/scripts_logs/{Category}/...` | No domain subdirectory |
| Log Reader | `dashboard_log_reader.php` | Reads from one flat path |
| Operations | All handlers | Target one AD (no domain context) |

## 2. Requirements (User-Approved Design)

### 2.1 Active Domain Switching
- Assistant pane title ke niche active AD domain name show korbo
- AD switching feature rakhbo — ekhan theke multiple domain listed thakbe
- **Jei domain ta active korbo, all operation oi domain er upor apply hobe**
- No per-operation routing — simple global active domain switch

### 2.2 Logs Path Per-Domain
- New structure: `{base_log_path}/{domain_name}/scripts_logs/{Category}/...`
- Only PowerShell shell scripts need this (they write physical log files via `Write-Log`)
- Application user activity logs are NOT per-domain — they stay in `General/` CSV

### 2.3 Dashboard Log Reader
- `dashboard_log_reader.php` update korte hobe
- Domain-specific path theke read korar jonno modify korte hobe

## 3. Data Model

### 3.1 Config Storage

```
{secure_base}/ldap/
  ├── domains.json        # Array of domain configs
  │   [
  │     {
  │       "key": "wgbd",
  │       "label": "WGBD Corp",
  │       "host": "dc01.wgbd.com",
  │       "port": 389,
  │       "use_tls": false,
  │       "base_dn": "DC=wgbd,DC=COM",
  │       "user_search_base": "",
  │       "bind_dn": "CN=svc,OU=...,DC=wgbd,DC=COM",
  │       "backend": "ldap",
  │       "enabled": true
  │     },
  │     { "key": "whildc", ... }
  │   ]
  ├── active_domain.txt   # "wgbd" — currently active domain key
  └── secrets/
      ├── wgbd.json       # {"password": "..."}
      └── whildc.json
```

### 3.2 Active Domain State
- Stored in `{secure_base}/ldap/active_domain.txt` (simple text file)
- PHP reads this once per request and caches in memory
- Switching domain writes to this file
- All subsequent operations use this domain

### 3.3 Log Path Structure

| Current | New (Per-Domain) |
|---------|------------------|
| `{base_log_path}/scripts_logs/User_Management/NewUser/...` | `{base_log_path}/wgbd/scripts_logs/User_Management/NewUser/...` |
| `{base_log_path}/scripts_logs/Directory_Services/Ou_Group_Mgt/...` | `{base_log_path}/whildc/scripts_logs/Directory_Services/Ou_Group_Mgt/...` |
| `{base_log_path}/General/activity.csv` | `{base_log_path}/General/activity.csv` **(unchanged)** |

## 4. Implementation Phases

### Phase 1: Config Storage — Domain Registry
- [ ] `app/Ldap/Config/ldap_config_repository.php`:
  - Add `ldap_get_domains()` → returns array of all domains
  - Add `ldap_get_domain(key)` → returns single domain or null
  - Add `ldap_upsert_domain(array)` → add/update a domain
  - Add `ldap_delete_domain(key)` → remove a domain
  - Add `ldap_get_active_domain()` → reads `active_domain.txt`
  - Add `ldap_set_active_domain(key)` → writes `active_domain.txt`
  - Add `ldap_read_domain_secret(key)` → per-domain password
  - Add `ldap_write_domain_secret(key, password)` → per-domain password
- [ ] Auto-migration: Read existing `config.json` + `bind_secret.json` → seed first domain as "default"

### Phase 2: Connection — Active Domain
- [ ] `app/Ldap/Connection/ldap_connection_factory.php`:
  - Add `ldap_connect_active()` → reads active domain, connects to it
  - Existing `ldap_connect_and_bind()` stays as BC for callers that pass explicit config
  - New central dispatch: `ad_operation_router.php` calls `ldap_connect_active()` instead of `ldap_connect_and_bind()`
- [ ] No handler code changes needed — all handlers receive `$connection` from caller

### Phase 3: Log Path — PowerShell Scripts
- [ ] `ldap_helpers.php` → `ldap_script_log_base_path()`:
  - Add domain subdirectory: `{base_log_path}/{active_domain}/scripts_logs/`
  - Only PowerShell scripts use this path
  - PHP LDAP handlers also call `ldap_write_script_log()` — they use the same path
- [ ] `Write-Log` in PowerShell `.ps1` files:
  - Pass `-DomainName` parameter to scripts
  - Scripts construct path: `$BaseLogPath\$DomainName\scripts_logs\$Category\$action\`
  - But scripts run under IIS and don't know the active domain from PHP...

Wait, here's the issue: PowerShell scripts are called from PHP via `exec()`. The PHP controller knows the active domain. How does the domain get to the PowerShell script?

**Options:**
1. Pass `-DomainName` as a CLI argument to PowerShell scripts (security concern — visible in process list)
2. Write a temp file `{secure_base}/ldap/active_domain.txt` (already exists for PHP) and have PowerShell read it
3. Store in `shared_config.json` that PowerShell already reads
4. Use environment variable

**Best Option: shared_config.json** — PowerShell already reads this file via `ldap_ad_helpers.ps1`. Add `active_domain` to it.

```
// config/shared_config.json (already exists for PowerShell)
{
  "default_password": "...",
  "domain_name": "wgbd.com",
  "active_domain": "wgbd",
  ...
}
```

Whenever active domain changes, PHP writes `shared_config.json` with the new `active_domain`. PowerShell scripts read it via `Get-Content` / `ConvertFrom-Json`.

- [ ] `sync_shared_config()` in `system_config.php` — add `active_domain` to shared JSON
- [ ] PowerShell scripts read `active_domain` from shared_config to build log path

### Phase 4: UI — Assistant Pane Domain Display & Switcher
- [ ] `resources/views/layouts/master.php` or Assistant partial:
  - Title er niche active AD domain name show kora
  - A small badge/pill: "wgbd.com" with a dropdown arrow
  - Click → dropdown listing all configured domains
  - Select → AJAX call to switch active domain
- [ ] API endpoint: `POST /api/index.php?endpoint=switch_domain` → body: `{"domain_key": "whildc"}`
  - Calls `ldap_set_active_domain(key)`
  - Returns success → UI updates badge + page content refreshes
- [ ] Controller: `app/Application/Http/Controllers/system_config.php` or new `domain_switch.php`
  - Reads active domain from `active_domain.txt`
  - Updates `shared_config.json` for PowerShell

### Phase 5: UI — System Config Domain Management
- [ ] `system_config_view.php`: Domain list section with add/edit/delete
- [ ] Each domain form: key, label, host, port, TLS, base DN, search base, bind DN, bind password, backend mode, enable/disable
- [ ] Per-domain "Test Connection" button
- [ ] "Set as Active" button per domain row
- [ ] Warning: Changing active domain affects all operations portal-wide

### Phase 6: Log Reader Update
- [ ] `dashboard_log_reader.php`:
  - `dashboard_category_path_map()` → prepend `{active_domain}/` to `scripts_logs/` path
  - Read active domain from `shared_config.json` or `active_domain.txt`
  - All existing log reading logic unchanged — just path prefix changes
- [ ] `dashboard_log_reader.php` already uses `get_external_log_base()` for base path — just add domain subdirectory

### Phase 7: Dashboard — Domain Context
- [ ] Show active domain in dashboard/filter UI
- [ ] Log viewer: Domain column (optional, all logs belong to active domain)
- [ ] Health check: Run against active domain

## 5. Key Code Changes (All Files)

| File | Change | Priority |
|------|--------|----------|
| `app/Ldap/Config/ldap_config_repository.php` | Add domain CRUD + active domain get/set + per-domain secrets | P0 |
| `app/Ldap/Connection/ldap_connection_factory.php` | Add `ldap_connect_active()` | P0 |
| `app/Ldap/Router/ad_operation_router.php` | Use `ldap_connect_active()` instead of `ldap_connect_and_bind()` | P0 |
| `app/Ldap/Support/ldap_helpers.php` | `ldap_script_log_base_path()` — prepend `{domain}/` | P0 |
| `config/shared_config.json` | Add `active_domain` field (written by PHP, read by PowerShell) | P0 |
| `app/Infrastructure/PowerShell/powershell_runner.php` | No change needed — scripts read shared_config.json directly | — |
| PowerShell `.ps1` scripts | `Write-Log` path uses `active_domain` from shared_config.json | P1 |
| `dashboard_log_reader.php` | Prepend active domain to log path | P1 |
| `resources/views/layouts/master.php` (Assistant) | Active domain badge + switcher dropdown | P1 |
| New controller: `domain_switch.php` | API endpoint to switch active domain | P1 |
| `resources/views/pages/tools/system_config_view.php` | Domain list CRUD section | P2 |
| `system_config.php` controller | Domain CRUD in `save_ldap` action | P2 |

## 6. Files NOT Needing Change

| File | Reason |
|------|--------|
| `app/Ldap/Operations/*.php` | All handlers receive `$connection` from caller — no handler logic changes needed |
| `app/Ldap/Services/ldap_config_service.php` | Delegates to repository — no domain logic needed here |
| `app/Ldap/Support/ldap_response_adapter.php` | Response format does not change |
| `config/ldap/ldap_operations.php` | Operation readiness flags are domain-agnostic |
| `config/app.php` `domain_name` / `base_dn` | Become read-only legacy — actual domain config comes from domains.json |
| Application user activity logs | Not per-domain — stay in `General/` |

## 7. Licensing Impact

- License binds to `deployment_id` — one deployment = one license
- Multiple ADs under same deployment = no additional license needed
- The domain dropdown in request portal (wgbd, whildc) already anticipates this
- Assistant pane domain switcher does not affect license check

## 8. Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Switching domain mid-operation | Operation targets wrong AD | Operations read active domain at dispatch time — atomic |
| PowerShell writes logs before domain file updated | Logs go to wrong path | Write `active_domain` to `shared_config.json` synchronously |
| Log reader sees logs from wrong domain | Dashboard shows wrong data | Reader always reads active domain path — matches current context |
| Domain deleted while active | Operations fail | Prevent deletion of active domain; force switch first |
| No domains configured | No AD operations possible | Show clear message in Assistant; require at least one domain in system config |
