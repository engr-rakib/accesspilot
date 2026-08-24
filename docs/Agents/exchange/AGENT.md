# Exchange Integration — AI Agent Reference

## Current Status: ✅ COMPLETE

All Phase 1-3 features implemented. Exchange fully integrated with dual LDAP + PowerShell backend, Kerberos auth on Linux, structured logging.

---

## Quick Facts

| Metric | Value |
|--------|-------|
| **Page URL** | `index.php?page=exchange` |
| **API endpoint** | `POST /api/index.php?endpoint=exchange` |
| **Actions** | **46 total** (30 mutating + 16 read-only) |
| **Controller** | `exchange.php` — **1741 lines** |
| **PS runner** | `ExchangePsRunner.php` — **957 lines**, 58 functions, 47 cmdlet wrappers |
| **JS module** | `exchange_actions.js` — **2119 lines**, IIFE pattern, ~55 top-level functions |
| **View** | `view.php` — **477 lines**, 3 tabs |
| **RBAC permissions** | **12 keys** in `components_config.php:399-422` |

---

## Architecture

```
Browser JS → PHP 8.2 (Linux Docker) → LDAP (read)
                                     → ExchangePsRunner (write)
                                         ↕
                                  pwsh (PowerShell 7.6.3 on Linux)
                                         ↕
                                  WinRM (WSMan via PSWSMan library)
                                         ↕
                                  Exchange Server IIS /PowerShell/
```

### Key Points

- **App hosting:** Linux Docker container (Debian 12, php:8.2-fpm)
- **PowerShell:** `pwsh` 7.6.3 runs INSIDE the Linux container — not on a Windows server
- **Remote protocol:** WinRM over HTTP — `PSWSMan` provides the native WSMan client library for Linux (`libpsrpclient.so`)
- **Auth chain:** LDAP bind password → ktutil keytab → kinit ticket → Kerberos PSSession → Exchange accepts
- **Exchange endpoint:** IIS `/PowerShell/` virtual directory with `ConfigurationName: Microsoft.Exchange`
- **Server discovery:** 3-level fallback (Config NC → Database → msExchHomeServerName)
- **Host resolution:** Auto-mapped to `/etc/hosts` at container startup by `resolve_exchange_hosts.php`

### Decision: LDAP vs PowerShell

| Use LDAP (direct) | Use PowerShell (WinRM) |
|---|---|
| User search by sAMAccountName/email | Enable/Disable-Mailbox |
| Read proxyAddresses, mail, msExch attrs | Set-Mailbox (quota, forward, SMTP) |
| Exchange server discovery | Get-MailboxStatistics |
| Database discovery (msExchMDB) | Distribution group CRUD |
| Count users with mailbox | Monitoring (queues, tracking, transport) |
| Auto-provision on user create | Full Access, Send-As, OOF, GAL, move |
| Hidden from GAL (LDAP write) | Archive, MailTip, Calendar, restore |
| SMTP addresses (LDAP write) | Shared/Room/Equipment mailbox creation |
| Group search / group members | New-DistributionGroup, Add/Remove member |

---

## Combined Search (Mailbox + Group)

**Tab "Mailboxes & Groups"** (#tab-recipients) merges mailbox and group search into one tab.

```
Two inputs side-by-side:
  ┌──────────────────────┐  ┌──────────────────────┐  ┌──────────┐
  │ #exchangeMailboxId   │  │ #exchangeGroupKeyword │  │  Search  │
  └──────────────────────┘  └──────────────────────┘  └──────────┘

Mutual exclusion via setLocked():
  mbInput typed    → grpInput disabled + opacity 0.5
  grpInput typed   → mbInput disabled + opacity 0.5
  both empty       → both enabled

Go button routes:
  mbVal && grpVal  → ERROR: "Clear one field"
  mbVal            → loadMailboxList(mbVal)
  grpVal           → doGroupSearch(grpVal)
  neither          → INFO: "Enter a value"
```

### Key JS Functions

| Function | Lines | Purpose |
|----------|-------|---------|
| `bindOuTree(displayId, dropdownId, listId, hiddenId, types)` | 111-153 | OU tree dropdown |
| `bindGroupMemberSearch()` | 156-196 | Group membership search dropdown |
| `bindCombinedSearch()` | 215-259 | Mutual-exclusion search binding |
| `loadMailboxList(keyword)` | 280-308 | Fetch + render mailbox results |
| `renderMailboxList(mailboxes, data)` | 318-354 | Table with clickable rows |
| `doGroupSearch(keyword)` | 367-392 | Fetch + render group results |
| `renderGroupList(groups, body, title)` | 394-429 | Group results table |
| `bindMailboxUserCreate()` | 507-570 | Form toggle + OU/Group lazy-bind |
| `doMailboxUserCreate()` | 573-629 | Submit create user + mailbox + groups |
| `renderMailboxResult()` | 944-1359 | Full mailbox detail card |
| `doMailboxAction()` | 1732-1760 | Generic mutating action POST |
| `showExchangeFeedback(success, title, message)` | 1719-1730 | Feedback card (boolean) |
| `showExchangeAction(type, message)` | 1702-1717 | Feedback card (type string) |

---

## OU Tree Dropdown

The OU selector in the create user form uses a **custom dropdown** (not Bootstrap):

```
bindOuTree('exchangeUserCreateOUDisplay', 'exchangeUserCreateOUDropdown',
           'exchangeUserCreateOUList', 'exchangeUserCreateOU', ['OU', 'Domain'])

open():
  → dropdown.style.display = 'block'
  → .workspace-content-scroll.classList.add('dropdown-open')
  → adTreeDropdown.fetchUnifiedTree(listEl, callback, types, value)
  → callback: sets display + hidden input values → close()

close():
  → dropdown.style.display = 'none'
  → .workspace-content-scroll.classList.remove('dropdown-open')

Events: focus, input, click → open; Escape → close; document click → close

CSS: position: absolute; top: 100%; left: 0; right: 0; z-index: 999999
Card: overflow-visible-card class (overflow: visible; transform: none)
```

### Key Points
- Scrolling enabled when dropdown open via `.dropdown-open` class
- `scrollbar-gutter: stable` prevents layout shift
- No inline position calculation — all CSS-driven

---

## Create User Flow (Backend)

`handle_mailbox_user_create()` in `exchange.php:774-875`:

```
Step 1: LDAP Create
  → ldap_add() with:
    objectClass: [top, person, organizationalPerson, user]
    userAccountControl: 544 (enabled + password not required)
  → Container DN from OU selector or base DN

Step 2: Enable Mailbox (PowerShell)
  → exchange_enable_mailbox($username)
  → Optionally Set-Mailbox for primary SMTP

Step 3: Group Membership (LDAP)
  → foreach ($input['groups'] as $groupDn):
      ldap_mod_add($connection, $groupDn, ['member' => $userDn])
  → Uses fresh ldap_run_with_connection() call
```

---

## Structured Logging

All 30 mutating Exchange actions write **structured logs** matching the AD script log format.

### Directory Layout

```
/data/logs/{domain}/scripts_logs/Exchange/
  ├── Mailbox/audit-{Y-m-d}.log     ← all mailbox_* actions
  ├── Group/audit-{Y-m-d}.log       ← all group_* actions
  └── Settings/audit-{Y-m-d}.log    ← settings_save
```

### Log Entry Format

```
[{Y-m-d h:i:s A}] Action: {CODE} | TargetUser: {target} | Status: SUCCESS/FAILED | Message: {msg} | ExecutedBy: {user}
```

### Category Routing

```
ldap_script_log_category($operation):
  'settings_save'                   → 'ExchangeSettings'
  str_starts_with('mailbox_')       → 'ExchangeMailbox'
  str_starts_with('group_')         → 'ExchangeGroup'

ldap_write_script_log() pathMap:
  'ExchangeMailbox'  → 'Exchange/Mailbox'
  'ExchangeGroup'    → 'Exchange/Group'
  'ExchangeSettings' → 'Exchange/Settings'
```

### Audit function

`exchange_audit_response()` at `exchange.php:238`:
1. Checks if action is in `$mutatingActions` list (30 actions)
2. Calls `log_activity()` → CSV audit in `app_audit_logs/audit-*.csv`
3. Calls `ldap_write_script_log()` → structured file log

### Action Codes (ldap_script_log_action)

```
MBX_ENABLE       MBX_DISABLE        MBX_USER_CREATE    MBX_SHARED
MBX_ROOM         MBX_EQUIP          MBX_QUOTA          MBX_FWD
MBX_PRI_SMTP     MBX_ADD_ADDR       MBX_REM_ADDR       MBX_FULL_ACCESS
MBX_REM_FULL_ACCESS  MBX_SEND_AS    MBX_REM_SEND_AS    MBX_LIT_HOLD
MBX_HID_GAL      MBX_UPD_PROFILE    MBX_OOF            MBX_MOVE
MBX_ARCH_ON      MBX_ARCH_OFF       MBX_ARCH_GET       MBX_MAIL_TIP
MBX_CAL_PERM     MBX_REM_CAL_PERM   MBX_RESTORE
GRP_CREATE       GRP_ADD_MEM        GRP_REM_MEM        GRP_DELETE
GRP_SEARCH       GRP_MEMBERS
SETTINGS
```

---

## Docker Dependencies

| Package | Why | Installed Via |
|---------|-----|--------------|
| `pwsh` | PowerShell Core 7.6.3 — run Exchange cmdlets | `Dockerfile` Microsoft repo + apt |
| `PSWSMan` module | WSMan native lib for Linux (`libpsrpclient.so`) | `Install-Module PSWSMan -Force` + `Install-WSMan` |
| `krb5-user` | `kinit`, `ktutil` — Kerberos ticket acquisition | `apt-get install krb5-user` |
| `libgssapi-krb5-2` | GSSAPI Kerberos mechanism for .NET/pwsh | Debian 12 base |
| `extra_hosts` / `resolve_exchange_hosts.php` | Exchange FQDN resolution | Container startup script |

### Container Setup Sequence

```bash
# 1. Install pwsh (Dockerfile)
wget -q https://packages.microsoft.com/config/debian/12/packages-microsoft-prod.deb
dpkg -i packages-microsoft-prod.deb
apt-get install -y powershell

# 2. Install PSWSMan (once, baked into image)
pwsh -Command 'Install-Module PSWSMan -Force; Import-Module PSWSMan; Install-WSMan'

# 3. Install Kerberos (Dockerfile)
apt-get install -y krb5-user

# 4. krb5.conf (at /etc/krb5.conf)
[libdefaults]
    default_realm = WHILDC.COM
    dns_lookup_realm = false
    dns_lookup_kdc = true
    ticket_lifetime = 24h
    renew_lifetime = 7d
    forwardable = true

[realms]
    WHILDC.COM = {
        kdc = dc-ad1.whildc.com
        admin_server = dc-ad1.whildc.com
        default_domain = whildc.com
    }
```

---

## LDAP Credential Loading (Exchange Auth)

### File Locations

| File | Content |
|------|---------|
| `/data/secure/ldap/domains.json` | Domain config: host, bind_dn, exchange sub-config |
| `/data/secure/ldap/exchange_secrets/{domain_key}.json` | Exchange PS password, encrypted `enc:<iv_hex>:<ciphertext_hex>` |
| `/data/secure/ldap/secrets/{domain}.json` | LDAP bind password (fallback if exchange_secrets empty) |

### Config Fallback Chain

```
domains.json → exchange.ps_username
  ↓ if empty
domains.json → bind_dn  (e.g. "rakibu66684@WHILDC.COM")
```

### Password Decryption

```
vault JSON → "enc:b850aa2b8...:8b49241b8d..."
  ↓ ldap_read_domain_secret()
  ↓ ldap_decrypt_password()
  ↓
deployment_encryption_key() → AES-256-CBC key (32 bytes)
openssl_decrypt(ciphertext, 'aes-256-cbc', key, OPENSSL_RAW_DATA, iv)
  ↓
plaintext password
```

### exchange_get_credential() flow (`ExchangePsRunner.php:90`)

1. Read `exchange.ps_username` from domain config
2. If empty → use `bind_dn` (LDAP bind user)
3. Read `ps_password` from vault (`ldap_read_exchange_secret()`)
4. If empty → call `ldap_read_bind_password()` (LDAP bind password decryption)
5. Return `['username' => ..., 'password' => ...]`

**Result:** Same AD user credentials used for both LDAP and Exchange — no separate Exchange user needed.

---

## Kerberos Auth Flow

```
exchange_run_cmdlet()
  ↓
exchange_ensure_kerberos_ticket()          ← ExchangePsRunner.php:214
  ├─ klist -s → check existing cached ticket
  │   (valid 10h, renewable 7d)
  ├─ exchange_get_credential() → password
  ├─ ktutil: create keytab from password
  │   add_entry -password -p user@REALM -k 1 -e aes256-cts-hmac-sha1-96
  │   write_kt /tmp/exchange_krb5.keytab
  ├─ kinit -k -t /tmp/exchange_krb5.keytab user@REALM
  └─ delete keytab
  ↓
exchange_build_inline_script()             ← ExchangePsRunner.php:118
  → $session = New-PSSession -ConfigurationName Microsoft.Exchange
         -ConnectionUri 'http://FQDN/PowerShell/'
         -Authentication Kerberos -ErrorAction Stop
  → Import-PSSession $session
  → cmdlet | ConvertTo-Json
  ↓
powershell_run_inline()                    ← powershell_runner.php:172
  → pwsh -NoProfile -ExecutionPolicy Bypass -File /tmp/ps_XXXX.ps1
  → JSON output parsed
```

### Key Constraint: FQDN Required

**Must use FQDN in URI** — IP address won't work because Kerberos looks up `HTTP/<host>` SPN:
```
✅ http://DC-EX-MBX01.WHILDC.COM/PowerShell/  ← works
❌ http://192.168.119.160/PowerShell/          ← "Server not found in Kerberos database"
```

---

## Exchange Server Discovery (3-level fallback)

`exchange_discover_server()` at `ExchangePsRunner.php:6`:

```
1. Config NC search
   Query: (&(objectClass=msExchExchangeServer)(...ServerRoles...:=2))
   Needs: Enterprise/Admin Config NC read access
   ↓ fail
2. Database discovery
   Query: objectClass=msExchMDB
   Extract: server name from each database's server attribute
   Same permission as #1
   ↓ fail
3. msExchHomeServerName fallback
   Find any user: msExchMailboxGuid=* AND msExchHomeServerName=*
   Parse: cn=Servers/cn=SERVERNAME/... from msExchHomeServerName DN
   No special permissions needed
   ↓
Result: "DC-EX-MBX01" → FQDN (resolved via DNS or AD DNS fallback)
```

### Exchange Host Resolution

At container startup, `scripts/resolve_exchange_hosts.php`:
1. Reads all domains from `/data/secure/ldap/domains.json`
2. For each enabled Exchange domain, tries DNS resolution
3. Falls back to AD DNS (`nslookup host AD_IP`)
4. Writes results to `/etc/hosts`

---

## Feedback Card System

Two parallel feedback mechanisms in JS:

| Function | Used By | Params | Auto-hide |
|----------|---------|--------|-----------|
| `showExchangeAction(type, message)` | Combined search, validation | `type` (string), `message` (plain text) | 8s |
| `showExchangeFeedback(success, title, message)` | `doMailboxAction()`, `doMailboxUserCreate()` | `success` (bool), `title` + `message` (HTML) | 8s success / 15s failure |

**Colors:** Error=red, Success=green, Info=blue, default=yellow (border-left).

**DOM:** `#exchangeActionCard` with `#exchangeActionTitle` + `#exchangeActionMessage`.
- Overrides `.alert { display: flex }` with `display: block !important`

---

## Key Files & Their Roles

| File | What to Know |
|------|-------------|
| `exchange.php` | 46 action handlers in `switch($action)`. Permission map at lines 28-73. Each handler checks RBAC, calls LDAP or PS, returns JSON. Audit + structured logging on mutations. |
| `ExchangePsRunner.php` | `exchange_run_cmdlet()` is the core executor (line 252). Key functions: `exchange_ensure_kerberos_ticket()` (line 214), `exchange_build_inline_script()` (line 118). |
| `ldap_helpers.php:496-528` | `ldap_script_log_category()` — routes Exchange operations to ExchangeMailbox/Group/Settings |
| `ldap_helpers.php:530-586` | `ldap_script_log_action()` — Exchange action code map (30+ codes) |
| `ldap_helpers.php:658-720` | `ldap_write_script_log()` — structured log writer with pathMap for Exchange directories |
| `ldap_helpers.php:823-932` | `ldap_exchange_discover_servers()`, `ldap_exchange_get_databases()`, `ldap_parse_proxy_addresses()`, `ldap_user_has_mailbox()` |
| `ldap_response_adapter.php:179-200` | `exchange_mailbox` sub-array in user info response — 21 fields |
| `ldap_user_writer.php:972-989` | Auto-provision mailbox flag during AD user creation |
| `ldap_config_repository.php` | Exchange password vault read/write per domain |
| `exchange_actions.js` | All frontend logic (2119 lines). Key: `renderMailboxResult()` (944), `doMailboxAction()` (1732), `showExchangeFeedback()` (1719) |
| `exchange/view.php` | 3-tab page (477 lines). Combined search + feedback card |
| `exchange.css` | Page-specific styles (37 lines) |
| `powershell.php` (config) | Binary path: `PHP_OS_FAMILY` switch — `/usr/bin/pwsh` (Linux) / `powershell.exe` (Windows) |
| `resolve_exchange_hosts.php` (scripts/) | Startup script (74 lines): auto-resolves Exchange FQDN in /etc/hosts |
| `page_registry.php:203` | Route registration for `?page=exchange` |
| `menu_config.php:77` | Sidebar nav item with `page_exchange` permission |
| `components_config.php:400-422` | 12 RBAC permission keys + 4 card definitions |

---

## RBAC Permission Keys

| Key | Mutates? | Checked In |
|-----|----------|------------|
| `page_exchange` | No | Menu + page access |
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

---

## LDAP Exchange Attributes (21 fields in response)

From `ldap_response_adapter.php:179-200`:
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

---

## Exchange Config in LDAP Domain JSON

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
| `enabled` | Enable Exchange for this domain | Boolean |
| `server_override` | Hard-code Exchange server name | Empty = auto-discover |
| `ps_uri_override` | Full PSSession URI | Must use FQDN (not IP) for Kerberos SPN |
| `ps_use_https` | Use HTTPS for PS connection | Currently false (HTTP port 80) |
| `ps_username` | Exchange-specific username | Empty = use LDAP bind user |
| `ps_password` | Stored in vault (separate file) | AES-256-CBC encrypted |

### Credential Mode (Bind vs Override)

| Mode | When | Auth method | PS URI |
|------|------|------------|--------|
| **Bind** (default) | `ps_username` empty | Kerberos (kinit keytab from bind pw) | Auto-built from server + port |
| **Override** | `ps_username` set | Basic auth with explicit PSCredential | Uses `ps_uri_override` or auto-built |

Set via **System Config → Edit Domain** card → Exchange section → Credential Mode dropdown.

---

## Multi-User / Bulk Support

- `mailbox_list` action performs LDAP search across users, returns paginated results
- `monitoring_quota` iterates all mailboxes via PS, filters by >80% usage
- Individual operations (enable, disable, set quota) are single-user only
- No CSV bulk import implemented

---

## Common Pitfalls

1. **Exchange server hostname not resolved:** Kerberos requires FQDN in URI. If using IP, "Server not found in Kerberos database" error. Fix: auto-resolve via `resolve_exchange_hosts.php`.

2. **pwsh binary path:** On Linux, must be `/usr/bin/pwsh`. Config at `config/powershell.php:12` uses `PHP_OS_FAMILY` to select.

3. **proc_open() disabled:** PHP distro may disable `proc_open()`. The `exchange_ensure_kerberos_ticket()` uses temp file + `exec('ktutil < file')` instead.

4. **TLS/SASL from CLI:** Library loading order may cause issues with some PHP functions. Always use the app's bootstrap functions for LDAP.

5. **Password vault not writable:** `ldap_read_bind_password()` can't decrypt if `deployment_encryption_key()` returns wrong key.

6. **Exchange PS vDir auth:** Must accept Kerberos (not just Negotiate). IIS should show `WWW-Authenticate: Kerberos` header.

7. **Ticket expiration:** `kinit` creates 10-hour ticket. Next `exchange_run_cmdlet()` call re-acquires if expired.

8. **PS session pooling:** Each cmdlet creates its own PSSession — no pooling. Intentional for simplicity.

9. **LDAP write conflicts:** Exchange may overwrite LDAP-written proxyAddresses on next sync. For permanent SMTP changes, use PowerShell.

10. **Config NC access:** Auto-discovery may fail if AD user lacks Enterprise read access to Configuration partition. Level 3 fallback (msExchHomeServerName) always works.

11. **OU dropdown clipping:** The OU/Group dropdown uses `position:absolute; z-index:999999`. The containing card must have class `overflow-visible-card` (sets `overflow:visible`). If dropdown appears clipped, check parent stacking contexts.

12. **Feedback card not showing:** `#exchangeActionCard` has `display: block !important` to override `.alert { display: flex }`. If card doesn't appear, check DevTools for JS errors.

---

## Adding a New Action

1. **PS wrapper** in `ExchangePsRunner.php` — create function calling `exchange_run_cmdlet('Cmdlet-Name', $params)`
2. **Handler** in `exchange.php` — add `case 'new_action':` in switch, RBAC check, call PS/LDAP, return JSON
3. **RBAC permission** in `components_config.php` if new permission needed
4. **JS function** in `exchange_actions.js` — POST to endpoint, render results, call `showExchangeFeedback()`
5. **UI elements** in `exchange/view.php` — buttons/forms in the appropriate tab
6. **Logging** — add to `$mutatingActions` list if mutating. If action name starts with `mailbox_` or `group_` the category routing works automatically. Otherwise add explicit mapping in `ldap_script_log_category()` and `ldap_script_log_action()`.

---

## Testing

- Check browser console for feedback card (`#exchangeActionCard`) after operations
- DevTools Network tab: POST to `/api/index.php?endpoint=exchange`
- After PHP changes: `docker exec accesspilot_php php -r 'opcache_reset();'`
- After JS/CSS changes: Hard refresh (Ctrl+F5)
- Exchange server reachable? `curl -v http://HOST/PowerShell/` → expect 401 (auth required)
- Kerberos ticket valid? `docker exec accesspilot_php klist`
- Structured logs: Check `/data/logs/{domain}/scripts_logs/Exchange/{Mailbox,Group,Settings}/audit-*.log`
- CSV audit: Check `/data/logs/app_audit_logs/audit-*.csv` for `exchange_*` entries

---

## Exchange Version Compatibility

Tested with: Exchange 2016 CU23+, 2019 CU12+
Requires: Remote PowerShell enabled, IIS-hosted `/PowerShell/` virtual directory with Kerberos auth
RBAC roles: `View-Only Recipients` (read), `Recipient Management` (write)
