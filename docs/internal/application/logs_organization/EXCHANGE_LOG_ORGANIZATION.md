# Exchange Log Organization Reference

## Overview

Exchange actions log to the **same log infrastructure** as AD operations. All mutating Exchange actions write structured logs under `{BaseLogPath}/{activeDomain}/scripts_logs/Exchange/` in the same format as AD script logs. Additionally, every mutating Exchange action writes to the CSV audit file under `{BaseLogPath}/app_audit_logs/`.

This document covers only the Exchange-specific portions. For the general log infrastructure (base path resolution, domain key resolution, CSV audit format), see `LOG_ORGANIZATION.md`.

---

## Directory Structure

All Exchange logs are written under `{BaseLogPath}/{activeDomain}/scripts_logs/Exchange/`:

```
{BaseLogPath}/                          (e.g., /data/logs or C:/access_pilot_logs)
├── app_audit_logs/                     — CSV audit (log_activity)
│   └── audit-YYYY-MM-DD.csv
│       (contains exchange_* entries for all mutating actions)
│
└── {activeDomain}/                     (e.g., wgbd.com, whildc.com)
    └── scripts_logs/
        └── Exchange/                   ← Exchange operations
            ├── Mailbox/                — all mailbox_* operations
            │   └── audit-YYYY-MM-DD.log
            ├── Group/                  — all group_* operations
            │   └── audit-YYYY-MM-DD.log
            └── Settings/               — settings_save
                └── audit-YYYY-MM-DD.log
```

### Real Example

```
/data/logs/wgbd.com/scripts_logs/Exchange/Mailbox/audit-2026-07-12.log
/data/logs/whildc.com/scripts_logs/Exchange/Group/audit-2026-07-12.log
/data/logs/wgbd.com/scripts_logs/Exchange/Settings/audit-2026-07-12.log
```

---

## Path Resolution

Exchange logs reuse the identical path resolution chain as AD logs:

### Base Path

`get_external_log_base()` in `helpers.php:269`:
1. **Priority 1:** `BaseLogPath` from secure XML metadata (`license_parse_secure_config_metadata()`)
2. **Priority 2:** `config/storage.php` → `storage.log_base_path` (supports `ACCESSPILOT_LOG_BASE_PATH` env var, set to `/data/logs` in Docker)

### Domain Path

The Exchange log path appends `Exchange/{Category}/` to the standard domain log path:

```
{BaseLogPath}/{activeDomain}/scripts_logs/Exchange/{Category}/
```

Where `{activeDomain}` is resolved by `ldap_active_domain_ad_name()` (e.g., `wgbd.com` from `DC=wgbd,DC=com`). Falls back to `ldap_active_domain_key()` then `'default'`.

### Writer

All Exchange structured logs are written by **`ldap_write_script_log()`** in `ldap_helpers.php:658` — the same function that writes AD operation logs. The only difference is the category routing:

| Parameter | Source | Example |
|-----------|--------|---------|
| `$operation` | Exchange action name from controller | `mailbox_enable` |
| `$targetUser` | `exchange_audit_target()` output | `identity=john.doe` |
| `$success` | From JSON response `success` field | `true` / `false` |
| `$message` | From JSON response `message` field | `Mailbox enabled.` |
| `$executedBy` | `$_SESSION['username']` | `admin` |
| Category | `ldap_script_log_category($operation)` | `ExchangeMailbox` |

### Trace: Code to Disk

```
exchange.php:238  exchange_audit_response()
  └─ exchange.php:287  ldap_write_script_log($action, $target, $success, $message, $username)
       └─ ldap_helpers.php:663  ldap_script_log_category($operation)
            └─ Returns: 'ExchangeMailbox' | 'ExchangeGroup' | 'ExchangeSettings'
       └─ ldap_helpers.php:701  $pathMap[category]
            └─ 'ExchangeMailbox'   → 'Exchange/Mailbox'
            └─ 'ExchangeGroup'     → 'Exchange/Group'
            └─ 'ExchangeSettings'  → 'Exchange/Settings'
       └─ ldap_helpers.php:726  Builds $logDir:
            {BaseLogPath}/{activeDomain}/scripts_logs/Exchange/{Category}/
       └─ ldap_helpers.php:734  Appends to:
            {logDir}/audit-{Y-m-d}.log
```

### Dual Logging

Every mutating Exchange action produces **two log entries**:

| Log | Target File | Writer | Format |
|-----|-------------|--------|--------|
| CSV Audit | `{BaseLogPath}/app_audit_logs/audit-YYYY-MM-DD.csv` | `log_activity()` | CSV |
| Structured | `{BaseLogPath}/{domain}/scripts_logs/Exchange/{Cat}/audit-YYYY-MM-DD.log` | `ldap_write_script_log()` | `[timestamp] Action: ...` |

---

## Writer → Path Map

### Category Routing (`ldap_script_log_category()`)

| Action Pattern | Category | Target Directory |
|----------------|----------|-----------------|
| `mailbox_enable` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_disable` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_user_create` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_set_quota` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_set_forward` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_set_primary_smtp` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_add_address` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_remove_address` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_add_full_access` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_remove_full_access` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_add_send_as` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_remove_send_as` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_set_litigation_hold` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_set_hidden_gal` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_update_profile` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_set_oof` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_move_request` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_create_shared` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_create_room` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_create_equipment` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_enable_archive` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_disable_archive` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_set_mail_tip` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_set_calendar_permissions` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_remove_calendar_permissions` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `mailbox_restore_request` | `ExchangeMailbox` | `Exchange/Mailbox` |
| `group_create` | `ExchangeGroup` | `Exchange/Group` |
| `group_add_member` | `ExchangeGroup` | `Exchange/Group` |
| `group_remove_member` | `ExchangeGroup` | `Exchange/Group` |
| `group_delete` | `ExchangeGroup` | `Exchange/Group` |
| `settings_save` | `ExchangeSettings` | `Exchange/Settings` |

**Routing logic** (`ldap_script_log_category()` at `ldap_helpers.php:496`):
- `settings_save` → explicit map entry
- Any operation starting with `mailbox_` → prefix match → `ExchangeMailbox`
- Any operation starting with `group_` → prefix match → `ExchangeGroup`
- Everything else → `General`

### Action Code Map (`ldap_script_log_action()`)

| Exchange Action | Action Code (Log Field) |
|----------------|------------------------|
| `mailbox_enable` | `MBX_ENABLE` |
| `mailbox_disable` | `MBX_DISABLE` |
| `mailbox_user_create` | `MBX_USER_CREATE` |
| `mailbox_create_shared` | `MBX_SHARED` |
| `mailbox_create_room` | `MBX_ROOM` |
| `mailbox_create_equipment` | `MBX_EQUIP` |
| `mailbox_set_quota` | `MBX_QUOTA` |
| `mailbox_set_forward` | `MBX_FWD` |
| `mailbox_set_primary_smtp` | `MBX_PRI_SMTP` |
| `mailbox_add_address` | `MBX_ADD_ADDR` |
| `mailbox_remove_address` | `MBX_REM_ADDR` |
| `mailbox_add_full_access` | `MBX_FULL_ACCESS` |
| `mailbox_remove_full_access` | `MBX_REM_FULL_ACCESS` |
| `mailbox_add_send_as` | `MBX_SEND_AS` |
| `mailbox_remove_send_as` | `MBX_REM_SEND_AS` |
| `mailbox_set_litigation_hold` | `MBX_LIT_HOLD` |
| `mailbox_set_hidden_gal` | `MBX_HID_GAL` |
| `mailbox_update_profile` | `MBX_UPD_PROFILE` |
| `mailbox_set_oof` | `MBX_OOF` |
| `mailbox_move_request` | `MBX_MOVE` |
| `mailbox_enable_archive` | `MBX_ARCH_ON` |
| `mailbox_disable_archive` | `MBX_ARCH_OFF` |
| `mailbox_get_archive` | `MBX_ARCH_GET` |
| `mailbox_set_mail_tip` | `MBX_MAIL_TIP` |
| `mailbox_set_calendar_permissions` | `MBX_CAL_PERM` |
| `mailbox_remove_calendar_permissions` | `MBX_REM_CAL_PERM` |
| `mailbox_restore_request` | `MBX_RESTORE` |
| `group_create` | `GRP_CREATE` |
| `group_add_member` | `GRP_ADD_MEM` |
| `group_remove_member` | `GRP_REM_MEM` |
| `group_delete` | `GRP_DELETE` |
| `group_search` | `GRP_SEARCH` |
| `group_members` | `GRP_MEMBERS` |
| `settings_save` | `SETTINGS` |

---

## Mutating vs Read-Only Actions

Only **28 mutating actions** produce logs. **18 read-only actions** produce no logs.

### Mutating (logged)

```
settings_save
mailbox_enable
mailbox_disable
mailbox_user_create
mailbox_set_quota
mailbox_set_forward
mailbox_set_primary_smtp
mailbox_add_address
mailbox_remove_address
mailbox_add_full_access
mailbox_remove_full_access
mailbox_add_send_as
mailbox_remove_send_as
mailbox_set_litigation_hold
mailbox_set_hidden_gal
mailbox_update_profile
mailbox_set_oof
mailbox_move_request
mailbox_create_shared
mailbox_create_room
mailbox_create_equipment
mailbox_enable_archive
mailbox_disable_archive
mailbox_set_mail_tip
mailbox_set_calendar_permissions
mailbox_remove_calendar_permissions
mailbox_restore_request
group_create
group_add_member
group_remove_member
group_delete
```

These 31 entries are listed in the `$mutatingActions` array at `exchange.php:240`.

### Read-Only (not logged)

```
discover
connection_test
exchange_diagnostic_test
mailbox_list
mailbox_search
mailbox_stats
mailbox_get_archive
group_search
group_members
monitoring_databases
monitoring_quota
monitoring_queues
monitoring_message_tracking
monitoring_transport_rules
monitoring_retention_policies
```

These actions exit early from `exchange_audit_response()` — no CSV audit entry and no structured log file entry.

---

## Log Entry Format

### Structured Script Log (`Exchange/{Category}/audit-YYYY-MM-DD.log`)

Single-line format identical to AD script logs:

```
[{Y-m-d h:i:s A}] Action: {CODE} | TargetUser: {target} | Status: {SUCCESS/FAILED} | Message: {message} | ExecutedBy: {username}
```

Fields:

| Field | Source | Example |
|-------|--------|---------|
| `timestamp` | `DateTime('now', 'Asia/Dhaka')` | `2026-07-12 03:30:00 PM` |
| `Action` | `ldap_script_log_action($operation)` | `MBX_USER_CREATE` |
| `TargetUser` | `exchange_audit_target($input)` — extracts first non-empty value from identity/user/group/member/name/email keys | `uddin69557` |
| `Status` | `SUCCESS` if JSON `success=true`, else `FAILED` | `SUCCESS` |
| `Message` | JSON `message` field, cleaned | `User created and mailbox enabled.` |
| `ExecutedBy` | `$_SESSION['username']` | `admin` |

**Message cleaning:**
- Everything after `\n\n` stripped (removes summary lines)
- `^(SUCCESS|ERROR|FAILED|WARN):\s*` prefix stripped via `preg_replace`
- Newlines replaced with ` | ` (pipe-space-pipe)

### CSV Audit Log (`app_audit_logs/audit-YYYY-MM-DD.csv`)

Standard CSV format written by `log_activity()`:

```
Timestamp,Username,Action,Status,Details
2026-07-12 15:30:00,admin,exchange_mailbox_user_create,success,Target: identity=john.doe. User created and mailbox enabled.
```

| Field | Value |
|-------|-------|
| `Action` | `exchange_{action_name}` (prefixed with `exchange_`) |
| `Status` | `success` or `failure` |
| `Details` | `Target: {target}. {message}` |

---

## Action Name Normalization

Exchange action codes follow a consistent `{PREFIX}_{SHORT_NAME}` pattern. The dashboard reader (`dashboard_normalize_action_name()`) normalizes them for display:

| Raw Action Code | Normalized | Description |
|----------------|------------|-------------|
| `MBX_ENABLE` | `MBX ENABLE` | Enable mailbox |
| `MBX_DISABLE` | `MBX DISABLE` | Disable mailbox |
| `MBX_USER_CREATE` | `MBX USER CREATE` | Create user + mailbox |
| `MBX_QUOTA` | `MBX QUOTA` | Set mailbox quota |
| `MBX_FWD` | `MBX FWD` | Set forwarding |
| `MBX_PRI_SMTP` | `MBX PRI SMTP` | Set primary SMTP |
| `MBX_ADD_ADDR` | `MBX ADD ADDR` | Add email address |
| `MBX_REM_ADDR` | `MBX REM ADDR` | Remove email address |
| `MBX_FULL_ACCESS` | `MBX FULL ACCESS` | Grant Full Access |
| `MBX_REM_FULL_ACCESS` | `MBX REM FULL ACCESS` | Revoke Full Access |
| `MBX_SEND_AS` | `MBX SEND AS` | Grant Send-As |
| `MBX_REM_SEND_AS` | `MBX REM SEND AS` | Revoke Send-As |
| `MBX_LIT_HOLD` | `MBX LIT HOLD` | Toggle litigation hold |
| `MBX_HID_GAL` | `MBX HID GAL` | Toggle hidden from GAL |
| `MBX_UPD_PROFILE` | `MBX UPD PROFILE` | Update profile |
| `MBX_OOF` | `MBX OOF` | Set out-of-office |
| `MBX_MOVE` | `MBX MOVE` | Move mailbox |
| `MBX_SHARED` | `MBX SHARED` | Create shared mailbox |
| `MBX_ROOM` | `MBX ROOM` | Create room mailbox |
| `MBX_EQUIP` | `MBX EQUIP` | Create equipment mailbox |
| `MBX_ARCH_ON` | `MBX ARCH ON` | Enable archive |
| `MBX_ARCH_OFF` | `MBX ARCH OFF` | Disable archive |
| `MBX_ARCH_GET` | `MBX ARCH GET` | Get archive info |
| `MBX_MAIL_TIP` | `MBX MAIL TIP` | Set mail tip |
| `MBX_CAL_PERM` | `MBX CAL PERM` | Set calendar permissions |
| `MBX_REM_CAL_PERM` | `MBX REM CAL PERM` | Remove calendar permissions |
| `MBX_RESTORE` | `MBX RESTORE` | Restore mailbox |
| `GRP_CREATE` | `GRP CREATE` | Create distribution group |
| `GRP_ADD_MEM` | `GRP ADD MEM` | Add group member |
| `GRP_REM_MEM` | `GRP REM MEM` | Remove group member |
| `GRP_DELETE` | `GRP DELETE` | Delete distribution group |
| `GRP_SEARCH` | `GRP SEARCH` | Search groups (read-only) |
| `GRP_MEMBERS` | `GRP MEMBERS` | List group members (read-only) |
| `SETTINGS` | `SETTINGS` | Save exchange settings |

---

## Reader Coverage

The dashboard log reader (`dashboard_read_logs()` in `dashboard_log_reader.php`) covers Exchange logs through its category-based scanner.

### How Exchange logs appear

The reader iterates over default categories, looks up each category's relative path in `dashboard_category_path_map()`, then scans that directory for `audit-*.log` files.

Exchange entries added to the reader:

**`dashboard_log_default_categories()`** — includes:
```
ExchangeMailbox
ExchangeGroup
ExchangeSettings
```

**`dashboard_category_path_map()`** — includes:
```
'ExchangeMailbox'   => 'Exchange/Mailbox',
'ExchangeGroup'     => 'Exchange/Group',
'ExchangeSettings'  => 'Exchange/Settings',
```

These mirror the writer-side entries in `ldap_write_script_log()` (`ldap_helpers.php:723-725`).

### Category display in filter UI

Categories appear as "ExchangeMailbox", "ExchangeGroup", "ExchangeSettings" in the dashboard log filter dropdown. The action codes (`MBX_ENABLE`, `GRP_CREATE`, etc.) are normalized by `dashboard_normalize_action_name()` for display as "MBX ENABLE", "GRP CREATE", etc.

---

## Implementation Summary

| Aspect | Detail |
|--------|--------|
| **Writer function** | `ldap_write_script_log()` in `ldap_helpers.php:658` |
| **Category function** | `ldap_script_log_category()` in `ldap_helpers.php:496` — prefix-match for `mailbox_*` and `group_*` |
| **Action code function** | `ldap_script_log_action()` in `ldap_helpers.php:530` — 30+ Exchange codes |
| **Controller trigger** | `exchange_audit_response()` in `exchange.php:238` — called after every action |
| **Mutating filter** | `$mutatingActions` array in `exchange.php:240` — 31 entries |
| **CSV audit** | `log_activity()` in `audit_service.php` — always called for mutating actions |
| **File name** | `audit-YYYY-MM-DD.log` (same as AD logs) |
| **Time zone** | `Asia/Dhaka` (same as AD logs) |
| **Target extraction** | `exchange_audit_target()` in `exchange.php:298` — reads identity/group/member/user/name/email fields from request input |

---

## Verification

To verify Exchange logs are being written:

```bash
# Check structured logs
ls -la /data/logs/{domain}/scripts_logs/Exchange/Mailbox/audit-*.log
ls -la /data/logs/{domain}/scripts_logs/Exchange/Group/audit-*.log
ls -la /data/logs/{domain}/scripts_logs/Exchange/Settings/audit-*.log

# Check CSV audit
grep "exchange_" /data/logs/app_audit_logs/audit-*.csv

# View latest Exchange log entries
tail -5 /data/logs/{domain}/scripts_logs/Exchange/Mailbox/audit-$(date +%F).log

# Check dashboard category availability
# Open dashboard → log filter → categories should include "Exchange/Mailbox", "Exchange/Group", "Exchange/Settings"
```

### Example Log Lines

```
[2026-07-12 03:30:00 PM] Action: MBX_USER_CREATE | TargetUser: john.doe | Status: SUCCESS | Message: User created and mailbox enabled. | ExecutedBy: admin
[2026-07-12 03:31:15 PM] Action: MBX_ENABLE | TargetUser: jane.smith | Status: SUCCESS | Message: Mailbox enabled for jane.smith. | ExecutedBy: operator
[2026-07-12 03:32:00 PM] Action: MBX_QUOTA | TargetUser: bob@company.com | Status: SUCCESS | Message: Quota set to 5 GB warning, 8 GB prohibit send. | ExecutedBy: admin
[2026-07-12 03:33:00 PM] Action: GRP_CREATE | TargetUser: DL-Sales | Status: FAILED | Message: The group 'DL-Sales' already exists. | ExecutedBy: admin
[2026-07-12 03:34:00 PM] Action: SETTINGS | TargetUser:  | Status: SUCCESS | Message: Exchange settings saved. | ExecutedBy: admin
```
