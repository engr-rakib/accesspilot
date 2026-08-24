# User Creation — Intelligent, Automated, and Always in Control

## Imagine This

A new employee joins on Monday. HR sends the details. The clock starts ticking.

Without automation: an admin manually creates the AD account, picks an OU, assigns groups, sets attributes, hopes everything is right. It takes 15-20 minutes per user. For 50 new hires, that's an entire day.

With AccessPilot: you type the employee ID. Press **New User**. The system handles everything — OU placement, group assignment, attribute population — all from HRMS data. The account is ready in seconds.

Now imagine the edge cases: a contractor who isn't in the HRMS records. A service account for a print server. An existing employee who needs their account rebuilt. The **Manual** button handles those.

---

## Two Ways to Create Any User

| Method | Button | Best For |
|--------|--------|----------|
| **Automated** | "New User" (Core Operations) | HRMS-tracked employees — bulk onboarding, regular hires |
| **Manual** | "Manual" (Advanced Provisioning) | Contractors, service accounts, HRMS exceptions, off-system users |

Both create real Active Directory users. Both are instant. The difference is how much you need to tell the system.

---

## Automated Creation — The "New User" Button

### How It Works

Enter an employee ID or username. Click **New User**. The system does the rest:

```
You type: EMP12345
Click: [New User]
────────────────────────────────────────────────────────────
System auto-fetches from HRMS:
  ├── Employee Name → Display Name, CN
  ├── Department   → Department attribute
  ├── Section      → OU placement
  ├── Email        → mail attribute
  ├── Designation  → Title attribute
  ├── Location     → Office attribute
  └── Company      → Company attribute

System auto-creates OU path:
  Company > Department > Section > Product > SubSection
  (Creates any missing OUs on the fly)

System auto-assigns group:
  Creates or finds "{Section Name} Group" → adds user

System sets all AD fields:
  logon name, display name, title,
  department, company, office, email, manager, etc.
────────────────────────────────────────────────────────────
User created. Ready in seconds.
```

### What Makes It Intelligent

| Capability | What It Does |
|------------|-------------|
| **HRMS auto-fetch** | Fetches employee details from HRMS using the employee ID — no manual data entry needed |
| **Smart OU routing** | Builds the OU path from HRMS organizational hierarchy — users land exactly where they belong |
| **Auto OU creation** | If the required OU doesn't exist, it's created automatically — no pre-configuration needed |
| **Intelligent group assignment** | Auto-creates or assigns a group matching the user's section/team |
| **Attribute enrichment** | Populates title, department, email, company, office, and manager from HRMS — every field filled |
| **Reconciliation** | If the user already exists in AD, the system moves them to the correct OU, enables the account, resets password, and updates group memberships |
| **Password handling** | Generates a secure temporary password and forces change on first login |

---

## Manual Creation — The "Manual" Button

### When You Need It

Not every user lives in HRMS. Contractors, vendors, interns, and service accounts often don't. The Manual button gives you full control with an intuitive form:

```
┌─────────────────────────────────────────────────────────────┐
│  Manual User Creation                                        │
│                                                              │
│  Display Name: [UPS Service Account            ]             │
│  Username:     [svc_ups_monitor                ]             │
│  Description:  [Service account for UPS monitor]             │
│  OU:           [Search... Service Accounts     ]  ▾         │
│  Groups:       [Search... UPS_Admins,          ]  ▾         │
│                [          Svc_Monitors         ]             │
│                                                              │
│  ☑ Service Account                                           │
│  Server/Operation: [UPS Monitoring Server       ]            │
│  ☑ Password never expires                                    │
│                                                              │
│  [Submit]  [Cancel]                                          │
└─────────────────────────────────────────────────────────────┘
```

### What You Can Do

| Feature | How It Works |
|---------|-------------|
| **OU Tree Search** | Type to search — the system shows matching OUs from the AD tree. Pick the right one instantly. |
| **Group Multi-Select** | Search and select multiple groups with autocomplete tags. See all assigned groups before submitting. |
| **Service Account Mode** | Check "Service Account" — username auto-prefixes with `svc_`, OU defaults to Service Accounts, password never expires is enabled by default, and a "Server/Operation" field captures the purpose. |
| **HRMS fallback** | Even in manual mode, the system attempts to fetch HRMS data for extra attribute enrichment — no extra effort needed. |
| **Modify Mode** | The same form doubles as a Modify User tool. Change username, display name, description, OU, group memberships, reset password, and set password policies (must change, can't change, never expires). |

---

## Real Use Cases

### Bulk Onboarding — 50 New Hires

```
Scenario: University hiring season. 50 new faculty members join.
Process:
  1. HR provides spreadsheet with employee IDs
  2. Type first ID → [New User] → done (3 seconds)
  3. Type next ID → [New User] → done (3 seconds)
  ...
  50 users created in under 3 minutes
  Each user: correct OU, correct group, correct attributes
```

Without automation: 15 minutes per user × 50 = 12.5 hours of AD work.

### Service Account for Application

```
Scenario: New print monitoring system needs a service account.
Process:
  1. Click [Manual]
  2. Check "Service Account"
  3. Type: "Print Monitor Service", "svc_print_monitor"
  4. Search and select OU: "Service Accounts"
  5. Search and select groups: "Print_Admins", "Svc_Monitors"
  6. Type server name: "PRT-SRV-01"
  7. Click Submit
  Total time: 30 seconds
```

### Contractor Access

```
Scenario: External auditor needs temporary AD access for 3 months.
They don't exist in HRMS.
Process:
  1. Click [Manual]
  2. Fill: Name, Username, Description
  3. Select OU: "Contractors > External Auditors"
  4. Select groups: "Audit_ReadOnly", "Report_Viewers"
  5. Click Submit
  Total time: 20 seconds
```

### Employee Transfer (Reconciliation)

```
Scenario: Employee moved from Engineering to Sales.
Their AD account still shows old department and OU.
Process:
  1. Type employee ID → [New User]
  2. System detects user already exists
  3. Auto-moves OU to Sales hierarchy
  4. Auto-updates department, title, manager
  5. Auto-updates group memberships
  6. Account is re-enabled with fresh password
  Total time: 3 seconds (auto)
```

---

## How They Compare

| Aspect | New User (Automated) | Manual |
|--------|---------------------|--------|
| **Data source** | HRMS records via employee ID | User provides all fields |
| **OU assignment** | Auto-detected from HRMS hierarchy | User selects via tree search |
| **Group assignment** | Auto-created/assigned from OU | User selects via multi-search |
| **Attributes** | Populated entirely from HRMS | User-provided + HRMS fallback |
| **Service accounts** | Not supported | Full support with auto-prefix |
| **Best for** | Employees in HRMS, bulk onboarding | Contractors, service accounts, exceptions |
| **Time per user** | 2-3 seconds | 20-30 seconds |
| **Re-run on existing user** | Reconciles OU/groups/attributes | Modify mode for targeted updates |

---

## Safety & Intelligence

| Safety Feature | How It Works |
|---------------|-------------|
| **Duplicate detection** | If user already exists, system reconciles instead of failing |
| **OU verification** | System verifies OU exists before creating — creates if missing |
| **Group validation** | Groups are validated against AD before assignment |
| **Attribute fallback** | If HRMS is unavailable, manual fields provide complete fallback |
| **Password security** | Temporary passwords force change on first login |
| **Audit trail** | Every creation logged with operator, timestamp, and result |
| **Permission guard** | Only authorized roles can create users — controlled by RBAC |

---

## What You Get

| Benefit | Impact |
|---------|--------|
| **Instant onboarding** | New users ready in seconds, not hours |
| **Zero errors** | No mistyped attributes or wrong OUs — HRMS data is authoritative |
| **Self-organizing** | OUs and groups are auto-created — no pre-settings needed |
| **Full coverage** | Automated for employees, manual for everything else |
| **Bulk capable** | Handle 1 user or 100 with the same workflow |
| **Service account support** | Dedicated flow with naming conventions and policies |
| **Reconciliation** | Re-run on existing users to fix drift |
| **No AD tools needed** | Everything from the browser — no Remote Desktop, no ADUC |

---

## Summary

**New User** is for when you have an ID and want everything done automatically. **Manual** is for when you need full control over every detail.

Together, they cover every user creation scenario — from bulk onboarding of 100 new employees to creating a single service account for a print server.

No AD tools. No command-line commands. No manual OU lookups.

Type an ID. Click a button. The user is created.

---

*AccessPilot — User Creation, Intelligent and Instant.*
