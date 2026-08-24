# Architecture — Exchange Management

## Overview

Exchange Management provides mailbox lifecycle management, distribution group administration, mail flow monitoring, and settings through a unified 3-tab UI. Uses **dual-backend**: LDAP for reads (mailbox attributes, server discovery), PowerShell via WinRM for writes and monitoring queries.

## Quick Specs

| Metric | Value |
|--------|-------|
| Actions | **46** (30 mutating + 16 read-only) |
| Controller | `exchange.php` — **1,741 lines**, 46 case handlers |
| PS Runner | `ExchangePsRunner.php` — **957 lines**, **58 functions** (47 cmdlet wrappers + 11 helpers) |
| JS Module | `exchange_actions.js` — **2,119 lines**, ~55 top-level functions |
| View | `view.php` — **477 lines**, 3 tabs |
| Exchange CSS | `exchange.css` — **37 lines** |
| RBAC permissions | **12 keys** in `components_config.php:400-422` |
| API endpoint | `POST /api/index.php?endpoint=exchange` |
| Page URL | `index.php?page=exchange` |

## Architecture Diagram

```
Browser (exchange_actions.js — 2,119 lines)
  Tab: Mailboxes & Groups | Monitoring | Settings
  │
  │ POST /api/index.php?endpoint=exchange
  │ X-CSRF-Token header (getCsrfToken())
  ▼
exchange.php (1,741 lines) — Controller
  ├─ 46 case labels in switch($action)
  ├─ Permission map (46 entries) → 12 RBAC keys
  ├─ exchange_audit_response() → CSV + structured logs
  │
  ├── LDAP Path (reads) ───────────────────┐
  │   mailbox_list, mailbox_search          │
  │   group_search, group_members           │
  │   Exchange server discovery             │
  │   Database discovery                    │
  │                                         │
  └── PS Path (writes + monitoring) ────────┤
      ExchangePsRunner.php (957 lines)      │
        exchange_run_cmdlet()               │
        exchange_ensure_kerberos_ticket()   │
        exchange_build_inline_script()      │
            │                               │
            ▼                               │
      pwsh (PowerShell Core 7.x)            │
        New-PSSession -Auth Kerberos        │
        Import-PSSession                    │
        Cmdlet | ConvertTo-Json -Depth 3    │
            │                               │
            ▼                               │
      Exchange Server IIS /PowerShell/      │
        40+ cmdlets (Get/Set/Enable/        │
        Disable-Mailbox, New/Remove-        │
        DistributionGroup, Get-Queue,       │
        Get-TransportRule, etc.)            │
```

## Routing & Registration

```
URL:   index.php?page=exchange
API:   POST /api/index.php?endpoint=exchange

page_registry.php:203 → case 'exchange'
  → exchange.css loaded
  → exchange_actions.js loaded
  → exchange/view.php rendered via include_path()

menu_config.php:77 → Nav rail item, permission: page_exchange
components_config.php:400-422 → 12 permission keys + 4 card definitions
```

## Key Design Decisions

### ADR-1: Dual Backend (LDAP reads, PowerShell writes)

Exchange attributes (mailbox GUID, proxy addresses, quotas) are in AD/LDAP. Write operations (Enable-Mailbox, Set-Mailbox, Get-Queue) require Exchange Management Shell via PowerShell.

Read mailbox attrs + server info from LDAP. Dispatch all mutations to PowerShell. Unified response via `ldap_response_adapter.php`.

### ADR-2: Per-Action PSSession (no pooling)

Each action creates a new PSSession. `exchange_run_cmdlet()` flow:
1. `exchange_ensure_kerberos_ticket()` — reuses cached TGT if valid
2. Creates new PSSession via `New-PSSession`
3. Imports session via `Import-PSSession`
4. Runs cmdlet, parses JSON
5. Closes session via `Remove-PSSession`

~1-3s overhead per action. No stale sessions to clean up.

### ADR-3: Combined Search (mutual exclusion)

Single "Mailboxes & Groups" tab. Two inputs: mailbox field + group field. Typing in one disables the other. Single Search button routes to `loadMailboxList()` or `doGroupSearch()`.

### ADR-4: ConvertTo-Json -Depth 3

Default depth (2) truncates nested objects to `@{property=value}`. All inline scripts use `ConvertTo-Json -Depth 3 -WarningAction SilentlyContinue`.

## All 46 Actions

### Mailbox Actions (28)

| Action | Permission | Backend | Description |
|--------|-----------|---------|-------------|
| `discover` | settings | LDAP | Discover Exchange server via Config NC |
| `mailbox_list` | mailbox_view | LDAP | List mailboxes by keyword (displayName, sAMAccountName, mail, UPN) |
| `mailbox_search` | mailbox_view | LDAP | Get single user mailbox detail |
| `mailbox_stats` | mailbox_view | PS | Get-MailboxStatistics (size, item count, logon) |
| `mailbox_enable` | mailbox_enable | PS | Enable-Mailbox for AD user |
| `mailbox_disable` | mailbox_disable | PS | Disable-Mailbox (keeps AD user) |
| `mailbox_user_create` | mailbox_enable | LDAP+PS | Create AD user + Enable-Mailbox + group membership |
| `mailbox_set_quota` | mailbox_quota | PS | Set-Mailbox — issue warning / prohibit send / prohibit receive |
| `mailbox_set_forward` | mailbox_forward | PS | Set-Mailbox — forwarding address + keep local copy |
| `mailbox_set_primary_smtp` | mailbox_address | PS | Set-Mailbox — change primary SMTP |
| `mailbox_add_address` | mailbox_address | PS | Set-Mailbox — add proxy address |
| `mailbox_remove_address` | mailbox_address | PS | Set-Mailbox — remove proxy address |
| `mailbox_add_full_access` | mailbox_address | PS | Add-MailboxPermission -FullAccess |
| `mailbox_remove_full_access` | mailbox_address | PS | Remove-MailboxPermission |
| `mailbox_add_send_as` | mailbox_address | PS | Add-ADPermission -ExtendedRights "send-as" |
| `mailbox_remove_send_as` | mailbox_address | PS | Remove-ADPermission |
| `mailbox_set_litigation_hold` | mailbox_quota | PS | Set-Mailbox -LitigationHoldEnabled |
| `mailbox_set_hidden_gal` | mailbox_address | PS | Set-Mailbox -HiddenFromAddressListsEnabled |
| `mailbox_update_profile` | mailbox_address | PS | Set-Mailbox (displayName, phone, title, dept, company, office) |
| `mailbox_set_oof` | mailbox_address | PS | Set-MailboxAutoReplyConfiguration |
| `mailbox_move_request` | mailbox_quota | PS | New-MoveRequest to target database |
| `mailbox_create_shared` | mailbox_enable | PS | New-Mailbox -Shared |
| `mailbox_create_room` | mailbox_enable | PS | New-Mailbox -Room (with capacity) |
| `mailbox_create_equipment` | mailbox_enable | PS | New-Mailbox -Equipment |
| `mailbox_enable_archive` | mailbox_enable | PS | Enable-Mailbox -Archive |
| `mailbox_disable_archive` | mailbox_disable | PS | Disable-Mailbox -Archive |
| `mailbox_get_archive` | mailbox_view | PS | Get-Mailbox -Archive info |
| `mailbox_set_mail_tip` | mailbox_address | PS | Set-Mailbox -MailTip |
| `mailbox_set_calendar_permissions` | mailbox_address | PS | Set-MailboxFolderPermission |
| `mailbox_remove_calendar_permissions` | mailbox_address | PS | Remove-MailboxFolderPermission |
| `mailbox_restore_request` | mailbox_enable | PS | New-MailboxRestoreRequest |

### Group Actions (5)

| Action | Permission | Backend | Description |
|--------|-----------|---------|-------------|
| `group_search` | group_view | LDAP | Search distribution groups by cn/mail |
| `group_members` | group_view | LDAP | List group members |
| `group_create` | group_create | PS | New-DistributionGroup |
| `group_add_member` | group_modify | PS | Add-DistributionGroupMember |
| `group_remove_member` | group_modify | PS | Remove-DistributionGroupMember |
| `group_delete` | group_delete | PS | Remove-DistributionGroup |

### Monitoring Actions (7)

| Action | Permission | Backend | Description |
|--------|-----------|---------|-------------|
| `monitoring_databases` | monitoring | PS | Get-MailboxDatabase (server, mounted, size, backup) |
| `monitoring_quota` | monitoring | PS | Mailboxes >80% usage report |
| `monitoring_queues` | monitoring | PS | Get-Queue (delivery type, status, count, risk) |
| `monitoring_message_tracking` | monitoring | PS | Get-MessageTrackingLog (sender, recipient, date range) |
| `monitoring_transport_rules` | monitoring | PS | Get-TransportRule (name, state, priority, conditions) |
| `monitoring_retention_policies` | monitoring | PS | Get-RetentionPolicy (name, retention tags) |

### Settings Actions (3)

| Action | Permission | Backend | Description |
|--------|-----------|---------|-------------|
| `connection_test` | settings | PS | Test PSSession connectivity |
| `exchange_diagnostic_test` | settings | PS | Full diagnostic with config_override |
| `settings_save` | settings | Config | Save default database, quota, warning threshold |

## Mutating Actions (30)

List in `exchange.php:240-272` — `$mutatingActions` array:

```
settings_save
mailbox_enable, mailbox_disable, mailbox_set_quota, mailbox_set_forward
mailbox_set_primary_smtp, mailbox_add_address, mailbox_remove_address
mailbox_add_full_access, mailbox_remove_full_access
mailbox_add_send_as, mailbox_remove_send_as
mailbox_set_litigation_hold, mailbox_set_hidden_gal
mailbox_update_profile, mailbox_user_create, mailbox_set_oof
mailbox_move_request, mailbox_create_shared, mailbox_create_room
mailbox_create_equipment, mailbox_enable_archive, mailbox_disable_archive
mailbox_set_mail_tip, mailbox_set_calendar_permissions
mailbox_remove_calendar_permissions, mailbox_restore_request
group_create, group_add_member, group_remove_member, group_delete
```

## RBAC Permission Keys

12 keys in `components_config.php:400-422`:

| Key | Mutates? | Actions |
|-----|----------|---------|
| `page_exchange` | No | Page + menu access |
| `action_exchange_mailbox_view` | No | mailbox_list, search, stats, get_archive |
| `action_exchange_mailbox_enable` | Yes | enable, user_create, archive enable, shared/room/equip create, restore |
| `action_exchange_mailbox_disable` | Yes | disable, archive disable |
| `action_exchange_mailbox_quota` | Yes | set_quota, litigation_hold, move_request |
| `action_exchange_mailbox_forward` | Yes | set_forward |
| `action_exchange_mailbox_address` | Yes | SMTP, Full Access, Send-As, OOF, GAL, MailTip, Calendar, profile |
| `action_exchange_group_view` | No | group_search, group_members |
| `action_exchange_group_create` | Yes | group_create |
| `action_exchange_group_modify` | Yes | group_add/remove_member |
| `action_exchange_group_delete` | Yes | group_delete |
| `action_exchange_monitoring` | No | databases, quota, queues, message_tracking, transport_rules, retention |
| `action_exchange_settings` | Yes | discover, connection_test, settings_save |

Permission map in `exchange.php:28-75` — each of 46 actions maps to one RBAC key.

## PowerShell Integration

### Authentication Flow

```php
exchange_ensure_kerberos_ticket()  // ExchangePsRunner.php:216
  1. exec('klist -s 2>&1') — check cached TGT
  2. If valid (exit 0) → return true (skip recreation)
  3. exchange_get_credential() → username + password
  4. Create keytab via ktutil:
       add_entry -password -p user@REALM -k 1 -e aes256-cts-hmac-sha1-96
       write_kt /tmp/exchange_krb5.keytab
  5. kinit -k -t /tmp/exchange_krb5.keytab user@REALM
  6. unlink /tmp/exchange_krb5.keytab (cleanup)
  7. exec('klist -s') → verify ticket acquired

exchange_run_cmdlet($cmdlet, $params, $configOverride)  // line 254
  1. Resolve server (override → auto-discover)
  2. Resolve PS URI (override → build from server)
  3. Resolve credentials (ps_username → bind DN)
  4. exchange_ensure_kerberos_ticket()
  5. exchange_build_inline_script() → PS script string
  6. pwsh -NoProfile -ExecutionPolicy Bypass -File /tmp/ps_XXXX.ps1
  7. Parse JSON output
```

### Auth Mode Comparison

| Mode | Condition | Auth | PSSession |
|------|-----------|------|-----------|
| **Kerberos** | Linux + ps_username empty | kinit keytab → cached TGT | New-PSSession -Auth Kerberos |
| **Basic** | Linux + ps_username set | PSCredential from stored password | New-PSSession -Auth Basic -Credential |
| **Basic** | Windows IIS | PSCredential | New-PSSession -Auth Basic -Credential |

## Exchange Server Discovery (3-level)

`exchange_discover_server()` at `ExchangePsRunner.php:6`:

1. **Config NC search**: LDAP query `(&(objectClass=msExchExchangeServer)(msExchCurrentServerRoles:...:=2))` on Configuration Naming Context. Requires Enterprise read access.

2. **Database discovery**: Search `objectClass=msExchMDB`, extract server from each database. Same permission requirement.

3. **msExchHomeServerName fallback**: Find user with `msExchMailboxGuid=* AND msExchHomeServerName=*`. Parse server name from DN. No special permissions needed.

Result FQDN resolved via DNS or AD DNS fallback (`nslookup host AD_IP`).

### Host Resolution at Startup

`scripts/resolve_exchange_hosts.php` (74 lines) runs at container boot:
1. Reads all domains from `/data/secure/ldap/domains.json`
2. For each enabled Exchange domain, tries DNS resolution
3. Falls back to AD DNS (`nslookup host AD_IP`)
4. Writes `IP FQDN` to `/etc/hosts`

## Credential Storage

### File Locations

| File | Content |
|------|---------|
| `/data/secure/ldap/domains.json` | Domain config with `exchange` sub-object |
| `/data/secure/ldap/exchange_secrets/{domain_key}.json` | Exchange PS password (AES-256-CBC encrypted) |
| `/data/secure/ldap/secrets/{domain}.json` | LDAP bind password (fallback if exchange_secrets empty) |

### exchange_get_credential() flow (`ExchangePsRunner.php:91`)

1. Read `exchange.ps_username` from domain config
2. Empty → fallback to `bind_dn` (LDAP bind user)
3. Read `ps_password` from `ldap_read_exchange_secret()`
4. Empty → fallback to `ldap_read_bind_password()` (LDAP bind password)
5. Return `['username' => ..., 'password' => ...]`

## Exchange Config in Domain JSON

```json
"exchange": {
    "enabled": true,
    "server_override": "",
    "ps_uri_override": "http://DC-EX-MBX01.WHILDC.COM/PowerShell/",
    "ps_use_https": false,
    "ps_username": ""
}
```

| Field | Purpose | Notes |
|-------|---------|-------|
| `enabled` | Feature toggle | Boolean |
| `server_override` | Hard-code Exchange FQDN | Empty = auto-discover |
| `ps_uri_override` | Full PSSession URI | Must use FQDN (not IP) for Kerberos SPN |
| `ps_use_https` | HTTPS for PS connection | Currently false (HTTP port 80) |
| `ps_username` | Exchange-specific username | Empty = LDAP bind user |
| `ps_password` | Stored in vault (exchange_secrets/) | AES-256-CBC encrypted |

### Credential Mode (System Config → Edit Domain)

| Mode | ps_username | Auth | PS URI |
|------|-------------|------|--------|
| **Bind** (default) | empty | Kerberos (kinit from bind password) | Auto-built from server + port |
| **Override** | set | Basic (PSCredential) | Uses ps_uri_override or auto-built |

## Structured Logging

All 30 mutating actions write **dual logs**:

### 1. CSV Audit (`log_activity()`)
`/data/logs/app_audit_logs/audit-{Y-m-d}.csv`
Format: `Timestamp,Username,exchange_{action},success/failure,"Target:..."`

### 2. Structured Script Log (`ldap_write_script_log()`)
```
/data/logs/{domain}/scripts_logs/Exchange/
  ├── Mailbox/audit-{Y-m-d}.log     ← all mailbox_* actions
  ├── Group/audit-{Y-m-d}.log       ← all group_* actions
  └── Settings/audit-{Y-m-d}.log    ← settings_save
```

Format:
```
[{Y-m-d h:i:s A}] Action: {CODE} | TargetUser: {target} | Status: SUCCESS/FAILED | Message: {msg} | ExecutedBy: {user}
```

### Category Routing

`ldap_script_log_category()` in `ldap_helpers.php:496`:
- `settings_save` → `ExchangeSettings`
- `mailbox_*` prefix → `ExchangeMailbox`
- `group_*` prefix → `ExchangeGroup`

`ldap_write_script_log()` pathMap (line 708):
- `ExchangeMailbox` → `Exchange/Mailbox`
- `ExchangeGroup` → `Exchange/Group`
- `ExchangeSettings` → `Exchange/Settings`

### Action Codes

Defined in `ldap_script_log_action()` at `ldap_helpers.php:531`:

```
Mailbox:   MBX_ENABLE  MBX_DISABLE  MBX_USER_CREATE  MBX_SHARED
           MBX_ROOM    MBX_EQUIP    MBX_QUOTA         MBX_FWD
           MBX_PRI_SMTP  MBX_ADD_ADDR  MBX_REM_ADDR
           MBX_FULL_ACCESS  MBX_REM_FULL_ACCESS
           MBX_SEND_AS      MBX_REM_SEND_AS
           MBX_LIT_HOLD  MBX_HID_GAL  MBX_UPD_PROFILE
           MBX_OOF  MBX_MOVE  MBX_ARCH_ON  MBX_ARCH_OFF  MBX_ARCH_GET
           MBX_MAIL_TIP  MBX_CAL_PERM  MBX_REM_CAL_PERM  MBX_RESTORE

Group:     GRP_CREATE  GRP_ADD_MEM  GRP_REM_MEM  GRP_DELETE
           GRP_SEARCH  GRP_MEMBERS

Settings:  SETTINGS
```

### exchange_audit_response() (exchange.php:238)

1. Check if action in `$mutatingActions` (30 items)
2. `log_activity()` → CSV audit log
3. `ldap_write_script_log()` → structured file log

## LDAP Exchange Attributes (response sub-array)

From `ldap_response_adapter.php:179-200` — 21 fields under `exchange_mailbox`:

```php
'has_mailbox'               // bool — msExchMailboxGUID != null
'mailbox_guid'              // string
'alias'                     // mailNickname
'primary_smtp'              // mail field
'proxy_addresses'           // [{address, type, is_primary}]
'home_database'             // msExchHomeServerName
'recipient_type'            // 'UserMailbox'|'SharedMailbox'|'RoomMailbox'|'EquipmentMailbox'|'None'
'recipient_type_details'    // int code
'hidden_from_gal'           // bool
'when_created'              // msExchWhenMailboxCreated
'archive_guid'              // msExchArchiveGUID
'archive_name'              // msExchArchiveName
'mailbox_disabled'          // bool
'quota_use_database_defaults' // bool
'issue_warning_quota_kb'    // int — mDBStorageQuota
'prohibit_send_quota_kb'    // int — mDBOverQuotaLimit
'prohibit_send_receive_quota_kb' // int — mDBOverHardQuotaLimit
'issue_warning_quota'       // human-readable
'prohibit_send_quota'       // human-readable
'prohibit_send_receive_quota' // human-readable
```

## Frontend Architecture

### exchange_actions.js (2,119 lines)

IIFE pattern (`'use strict'`), all functions private inside closure.

Key state variables:
- `activeIdentity` — currently selected mailbox identity
- `mailboxList` — cached mailbox search results
- `exchangeDatabases` — cached database list
- `_exchangeSelectedGroups` — selected groups for new user create

Key functions:

| Function | Lines | Purpose |
|----------|-------|---------|
| `init()` | 92-109 | Entry: bind search, tabs, form toggles |
| `bindOuTree()` | 111-153 | OU tree dropdown |
| `bindGroupMemberSearch()` | 156-196 | Group membership search |
| `bindCombinedSearch()` | 215-259 | Mutual-exclusion search |
| `loadMailboxList()` | 280-308 | Fetch mailbox list from API |
| `renderMailboxList()` | 318-354 | Render results table |
| `doGroupSearch()` | 367-392 | Fetch groups from API |
| `renderGroupList()` | 394-429 | Render group results |
| `bindMailboxUserCreate()` | 507-570 | Create form toggle + lazy binding |
| `doMailboxUserCreate()` | 573-629 | Submit create user + mailbox + groups |
| `doGroupMembers()` | 632-657 | Fetch group members |
| `renderGroupMembers()` | 660-716 | Render member list + add/remove |
| `loadMailboxStats()` | 1511-1600 | Get mailbox size/usage via PS |
| `renderMailboxResult()` | 944-1359 | Full mailbox detail card |
| `doMailboxAction()` | 1732-1760 | Generic mutating action POST |
| `showExchangeAction()` | 1702-1717 | Feedback card (type string) |
| `showExchangeFeedback()` | 1719-1730 | Feedback card (boolean) |
| `getCsrfToken()` | 2088-2090 | CSRF token from meta tag |
| `parseJsonResponse()` | 2092-2103 | Fetch response wrapper |

### Feedback Card

Two parallel mechanisms:

| Function | Params | Used By | Auto-hide |
|----------|--------|---------|-----------|
| `showExchangeAction(type, message)` | string, plain text | Combined search, validation | 8s |
| `showExchangeFeedback(success, title, message)` | bool, HTML strings | doMailboxAction, doMailboxUserCreate | 8s / 15s |

DOM: `#exchangeActionCard` with `#exchangeActionTitle` + `#exchangeActionMessage`.
Colors: Error=red, Success=green, Info=blue, default=yellow (border-left).
Overrides `.alert { display: flex }` with `display: block !important`.

### OU Tree Dropdown

Custom dropdown (no Bootstrap):
- `bindOuTree(displayId, dropdownId, listId, hiddenId, ['OU', 'Domain'])`
- Opens on focus/input/click, closes on Escape or outside click
- `.workspace-content-scroll` gets `.dropdown-open` class for scroll
- CSS: `position:absolute; z-index:999999`
- Card requires `overflow-visible-card` class

### Group Membership Search

- `bindGroupMemberSearch()` — similar OU dropdown pattern
- Groups stored in `_exchangeSelectedGroups[]`
- Rendered as tag badges with remove (🗑) button
- Hidden input receives comma-joined DNs

## View Structure (view.php, 477 lines)

```
Tab 1: #tab-recipients — Mailboxes & Groups
  ├─ Two inputs + Go button (mutual exclusion)
  ├─ "New User" + "New Group" buttons
  ├─ Create Mailbox User Form (#exchangeMailboxUserCreateForm)
  │   ├─ Existing AD User / New User toggle
  │   ├─ First/Last/Username/DisplayName/Email
  │   ├─ OU tree selector
  │   ├─ Group membership search (tags UI)
  │   └─ Submit button
  ├─ Create Group Form (#exchangeGroupCreateForm)
  │   ├─ Name/Alias/Description/OU
  │   └─ Submit button
  ├─ Action Card (#exchangeActionCard) — feedback
  └─ Result Card (#exchangeResultCard) — dynamic content

Tab 2: #tab-monitoring
  ├─ Database Status (refresh)
  ├─ Quota Warning Report (refresh)
  ├─ Mail Flow Queues (refresh)
  ├─ Message Tracking (sender/recipient/dates)
  ├─ Transport Rules
  └─ Retention Policies

Tab 3: #tab-settings
  ├─ Connection status + Test Connection
  ├─ Default Database / Default Quota / Warning Threshold → Save
  └─ Shared/Room/Equipment mailbox creation forms
```

## Docker Dependencies

| Package | Purpose | Installation |
|---------|---------|-------------|
| `pwsh` | PowerShell Core 7.x — run Exchange cmdlets | Dockerfile: Microsoft repo + apt |
| `PSWSMan` | WinRM native library (`libpsrpclient.so`) | `Install-Module PSWSMan -Force; Install-WSMan` |
| `krb5-user` | kinit, ktutil — Kerberos ticket mgmt | `apt-get install krb5-user` |
| `libgssapi-krb5-2` | GSSAPI for .NET/pwsh | Debian 12 base |
| `resolve_exchange_hosts.php` | Auto-resolve Exchange FQDN in /etc/hosts | Container boot command |

## Key Files Reference

| File | Lines | Role |
|------|-------|------|
| `exchange.php` | 1,741 | 46 action handlers, RBAC map (28-75), audit(238-296) |
| `ExchangePsRunner.php` | 957 | 58 functions: discover_server(6), get_credential(91), build_inline_script(119), ensure_kerberos_ticket(216), run_cmdlet(254), 47 cmdlet wrappers |
| `ldap_helpers.php` | 1,009 | log_category(496), log_action(531), write_script_log(701), discover_servers(823), get_databases(870), parse_proxy(905) |
| `ldap_response_adapter.php:179-200` | ~22 | `exchange_mailbox` sub-array (21 fields) |
| `ldap_user_writer.php:972-989` | ~18 | Auto-provision mailbox flag on AD user create |
| `ldap_config_repository.php` | ~220 | Exchange secret read/write (exchange_secrets/) |
| `exchange_actions.js` | 2,119 | All UI rendering, event bindings, fetch |
| `exchange/view.php` | 477 | 3-tab page structure |
| `exchange.css` | 37 | Page-specific styles |
| `config/powershell.php` | 47 | pwsh binary path, flags, script temp path |
| `config/components_config.php:400-422` | ~23 | RBAC keys + card definitions |
| `config/page_registry.php:203` | ~1 | Route: `?page=exchange` → view + assets |
| `config/menu_config.php:77` | ~1 | Sidebar nav item |
| `scripts/resolve_exchange_hosts.php` | 74 | Boot script: DNS → /etc/hosts |
