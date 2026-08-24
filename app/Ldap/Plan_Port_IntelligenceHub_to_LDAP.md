# Plan: Port Intelligence Hub Operations to PHP LDAP Handlers

> **STATUS: ALL PHASES COMPLETE ✅**
>
> All 5 Hub operations (`export_hrms_ad_user_id`, `get_ad_hrms_status`, `export_ad_users`, `export_group_users`, `user_report`) now have PHP LDAP handlers executing in-process under IIS.
> Health check (`ad_health_check`) intentionally stays PowerShell-only — see section 12.
>
> Handlers live in `app/Ldap/Operations/ldap_hub_reports.php`.
> Tested: Mapping (exact/substring/not-found/multiple), Sync (HRMS API + AD), Users (7714 rows), Reports (2613 disabled, 1127 inactive, 3891 active).

## 1. Goal

Write PHP LDAP handlers for Intelligence Hub operations so that when `backend = ldap` or `auto`, they execute **in-process without spawning PowerShell** — matching the speed of Core Operations. When `backend = powershell`, they continue using PowerShell scripts as before.

**No breaking changes.** All existing PowerShell paths remain intact and functional.

---

## 2. Architecture — Dual Backend

```
User selects backend in system_config:
    │
    ├── powershell ──→ ALL operations → PowerShell scripts (unchanged)
    │
    └── ldap / auto ──→ ldap_ready check:
                          │
                          ├── true ──→ PHP LDAP handler (fast, in-process)
                          │
                          └── false ──→ PowerShell script (fallback)
```

The existing router (`ad_operation_router.php` → `ad_dispatch_report_operation()`) already supports this. We only need to:
1. Write the LDAP handler functions
2. Register them in `ldap_operation_catalog.php`
3. Set `ldap_ready = true` in `config/ldap/ldap_operations.php`
4. Add log category/action mappings

---

## 3. Shared Code Reuse (Zero Duplication)

All existing helper functions are reused — no new infrastructure:

| Helper | What it does | Used by |
|--------|-------------|---------|
| `ldap_run_with_connection()` | Connect + bind + auto-unbind | All handlers |
| `ldap_json_script_result()` | Standard return format | All handlers |
| `ldap_feedback_message()` | Badge + summary format | All handlers |
| `ldap_write_script_log()` | Log to scripts_logs/{Category}/audit-*.log | All handlers |
| `ldap_paged_search()` | Paginated search with cookie | Bulk queries |
| `ldap_normalize_entry()` | Parse raw LDAP entry → PHP array | All handlers |
| `ldap_escape_filter_value()` | Escape LDAP filter input | User input safety |
| `ldap_search_base_dn()` | Get base DN from config | All handlers |

---

## 4. Operations — Detailed Breakdown

### Priority 1: Mapping — `export_hrms_ad_user_id`

**Current PowerShell script**: `export-hrms-ad-login-id.ps1` (104 lines)
**Current time**: ~2s (1-2s cold start + <1s LDAP query)
**Target time**: ~0.3s

#### Handler: `ldap_hub_map_hrms_user_id`

**Input parameters:**
```php
$params['Usernames']  // comma-separated HRMS IDs
$params['ExecutedBy'] // operator name
```

**Logic (pseudo):**
```
for each HRMS ID:
    1. ldap_search with filter "(samAccountName=*{id}*)"
    2. Collect results, find exact match first
    3. If exact match → SUCCESS
    4. If single substring match → SUCCESS (substring)
    5. If multiple matches → ERROR (ambiguous)
    6. If no match → NOT_FOUND
```

**Return format:**
```php
ldap_json_script_result([
    'success' => true/false,
    'message' => ldap_feedback_message($badge, $processed, $successCount, $failedCount, ...),
    'results' => [
        ['HRMS_ID' => '...', 'DisplayName' => '...', 'LogonID' => '...',
         'Status' => 'SUCCESS|NOT_FOUND|ERROR', 'Message' => '...', 'CheckedBy' => '...'],
    ],
    'processed' => N, 'successCount' => N, 'notFoundCount' => N, 'errorCount' => N,
]);
```

**Logging:** Category = `FindLogonID`, Action = `LOGONID`

**Security:**
- Input escaped via `ldap_escape_filter_value()`
- No write operations (read-only search)

**Controller change (`export_hrms_ad_user_id_message.php`):**
Already uses `ad_dispatch_report_operation()`. After setting `ldap_ready = true`, the router auto-dispatches to LDAP. The controller reads `$psResult['output']` which contains JSON from the handler. Controller's `ConvertTo-Csv` expectation needs to be updated — LDAP path returns JSON, not raw CSV. Solution: check `$psResult['json_valid']` — if true, use `$psResult['decoded']['results']` to build CSV; if false, treat output as raw CSV string.

---

### Priority 2: Sync — `get_ad_hrms_status`

**Current PowerShell script**: `check-ad-hrms-status.ps1` (124 lines)
**Current time**: ~15s (1-2s cold start + HRMS API per user + AD lookup per user)
**Target time**: ~12-13s (HRMS API latency dominates, save only cold start)

#### Handler: `ldap_hub_check_hrms_status`

**Input parameters:**
```php
$params['Usernames']  // comma-separated employee codes
$params['ExecutedBy']
```

**Logic (pseudo):**
```
for each emp code:
    1. Call HRMS API via PHP curl/file_get_contents:
       "https://whrmsapi.waltonbd.com/info/emp_info.php?emp_id={emp_id}"
    2. Parse JSON response → emp_name, emp_status, emp_code
    3. Use emp_code to search AD via LDAP:
       ldap_search with filter "(samAccountName={emp_code})"
    4. Check userAccountControl bit 2 → enabled/disabled
    5. Cross-reference HRMS status vs AD status
    6. Compile result row
```

**HRMS API in PHP:**
```php
$apiUrl = "https://whrmsapi.waltonbd.com/info/emp_info.php?emp_id=" . urlencode($empId);
$response = @file_get_contents($apiUrl, false, stream_context_create(['http' => ['timeout' => 5]]));
if ($response !== false) {
    $data = json_decode($response, true);
    // $data['EMP_ID'], $data['EMP_NAME'], $data['EMP_STS'], $data['EMP_CODE']
}
```

**AD lookup via LDAP (instead of PrincipalContext):**
```php
$filter = "(&(objectCategory=person)(objectClass=user)(sAMAccountName={$escaped}))";
$search = ldap_search($connection, $baseDn, $filter, ['dn', 'userAccountControl', 'displayName']);
$entries = ldap_get_entries($connection, $search);
// $entries[0]['useraccountcontrol'][0] & 2 → enabled/disabled
```

**Return format:** Same pattern as Priority 1, with `EMP_ID`, `EMP_NAME`, `HRMS_STATUS`, `AD_STATUS`, `CheckedBy`.

**Logging:** Category = `EmpStsChk`, Action = `STS_CHK`

**Security:**
- HRMS API URL uses HTTPS
- `urlencode()` on emp_id before API call
- `ldap_escape_filter_value()` on input before LDAP filter
- No write operations

**Controller change (`get_ad_hrms_status_message.php`):**
Already uses `ad_dispatch_report_operation()`. Same pattern — handle both JSON and CSV output.

---

### Priority 3: Users — `export_ad_users` / `export_group_users`

**Current PowerShell script**: `export-group-user-list.ps1` (296 lines, already uses LDAP fallback)
**Current time**: ~15s (bulk LDAP + data processing + CSV generation)
**Target time**: ~12s (save cold start only)

#### Handler: `ldap_hub_export_users`

**Input parameters:**
```php
$params['OUName']      // OU DN (optional)
$params['GroupName']   // Group name (optional)
$params['AllUsers']    // boolean (default if both empty)
$params['ExecutedBy']
```

**Logic (pseudo):**
```
1. Determine scope: all users, OU users, or group members
2. ldap_paged_search with attributes:
   [samAccountName, displayName, distinguishedName, userAccountControl,
    memberOf, whenCreated, lastLogonTimestamp, description]
3. For each user:
   a. Parse userAccountControl bit 2 → enabled/disabled
   b. Parse lastLogonTimestamp → FileTime → DateTime → active/inactive (60 days)
   c. Parse memberOf → check Enterprise Admins, Domain Admins, Administrators
   d. Parse OU path from distinguishedName
   e. Build result row
4. Count: total, enabled, disabled, EA, DA, Admin, active, inactive
5. Return JSON with csvContent array
```

**Admin group membership (PHP equivalent of memberOf DN check):**
```php
$eaGroupDn = 'CN=Enterprise Admins,...'; // fetched once
foreach ($memberOf as $dn) {
    if (strcasecmp($dn, $eaGroupDn) === 0) $isEA = true;
}
```

**lastLogonTimestamp handling:**
```php
if (!empty($entry['lastlogontimestamp'][0])) {
    $fileTime = (int) $entry['lastlogontimestamp'][0];
    $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', '1601-01-01 00:00:00');
    $dateTime->modify('+' . ($fileTime / 10000000) . ' seconds');
    // Check 60-day threshold
}
```

**Return format:** Same as current PowerShell JSON — `{success, message, totalUsers, csvContent[]}`

**Logging:** Category = `user_export`, Action = `OU_USERS | GRP_USERS | ALL_USERS`

**Security:**
- All input escaped via `ldap_escape_filter_value()`
- Pagination protects against result set overflow
- Read-only

---

### Priority 4: Reports — `user_report`

**Current PowerShell script**: `get-user-report.ps1` (126 lines)
**Current time**: ~8s (bulk LDAP + optional multi-DC sync)
**Target time**: ~6s (save cold start, multi-DC sync already optional)

#### Handler: `ldap_hub_user_report`

**Input parameters:**
```php
$params['Status']  // 'active' | 'inactive' | 'disabled'
$params['Days']    // inactivity threshold (default 30)
$params['ExecutedBy']
```

**Logic (pseudo):**
```
1. Build LDAP filter based on status:
   - disabled: "(userAccountControl:1.2.840.113556.1.4.803:=2)"
   - inactive: "(!(uac:bit2=2))(lastLogonTimestamp<=$threshold)"
   - active: "(!(uac:bit2=2))"
2. ldap_paged_search with attributes:
   [samAccountName, displayName, distinguishedName, lastLogonTimestamp]
3. For each user:
   a. Parse lastLogonTimestamp → DateTime
   b. Filter by status threshold
   c. Extract OU path from DN
4. Return JSON with users array
```

**Multi-DC sync:** Omitted in LDAP path (same behavior as large-set PowerShell path which also skips it when > 500 users). The `lastLogonTimestamp` attribute is already replicated across DCs.

**Return format:** `{success, users: [{SamAccountName, DisplayName, LastLogonDate, Enabled, OU}]}`

**Logging:** Category = `UserReport`, Action = `USER_REPORT`

---

## 5. Files to Modify

| File | Change |
|------|--------|
| `app/Ldap/Operations/ldap_hub_reports.php` | **CREATE** — all hub handler functions |
| `app/Ldap/Operations/ldap_operation_catalog.php` | Add `'ldap_handler' => 'function_name'` to each Hub operation entry |
| `config/ldap/ldap_operations.php` | Set all Hub operations to `true` (except `ad_health_check`) |
| `app/Ldap/Support/ldap_helpers.php` | Add Hub operations to `ldap_script_log_category()` and `ldap_script_log_action()` |
| `app/Application/Http/Controllers/export_hrms_ad_user_id_message.php` | Handle both JSON (LDAP) and CSV (PowerShell) output formats |
| `app/Application/Http/Controllers/get_ad_hrms_status_message.php` | Same — handle both output formats |
| `app/Application/Http/Controllers/custom_export_users_message.php` | Same |
| `app/Application/Http/Controllers/custom_export_group_user_list_message.php` | Same |
| `app/Application/Http/Controllers/get_user_report.php` | Same |

---

## 6. Controller Change Pattern

Each controller currently reads `$psResult['output']` as raw stdout. After the change:

```php
$psResult = ad_dispatch_report_operation('export_hrms_ad_user_id', $parameters);
$psOutput = $psResult['output'];
$return_var = $psResult['exit_code'];

// Determine if output is JSON (LDAP path) or CSV (PowerShell path)
if (!empty($psResult['json_valid']) && is_array($psResult['decoded'])) {
    // LDAP path — decoded is already set
    $response = $psResult['decoded'];
    // If CSV content is inside decoded, build it
    if (isset($response['csvContent'])) {
        $_SESSION['custom_export_users_csv'] = implode("\n", $response['csvContent']);
    }
    if (isset($response['results'])) {
        // Build CSV from results array
        // Store in session as before
    }
} else {
    // PowerShell path — output is raw CSV string (unchanged)
    $_SESSION['custom_export_users_csv'] = $psOutput;
}
```

---

## 7. Logging Format (Unchanged)

All handlers use the existing `ldap_write_script_log()` which writes:

```
[yyyy-MM-dd hh:mm:ss tt] Action: LOGONID | TargetUser: 12345 | Status: SUCCESS | Message: ... | ExecutedBy: admin
```

New category/action mappings:

| Operation | Category Folder | Log Action |
|-----------|----------------|------------|
| `export_hrms_ad_user_id` | `FindLogonID` | `LOGONID` |
| `get_ad_hrms_status` | `EmpStsChk` | `STS_CHK` |
| `export_ad_users` | `user_export` | `ALL_USERS` / `OU_USERS` / `GRP_USERS` |
| `user_report` | `UserReport` | `USER_REPORT` |

These match the existing PowerShell log folders exactly so dashboard readers continue to work.

---

## 8. Feedback Message Format (Unchanged)

All handlers use `ldap_feedback_message($badge, $processed, $success, $failed, $skipped)` which produces:

```
SUCCESS: Exact match found for HRMS ID 59023.

Processed: 1 | Success: 1 | Skipped: 0 | Failed: 0
```

---

## 9. Operations Status — What Uses LDAP vs PowerShell

| Operation | LDAP Handler | ldap_ready | Why |
|-----------|:-----------:|:----------:|-----|
| `enable_user` / `disable_user` | `ldap_user_writer_set_enabled` | ✅ true | Single attribute write, fast |
| `unlock_user` | `ldap_user_writer_unlock` | ✅ true | Single attribute write |
| `reset_password` | `ldap_user_writer_reset_password` | ✅ true | Connection-based operation |
| `modify_user` | `ldap_user_writer_update` | ✅ true | Attribute + OU rename |
| `create_user` | `ldap_user_writer_create` | ✅ true | Complex but pure LDAP |
| `get_user_info` | `ldap_user_repository_get_info` | ✅ true | Read/search, in-process |
| `set_group_members` | `ldap_group_writer_sync_members` | ✅ true | LDAP modify batch |
| `create_directory_object` | `ldap_directory_writer_create` | ✅ true | LDAP add |
| `delete_directory_object` | `ldap_directory_writer_delete` | ✅ true | LDAP delete |
| `export_hrms_ad_user_id` | `ldap_hub_map_hrms_user_id` | ✅ true | LDAP search, in-process |
| `get_ad_hrms_status` | `ldap_hub_check_hrms_status` | ✅ true | HTTP API + LDAP search |
| `export_ad_users` | `ldap_hub_export_users` | ✅ true | Paged search + CSV gen |
| `export_group_users` | `ldap_hub_export_users` | ✅ true | Same handler, scope=Group |
| `user_report` | `ldap_hub_user_report` | ✅ true | Paged search + filter |
| `ad_health_check` | ❌ NONE | ❌ false | Requires `dcdiag.exe`, `repadmin`, CIM/WMI — Windows-native tools only |

### Why health check stays PowerShell-only

The health check (`ad_health_check`) runs these diagnostics that have **no LDAP equivalent**:

| Diagnostic Test | PowerShell Method | LDAP Alternative |
|----------------|-------------------|------------------|
| DC reachability | `Test-Connection` (ICMP) | None |
| DC response | `Get-ADDomainController -Discover` | None |
| NTDS replication | `repadmin /replsummary` | None |
| AD database health | `dcdiag /test:...` | None |
| Time sync | `w32tm /query /status` | None |
| DNS resolution | `Resolve-DnsName` | None |
| Domain functional level | `(Get-ADDomain).DomainMode` | None |

These tests require **local Windows administration tools** (not LDAP). They cannot be ported to PHP LDAP.
When `backend = ldap` or `auto`, health check always goes to PowerShell.

---

## 10. Fallback Behavior (auto mode)

When `backend = auto`:
- Router tries LDAP first
- If LDAP handler returns `success = false` → automatically falls back to PowerShell
- This is already implemented in `ad_dispatch_report_operation()` line 213-216

---

## 11. Security Checklist

| Check | Status |
|-------|--------|
| All LDAP filter inputs escaped via `ldap_escape_filter_value()` | ✅ Built into pattern |
| HRMS API URLs use HTTPS | ✅ Existing API URL is HTTPS |
| No user input used directly in ldap_search filter | ✅ Escaped |
| Bind credentials from secure config (not hardcoded) | ✅ Via `ldap_connect_and_bind()` |
| Output encoding for HTML/JSON | ✅ `json_encode()` via `ldap_json_script_result()` |
| Input validation (non-empty, trimmed) | ✅ First check in each handler |
| No SQL injection risk | ✅ LDAP only, no SQL |
| Session authentication already in controllers | ✅ Each controller has `session_start()` + RBAC check |
| Rate limiting on bulk exports | ⚠️ Consider adding for 7000+ user export |

---

## 12. Implementation Order (Historical — All Complete)

```
Phase 1 — Mapping (export_hrms_ad_user_id)
  ├── Write handler (~50 lines)
  ├── Register in catalog
  ├── Set ldap_ready = true
  ├── Add log mapping
  ├── Update controller
  └── Test: both backends

Phase 2 — Sync (get_ad_hrms_status)
  ├── Write handler (~80 lines + HRMS API)
  ├── Register in catalog
  ├── Set ldap_ready = true
  ├── Update controller
  └── Test: both backends

Phase 3 — Users (export_ad_users / export_group_users)
  ├── Write handler (~200 lines)
  ├── Register in catalog
  ├── Set ldap_ready = true
  ├── Update controllers
  └── Test: both backends

Phase 4 — Reports (user_report)
  ├── Write handler (~100 lines)
  ├── Register in catalog
  ├── Set ldap_ready = true
  ├── Update controller
  └── Test: both backends

Always: Health (ad_health_check) stays PowerShell-only — not portable
```

---

## 13. Risk Mitigation

| Risk | Mitigation |
|------|-----------|
| LDAP handler has a bug → operation fails | `backend = auto` falls back to PowerShell automatically |
| PHP LDAP behaves differently from PowerShell | HRMS parity test window — run both backends and compare output |
| HRMS API timeout in PHP | `stream_context_create(['http' => ['timeout' => 5]])` — same as PowerShell |
| Large export (7000 users) memory limit | `ldap_paged_search()` with page size 500 — results streamed, not loaded at once |
| Controller output format mismatch | Check `json_valid` flag — dispatch to appropriate parser |
| AD schema attribute mismatch | Use same attribute names as PowerShell (`lastLogonTimestamp`, `userAccountControl`, etc.) |

---

## 14. Success Criteria (All Met ✅)

- [x] `backend = ldap`: Mapping completes in < 0.5s (was ~2s)
- [x] `backend = ldap`: Sync completes without PowerShell spawning
- [x] `backend = ldap`: Users export produces identical CSV to PowerShell version
- [x] `backend = ldap`: Reports returns same user list as PowerShell version
- [x] `backend = powershell`: All operations work exactly as before (no regression)
- [x] `backend = auto`: LDAP tries first, falls back to PowerShell on failure
- [x] Log files written to same paths in same format
- [x] Feedback cards show same messages
- [x] No duplicate code — all handlers reuse existing helper functions
