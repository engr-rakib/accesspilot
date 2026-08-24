# AccessPilot — HRMS Data Guide

**A walkthrough for system administrators and coordinators — no programming knowledge required.**

---

## Table of Contents

1. [Why Does the Application Need HRMS Data?](#1-why-does-the-application-need-hrms-data)
2. [What Automations Does the Data Feed Power?](#2-what-automations-does-the-data-feed-power)
3. [How the Data Feed Works](#3-how-the-data-feed-works)
4. [Data Fields — What Comes Back](#4-data-fields--what-comes-back)
5. [Example of a Response](#5-example-of-a-response)
6. [Data Feed Operations Available](#6-data-feed-operations-available)
7. [How to Set Up the Data Feed](#7-how-to-set-up-the-data-feed)
8. [Common Questions](#8-common-questions)

---

## 1. Why Does the Application Need HRMS Data?

The application (AccessPilot) manages user accounts in **Active Directory (AD)** — the system that controls login, email, and access to computers across your organization.

To create or update a user in Active Directory, the application needs information about that employee:

- What is their name?
- What is their email address?
- Which department do they work in?
- What is their job title?
- Are they still an active employee?

This information lives in your **HRMS (Human Resource Management System)** — the system your HR team uses every day.

The data feed is the **bridge** between HRMS and AccessPilot. When the application needs to create or update a user, it asks HRMS: *"Give me the details for this employee."* HRMS replies with the employee's data, and AccessPilot uses that data to build the Active Directory account.

**Without the data feed:**
- Every user would need to be created manually
- Information would become outdated quickly
- Mistakes would happen due to manual data entry

**With the data feed:**
- User creation and updates are automatic
- Data is always up-to-date from HRMS
- No manual errors

---

## 2. What Automations Does the Data Feed Power?

The data feed powers three major automations inside AccessPilot:

### 2.1 Automatic User Creation

When a new employee joins the company:

1. HR enters the employee into HRMS (as usual)
2. An administrator enters the employee code in AccessPilot
3. AccessPilot requests the employee's details from HRMS
4. The application:
   - Creates the AD user account
   - Sets the username, full name, email, phone, job title
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

## 3. How the Data Feed Works

### 3.1 The Big Picture

```
┌──────────────────────┐                ┌──────────────────────┐
│    Your HRMS System  │◄──────────────►│   AccessPilot Portal │
│                      │   data request │                      │
│   ┌────────────────┐ │   + reply     │  ┌────────────────┐  │
│   │ Employee Records│ │               │  │ Active Directory │  │
│   └────────────────┘ │               │  │ users, OUs,      │  │
│   ┌────────────────┐ │               │  │ groups — all     │  │
│   │ Photo Storage   │ │               │  │ created here     │  │
│   └────────────────┘ │               │  └────────────────┘  │
└──────────────────────┘                └──────────────────────┘
```

### 3.2 The Data Address

The application needs one main web address from you — **the place where your HRMS answers data requests**. This is a web address that looks like:

```
https://hrms.yourcompany.com/employee
```

The application calls this address by adding what it needs at the end:

| What you need | Example |
|-----------|---------|
| A single employee's details | `https://hrms.yourcompany.com/employee?emp_id=59022` |
| Everyone with a certain status | `https://hrms.yourcompany.com/employee?emp_sts=ACTIVE` |

### 3.3 How the Application Requests Data

When you enter an employee code and click "Test" or "Create User":

1. The application takes the HRMS data address
2. It removes anything already added at the end (if present)
3. It appends the employee code or the employment status
4. It sends the request over a secure, encrypted connection
5. It waits for the structured data reply
6. It reads the reply and uses the data

### 3.4 Authentication

The data feed does **not** require any username/password or key. It is a simple read request. If your organization requires authentication, the application can be configured to support it. Contact your implementation team for details.

---

## 4. Data Fields — What Comes Back

The HRMS returns a **structured block of data** (a collection of fields) with the items listed below.

### 4.1 Fields Required for User Creation (Critical)

These fields are **mandatory**. If any of these are missing, renamed, or empty, user creation will fail:

| # | Field Name | What It Contains | Used For |
|--|-----------|------------------|----------|
| 1 | `EMP_CODE` | Employee's unique ID number | This becomes the Windows username (logon name). Example: `59022` |
| 2 | `EMP_NAME` | Employee's full name | Split into First Name and Last Name for the AD account. Example: `Md. Abdus Sobur` |
| 3 | `EMAIL` | Official company email | Set as the user's email address in AD. Example: `md.sobur@company.com` |
| 4 | `MOBILE` | Mobile phone number | Set as the user's phone number in AD. Example: `+8801700000000` |
| 5 | `DESIGNATION` | Job title / position | Set as the user's Title in AD. Example: `Jr. Officer` |
| 6 | `DEPARTMENT_TITLE` | Department name | Used as the Department field. Part of the configurable OU hierarchy by default. Example: `ICT` |
| 7 | `OPERATING_UNIT_TITLE` | Operating unit / company name | Used as the Company field. Top-level OU by default (configurable). Example: `Company Name Ltd.` |
| 8 | `LOCATION_TITLE` | Office / branch location | Set as the Office field. Example: `Head Office` |
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
| 14 | `EMP_ID` | Internal HRMS record ID (used in the data request) |
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
- The application reads these field names directly; if a name changes, the data will not be found
- However, the **OU hierarchy field mapping** is configurable — you can map any HRMS field to any OU level using the AD Objects tab in System Configuration (e.g., map `SECTION_TITLE` to Level 3, or set a level to "Skip")
- Similarly, **security group rules** can match on any HRMS field for conditional assignment (e.g., "if `DEPARTMENT_TITLE` = 'ICT', add user to `ICT-Support-Group`")
- `EMP_CODE` is the primary identifier, **not** `EMP_ID`
- The employee-code portion of the address should accept the same value as `EMP_CODE`
- `PIC_URL_` returns a partial path (like `images/repository/HrCrEmp/...`); the application combines it with the **Image Base URL** to display the photo
- The feed should return between 20 and 30 fields; 23 is standard

---

## 5. Example of a Response

When the application requests data, it expects a **structured collection of fields**. Here is what a complete reply covers:

```
Common fields returned:

  EMP_ID, EMP_CODE, EMP_NAME, DESIGNATION, EMAIL, MOBILE,
  OPERATING_UNIT_TITLE, LOCATION_TITLE, DEPARTMENT_TITLE, RANK,
  SECTION_TITLE, PRODUCT_TITLE, SUB_SECTION_TITLE, EMP_STS,
  PIC_URL_, EMP_CAT_TITLE, PRODUCT_GROUP_TITLE, JOINING_DT,
  DOB, AGE, GENDER, RESPONSIBILITY ...
```

### What to Check in the Reply

| Check | What to Look For |
|-------|-----------------|
| All fields present | All 23 fields listed above should appear |
| Field names correct | Capitalization and underscores must match exactly |
| EMP_CODE present | This is the most critical field |
| EMP_STS = "ACTIVE" | User creation requires this value |
| EMAIL is valid | Must be a proper email address with @ |
| PIC_URL_ format | Should be a relative path, not a full URL |

### What Happens If the Reply Is Wrong

- **Field missing**: The application shows a warning and skips that attribute
- `EMP_STS` is not `ACTIVE`: User creation is blocked (prevents creating accounts for resigned employees)
- `EMP_CODE` is missing: User cannot be created at all
- **Feed returns an error**: The application shows the error message from HRMS

---

## 6. Data Feed Operations Available

The application currently uses **two operations**:

### 6.1 Get Employee by ID

- **Purpose**: Fetch full details for a single employee
- **How it's requested**: append the employee code to the data address
- **What it does**: Returns the complete employee record (all 23 fields)
- **Used when**: Creating a single user, viewing employee details, manual testing
- **Reply**: A single record with all fields

### 6.2 Get Employees by Status

- **Purpose**: Fetch a list of all employees with a specific employment status
- **How it's requested**: append the employment status to the data address
- **What it does**: Returns a list of employee records matching the status
- **Used when**: Bulk operations like auditing, mass-disable, status-based reports
- **Reply**: A collection of employee records

### 6.3 OU & Group Customization

The application supports **per-domain customization** of how HRMS fields map to Active Directory OUs and security groups.

- **OU Hierarchy**: Each of 5 OU levels can be independently mapped to any HRMS field (or skipped). Custom prefix/suffix can be applied to all OU names. A live preview shows the generated OU path.
- **Auto-Create Groups**: Security groups are created for each OU level by default. This can be disabled, and group naming follows configurable prefix/suffix patterns.
- **Conditional Group Rules**: Administrators can define rules like "if `DEPARTMENT_TITLE` equals `ICT`, add the user to `ICT-Support-Group`". Rules are evaluated at user creation time.
- **Settings**: All settings are managed per-domain from **System Configuration → AD Objects → OU Management / Group Management** cards.

### 6.4 Image Base URL

- **Purpose**: Display employee profile photos
- **How it works**: The `PIC_URL_` field contains a relative path (e.g., `images/repository/HrCrEmp/...`). The application prepends the Image Base URL to create a full URL.
- **Example**: If Image Base URL is `https://hrms.example.com` and `PIC_URL_` is `images/repository/HrCrEmp/PIC_/59022~abc.png`, the application loads `https://hrms.example.com/images/repository/HrCrEmp/PIC_/59022~abc.png`

---

## 7. How to Set Up the Data Feed

### 7.1 Who Provides the Data?

The data is typically **already available** if your organization uses an HRMS system (like Oracle HRMS, SAP SuccessFactors, HRIS, or a custom HR system). You may need to ask your IT department or software vendor for the data address.

### 7.2 What You Need to Provide to AccessPilot

As the implementing administrator, you need to obtain **two pieces of information**:

| What | Example | How to Get It |
|------|---------|---------------|
| HRMS data address | `https://hrms.company.com/employee` | From your IT/HRMS team |
| Image Base URL | `https://hrms.company.com/images` | From your IT/HRMS team (if profile photos are needed) |

### 7.3 What the Data Feed Must Support

The feed must:

1. **Respond to requests** with the employee code as part of the address
2. **Return a structured collection of fields** (standard, easy-to-read data)
3. **Include all fields listed in Section 4** of this guide
4. **Use a secure, encrypted connection**
5. **Work with self-signed certificates** (the application can be configured to accept them)

### 7.4 For Your IT Team

If your IT team needs to build the data reader, a simple page that looks up an employee by code and returns the fields listed in Section 4 is enough. No complex setup required. The application includes a built-in **Test** button so you can verify the reply before going live.

### 7.5 Testing the Data Feed

Before connecting to AccessPilot, test it with a browser or a basic testing tool:

1. Open your browser
2. Enter: `https://hrms.company.com/employee?emp_id=59022`
3. You should see the employee's data listed on screen
4. If you see an error or blank page, check with your IT team

AccessPilot also has a **built-in test feature** on the System Configuration page — you can enter the data address and test it directly from the application.

---

## 8. Common Questions

### Q: Does the data feed need to be public on the internet?

No. It can be on your internal network only, as long as the AccessPilot server can reach it.

### Q: What if the feed uses an unencrypted connection instead of a secure one?

A secure, encrypted connection is strongly recommended. If a plain connection must be used, the data will not be encrypted in transit.

### Q: Can I change the data address later?

Yes. The data address and Image Base URL can be updated anytime from the System Configuration page.

### Q: What if some fields are not available in my HRMS?

Contact your AccessPilot implementation team. Some fields can be made optional, but the critical fields (EMP_CODE, EMP_NAME, EMP_STS) are required.

### Q: How often does the application request data?

- **On-demand**: When an administrator initiates a user creation or tests an employee code
- **Manual**: There is no automatic background sync; operations are triggered by administrators

### Q: What if the feed returns an error?

The application displays the error message from HRMS. Common issues include:
- Invalid employee code (not found in HRMS)
- Network connectivity problems
- Certificate/SSL errors (the application can handle self-signed certificates)
- Timeout (the feed took too long to respond)

### Q: What is the difference between `EMP_ID` and `EMP_CODE`?

- `EMP_ID`: Internal record ID used by HRMS (sometimes called the system ID)
- `EMP_CODE`: The employee's unique identifier used for login and daily operations

AccessPilot uses `EMP_CODE` as the primary identifier (Windows username).

### Q: Is the data feed used for user updates (not just creation)?

Currently, the feed is used during user creation and on-demand testing. Updates to existing AD accounts (e.g., changed name, new department) are handled through a separate flow.