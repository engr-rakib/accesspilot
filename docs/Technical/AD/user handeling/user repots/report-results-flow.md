# Report Results — Full Flow (UI → JS → PHP → PowerShell/LDAP → Display)

## 1. Trigger Button

**File:** `resources/views/components/sidebar_actions.php` (line 115-118)

```html
<button type="button" id="userReportButton" class="btn action-button btn-reports-action flex-fill">
    <i class="fas fa-file-invoice me-1"></i> Reports
</button>
```

- Permission: `has_permission('action_user_report')`

## 2. Report Card (form + results table)

**File:** `resources/views/components/global/user_report_card.php`

Hidden by default (`display: none` on `#userReportCardContainer`).

| Element ID | Type | Purpose |
|---|---|---|
| `#userStatus` | `<select>` | `active`, `inactive`, `disabled` |
| `#reportDays` | `<select>` | `15`, `30`, `45`, `60`, `90`, `custom` |
| `#customDays` | `<input>` | Appears when "Custom Days" selected |
| `#submitUserReport` | `<button>` | Submits the report request |
| `#userReportResults` | `<div>` | Results area (hidden initially) |
| `#userReportTbody` | `<tbody>` | Table body for user rows |
| `#downloadUserReport` | `<button>` | Download CSV (client-side) |
| `#disableAllInactive` | `<button>` | Bulk disable (inactive only) |

## 3. JS — Toggle Card

**File:** `public/resources/frontend/js/admin/user_report_actions.js` (line 62-71)

```javascript
userReportButton.addEventListener('click', () => {
    const container = document.getElementById('userReportCardContainer');
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
});
```

## 4. JS — Submit Report

**File:** `public/resources/frontend/js/admin/user_report_actions.js` (line 101-136)

```javascript
submitBtn.addEventListener('click', async () => {
    const status = document.getElementById('userStatus').value;
    const days = customDays.value || reportDays.value;
    const res = await fetch(apiBase + '?endpoint=get_user_report', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `status=${status}&days=${days}`
    });
    const data = await res.json();
    if (data.success) renderResults(data.users, status);
});
```

## 5. API Router

**File:** `public/api/index.php`

```php
'get_user_report' => 'get_user_report.php'
```

## 6. PHP Controller

**File:** `app/Application/Http/Controllers/get_user_report.php`

```php
$psResult = ad_dispatch_report_operation('user_report', [
    'Status' => $status,
    'Days' => $days,
    'ExecutedBy' => $loggedInUser,
]);
echo json_encode($psResult);
```

## 7. Router / Backend Dispatch

**File:** `app/Ldap/Router/ad_operation_router.php` — `ad_dispatch_report_operation()` (line 203)

1. Looks up `'user_report'` in operation catalog
2. Calls `ad_resolve_backend('user_report')` → decides PowerShell vs LDAP
3. If **PowerShell**: calls `powershell_run_script('user_report', ...)`
4. If **LDAP**: calls `ad_ldap_execute('user_report', ...)` → `ldap_hub_user_report()`

### Operation Catalog Entry

**File:** `app/Ldap/Operations/ldap_operation_catalog.php` (line 135-140)

```php
'user_report' => [
    'api_endpoint' => 'get_user_report',
    'ps_script_key' => 'user_report',
    'ldap_handler' => 'ldap_hub_user_report',
    'phase' => 1,
],
```

## 8. PowerShell Path

### Script Mapping

**File:** `config/powershell.php` (line 41)

```php
'user_report' => $powershellRoot . '/get-user-report.ps1',
```

### PowerShell Script

**File:** `scripts/powershell/get-user-report.ps1`

**Parameters:** `SecureConfigPath`, `Status`, `Days`, `ExecutedBy`

**Logic:**
1. Dot-sources `ldap_ad_helpers.ps1`
2. Builds LDAP filter based on status:
   - `disabled`: `(&(objectClass=user)(userAccountControl:1.2.840.113556.1.4.803:=2))`
   - `inactive`: `(&(objectClass=user)(!(userAccountControl:1.2.840.113556.1.4.803:=2))(lastLogonTimestamp<=threshold))`
   - `active`: `(&(objectClass=user)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))`
3. Uses `[adsisearcher]` with filter and `PageSize = 2000`
4. Calls `$searcher.FindAll()` (line 44)
5. For <= 500 users, contacts each DC for accurate `lastLogon` (lines 67-89)
6. Builds result array and outputs JSON: `{ success: true, users: [...] }`

### PowerShell Runner

**File:** `app/Infrastructure/PowerShell/powershell_runner.php`

- `powershell_run_script()` (line 86) — builds command via `powershell_build_command()`, calls `exec()` (line 193)
- `exec($command, $output, $return_var)` — **no timeout**, waits indefinitely
- Output encoding converted to UTF-8 (line 114)

## 9. LDAP PHP Path

### LDAP Handler

**File:** `app/Ldap/Operations/ldap_hub_reports.php` — `ldap_hub_user_report()` (line 553-641)

```php
function ldap_hub_user_report($params, $executedBy) {
    $connection = ldap_run_with_connection(); // connect + bind
    $filter = build_filter_based_on_status($status, $days);
    $users = ldap_paged_search($connection, $baseDn, $filter, $attributes, 1000);
    // format each user
    return ldap_json_script_result(['success' => true, 'users' => $formatted]);
}
```

### ldap_paged_search()

**File:** `app/Ldap/Support/ldap_helpers.php` (line 392-436)

Uses `LDAP_CONTROL_PAGEDRESULTS` cookie loop for paging:
```php
ldap_set_option($connection, LDAP_CONTROL_PAGEDRESULTS, ['size' => $pageSize]);
while (ldap_search($connection, $baseDn, $filter, $attrs)) {
    // collect results, update cookie
}
```

## 10. Result Rendering (JS)

**File:** `public/resources/frontend/js/admin/user_report_actions.js` — `renderResults()` (line 138-168)

```javascript
function renderResults(users, status) {
    users.forEach(u => {
        const row = `<tr>
            <td>${u.SamAccountName}</td>
            <td>${u.DisplayName}</td>
            <td>${u.LastLogonDate}</td>
            <td><span class="status-badge ${u.Enabled ? 'active' : 'inactive'}">${u.Enabled ? 'Enabled' : 'Disabled'}</span></td>
            <td>${u.OU}</td>
        </tr>`;
        tbody.innerHTML += row;
    });
    document.getElementById('userReportResults').style.display = 'block';
}
```

## 11. Download CSV (Client-Side)

**File:** `public/resources/frontend/js/admin/user_report_actions.js` (line 28-59)

**No API call.** CSV is built from the visible table DOM:
```javascript
downloadBtn.addEventListener('click', () => {
    const rows = Array.from(document.querySelectorAll('#userReportTable tr'));
    const csv = rows.map(r => Array.from(r.cells).map(c => c.textContent).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = 'report.csv'; a.click();
});
```

## Complete File Map

| Layer | File | Key Function/Lines |
|---|---|---|
| UI Button | `sidebar_actions.php:115` | `#userReportButton` |
| UI Card | `user_report_card.php` | Form + table elements |
| JS Card Toggle | `user_report_actions.js:62` | Toggle `#userReportCardContainer` |
| JS Submit | `user_report_actions.js:101` | POST → `get_user_report` |
| JS Render | `user_report_actions.js:138` | `renderResults()` |
| JS Download | `user_report_actions.js:28` | Client-side CSV from DOM |
| JS Bulk Disable | `user_report_actions.js:171` | Loops users → `execute_action` |
| API Router | `api/index.php:31` | `get_user_report` → `get_user_report.php` |
| PHP Controller | `get_user_report.php` | Calls `ad_dispatch_report_operation('user_report', ...)` |
| Backend Router | `ad_operation_router.php:203` | `ad_dispatch_report_operation()` decides PS vs LDAP |
| Operation Catalog | `ldap_operation_catalog.php:135` | Maps `user_report` to PS script + LDAP handler |
| PowerShell Script | `get-user-report.ps1` | ADSI `[adsisearcher].FindAll()` |
| PowerShell Runner | `powershell_runner.php:86` | `exec()` → PS script |
| LDAP Handler | `ldap_hub_reports.php:553` | `ldap_hub_user_report()` |
| LDAP Paged Search | `ldap_helpers.php:392` | `ldap_paged_search()` |
| LDAP Helpers | `ldap_ad_helpers.ps1` | `ldap-search`, `ldap-result`, etc. |

## Pattern Summary (for implementing other buttons)

To add a new report/export button, follow this pattern:

1. **UI**: Add `<button>` in `sidebar_actions.php` + card component in `resources/views/components/global/`
2. **JS**: Add click handler in a `*_actions.js` file:
   - Toggle card visibility
   - POST to API endpoint with form data
   - Parse JSON response → render HTML table / trigger download
3. **PHP**: Add controller in `app/Application/Http/Controllers/`
4. **PS Script Key**: Add entry in `config/powershell.php`
5. **Operation Catalog**: Add entry in `app/Ldap/Operations/ldap_operation_catalog.php` with `ps_script_key` and `ldap_handler`
6. **PowerShell Script**: Create `.ps1` in `scripts/powershell/`
7. **LDAP Handler** (optional): Add function in `ldap_hub_reports.php` or similar
8. **API Router**: Add endpoint in `public/api/index.php`
9. **For long-running operations**: Add `set_time_limit(0)` in the PHP controller
