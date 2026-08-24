# AccessPilot HRMS API Integration Guide

**A walkthrough for system administrators and coordinators — no programming knowledge required.**

---

## Table of Contents

1. [Why Does the Application Need an API?](#1-why-does-the-application-need-an-api)
2. [What Automations Are Done via the API?](#2-what-automations-are-done-via-the-api)
3. [API Structure — How It Works](#3-api-structure--how-it-works)
4. [API Response Fields — What Data Comes Back](#4-api-response-fields--what-data-comes-back)
5. [How the API Responds (Example JSON)](#5-how-the-api-responds-example-json)
6. [APIs Available in the Application](#6-apis-available-in-the-application)
7. [How to Create / Provide an API](#7-how-to-create--provide-an-api)
8. [Common Questions](#8-common-questions)

---

## 1. Why Does the Application Need an API?

The application (AccessPilot) manages user accounts in **Active Directory (AD)** — the system that controls login, email, and access to computers across your organization.

To create or update a user in Active Directory, the application needs information about that employee:

- What is their name?
- What is their email address?
- Which department do they work in?
- What is their job title?
- Are they still an active employee?

This information lives in your **HRMS (Human Resource Management System)** — the database your HR team uses every day.

The API is the **bridge** between HRMS and AccessPilot. When the application needs to create or update a user, it asks the HRMS API: *"Give me the details for this employee."* The API responds with the employee's data, and AccessPilot uses that data to build the Active Directory account.

**Without the API:**
- Every user would need to be created manually
- Information would become outdated quickly
- Mistakes would happen due to manual data entry

**With the API:**
- User creation and updates are automatic
- Data is always up-to-date from HRMS
- No manual errors

---

## 2. What Automations Are Done via the API?

The API powers three major automation tasks inside AccessPilot:

### 2.1 Automatic User Creation

When a new employee joins the company:

1. HR enters the employee into HRMS (as usual)
2. An administrator enters the employee code in AccessPilot
3. AccessPilot calls the API to get the employee's details
4. The application:
   - Creates the AD user account
   - Sets the username (sAMAccountName), full name, email, phone, job title
   - Places the user in the correct Organizational Unit (OU) based on configurable field-to-level mapping
   - Adds the user to the appropriate security groups (auto-created per OU level, plus conditional rules)
5. The user can log in on day one — everything is ready

### 2.2 Status-Based Administration

Administrators can see a list of all employees who share the same employment status, such as:

- **ACTIVE** — Currently employed
- **RESIGNED** — No longer with the company
- **CONTRACTUAL** — Fixed-term contract employees
- **RETIRED** — Retired employees

This helps with bulk operations like:
- Reviewing who is active versus inactive
- Auditing access for specific employment categories
- Mass-disabling accounts for resigned employees

### 2.3 Profile Photo Retrieval

When viewing or creating a user, the application can fetch the employee's profile photo from HRMS and display it on screen — helping administrators verify they are working with the correct person.

---

## 3. API Structure — How It Works

### 3.1 The Big Picture

```
┌──────────────────────┐         ┌──────────────────────┐
│    Your HRMS System  │         │   AccessPilot Portal │
│                      │         │                      │
│   ┌────────────────┐ │   API   │  ┌────────────────┐  │
│   │ Employee Database│ │◄───────►│  │ Sync Engine    │  │
│   └────────────────┘ │ Request  │  └────────────────┘  │
│                      │ Response │                      │
│   ┌────────────────┐ │         │  ┌────────────────┐  │
│   │ Photo Storage   │ │         │  │ AD Connector   │  │
│   └────────────────┘ │         │  └────────────────┘  │
│                      │         │                      │
└──────────────────────┘         └──────────────────────┘
```

### 3.2 API Endpoint (URL)

The application needs one main URL from you — the **API Endpoint**. This is a web address that looks like:

```
https://hrms.yourcompany.com/api/employee
```

The application calls this URL by adding parameters at the end:

| Parameter | Value | Example |
|-----------|-------|---------|
| `emp_id` | Employee code | `https://hrms.yourcompany.com/api/employee?emp_id=59022` |
| `emp_sts` | Employment status | `https://hrms.yourcompany.com/api/employee?emp_sts=ACTIVE` |

### 3.3 How the Application Calls the API

When you enter an employee code and click "Test" or "Create User":

1. The application takes the API Endpoint URL
2. It removes any existing query parameters (if present)
3. It appends `?emp_id=CODE` or `?emp_sts=STATUS`
4. It sends the request using HTTPS (secure, encrypted)
5. It waits for the JSON response
6. It parses the response and uses the data

### 3.4 Authentication

The API does **not** require any username/password or API key. It is a simple GET request. If your organization requires authentication, the application can be configured to support it. Contact your implementation team for details.

---

## 4. API Response Fields — What Data Comes Back

The API returns a **JSON object** (a structured collection of data) with the fields listed below.

### 4.1 Fields Required for User Creation (Critical)

These fields are **mandatory**. If any of these are missing, renamed, or empty, user creation will fail:

| # | Field Name | What It Contains | Used For |
|--|-----------|------------------|----------|
| 1 | `EMP_CODE` | Employee's unique ID number | This becomes the Windows username (sAMAccountName). Example: `59022` |
| 2 | `EMP_NAME` | Employee's full name | Split into First Name and Last Name for the AD account. Example: `Md. Abdus Sobur` |
| 3 | `EMAIL` | Official company email | Set as the user's email address in AD. Example: `md.sobur@company.com` |
| 4 | `MOBILE` | Mobile phone number | Set as the user's phone number in AD. Example: `+8801700000000` |
| 5 | `DESIGNATION` | Job title / position | Set as the user's Title in AD. Example: `Jr. Officer` |
| 6 | `DEPARTMENT_TITLE` | Department name | Used as the Department attribute. Part of the configurable OU hierarchy by default. Example: `ICT` |
| 7 | `OPERATING_UNIT_TITLE` | Operating unit / company name | Used as the Company attribute. Top-level OU by default (configurable). Example: `Company Name Ltd.` |
| 8 | `LOCATION_TITLE` | Office / branch location | Set as the Office attribute. Example: `Head Office` |
| 9 | `RANK` | Employee rank/level | Written into the Description field as `Rank: 27 \| OU: ...` |
| 10 | `SECTION_TITLE` | Section within department | Part of the configurable OU hierarchy (default level 3). Example: `Software Development` |
| 11 | `PRODUCT_TITLE` | Product group | Part of the configurable OU hierarchy (default level 4). Example: `AccessPilot` |
| 12 | `SUB_SECTION_TITLE` | Sub-section (lowest level) | Part of the configurable OU hierarchy (default level 5 — user OU). Example: `Web Development` |
| 13 | `EMP_STS` | Employment status | Must be `"ACTIVE"` for user creation. If not ACTIVE, the user cannot be created. Example: `ACTIVE` |

> **Note:** The OU hierarchy field mapping is fully configurable via the **AD Objects** tab in System Configuration. Each of the 5 OU levels can be mapped to any HRMS field, set to "Skip", or given custom prefix/suffix. Security group creation follows the same pattern — group naming (prefix/suffix), auto-create toggle, and conditional assignment rules are all configurable per domain.

### 4.2 Fields Used for Reference (Informational)

These fields provide extra information and are displayed in the application but are not required for AD account creation:

| # | Field Name | What It Contains |
|---|-----------|------------------|
| 14 | `EMP_ID` | Internal HRMS database ID (used as the query parameter) |
| 15 | `PIC_URL_` | Path to the employee's profile photo (combined with Image Base URL) |
| 16 | `EMP_CAT_TITLE` | Employment category (Permanent, Contractual, etc.) |
| 17 | `PRODUCT_GROUP_TITLE` | Product group (e.g., WCOM) |
| 18 | `JOINING_DT` / `JOINING_DATE` | Date of joining the company |
| 19 | `DOB` | Date of birth |
| 20 | `AGE` | Calculated age |
| 21 | `GENDER` | Gender |
| 22 | `RESPONSIBILITY` | Job responsibility description |
| 23 | `OTHERS` | Includes: `ROLE_TITLE`, `TEAM_TITLE`, `SUB_TEAM_TITLE`, `DESIGNATION_ORDER`, `JOB_LOCATION_ID`, `ALL_ORG_MST_*`, etc. |

### 4.3 Important Notes About Field Names

- **Field names must match exactly** as shown above — including capital letters and underscores
- The application reads these field names directly; if a name changes, the script will not find the data
- However, the **OU hierarchy field mapping** is configurable — you can map any HRMS field to any OU level using the AD Objects tab in System Configuration (e.g., map `SECTION_TITLE` to Level 3, or set a level to "Skip")
- Similarly, **security group rules** can match on any HRMS field for conditional assignment (e.g., "if `DEPARTMENT_TITLE` = 'ICT', add user to `ICT-Support-Group`")
- `EMP_CODE` is the primary identifier, **not** `EMP_ID`
- The `emp_id` query parameter should accept the same value as `EMP_CODE`
- `PIC_URL_` returns a partial path (like `images/repository/HrCrEmp/...`); the application combines it with the **Image Base URL** to display the photo
- The API should return between 20 and 30 fields; 23 is standard

---

## 5. How the API Responds (Example JSON)

When the application calls the API, it expects a response in **JSON format**. Here is what a complete response looks like:

```json
{
    "EMP_ID": "99999999",
    "EMP_CODE": "12345",
    "EMP_NAME": "John Doe",
    "DESIGNATION": "Software Engineer",
    "ROLE_TITLE": null,
    "EMAIL": "john.doe@example.com",
    "MOBILE": "+8801999999999",
    "OPERATING_UNIT_TITLE": "Example Corp Ltd.",
    "LOCATION_TITLE": "Main Office",
    "JOB_LOCATION_ID": "88888888",
    "DEPARTMENT_TITLE": "Engineering",
    "SECTION_TITLE": "Product Development",
    "PRODUCT_TITLE": "Example Product",
    "SUB_SECTION_TITLE": "Backend Team",
    "EMP_STS": "ACTIVE",
    "PIC_URL_": "images/repository/HrCrEmp/PIC_/12345~abc12345-def6-7890-abcd-ef1234567890.png",
    "TEAM_TITLE": null,
    "SUB_TEAM_TITLE": null,
    "ALL_ORG_MST_ID": "77777777",
    "ALL_ORG_MST_TEAM_ID": null,
    "EMP_CAT_TITLE": "Permanent",
    "PRODUCT_GROUP_TITLE": "DEFAULT",
    "JOINING_DT": "2023-01-01",
    "DOB": "1990-01-01",
    "AGE": "35",
    "GENDER": "Male",
    "RESPONSIBILITY": "Example responsibility description",
    "RANK": "10"
}
```

### What to Check in the Response

| Check | What to Look For |
|-------|-----------------|
| All fields present | All 23 fields listed above should appear in the JSON |
| Field names correct | Capitalization and underscores must match exactly |
| EMP_CODE present | This is the most critical field |
| EMP_STS = "ACTIVE" | User creation requires this value |
| EMAIL is valid | Must be a proper email address with @ |
| PIC_URL_ format | Should be a relative path, not a full URL |

### What Happens If the Response Is Wrong

- **Field missing**: The application shows a warning and skips that attribute
- `EMP_STS` is not `ACTIVE`: User creation is blocked (prevents creating accounts for resigned employees)
- `EMP_CODE` is missing: User cannot be created at all
- API returns an error: The application shows the error message from the server

---

## 6. APIs Available in the Application

The application currently uses **two API operations**:

### 6.1 Get Employee by ID

- **Purpose**: Fetch full details for a single employee
- **How it's called**: `?emp_id=CODE`
- **What it does**: Returns the complete employee record (all 23 fields)
- **Used when**: Creating a single user, viewing employee details, manual testing
- **Response**: A single JSON object with all fields

### 6.2 Get Employees by Status

- **Purpose**: Fetch a list of all employees with a specific employment status
- **How it's called**: `?emp_sts=STATUS`
- **What it does**: Returns an array of employee records matching the status
- **Used when**: Bulk operations like auditing, mass-disable, status-based reports
- **Response**: An array of JSON objects

### 6.3 OU & Group Customization

The application supports **per-domain customization** of how HRMS fields map to Active Directory OUs and security groups.

- **OU Hierarchy**: Each of 5 OU levels can be independently mapped to any HRMS field (or skipped). Custom prefix/suffix can be applied to all OU names. A live preview shows the generated OU path.
- **Auto-Create Groups**: Security groups are created for each OU level by default. This can be disabled, and group naming follows configurable prefix/suffix patterns.
- **Conditional Group Rules**: Administrators can define rules like "if `DEPARTMENT_TITLE` equals `ICT`, add the user to `ICT-Support-Group`". Rules are evaluated at user creation time.
- **Configuration**: All settings are managed per-domain from **System Configuration → AD Objects → OU Management / Group Management** cards.

### 6.4 Image Base URL

- **Purpose**: Display employee profile photos
- **How it works**: The `PIC_URL_` field contains a relative path (e.g., `images/repository/HrCrEmp/...`). The application prepends the Image Base URL to create a full URL.
- **Example**: If Image Base URL is `https://hrms.example.com` and `PIC_URL_` is `images/repository/HrCrEmp/PIC_/59022~abc.png`, the application loads `https://hrms.example.com/images/repository/HrCrEmp/PIC_/59022~abc.png`

---

## 7. How to Create / Provide an API

### 7.1 Who Creates the API?

The API is typically **already available** if your organization uses an HRMS system (like Oracle HRMS, SAP SuccessFactors, HRIS, or a custom HR database). You may need to ask your IT department or software vendor for the API endpoint URL.

### 7.2 What You Need to Provide to AccessPilot

As the implementing administrator, you need to obtain **two pieces of information**:

| What | Example | How to Get It |
|------|---------|---------------|
| API Endpoint URL | `https://hrms.company.com/api/employee` | From your IT/HRMS team |
| Image Base URL | `https://hrms.company.com/images` | From your IT/HRMS team (if profile photos are needed) |

### 7.3 What the API Must Support

The API must:

1. **Respond to GET requests** with the employee code as a query parameter
2. **Return JSON format** (standard, easy-to-read data format)
3. **Include all fields listed in Section 4** of this guide
4. **Use HTTPS** (secure, encrypted connection)
5. **Work with self-signed certificates** (the application can be configured to accept them)

### 7.4 Sample Code for Your IT Team

If your IT team needs to build the API, here is a simple example in PHP:

```php
header('Content-Type: application/json');

$emp_id = $_GET['emp_id'] ?? '';
if (!$emp_id) {
    echo json_encode(['error' => 'emp_id is required']);
    exit;
}

// Query your HR database
$employee = $db->query("SELECT * FROM employees WHERE emp_code = ?", [$emp_id]);

if (!$employee) {
    echo json_encode(['error' => 'Employee not found']);
    exit;
}

echo json_encode($employee);
```

### 7.5 Testing the API

Before connecting to AccessPilot, test the API with a browser or a tool like Postman:

1. Open your browser
2. Enter: `https://hrms.company.com/api/employee?emp_id=59022`
3. You should see a JSON response with the employee data
4. If you see an error or blank page, check with your IT team

AccessPilot also has a **built-in test feature** on the System Configuration page — you can enter the API URL and test it directly from the application.

---

## 8. Common Questions

### Q: Does the API need to be public on the internet?

No. It can be on your internal network only, as long as the AccessPilot server can reach it.

### Q: What if the API uses HTTP instead of HTTPS?

HTTPS is strongly recommended. If HTTP must be used, the connection will not be encrypted.

### Q: Can I change the API URL later?

Yes. The API URL and Image Base URL can be updated anytime from the System Configuration page.

### Q: What if some fields are not available in my HRMS?

Contact your AccessPilot implementation team. Some fields can be made optional, but the critical fields (EMP_CODE, EMP_NAME, EMP_STS) are required.

### Q: How often does the application call the API?

- **On-demand**: When an administrator initiates a user creation or tests an employee code
- **Manual**: There is no automatic background sync; operations are triggered by administrators

### Q: What if the API returns an error?

The application displays the error message from the API. Common issues include:
- Invalid employee code (not found in HRMS)
- Network connectivity problems
- Certificate/SSL errors (the application can handle self-signed certificates)
- Timeout (API took too long to respond)

### Q: What is the difference between `EMP_ID` and `EMP_CODE`?

- `EMP_ID`: Internal database ID used by HRMS (sometimes called the system ID)
- `EMP_CODE`: The employee's unique identifier used for login and daily operations

AccessPilot uses `EMP_CODE` as the primary identifier (Windows username).

### Q: Is the API used for user updates (not just creation)?

Currently, the API is used during user creation and on-demand testing. Updates to existing AD accounts (e.g., changed name, new department) are handled through a separate process.
