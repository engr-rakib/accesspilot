# AccessPilot — The Application Book

**The complete start-to-admin book.** Every page, every button, every workflow — described the way you will actually use AccessPilot in production. Built by **AccessPilot Engineering** as the definitive guide for evaluation teams, implementers and day-to-day operators.

---

## Contents

1. [What you are getting](#1-what-you-are-getting)
2. [Requirements](#2-requirements)
3. [Technology](#3-technology)
4. [First Run — your 10-minute start](#4-first-run--your-10-minute-start)
5. [The Journey — start to end](#5-the-journey--start-to-end)
6. [Page-by-Page, Button-by-Button](#6-page-by-page-button-by-button)
7. [Every Feature — and the facility it gives you](#7-every-feature--and-the-facility-it-gives-you)
8. [Workflows & Lifecycle with Benefits](#8-workflows--lifecycle-with-benefits)
9. [License & Purchase — how this book becomes your portal](#9-license--purchase--how-this-book-becomes-your-portal)

---

## 1 · What You Are Getting

AccessPilot is a **complete corporate identity command center**. With it, one IT operator can do the work of a whole helpdesk shift — securely and measurably.

**The facilities you get, in one sentence each:**

| Facility | What it means for you |
|----------|----------------------|
| **Create users in seconds** | Type an employee ID → correct OU, groups and attributes appear automatically from HRMS |
| **Fix accounts instantly** | Enable, disable, unlock, reset password — one click, single or bulk |
| **See a full user story** | One screen: AD identity + HRMS record + group membership + security events + workstations |
| **Tame Exchange visually** | Mailboxes, distribution groups, resources, quotas, permissions, archives — no command-line expertise required |
| **Watch your infrastructure** | Live CPU/MEM/Disk/Net/FPM/Docker telemetry, uptime, availability summaries |
| **Diagnose anything** | Ping, DNS, traceroute, MTR, WHOIS, port checks, email DNS/SPF/DMARC/blacklists |
| **Govern everything** | 270+ permission keys, approvals, full audit trail, session control, IP blocking |
| **Stay current** | Health checks, notifications, hourly/daily reports, CSV exports, reconciliation with HRMS |

**Who uses it:** IT admins (daily power), helpdesk operators (quick actions), managers (reports), auditors (trail complete).

---

## 2 · Requirements

### Server (one of two supported platforms)

| Requirement | Linux (Docker) | Windows (IIS) |
|-------------|----------------|---------------|
| Operating system | Any modern Linux distribution with Docker + Docker Compose | Windows Server 2019/2022 with IIS |
| Web serving | Managed by the container stack (HTTP + HTTPS) | IIS 10 |
| Application runtime | PHP 8.2 (bundled in the container image) | PHP 8.5 (ship/install with the IIS package) |
| Required PHP extensions | Bundled with the installer | Bundled with the installer package |
| Secure runtime area | Mounted outside the web root | Protected directory outside the web root |
| Storage | Vault + log volume (host-attached) | Vault + log directory |

### Target environment

| Component | Minimum |
|-----------|---------|
| Active Directory domain | One or more forests (multi-AD supported) |
| Exchange | Exchange 2013+ (mailbox/group operations) |
| HRMS | Any REST API returning employee JSON (for intelligence features) |
| Browser | Chrome / Edge / Firefox — latest 2 versions |

**Optional but recommended:** HTTPS certificate, dedicated service account for AD binds, monitoring agents on your hosts.

---

## 3 · Technology

| Layer | What you can rely on |
|-------|----------------------|
| Frontend | Fast app-style interface with 7 themes; works in modern browsers without extra plugins |
| Orchestration | A single console coordinates every screen, action and report across all five capability groups |
| Directory & Mail | Reads/writes your existing Active Directory and Exchange directly — everything visual, nothing manual |
| Intelligence | Employee data merges into account workflows for correct provisioning |
| Secure storage | Protected runtime area outside the web root holds only operational config, logs and portal accounts |
| Security | Permission-based access, request protection, rate limiting, IP protection, and signed licensing |
| Licensing | Signed digital certificate bound to your deployment; restricted read-only mode when not licensed |
| Integrations | HRMS API, multiple AD forests, monitoring agents — plug in and go |

Read the deep dive → [ARCHITECTURE.md](ARCHITECTURE.md)

---

## 4 · First Run — your 10-minute start

1. **Deploy** (Docker: `docker compose up -d`) and open the portal URL.
2. **Log in** with the seeded default administrator — the **first thing you do is change this password** (mandatory, see [SECURITY.md](SECURITY.md)).
3. **Licensing** — open **License Center** → *Apply Certificate*. Drop in your issued certificate (see §9) or evaluate in read-only mode.
4. **Connect AD** → System Config → *Add Domain*: key, host, base DN, bind account.
5. **Connect HRMS** (for intelligence) → System Config → HRMS API URL.
6. **Create your first user** — Employee Database → *Search*, then **New User**.
7. **Watch it happen** — the action card reports processed/success/skipped/failed in seconds.

---

## 5 · The Journey — start to end

```
 Login/Register
      │
      ▼
 Dashboard ────────► live overview, quick actions, monitoring, notifications
      │
      ├──► Identity Lifecycle          (create → manage → fix → report)
      ├──► Directory Builder           (OUs, groups, membership)
      ├──► People Intelligence         (employee DB, HRMS↔AD, security events)
      ├──► Exchange Studio             (mailboxes, groups, quotas, permissions)
      ├──► Observability              (monitoring, health, diagnostics, email tools)
      ├──► Governance                  (roles, requests, sessions, audit)
      └──► Self Care                    (profile, preferences, password store)

 Every stop is logged. Every action is one or two clicks.
```

---

## 6 · Page-by-Page, Button-by-Button

> Buttons are listed in the order they appear on the page. This is the app exactly as you will meet it.

### 6.1 Login
- **Username / Password** — form + secure authentication against your directory.
- **Remind password?** — remember-me (2-hour session).
- **Forgot Password** — self-service flow.
- **Register** — apply for an account (admin approval).
- **Security notes:** invalid/locked/disabled get different messages, so support replies faster.

### 6.2 Dashboard
- **Quick Actions** — *New User, Unlock, Reset Password, Enable, Disable* (one-click common fixes).
- **Live Monitoring** — node cards with current CPU/MEM/Disk/Net.
- **Recent Activity** — what the team changed and when.
- **Notifications** — broadcasts, renewals, approvals pending.
- **Assistant / Rail** — global search to jump anywhere.

### 6.3 Access Control (User Management)
- **Search filters** — role, status, date.
- **Add User** — create an operator: username, role, permissions.
- **Edit** — change role/status per operator.
- **Approve / Deny** — pending registrations.
- **Permissions tree** — 270+ keys across 19 categories; assign at role level.

### 6.4 Role Management
- **New Role** — name + description.
- **Permissions screen** — check the capability set for any role.
- **Members** — assign roles to users; changes apply immediately.

### 6.5 Create New User
- **Employee ID mode (Intelligent)** — type ID → **Fetch** → HRMS populates everything; choose group template → **Create User**.
- **Manual mode** — for contractors/service accounts (`svc_` detection applies never-expire + no-interactive-logon policy automatically).
- **Fields** — logon name, display name, UPN, OU (picker), groups (multi-select), credentials options.

### 6.6 Edit User
- **Modify** — attributes, OU move, display name, UPN, contact info.
- **Quick actions** — Enable / Disable / Unlock / Reset on the same screen.

### 6.7 User Intelligence (Get User Info)
- **Search** → identity card: AD account, UPN, member-of, plus **HRMS tab** (employee record).
- **Related-ID suggestions** — near-matches auto-load as extra tabs.
- **Security Events / Workstations** — forensics for one user.

### 6.8 Employee Database
- **Search** — name / ID / filter by unit.
- **Add / Edit / Delete** — HRMS directory managed from the portal (audit-logged).
- **Export** — CSV in one click.

### 6.9 OU & Groups Manager (Directory Builder)
- **OU Tree / Group List** — browse.
- **Create OU / Create Group** — wizard (parent picker).
- **Membership** — add/remove users; group pickers are wired into every workflow.

### 6.10 Reports
- **User Report / OU & Group Report** — filters + **CSV Export**.
- **HRMS↔AD Status** — reconciliation view (who's missing where).
- **Usage**: compliance, licensing, onboarding audits.

### 6.11 AD Health Check
- **Run Health** → deep DC assessment (message/deep/report modes).
- **Report** — copyable, filterable health summary of the whole forest.

### 6.12 Exchange Studio
- **Combined Search** — mailbox or group; the two fields disable each other (no accidental mixed searches).
- **Result card** — mailbox detail (edit panel, addresses, size/quotas) or group detail.
- **Mailbox buttons** — Enable/Disable, Quotas, Forwarding, Aliases (proxy addresses), Primary SMTP.
- **Permissions** — Full Access, Send As, Calendar, Delegation.
- **Archives** — enable/disable, restore request, move.
- **Resources** — New Shared / Room / Equipment mailbox.
- **Distribution Group** — New Group, Membership, Delete.
- **Monitoring tab** — databases, quota, queues, message tracking, transport rules, retention, server connection test.

### 6.13 Monitoring (Infrastructure)
- **Node list** — IPs with live status + RTT chips.
- **Per-node card** — CPU/MEM gauges, trend charts (60-point windows), disk bar, FPM workers, Docker CPU/MEM.
- **Add Node** — register a monitored server.
- **Summary** — hourly 24-block grid, 7-day table, uptime %, downtime history.
- **Event logs** — auto-refresh, color-coded.

### 6.14 Diagnostics Card (PING / DNS)
- **Mode toggle** — PING / DNS.
- **Ping** — live 3s polling; detailed `-c 4` for a single target; summary table for many.
- **IP input** — accepts comma/space separated targets; **STOP** ends polling.
- **DNS** — lookup records for any host.

### 6.15 Network & Email Toolbox (Tools)
- **Ping / Traceroute / MTR / WHOIS / Port checks** — full network forensics.
- **Email analytics** — DNS records, headers, blacklists, SPF/DKIM/DMARC, SMTP test, BIMI, MTA-STS.

### 6.16 Password Manager
- **Entries** — URL, username, encrypted password.
- **Share toggle** — per-entry visibility.
- **Generate** — strong password creator.

### 6.17 Request Portal
- **15 AD + 15 Exchange request types** — public/self-service forms.
- **Approval flow** — requests route to the approver queue in Access Control.
- **Tracking** — applicant sees status; history preserved.

### 6.18 Notifications
- **Bell** — unread count, preferences; **Broadcast** (admin-composed announcements); **Toasts**.

### 6.19 Profile
- Edit details, avatar, theme preference, password change, activity view.

### 6.20 License Center
- **Status** — valid / expiring / expired, server + site binding.
- **Apply certificate** — paste/upload `.pem`.
- **Renewal** — alerts at 90/60/30 days; policy display.
- **Restricted mode** — expired? portal becomes read-only (transparent, never silent).

---

## 7 · Every Feature — and the facility it gives you

Full matrix → [FEATURES.md](FEATURES.md). The highlight shelf:

- **Intelligent user creation** — employee ID in, correct profile out. *Facility: zero-typo onboarding.*
- **Bulk quick actions** — `u1, u2, u3` pasted, all processed. *Facility: 5-minute bulk fixes.*
- **One-view user story** — AD + HRMS + events. *Facility: no more tab-hopping.*
- **Visual Exchange** — over 40 mail operations, entirely visual. *Facility: mailbox admin is anyone with permission.*
- **Multi-domain AD** — manage several forests from one portal, each with its own connection settings; Exchange servers are discovered automatically per domain. *Facility: one console for the whole enterprise.*
- **Infrastructure observability** — trends you can act on. *Facility: catch problems before users do.*
- **Governance & audit** — 270+ keys, full trail. *Facility: pass any audit, prove any change.*
- **Self-service requests** — helpdesk becomes review-only. *Facility: less toil, faster users.*

---

## 8 · Workflows & Lifecycle with Benefits

### Workflow A — New employee, day one
1. HRMS feeds Employee Database.
2. Operator opens **New User**, types employee ID, **Fetch**.
3. OU + groups auto-suggest → **Create User**.
4. Exchange auto-provisions mailbox on user create.
5. Card reports success; audit records it.

**Benefit:** onboarding in **< 1 minute**, no repeated data entry, no orphaned mailboxes.

### Workflow B — Account trouble (locked/forgot password)
1. User calls helpdesk (or uses Request Portal).
2. Operator pastes ID into **Reset + Unlock**.
3. Result card: processed 1, success 1. Done.

**Benefit:** average resolution time measured in **seconds**, ticket queue shrinks.

### Workflow C — Manager change / transfer
1. Operator modifies user: new OU, new groups, new manager field.
2. Related-ID suggestions speed up "who else transfers?".
3. Report captures the change for the record.

**Benefit:** compliance-grade change history without extra effort.

### Workflow D — Department head asks "who's in my OU?"
1. OU & Group Manager → select OU → **Export CSV** (or HRMS↔AD report for full view).

**Benefit:** answers in a click, format-ready.

### Workflow E — Something feels slow
1. Monitoring → check node trends → spot the spike.
2. Diagnostic ping/DNS/port check pinpoints the hop.
3. Health check confirms DC health.

**Benefit:** problems found **before** users escalate.

### Workflow F — Someone leaves
1. Quick **Disable** (bulk if needed) — audit records actor + time.
2. HRMS↔AD report flags lingering accounts daily.

**Benefit:** offboarding controlled in seconds; stale accounts never lurk.

---

## 9 · License & Purchase — how this book becomes your portal

AccessPilot is a **licensed product**, and the license is what keeps the platform a living, supported product.

### What the license gives you
- **Operational features unlocked** — all write operations in this book.
- **Deployment binding** — certificate tied to your server + site (no "works on my machine" grey areas).
- **Renewal runway** — 90/60/30-day alerts, so expiry never surprises you.
- **Update + support channel** — vendor-issued renewals come with guidance and updates.

### How it works
1. Run the app in **read-only evaluation** — explore every screen, every report, every workflow.
2. When you're ready to **operate** (create users, change the directory, manage mailboxes), purchase a deployment license.
3. The vendor issues a signed RSA-2048 certificate bound to your environment.
4. **License Center → Apply Certificate** → full mode. Done.

### Purchase this book as a licensee
Send your **machine ID + site ID** (shown in License Center) to the AccessPilot Licensing Desk via your vendor channel. You will receive a signed certificate by return — usually within one business day.

---

© 2026 AccessPilot Engineering · All Rights Reserved
The AccessPilot name, logo and product identity remain the property of AccessPilot Engineering.
No part of this book may be reproduced to re-sell AccessPilot as a rival product.