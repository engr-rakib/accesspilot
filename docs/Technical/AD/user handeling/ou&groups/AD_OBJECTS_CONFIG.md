# Intelligent OU & Group Management

**Guide for system administrators — how AD OU hierarchy and security group creation are dynamically configured.**

---

## Table of Contents

1. [Overview](#1-overview)
2. [OU Management — How It Works](#2-ou-management--how-it-works)
3. [Group Management — How It Works](#3-group-management--how-it-works)
4. [Configuration Walkthrough](#4-configuration-walkthrough)
5. [Application Architecture & Data Flow](#5-application-architecture--data-flow)
6. [Use Cases & Examples](#6-use-cases--examples)
7. [Best Practices](#7-best-practices)

---

## 1. Overview

When a new user is created from HRMS data, the application:

1. **Builds an OU path** by reading HRMS fields (e.g., `OPERATING_UNIT_TITLE`, `DEPARTMENT_TITLE`, etc.) and mapping each to an OU level.
2. **Creates OUs** in Active Directory if they don't exist.
3. **Creates security groups** for each OU level (optional, configurable).
4. **Adds the user** to the appropriate groups — both auto-created per-level groups and conditional groups based on HRMS field values.

All of this is **fully configurable per domain** from the System Configuration page. No hardcoded logic — the administrator decides which HRMS field maps to which OU level, how groups are named, and which conditions trigger group membership.

---

## 2. OU Management — How It Works

### 2.1 OU Hierarchy Structure

The OU hierarchy supports **5 levels**, from top (broadest) to bottom (the user's OU):

| Level | Default HRMS Field      | Typical Meaning               |
|-------|------------------------|-------------------------------|
| 1     | `OPERATING_UNIT_TITLE` | Company / Operating Unit       |
| 2     | `DEPARTMENT_TITLE`     | Department                     |
| 3     | `SECTION_TITLE`        | Section / Division             |
| 4     | `PRODUCT_TITLE`        | Product / Team                 |
| 5     | `SUB_SECTION_TITLE`    | Sub-section (user's OU)        |

Example OU path generated:
```
OU=CompanyName,OU=ICT,OU=SoftwareDev,OU=AccessPilot,OU=WebTeam,DC=domain,DC=com
```

### 2.2 Configurable Mapping

Each of the 5 levels can be independently configured:

- **Field mapping**: Map any HRMS field to any level (e.g., Level 1 → `LOCATION_TITLE` instead of `OPERATING_UNIT_TITLE`)
- **Skip**: Set a level to "Skip" to exclude it from the OU path entirely
- **Prefix**: A string prepended to every OU name (e.g., `BD_`)
- **Suffix**: A string appended to every OU name (e.g., `_OU`)

If a mapped HRMS field value is empty or `"N/A"`, that level is automatically skipped.

### 2.3 Backward Compatibility

When no custom config is set (default state), the system behaves exactly as before — using the hardcoded 5-level hierarchy shown above.

### 2.4 Preview

The configuration UI shows a **live preview** of the generated OU path as you change field mappings, so you can see the result before saving.

---

## 3. Group Management — How It Works

### 3.1 Auto-Created Groups

By default, for each OU level in the hierarchy, a **security group** is created. The group is placed inside its corresponding OU.

**Default naming**: `"{OU_Name} Group"` (e.g., `WebTeam Group`)

**Configurable naming**: With custom prefix and suffix:
- Prefix: `GRP_`
- Suffix: `_Sec`
- Result: `GRP_WebTeam_Sec Group`

Nested group membership is maintained automatically — each child OU's group is added as a member of its parent OU's group.

### 3.2 Auto-Create Toggle

You can **disable** auto-creation entirely. When disabled:
- No security groups are created during OU creation
- No nested group membership is set up
- Users are not added to per-level groups

### 3.3 Conditional Group Assignment Rules

Administrators can define rules that automatically add users to specific security groups based on HRMS field values.

**Rule structure**:
```
If [HRMS Field] = [Value] → Add to [Group Name]
```

**Example rules**:
| Field | Value | Target Group |
|-------|-------|-------------|
| `DEPARTMENT_TITLE` | `ICT` | `ICT-Support-Group` |
| `LOCATION_TITLE` | `Head Office` | `HO-All-Staff` |
| `DESIGNATION` | `Manager` | `Managers-Only` |

**How rules are evaluated**:
1. After user creation (or existing user move), the system iterates all defined rules
2. For each rule, the HRMS field value is read from the API response
3. If the value matches, the user is added to the target group
4. If the group doesn't exist, a warning is logged and the rule is skipped
5. If the user is already a member, no action is taken

### 3.4 Backward Compatibility

When no custom group config is set, groups are auto-created with default naming (`"{OU_Name} Group"`) and no conditional rules apply.

---

## 4. Configuration Walkthrough

### 4.1 Accessing the Configuration

1. Log in to the portal as an administrator
2. Navigate to **System Configuration** → **AD Objects** tab
3. The tab is organized in a single column layout:

```
┌─────────────────────────────────┐
│ Per-Domain Configuration         │
├─────────────────────────────────┤
│ User Properties Configuration    │
├─────────────────────────────────┤
│ OU Management Configuration      │
├─────────────────────────────────┤
│ Group Management Configuration   │
└─────────────────────────────────┘
```

### 4.2 Selecting a Domain

Use the **domain selector** at the top of the AD Objects tab. OU and Group configs are stored per-domain. When you switch domains, the corresponding OU/Group config is loaded automatically.

### 4.3 Configuring OU Management

1. Toggle the **Customize** switch to enable custom OU configuration
2. The default hint (with tree diagram) is replaced by editable fields
3. For each level (1–5), select an HRMS field from the dropdown or choose "— Skip —"
4. Optionally enter a **Prefix** and **Suffix** applied to all OU names
5. Watch the **Preview** update in real time
6. Click **Save to Domain** to persist the configuration
7. Click **Reset** to revert to the default hardcoded mapping

### 4.4 Configuring Group Management

1. Toggle the **Customize** switch to enable custom group configuration
2. Choose **Auto-Create Groups**: Enabled (default) or Disabled
3. Optionally enter a **Group Name Prefix** and **Group Name Suffix**
4. Under **Conditional Group Assignment Rules**, click **Add Rule**
5. Each rule row contains:
   - **HRMS Field** dropdown (select the source field)
   - **Value** input (the value to match)
   - **Target Group** input (the AD group name to add the user to)
6. Add multiple rules as needed; remove any rule with the × button
7. Watch the **Preview** update in real time
8. Click **Save to Domain** to persist
9. Click **Reset** to revert to defaults

### 4.5 Saving

- Each card has its own **Save to Domain** and **Reset** buttons
- Save writes the config to the domain's storage (via `domain_api.php`)
- Reset reverts the card to its default state (not previously saved)
- The config is persisted per-domain and survives application restarts

---

## 5. Application Architecture & Data Flow

### 5.1 Full End-to-End Data Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    BROWSER (System Config UI)                    │
│                                                                  │
│  JS: ouGetConfig() / grpGetConfig() → JSON                      │
│  POST /domain_api?action=update_domain                          │
└───────────────────────┬─────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│                    PHP – domain_api.php                          │
│                                                                  │
│  Receives: { key, ou_config: {...}, group_config: {...} }        │
│  Validates domain exists                                         │
│  $domain['ou_config'] = $data['ou_config'];                     │
│  $domain['group_config'] = $data['group_config'];               │
│  Calls ldap_upsert_domain()                                      │
└───────────────────────┬─────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│                    STORAGE – domains.json                        │
│                                                                  │
│  Path: app/Ldap/Config/domains.json                              │
│  Format: JSON array of domain objects                            │
│  Each domain object has:                                         │
│    {                                                             │
│      "key": "domain.local",                                      │
│      "ou_config": { ... },          ← saved here                 │
│      "group_config": { ... },       ← saved here                 │
│      "label": "Domain (local)",                                  │
│      "naming": { ... },                                         │
│      "ldap": { ... },                                           │
│      ...                                                        │
│    }                                                             │
└───────────────────────┬─────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│                    TRIGGER – User Creation                       │
│                                                                  │
│  1. HRMS API returns employee data                               │
│  2. execute_action.php receives createUser action                │
│  3. Calls ad_action_service.php::createUser()                    │
└───────────────────────┬─────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│                    PHP – ad_action_service.php                   │
│                                                                  │
│  Flow inside createUser():                                       │
│                                                                  │
│  1. Load active domain via ldap_get_domain()                     │
│     → reads domains.json, finds current domain                   │
│                                                                  │
│  2. Build PowerShell parameters:                                 │
│     if (!empty($domain['ou_config'])) {                          │
│       $json = json_encode($domain['ou_config']);                 │
│       $psParams['OuConfig'] = $json;                             │
│     }                                                            │
│     if (!empty($domain['group_config'])) {                       │
│       $json = json_encode($domain['group_config']);              │
│       $psParams['GroupConfig'] = $json;                          │
│     }                                                            │
│                                                                  │
│  3. Merge with user data, credentials, other params              │
│  4. Invoke PowerShell:                                           │
│     $ps = Process('powershell', '-File create-user.ps1 ...')     │
│     $ps.Arguments = all params serialized                        │
│  5. Parse JSON response from PowerShell STDOUT                   │
│  6. Return action result to caller                               │
└───────────────────────┬─────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│                    POWERSHELL – create-user-core.ps1             │
│                                                                  │
│  Receives: $OuConfigJson, $GroupConfigJson as strings            │
│                                                                  │
│  Step 1 – Parse Configs:                                         │
│    $Script:OuConfig = $OuConfigJson                              │
│      ? ($OuConfigJson | ConvertFrom-Json) : $null                │
│    $Script:GroupConfig = $GroupConfigJson                        │
│      ? ($GroupConfigJson | ConvertFrom-Json) : $null             │
│                                                                  │
│  Step 2 – Build OU Hierarchy:                                    │
│    if ($Script:OuConfig.customized -eq $true) {                  │
│      for ($i=1; $i -le 5; $i++) {                               │
│        $field = $Script:OuConfig.levels."$i".field               │
│        if empty/Skip → continue                                  │
│        $rawValue = $EmpData.$field                               │
│        $ouName = $prefix + $rawValue + $suffix                   │
│        $OUHierarchy += $ouName                                   │
│      }                                                           │
│    } else {                                                      │
│      // Hardcoded legacy fallback                                │
│      $OUHierarchy = @($OperatingUnit, $Department, ...)          │
│    }                                                             │
│                                                                  │
│  Step 3 – Create OUs (bottom-up):                                │
│    $OUPath = $BaseDN  (or root_ou,$BaseDN if set)                │
│    foreach ($OU in $OUHierarchy) {                               │
│      $OUPath = Get-OrCreateOU -Name $OU -Parent $OUPath         │
│    }                                                             │
│                                                                  │
│  Step 4 – Create/Assign Groups:                                  │
│    if ($GroupConfig.auto_create -ne $false) {                    │
│      foreach OU level → create security group                    │
│      maintain nested membership (child → parent)                 │
│    }                                                             │
│                                                                  │
│  Step 5 – Evaluate Conditional Rules:                            │
│    foreach ($rule in $GroupConfig.rules) {                       │
│      if ($EmpData.($rule.field) -eq $rule.value) {               │
│        Add-ADGroupMember -Identity $rule.group                   │
│      }                                                           │
│    }                                                             │
│                                                                  │
│  Step 6 – Place User:                                            │
│    if user exists → Move-ADObject to $OUPath                     │
│    if user new → New-ADUser -Path $OUPath                        │
│                                                                  │
│  Step 7 – Return Result:                                         │
│    Write-Output (JSON result with actions taken, status)         │
└─────────────────────────────────────────────────────────────────┘
                        │
                        ▼
              Active Directory
```

### 5.2 File-by-File Role Map

| File | Role | Key Responsibility |
|------|------|-------------------|
| `resources/views/pages/tools/system_config_view.php` | UI | Renders OU/Group config form, JS preview, save/reset buttons |
| `domain_api.php` | API | `update_domain` action saves ou_config + group_config to domain |
| `app/Ldap/Config/ldap_config_repository.php` | Storage | `ldap_upsert_domain()` writes to domains.json; `ldap_get_domain()` reads |
| `app/Domain/ActiveDirectory/ad_action_service.php` | Orchestrator | Loads domain config, JSON-encodes ou_config/group_config, passes to PowerShell |
| `execute_action.php` | Entry | Receives `createUser` action, delegates to `ad_action_service.php::createUser()` |
| `scripts/powershell/create-user.ps1` | Wrapper | Accepts params, calls create-user-core.ps1 |
| `scripts/powershell/create-user-core.ps1` | Core | Parses JSON configs, builds OU hierarchy, creates OUs/groups, places user |

### 5.3 Configuration Schema (Detailed)

**ou_config** (stored in `domains.json` per-domain):
```json
{
  "customized": true,
  "levels": {
    "1": { "field": "OPERATING_UNIT_TITLE" },
    "2": { "field": "DEPARTMENT_TITLE" },
    "3": { "field": "SECTION_TITLE" },
    "4": { "field": "PRODUCT_TITLE" },
    "5": { "field": "SUB_SECTION_TITLE" }
  },
  "prefix": "BD_",
  "suffix": "_OU",
  "root_ou": "OU=CompanyUsers"
}
```

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `customized` | bool | no | `false` | If true, use levels config; if false/absent, use hardcoded 5-level defaults |
| `levels["1..5"].field` | string | no | See defaults | HRMS field name, or empty/Skip to omit level |
| `prefix` | string | no | `""` | Prepended to every OU name in the hierarchy |
| `suffix` | string | no | `""` | Appended to every OU name in the hierarchy |
| `root_ou` | string | no | `""` | Base container DN (e.g. `OU=CompanyUsers`); OUs created under it |

**group_config**:
```json
{
  "customized": true,
  "auto_create": true,
  "prefix": "GRP_",
  "suffix": "_Sec",
  "rules": [
    { "field": "DEPARTMENT_TITLE", "value": "ICT", "group": "ICT-Support-Group" },
    { "field": "LOCATION_TITLE", "value": "Head Office", "group": "HO-All-Staff" }
  ]
}
```

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `customized` | bool | no | `false` | If true, use custom group config; if false/absent, use default naming |
| `auto_create` | bool | no | `true` | If true, create security groups for each OU level |
| `prefix` | string | no | `""` | Prepended to every auto-created group name |
| `suffix` | string | no | `""` | Appended to every auto-created group name |
| `rules[]` | array | no | `[]` | Conditional group assignment rules |
| `rules[].field` | string | yes | — | HRMS field to check |
| `rules[].value` | string | yes | — | Value to match against |
| `rules[].group` | string | yes | — | AD group name to add user to |

### 5.4 PowerShell Script Architecture

**Script entry**: `create-user-core.ps1`

The script operates in distinct phases:

**Phase A – Parameter Parsing** (lines ~1–60):
```
$OuConfigJson   → ConvertFrom-Json → $Script:OuConfig (PSObject or $null)
$GroupConfigJson → ConvertFrom-Json → $Script:GroupConfig (PSObject or $null)
Fallback: if parse fails or empty → $Script:OuConfig = $null (uses hardcoded)
```

**Phase B – OU Hierarchy Assembly** (lines ~395–433):
```
if ($Script:OuConfig -and $Script:OuConfig.customized -eq $true)
  → iterate levels 1..5, read field from config, apply prefix/suffix
else
  → hardcoded: $OperatingUnit, $Department, $EmpData.SECTION_TITLE, ...
```

**Phase C – OU Creation** (lines ~435–445):
```
$rootOUPath = $BaseDN
if ($Script:OuConfig.root_ou) { $rootOUPath = "$root_ou,$BaseDN" }

$OUPath = $rootOUPath
foreach ($OU in $OUHierarchy) {
  $OUPath = Get-OrCreateOU -Name $OU -Parent $OUPath -GroupConfig $Script:GroupConfig
}
```

**Phase D – Group Handling** (inside `Get-OrCreateOU`, lines ~172–240):
```
$grpPrefix = $GroupConfig.prefix || ''
$grpSuffix = $GroupConfig.suffix || ''
$shouldAutoCreate = ($GroupConfig.auto_create -ne $false)

if ($shouldAutoCreate) {
  $GroupName = "${grpPrefix}${SafeOUName}${grpSuffix} Group"
  Check if group exists → create if not
  Add child group to parent group (nested membership)
}
```

**Phase E – Conditional Rules** (lines ~540–570):
```
foreach ($rule in $Script:GroupConfig.rules) {
  $fieldValue = $EmpData.($rule.field)
  if ($fieldValue -eq $rule.value) {
    Add-ADGroupMember -Identity $rule.group -Members $UserDN
  }
}
```

**Phase F – User Placement** (lines ~447–530):
```
if ($ExistingUser) {
  → Move-ADObject to $OUPath if DN differs
  → Enable account if disabled
} else {
  → New-ADUser with all mapped attributes
}
```

**Phase G – Result Output** (lines ~580+):
```
$result = @{
  success     = $script:overallSuccess
  actions     = $actionsTaken
  user        = $UserName
  ou_path     = $OUPath
  groups      = $groupsAdded
  …
}
Write-Output ($result | ConvertTo-Json)
```

### 5.5 Error Handling & Edge Cases

| Scenario | Handling |
|----------|----------|
| JSON parse failure | Config treated as `$null` → hardcoded defaults used. Error logged. |
| Empty HRMS field value | Level auto-skipped (not created in AD). |
| HRMS field value is `"N/A"` | Same as empty — level skipped. |
| OU already exists | Reused (no error). `Get-ADOrganizationalUnit` check before `New-ADOrganizationalUnit`. |
| Group already exists | Reused. Membership updated but not duplicated. |
| Target group in conditional rule doesn't exist | Warning logged, rule skipped. User not added. |
| User already in target group | No action taken (idempotent — `Add-ADGroupMember` fails silently for existing members). |
| AD connection fails | `$script:overallSuccess = $false`, error logged per-operation. |
| Root OU container doesn't exist | OU creation fails → error logged → `$overallSuccess = false`. |
| Concurrent user creation | Each call is independent (no shared state). `domains.json` read per-request. |
| Special chars in OU name | `/ \ [ ] : ; \| = + * ? < > @` stripped. `&` replaced with `and`. |

### 5.6 Backward Compatibility Guarantee

When `ou_config` and `group_config` are absent (or `customized` is falsy), the system falls back to the **exact original behavior**:

- 5-level hardcoded OU: `OPERATING_UNIT_TITLE → DEPARTMENT_TITLE → SECTION_TITLE → PRODUCT_TITLE → SUB_SECTION_TITLE`
- Group naming: `"{OU_Name} Group"` (no prefix/suffix)
- Auto-create: enabled
- No conditional rules

This means existing deployments with no custom config continue working without any migration.

---

## 6. Use Cases & Examples

### 6.1 Simple 3-Level Hierarchy

**Requirement**: Only use Operating Unit → Department → Section (skip Product and Sub-Section).

**Config**:
| Level | Field |
|-------|-------|
| 1 | `OPERATING_UNIT_TITLE` |
| 2 | `DEPARTMENT_TITLE` |
| 3 | `SECTION_TITLE` |
| 4 | — Skip — |
| 5 | — Skip — |

**Result**: `OU=Company,OU=ICT,OU=SoftwareDev,DC=domain,DC=com`

### 6.2 Custom OU Prefix/Suffix

**Requirement**: Add `BD_` prefix and `_OU` suffix to all OU names.

**Config**:
- Prefix: `BD_`
- Suffix: `_OU`
- Levels: default mapping

**Result**: `OU=BD_Company_OU,OU=BD_ICT_OU,OU=BD_SoftwareDev_OU,DC=domain,DC=com`

### 6.3 Disable Auto-Groups with Conditional Rules

**Requirement**: Don't create OU-level security groups, but automatically add ICT department members to `ICT-All` group and managers to `Managers-Group`.

**Config**:
- Auto-Create Groups: **Disabled**
- Rules:
  | Field | Value | Group |
  |-------|-------|-------|
  | `DEPARTMENT_TITLE` | `ICT` | `ICT-All` |
  | `DESIGNATION` | `Manager` | `Managers-Group` |

**Result**: No OU-level groups are created. When an ICT Manager is created, they are added to both `ICT-All` and `Managers-Group`.

### 6.4 Custom Group Naming with Location-Based Rules

**Requirement**: Groups named as `{OU_Name}-Group`, plus add all HQ employees to `Head-Office-Group`.

**Config**:
- Auto-Create Groups: **Enabled**
- Prefix: (empty)
- Suffix: `-Group`
- Rules:
  | Field | Value | Group |
  |-------|-------|-------|
  | `LOCATION_TITLE` | `Head Office` | `Head-Office-Group` |

**Result**: Group for ICT becomes `ICT-Group` (not `ICT Group`). All Head Office employees are added to `Head-Office-Group`.

---

## 7. Best Practices

1. **Plan your OU structure before configuring** — decide which HRMS fields map to which levels and whether you need all 5 levels.

2. **Use Skip for unused levels** rather than leaving them empty — unused levels will still try to read the default field.

3. **Keep prefixes/suffixes short** — OU names and group names have length limits in AD (64 chars for OU name, 256 chars for group name).

4. **Test with preview first** — the live preview shows the exact OU path that will be created, so verify it before saving.

5. **Start with a test domain** — if you have a test AD domain, configure there first before applying to production.

6. **Conditional rules are additive** — users can be added to multiple groups via multiple rules. There's no limit on rule count.

7. **Group existence matters** — for conditional rules, the target group must already exist in AD. Auto-created groups (from the OU hierarchy) are created automatically.

8. **Backward compatibility is built in** — if you never Customize a card, the system behaves exactly like the original hardcoded version. No migration needed.
