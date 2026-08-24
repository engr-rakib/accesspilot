# AccessPilot — Feature Catalog

> Every capability, mapped to its value, entry points and backend. Use this as the definitive feature reference for evaluation, demos and sales documentation.

---

## 1 · Active Directory Lifecycle

| # | Feature | Value | Entry points |
|---|---------|-------|--------------|
| 1 | **Instant user lookup** | See everything about a user in one view (AD + HRMS) | Assistant search, user search, info actions |
| 2 | **Quick AD actions** | One-click enable / disable / unlock / password reset | Quick Actions bar, result cards |
| 3 | **Bulk multi-ID operations** | Space/comma/semicolon-separated IDs, processed per user | any Quick Action, per-user results |
| 4 | **Intelligent user creation** | Type an employee ID → correct OU, groups, attributes from HRMS | New User workflow |
| 5 | **Manual user creation** | Contractors, service accounts, HRMS exceptions, off-system users | Manual Provisioning, manual_create_user |
| 6 | **User modification** | Attributes, OU moves, UPN, display name, contact details | Modify card, modify_ad_user |
| 7 | **Directory builder** | Create/delete OUs and groups; manage membership | OU & Groups Manager, create/delete_directory_object |
| 8 | **Group & OU browsing / pickers** | Fast selectors wired into every workflow | get_groups, get_ous, get_group_members |
| 9 | **AD health check** | Deep DC health assessment with actionable report | AD Health Check (message/deep/report) |

## 2 · Identity & Employee Intelligence

| # | Feature | Value | Entry points |
|---|---------|-------|--------------|
| 10 | **Employee database** | Full HRMS directory search/CRUD from the portal | Employee Database page |
| 11 | **HRMS↔AD reconciliation** | Spot divergences between HR and the directory instantly | AD↔HRMS status + report |
| 12 | **User/OU/Group reports** | Compliant, exportable reporting | User Report, OU&Group Report, CSV exports |
| 13 | **Security events & workstations** | Forensic view of a user's security events and joined workstations | lookup_user_workstations, get_user_security_events |
| 14 | **Service account lifecycle** | Dedicated create/verify flow for `svc_` accounts (no interactive logon, strong password, no admin groups) | Manual provisioning service-account toggle |

## 3 · Exchange & Mail

| # | Feature | Value | Entry points |
|---|---------|-------|--------------|
| 15 | **Mailbox management** | Enable/disable, quotas, forwarding, primary SMTP, aliases | Exchange page, mailbox_* actions |
| 16 | **Mailbox permissions** | Full Access, Send As, calendar permissions, delegation | mailbox add/remove full-access/send-as/calendar |
| 17 | **Archives** | Enable/disable archives, restore requests, moves | mailbox archive actions |
| 18 | **Resource mailboxes** | Shared, room, equipment | mailbox_create_shared/room/equipment |
| 19 | **Distribution groups** | Full lifecycle incl. membership management | group search/create/add/remove/delete |
| 20 | **Exchange monitoring** | Databases, quota, queues, message tracking, transport rules, retention | exchange monitoring_* actions |
| 21 | **Smart settings & diagnostics** | Server discovery, connection tests, diagnostic suite | exchange discover/connection_test/diagnostic |
| 22 | **Mailbox hygiene** | Hidden from GAL, litigation hold, OOF, mail tips | mailbox set_hidden_gal/hold/oof/mail_tip |

## 4 · Monitoring & Diagnostics

| # | Feature | Value | Entry points |
|---|---------|-------|--------------|
| 23 | **Infrastructure monitor** | vCenter-style telemetry: CPU/MEM/Disk/Net/FPM/Docker trends | Monitoring page, monitoring_api |
| 24 | **Node summaries** | Hourly/daily/monthly availability with uptime % and downtime history | get_node_summary, get_history_summary |
| 25 | **Network diagnostics** | Ping (single/multi), DNS, traceroute, MTR, WHOIS, port checks | manual_ping, dns_lookup, traceroute, mtr_report, whois_lookup |
| 26 | **Email analysis toolkit** | DNS records, headers, blacklists, validation, SMTP test, BIMI, MTA-STS | Tools → Email tools |

## 5 · Access Control & Governance

| # | Feature | Value | Entry points |
|---|---------|-------|--------------|
| 27 | **Role-based access control** | 270+ permission keys, 19 categories | Access Control page, role editor |
| 28 | **Registration & approvals** | Controlled onboarding with approve/deny flow | Approvals queue in Access Control |
| 29 | **Session control** | Online users, real-time forced termination | Online Users panel |
| 30 | **Secure password store** | Encrypted shared credential store with sharing toggles | Password Manager page |
| 31 | **Multiple AD forests** | Manage several domains from one portal, each with own settings | Domains configuration |

## 6 · Self-Service & Communication

| # | Feature | Value | Entry points |
|---|---------|-------|--------------|
| 32 | **AD/Exchange request portal** | Public self-service requests (15 AD + 15 Exchange types) with tracking | Request Portal page |
| 33 | **Notification center** | Bell, toasts, preferences, broadcasts, admin-composed announcements | Notification bell, inbox |
| 34 | **Profile & preferences** | Self-service profile, avatar, theme preference, activity | Profile page |

## 7 · Security & Licensing

| # | Feature | Value | Entry points |
|---|---------|-------|--------------|
| 35 | **IP blocking** | Self-defense: blocklist/allowlist with CIDR at the web edge | Security panel on the dashboard |
| 36 | **License centre** | Status, expiry, renewal alerts, restricted-mode transparency | License Center |
| 37 | **Signed licensing** | Deployment-bound certificate keeps operations compliant and auditable | License Center apply flow |
| 38 | **Audit & activity** | Full audit trail of pages and actions | User Activity page |
| 39 | **Theming & UX** | 7 themes, app-like navigation, fast interactions | Theme selector, global settings |

---

## Feature dose on one screen

The **Dashboard** ties it all together: quick actions, monitoring, recent activity, notifications and health at a glance — the control room for the whole directory.

© 2026 AccessPilot Engineering