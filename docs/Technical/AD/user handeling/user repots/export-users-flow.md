# Export Users (Group) — Full Flow (UI → JS → PHP → PowerShell → LDAP → Display)

## 1. Trigger Button

**File:** `resources/views/components/sidebar_actions.php` (line 122-126)

```html
<button type="button" id="exportUsersButton" class="btn action-button btn-export-action flex-fill">
    <i class="fas fa-file-export me-1"></i> Export Users
</button>
```

- Permission: `has_permission('action_user_report')`

## 2. Export Card (form)

**File:** `resources/views/components/global/export_users_card.php`

Hidden by default (`display: none` on `#exportUsersCardContainer`).

| Element ID | Type | Purpose |
|---|---|---|
| `#exportGroupInput` | `<input>` | Group name (e.g. "Enterprise Admins") |
| `#exportOUInput` | `<input>` | OU path |
| `#exportRunButton` | `<button>` | Run export |
| `#exportDownloadButton` | `<button>` | Download CSV |
| `#exportUserCount` | `<span>` | Shows user count |
| `#exportResultsTable` | `<table>` | Results table (hidden initially) |

## 3. JS — Toggle Card + Submit

**File:** `public/resources/frontend/js/admin/action_processor.js` (line 210-240)

```javascript
exportUsersButton.addEventListener('click', () => {
    const container = document.getElementById('exportUsersCardContainer');
    container.style.display = container.style.display === 'none' ? 'block' : 'none';
});

exportRunButton.addEventListener('click', async () => {
    showButtonLoading(exportRunButton, true);
    const res = await fetch(apiBase + '?endpoint=custom_export_users_message', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `GroupName=${encodeURIComponent(group)}&OUName=${encodeURIComponent(ou)}&AllUsers=${!group && !ou}`
    });
    const data = await res.json();
    if (data.success) renderExportResults(data.users, data.totalCount);
    showButtonLoading(exportRunButton, false);
});
```

## 4. API Router

**File:** `public/api/index.php`

```php
'custom_export_users_message' => 'custom_export_users_message.php'
```

## 5. PHP Controller

**File:** `app/Application/Http/Controllers/custom_export_users_message.php`

```php
set_time_limit(0); // ← Prevents PHP timeout for long exports
$psResult = ad_dispatch_report_operation('export_ad_users', [
    'OUName' => $ouName,
    'GroupName' => $groupName,
    'AllUsers' => empty($ouName) && empty($groupName),
    'ExecutedBy' => $loggedInUser,
]);
echo json_encode($psResult);
```

## 6. Router / Backend Dispatch

**File:** `app/Ldap/Router/ad_operation_router.php` — `ad_dispatch_report_operation()` (line 203)

1. Looks up `'export_ad_users'` in operation catalog
2. Calls `ad_resolve_backend('export_ad_users')` → returns `'powershell'` (no LDAP handler configured)
3. Calls `powershell_run_script('export_ad_users', ...)`

### Operation Catalog Entry

**File:** `app/Ldap/Operations/ldap_operation_catalog.php`

```php
'export_ad_users' => [
    'api_endpoint' => 'custom_export_users_message',
    'ps_script_key' => 'export_ad_users',
    'phase' => 2,  // report phase
],
```

- **No `ldap_handler`** — PowerShell-only operation.

## 7. PowerShell Runner

**File:** `app/Infrastructure/PowerShell/powershell_runner.php`

```php
function powershell_run_script($scriptKey, $parameters) {
    build_powershell_command();  // wraps in pwsh -NoProfile -File ...
    exec($command, $output, $returnVar);  // ← NO TIMEOUT
    return ['success' => $returnVar === 0, 'output' => implode("\n", $output)];
}
```

- `exec()` blocks until PowerShell finishes — no timeout.
- If PHP has `max_execution_time` (default 30s), `set_time_limit(0)` prevents PHP from killing it.

## 8. PowerShell Script

**File:** `scripts/powershell/export-group-user-list.ps1`

### Parameters

| Param | Type | Source |
|---|---|---|
| `OUName` | string | `exportOUInput.value` |
| `GroupName` | string | `exportGroupInput.value` |
| `AllUsers` | switch | `!group && !ou` |
| `ExecutedBy` | string | session user |

### Logic (simplified)

```
1. . "$PSScriptRoot\ldap_ad_helpers.ps1"   ← dot-source helpers
2. Get SecureConfigPath (for AD module, but not used in IIS mode)
3. If AllUsers:
     ldap-search → FindAll() with filter (objectClass=user)(objectCategory=person)
     → iterates ALL users (potentially 1000s)
4. If OUName (not AllUsers, no GroupName):
     Get-ADOrganizationalUnitViaLDAP → ldap-search
     → ldap-search → FindAll() for users under OU
5. If GroupName:
     Get-ADGroupViaLDAP → resolves group DN
     → Get-ADGroupMemberViaLDAP → iterates each member
     → For each member DN: [ADSI]"LDAP://$dn" → read properties
     → 1167 iterations for "Server Administration Group"
6. For each user found:
     - Parse userAccountControl (enabled/disabled)
     - Check admin group membership (via memberOf)
     - Parse lastLogonTimestamp
     - Extract OU from distinguishedName
     - Build PSCustomObject[] with selected properties
7. Convert to JSON → echo to stdout
```

## 9. LDAP Helpers Used

**File:** `scripts/powershell/ldap_ad_helpers.ps1`

| Function | Line | What it does |
|---|---|---|
| `init-ldap` | 11 | Sets up `$script:LdapSearcher` with `PageSize=1000` |
| `ldap-search` | 23 | `DirectorySearcher.FindAll()` with try-catch |
| `ldap-result` | 38 | Converts raw SearchResultCollection to PSCustomObject[] |
| `Get-ADGroupViaLDAP` | 66 | For CN= groups: direct `[ADSI]"LDAP://$DN"`; else `ldap-search` |
| `Get-ADGroupMemberViaLDAP` | 98 | `[ADSI]"LDAP://$GroupDN"` → reads `member` attr → per-member `[ADSI]"LDAP://$dn"` loop |

## 10. Result Rendering (JS)

**File:** `public/resources/frontend/js/admin/action_processor.js` — `renderExportResults()`

```javascript
function renderExportResults(users, total) {
    document.getElementById('exportUserCount').textContent = total + ' users found';
    const tbody = document.getElementById('exportResultsTable').querySelector('tbody');
    users.forEach(u => {
        tbody.innerHTML += `<tr>
            <td>${u.SamAccountName}</td>
            <td>${u.DisplayName}</td>
            <td>${u.Enabled ? 'Active' : 'Inactive'}</td>
            <td>${u.OU}</td>
        </tr>`;
    });
    document.getElementById('exportResultsSection').style.display = 'block';
}
```

## 11. Download CSV

**File:** `public/resources/frontend/js/admin/action_processor.js` (line 250-270)

Same pattern as Report Results — client-side from DOM table.

## Why Export Users Times Out (vs Report Results)

| Factor | Report Results | Export Users |
|---|---|---|
| **LDAP strategy** | Single `FindAll()` with `PageSize=2000` | Finds group → reads `member` → per-member `[ADSI]` loop (1167 iterations) |
| **Network round trips** | 1 (paged by LDAP server) | 1 (group) + 1 (member list) + n (one per member) = ~1169 |
| **Search pattern** | `(objectClass=user)` filter on whole domain | `[ADSI]"LDAP://$DN"` per member (single-attribute reads) |
| **PowerShell processing** | Moderate (field mapping per user) | Heavy (UAC parsing + admin check + OU extraction + memberOf per user) |
| **PHP timeout** | Default 30s sufficient | Need `set_time_limit(0)` |
| **Cumulative time** | ~2-5 seconds | ~60-120+ seconds for 1167 users |

### The bottleneck

The per-member `[ADSI]"LDAP://$dn"` loop in `Get-ADGroupMemberViaLDAP` makes one network call per member. Even if each call takes 50ms, 1167 × 50ms = ~58 seconds. Add PowerShell object construction and ~60-90 seconds total is plausible.

## Complete File Map

| Layer | File | Key Function/Lines |
|---|---|---|
| UI Button | `sidebar_actions.php:122` | `#exportUsersButton` |
| UI Card | `export_users_card.php` | Form + table |
| JS Toggle | `action_processor.js:210` | Toggle card |
| JS Submit | `action_processor.js:220` | POST → `custom_export_users_message` |
| JS Render | `action_processor.js:240` | `renderExportResults()` |
| JS Download | `action_processor.js:250` | Client-side CSV |
| API Router | `api/index.php` | `custom_export_users_message` |
| PHP Controller | `custom_export_users_message.php` | `set_time_limit(0)` + dispatch |
| Backend Router | `ad_operation_router.php:203` | `powershell` only (no LDAP handler) |
| Operation Catalog | `ldap_operation_catalog.php` | `export_ad_users` |
| PowerShell Script | `export-group-user-list.ps1` | Group member iteration loop |
| PS Helpers | `ldap_ad_helpers.ps1` | `Get-ADGroupViaLDAP`, `Get-ADGroupMemberViaLDAP` |
| Powershell Runner | `powershell_runner.php:86` | `exec()` unlimited wait |

## Key Files (sorted by relevance)

| Priority | File | Why |
|---|---|---|
| 1 | `scripts/powershell/export-group-user-list.ps1` | Main export script — the 1167-member loop lives here |
| 2 | `scripts/powershell/ldap_ad_helpers.ps1` | `Get-ADGroupMemberViaLDAP` — per-member `[ADSI]` reads |
| 3 | `app/Application/Http/Controllers/custom_export_users_message.php` | PHP controller — `set_time_limit(0)` already added |
| 4 | `public/resources/frontend/js/admin/action_processor.js` | JS submit + render |
| 5 | `resources/views/components/global/export_users_card.php` | Card HTML |
| 6 | `config/powershell.php` | Maps `export_ad_users` → `export-group-user-list.ps1` |
| 7 | `app/Ldap/Operations/ldap_operation_catalog.php` | Operation metadata |
| 8 | `app/Infrastructure/PowerShell/powershell_runner.php` | `exec()` wrapper |
| 9 | `app/Ldap/Router/ad_operation_router.php` | Backend dispatch |
