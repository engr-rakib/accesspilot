# AccessPilot — Security Posture (Client View)

> What AccessPilot guarantees you, and what you must do as a deployer. Written for IT leadership, auditors and security reviewers — the engineering mechanics live in the internal documentation.

---

## 1 · What AccessPilot Guarantees

### Identity & Sessions
- Every operator logs in with their own account and role.
- Sessions are browser-secure by default: cookies are `HttpOnly` + `SameSite` and HTTPS-only.
- Idle sessions expire automatically (15 minutes standard, 2 hours with remember-me) and the portal warns you — and logs you out — before expiry.
- Administrators can see who is online and **terminate any session in real time**.

### Access Control (RBAC)
- **270+ permission keys across 19 categories** — grants are granular enough to give someone "reset passwords, nothing else" or "reports only".
- Every screen and every action re-checks your permission server-side — the button being hidden is never the only defence.
- Four enforcement layers: page access, action access, data scope, and API-level validation.

### Request-Level Protection
- Every write request carries a per-session security token; mismatches are rejected — cross-site request forgery is structurally blocked.
- Login attempts are rate-limited to slow brute force.
- The portal can **block attacker IPs** (or entire subnets) at the edge: blocked sources get an empty 403 response before any logic runs. An allowlist protects your own team from lockout.

### Data Protection
- Sensitive operational data (bind accounts, certificates, machine identity) is stored **outside the web root** in a protected runtime vault.
- Reports and screens show exactly the data your role allows — no tech-support leaks into executive eyes.
- Output is filtered; internal/system details are never echoed back into the browser.

### Auditability
- **Every authenticated page view and every action is logged** with actor, timestamp, target and outcome.
- Feature-level operation logs keep full transcripts per action.
- You can answer, in seconds: who changed what, when, and what happened — for any account, any mailbox, any role.

### Licensing & Operational Integrity
- AccessPilot runs under a **signed digital certificate** tied to your deployment.
- If the license is missing or expired, the portal switches to **restricted read-only mode** — you can always see your data and your history, but write operations are refused — nothing ever runs "off-license" silently.
- Renewal warnings appear at 90, 60 and 30 days, so expiry is never a surprise.

---

## 2 · Defence in Depth (what sits between an attacker and your directory)

```
 Attacker
    │
    ▼
 1. Edge IP protection ────────► blocked? HTTP 403 empty page. Done.
    │
 2. HTTPS (TLS) ───────────────► traffic protected in transit.
    │
 3. Login + sessions ──────────► rate-limited, session-secure, idle timeouts.
    │
 4. Request token ─────────────► every write validated.
    │
 5. Permissions ───────────────► every page/action re-checks the operator's role.
    │
 6. Audit ─────────────────────► every action recorded and attributable.
    ▼
 Your Active Directory / Exchange
```

No single layer carries the whole load — an attacker would need to defeat every one of them in sequence.

---

## 3 · What You Must Do (deployer hardening checklist)

1. **Change the seeded administrator password on first login** — the portal forces this understanding, and the first-run guide walks you through it.
2. **Use HTTPS from day one** across the whole site.
3. **Bind the license** correctly — License Center → apply your signed certificate, set renewal policy, note the server/site binding.
4. **Assign permissions by role, not by exception** — the 19 categories map to real jobs.
5. **Use the vault** for production configuration — never hand-edit files inside the web root.
6. **Create a dedicated bind account** for the portal with the least privilege it needs (no domain-admin membership).
7. **Review the audit trail weekly** — it is designed to be your first line of detection.
8. **Watch the notification centre** for license-expiry and health alerts.

---

## 4 · Security FAQ (client answers)

**Q: Do you see or store my directory data?** No. AccessPilot reads/writes your Active Directory and Exchange directly. It stores only operational configuration, logs and portal accounts.

**Q: What happens if my license expires at 2 a.m.?** Nothing destructive — the portal becomes read-only immediately and explains why. No writes, no silent failures, no data loss.

**Q: Can a blocked user still see any of the site?** No — blocked sources receive a blank 403 response at the edge, before any application logic runs.

**Q: Who can audit?** Anyone with the audit permission — typically IT managers and compliance — sees the full, timestamped trail.

**Q: Are operators exposed to technical plumbing (command shells, raw interfaces)?** No — operators only ever use the portal screens and buttons. Technical transport channels are application-internal and never handed to end users.

© 2026 AccessPilot Engineering · All Rights Reserved