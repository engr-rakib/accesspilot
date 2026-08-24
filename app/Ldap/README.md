# LDAP Module — Complete Reference

## Table of Contents

1. [LDAP Blueprint & Architecture](#1-ldap-blueprint--architecture)
2. [Backend Routing System](#2-backend-routing-system)
3. [Operations Status](#3-operations-status)
4. [Workflow Charts](#4-workflow-charts)
5. [Intelligence Hub — LDAP Integration](#5-intelligence-hub--ldap-integration)
6. [Adding a New LDAP Handler](#6-adding-a-new-ldap-handler)
7. [Security Architecture](#7-security-architecture)
8. [Previous Issues & Agent Instructions](#8-previous-issues--agent-instructions)
9. [Key Files Reference](#9-key-files-reference)

---

## 1. LDAP Blueprint & Architecture

### Tier 1 — Controllers (UI Entry Points)

```
Browser/API Request
    │
    ┌───────────────┬───────────────────┬───────────────────────┐
    ▼               ▼                   ▼                       ▼
execute_action   Direct Controller   Intelligence Hub        PowerShell
(router-based)   (modify/create/    Controller               Scripts
                 group/directory)   (5 LDAP handlers         (scripts/                  1 health check)         powershell/)
```

### Tier 2 — Router & Dispatch

```
                         ad_operation_router.php
                               │
                  ┌────────────┴────────────┐
                  ▼                         ▼
         ad_ldap_execute()        ad_powershell_execute_script()
                  │                         │
                  ▼                         ▼
         LDAP Handler function     PowerShell .ps1 script
         (in-process, PHP)         (separate process)
                  │                         │
                  ▼                         ▼
         ldap_json_script_result()   stdout JSON/CSV
```

### Tier 3 — LDAP Handlers

```
app/Ldap/Operations/
    │
    ├── ldap_user_writer.php          — enable, disable, unlock, reset-pw, modify, create
    ├── ldap_directory_writer.php     — list OUs, create/delete objects, group sync
    ├── ldap_user_repository.php      — get_user_info (read/search)
    └── ldap_hub_reports.php          — Mapping, Sync, Users, Reports (Intelligence Hub)
```

### Tier 4 — Support Infrastructure

```
app/Ldap/Support/
    ├── ldap_helpers.php              — json_script_result, feedback_message, write_script_log,
    │                                    paged_search, normalize_entry, escape_filter
    ├── ldap_config_repository.php    — read/write secure config XML
    └── ldap_response_adapter.php     — normalize output format

app/Ldap/Connection/
    └── ldap_connection_factory.php   — connect + bind + unbind

app/Ldap/Router/
    └── ad_operation_router.php       — backend resolution, dispatch, auto-logging

config/ldap/
    ├── ldap_operations.php           — per-operation ldap_ready flags
    └── LogonConfig.xml               — secure config (credentials, servers)
```

---

## 2. Backend Routing System

### 2.1 Backend Mode (user-configurable)

Set in LDAP Settings UI → stored in `LogonConfig.xml`.

| Mode | Behavior |
|------|----------|
| `powershell` | Always runs PowerShell scripts (default, backward compatible) |
| `ldap` | Runs PHP LDAP handlers for enabled operations; fails if LDAP unavailable |
| `auto` | Runs PHP LDAP handlers first; falls back to PowerShell if handler returns `success=false` |

### 2.2 Operation Ready Flag

`config/ldap/ldap_operations.php` defines `$ldapOperations['ldap_ready']`:

```php
'ldap_ready' => [
    'enable_user'       => true,   // LDAP handles this when backend=ldap
    'modify_user'       => true,
    'ad_health_check'   => false,  // Always PowerShell (needs dcdiag/repadmin)
    ...
]
```

**To enable LDAP for an operation:** set its key to `true`.
**To disable (revert to PowerShell):** set to `false`.

### 2.3 Backend Resolution Algorithm

```
ad_resolve_backend($operation):
    mode = ldap_read_config()['backend'] ?? 'powershell'
    ready = ldap_operations['ldap_ready'][$operation] ?? false

    if mode === 'powershell':    return 'powershell'   # forced PowerShell
    if mode === 'ldap':          return 'ldap'          # forced LDAP (or fail)
    if mode === 'auto':
        if ready:                return 'ldap'          # try LDAP first
        else:                    return 'powershell'    # fallback for unready ops

    return 'powershell'  # default
```

### 2.4 Two Dispatch Paths

#### Path A — Router (`ad_operation_router.php`)

Used by `execute_action.php` for single-user operations (enable, disable, unlock, reset-pw, create).

```
Controller → ad_execute_action() → ad_operation_router.php
    → ad_ldap_execute()           # LDAP path
      → ldap_dispatch_execute_action()
        → maps action string to handler function
        → calls handler(connection, params)
        → handler returns decoded result
        → ldap_write_script_log() auto-called
    → ad_powershell_execute_script()    # PowerShell path
```

#### Path B — Intelligence Hub Centralized Dispatch (`ad_dispatch_report_operation()`)

Used by all 6 Intelligence Hub controllers.

```
Controller → ad_dispatch_report_operation($operation, $params)
    → Looks up catalog: ldap_operation_catalog.php
    → ad_resolve_backend($operation)
    → if 'ldap': ad_ldap_execute($operation, $params, $executedBy)
        → calls handler function from catalog
        → handler returns ['output' => json, 'decoded' => [...], 'json_valid' => true]
        → ldap_write_script_log() auto-called
    → if 'powershell': ad_powershell_execute_script($scriptKey, $params)
        → runs .ps1 script, returns stdout
    → Controller handles output:
        if json_valid: use decoded data directly
        else: treat output as raw text (CSV)
```

#### Path C — Direct Controllers

Older controllers that check backend inline instead of going through router:

| Controller | Backend Check | LDAP Handler Called |
|------------|---------------|---------------------|
| `modify_ad_user.php` | Inline `ldap_read_config()['backend']` | `ldap_user_writer_update` |
| `manual_create_user.php` | Inline | `ldap_user_writer_create` |
| `update_group_members.php` | Inline | `ldap_group_writer_sync_members` |
| `create_directory_object.php` | Inline | `ldap_directory_writer_create` |
| `delete_directory_object.php` | Inline | `ldap_directory_writer_delete` |

---

## 3. Operations Status

### 3.1 All Operations — LDAP vs PowerShell

| # | Operation | Category | LDAP Handler | ldap_ready | Dispatched Via |
|---|-----------|----------|:------------:|:----------:|----------------|
| 1 | `enable_user` | UserEnable | `ldap_user_writer_set_enabled` | ✅ true | Router (Path A) |
| 2 | `disable_user` | UserDisable | `ldap_user_writer_set_enabled` | ✅ true | Router (Path A) |
| 3 | `unlock_user` | unlock | `ldap_user_writer_unlock` | ✅ true | Router (Path A) |
| 4 | `reset_password` | PassReset | `ldap_user_writer_reset_password` | ✅ true | Router (Path A) |
| 5 | `create_user` | NewUser | `ldap_user_writer_create` | ✅ true | Router (Path A) |
| 6 | `modify_user` | UserModify | `ldap_user_writer_update` | ✅ true | Direct |
| 7 | `get_user_info` | UserInfo | `ldap_user_repository_get_info` | ✅ true | Direct |
| 8 | `set_group_members` | GroupMgmt | `ldap_group_writer_sync_members` | ✅ true | Direct |
| 9 | `create_directory_object` | Ou&Grp_mgt | `ldap_directory_writer_create` | ✅ true | Direct |
| 10 | `delete_directory_object` | Ou&Grp_mgt | `ldap_directory_writer_delete` | ✅ true | Direct |
| 11 | `export_hrms_ad_user_id` | FindLogonID | `ldap_hub_map_hrms_user_id` | ✅ true | Hub (Path B) |
| 12 | `get_ad_hrms_status` | EmpStsChk | `ldap_hub_check_hrms_status` | ✅ true | Hub (Path B) |
| 13 | `export_ad_users` | user_export | `ldap_hub_export_users` | ✅ true | Hub (Path B) |
| 14 | `export_group_users` | user_export | `ldap_hub_export_users` | ✅ true | Hub (Path B) |
| 15 | `user_report` | UserReport | `ldap_hub_user_report` | ✅ true | Hub (Path B) |
| 16 | `ad_health_check` | N/A | ❌ NONE | ❌ false | Hub (Path B) |

### 3.2 Why Health Check (`ad_health_check`) Stays PowerShell-Only

The health check runs Windows-native diagnostics that have **no LDAP equivalent**:

| Diagnostic | Tool Required | LDAP Alternative |
|------------|--------------|------------------|
| DC reachability (ICMP ping) | `Test-Connection` | None |
| Active Directory Web Services | `Get-ADDomainController` | None |
| NTDS replication summary | `repadmin /replsummary` | None |
| AD database & services | `dcdiag /test:...` | None |
| Time synchronization | `w32tm /query /status` | None |
| DNS resolution for DCs | `Resolve-DnsName` | None |
| Domain functional level | `(Get-ADDomain).DomainMode` | None |

These require **local Windows administration tools** — not accessible via LDAP port 389.
Health check always dispatches to PowerShell regardless of backend mode.

---

## 4. Workflow Charts

### 4.1 Single-User Operation (enable/disable/unlock/reset/create)

```
User clicks "Enable User" in UI
    │
execute_action.php
    │ POST action=enableUser&username=59023
    ▼
ad_execute_action('execute_action', '59023', 'enableUser', $loggedInUser)
    │
    ▼
ad_operation_router.php → ad_resolve_backend('execute_action')
    │
    ├── backend='powershell' ──→ PowerShell: enable-user.ps1
    │                               │
    │                               ▼ Write-Output (ConvertTo-Json $result)
    │
    └── backend='ldap' ──→ ldap_dispatch_execute_action('enableUser', $connection, $config)
                               │
                               ▼ ldap_user_writer_set_enabled($connection, $config, $username, true)
                                    │
                                    ├── Search user by sAMAccountName
                                    ├── Check userAccountControl bit 2
                                    ├── If already enabled → skip
                                    ├── Modify userAccountControl: 512 (enabled)
                                    └── Return ldap_json_script_result(...)
                                    │
                                    ▼ ldap_write_script_log() auto-called by router
                                    │
                                    ▼ Controller reads decoded.message
                                         Echoes JSON to UI → Feedback card
```

### 4.2 Intelligence Hub Operation (Mapping/Sync/Users/Reports)

```
User clicks "Export Users" in UI
    │
custom_export_users_message.php
    │ POST ouName=&groupName=
    ▼
ad_dispatch_report_operation('export_ad_users', ['AllUsers'=>true, ...])
    │
    ├── 1. Look up catalog: ldap_operation_catalog.php
    │       → ldap_handler: 'ldap_hub_export_users'
    │       → ps_script_key: 'Custom_export_group_USer_list'
    │
    ├── 2. ad_resolve_backend('export_ad_users')
    │       → backend='ldap' + ldap_ready=true → return 'ldap'
    │
    ├── 3. ad_ldap_execute('export_ad_users', $params, $executedBy)
    │       → ldap_run_with_connection(function($conn, $config) { ... })
    │           → ldap_hub_export_users($params, $executedBy)
    │               → Determine scope: All/OU/Group
    │               → ldap_paged_search(...)
    │               → Build csvContent[] array
    │               → Return ldap_json_script_result([...])
    │       → ldap_write_script_log() auto-called
    │       → Return ['output'=>json, 'decoded'=>[...], 'json_valid'=>true]
    │
    ├── (if auto-fallback: ldapResult.success=false → ad_powershell_execute_script)
    │
    ├── 4. Controller:
    │       if json_valid: $psOutput = implode("\n", decoded['csvContent'])
    │       else:          $psOutput = raw text (PowerShell path)
    │       Store in $_SESSION, return JSON response
    │
    ▼
UI feedback: "Users member list report generated successfully..."
```

### 4.3 Dual-Output Decision Tree (Controllers)

```
$psResult = ad_dispatch_report_operation(...)
    │
    ▼
┌─ $psResult['json_valid'] === true AND is_array($psResult['decoded']) ?
│
├── YES (LDAP path) ──→ Use decoded structure:
│   ├── 'results'[] → build CSV from array
│   ├── 'csvContent'[] → implode("\n", ...) for stored CSV
│   ├── 'success' → boolean
│   └── 'message' → feedback string
│
└── NO (PowerShell path) ──→ Use raw output:
    ├── $psResult['output'] → treat as CSV/JSON string
    └── Parse manually (existing pattern)
```

---

## 5. Intelligence Hub — LDAP Integration

### 5.1 Background

All 5 Intelligence Hub report/export operations now have PHP LDAP handlers in a single file:

**`app/Ldap/Operations/ldap_hub_reports.php`** — 4 handler functions:

| Function | Serves | Input | Output |
|----------|--------|-------|--------|
| `ldap_hub_map_hrms_user_id` | `export_hrms_ad_user_id` | Comma-sep HRMS IDs | JSON with results[] |
| `ldap_hub_check_hrms_status` | `get_ad_hrms_status` | Comma-sep emp codes | JSON with results[] |
| `ldap_hub_export_users` | `export_ad_users` + `export_group_users` | OU/Group/All scope | JSON with csvContent[] |
| `ldap_hub_user_report` | `user_report` | Status + Days | JSON with users[] |

### 5.2 Test Results (PHP CLI — backend=ldap)

| Operation | Result | Time |
|-----------|--------|------|
| Mapping — exact match | ✅ Single AD user found | ~0.3s |
| Mapping — substring match | ✅ Single match returned | ~0.3s |
| Mapping — not found | ✅ ERROR properly reported | ~0.3s |
| Mapping — multiple matches | ✅ Ambiguous error returned | ~0.3s |
| Sync — single emp code | ✅ HRMS API + AD lookup | ~3s (API latency) |
| Users — All Users export | ✅ 7714 rows, admin flags | ~12s |
| Users — Group filter | ✅ Group scope correct | ~2s |
| Reports — Disabled | ✅ 2613 users | ~8s |
| Reports — Inactive (30d) | ✅ 1127 users | ~8s |
| Reports — Active (30d) | ✅ 3891 users | ~8s |

### 5.3 Catalog Registration

```php
// app/Ldap/Operations/ldap_operation_catalog.php
'export_ad_users' => [
    'api_endpoint' => 'custom_export_users',
    'ps_script_key' => 'Custom_export_group_USer_list',
    'ldap_handler' => 'ldap_hub_export_users',   // function name
    'phase' => 3,
],
```

### 5.4 Log Category/Action Mappings

| Operation | Category (folder) | Action | Where Defined |
|-----------|-------------------|--------|---------------|
| `export_hrms_ad_user_id` | `FindLogonID` | `LOGONID` | `ldap_helpers.php` |
| `get_ad_hrms_status` | `EmpStsChk` | `STS_CHK` | `ldap_helpers.php` |
| `export_ad_users` | `user_export` | `EXPORT_AD_USERS` | `ldap_helpers.php` |
| `export_group_users` | `user_export` | `EXPORT_GROUP_USERS` | `ldap_helpers.php` |
| `user_report` | `UserReport` | `USER_REPORT` | `ldap_helpers.php` |

---

## 6. Adding a New LDAP Handler

### 6.1 Step-by-Step

#### Step 1: Write the handler function

Pick the right file in `app/Ldap/Operations/`:

| If the operation is... | Add to file |
|------------------------|-------------|
| Single-user action (enable/disable/unlock/reset/modify/create) | `ldap_user_writer.php` |
| Directory/group management | `ldap_directory_writer.php` |
| Read/search (get_user_info) | `ldap_user_repository.php` |
| Bulk export/report | `ldap_hub_reports.php` |

**Function signature (MUST match):**

```php
function my_handler(array $params, string $executedBy): array
```

**Return (MUST use ldap_json_script_result):**

```php
return ldap_json_script_result([
    'success' => true|false,
    'message' => ldap_feedback_message($badge, $processed, $successCount, $failedCount),
    // Extra fields as needed:
    'results' => [...],
    'csvContent' => [...],
    'users' => [...],
    'targetUser' => $username,
], $success, $exitCode);
```

The `ldap_json_script_result()` function returns:
```php
[
    'success' => $success,
    'output' => json_encode($data),
    'exit_code' => $exitCode,
    'json_valid' => true,
    'decoded' => $data,
]
```

**When using ldap_run_with_connection (RECOMMENDED for LDAP operations):**

```php
function my_handler(array $params, string $executedBy): array
{
    return ldap_run_with_connection(function ($connection, $config) use ($params, $executedBy) {
        $baseDn = ldap_search_base_dn($config);
        // ... LDAP operations ...
        return ldap_json_script_result([...], true, 0);
    });
}
```

`ldap_run_with_connection` auto-connects, binds, and unbinds. If connect/bind fails, it returns a standardized error via `ldap_json_script_result`.

#### Step 2: Register in operation catalog

File: `app/Ldap/Operations/ldap_operation_catalog.php`

```php
'my_operation' => [
    'api_endpoint' => 'my_endpoint_name',    // controller class name
    'ps_script_key' => 'myScriptKey',         // PowerShell key (for fallback)
    'ldap_handler' => 'my_handler',           // function name in Operations/
    'phase' => 3,
],
```

#### Step 3: Enable ldap_ready

File: `config/ldap/ldap_operations.php`

```php
'ldap_ready' => [
    ...
    'my_operation' => true,
],
```

#### Step 4: Add log mappings

File: `app/Ldap/Support/ldap_helpers.php`

```php
// In ldap_script_log_category():
'my_operation' => 'MyCategory',   // folder name for logs

// In ldap_script_log_action():
'my_operation' => 'MY_ACTION',    // abbreviation for log line
```

#### Step 5: Update controller for dual output

```php
$psResult = ad_dispatch_report_operation('my_operation', $params);
$jsonValid = !empty($psResult['json_valid']);
$decoded = $psResult['decoded'] ?? null;

if ($jsonValid && is_array($decoded) && isset($decoded['results'])) {
    // LDAP path — use decoded data
    foreach ($decoded['results'] as $row) { ... }
} else {
    // PowerShell path — treat output as raw text
    $rawOutput = $psResult['output'];
    // Parse as before
}
```

### 6.2 Handler Function Checklist

- [ ] Signature: `function name(array $params, string $executedBy): array`
- [ ] Uses `ldap_run_with_connection()` for LDAP access
- [ ] Returns `ldap_json_script_result(...)` with `success`, `message`
- [ ] `message` uses `ldap_feedback_message()` for batch operations
- [ ] Input sanitized via `ldap_escape_filter_value()`
- [ ] All exception paths return proper error JSON
- [ ] Tested: `backend=ldap` + `backend=auto` + `backend=powershell`

### 6.3 Registering for Router Dispatch (vs Direct)

| Dispatch Method | Handler Requirement | Logging |
|----------------|-------------------|---------|
| **Router (Path A)** — via `execute_action.php` | Handler registered in `ldap_dispatch_execute_action()` map | Auto-logged by `ad_ldap_execute()` |
| **Hub (Path B)** — via `ad_dispatch_report_operation()` | Handler in catalog with `ldap_handler` key | Auto-logged by `ad_ldap_execute()` |
| **Direct Controller (Path C)** — inline check | Handler called directly | Controller must call `ldap_write_script_log()` inline |

---

## 7. Security Architecture

### 7.1 Credential Flow

```
LogonConfig.xml  (encrypted XML on disk)
    │
    ▼
ldap_config_repository.php → ldap_read_config()
    │ Returns array: [username, password, domain, base_dn, servers, backend, ...]
    │
    ▼
ldap_connection_factory.php → ldap_connect_and_bind()
    │ Uses DOMAIN\Username + Password for SASL NTLM bind
    │
    ▼
Active Directory (LDAPS port 636 or LDAP port 389)
```

**Security guarantees:**
- Credentials are NEVER hardcoded in PHP code
- Credentials are NEVER passed as CLI arguments (no `--password` in process list)
- Credentials are NEVER logged or echoed
- The secure config XML is stored outside webroot (`C:\inetpub\wwwroot\UM-portal` is not web-served for config files)
- No DPAPI dependency (unlike PowerShell CLIXML) — works under IIS AppPool identity

### 7.2 Input Sanitization

Every user-supplied value used in an LDAP filter MUST be escaped:

```php
use function ldap_escape_filter_value;

$safeInput = ldap_escape_filter_value($userInput);
$filter = "(&(objectClass=user)(sAMAccountName={$safeInput}))";
```

`ldap_escape_filter_value()` escapes: `( ) \ * NUL / and other LDAP special characters.

### 7.3 SQL Injection

Not applicable — 100% LDAP, no SQL. All data stored in Active Directory.

### 7.4 XSS Prevention

All handler output returned via `ldap_json_script_result()` which uses `json_encode()`.
Controllers echo `json_encode($response)` with `Content-Type: application/json`.
Browsers will not execute HTML/script in JSON responses.

### 7.5 Authentication & Authorization

- Every controller performs `session_start()` + RBAC check before dispatch
- RBAC enforced via `has_permission('action_name')` based on user role
- No handler bypasses authentication — handlers are never called without a controller

### 7.6 Rate Limiting

Bulk exports (7000+ users) may take 10-15 seconds. No explicit rate limiting.
The HTTP timeout for PHP (typically 30-60s) prevents runaway execution.

---

## 8. Previous Issues & Agent Instructions

This section is a **self-learning knowledge base** for AI agents.
Every bug that was found and fixed is documented here so the same issue never occurs again.

### Issue 1: PowerShell SecureConfigPath Required (Non-Fatal Loading)

**Symptom:** `export-group-user-list.ps1` failed under IIS with `Cannot bind argument to parameter 'SecureConfigPath' because it is null`.

**Root cause:** The script used `[CmdletBinding()]` making `$SecureConfigPath` a mandatory parameter, but IIS AppPool doesn't pass it as a CLI argument.

**Fix:** Changed parameter to `[string]$SecureConfigPath = ''` and wrapped the config-loading block in `if ($SecureConfigPath) { ... }` with a non-fatal fallback:

```powershell
if ($SecureConfigPath -and (Test-Path -LiteralPath $SecureConfigPath -ErrorAction SilentlyContinue)) {
    # load config
} else {
    # use LDAP fallback (no config needed)
}
```

**Rule for agents:** NEVER make config paths mandatory in PowerShell scripts used under IIS.
Always provide a default value and handle the missing-config case gracefully with LDAP fallback.

### Issue 2: include_secure_config Leaking via CLI Arguments

**Symptom:** `custom_export_users_message.php` and other controllers passed `include_secure_config => true` to `powershell_run_script()`. This appended the secure config path as a CLI argument, visible in process list and logs.

**Root cause:** The controller set `$options = ['include_secure_config' => true]` which made the router pass `--SecureConfigPath "C:\...\LogonConfig.xml"` to PowerShell. This exposed the full config path with username/server details in command line.

**Fix:** Removed `include_secure_config => true` from:
- `custom_export_users_message.php`
- `custom_export_group_user_list_message.php`
- `get_user_report.php`
- `export_hrms_ad_user_id_message.php`
- `get_ad_hrms_status_message.php`

The PowerShell scripts now handle config as optional (see Issue 1).

**Rule for agents:** NEVER pass `include_secure_config => true` to `powershell_run_script()`.
The secure config path should NOT be visible in process command lines.
When `backend = ldap`, the PHP handler reads the config directly (no CLI exposure).

### Issue 3: FileTime Conversion Difference (PHP vs PowerShell)

**Symptom:** `lastLogonTimestamp` values decoded incorrectly, causing wrong active/inactive classification.

**Root cause:** PowerShell uses `[DateTime]::FromFileTime($fileTime)` which converts 100-nanosecond Windows FileTime to DateTime.
PHP must replicate this manually.

**Correct PHP conversion:**

```php
$windowsEpoch = new DateTime('1601-01-01 00:00:00');
$unixTs = $windowsEpoch->getTimestamp() + (int)($fileTime / 10000000);
$dateTime = new DateTime("@{$unixTs}");
```

**Rule for agents:** Never use `date()` for FileTime conversion.
Always use the 3-line formula above with `1601-01-01` Windows epoch.
Test with known values: FileTime `133648046396875000` = 2025-07-21 (verified).

### Issue 4: log Category/Action Mismatch

**Symptom:** Dashboard log reader shows no entries for Hub operations, or entries in wrong category/action.

**Root cause:** New operation keys were added to catalog but corresponding entries in `ldap_script_log_category()` and `ldap_script_log_action()` were missing.

**Fix:** Every new operation MUST have entries in BOTH mapping functions in `ldap_helpers.php`.

**Rule for agents:** When adding a new LDAP handler:
1. Add to `ldap_script_log_category()` — folder name (MUST match PowerShell folder)
2. Add to `ldap_script_log_action()` — action abbreviation (MUST match PowerShell action)
3. Verify both mappings before testing

### Issue 5: `distinguishedname` is an Array, Not a String

**Symptom:** `explode(',', $entry['distinguishedname'])` throws `explode(): Argument #2 ($string) must be of type string, array given`.

**Root cause:** `ldap_normalize_entry()` stores the entry's DN in `$entry['dn']` (string), while `$entry['distinguishedname']` is a regular attribute (array with one element).

```php
// CORRECT — use 'dn' key (string)
$dn = $entry['dn'] ?? '';

// WRONG — 'distinguishedname' is an array
$dn = $entry['distinguishedname'] ?? '';
```

**Fix:** Always use `$entry['dn']` to get the distinguished name string.
Use `ldap_first_attr($entry, 'distinguishedname')` if you need it from the attribute array.

**Rule for agents:** After `ldap_normalize_entry()`, the DN is in `$entry['dn']` as a string.
All other attributes are arrays indexed `[0]`, `[1]`, etc.
Never call `explode()` or `strpos()` on `$entry['distinguishedname']` directly.

### Issue 6: Timezone Mismatch in Log Filenames

**Symptom:** Log files written with yesterday's date; dashboard shows operations under wrong date or missing.

**Root cause:** PHP's `date('Y-m-d')` uses server timezone (UTC), but dashboard reads logs in `Asia/Dhaka` (UTC+6).

**Fix:** `ldap_write_script_log()` uses `new DateTime('now', new DateTimeZone('Asia/Dhaka'))` for both filename and timestamp.

**Rule for agents:** Never use `date()` for log filenames or timestamps.
Always create a `DateTime` object with explicit `'Asia/Dhaka'` timezone:
```php
$now = new DateTime('now', new DateTimeZone('Asia/Dhaka'));
$logFile = 'audit-' . $now->format('Y-m-d') . '.log';
```

### Issue 7: Feedback Message Missing Badge or Summary

**Symptom:** UI feedback card shows only "Processed: 1 | Success: 1" without the badge/explanation line.

**Root cause:** Handler returned a raw string as `message` instead of using `ldap_feedback_message()` which generates `badge + \n\n + summary`.

**Fix:** Always use `ldap_feedback_message($badge, $processed, $successCount, $failedCount, $skippedCount)` for batch operations.

**Rule for agents:** Every batch operation's `decoded.message` MUST contain BOTH:
1. Per-user badge line (e.g., `SUCCESS: User '59023' unlocked.`)
2. Summary line (e.g., `Processed: 1 | Success: 1 | Skipped: 0 | Failed: 0`)

Separated by a blank line (`\n\n`). Use `ldap_feedback_message()` to generate this.

### Issue 8: Controller Expects Raw CSV but Gets JSON

**Symptom:** `custom_export_group_user_list_message.php` stores `$_SESSION['custom_ad_user_list_csv'] = $psOutput;` but LDAP returns JSON, not CSV.

**Root cause:** The controller was written for PowerShell which outputs raw CSV text.
LDAP handler returns JSON with `csvContent` array.

**Fix:** Check `$psResult['json_valid']` flag:

```php
$jsonValid = !empty($psResult['json_valid']);
$decoded = $psResult['decoded'] ?? null;

if ($jsonValid && is_array($decoded) && isset($decoded['csvContent'])) {
    // LDAP path — JSON with csvContent array
    $psOutput = implode("\n", $decoded['csvContent']);
} else {
    // PowerShell path — raw CSV
    $psOutput = $psResult['output'];
}
$_SESSION['custom_ad_user_list_csv'] = $psOutput;
```

**Rule for agents:** Every Hub controller must handle dual output (JSON from LDAP, raw text from PowerShell).
Use the `json_valid` flag pattern above. Never assume the output format.

### Issue 9: Auto-Fallback Fails When LDAP Handler Returns Unexpected Format

**Symptom:** `backend = auto` falls back to PowerShell even when LDAP handler succeeded.

**Root cause:** The router checks `$ldapResult['success']` to decide fallback. If the handler returns the LDAP result array incorrectly, `success` may be missing.

**Fix:** The handler must return via `ldap_json_script_result()` which sets `success`, `output`, `decoded`, `json_valid`, `exit_code` consistently.

**Rule for agents:** Always use `ldap_json_script_result()` as the return mechanism.
Never construct `['success' => ..., 'output' => ...]` manually — use the helper.
The router relies on the structure being consistent.

### Issue 10: PowerShell Script Uses AD Module Under IIS (No Credential)

**Symptom:** Under IIS, `Get-ADUser` fails with "The authentication mechanism is unknown" or Kerberos errors.

**Root cause:** IIS AppPool identity cannot decrypt DPAPI CLIXML credential files.
Without `-Credential`, AD module uses the process token which fails for IIS.

**Fix:** The `ldap_ad_helpers.ps1` library provides transparent fallback to .NET `DirectoryServices` (LDAP on port 389) when credentials are unavailable:

```powershell
function Get-ADUserViaLDAP {
    if ($Credential -and (Get-Module -Name ActiveDirectory)) {
        return Get-ADUser ...  # CLI path
    }
    # IIS path: use DirectorySearcher
    $searcher = [System.DirectoryServices.DirectorySearcher]::new()
    ...
}
```

**Rule for agents:** PowerShell scripts that run under IIS must use `ldap_ad_helpers.ps1` functions instead of direct `Get-AD*` cmdlets.
Scripts with mandatory `-Credential` parameters must have null-value handling.

### Issue 11: OU Path Parsing Returns Empty or Raw DN

**Symptom:** User info page shows blank OU field or full DN instead of OU name.

**Root cause:** `ldap_parse_ou_from_dn()` in `ldap_helpers.php` was using incorrect regex.

**Correct OU extraction from DN:**

```php
// Extract first OU closest to CN
preg_match('/^CN=[^,]*,OU=([^,]+)/i', $dn, $matches)
// $matches[1] = first OU name

// Extract full OU hierarchy
$ouParts = [];
foreach (explode(',', $dn) as $p) {
    if (stripos($p, 'OU=') === 0) {
        $ouParts[] = substr($p, 3);
    }
}
$fullPath = implode(' > ', array_reverse($ouParts));
```

**Rule for agents:** Use `$entry['dn']` for the DN string (not `distinguishedname` attribute).
Extract OU by parsing the DN string with explode/strpos, not regex on the full DN.
The DN format is: `CN=User,OU=Section,OU=Department,DC=domain,DC=com`.

### Issue 12: `ldap_ready` Not Set After Adding Handler

**Symptom:** New handler function exists and is registered in catalog, but router always falls back to PowerShell.

**Root cause:** Missing entry in `config/ldap/ldap_operations.php` → `$ldapOperations['ldap_ready']`.

**Fix:** Every operation with a handler needs:
```php
'my_operation' => true,
```

**Rule for agents:** After writing a handler and registering in catalog, ALWAYS check `config/ldap/ldap_operations.php`.
If `ldap_ready[my_operation]` is missing or `false`, the router ignores the handler.

---

## 9. Key Files Reference

### Core LDAP Module

| File | Purpose |
|------|---------|
| `app/Ldap/Connection/ldap_connection_factory.php` | Connect/bind/unbind to AD via LDAP |
| `app/Ldap/Support/ldap_helpers.php` | `ldap_json_script_result()`, `ldap_feedback_message()`, `ldap_write_script_log()`, `ldap_paged_search()`, `ldap_normalize_entry()`, `ldap_escape_filter_value()` |
| `app/Ldap/Support/ldap_config_repository.php` | Read/write `LogonConfig.xml` secure config |
| `app/Ldap/Support/ldap_response_adapter.php` | Normalize handler output for UI |
| `app/Ldap/Router/ad_operation_router.php` | Backend resolution, dispatch, auto-logging |
| `app/Ldap/Operations/ldap_operation_catalog.php` | Operation → handler function registry |
| `config/ldap/ldap_operations.php` | Per-operation `ldap_ready` flags |

### Handler Files

| File | Handlers | Operations |
|------|----------|------------|
| `app/Ldap/Operations/ldap_user_writer.php` | `set_enabled`, `unlock`, `reset_password`, `update`, `create` | enable, disable, unlock, reset, modify, create |
| `app/Ldap/Operations/ldap_directory_writer.php` | `list_ous`, `create`, `delete`, `sync_members` | list OUs, create/delete OU/group, group sync |
| `app/Ldap/Operations/ldap_user_repository.php` | `get_info` | get_user_info |
| `app/Ldap/Operations/ldap_hub_reports.php` | `map_hrms_user_id`, `check_hrms_status`, `export_users`, `user_report` | Mapping, Sync, Users, Reports |

### Controller Files

| Controller | Operation | Dispatch Path |
|------------|-----------|---------------|
| `execute_action.php` | enable, disable, unlock, reset, create | Router (Path A) |
| `modify_ad_user.php` | modify_user | Direct (Path C) |
| `manual_create_user.php` | create_user | Direct (Path C) |
| `update_group_members.php` | set_group_members | Direct (Path C) |
| `create_directory_object.php` | create_directory_object | Direct (Path C) |
| `delete_directory_object.php` | delete_directory_object | Direct (Path C) |
| `export_hrms_ad_user_id_message.php` | export_hrms_ad_user_id | Hub (Path B) |
| `get_ad_hrms_status_message.php` | get_ad_hrms_status | Hub (Path B) |
| `custom_export_users_message.php` | export_ad_users | Hub (Path B) |
| `custom_export_group_user_list_message.php` | export_group_users | Hub (Path B) |
| `get_user_report.php` | user_report | Hub (Path B) |
| `get_ad_health_check_message.php` | ad_health_check | Hub (Path B) |

### Config Files

| File | Purpose |
|------|---------|
| `config/ldap/ldap_operations.php` | Enable/disable LDAP per operation |
| `config/ldap/LogonConfig.xml` | Encrypted AD credentials + backend mode |
| `config/ldap/Plan_Port_IntelligenceHub_to_LDAP.md` | Porting plan (historical, all complete) |
| `config/ldap/LDAP_vs_PowerShell_Feasibility.md` | Performance comparison study |

### PowerShell Fallback Scripts

| Script | Operation |
|--------|-----------|
| `scripts/powershell/ldap_ad_helpers.ps1` | Shared LDAP fallback functions for PS scripts |
| `scripts/powershell/export-group-user-list.ps1` | Users/Group export |
| `scripts/powershell/get-user-report.ps1` | User report |
| `scripts/powershell/export-hrms-ad-login-id.ps1` | Mapping |
| `scripts/powershell/check-ad-hrms-status.ps1` | Sync |
| `scripts/powershell/health-check.ps1` | Health check |

---

## Quick Troubleshooting

| Problem | Likely Cause | Fix |
|---------|-------------|-----|
| Operation always falls back to PowerShell | `ldap_ready` is `false` or missing | Set to `true` in `ldap_operations.php` |
| Log files not written | `ldap_script_log_category()` missing entry | Add `'my_op' => 'Category'` in `ldap_helpers.php` |
| Wrong log action in file | `ldap_script_log_action()` missing entry | Add `'my_op' => 'ACTION'` in `ldap_helpers.php` |
| `explode() expects string, array given` | Used `distinguishedname` instead of `dn` | Use `$entry['dn']` for DN string |
| Feedback shows only summary | Not using `ldap_feedback_message()` | Wrap badge in `ldap_feedback_message()` |
| Dashboard logs wrong date | Timezone not set to `Asia/Dhaka` | Use `DateTime('now', 'Asia/Dhaka')` |
| Handler not found | Not registered in catalog | Add to `ldap_operation_catalog.php` |
| CLI shows config path | `include_secure_config` still `true` | Remove the option from controller |
