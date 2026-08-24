# WHILDC Domain — Complete User Creation & Resolution Process

> **Document Date:** 2026-07-06
> **Domain Key:** `whildc.com`
> **AD Server:** `DC-AD1.WHILDC.COM` (192.168.119.169)
> **Naming Mode:** `last_name_id` (surname + emp_code)

---

## Table of Contents

1. [Overview](#1-overview)
2. [User ID — HRMS vs AD](#2-user-id--hrms-vs-ad)
3. [User Resolution (3-Tier Search Strategy)](#3-user-resolution-3-tier-search-strategy)
4. [Server Information Card (Info Lookup)](#4-server-information-card-info-lookup)
5. [New User Creation Flow](#5-new-user-creation-flow)
6. [Existing User Handling (on Create)](#6-existing-user-handling-on-create)
7. [Operations: Unlock / Reset / Enable / Disable / Modify](#7-operations-unlock--reset--enable--disable--modify)
8. [Scenarios & Edge Cases](#8-scenarios--edge-cases)
9. [Code Flow Diagrams](#9-code-flow-diagrams)

---

## 1. Overview

The system bridges **two identity sources**:

| Source | What it provides |
|--------|-----------------|
| **HRMS (API)** | Employee data: name, email, department, section, designation, office, phone, rank, company, product |
| **Active Directory** | User objects with sAMAccountName, UPN, group membership, OU location, account status |

The core challenge: **the entered username may be either an HRMS employee code (`C-13088`) or an existing AD logon name (`fokirC-13088`).** The system must correctly resolve which AD user the operator intends, even when HRMS returns data for the wrong person.

---

## 2. User ID — HRMS vs AD

### 2.1 Example: Shohag Fokir

| Attribute | Value |
|-----------|-------|
| HRMS Employee Code | `C-13088` |
| AD sAMAccountName | `fokirC-13088` |
| HRMS numeric-only fallback | `13088` (extracted via `preg_replace('/[^0-9]/','',$username)`) |

### 2.2 Two Different People Can Share the Same Number

| Person | HRMS Code | AD sAMAccountName | HRMS numeric fallback |
|--------|-----------|-------------------|----------------------|
| Shohag Fokir | `C-13088` | `fokirC-13088` | `13088` |
| Islam (different) | (different) | `islam13088` | `13088` |

This is the root cause of ambiguity — **both users contain `13088`** in their AD logon name. A naive search for `13088` matches both.

---

## 3. User Resolution (3-Tier Search Strategy)

When any operation needs to find an AD user, the system uses a **3-tier search** defined in `ldap_resolve_user_for_handler()` (`ldap_helpers.php:92`).

### Tier 1: Exact Match

```
sAMAccountName = {exact input}
```

- **Input `C-13088`** → search for `C-13088` → not found (AD name is `fokirC-13088`)
- **Input `fokirC-13088`** → search for `fokirC-13088` → **FOUND** (returns immediately)

### Tier 2: Prefix Wildcard

```
sAMAccountName = *{full input}
```

Matches entries **ending with** the full input string.

- **Input `C-13088`** → `*C-13088` → matches `fokirC-13088` (ends with `C-13088`) → **FOUND**
- **Input `fokirC-13088`** → Tier 1 already resolved, skipped

#### If Multiple Prefix Matches Found:

The system checks if the input contains a **dash (`-`)** before attempting disambiguation:

- **Has dash** (e.g. `C-13088`): picks the user whose sAMAccountName ends with `-{number}` (e.g. `-13088`). For `fokirC-13088`: `substr('fokirC-13088', -6) === '-13088'` → match ✓
- **No dash** (e.g. `13088`): **skips suffix matching** entirely → falls to Tier 3 → shows "Multiple matching IDs"

### Tier 3: Broad Numeric Wildcard (Last Resort)

```
sAMAccountName = *{numeric_part}*
```

Only triggers when input ends with digits (e.g. `C-13088` → `13088`, or `13088` → `13088`).

- **Exactly 1 match**: returns it
- **Multiple matches**: shows `💡 Multiple matching IDs: fokirC-13088, islam13088`

---

## 4. Server Information Card (Info Lookup)

**File:** `ldap_user_repository.php:117` — `ldap_user_repository_find()`

The info card uses **the same 3-tier strategy** with additional detail:

### Flow

```
User enters ID in search box
         ↓
  ldap_user_lookup_entry()
   - Tries sAMAccountName / userPrincipalName / cn exact match
         ↓
  null? → Prefix wildcard (*{full input})
         → 1 match?  → show info card
         → Multiple? → has dash → suffix match → show info card
                      → no dash  → fall through
         ↓
  null? → Broad numeric wildcard (*{num}*)
         → 1 match?  → show info card (updates $username to resolved name)
         → Multiple? → show "💡 Multiple matching IDs: ..." with suggestions
         ↓
  null? → "User 'X' not found."
```

### What the Info Card Shows

| Section | Content |
|---------|---------|
| **Identity** | Logon Name, Principal ID, Display Name |
| **Account** | UAC status, Lockout, pwdLastSet, created date |
| **Contact** | Email, Phone, Office |
| **Organization** | Title, Department, Company, Manager |
| **HRMS Info** | Employee ID, EMP Code (from HRMS API, separate call) |

---

## 5. New User Creation Flow

**File:** `ldap_user_writer.php:537` — `ldap_user_writer_create()`

```
User enters ID and clicks "New User"
         ↓
   ┌─ Is Service Account? ──→ Skip HRMS, use form fields directly
   │
   └─ Regular user
         ↓
   ldap_writer_hrms_api($username) — fetches HRMS data
         ↓
   HRMS found? → Extract: empCode, fullName, email, department, etc.
   Not found?  → Use form fields (DisplayName, OU)
         ↓
   Enter closure (LDAP connection opened)
         ↓
   ldap_generate_username_from_name(fullName, empCode, namingConfig)
     → generates expected AD username (e.g. "fokirC-13088")
         ↓
   Resolve OU path from HRMS fields (company → department → section → ...)
         ↓
   ┌─────────────────────────────────────────────────────┐
   │           EXISTING USER DETECTION                   │
   ├─────────────────────────────────────────────────────┤
   │                                                     │
   │  Step 1: Search by GENERATED username               │
   │  sAMAccountName = {generatedUsername}               │
   │                                                     │
   │  Not found? AND generatedUsername ≠ raw input?      │
   │                                                     │
   │  Step 2: Search by RAW entered username (fallback)  │
   │  sAMAccountName = {raw $username}                   │
   │  → Found? → mark foundByRawFallback = true          │
   │                                                     │
   │  Found? → Go to Existing User Handler (Section 6)  │
   │  Not found? → Go to Create New User (Section 5.1)  │
   └─────────────────────────────────────────────────────┘
```

### 5.1 New User — AD Entry Creation

When user does NOT exist, the system:

1. **Parses name** into givenName (first_non_prefix) and sn (last_part)
2. **Builds DN:** `CN={displayName},{expectedOUPath}`
3. **Generates UPN:** `{sAMAccountName}@{domain}`
4. **Creates entry** with: sAMAccountName, userPrincipalName, cn, givenName, sn, displayName, userAccountControl (disabled = 514), mail, telephoneNumber, title, department, company, physicalDeliveryOfficeName
5. **Sets password** (default: `CRESET@1234`)
6. **Enables account** (UAC = 512), forces password change on next login (pwdLastSet = 0)
7. **Sets description** (rank + OU path)
8. **Adds to group** (manual groups or auto-created OU group)

### 5.2 Dual-ID Display Format

When HRMS code differs from AD sAMAccountName:

```
HRMS ID 'C-13088' — AD ID 'fokirC-13088'
```

When same (wgbd domain, `emp_code` mode):

```
User ID '59022'
```

---

## 6. Existing User Handling (on Create)

When the system detects an existing user **during the Create flow**, it applies corrections rather than creating a duplicate:

```
User exists
  ↓
Check if foundByRawFallback? (HRMS data unreliable)
  ↓
  YES → Skip OU check and HRMS info update
          (HRMS data belongs to different employee)
  NO  → Proceed with OU compare and HRMS update
  ↓
[OU Check — only if NOT foundByRawFallback]
  Compare current DN's OU vs expected OU path
  Mismatch? → ldap_rename() to correct OU
           → mark isMoved = true
  ↓
[HRMS Info Update — only if NOT foundByRawFallback]
  Compare AD attributes vs HRMS data:
    displayName, mail, telephoneNumber, title, physicalDeliveryOfficeName
  Any different? → ldap_modify_batch() to update
  ↓
[Account Enable]
  Is disabled (UAC & 2)? → clear bit 1 → enable
  ↓
[Password Reset]
  Set default password
  Force change on next login (pwdLastSet = 0)
  ↓
[Group Membership]
  Add to manual groups (from GroupMembers param)
  OR add to auto-created OU group
  ↓
[Return Message]
  "HRMS ID 'C-13088' — AD ID 'fokirC-13088' already exists
   With preferred object location.
   Quick Action:> Password reset triggered. ..."
```

---

## 7. Operations: Unlock / Reset / Enable / Disable / Modify

**All use** `ldap_resolve_user_for_handler()` with the 3-tier strategy.

| Operation | Function | LDAP Action |
|-----------|----------|-------------|
| **Enable** | `ldap_user_writer_set_enabled` | Clear bit 1 of userAccountControl |
| **Disable** | `ldap_user_writer_set_enabled` | Set bit 1 of userAccountControl |
| **Unlock** | `ldap_user_writer_unlock` | Set lockoutTime = 0 |
| **Reset Password** | `ldap_user_writer_reset_password` | Set unicodePwd, lockoutTime = 0, pwdLastSet = 0 |
| **Modify** | `ldap_user_writer_update` | Various attribute updates |

### Resolution Path (Example: Enter `C-13088`)

```
Tier 1: sAMAccountName=C-13088       → not found
Tier 2: sAMAccountName=*C-13088      → matches "fokirC-13088" (1 match) → FOUND
         ↓
  Returns user, updates $username to "fokirC-13088"
  Success message shows resolved name
```

### Resolution Path (Example: Enter `13088`)

```
Tier 1: sAMAccountName=13088         → not found
Tier 2: sAMAccountName=*13088        → multiple matches
         Input has no dash → skip suffix matching → fall through
Tier 3: sAMAccountName=*13088*       → multiple matches
         → return [] → "ERROR: User '13088' not found."
```

---

## 8. Scenarios & Edge Cases

### Scenario A: Enter HRMS Code for Existing User

| Step | Detail |
|------|--------|
| **Input** | `C-13088` |
| **HRMS lookup** | Finds employee `C-13088` → returns Fokir's data (correct) |
| **Generated username** | `ldap_generate_username_from_name('Shohag Fokir', 'C-13088', ...)` → `fokirC-13088` |
| **User search** | `sAMAccountName=fokirC-13088` → **FOUND** |
| **Action** | Existing user: OU check, HRMS update, password reset, group add |

### Scenario B: Enter AD Logon Name Instead of HRMS Code

| Step | Detail |
|------|--------|
| **Input** | `fokirC-13088` |
| **HRMS lookup** | `getHRMSInfo('fokirC-13088')` → strips to `13088` → **finds wrong employee** |
| **Generated username** | `ldap_generate_username_from_name(wrongName, '13088', ...)` → `something13088` (wrong!) |
| **User search (Step 1)** | `sAMAccountName=something13088` → not found |
| **User search (Step 2)** | `sAMAccountName=fokirC-13088` → **FOUND** (via raw input fallback) |
| **foundByRawFallback** | `true` → **skips OU check and HRMS info update** (HRMS data unreliable) |
| **Action** | Enable (if disabled), password reset, group add only |

### Scenario C: Enter Plain Number (Ambiguous)

| Step | Detail |
|------|--------|
| **Input** | `13088` |
| **HRMS lookup** | Finds employee `13088` (different person with EMP Code `589982`) |
| **Generated username** | Uses HRMS name → generates wrong username |
| **User search** | Not found by generated name |
| **Fallback search** | Not found by raw input either (no AD user with exact `13088`) |

**For Info Card:** Shows HRMS info for employee `13088` (correct per HRMS), but AD shows `💡 Multiple matching IDs: fokirC-13088, islam13088`

**For Operations:** `ldap_resolve_user_for_handler()` returns empty → "ERROR: User '13088' not found in Active Directory."

### Scenario D: Enter Exact AD Logon Name (Direct Hit)

| Step | Detail |
|------|--------|
| **Input** | `fokirC-13088` |
| **Tier 1 search** | `sAMAccountName=fokirC-13088` → **FOUND** immediately |
| **Resolution** | Returns at Tier 1, no fallback needed |

### Scenario E: Create New User (No Existing AD Account)

| Step | Detail |
|------|--------|
| **Input** | `C-13088` (new employee, no AD account) |
| **HRMS lookup** | Finds employee → returns name, department, etc. |
| **Generated username** | `fokirC-13088` |
| **User search** | Not found (no existing AD user with that name) |
| **OU created** | Auto-creates OU path (company → department → section → ...) |
| **User created** | sAMAccountName `fokirC-13088`, displayName from HRMS, password set, enabled |

### Scenario F: HRMS ID with Different Domain Naming (wgbd)

| Step | Detail |
|------|--------|
| **Domain** | wgbd.com (naming mode = `emp_code`) |
| **Input** | `59022` |
| **Generated username** | `59022` (emp_code mode, no name transformation) |
| **User search** | `sAMAccountName=59022` → **FOUND** |
| **ID display** | `User '59022'` (emp_code === generatedUsername, so no dual ID) |

---

## 9. Code Flow Diagrams

### 9.1 `ldap_resolve_user_for_handler()` — Used by: Unlock / Reset / Enable/Disable / Modify

```
ldap_resolve_user_for_handler(connection, baseDn, username, attributes)
│
├─ Exact match: (sAMAccountName={username})
│   └─ Found? → return user
│
├─ Prefix wildcard: (sAMAccountName=*{username})
│   ├─ 1 match? → return user (update $username)
│   ├─ Multiple + has dash? → suffix match (-{number}) → return best match
│   └─ No match / ambiguous → continue
│
├─ Numeric wildcard: (sAMAccountName=*{digits}*)
│   ├─ 1 match? → return user (update $username)
│   ├─ Multiple + has dash? → suffix match (-{number}) → return best match
│   └─ No match / ambiguous → continue
│
└─ return [] (not found)
```

### 9.2 `ldap_user_writer_create()` — New User + Existing Handler

```
ldap_user_writer_create(params, executedBy)
│
├─ isServiceAccount → skip HRMS, use form fields
│
├─ HRMS lookup → empCode, fullName, email, department, section, etc.
│
├─ Enter closure (LDAP connection)
│   │
│   ├─ namingConfig = config['naming']
│   ├─ generatedUsername = ldap_generate_username_from_name(fullName, empCode, namingConfig)
│   │
│   ├─ Resolve OU path (from HRMS fields or explicit OU param)
│   │
│   ├─ Search AD for generatedUsername (exact match)
│   │   └─ Not found? Search AD for raw $username (exact match)
│   │       └─ Found? → foundByRawFallback = true
│   │
│   ├─ [FOUND] ──→ Existing user handler
│   │   ├─ foundByRawFallback? → skip OU/HRMS updates
│   │   ├─ OU check + move (if !foundByRawFallback)
│   │   ├─ HRMS field update (if !foundByRawFallback)
│   │   ├─ Enable if disabled
│   │   ├─ Reset password + pwdLastSet=0
│   │   └─ Group membership
│   │
│   └─ [NOT FOUND] ──→ Create new user
│       ├─ Parse name → givenName / sn / displayName
│       ├─ Build DN + UPN + LDAP entry
│       ├─ ldap_add()
│       ├─ Set password + enable + pwdLastSet=0
│       ├─ Description
│       └─ Group membership
│
└─ Return JSON result
```

### 9.3 `ldap_user_repository_find()` — Info Card Lookup

```
ldap_user_repository_find(params, executedBy)
│
├─ ldap_user_lookup_entry() → exact match on sAMAccountName / UPN / cn
│   └─ Found? → show info card
│
├─ Prefix wildcard: (samAccountName=*{input})
│   ├─ 1 match? → show info card
│   ├─ Multiple + has dash? → suffix match → show info card
│   └─ No match / ambiguous → continue
│
├─ Numeric wildcard: (samAccountName=*{digits}*)
│   ├─ 1 match? → show info card (resolved)
│   ├─ Multiple? → "💡 Multiple matching IDs: ..." + suggestions
│   └─ No match → continue
│
└─ "User 'X' not found."
```

---

## Key Files

| File | Purpose |
|------|---------|
| `app/Ldap/Support/ldap_helpers.php` | `ldap_resolve_user_for_handler()` — 3-tier search (ops) |
| `app/Ldap/Operations/ldap_user_writer.php` | `ldap_user_writer_create()` — new user + existing handler |
| `app/Ldap/Operations/ldap_user_repository.php` | `ldap_user_repository_find()` — info card + `ldap_user_lookup_entry()` |
| `app/Domain/HRMS/directory_info_service.php` | `getHRMSInfo()` — HRMS API call with numeric fallback |

## Key Decision: Why Dash-Guarded Suffix Matching?

The `str_contains($username, '-')` guard prevents automatic resolution when the input is a plain number:

| Input | Has dash? | Behavior |
|-------|-----------|----------|
| `C-13088` | ✅ Yes | Prefix wildcard → suffix `-13088` match → picks `fokirC-13088` |
| `13088` | ❌ No | Prefix wildcard hits multiple → skips suffix → falls to Tier 3 → "Multiple matching IDs" |

This avoids silently picking `fokirC-13088` when the operator entered `13088` (which could refer to a different employee).
