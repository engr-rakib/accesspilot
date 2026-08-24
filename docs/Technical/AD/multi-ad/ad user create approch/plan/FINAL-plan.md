# FINAL PLAN: Per-Domain AD User Creation Customization

> **Important:** This is a CUSTOMIZATION feature, NOT a default AD behavior.
> Each domain admin configures it their own way from UI.
> Domains without configuration get the default `emp_code` behavior (zero change).

---

## 1. Case Study Verification (Real User)

### Reference: "Md. Abdus Sobur" (ID: 59022)

```
Full Name:          Md. Abdus Sobur
Parts:              ["Md.", "Abdus", "Sobur"]
                      │        │         │
                      │        │         └── Surname (Sobur)
                      │        │
                      │        └── Given Name (Abdus)
                      │
                      └── Honorific Prefix (skip)
```

### Expected AD Attributes

| Attribute | Value | How |
|-----------|-------|-----|
| **sAMAccountName** | `sobur59022` | surname + empCode (`last_name_id`) |
| **userPrincipalName** | `sobur59022@domain.com` | same + @domain |
| **givenName** | `Abdus` | first non-prefix part |
| **sn (surname)** | `Sobur` | last non-prefix part (`last_part` mode) |
| **displayName** | `Md. Abdus Sobur` | ORIGINAL HRMS name (unchanged) |
| **cn** | `Md. Abdus Sobur` | ORIGINAL HRMS name (unchanged) |
| **mail, title, dept, etc.** | from HRMS | unchanged |

### More Examples (Verified)

| HRMS Name | ID | sAMAccountName | givenName | sn | displayName |
|-----------|-----|---------------|-----------|-----|-------------|
| Md. Abdus Sobur | 59022 | sobur59022 | Abdus | Sobur | Md. Abdus Sobur |
| Md. Rakib Hossain Niloy | 77896 | niloy77896 | Rakib | Niloy | Md. Rakib Hossain Niloy |
| Mr. John William Smith | 12345 | smith12345 | John | Smith | Mr. John William Smith |
| Dr. Sarah Ahmed | 45678 | ahmed45678 | Sarah | Ahmed | Dr. Sarah Ahmed |
| Rakib Hasan | 33456 | hasan33456 | Rakib | Hasan | Rakib Hasan |

### Surname Strategy: `last_part` (Recommended)
- Bengali/Asian names: surname is the LAST part
- `after_given_name` wrongly includes middle names as surname
- Example: "Md. Rakib Hossain Niloy" → `last_part` = "Niloy" ✅ vs `after_given_name` = "Hossain Niloy" ❌

---

## 2. Feature Scope (What This Covers)

### Part A: sAMAccountName Generation ✅ (Already Implemented)
- `ldap_generate_username_from_name()` — creates `sobur59022` from name + code
- Per-domain: `mode`, `exclude_prefixes`, `case`, `separator`
- Modes: `emp_code`, `first_non_prefix_id`, `last_name_id`, `index:N_id`, `full_name_slug_id`

### Part B: givenName/sn Name Parsing 🔧 (Needs Implementation)
- Replace naive `preg_split('/\s+/', $fullName, 2)` with proper name parser
- Skip common prefixes for givenName
- Extract surname using `surname_mode` (per-domain config)

### What is NOT Changed
- displayName = HRMS full name (preserved as-is)
- cn = HRMS full name (preserved as-is)
- All other HRMS attributes (mail, title, department, company, etc.)
- Emp_code mode domains (no naming config → current behavior)

---

## 3. Configuration Schema (Per-Domain)

```json
{
  "key": "whildc",
  "label": "WHILD Corporate",
  ...
  "naming": {
    "mode": "last_name_id",
    "exclude_prefixes": ["md.", "md", "mr.", "mrs.", "dr.", "prof.", "mohammad", "muhammad"],
    "case": "lowercase",
    "separator": "",
    "surname_mode": "last_part"
  }
}
```

### Existing Fields (already implemented)
| Field | Purpose | Values |
|-------|---------|--------|
| `mode` | sAMAccountName format | `emp_code` (default), `last_name_id`, `first_non_prefix_id`, `index:N_id`, `full_name_slug_id` |
| `exclude_prefixes` | Prefixes to skip | array of strings |
| `case` | Case for sAMAccountName | `lowercase`, `uppercase`, `as_is` |
| `separator` | Separator for full_name_slug | string |

### New Field (to implement)
| Field | Purpose | Values |
|-------|---------|--------|
| `surname_mode` | How to extract surname for givenName/sn | `last_part` (default), `after_given_name` |

When `mode` is `emp_code`, name parsing is not applied (givenName/sn stay as naive split).

---

## 4. Implementation Steps

### Step 1: Create `ldap_parse_full_name()` helper
**File:** `app/Ldap/Support/ldap_helpers.php`

```php
function ldap_parse_full_name(string $fullName, array $config = []): array
```

Returns: `['given_name' => ..., 'surname' => ..., 'all_parts' => [...], 'prefix_skipped' => bool]`

Logic:
- Extract `exclude_prefixes` and `surname_mode` from config
- Split name by whitespace/dots
- Filter out excluded prefixes
- given_name = first filtered part (or full name if nothing filtered)
- surname = last part (`last_part`) OR everything after given_name (`after_given_name`)
- Return structured array

### Step 2: Refactor `ldap_generate_username_from_name()`
- Use `ldap_parse_full_name()` internally instead of duplicating skip-prefix logic
- Build username from `all_parts` + `empCode` (same as before)

### Step 3: Update `ldap_user_writer_create()` givenName/sn
**File:** `app/Ldap/Operations/ldap_user_writer.php`

Replace:
```php
$nameParts = preg_split('/\s+/', $fullName, 2);
$firstName = $nameParts[0] ?? $fullName;
$lastName = $nameParts[1] ?? '';
```

With:
```php
$parsedName = ldap_parse_full_name($fullName, $namingConfig);
$firstName = $parsedName['given_name'];
$lastName = $parsedName['surname'];
```

### Step 4: Add `surname_mode` to UI
**File:** `resources/views/pages/tools/system_config_view.php`
- Add "Surname Mode" dropdown in the naming form section
- Options: `last_part` (default), `after_given_name`

**File:** `public/resources/frontend/js/admin/system_config_domains.js`
- Handle surname_mode in save/load/reset

**File:** `app/Application/Http/Controllers/domain_api.php`
- `surname_mode` is part of `naming` object, already saved as free-form JSON (no change needed)

---

## 5. Files to Modify

| # | File | Change |
|---|------|--------|
| 1 | `app/Ldap/Support/ldap_helpers.php` | Add `ldap_parse_full_name()`, refactor `ldap_generate_username_from_name()` to use it |
| 2 | `app/Ldap/Operations/ldap_user_writer.php` | Replace naive name split with parsed name for givenName/sn |
| 3 | `resources/views/pages/tools/system_config_view.php` | Add `surname_mode` dropdown in naming form |
| 4 | `public/resources/frontend/js/admin/system_config_domains.js` | Handle surname_mode in JS |

---

## 6. Test Cases

### Test 1: whildc domain with `last_name_id` + `last_part`
```
Input:  Md. Abdus Sobur (59022)
Result: sAMAccountName = sobur59022
        givenName = Abdus
        sn = Sobur
        displayName = Md. Abdus Sobur ✅
```

### Test 2: whildc domain without naming config (emp_code default)
```
Input:  Md. Abdus Sobur (59022)
Result: sAMAccountName = 59022
        givenName = Md. (current naive split)
        sn = Abdus Sobur
        displayName = Md. Abdus Sobur
```

### Test 3: wgbd domain (no naming config → full backward compat)
```
Input:  Any user
Result: Same as current behavior (emp_code mode)
```

---

## 7. Risk & Compatibility

- **Backward compatible:** No naming config → original behavior
- **Existing users unaffected:** Only new creation
- **No DB migration:** domains.json is free-form
- **No PowerShell scripts changed:** LDAP layer only
- **No override of displayName:** Always preserved as HRMS full name
