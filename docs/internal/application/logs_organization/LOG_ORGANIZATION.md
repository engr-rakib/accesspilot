# Log Organization Reference

## Directory Structure (Domain‑Aware Nested Paths — AD Name)

All logs written under two separate trees under `$BaseLogPath`:
1. **`{BaseLogPath}/app_audit_logs/`** — PHP audit CSV files (portal events: logins, config changes, AD actions from `log_activity()`)
2. **`{BaseLogPath}/{activeDomain}/scripts_logs/`** — Per-domain AD operation logs from both PHP LDAP and PowerShell backends

Default paths: `C:/access_pilot_logs` (Windows) or `/data/logs` (Docker, via `ACCESSPILOT_LOG_BASE_PATH` env var). The env var overrides `config/storage.php:10`.

### Path Resolution Order

`get_external_log_base()` (`helpers.php:269`):
1. **Priority 1:** `BaseLogPath` from secure XML metadata (`license_parse_secure_config_metadata()`)
2. **Priority 2:** `config/storage.php` → `storage.log_base_path` (supports `ACCESSPILOT_LOG_BASE_PATH` env var override)
3. **PowerShell** receives `BaseLogPath` from the same XML via `Import-Clixml`

### Domain Path Resolution

The AD domain name (`ldap_active_domain_ad_name()`) is extracted from `base_dn` in `domains.json` (e.g., `DC=wgbd,DC=COM` → `wgbd.com`). It is persisted in `config.json` as `active_domain_ad_name` for PowerShell consumption.

**Fallback chain** for script log path (`ldap_helpers.php:624-625`):
1. `ldap_active_domain_ad_name()` — AD name from base_dn (e.g., `wgbd.com`)
2. `ldap_active_domain_key()` — config key (e.g., `wgbd`)
3. `'default'` — hardcoded fallback

**Migration**: Logs were migrated from old key-based dirs to AD-named dirs:
- `default/` → `wgbd.com/` (877 files)
- `whildc/` → `whildc.com/`

**Reader fallback**: `dashboard_log_base_dir()` tries AD-named dir first, falls back to key-based dir (`dashboard_log_reader.php:63`).

| Backend | Mechanism |
|---------|-----------|
| **PHP `ldap_write_script_log()`** | `$logDir = BaseLogPath . DS . $activeDomain . DS . 'scripts_logs' . DS . $relativePath` (`ldap_helpers.php:626`) |
| **PHP `dashboard_log_base_dir()`** | `$scriptsLogsDir = BaseLogPath . DS . $activeDomain . DS . 'scripts_logs'` (`dashboard_log_reader.php:63`) |
| **PowerShell `Write-Log`** (all 15 scripts) | `Join-Path $BaseLogPath "$activeDomainAdName\scripts_logs\<Category>"` → reads `active_domain_ad_name` from `shared_config.json` |

### Full Directory Tree

```
{BaseLogPath}\                       (e.g., /data/logs or C:/access_pilot_logs)
├── app_audit_logs\                  — PHP audit service (log_activity) CSV files
│   └── audit-YYYY-MM-DD.csv
│
├── {activeDomain}\                  (e.g., wgbd.com, whildc.com)
│   └── scripts_logs\
│       ├── User_Management\
│       │   ├── UserEnable\            — enable user
│       │   ├── UserDisable\           — disable user
│       │   ├── unlock\                — unlock user
│       │   ├── PassReset\             — reset + unlock password
│       │   ├── UserModify\            — modify user
│       │   ├── UserInfo\              — get user info
│       │   ├── NewUser\               — create user
│       │   │   └── New_user_transcript_logs\  — create user transcripts
│       │   ├── ManualCreate\          — manual create user
│       │   └── UserInfo_disable\      — user info (disable path, PS only)
│       ├── UserReport\                — user report export (PHP only)
│       ├── Directory_Services\
│       │   ├── Ou_Group_Mgt\          — create/delete OU or group
│       │   ├── GroupMgmt\             — set group members
│       │   └── GroupMembership\       — get group members
│       ├── Integration\
│       │   ├── EmpStsChk\             — check AD-HRMS status
│       │   ├── FindLogonID\           — export HRMS-AD login ID
│       │   └── user_export\           — export group users
│       ├── HealthCheck\               — AD health check
│       └── General\                   — misc script logs
│
└── default\                         — legacy key-based dir (old format, read-only fallback)
```

**Real example** — active domain `wgbd.com` (Docker):
```
/data/logs/wgbd.com/scripts_logs/User_Management/UserEnable/audit-2026-06-12.log
```

### Domain Key Resolution

`ldap_active_domain_key()` reads from `config/shared_config.json` → `active_domain` field, falling back to `'default'` if unset or no config exists. Both PHP and PowerShell resolve the same source.

## Writer → Path Map

### PHP Backend (`ext-ldap` via `ldap_write_script_log()`)

The PHP `ldap_script_log_category()` function maps operations → categories, then `ldap_write_script_log()` resolves categories → directory paths. Operations not in the map default to `General`.

| Operation | Category | Action (Log Field) | Target Directory |
|-----------|----------|-------------------|-----------------|
| `reset_password` | `PassReset` | `U&RESET` | `User_Management/PassReset` |
| `unlock_user` | `unlock` | `UNLOCK` | `User_Management/unlock` |
| `enable_user` | `UserEnable` | `ENABLE` | `User_Management/UserEnable` |
| `disable_user` | `UserDisable` | `DISABLE` | `User_Management/UserDisable` |
| `modify_user` | `UserModify` | `MODIFY` | `User_Management/UserModify` |
| `create_user` | `NewUser` | `CREATE` | `User_Management/NewUser` |
| `set_group_members` | `GroupMgmt` | `grp_m.mgt` | `Directory_Services/GroupMgmt` |
| `create_directory_object` | `Ou&Grp_mgt` | `CREATE_OBJECT` | `Directory_Services/Ou_Group_Mgt` |
| `delete_directory_object` | `Ou&Grp_mgt` | `DELETE_OBJECT` | `Directory_Services/Ou_Group_Mgt` |
| `get_user_info` | `UserInfo` | `INFO` | `User_Management/UserInfo` |
| `export_hrms_ad_user_id` | `FindLogonID` | `LOGONID` | `Integration/FindLogonID` |
| `get_ad_hrms_status` | `EmpStsChk` | `STS_CHK` | `Integration/EmpStsChk` |
| `ou_group_user_report` | `user_export` | `USER_REPORT` | `Integration/user_export` |
| `user_report` | `UserReport` | `USER_REPORT` | `UserReport` |
| `ad_health_check` | `HealthCheck` | `HEALTH` | `HealthCheck` |

### PowerShell Backend (30 `.ps1` scripts via `Write-Log`)

PowerShell scripts use hardcoded action codes and paths in each script file.

| Writer | Operation | Action (Log Field) | Target Directory |
|--------|-----------|-------------------|-----------------|
| `enable-user.ps1` | enable | `ENABLE` | `User_Management/UserEnable` |
| `disable-user.ps1` | disable | `DISABLE` | `User_Management/UserDisable` |
| `unlock-user.ps1` | unlock | `UNLOCK` | `User_Management/unlock` |
| `reset-unlock-user.ps1` | reset+unlock | `U&RESET` | `User_Management/PassReset` |
| `modify-ad-user.ps1` | modify | `MODIFY` | `User_Management/UserModify` |
| `get-user-info.ps1` | info | `INFO` | `User_Management/UserInfo` |
| `get-user-info.ps1` (disable path) | info (disable) | `INFO` | `User_Management/UserInfo_disable` |
| `create-user-core.ps1` | create | `CREATE` | `User_Management/NewUser` |
| `manual-create-ad-user.ps1` | manual create | `M_CREATE` | `User_Management/ManualCreate` |
| `set-ad-group-members.ps1` | set members | `G_UPD` | `Directory_Services/GroupMgmt` |
| `get-ad-group-members.ps1` | get members | `GROUP_MEMBERSHIP_READ` | `Directory_Services/GroupMembership` |
| `create-ad-directory-object.ps1` | create object | `C_OU` / `C_GRP` | `Directory_Services/Ou_Group_Mgt` |
| `delete-ad-directory-object.ps1` | delete object | `D_OU` / `D_GRP` | `Directory_Services/Ou_Group_Mgt` |
| `check-ad-hrms-status.ps1` | check status | `STS_CHK` | `Integration/EmpStsChk` |
| `export-hrms-ad-login-id.ps1` | export logon ID | `LOGONID` | `Integration/FindLogonID` |
| `export-group-user-list.ps1` | export group | `user_export` | `Integration/user_export` |
| `get-ad-health.ps1` | health check | `HEALTH` | `HealthCheck` |

Both backends write to the identical nested directory structure (prefixed by `{activeDomain}\`). The `ldap_write_script_log()` function in `ldap_helpers.php` uses the same path map as `dashboard_log_reader.php`.

**Note:** PHP and PowerShell action codes may differ for the same operation (e.g., `grp_m.mgt` vs `G_UPD` for group management). The reader normalizes both via `dashboard_normalize_action_name()`.

## Log Entry Formats

### PHP Audit Service (`log_activity()` → `app_audit_logs/`)
CSV format with header row. Written by `resolved_log_path('audit.csv')`:
```
Timestamp,Username,Action,Status,Details
2026-06-22 10:30:00,admin,domain_switch,success,IP: 192.168.1.100, Details: Switched active domain to: wgbd.com
```

### Script Logs (`scripts_logs/` — both backends)
Single-line structured format:
```
[YYYY-MM-DD hh:mm:ss AM/PM] Action: <ACTION> | TargetUser: <user> | Status: <SUCCESS|FAILED|SKIPPED> | Message: <message> | ExecutedBy: <operator>
```

No `SUCCESS:` / `ERROR:` / `FAILED:` / `WARN:` prefix in the Message field — status is indicated by the Status field alone.

**Message cleaning (both backends):**
- PHP `ldap_write_script_log()`: strips everything after `\n\n` (removes summary like `Processed: 1 | Success: 1...`), then strips `^(SUCCESS|ERROR|FAILED|WARN):\s*` prefix via `preg_replace`
- PowerShell `Write-Log` (all 16 scripts): strips `^(SUCCESS|ERROR|FAILED|WARN):\s*` prefix via `-replace` before writing the log entry

## Config Resolution (`get_external_log_base()`)

1. **Priority 1:** `BaseLogPath` from secure XML metadata (`license_parse_secure_config_metadata()`)
2. **Priority 2:** `config/storage.php` → `storage.log_base_path` (default determined at config load):
   - Supports `ACCESSPILOT_LOG_BASE_PATH` env var (set to `/data/logs` in Docker `docker-compose.yml`)
   - Falls back to `C:/access_pilot_logs` on Windows
3. **PowerShell** receives `BaseLogPath` from the same XML via `Import-Clixml`

Admin can update via `index.php?page=system_config` → updates both `config/storage.php` and the XML metadata.

### Domain Key Resolution

The active domain key for the log path is resolved by `ldap_active_domain_key()` (`ldap_config_repository.php:297`):

```php
function ldap_active_domain_key(): string {
    $config = ldap_read_config();
    return (string) ($config['active_domain'] ?? 'default');
}
```

PowerShell scripts read the same `active_domain` field from `config/shared_config.json`. Falls back to `'default'` if unset.

## Reader Path Map (`dashboard_category_path_map()` in `dashboard_log_reader.php`)

```php
'NewUser'           => 'User_Management/NewUser',
'createUser'        => 'User_Management/NewUser',
'ManualCreate'      => 'User_Management/ManualCreate',
'ManulCreate'       => 'User_Management/ManualCreate',
'PassReset'         => 'User_Management/PassReset',
'unlock'            => 'User_Management/unlock',
'UserDisable'       => 'User_Management/UserDisable',
'UserEnable'        => 'User_Management/UserEnable',
'UserModify'        => 'User_Management/UserModify',
'UserInfo'          => 'User_Management/UserInfo',
'UserInfo-disable'  => 'User_Management/UserInfo_disable',
'Ou&Grp_mgt'        => 'Directory_Services/Ou_Group_Mgt',
'DirBuilder'        => 'Directory_Services/Ou_Group_Mgt',
'GroupMgmt'         => 'Directory_Services/GroupMgmt',
'GroupMembership'   => 'Directory_Services/GroupMembership',
'EmpStsChk'         => 'Integration/EmpStsChk',
'FindLogonID'       => 'Integration/FindLogonID',
'user_export'       => 'Integration/user_export',
'UserReport'        => 'UserReport',                <!-- PHP-only: user_report operation -->
'HealthCheck'       => 'HealthCheck',
'General'           => 'General',
```

## Action Name Normalization (`dashboard_normalize_action_name`)

| Raw | Normalized |
|-----|-----------|
| `ENABLE`, `ENABLE_USER`, `ENABLE USER` | `ENABLE` |
| `DISABLE`, `DISABLE USER` | `DISABLE` |
| `UNLOCK`, `UNLOCKUSER` | `UNLOCK` |
| `U&RESET`, `RESET+UNLOCK`, `RESETUNLOCK` | `U & RESET` |
| `CREATE`, `CREATEUSER` | `CREATE` |
| `C_OU`, `CREATE_OU` | `CREATE OU` |
| `C_GRP`, `CREATE_GRP` | `CREATE GRP` |
| `D_OU`, `DLT_OU` | `DELETE OU` |
| `D_GRP`, `DLT_GRP` | `DELETE GRP` |
| `G_UPD`, `GRP_M.MGT` | `GRP UPDATE` |
