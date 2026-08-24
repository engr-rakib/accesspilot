# AccessPilot — Architecture (Client View)

> How the application is organised from the outside: the modules you get, how information flows between them, and where your directory and mailbox activity happens. This is the **capability architecture** — the engineering blueprint lives in the internal documentation.

---

## 1 · One Portal, Five Capability Groups

AccessPilot is one console around five families of capability:

```
                         ┌────────────────────────────────────────────┐
                         │            ACCESSPILOT PORTAL              │
                         ├────────────┬────────────┬──────────────────┤
                         │   Identity │  Exchange  │  Observability   │
                         │  Lifecycle │   Studio   │                  │
                         │────────────┼────────────┼──────────────────┤
                         │  People    │  Access &  │  Automation &    │
                         │  Insights  │ Governance │    Reporting     │
                         └────────────┴────────────┴──────────────────┘
                              │            │              │
                    ┌─────────┴──┐  ┌─────┴────┐  ┌──────┴─────┐
                    ▼            ▼  ▼          ▼  ▼            ▼
             Your Directory   Your Mailbox   Your Servers  Your people data
```

Every capability is delivered as a set of pages, actions and reports — no separate tools, no context switching, one permission model across all of it.

---

## 2 · The Five Capability Groups

### Group 1 — Identity Lifecycle
Everything about user accounts: create, modify, move, enable, disable, unlock, reset, group/OU membership, service accounts.

### Group 2 — People Insights
Employee intelligence: full directory search, one-view user story (AD + HRMS), security events, workstation joins, HRMS↔AD reconciliation.

### Group 3 — Exchange Studio
Mailbox and group administration for users and shared resources: mailboxes, distribution groups, quotas, forwarding, permissions, archives — all visual, no command-line expertise required.

### Group 4 — Observability
Infrastructure monitoring of your hosts and containers, AD health checks, and a full network + email diagnostics toolbox (ping, DNS, traceroute, WHOIS, SPF/DKIM/DMARC, blacklists, headers, SMTP...).

### Group 5 — Access & Governance
Who can see/do what: 270+ permissions, role management, approvals, session control, audit trail, IP protection, password store, request portal for self-service.

---

## 3 · Information Flow (what happens when you press a button)

```
You click a button
        │
        ▼
1. Portal verifies identity + permission        (are you allowed? which permissions apply?)
        │
        ▼
2. Portal validates the request                 (correct formats, duplicate protection, safety rules)
        │
        ▼
3. Portal applies intelligence                  (HRMS data, OU/group conventions, service-account policy)
        │
        ▼
4. Change executes against the right target     (your Active Directory / Exchange / filesystem)
        │
        ▼
5. Result comes back                            (success / skipped / failed, per-user, with reasons)
        │
        ▼
6. Portal shows a clear action card + records it (copyable, timestamped, attributable)
```

You never touch the underlying systems directly — the portal is the safe, enforced middleman.

---

## 4 · Where Your Data Lives

AccessPilot never stores copies of your directory. It reads and writes **directly against your existing Active Directory and Exchange** and keeps only:

- **Configuration** — which domains, servers, conventions to use
- **Operational log** — what was done, when, by whom
- **Portal accounts** — who is allowed into the portal and with what role

Sensitive operational data (bind accounts, certificates, machine identity) is stored **outside the web root**, in a protected runtime vault that the OS restricts to the application process only.

---

## 5 · Deployment Shape (the same application, two homes)

| Shape | Where it runs | For whom |
|-------|---------------|----------|
| **Linux / Docker** | Containerised web + application services behind one HTTPS endpoint | Organisations preferring Linux operations |
| **Windows / IIS** | Native IIS website with certificate binding | Organisations standardising on Windows Server |

Both shapes deliver the **same features, same screens, same behaviour** — deployment choice is an ops preference, not a capability downgrade.

---

## 6 · Extensibility & Integration Points

- **HRMS API** — plug in your human-resources system for employee intelligence and intelligent provisioning.
- **Multiple AD forests** — manage more than one domain from the same portal, each with its own settings.
- **Monitoring agents** — register hosts/containers to appear in the observability console.
- **Email tooling** — works against any public DNS/mail infrastructure you already run.

---

## 7 · Runtime Blueprint (internal)

Details of internal orchestration, transport channels and implementation choices are maintained in the **internal architecture documentation** (`internal-docs/ARCHITECTURE.md`), kept out of the public repository by policy.

---

© 2026 AccessPilot Engineering · All Rights Reserved