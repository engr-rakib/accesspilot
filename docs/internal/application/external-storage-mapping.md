# External Storage Architecture

> **Current runtime storage mapping — Docker + IIS.**  
> Covers secure vault (`/data/secure`), log storage (`/data/logs`), config override system, and path resolution.

---

## 1. System Overview

AccessPilot separates **codebase** (PHP application files) from **data** (config, users, logs, secrets). Data lives outside the codebase and survives codebase upgrades, container restarts, and `docker compose down -v`.

```
┌─────────────────────────────────────────────────────────────────────┐
│                        HOST FILESYSTEM                              │
│                                                                     │
│  /opt/accesspilot/          ← Codebase (upgradeable, replaceable)   │
│  ├── app/                                                           │
│  ├── bootstrap/                                                     │
│  ├── config/                                                        │
│  ├── public/                                                        │
│  └── resources/                                                     │
│                                                                     │
│  /data/secure/              ← Vault (persistent, rw)                │
│  ├── config/                ← Runtime overrides                     │
│  ├── ldap/                  ← Domain config + bind secrets          │
│  ├── appusers/              ← Users, roles, auth state              │
│  ├── app_notifications/     ← Notification settings                 │
│  ├── monitoring/            ← Monitoring server configs             │
│  ├── requests/              ← Registration + password reset reqs    │
│  ├── passwd/                ← Password manager vault                │
│  ├── profile_img/           ← Avatar uploads                        │
│  ├── deployment_active_license/ ← License PEM files                 │
│  ├── vendor_issued_licenses/    ← Vendor license store              │
│  └── vendor_signing_keys/       ← Signing public keys               │
│                                                                     │
│  /data/logs/                ← Logs (persistent, rw)                 │
│  ├── app_audit_logs/        ← PHP audit CSV (log_activity)         │
│  ├── {domain}/              ← Per-domain script logs                │
│  │   └── scripts_logs/                                              │
│  │       ├── User_Management/                                       │
│  │       │   ├── NewUser/       + transcript logs                   │
│  │       │   ├── PassReset/                                        │
│  │       │   ├── unlock/                                            │
│  │       │   ├── UserDisable/                                       │
│  │       │   ├── UserEnable/                                        │
│  │       │   └── UserModify/                                        │
│  │       └── Directory_Services/                                    │
│  │           ├── Ou_Group_Mgt/                                      │
│  │           └── GroupMgmt/                                         │
│  └── {config-key}/          ← Fallback if AD-named dir missing      │
│                                                                     │
│  /opt/accesspilot/App_Data/ ← Bootstrap state, sessions, fail-safe  │
│  ├── setup_complete.lock                                            │
│  ├── internal_admin.json                                            │
│  └── (session files)                                                │
└─────────────────────────────────────────────────────────────────────┘
```

### Docker Container Architecture

```
                          HOST:8080
                             │
                             ▼
             ┌───────────────────────────────┐
             │   nginx:1.25-alpine            │
             │   accesspilot_web              │
             │   Mounts: public/ (ro),        │
             │   resources/ (ro)              │
             │   Denies: /app/, /bootstrap/,  │
             │   /config/, /scripts/          │
             └──────────┬────────────────────┘
                        │ FastCGI :9000
             ┌──────────▼────────────────────┐
             │   PHP 8.2-FPM                  │
             │   accesspilot_php              │
             │   Mounts:                      │
             │   app/ (ro), bootstrap/ (ro)   │
             │   public/ (ro), resources/ (ro)│
             │   config/ (rw), App_Data/ (rw) │
             │   /data/secure/ (rw)           │
             │   /data/logs/ (rw)             │
             └────────────────────────────────┘
                        │
                        ▼
             ┌────────────────────────────────┐
             │   Active Directory             │
             │   (LDAP 389/636)               │
             └────────────────────────────────┘
```

### IIS Coexistence

The same codebase runs on Windows IIS 10 + PHP 8.5.4 NTS. Paths differ:

| Aspect | Linux Docker | Windows IIS |
|--------|-------------|-------------|
| Web server | Nginx 1.25-alpine | IIS 10 |
| PHP | 8.2-FPM (ext-ldap) | 8.5.4 NTS (php_ldap.dll) |
| Secure vault | `/data/secure` | `C:\inetpub\Desk_secure_files\` |
| Log path | `/data/logs` | `C:\access_pilot_logs\` |
| App_Data | `/opt/accesspilot/App_Data` | `C:\inetpub\wwwroot\UM-portal\App_Data` |
| Config override | Env vars + vault config | vault config (env var optional) |
| AD execution | In-process LDAP via ext-ldap | PowerShell scripts (fallback) |

---

## 2. Secure Vault (`/data/secure/`)

Set via env var `ACCESSPILOT_SECURE_BASE_PATH` (Docker) or `config/storage.php`.
Default: `C:/inetpub/Desk_secure_files` (IIS fallback).

### 2.1 Vault Directory Reference

| Path | Contents | Created By | Read By |
|------|----------|------------|---------|
| `config/app_overrides.php` | Domain, org, password overrides | `save_org`, `save_passwords` | `app_config()` merge |
| `config/shared_config.json` | Mirror for PowerShell | `sync_shared_config()` | PowerShell scripts |
| `config/app_storage.php` | Storage path overrides | `save_storage` | `app_config()` merge |
| `config/app_integrations.php` | API URL overrides | `save_integrations` | `app_config()` merge |
| `api/integrations.php` | Authoritative API config | Admin API settings | `app_config()` merge (overrides codebase) |
| `ldap/config.json` | Domain connection settings | Domain CRUD | `ldap_connect()`, dashboard |
| `ldap/domains.json` | Multi-domain list + license | License + domain CRUD | Domain switching |
| `ldap/domains_cache.json` | Cached domain data | Runtime | Dashboard performance |
| `ldap/secrets/{domain}.json` | Per-domain encrypted bind pwds | Domain CRUD | `ldap_bind()` |
| `ldap/last_test.json` | Last LDAP test result | Test connection | Dashboard status |
| `appusers/users.json` | Admin user accounts | User management | `authenticate()` |
| `appusers/roles.json` | Role definitions | Role management | Authorization |
| `appusers/authenticated_users.json` | Active sessions | Login | Session validation |
| `appusers/forced_logouts.json` | Force-logout list | Admin action | Mid-session check |
| `requests/registration_requests.json` | Pending registrations | Registration | Approval workflow |
| `requests/password_reset_requests.json` | Pwd reset requests | Self-service | Admin approval |
| `passwd/` | Password manager encrypted data | Password manager | Password manager |
| `profile_img/` | Uploaded avatars | Profile edit | Profile display |
| `monitoring/` | AD monitoring server configs | Monitoring setup | Health checks |
| `app_notifications/` | Notification preferences | Settings | Notification engine |
| `deployment_active_license/` | Active license PEM files | License activation | License validation |
| `vendor_issued_licenses/` | Vendor-issued license files | License generation | Client release |
| `vendor_signing_keys/` | RSA public keys for verify | Onboarding | License verification |

### 2.2 Vault Config Override Flow

```
┌──────────────┐    ┌──────────────────┐    ┌──────────────────────┐
│ config/      │───▶│ app_config.php   │───▶│ Merged config array  │
│ app.php      │    │ loads all files  │    │                      │
│ storage.php  │    │ recursively      │    │ codebase defaults    │
│ integrations │    │                  │    │ +                    │
│ ...          │    │                  │    │ vault overrides      │
└──────────────┘    └────────┬─────────┘    │ = FINAL config       │
                             │               └──────────────────────┘
                             ▼
              ┌─────────────────────────┐
              │ vault_ensure_all_dirs() │
              │ vault_migrate_existing()│
              │                         │
              │ Merge in order:         │
              │ 1. app_overrides.php    │
              │ 2. app_storage.php      │
              │ 3. app_integrations.php │
              │ 4. api/integrations.php │
              └─────────────────────────┘
                        ▲
                        │ reads from
              ┌─────────┴────────────┐
              │ /data/secure/config/ │
              │ (vault)              │
              └──────────────────────┘
```

**Key principle**: Codebase config files (`config/app.php`, etc.) provide defaults. If vault config exists, it overrides. This means codebase can be replaced entirely — runtime settings survive in vault.

### 2.3 Path Resolution Functions

| Function | Returns | Fallback | Defined In |
|----------|---------|----------|------------|
| `get_secure_base_path()` | Vault root dir | `C:/inetpub/Desk_secure_files` | `helpers.php:397` |
| `vault_config_path()` | `{vault}/config/` | — | `helpers.php:403` |
| `vault_config_file($name)` | `{vault}/config/{name}` | — | `helpers.php:409` |
| `vault_shared_config_path()` | `{vault}/config/shared_config.json` | — | `helpers.php:440` |
| `vault_api_path()` | `{vault}/api/` | — | `helpers.php:446` |
| `get_external_log_base()` | Log root dir | `C:/access_pilot_logs` | `helpers.php:265` |
| `resolved_log_path()` | `{log}/app_audit_logs/audit-{date}.csv` | — | `helpers.php:325` |

The env var `ACCESSPILOT_SECURE_BASE_PATH` is read in `config/storage.php:9` and takes highest priority (Docker sets it).

---

## 3. Log Storage (`/data/logs/`)

Set via env var `ACCESSPILOT_LOG_BASE_PATH` (Docker) or `config/storage.php`.
Default: `C:/access_pilot_logs` (IIS fallback).

### 3.1 Log Directory Tree

```
/data/logs/
├── app_audit_logs/               ← PHP audit trail (log_activity)
│   └── audit-{YYYY-MM-DD}.csv    ← Daily CSV with header row
│       Columns: Timestamp,Username,Action,Status,Details
│
├── {domain}/                     ← Per-domain script logs
│   └── scripts_logs/
│       ├── User_Management/
│       │   ├── NewUser/
│       │   │   ├── audit-{date}.log           ← ldap_write_script_log
│       │   │   └── New_user_transcript_logs/  ← ldap_write_transcript_log
│       │   ├── ManualCreate/
│       │   ├── PassReset/
│       │   ├── unlock/
│       │   ├── UserDisable/
│       │   ├── UserEnable/
│       │   ├── UserModify/
│       │   └── UserInfo/
│       ├── Directory_Services/
│       │   ├── Ou_Group_Mgt/
│       │   └── GroupMgmt/
│       ├── Integration/
│       │   ├── EmpStsChk/
│       │   ├── FindLogonID/
│       │   └── user_export/
│       └── HealthCheck/
│
└── {config-key}/                 ← Fallback if domain dir missing
    └── scripts_logs/
```

### 3.2 Log Writers

| Writer | Path | Format | Line |
|--------|------|--------|------|
| `log_activity()` | `{log}/app_audit_logs/audit-{date}.csv` | CSV with header | `audit_service.php:19` |
| `ldap_write_script_log()` | `{log}/{domain}/scripts_logs/{category}/audit-{date}.log` | `[timestamp] Action: {op} \| Status: {S/F} \| Message: ... \| ExecutedBy: {user}` | `ldap_helpers.php:601` |
| `ldap_write_transcript_log()` | `{log}/{domain}/scripts_logs/NewUser/New_user_transcript_logs/audit-{date}.log` | `[timestamp] {message} [User: {id}]` | `ldap_helpers.php:643` |

### 3.3 Domain Resolution for Log Paths

```
ldap_active_domain_ad_name()      ← "wgbd.com" (from base_dn → DC parts)
        │ exists?
        ├── YES → use as directory name
        └── NO  → ldap_active_domain_key() ← "default" (config key)
                    │
                    ▼
              /data/logs/wgbd.com/scripts_logs/...   ← primary
              /data/logs/default/scripts_logs/...     ← fallback
```

Extraction: `preg_match_all('/DC\s*=\s*([^,]+)/i', $baseDn, $parts)` → `strtolower(implode('.', $parts[1]))`.

---

## 4. Docker Volume Mapping Detail

### 4.1 PHP Container (`accesspilot_php`)

| Host Path | Container Path | Mode | Purpose |
|-----------|---------------|------|---------|
| `/data/secure` | `/data/secure` | rw | Secure vault (config, users, LDAP, license) |
| `/data/logs` | `/data/logs` | rw | Audit + script logs |
| `/opt/accesspilot/app` | `/var/www/html/app` | ro | Application logic |
| `/opt/accesspilot/bootstrap` | `/var/www/html/bootstrap` | ro | Bootstrap + routing |
| `/opt/accesspilot/config` | `/var/www/html/config` | rw | Codebase config (app.php writable for org reg) |
| `/opt/accesspilot/public` | `/var/www/html/public` | ro | Web root, entry points |
| `/opt/accesspilot/resources` | `/var/www/html/resources` | ro | Views, templates |
| `/opt/accesspilot/App_Data` | `/var/www/html/App_Data` | rw | Lock file, sessions |

### 4.2 Nginx Container (`accesspilot_web`)

| Host Path | Container Path | Mode | Purpose |
|-----------|---------------|------|---------|
| `/opt/accesspilot/public` | `/var/www/html/public` | ro | Static files |
| `/opt/accesspilot/resources` | `/var/www/html/resources` | ro | CSS/JS assets |
| `./nginx/default.conf` | `/etc/nginx/conf.d/default.conf` | ro | Nginx config |

### 4.3 Security Properties

- **Read-only codebase**: `app/`, `bootstrap/`, `public/`, `resources/` are `ro` — a compromised PHP process cannot write webshells
- **Nginx denies**: `/app/`, `/bootstrap/`, `/config/`, `/scripts/`, `/App_Data/` return 403
- **Vault isolation**: Nginx has no access to `/data/secure` or `/data/logs`
- **Ownership fix**: Container runs `chown www-data:www-data /data/secure /data/logs` at start
- **Survives `down -v`**: Bind mounts are host-paths — `docker compose down -v` does NOT delete them (unlike named volumes)

---

## 5. IIS Storage Architecture

On Windows IIS, there are no containers. PHP runs as a FastCGI module inside IIS:

```
┌───────────────────────────────────────────────────────┐
│                   IIS 10                               │
│   C:\inetpub\wwwroot\UM-portal\                        │
│   ├── app\                        ← PHP logic          │
│   ├── bootstrap\                                        │
│   ├── config\                     ← Codebase config     │
│   ├── public\                     ← Web root            │
│   ├── resources\                                        │
│   └── App_Data\                   ← Lock + sessions     │
│                                                         │
│   C:\inetpub\Desk_secure_files\   ← IIS secure vault    │
│   ├── config\app_overrides.php                          │
│   ├── ldap\                                             │
│   ├── appusers\                                         │
│   └── ...                                               │
│                                                         │
│   C:\access_pilot_logs\           ← IIS logs            │
│   ├── app_audit_logs\                                   │
│   └── {domain}\scripts_logs\                            │
└───────────────────────────────────────────────────────┘
```

### Key Differences from Docker

| Aspect | Docker | IIS |
|--------|--------|-----|
| Codebase protection | `ro` bind mounts | No filesystem protection (IIS app pool identity has RW) |
| Path config | Env vars (`ACCESSPILOT_*`) | `config/storage.php` defaults |
| Config override | Env → codebase → vault | Codebase → vault (no env) |
| AD execution | In-process LDAP (ext-ldap) | PowerShell scripts (fallback) |
| Log writer | PHP `file_put_contents()` | Same PHP functions |
| Secrets | Encrypted JSON per domain | Same encrypted JSON format |
| State files | Bind-mounted per domain | Same JSON files |

---

## 6. Data Flow: Write Paths

### 6.1 Admin Save Config

```
User clicks "Save" (org, storage, integrations, passwords)
        │
        ▼
system_config.php handler
        │
        ├───▶ Write to codebase config file (backward compatible)
        │         config/app.php, config/storage.php, etc.
        │
        ├───▶ Write vault config override
        │         /data/secure/config/app_overrides.php
        │         /data/secure/config/app_storage.php
        │         /data/secure/config/app_integrations.php
        │
        └───▶ sync_shared_config()
                  /data/secure/config/shared_config.json
```

### 6.2 Log Activity (PHP Audit)

```
log_activity($username, $action, $status, $details)
        │
        ▼
resolved_log_path('audit.csv')
        │
        ├───▶ get_external_log_base()  →  /data/logs  (or C:\access_pilot_logs)
        ├───▶ appends /app_audit_logs/
        ├───▶ date-based rename: audit-{date}.csv
        └───▶ fputcsv() with header row on first write
```

### 6.3 LDAP Script Log

```
ldap_write_script_log($operation, $targetUser, $success, $message, ...)
        │
        ├───▶ Resolve category from operation name
        ├───▶ Resolve domain: ldap_active_domain_ad_name()
        ├───▶ Build path: {log}/{domain}/scripts_logs/{category}/audit-{date}.log
        └───▶ Append formatted entry with LOCK_EX
```

---

## 7. Config Override Resolution Order

```
Highest priority ──────────────────────────────────────────┐
                                                            ▼
 1. Environment variables   ACCESSPILOT_SECURE_BASE_PATH   Docker only
                            ACCESSPILOT_LOG_BASE_PATH
 2. Vault config            /data/secure/config/            Both platforms
    files                   app_overrides.php
                            app_storage.php
                            app_integrations.php
 3. Vault API config        /data/secure/api/               Both platforms
                            integrations.php
 4. Codebase config         config/storage.php              Fallback only
    files                   config/app.php
 ───────────────────────────────────────────────────────────
Lowest priority
```

On IIS: step 1 (env vars) typically absent, so vault config (step 2) is the highest override.

---

## 8. Vault Initialization Sequence

On every page load, `app_config()` (in `helpers.php:48`) runs:

```
app_config()
  ├── Load codebase config/*.php via config/app_config.php
  ├── vault_ensure_all_dirs()       ← Create missing vault subdirs
  ├── vault_migrate_existing_config() ← Copy codebase config → vault
  │      (one-time, if vault app_overrides.php doesn't exist yet)
  ├── Merge vault overrides:
  │     app_overrides.php    (domain, org, passwords)
  │     app_storage.php      (path overrides)
  │     app_integrations.php (API URL overrides)
  └── Merge vault API config:
        api/integrations.php (authoritative API config)
```

---

## 9. Survival Matrix

| Data | Docker Path | Mount Type | Survives `down`? | Survives `down -v`? |
|------|-------------|------------|-------------------|---------------------|
| Config overrides | `/data/secure/config/` | Host bind | ✅ | ✅ |
| API config | `/data/secure/api/` | Host bind | ✅ | ✅ |
| Users, roles | `/data/secure/appusers/` | Host bind | ✅ | ✅ |
| LDAP config | `/data/secure/ldap/` | Host bind | ✅ | ✅ |
| License PEM | `/data/secure/deployment_active_license/` | Host bind | ✅ | ✅ |
| Profile images | `/data/secure/profile_img/` | Host bind | ✅ | ✅ |
| Audit logs | `/data/logs/` | Host bind | ✅ | ✅ |
| Script logs | `/data/logs/{domain}/scripts_logs/` | Host bind | ✅ | ✅ |
| Codebase config | `/var/www/html/config/` | Host bind (rw) | ✅ | ✅ |
| App_Data | `/var/www/html/App_Data/` | Host bind (rw) | ✅ | ✅ |
| PHP code | `/var/www/html/app/` | Host bind (ro) | ✅ | ✅ |
| Views/templates | `/var/www/html/resources/` | Host bind (ro) | ✅ | ✅ |

**Key**: Production uses **host bind mounts** (not named volumes) for `/data/secure` and `/data/logs`. Unlike named volumes, bind mounts **survive `docker compose down -v`**. Data is at a well-known host path and can be backed up independently.

---

## 10. Fresh Deploy Flow

### Docker

```bash
cd /opt/accesspilot/docker
sudo docker compose down -v
sudo docker compose build --no-cache php
sudo docker compose up -d
```

1. `down -v` → removes anonymous volumes only (bind mounts untouched)
2. `build` → builds image with latest codebase
3. `up -d` → starts containers
4. First HTTP request → `app_config()` → `vault_ensure_all_dirs()` creates all directories
5. `vault_migrate_existing_config()` copies codebase `config/app.php` to vault (if not empty)
6. Admin logs in, registers org → `save_org` writes to vault
7. License upload → PEM in `{vault}/deployment_active_license/`
8. Subsequent codebase upgrades → vault preserves all data

### IIS

1. Deploy codebase via WinSCP or git pull
2. First HTTP request → `app_config()` → creates `C:/inetpub/Desk_secure_files/config/`, `C:/inetpub/Desk_secure_files/api/`, etc.
3. `vault_migrate_existing_config()` copies existing `config/app.php` to vault
4. Admin saves org/integrations → vault updated
5. Codebase upgrade → vault intact

---

## 11. Auto-Creation at Bootstrap

On every PHP request, `app_config()` calls `vault_ensure_all_dirs()`:

```php
$base = rtrim(str_replace('\\', '/', get_secure_base_path()), '/');
$dirs = [
    'config', 'api',
    'appusers', 'ldap', 'ldap/secrets',
    'requests', 'passwd', 'profile_img',
    'monitoring', 'app_notifications',
    'deployment_active_license', 'vendor_issued_licenses', 'vendor_signing_keys',
];
```

Creates each under `{secure_base_path}/` if it doesn't exist. Idempotent — safe on every request.

**IIS**: Creates `C:/inetpub/Desk_secure_files/config/`, etc.
**Docker**: Creates `/data/secure/config/`, etc.

Then `vault_migrate_existing_config()` runs once:
- Only if `{vault}/config/app_overrides.php` does NOT exist
- Reads codebase `config/app.php`, copies non-empty values (`domain_name`, `org_name`, `base_dn`, `deployment_id`, `default_password`, `active_domain`, `pwd_reset_use_random`) to vault
- On subsequent requests, vault has the values → migration skipped

---

## 12. Files Deleted from Codebase (Vault-Only)

| File | Status | Why |
|------|--------|-----|
| `config/integrations.php` | ✅ Deleted | Vault-only via `{vault}/api/integrations.php` |
| `config/shared_config.json` | ✅ Deleted | Vault-only via `{vault}/config/shared_config.json` |

Both are now written and read exclusively from vault. Codebase restore = no config loss.

---

## 13. Nginx `/resources/` Root Note

In `docker/nginx/default.conf`, the `/resources/` location has:

```nginx
location /resources/ {
    root /var/www/html/public;    # NOT /var/www/html
    expires 7d;
}
```

**Critical**: `root` is set to `/var/www/html/public`, so `/resources/frontend/...` resolves to `public/resources/frontend/...`. This matches the Docker PHP container's read-only bind mount of `../public:/var/www/html/public`.

---

## 14. Environment Variables

| Variable | Default (Codebase) | Docker Value | Set In |
|----------|-------------------|--------------|--------|
| `ACCESSPILOT_SECURE_BASE_PATH` | `C:/inetpub/Desk_secure_files` | `/data/secure` | `docker-compose.yml` |
| `ACCESSPILOT_LOG_BASE_PATH` | `C:/access_pilot_logs` | `/data/logs` | `docker-compose.yml` |
| `AD_EXECUTION_MODE` | (not set) | `remote` | `docker-compose.yml` (Docker only) |

On IIS, if these env vars are not set, `config/storage.php` falls back to default paths.

---

## 15. Backup & Recovery

### Docker

`docker/deploy/backup.sh` creates timestamped archives:

| Backup Type | Contents | Retention |
|-------------|----------|-----------|
| Full | Codebase + config + App_Data + /data/secure + /data/logs | 5 archives |
| Data-only | /data/secure + /data/logs + App_Data | 7 daily |

Restore via `docker/deploy/rollback.sh [timestamp]` — stops containers, extracts backup, restarts, verifies HTTP 200.

### IIS

Backup via manual PowerShell or robocopy:
```powershell
# Backup vault + logs
Copy-Item C:\inetpub\Desk_secure_files D:\backups\secure_$(Get-Date -Format yyyyMMdd_HHmmss) -Recurse
Copy-Item C:\access_pilot_logs D:\backups\logs_$(Get-Date -Format yyyyMMdd_HHmmss) -Recurse
```

---

## 16. Migration from IIS to Docker

When migrating from IIS to Docker:

1. Copy secure data: `C:\inetpub\Desk_secure_files\*` → `/data/secure/`
2. Copy audit logs: `C:\access_pilot_logs\*` → `/data/logs/`
3. Copy codebase via WinCP: `C:\inetpub\wwwroot\UM-portal\*` → `/opt/accesspilot/`
4. Run `post_upload_cleanup.sh` — removes Windows-specific runtime files
5. Run `docker compose build && docker compose up -d`
6. OPcache reset: `docker exec accesspilot_php php -r 'opcache_reset();'`

The vault config at `/data/secure/config/app_overrides.php` already preserves runtime settings — no reconfiguration needed after migration.

---

## Appendix D: Key Files Reference

| File | Role |
|------|------|
| `config/storage.php` | Base path definitions, env var fallback |
| `config/app_config.php` | Config aggregator, loads all files |
| `app/Application/Support/helpers.php` | All path resolution, vault read/write, log path |
| `app/Domain/Audit/audit_service.php` | `log_activity()` — PHP audit CSV writer |
| `app/Ldap/Support/ldap_helpers.php` | `ldap_write_script_log()`, `ldap_write_transcript_log()` |
| `app/Ldap/Router/ad_operation_router.php` | PowerShell vs LDAP execution routing |
| `docker/docker-compose.yml` | Volume definitions, env vars |
| `docker/Dockerfile` | PHP build, extension installation |
| `docker/nginx/default.conf` | Nginx deny rules |

## Appendix E: Path Resolution Code

```php
// helpers.php:397
function get_secure_base_path(): string {
    return (string) config_get('storage.secure_base_path', 'C:/inetpub/Desk_secure_files');
}

// helpers.php:325
function resolved_log_path(string $filename = '', ?string $date = null): string {
    $baseLogPath = get_external_log_base();
    $auditDir = rtrim($baseLogPath, '/\\') . DIRECTORY_SEPARATOR . 'app_audit_logs';
    // ... date-based rename for audit.csv → audit-{date}.csv
}

// helpers.php:265
function get_external_log_base(): string {
    // 1. Priority: Secure XML metadata
    // 2. Fallback: Centralized mapping config
    return (string) config_get('storage.log_base_path', 'C:/access_pilot_logs');
}
```

## Appendix F: Log Entry Formats

### PHP Audit CSV (`app_audit_logs/audit-{date}.csv`)
```
Timestamp,Username,Action,Status,Details
2026-06-22 14:30:00,admin,login,success,IP: 192.168.1.100
2026-06-22 14:31:15,admin,create_user,success,IP: 192.168.1.100, Details: Created user jdoe
```

### Script Log (`scripts_logs/User_Management/PassReset/audit-{date}.log`)
```
[2026-06-22 02:30:00 PM] Action: RESET | TargetUser: jdoe | Status: SUCCESS | Message: Password reset for jdoe completed | ExecutedBy: admin
```

### Transcript Log (`NewUser/New_user_transcript_logs/audit-{date}.log`)
```
[2026-06-22 02:35:00 PM] Created user jdoe from HRMS data | CN=John Doe,OU=Users,DC=wgbd,DC=com [User: jdoe]
```
