# Export Users Redesign Plan — Match Report Results Pattern

## Current Problem
Export Users card uses complex autocomplete dropdowns (OU + Group search via `adTreeDropdown`), separate massive JS file (`export_user_actions.js:186` lines), separate drop-downs JS (`export_user_dropdowns.js:89` lines), and the PowerShell script does per-member iteration (slow, times out on 1167 members).

Report Results card works perfectly — simple form, single query, client-side CSV download.

## Goal
Remove existing Export Users (Users button) card + JS, rebuild exactly like Report Results pattern:
- Simple form with OU or Group selector
- Submit → fetch user list → table preview → download CSV
- Single efficient PowerShell/LDAP query

## What to Remove

### Files to DELETE
| File | Reason |
|---|---|
| `resources/views/components/global/export_user_card.php` | Old card with autocomplete dropdowns |
| `public/resources/frontend/js/admin/export_user_actions.js` | Old export JS (186 lines) |
| `public/resources/frontend/js/admin/export_user_dropdowns.js` | Autocomplete dropdown JS (89 lines) |
| `app/Application/Http/Controllers/custom_export_users_message.php` | Old PHP controller (113 lines) |
| `app/Application/Http/Controllers/custom_export_users.php` | Old download endpoint (53 lines) |
| `app/Application/Http/Controllers/custom_export_group_user_list_message.php` | Old group export controller (83 lines) |
| `app/Application/Http/Controllers/custom_export_group_user_list.php` | Old group download endpoint (52 lines) |
| `scripts/powershell/export-group-user-list.ps1` | Old per-member-loop PS script (291 lines) |

### API routes to REMOVE from `public/api/index.php`
- `custom_export_users_message`
- `custom_export_users`
- `custom_export_group_user_list_message`
- `custom_export_group_user_list`

### Operation catalog entries to REMOVE from `ldap_operation_catalog.php`
- `export_ad_users`
- `export_group_users`

### Config entries to REMOVE from `config/powershell.php`
- `Custom_export_group_USer_list`

### Config entries to REMOVE from `config/ldap/ldap_operations.php`
- `export_ad_users`
- `export_group_users`

### Config entries to REMOVE from `config/components_config.php`
- `action_export_ad_users`
- `action_export_group_users`
- `card_export_users`

## What to Create

### 1. New Card: `resources/views/components/global/export_user_oureport_card.php`
- Mirrors `user_report_card.php` structure
- Form fields: OU (text input with DC= hint), Group (text input), or Both
- Table preview with columns: Username, Display Name, Status, OU Path
- Download CSV button (client-side)
- Cancel button
- Permission: `action_export_ad_users`

### 2. New JS: `public/resources/frontend/js/admin/export_user_report_actions.js`
- Mirrors `user_report_actions.js` structure (IIFE, DOMContentLoaded)
- Toggle card visibility
- Submit → POST to `get_ou_group_user_report`
- Render results in table
- Download CSV (client-side from DOM)
- Cancel resets form

### 3. New Controller: `app/Application/Http/Controllers/get_ou_group_user_report.php`
- Mirrors `get_user_report.php`
- `set_time_limit(0)` for long exports
- Calls `ad_dispatch_report_operation('ou_group_user_report', ...)`
- Returns JSON with `{ success, users: [...] }`

### 4. New Operation Catalog Entry in `ldap_operation_catalog.php`
```php
'ou_group_user_report' => [
    'api_endpoint' => 'get_ou_group_user_report',
    'ps_script_key' => 'ou_group_user_report',
    'ldap_handler' => 'ldap_hub_ou_group_user_report',
    'phase' => 1,
],
```

### 5. New PS Script: `scripts/powershell/get-ou-group-user-report.ps1`
- Mirrors `get-user-report.ps1` pattern
- Parameters: `OUName`, `GroupName`, `ExecutedBy`
- For OU: single `ldap-search` with `(objectClass=user)(objectCategory=person)` under OU base
- For Group: `Get-ADGroupViaLDAP` → read `member` attr only → `ldap-search` with `(|(distinguishedName=dn1)(dn2)...)` batch query
- Returns JSON: `{ success: true, users: [...] }`

### 6. New PS Config in `config/powershell.php`
```php
'ou_group_user_report' => $powershellRoot . '/get-ou-group-user-report.ps1',
```

### 7. New API Route in `public/api/index.php`
```php
'get_ou_group_user_report' => 'get_ou_group_user_report.php',
```

### 8. Update sidebar_actions.php
- Replace old `#exportAdUsersButton` with new button that opens new card
- Keep permission `action_export_ad_users`

### 9. Update master.php
- Replace old `export_user_card.php` include with new `export_user_oureport_card.php`
- Replace `export_user_actions.js` + `export_user_dropdowns.js` with new `export_user_report_actions.js`

### 10. Update spa_response.php, user_management_view.php
- Replace old card include with new card include

## Key Design Decisions

| Decision | Choice | Reason |
|---|---|---|
| Form input type | Plain text inputs (no autocomplete) | Match Reports pattern, simpler |
| OU input accepts | OU name or DC= path | Same as old but simpler |
| Group input accepts | Group name or CN= path | Same as old but simpler |
| Submit endpoint | New `get_ou_group_user_report` | Clean break from old |
| PowerShell strategy | Batch LDAP filter `(|(dn=...))` for groups | Single query, no per-member loop |
| Download method | Client-side from DOM table | Same as Reports, no session hacks |
| Permission | Same `action_export_ad_users` | No new permission needed |

## PowerShell Script Design

### OU path (single query)
```
Filter: (&(objectClass=user)(objectCategory=person))
SearchBase: $OUName (if provided else domain root)
PageSize: 1000
Return: samAccountName, displayName, distinguishedName, userAccountControl
```

### Group path (two queries)
```
1. Get-ADGroupViaLDAP → finds group DN, gets member DNs
2. Batch search: Filter = "(|(distinguishedName=dn1)(distinguishedName=dn2)...)"
   SearchBase: domain root
   PageSize: 1000
   This is ONE query, not N queries
Return: same properties
```

### All users path
```
Same as get-user-report.ps1 but without status/day filters
```

## File Impact Summary

| Action | Files |
|---|---|
| DELETE | 7 files (card, 2 JS, 3 controllers, 1 PS script) |
| CREATE | 4 files (card, JS, controller, PS script) |
| EDIT | 6 files (sidebar, master.php, spa_response, user_mgmt_view, api/index, operation catalog, powershell config, ldap config, components config) |
