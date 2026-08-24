# Info Button — Server & Employee Information Retrieval

**Technical specification for fetching and displaying Active Directory and HRMS information for one or more users.**

> **Last Updated:** 2026-07-06
> **Primary Backend:** LDAP (PHP) with PowerShell fallback

---

## Table of Contents

1. [Overview](#1-overview)
2. [Input Resolution](#2-input-resolution)
3. [Server Info Card — AD Lookup (3-Tier Search)](#3-server-info-card--ad-lookup-3-tier-search)
4. [Employee Info Card — HRMS Lookup](#4-employee-info-card--hrms-lookup)
5. [Output Format — Two Cards](#5-output-format--two-cards)
6. [Tabbed Cards for Multi-User](#6-tabbed-cards-for-multi-user)
7. [Suggestions (Related IDs)](#7-suggestions-related-ids)
8. [System Architecture & Data Flow](#8-system-architecture--data-flow)
9. [API Reference](#9-api-reference)
10. [File Reference](#10-file-reference)

---

## 1. Overview

The **Info** button retrieves and displays side-by-side information about a user from two independent sources:

| Card | Source | Data Format |
|---|---|---|
| **Server Information** | Active Directory (LDAP) | Text block with labeled sections |
| **Employee Information** | HRMS API (JSON) | Structured key-value pairs |

Multiple users can be queried simultaneously by separating IDs with space, comma, or semicolon. Results are displayed in tabbed cards — one tab per user.

---

## 2. Input Resolution

### 2.1 Input Splitting

```javascript
// action_processor.js:468
const allUsers = user.split(/[\s,;]+/).map(u => u.trim()).filter(Boolean);
```

Supports: space, comma, semicolon, newline separation.

### 2.2 Per-User Resolution — AD (3-Tier Search)

Each user ID goes through `ldap_user_repository_find()` (`ldap_user_repository.php:117`), which uses a **3-tier search strategy**:

#### Tier 1 — Exact Match

```
ldap_user_lookup_entry() — searches by sAMAccountName, userPrincipalName, or name
```

- **Found?** → Formats and returns user info
- **Not found?** → Proceeds to Tier 2

#### Tier 2 — Prefix Wildcard

```
sAMAccountName = *{fullInput}
```

Matches entries **ending with** the full input string.

- **1 match** → return user info
- **Multiple matches + input has dash (`-`)** → suffix match by `-{number}` → return best match (e.g. `C-13088` picks `fokirC-13088` via suffix `-13088`)
- **Multiple matches + no dash** → skip suffix matching → fall through to Tier 3
- **No match** → fall through to Tier 3

#### Tier 3 — Broad Numeric Wildcard

```
sAMAccountName = *{digits}*
```

Only triggers when input ends with digits.

- **1 match** → return user info (resolves `$username` to actual sAMAccountName)
- **Multiple matches** → return as suggestions (see §7)
- **No match** → "User 'X' not found in Active Directory."

#### Resolution Examples

| Input | Tier 1 | Tier 2 | Tier 3 | Result |
|---|---|---|---|---|
| `fokirC-13088` | Exact match → FOUND | — | — | Returns immediately |
| `C-13088` | Not found | `*C-13088` → 1 match `fokirC-13088` | — | **FOUND** |
| `13088` | Not found | `*13088` → multiple, no dash | `*13088*` → multiple | `💡 Multiple matching IDs` |
| `islam13088` | Exact match → FOUND | — | — | Returns immediately |
| `99999` (no AD user) | Not found | Not found | Not found | "User not found" |

### 2.3 Per-User Resolution — HRMS

Each user ID is passed to the HRMS API:

```
GET https://whrmsapi.waltonbd.com/info/emp_info.php?emp_id={input}
```

- If the input is an AD username (non-numeric), the server strips non-digits via `preg_replace('/[^0-9]/', '', $username)` and retries
- ⚠ This numeric fallback may return a **different employee** if the number matches another record (e.g. `fokirC-13088` → `13088` → wrong employee)
- Returns the full HRMS JSON response as `apiData`

---

## 3. Server Info Card — AD Lookup (3-Tier Search)

### 3.1 Identity Section (always first)

Extracted from `infoOutput` text by `renderServerHtml()` (`action_processor.js:343`):

```
AD Account: mahi54593
User Principal ID: mahi54593@domain.com
```

| Display Label | Source Line Pattern |
|---|---|
| `Logon Name` | `/^AD Account:\s*/i` or `/^sAMAccount:\s*/i` or `/^logon name:\s*/i` |
| `Principal ID` | `/^User Principal ID:\s*/i` |

The identity fields are displayed with distinct styling (bold labels, colored values) at the top of the card.

### 3.2 Content Sections

The remaining `infoOutput` text is split into sections by line-by-line parsing:

| Section | Detected By |
|---|---|
| Current User Conditions | Line content matches condition keywords (locked, enabled, disabled, password, etc.) |
| Assigned Privileges | Line content matches privilege keywords (admin, operator, etc.) |
| User Activity | `lastlogon`, `lastlogontimestamp`, `logoncount`, `badpwdcount`, etc. |
| Infrastructure Information | `created`, `modified`, `objectcategory`, `distinguishedname`, `cn`, `manager`, `description`, `office`, `company`, `department`, `title`, `streetaddress` |
| User Profiling Information | `profilepath`, `home directory`, `scriptpath`, `account expires`, `logonworkstations` |
| User Information | All remaining lines not matched above |

Each label-value pair is displayed as a styled row. Status values (Enabled/Disabled/Locked/Unlocked) get color-coded badges via `formatServerStatusValue()`.

### 3.3 Suggestions Notice

If the response contains `suggestions`, a notice is appended at the bottom:

```
Multiple matching IDs found. Nearby IDs: 54593, 54600, ...
```

These suggested IDs are auto-fetched and appear as additional tabs (see §7).

---

## 4. Employee Info Card — HRMS Lookup

### 4.1 Identity Section

```
Employee ID (EMP_CODE): 54593
EMP Code (EMP_ID): 54593
```

Two lines rendered at the top: `EMP_CODE` and `EMP_ID` from HRMS API response. Both get distinct styling (different colors) matching the server card identity style.

### 4.2 Field Groups

The `apiData` (HRMS API JSON response) is organized into three visual groups by `renderHrmsHtml()` (`action_processor.js:406`):

| Group | Fields | Example Values |
|---|---|---|
| **Employee Overview** | `EMP_NAME`, `EMP_STS`, `DESIGNATION`, `RANK`, `ROLE_TITLE`, `EMAIL`, `MOBILE` | "Fahmida Haque Mahi", "ACTIVE", "Programmer", ... |
| **Organization** | `OPERATING_UNIT_TITLE`, `DEPT_TITLE`, `SECTION_TITLE`, `SUB_SECTION_TITLE`, `PRODUCT_TITLE`, `LOCATION_TITLE`, `DEPARTMENT_TITLE`, `UNIT_NAME`, `COST_CENTER` | "ICT", "Software", "Web", ... |
| **Personal** | `EMP_CATEGORY`, `JOINING_DATE`, `CONFIRM_DATE`, `DATE_OF_BIRTH`, `AGE`, `GENDER`, `BLOOD_GROUP`, `RELIGION`, `MARITAL_STATUS` | "Permanent", "2019-01-15", ... |

### 4.3 Employee Photo

If `apiData.PIC_URL_` is set, a profile photo is rendered in the top-right of the card. The photo URL is prefixed with the configured `hrmsImgBaseUrl`.

### 4.4 Status Color

`HRMS_STATUS` value determines the status badge color via `getEmployeeStatusClass()`:
- `ACTIVE` → green
- Other values → appropriate color

---

## 5. Output Format — Two Cards

The page renders two side-by-side cards:

```
┌─────────────────────────┬─────────────────────────┐
│  Server Information      │  Employee Information   │
│  ┌─────────────────────┐ │  ┌─────────────────────┐ │
│  │ Identity             │ │  │ Identity             │ │
│  │   Logon Name: ...   │ │  │   Employee ID: ...   │ │
│  │   Principal ID: ... │ │  │   EMP Code: ...     │ │
│  ├─────────────────────┤ │  ├─────────────────────┤ │
│  │ Current User Cond.. │ │  │ Employee Overview   │ │
│  │ Assigned Privileges │ │  │   Name, Status, ... │ │
│  │ User Activity       │ │  │ Organization        │ │
│  │ Infrastructure Inf. │ │  │   Dept, Location... │ │
│  │ User Profiling Inf. │ │  │ Personal            │ │
│  │ User Information    │ │  │   DOB, Gender, ...  │ │
│  └─────────────────────┘ │  └─────────────────────┘ │
└─────────────────────────┴─────────────────────────┘
```

Each card is a `col-md-6` rendered inside `ui_card.php` (`h-100` wrapper).

---

## 6. Tabbed Cards for Multi-User

### 6.1 When Tabs Appear

- **Single user** → no tabs (content displayed directly)
- **Multiple users** → tab bar rendered at the top of each card

### 6.2 Tab Labels

| Card | Tab Label Source |
|---|---|
| Server Information | `username` (the input value for that user) |
| Employee Information | `hrms.data.apiData.EMP_CODE` or `username` if EMP_CODE is empty |

### 6.3 Tab Switching

Handled by click delegation on the card container:
```javascript
container.addEventListener('click', function(e) {
    const tab = e.target.closest('.info-card-tab');
    if (!tab) return;
    const idx = parseInt(tab.dataset.tab);
    // Deactivate all, activate selected tab + pane
});
```

### 6.4 Suggestion Tabs

- **Server card**: Includes suggestion results as additional tabs (labeled by the suggested username)
- **Employee card**: Excludes suggestion results (only original users get employee info)

### 6.5 Loading Sequence

```javascript
// action_processor.js:472-483
const results = await Promise.all(allUsers.map(async (singleUser) => {
    const [serverData, hrmsData] = await Promise.all([
        fetch(..., { body: 'username=...&action=info&part=server_info' }),
        fetch(..., { body: 'username=...&action=info&part=hrms_info' })
    ]);
    return { username: singleUser, server: serverData, hrms: hrmsData };
}));
```

**N+1 pattern**: For N users, the browser makes N×2 parallel requests (one server + one HRMS per user).

---

## 7. Suggestions (Related IDs)

### 7.1 Purpose

When the input ID doesn't exactly match any AD account but has a numeric portion that matches multiple accounts, the system returns the matching IDs as suggestions.

### 7.2 Source

**LDAP backend** — Generated during the 3-tier search in `ldap_user_repository_find()` (`ldap_user_repository.php:117`):

```
For each failed lookup:
  1. Extract trailing digits: preg_match('/(\d+)$/', $input)
  2. Try prefix wildcard: samAccountName=*{fullInput}
     → 1 match? resolve
     → Multiple + has dash? suffix match → resolve
     → Otherwise → continue
  3. Try broad wildcard: samAccountName=*{digits}* (limit 5)
     → 1 match? resolve
     → Multiple? return { lookedUpUser: [suggestedId1, suggestedId2, ...] }
```

**PowerShell fallback** — Returns text line in `infoOutput`:
```
Multiple matching IDs: 54593, 54600, 54601
```

### 7.3 Frontend Processing

```javascript
// action_processor.js:486-518
// 1. Try structured suggestions (LDAP)
if (r.server?.data?.suggestions) {
    for (const ids of Object.values(r.server.data.suggestions)) {
        nearbyIds = ids; break;
    }
}
// 2. Fallback: text parse (PowerShell)
if (!nearbyIds && r.server?.data?.infoOutput) {
    const match = r.server.data.infoOutput.match(
        /(?:Multiple matching IDs|Nearby IDs that exist in AD):\s*([\d,\s]+)/
    );
    if (match) nearbyIds = match[1].split(',').map(id => id.trim());
}
// 3. Fetch server info for each suggested ID
if (nearbyIds?.length) {
    for (const sid of nearbyIds) {
        suggestionFetches.push(fetch(..., { body: 'username='+sid+'&action=info&part=server_info&isSuggestion=1' }));
    }
}
```

### 7.4 Display

- Suggested users appear as **additional tabs** in the **Server Information** card only
- The **Employee Information** card excludes suggestions
- Each suggestion tab is fetched and rendered on demand

---

## 8. System Architecture & Data Flow

### 8.1 Component Diagram

```
┌────────────────────────────────────────────────────────────────────┐
│                          Browser                                    │
│  ┌──────────────┐   ┌──────────────────────┐   ┌────────────────┐  │
│  │ Sidebar: Info│──▶│ action_processor.js  │──▶│ Info Cards     │  │
│  │ (value="info")│  │ refreshInfoCards()    │  │ Server + Empl.  │  │
│  └──────────────┘   └──────────┬───────────┘  └────────────────┘  │
│                                │                                    │
│  For each user, 2× parallel POST                                    │
│  ┌─────────────────────────────┼──────────────────────────────┐    │
│  │ part=server_info            │   part=hrms_info             │    │
│  └─────────────────────────────┼──────────────────────────────┘    │
└────────────────────────────────┼───────────────────────────────────┘
                                 │
┌────────────────────────────────┼───────────────────────────────────┐
│                      PHP Server                                    │
│                                 │                                   │
│  ┌──────────────────────────────▼──────────────────────────────┐   │
│  │  execute_action.php (Controller)                             │   │
│  │                                                              │   │
│  │  case 'server_info':                                         │   │
│  │    getADUserInfo($username)                                  │   │
│  │    → directory_info_service.php:6                            │   │
│  │      → ad_execute_json_script('get_user_info_bulk', ...)     │   │
│  │        → ad_operation_router.php                             │   │
│  │          → ad_resolve_backend('get_user_info_bulk')          │   │
│  │            ┌────────────────┐    ┌──────────────────┐       │   │
│  │            │ LDAP           │    │ PowerShell       │       │   │
│  │            │ ldap_user_     │    │ getUserInfo      │       │   │
│  │            │ repository_    │    │ script           │       │   │
│  │            │ find_many()    │    └──────────────────┘       │   │
│  │            │  → find()      │                               │   │
│  │            │    (3-tier)    │                               │   │
│  │            └────────────────┘                               │   │
│  │    Returns: { infoOutput, adData, suggestions }             │   │
│  │                                                              │   │
│  │  case 'hrms_info':                                          │   │
│  │    getHRMSInfo($username)                                   │   │
│  │    → directory_info_service.php:166                         │   │
│  │      → GET whrmsapi.waltonbd.com/info/emp_info.php          │   │
│  │    Returns: { apiData }                                     │   │
│  └─────────────────────────────────────────────────────────────┘   │
└────────────────────────────────────────────────────────────────────┘
```

### 8.2 Complete Request Sequence

```
1. User clicks "Info" button (sidebar_actions.php:18)
2. handleActionButtonClick() fires (action_processor.js:567)
   → action == 'info'
3. refreshInfoCards(username) called (action_processor.js:454)
4. Input split: username.split(/[\s,;]+/)
5. For each user, two concurrent fetch() calls:
   a. POST /api/index.php?endpoint=execute_action
      body: username=X&action=info&part=server_info
   b. POST /api/index.php?endpoint=execute_action
      body: username=X&action=info&part=hrms_info
6. Server-side:
   a. server_info case:
      - getADUserInfo() → directory_info_service.php:6
        → ad_execute_json_script('get_user_info_bulk', 'getUserInfo', ...)
          → ad_operation_router.php
            → ad_resolve_backend('get_user_info_bulk')
            → ldap_user_repository_find_many()
              → ldap_user_repository_find() for each user
                (3-tier search: exact → prefix wildcard → numeric wildcard)
              → suggestions returned if multiple numeric matches
            → formats: infoOutput (text), adData (structured), suggestions (map)
   b. hrms_info case:
      - getHRMSInfo() → directory_info_service.php:166
        → GET whrmsapi with username (fallback: numeric-only)
        → returns apiData (JSON)
7. Browser receives per-user { server, hrms } responses
8. Suggestions extracted and fetched (parallel)
9. renderServerHtml() → parses infoOutput text → Identity + sections → #serverUserInfoDisplay
10. renderHrmsHtml() → reads apiData → Identity + groups → #employeeInfoDisplay
11. buildTabbedCard() wraps if multiple users
12. Tab switching via click delegation
```

### 8.3 Backend Selection

```
ad_resolve_backend('get_user_info_bulk')
  → config/ldap/ldap_operations.php: 'get_user_info_bulk' => true
  → Returns 'ldap'
```

Catalog entry (`ldap_operation_catalog.php:17`):
```php
'get_user_info_bulk' => [
    'api_endpoint' => 'execute_action',
    'ps_script_key' => 'getUserInfo',
    'ldap_handler' => 'ldap_user_repository_find_many',
    'phase' => 1,
],
```

---

## 9. API Reference

### 9.1 Server Info Endpoint

```
POST /api/index.php?endpoint=execute_action
Content-Type: application/x-www-form-urlencoded

username=mahi54593&action=info&part=server_info
```

**Response (LDAP backend)**:
```json
{
  "success": true,
  "data": {
    "infoOutput": "AD Account: mahi54593\nUser Principal ID: mahi54593@domain.com\n...",
    "adData": {
      "thumbnailPhotoDataUri": "data:image/jpeg;base64,...",
      "rawAtributes": { ... }
    },
    "suggestions": {
      "54593": ["54593", "54600"]
    }
  }
}
```

### 9.2 Employee Info Endpoint

```
POST /api/index.php?endpoint=execute_action
Content-Type: application/x-www-form-urlencoded

username=mahi54593&action=info&part=hrms_info
```

**Response**:
```json
{
  "success": true,
  "data": {
    "apiData": {
      "EMP_ID": "54593",
      "EMP_CODE": "54593",
      "EMP_NAME": "Fahmida Haque Mahi",
      "EMP_STS": "ACTIVE",
      "DESIGNATION": "Programmer",
      "EMAIL": "fahmida@domain.com",
      "MOBILE": "017xxxxxxxx",
      "DEPARTMENT_TITLE": "ICT",
      "JOINING_DATE": "2020-01-15",
      "DATE_OF_BIRTH": "1995-06-10",
      ...
    }
  }
}
```

### 9.3 Suggestion Fetch (internal)

```
POST /api/index.php?endpoint=execute_action
body: username=54593&action=info&part=server_info&isSuggestion=1
```

Only `server_info` is fetched for suggested IDs (no HRMS data for suggestions).

---

## 10. File Reference

### 10.1 Backend Files

| File | Line(s) | Role |
|---|---|---|
| `app/Application/Http/Controllers/execute_action.php` | 96-109 | Controller — dispatches `server_info` and `hrms_info` cases |
| `app/Domain/HRMS/directory_info_service.php` | 6-137 | `getADUserInfo()` — calls router, formats `infoOutput` |
| `app/Domain/HRMS/directory_info_service.php` | 166-198 | `getHRMSInfo()` — calls HRMS API, returns `apiData` |
| `app/Ldap/Operations/ldap_user_repository.php` | 117-203 | `ldap_user_repository_find()` — 3-tier search (exact→prefix→numeric) |
| `app/Ldap/Operations/ldap_user_repository.php` | 205-275 | `ldap_user_repository_find_many()` — multi-user loop |
| `app/Ldap/Operations/ldap_user_repository.php` | 277-320 | `ldap_user_repository_suggest_nearby()` — suggestion search |
| `app/Ldap/Operations/ldap_operation_catalog.php` | 17-22 | Catalog entry for `get_user_info_bulk` |
| `app/Ldap/Router/ad_operation_router.php` | — | Backend resolution + dispatch |
| `config/ldap/ldap_operations.php` | 10 | `ldap_ready`: `'get_user_info_bulk' => true` |
| `public/api/index.php` | 10 | Route: `'execute_action' => 'execute_action.php'` |

### 10.2 Frontend Files

| File | Line(s) | Role |
|---|---|---|
| `resources/views/components/sidebar_actions.php` | 18-22 | Info button (`value="info"`, class `action-button`) |
| `public/resources/frontend/js/admin/action_processor.js` | 567-726 | `handleActionButtonClick()` — routes to info handler |
| `public/resources/frontend/js/admin/action_processor.js` | 454-549 | `refreshInfoCards()` — main fetch + render orchestrator |
| `public/resources/frontend/js/admin/action_processor.js` | 343-404 | `renderServerHtml()` — parses infoOutput → HTML |
| `public/resources/frontend/js/admin/action_processor.js` | 406-452 | `renderHrmsHtml()` — maps apiData → HTML |
| `public/resources/frontend/js/admin/action_processor.js` | 520-539 | `buildTabbedCard()` — tabbed multi-user card layout |
| `public/resources/frontend/js/admin/action_processor.js` | 37-48 | `formatServerStatusValue()` — status badge colors |
| `public/resources/frontend/js/admin/utils.js` | 57-76 | `styleFeedbackMessage()` — status line formatting |
| `resources/views/components/global/info_cards.php` | 1-52 | Two-column card layout (server + employee) |
| `resources/views/components/global/ui_card.php` | 1-16 | Card wrapper with `h-100` class |
| `public/resources/frontend/css/components.css` | — | Info card tab and pane styling |
