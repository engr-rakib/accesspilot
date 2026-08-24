# HRMS-AD User Mapping Report

**Technical specification for cross-referencing HRMS employee records against Active Directory user accounts.**

> **Last Updated:** 2026-07-06
> **Primary Backend:** LDAP (PHP) with PowerShell fallback

---

## Table of Contents

1. [Overview](#1-overview)
2. [Input Resolution Algorithm](#2-input-resolution-algorithm)
3. [Column Reference](#3-column-reference)
4. [Find_Status Reference](#4-find_status-reference)
5. [System Architecture & Data Flow](#5-system-architecture--data-flow)
6. [API Reference](#6-api-reference)
7. [File Reference](#7-file-reference)
8. [CSV Export Mechanism](#8-csv-export-mechanism)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. Overview

The HRMS-AD User Mapping Report provides a single unified interface to compare HRMS employee data with Active Directory user accounts. It replaces the previous separate "Sync" and "Mapping" operations.

### 1.1 Purpose

- Verify which employees have AD accounts and which do not
- Identify AD accounts that exist without corresponding HRMS records
- Cross-check HRMS status (ACTIVE/INACTIVE) against AD account status (Enabled/Disabled)
- Export the comparison as a downloadable CSV file

### 1.2 Input Format

- **Single ID** — e.g. `54593` or `mahi54593` or `C-13088`
- **Multiple IDs** — comma, semicolon, space, or newline separated: `mahi54593, 66684`
- **Mixed types** — HRMS numeric IDs, AD samAccountNames, prefixed codes can be mixed

### 1.3 Output

- Inline HTML table rendered in the action card
- Simultaneous CSV download available via "Download CSV" button
- Summary line: `Processed: N, Found: X, AD Only: Y, HRMS Only: Z, Errors: E`

---

## 2. Input Resolution Algorithm

For each input value, the system executes a **5-step resolution pipeline** to locate the user in both HRMS and AD.

### 2.1 Step 1 — HRMS API (Direct)

```
GET https://whrmsapi.waltonbd.com/info/emp_info.php?emp_id={input}
```

- Calls HRMS API with the raw input value
- If API returns valid JSON with a non-empty `EMP_ID` field, HRMS data is captured
- Timeout: 5 seconds

### 2.2 Step 2 — Numeric Extraction from Input

```
preg_match('/(\d+)$/', $input, $m)
$inputNum = $m[1] ?? ''
```

- Extracts trailing digits from the input string
- Example: `mahi54593` → `54593`, `C-13088` → `13088`
- If `$inputNum` is empty or identical to `$input`, this step is skipped

### 2.3 Step 3 — HRMS API (Numeric Fallback)

- Only executes if Step 1 failed AND Step 2 produced a different numeric value
- Calls HRMS API again: `?emp_id={inputNum}`
- ⚠ This may return a **different employee's** data than intended (see §9.2)

### 2.4 Step 4 — AD Exact Lookup

Search candidates (deduplicated):
1. Original input value
2. HRMS `EMP_CODE` (if HRMS was found)
3. Numeric part from Step 2 (if different from input)

For each candidate:
```
ldap_search(filter="(&(objectCategory=person)(objectClass=user)(samAccountName={candidate}))")
```

If found, these attributes are read:
- `samAccountName` → Logon_ID
- `displayName` → AD_Name
- `userAccountControl` → AD_STATUS (bit 1 = 0 means Enabled)
- `employeeID` → used for HRMS retry (see below)

**AD-to-HRMS retry**: If HRMS was not found in Steps 1/3 but AD has an `employeeID` attribute, the system calls HRMS API with that value as a final attempt.

### 2.5 Step 5 — Wildcard Fallback

Only executes if Step 4 found nothing and a numeric part exists:

```
Tier 1: Prefix wildcard — samAccountName=*{fullInput}
        → 1 match? → use it
        → Multiple + input has dash? → suffix match (-{number}) → use best
        → Neither? → continue

Tier 2: Broad wildcard — samAccountName=*{digits}*
        → 1 match? → use it
        → Multiple? → pick the first result (report context)
```

This handles cases where the samAccountName contains the numeric ID but doesn't exactly match any candidate (e.g. input `C-13088` but AD user is `fokirC-13088`).

### 2.6 Resolution Examples

| Input | HRMS ID Found | AD Found | Why |
|---|---|---|---|
| `mahi54593` | `54593` (Step 3) | `mahi54593` (Step 4) | Numeric fallback worked |
| `54593` | `54593` (Step 1) | `mahi54593` (Step 4) | Direct HRMS hit, AD uses different samAccountName |
| `66684` | `66684` (Step 1) | `rakib66684` (Step 4) | Direct HRMS hit, AD uses different samAccountName |
| `C-13088` | `C-13088` (Step 1) | `fokirC-13088` (Step 5) | Prefix wildcard `*C-13088` matched |
| `fokirC-13088` | `13088` (Step 3, wrong!) | `fokirC-13088` (Step 4) | Exact match via AD, HRMS shows different employee |
| `13088` | `13088` (Step 1) | `fokirC-13088` (Step 5) | Wildcard picks first match |
| `unknown123` | Not found | Not found | No HRMS record, no AD match |
| `no_ad_user` | `99999` (Step 1) | Not found | HRMS exists but AD not created |

---

## 3. Column Reference

| Column | Source Field | API / AD Attribute | Description |
|---|---|---|---|
| `HRMS_ID` | `EMP_CODE` | HRMS API `EMP_CODE` | Actual numeric employee ID from HRMS. Falls back to input if HRMS not found |
| `Logon_ID` | `samAccountName` | AD `samAccountName` | AD login username |
| `EMP_NAME` | `EMP_NAME` | HRMS API `EMP_NAME` | Employee full name from HRMS |
| `AD_Name` | `displayName` | AD `displayName` | Display name from Active Directory |
| `HRMS_STATUS` | `EMP_STS` | HRMS API `EMP_STS` | HRMS employment status (e.g. `ACTIVE`, `INACTIVE`, `N/A`) |
| `AD_STATUS` | `userAccountControl` | AD bitmask (bit 1) | `Enabled` if `(UAC & 2) === 0`, otherwise `Disabled`. Shows `Not Created` if AD not found |
| `Find_Status` | Computed | See §4 | Resolution outcome code |

---

## 4. Find_Status Reference

| Value | Condition | Meaning |
|---|---|---|
| `Found` | HRMS name != `N/A` AND AD found | User exists in **both** systems |
| `AD Only` | HRMS name == `N/A` AND AD found | AD account exists but no HRMS record |
| `HRMS Only` | HRMS name != `N/A` AND AD not found | HRMS employee exists but no AD account (`AD_STATUS` → `Not Created`) |
| `Not Found` | HRMS name == `N/A` AND AD not found | Neither system has a matching record |
| `Error` | PHP exception caught | Processing error (logged via `ldap_write_script_log`) |

---

## 5. System Architecture & Data Flow

### 5.1 Component Diagram

```
┌────────────────────────────────────────────────────────────────┐
│                        Browser                                  │
│  ┌──────────────┐   ┌──────────────────┐   ┌───────────────┐   │
│  │ Sidebar Button│──▶│ report_actions.js│──▶│ Action Card   │   │
│  │ (#getHrmsAd..)│   │ handleReportAct.│   │ Table + Btns  │   │
│  └──────────────┘   └────────┬─────────┘   └───────────────┘   │
│                              │ POST                             │
└──────────────────────────────┼──────────────────────────────────┘
                               │
┌──────────────────────────────┼──────────────────────────────────┐
│                    PHP Server                                   │
│  ┌───────────────────────────▼──────────────────────────────┐   │
│  │  api/index.php?endpoint=get_hrms_ad_report_message       │   │
│  └───────────────────────────┬──────────────────────────────┘   │
│                              │                                   │
│  ┌───────────────────────────▼──────────────────────────────┐   │
│  │  get_hrms_ad_report_message.php (Controller)             │   │
│  │  - Validates session + permission                        │   │
│  │  - Calls ad_dispatch_report_operation()                  │   │
│  │  - Converts results to CSV                               │   │
│  │  - Stores CSV in $_SESSION['hrms_ad_report_csv']         │   │
│  └───────────────────────────┬──────────────────────────────┘   │
│                              │                                   │
│  ┌───────────────────────────▼──────────────────────────────┐   │
│  │  ad_operation_router.php                                  │   │
│  │  - Looks up catalog entry for 'hrms_ad_report'            │   │
│  │  - Calls ad_resolve_backend()                             │   │
│  │  ┌─────────────────────┐   ┌─────────────────────────┐    │   │
│  │  │ LDAP Path (primary) │   │ PowerShell Path (fallb.)│    │   │
│  │  │ ad_ldap_execute()   │   │ ad_powershell_execute() │    │   │
│  │  └─────────┬───────────┘   └──────────┬──────────────┘    │   │
│  └────────────┼───────────────────────────┼───────────────────┘   │
│               │                           │                        │
│  ┌────────────▼───────────┐   ┌──────────▼──────────────┐        │
│  │ ldap_hub_hrms_ad_report│   │ get-hrms-ad-report.ps1  │        │
│  │ (ldap_hub_reports.php) │   │ (PowerShell script)     │        │
│  └────────────────────────┘   └─────────────────────────┘        │
└──────────────────────────────────────────────────────────────────┘
```

### 5.2 Request Flow (Detailed)

1. **User clicks** "HRMS AD" button in sidebar
2. **report_actions.js** validates input, shows loading animation, POSTs to `api/index.php?endpoint=get_hrms_ad_report_message`
3. **Controller** (`get_hrms_ad_report_message.php`):
   - Checks authentication and permission (`action_get_ad_hrms_status` or `action_export_hrms_ad_user_id`)
   - Calls `ad_dispatch_report_operation('hrms_ad_report', ['Usernames' => $username])`
4. **Router** (`ad_operation_router.php`):
   - Reads catalog for `hrms_ad_report` → gets `ldap_handler` and `ps_script_key`
   - Calls `ad_resolve_backend('hrms_ad_report')` → reads `config/ldap/ldap_operations.php`
   - If `ldap_ready` is `true` → LDAP path
   - If `ldap_ready` is missing → PowerShell fallback path
5. **LDAP handler** (`ldap_hub_hrms_ad_report()`):
   - Opens LDAP connection via `ldap_run_with_connection()`
   - Iterates each input ID through the 5-step resolution algorithm
   - Step 5 uses 2-tier wildcard: prefix wildcard (with dash-guarded suffix matching) → broad numeric wildcard
   - Returns `{ success, message, results }` wrapped via `ldap_json_script_result()`
6. **Controller** receives results:
   - Builds CSV string from `results` array
   - Stores in `$_SESSION['hrms_ad_report_csv']`
   - Returns JSON with `report_content` (CSV string)
7. **Browser** receives response:
   - `csvToHtmlTable()` converts CSV to HTML table
   - Action card displays the table
   - "Download CSV" and "Close" buttons rendered in header

### 5.3 Backend Selection Logic

```
ad_resolve_backend($operation)
  │
  ├── Reads config/ldap/ldap_operations.php → ['ldap_ready'][$operation]
  │
  ├── If true → return 'ldap'
  │
  ├── If false/missing → return 'powershell'
  │
  └── If 'auto' mode and LDAP fails → auto-fallback to PowerShell
```

**Note**: Every new catalog entry must also be added to `ldap_ready` map in `config/ldap/ldap_operations.php`. Missing entries silently fall back to PowerShell, which may produce different results or fail.

---

## 6. API Reference

### 6.1 Report Generation Endpoint

```
POST /api/index.php?endpoint=get_hrms_ad_report_message
Content-Type: application/x-www-form-urlencoded

username=mahi54593,66684
```

**Response (success)**:
```json
{
  "success": true,
  "message": "Report generated: 2 processed, 2 found, 0 not found.",
  "report_content": "\"HRMS_ID\",\"Logon_ID\",...\n\"54593\",\"mahi54593\",..."
}
```

**Response (error)**:
```json
{
  "success": false,
  "message": "ERROR: Username is required."
}
```

### 6.2 Download Endpoint

```
POST /api/index.php?endpoint=get_hrms_ad_report
```

- Reads `$_SESSION['hrms_ad_report_csv']`
- Returns as `text/csv` with `Content-Disposition: attachment; filename="HRMS_AD_Report.csv"`
- CSRF-exempt in `api/index.php` (line 70)
- Clears session data after output

---

## 7. File Reference

### 7.1 Backend Files

| File | Line(s) | Role |
|---|---|---|
| `app/Ldap/Operations/ldap_hub_reports.php` | 643–836 | `ldap_hub_hrms_ad_report()` — primary LDAP handler |
| `app/Ldap/Operations/ldap_operation_catalog.php` | 105–110 | Catalog registration for `hrms_ad_report` |
| `app/Ldap/Router/ad_operation_router.php` | 203–232 | `ad_dispatch_report_operation()` — dispatches to LDAP or PS |
| `config/ldap/ldap_operations.php` | 27 | `ldap_ready` entry: `'hrms_ad_report' => true` |
| `config/powershell.php` | — | Script key: `HRMS_AD_Report` |
| `app/Application/Http/Controllers/get_hrms_ad_report_message.php` | 1–88 | Report controller (generates CSV) |
| `app/Application/Http/Controllers/get_hrms_ad_report.php` | 1–35 | Download controller (serves CSV) |
| `scripts/powershell/get-hrms-ad-report.ps1` | 1–130 | PowerShell fallback script |
| `public/api/index.php` | 43–44, 70 | Route registration + CSRF exemption |

### 7.2 Frontend Files

| File | Line(s) | Role |
|---|---|---|
| `resources/views/components/sidebar_actions.php` | 84–88 | Button `#getHrmsAdReportButton` |
| `public/resources/frontend/js/admin/report_actions.js` | 6–120 | `handleReportAction()` — AJAX call + UI |
| `public/resources/frontend/js/admin/report_actions.js` | 128–141 | `#getHrmsAdReportButton` event handler |
| `public/resources/frontend/js/admin/utils.js` | 25–49 | `csvToHtmlTable()` — CSV → HTML table converter |
| `public/resources/frontend/js/admin/action_processor.js` | 9, 735 | Exclusion selector for HRMS AD button |
| `public/resources/frontend/js/admin/assistant_filter.js` | 88 | Report button ID list |
| `resources/views/components/global/action_taken_card.php` | 1–54 | Result card UI template |
| `public/resources/frontend/css/components.css` | — | `.dynamic-report-table` styling |

---

## 8. CSV Export Mechanism

### 8.1 Why iframe POST

The download uses an **iframe-based form POST** rather than AJAX or `window.location`:

```
1. Create hidden <iframe>, set name="download_iframe_..."
2. Create <form method="POST" target="download_iframe_..." action="/api/...?endpoint=get_hrms_ad_report">
3. Append form to document body
4. form.submit() → server returns CSV with Content-Disposition: attachment
5. Browser saves file, iframe receives binary
6. Cleanup: remove form + iframe after 5 seconds
```

This approach is used because:
- `window.location` cannot POST data
- AJAX cannot trigger a browser file-save dialog for binary content
- The download endpoint needs POST (same as report generation)

### 8.2 CSRF Exemption

The download endpoint `get_hrms_ad_report` is exempt from CSRF token validation because an iframe POST cannot set the required `X-CSRF-Token` header. This is safe because the controller still performs:
- Session authentication check (`$_SESSION['authenticated']`)
- Permission check (`action_get_ad_hrms_status` or `action_export_hrms_ad_user_id`)

### 8.3 CSV Format

```
HRMS_ID,Logon_ID,EMP_NAME,AD_Name,HRMS_STATUS,AD_STATUS,Find_Status
"54593","mahi54593","Fahmida Haque Mahi","Fahmida Haque Mahi","ACTIVE","Enabled","Found"
"66684","rakib66684","Rakibuzzaman","Rakibuzzaman","ACTIVE","Enabled","Found"
```

All values are double-quoted with internal quotes escaped as `""`.

---

## 9. Troubleshooting

### 9.1 Report Shows "Not Found" for All IDs

- **Check `ldap_ready` config**: Ensure `config/ldap/ldap_operations.php` has `'hrms_ad_report' => true`
- **Check operation catalog**: Ensure `app/Ldap/Operations/ldap_operation_catalog.php` has the `hrms_ad_report` entry
- **Check LDAP connectivity**: Verify `ldap_run_with_connection()` can bind successfully

### 9.2 HRMS Name Shows Wrong Employee

- **Input is AD logon name, not HRMS code**: The HRMS numeric fallback strips non-digits from `fokirC-13088` → `13088`, which may match a different employee
- **Prefix wildcard resolves AD correctly**: Step 5 Tier 1 (`*{fullInput}`) will find the correct AD user (e.g. `fokirC-13088`) but HRMS data may still be wrong
- **Solution**: Input the full HRMS employee code (e.g. `C-13088`) for correct HRMS results

### 9.3 Download Button Does Nothing

- **Check CSRF exemption**: Verify `get_hrms_ad_report` is listed in `$csrfExemptEndpoints` in `api/index.php`
- **Check session**: Report must be generated first (session stores the CSV)
- **Browser console**: Look for iframe-related errors

### 9.4 PowerShell Fallback Used Instead of LDAP

- Add `'hrms_ad_report' => true` to `config/ldap/ldap_operations.php` under `ldap_ready`
- Verify the catalog entry's `ldap_handler` is correctly named
- Check `ad_resolve_backend()` returns `'ldap'`
