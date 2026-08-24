# Intelligent User Creation from HRMS

**Complete feature specification — how HRMS data drives AD user creation, ID resolution, OU placement, and group assignment.**

> **Last Updated:** 2026-07-06
> **Implementation Language:** PHP (full-stack, no PowerShell dependency)
> **Entry Point:** `app/Ldap/Operations/ldap_user_writer.php` — `ldap_user_writer_create()`

---

## Table of Contents

1. [Overview](#1-overview)
2. [User ID Resolution (HRMS ↔ AD)](#2-user-id-resolution-hrms--ad)
3. [User Properties Assignment](#3-user-properties-assignment)
4. [Existing User Detection During Create](#4-existing-user-detection-during-create)
5. [Existing User Handling (Corrections)](#5-existing-user-handling-corrections)
6. [OU Assignment](#6-ou-assignment)
7. [New User AD Entry Creation](#7-new-user-ad-entry-creation)
8. [Group Assignment](#8-group-assignment)
9. [Operations: Unlock / Reset / Enable / Disable / Modify](#9-operations-unlock--reset--enable--disable--modify)
10. [Server Information Card](#10-server-information-card)
11. [End-to-End Flow Diagrams](#11-end-to-end-flow-diagrams)

---

## 1. Overview

When an administrator initiates user creation or lookup, the system bridges **two identity sources** with a smart resolution strategy:

```
User enters ID (HRMS code or AD logon name)
         │
         ├── HRMS API ──────→ Employee data (name, email, department, etc.)
         │
         └── Active Directory → User objects (sAMAccountName, groups, OU)
```

**Core challenge:** The entered username may be either:
- An **HRMS employee code** (e.g. `C-13088`) — needs HRMS lookup + AD search by generated name
- An **AD logon name** (e.g. `fokirC-13088`) — HRMS lookup returns wrong data if it strips the prefix

The system handles both cases with a **3-tier search strategy** and a **fallback guard**.

---

## 2. User ID Resolution (HRMS ↔ AD)

### 2.1 HRMS API Call

**File:** `app/Domain/HRMS/directory_info_service.php` — `getHRMSInfo()`

When a username is entered, HRMS is queried with this fallback:

```
Step 1: Query HRMS with the FULL entered username
        (e.g. "C-13088" or "fokirC-13088")

Step 2: If not found, strip non-numeric characters and retry
        preg_replace('/[^0-9]/', '', $username)
        (e.g. "C-13088" → "13088", "fokirC-13088" → "13088")
        ⚠ This may return a DIFFERENT employee's data!
```

### 2.2 The Ambiguity Problem

| Person | HRMS Code | AD sAMAccountName | HRMS numeric fallback |
|--------|-----------|-------------------|----------------------|
| Shohag Fokir | `C-13088` | `fokirC-13088` | `13088` |
| Islam (different) | (different) | `islam13088` | `13088` |

**Both** users have `13088` in their AD logon name. A naive search by number matches both.

### 2.3 sAMAccountName Construction (Naming Configuration)

After HRMS data is fetched, the expected AD username is generated:

```php
$namingConfig = $config['naming'] ?? [];
$generatedUsername = ldap_generate_username_from_name($fullName, $empCode, $namingConfig);
```

| Mode | Pattern | Example (`EMP_CODE=C-13088`, `EMP_NAME=Shohag Fokir`) |
|------|---------|--------------------------------------------------------|
| `emp_code` | `{EMP_CODE}` | `C-13088` |
| `last_name_id` | `{Surname}{EMP_CODE}` | `fokirC-13088` |
| `first_non_prefix_id` | `{FirstName}{EMP_CODE}` | `shohagC-13088` |
| `full_name_slug_id` | `{FullNameSlug}{EMP_CODE}` | `shohagfokirC-13088` |

**Additional settings:**
- **Case**: `lowercase`, `UPPERCASE`, or `As Is`
- **Separator**: Used in `full_name_slug` mode
- **Exclude Prefixes**: Honorifics to strip before name parsing (e.g., `Md.` → removed)
- **Given Name Mode**: How `givenName` is extracted from full name
- **Surname Mode**: How `sn` is extracted from full name
- **Display Name Format**: `original`, `first_last`, `last_first`

### 2.4 Dual-ID Display Format

When **HRMS code differs from AD sAMAccountName**, messages show both:

```
HRMS ID 'C-13088' — AD ID 'fokirC-13088'
```

When they are the same (e.g. `emp_code` mode on wgbd):

```
User ID '59022'
```

---

## 3. User Properties Assignment

### 3.1 Field-to-Attribute Mapping

| AD Attribute | Default HRMS Field | Configurable | Description |
|-------------|-------------------|--------------|-------------|
| `sAMAccountName` | (computed via naming) | via naming | Windows logon name |
| `userPrincipalName` | `{sAMAccountName}@{domain}` | via `upn_suffix` | UPN (auto or custom suffix) |
| `givenName` | (first name part) | via naming | First name |
| `sn` (Surname) | (last name part) | via naming | Last name |
| `displayName` | `EMP_NAME` | via naming | Full display name |
| `cn` | same as displayName | No | Common name (DN component) |
| `mail` | `EMAIL` | No | Email address |
| `telephoneNumber` | `MOBILE` | No | Phone number |
| `title` | `DESIGNATION` | No | Job title |
| `department` | `DEPARTMENT_TITLE` | No | Department |
| `company` | `OPERATING_UNIT_TITLE` | No | Company |
| `physicalDeliveryOfficeName` | `LOCATION_TITLE` | No | Office/branch |
| `description` | `RANK` + OU path | No | Auto-generated |

### 3.2 Service Account Path

If `IsServiceAccount` is true, HRMS lookup is skipped. The system uses form fields directly:

```php
$empCode = $username;                    // form input
$fullName = trim($params['DisplayName'] ?? $username);
$email = trim($params['Email'] ?? '');
$section = trim($params['OU'] ?? '');    // explicit OU
```

---

## 4. Existing User Detection During Create

**File:** `ldap_user_writer.php:685-711`

Inside the LDAP closure, after generating the expected username, the system checks for existing users in **two steps**:

### Step 1: Search by Generated Username

```php
$escaped = ldap_escape_filter_value($generatedUsername);
$filter = "(&(objectCategory=person)(objectClass=user)(sAMAccountName={$escaped}))";
$search = @ldap_search($connection, $baseDn, $filter, [...]);
```

- **Found?** → existing user handler runs (Section 5)
- **Not found?** → proceed to Step 2

### Step 2: Fallback — Search by Raw Entered Username

```php
if (!$userExists && $generatedUsername !== $username) {
    // Search by the exact raw $username value
    // e.g. "fokirC-13088" — the actual AD logon name
}
```

- **Found?** → sets `$foundByRawFallback = true`, routes to existing user handler with **limited** corrections (Section 5 — bypass guard)
- **Not found?** → proceed to new user creation (Section 7)

### Why Step 2 Exists

When the operator enters an AD logon name (`fokirC-13088`) instead of the HRMS code (`C-13088`):

1. HRMS lookup strips to `13088` → **wrong employee's** data returned
2. Generated username is computed from wrong data → won't match `fokirC-13088`
3. Step 1 search fails
4. Step 2 finds the actual user by their real AD logon name
5. `foundByRawFallback = true` prevents applying wrong HRMS data (OU move, info update)

---

## 5. Existing User Handling (Corrections)

**File:** `ldap_user_writer.php:713-891`

When an existing user is detected during a "Create" action, the system applies corrections instead of creating a duplicate:

### 5.1 Decision Tree

```
User exists
  │
  ├── foundByRawFallback? (HRMS data is for wrong employee)
  │     YES → Skip OU check and HRMS info update
  │     NO  → Proceed with full corrections
  │
  ├── [OU Check — only if NOT foundByRawFallback]
  │     Compare current DN's OU vs expected OU path
  │     Mismatch? → ldap_rename() to correct OU → mark isMoved = true
  │
  ├── [HRMS Info Update — only if NOT foundByRawFallback]
  │     Compare AD attributes vs HRMS data:
  │       displayName, mail, telephoneNumber, title, physicalDeliveryOfficeName
  │     Any different? → ldap_modify_batch() to update
  │
  ├── [Account Enable]
  │     Is disabled (userAccountControl & 2)?
  │     → Clear bit 1 → enable account
  │
  ├── [Password Reset]
  │     Set default password (config_get('default_password', 'CRESET@1234'))
  │     Force change on next login (pwdLastSet = 0)
  │
  └── [Group Membership]
        Add to manual groups (from GroupMembers param)
        OR add to auto-created OU group
```

### 5.2 Available Variables

| Variable | Source | Used For |
|----------|--------|----------|
| `$existingDn` | Found user's DN | All LDAP operations |
| `$currentUac` | Found user's userAccountControl | Enable/disable check |
| `$generatedUsername` | AD sAMAccountName (resolved) | Display in messages |
| `$foundByRawFallback` | Detection flag | Guard for HRMS-dependent ops |

### 5.3 Return Message Format

```
Warning!!: HRMS ID 'C-13088' — AD ID 'fokirC-13088' already exists
With preferred object location.
ACTION: Moved to correct OU Path: Web Team > AccessPilot > ...
Quick Action:> Password reset triggered. ...
```

If HRMS ID == AD ID (e.g. wgbd domain):
```
Warning!!: User '59022' already exists ...
```

---

## 6. OU Assignment

### 6.1 OU Hierarchy Model

The system builds a **5-level OU hierarchy** from HRMS data:

| Level | Default HRMS Field | Example |
|-------|-------------------|---------|
| 1 (Top) | `OPERATING_UNIT_TITLE` | Walton Group Ltd. |
| 2 | `DEPARTMENT_TITLE` | ICT |
| 3 | `SECTION_TITLE` | Software Development |
| 4 | `PRODUCT_TITLE` | AccessPilot |
| 5 (User) | `SUB_SECTION_TITLE` | Web Team |

**Generated path:** `OU=Web Team,OU=AccessPilot,OU=Software Development,OU=ICT,OU=Walton Group Ltd.,DC=whildc,DC=com`

### 6.2 OU Creation Behavior

The system iterates through the hierarchy and creates missing OUs:

```php
foreach ($ouHierarchy as $ouName) {
    $safeName = preg_replace('/[\/\\\[\]:;|=,+*?<>@]/', '', $ouName);
    $safeName = str_replace('&', 'and', $safeName);
    $ouDn = 'OU=' . ldap_escape_dn_value($safeName) . ',' . $expectedOuPath;

    $ouSearch = @ldap_read($connection, $ouDn, '(objectClass=*)', ['dn'], 0, 0, 0);
    if ($ouSearch === false) {
        // Create OU
        @ldap_add($connection, $ouDn, $ouEntry);
    }
    $expectedOuPath = $ouDn;
}
```

### 6.3 Explicit OU

If the `OU` parameter contains a full DN (contains `=`), it is used directly instead of the HRMS-based hierarchy:

```php
if ($hasExplicitOu) {
    $expectedOuPath = $explicitOu;
}
```

---

## 7. New User AD Entry Creation

**File:** `ldap_user_writer.php:893-1056`

When user does NOT exist, the system creates a new AD entry:

### 7.1 Entry Attributes

```php
$userEntry = [
    'objectClass' => ['top', 'person', 'organizationalPerson', 'user'],
    'sAMAccountName' => $generatedUsername,        // e.g. "fokirC-13088"
    'userPrincipalName' => $upn,                    // e.g. "fokirC-13088@whildc.com"
    'cn' => $cnValue,                               // display name
    'givenName' => $firstName,                      // first name part
    'sn' => $lastName ?: $firstName,                // last name part
    'displayName' => $displayName ?: $fullName,     // HRMS full name
    'userAccountControl' => '514',                  // disabled initially
    // Conditional fields (only set if non-empty):
    'mail', 'telephoneNumber', 'title', 'department', 'company', 'physicalDeliveryOfficeName'
];
```

### 7.2 Post-Creation Steps

```
1. ldap_add()             → Create the user entry (disabled state)
2. Set unicodePwd         → Set initial password
3. Enable account (UAC=512)
4. pwdLastSet=0           → Force password change on next login
5. Set description        → "Rank: {rank} | OU: {path}"
6. Group membership       → Manual groups or auto-created OU group
```

### 7.3 Service Account Differences

| Feature | Regular User | Service Account |
|---------|-------------|-----------------|
| HRMS lookup | Yes | No (form fields) |
| Initial UAC | 514 (disabled) → 512 (enabled) | 66048 (DONT_EXPIRE_PASSWD) |
| pwdLastSet=0 | Yes (force change) | No |
| Description | Rank + OU path | Form field or "Service Account for {operation}" |
| Password expire | Yes (must change) | No (if PasswordNeverExpires checked) |

### 7.4 UPN Suffix Priority

```
1. upn_suffix (configured in naming config)
   → fokirC-13088@sub.whildc.com
2. Auto-detected from AD root DSE
   → fokirC-13088@whildc.com
3. Extracted from domain config base_dn
   → fokirC-13088@whildc.com
4. domain.local (fallback)
```

---

## 8. Group Assignment

### 8.1 Group Membership Mechanisms

Two mechanisms exist, evaluated in order:

1. **Manual Groups** (from `GroupMembers` param — semicolon-delimited DNs)
2. **Auto-Created OU Group** (if no manual groups specified)

### 8.2 Manual Groups

```php
$manualGroups = trim((string) ($params['GroupMembers'] ?? ''));
if ($manualGroups !== '') {
    $groupDns = explode(';', $manualGroups);
    foreach ($groupDns as $gd) {
        // Add user as member of each group DN
        $addMember = [['attrib' => 'member', 'modtype' => LDAP_MODIFY_BATCH_ADD,
                       'values' => [$existingDn]]];
        @ldap_modify_batch($connection, $gd, $addMember);
    }
}
```

### 8.3 Auto-Created OU Group

If no manual groups are provided, the system creates/looks up a group matching the lowest OU level:

| Step | Action |
|------|--------|
| 1 | Get lowest OU name (e.g. `Web Team`) |
| 2 | Sanitize name (remove special chars) |
| 3 | Build group name: `{sanitized_name} Group` (e.g. `Web Team Group`) |
| 4 | Search AD for existing group |
| 5 | Not found? → Create with `groupType: -2147483646` |
| 6 | Add user as member |

### 8.4 Group Placement

Groups are created **inside the same OU path**:

```
DN: CN=Web Team Group,OU=Web Team,OU=AccessPilot,...,DC=whildc,DC=com
```

---

## 9. Operations: Unlock / Reset / Enable / Disable / Modify

**All use** `ldap_resolve_user_for_handler()` (`ldap_helpers.php:92`) with the **3-tier search strategy**.

### 9.1 The 3-Tier Search Strategy

```
ldap_resolve_user_for_handler(connection, baseDn, username, attributes)
│
├── Tier 1: Exact match (sAMAccountName={username})
│   └── Found? → return user immediately
│
├── Tier 2: Prefix wildcard (sAMAccountName=*{username})
│   ├── 1 match → return user (updates $username to resolved name)
│   ├── Multiple + has dash → suffix match (-{number}) → return best
│   └── No match / ambiguous → continue
│
├── Tier 3: Numeric wildcard (sAMAccountName=*{digits}*)
│   ├── 1 match → return user (updates $username)
│   ├── Multiple + has dash → suffix match → return best
│   └── No match / ambiguous → continue
│
└── return [] → "ERROR: User 'X' not found in Active Directory."
```

### 9.2 The Dash Guard

Suffix matching (picking the user ending with `-{number}`) only applies when input **contains a dash (`-`)**:

| Input | Has dash? | Tier 2 behavior |
|-------|-----------|-----------------|
| `C-13088` | ✅ Yes | Prefix matches `fokirC-13088` → suffix `-13088` picks it |
| `13088` | ❌ No | Multiple prefix matches → skip suffix → Tier 3 → "Multiple matching IDs" |

This prevents **silently picking the wrong user** when the operator enters a plain number.

### 9.3 Per-Operation Details

| Operation | Function | LDAP Action | Attributes Requested |
|-----------|----------|-------------|---------------------|
| **Enable** | `ldap_user_writer_set_enabled` | Clear bit 1 of `userAccountControl` | `['dn', 'userAccountControl']` |
| **Disable** | `ldap_user_writer_set_enabled` | Set bit 1 of `userAccountControl` | `['dn', 'userAccountControl']` |
| **Unlock** | `ldap_user_writer_unlock` | Set `lockoutTime = 0` | `['dn', 'lockoutTime']` |
| **Reset Password** | `ldap_user_writer_reset_password` | Set `unicodePwd`, `lockoutTime=0`, `pwdLastSet=0` | `['dn']` |
| **Modify** | `ldap_user_writer_update` | Various attribute modifications | `['dn', 'sAMAccountName', 'displayName', 'cn', ...]` |

### 9.4 Example Resolution Paths

**Enter `C-13088`:**
```
Tier 1: sAMAccountName=C-13088       → not found
Tier 2: sAMAccountName=*C-13088      → matches "fokirC-13088" (1 match) → FOUND
         ↓ $username updated to "fokirC-13088"
         ↓ Operation proceeds with resolved name
```

**Enter `fokirC-13088`:**
```
Tier 1: sAMAccountName=fokirC-13088  → FOUND immediately
         ↓ No fallback needed
```

**Enter `13088`:**
```
Tier 1: sAMAccountName=13088         → not found
Tier 2: sAMAccountName=*13088        → multiple matches (fokirC-13088, islam13088)
         Input has no dash → skip suffix matching → fall through
Tier 3: sAMAccountName=*13088*       → still multiple → return []
         ↓ "ERROR: User '13088' not found in Active Directory."
```

---

## 10. Server Information Card

**File:** `ldap_user_repository.php:117` — `ldap_user_repository_find()`

### 10.1 Lookup Flow

```
User enters ID in search box
         ↓
  ldap_user_lookup_entry()
   - Exact match: sAMAccountName / userPrincipalName / cn
         ↓
  null? → Prefix wildcard: samAccountName=*{input}
         → 1 match?  → show info card
         → Multiple? → has dash? → suffix match → show info card
                      → no dash  → fall through
         ↓
  null? → Numeric wildcard: samAccountName=*{digits}*
         → 1 match?  → show info card (resolved name)
         → Multiple? → "💡 Multiple matching IDs: fokirC-13088, islam13088"
                      + suggestions array for frontend
         ↓
  null? → "User 'X' not found in Active Directory."
```

### 10.2 Info Card Sections

| Section | Attributes Displayed |
|---------|---------------------|
| **Identity** | sAMAccountName, userPrincipalName, displayName |
| **Account** | userAccountControl, lockoutTime, pwdLastSet, whenCreated, accountExpires |
| **Contact** | mail, telephoneNumber, physicalDeliveryOfficeName |
| **Organization** | title, department, company, manager |
| **HRMS Info** | (separate API call — shows Employee ID, EMP Code) |

### 10.3 Multiple Matches UX

When the numeric wildcard finds multiple matches, the response includes:

```json
{
    "success": false,
    "message": "User '13088' not found.\n\n💡 Multiple matching IDs: fokirC-13088, islam13088",
    "suggestions": {
        "13088": ["fokirC-13088", "islam13088"]
    }
}
```

The frontend parses `suggestions` to show a selectable list, or falls back to parsing the `💡 Multiple matching IDs:` text.

---

## 11. End-to-End Flow Diagrams

### 11.1 New User Creation (No Existing AD Account)

```
Administrator          Portal              HRMS API              AD
    │                    │                    │                  │
    │  Enter "C-13088"   │                    │                  │
    │──────────────────► │                    │                  │
    │                    │  getHRMSInfo()     │                  │
    │                    │──────────────────►│                  │
    │                    │◄───────────────────│                  │
    │                    │  {EMP_NAME,EMAIL,  │                  │
    │                    │   DEPT,SECTION,...} │                  │
    │                    │                    │                  │
    │  Click "Create"    │                    │                  │
    │──────────────────► │                    │                  │
    │                    │  Open LDAP connection                │
    │                    │  generatedUsername =                 │
    │                    │    ldap_generate_username_from_name  │
    │                    │    (→ "fokirC-13088")                │
    │                    │                    │                  │
    │                    │  Resolve OU path   │                  │
    │                    │  Build hierarchy   │                  │
    │                    │                    │                  │
    │                    │  Search: sAMAccountName=fokirC-13088 │
    │                    │─────────────────────────────────────►│
    │                    │◄─────────────────────────────────────│
    │                    │  Not found          │                  │
    │                    │                    │                  │
    │                    │  Create OUs (if missing)             │
    │                    │─────────────────────────────────────►│
    │                    │  Create AD user                      │
    │                    │  Set password                        │
    │                    │  Enable account                      │
    │                    │  pwdLastSet=0                        │
    │                    │  Set description                     │
    │                    │  Add to group                        │
    │                    │─────────────────────────────────────►│
    │                    │◄─────────────────────────────────────│
    │◄───────────────────│                    │                  │
    │  Success message   │                    │                  │
```

### 11.2 Existing User Detection (Enter AD Logon Name)

```
Administrator          Portal              HRMS API              AD
    │                    │                    │                  │
    │  Enter "fokirC-13088"                  │                  │
    │──────────────────► │                    │                  │
    │                    │  getHRMSInfo("fokirC-13088")         │
    │                    │  → numeric fallback "13088"          │
    │                    │──────────────────►│                  │
    │                    │◄───────────────────│                  │
    │                    │  ⚠ Returns WRONG employee's data    │
    │                    │                    │                  │
    │  Click "Create"    │                    │                  │
    │──────────────────► │                    │                  │
    │                    │  generatedUsername = computed from   │
    │                    │    wrong HRMS data → doesn't match   │
    │                    │                    │                  │
    │                    │  Search Step 1: by generated name    │
    │                    │─────────────────────────────────────►│
    │                    │◄─────────────────────────────────────│
    │                    │  Not found          │                  │
    │                    │                    │                  │
    │                    │  Search Step 2: by raw "fokirC-13088"│
    │                    │─────────────────────────────────────►│
    │                    │◄─────────────────────────────────────│
    │                    │  FOUND! → foundByRawFallback=true    │
    │                    │                    │                  │
    │                    │  Existing handler (skip OU/HRMS)    │
    │                    │  Reset password                      │
    │                    │  Force pwd change                    │
    │                    │  Add to group                        │
    │                    │─────────────────────────────────────►│
    │                    │◄─────────────────────────────────────│
    │◄───────────────────│                    │                  │
    │  "HRMS ID 'C-13088' — AD ID 'fokirC-13088' already exists"
```

### 11.3 Quick Operations (Unlock / Reset / Enable / Search)

```
Administrator          Portal                              AD
    │                    │                                  │
    │  Enter "C-13088"   │                                  │
    │  Click "Unlock"    │                                  │
    │──────────────────► │                                  │
    │                    │  3-Tier Search:                  │
    │                    │  Tier 1: sAMAccountName=C-13088  │
    │                    │─────────────────────────────────►│
    │                    │◄─────────────────────────────────│
    │                    │  Not found                       │
    │                    │                                  │
    │                    │  Tier 2: sAMAccountName=*C-13088 │
    │                    │─────────────────────────────────►│
    │                    │◄─────────────────────────────────│
    │                    │  1 match: "fokirC-13088"         │
    │                    │  $username → "fokirC-13088"      │
    │                    │                                  │
    │                    │  lockoutTime = 0                 │
    │                    │─────────────────────────────────►│
    │                    │◄─────────────────────────────────│
    │◄───────────────────│                                  │
    │  "User 'fokirC-13088' unlocked successfully."         │
```

### 11.4 Plain Number (Ambiguous — Multiple Matches)

```
Administrator          Portal                              AD
    │                    │                                  │
    │  Enter "13088"     │                                  │
    │  Click "Search"    │                                  │
    │──────────────────► │                                  │
    │                    │  ldap_user_lookup_entry("13088") │
    │                    │─────────────────────────────────►│
    │                    │◄─────────────────────────────────│
    │                    │  Not found                       │
    │                    │                                  │
    │                    │  Tier 2: samAccountName=*13088   │
    │                    │─────────────────────────────────►│
    │                    │◄─────────────────────────────────│
    │                    │  Multiple: fokirC-13088, islam13088
    │                    │  No dash → skip suffix matching  │
    │                    │                                  │
    │                    │  Tier 3: samAccountName=*13088*  │
    │                    │─────────────────────────────────►│
    │                    │◄─────────────────────────────────│
    │                    │  Still multiple                   │
    │                    │                                  │
    │◄───────────────────│                                  │
    │  "💡 Multiple      │                                  │
    │   matching IDs:    │                                  │
    │   fokirC-13088,    │                                  │
    │   islam13088"      │                                  │
```

---

## Key Files

| File | Purpose |
|------|---------|
| `app/Ldap/Operations/ldap_user_writer.php` | Main create flow + existing handler |
| `app/Ldap/Support/ldap_helpers.php` | `ldap_resolve_user_for_handler()` — 3-tier search |
| `app/Ldap/Operations/ldap_user_repository.php` | Info card lookup + `ldap_user_lookup_entry()` |
| `app/Domain/HRMS/directory_info_service.php` | `getHRMSInfo()` — HRMS API with numeric fallback |
| `app/Ldap/Support/ldap_helpers.php` | `ldap_generate_username_from_name()` — naming convention engine |

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| **Step 2 (raw username fallback)** | Catches case where operator enters AD logon name instead of HRMS code |
| **`foundByRawFallback` guard** | Prevents applying wrong HRMS data (OU move, info update) when HRMS returned wrong employee |
| **Dash-guarded suffix matching** | Plain number (`13088`) won't silently pick a user ending with `-13088` |
| **Dual-ID message format** | Clearly shows both identities when HRMS code ≠ AD sAMAccountName |
| **Force password change** | `pwdLastSet=0` applied for both new and existing users, but not shown in action messages |
