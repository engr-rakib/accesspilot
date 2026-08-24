# AccessPilot — Security Posture

> How AccessPilot protects itself, its data and its directory. Written for security engineers, auditors and IT leadership.

---

## 1 · Defense in Depth Overview

```
                    ┌─────────────────────────────────────────────┐
   Attacker IP ───► │ 1. Edge IP block/allow list (HTTP 403 empty)│
                    ├─────────────────────────────────────────────┤
                    │ 2. HTTPS (TLS 1.2+)                          │
                    ├─────────────────────────────────────────────┤
  Login ──────────► │ 3. Session guard (HttpOnly, SameSite, rotate)│
                    ├─────────────────────────────────────────────┤
                    │ 4. CSRF token (all /api/ writes)             │
                    ├─────────────────────────────────────────────┤
                    │ 5. RBAC (274 keys, 4 enforcement layers)     │
                    ├─────────────────────────────────────────────┤
  Execute ────────► │ 6. Action-level authorization + license gate │
                    ├─────────────────────────────────────────────┤
                    │ 7. Auditing (every page + action)            │
                    └─────────────────────────────────────────────┘
```

## 2 · Transport & Web Edge

- **TLS everywhere** — Docker ships an in-container Nginx TLS layer; IIS uses its certificate binding. No plain HTTP in production.
- **IP blocking** — active blocklist/allowlist (exact + IPv4/IPv6 CIDR). Blocked sources get **HTTP 403 with an empty body**; allowlist overrides to prevent admin lockout.
- **Headers** — security headers set by the web layer; API responses carry a `600` stale-app advisory so a stale client is told to refresh instead of failing silently.

## 3 · Session Security

| Control | Setting |
|---------|---------|
| Cookie flags | `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS |
| Idle timeout | 15 min normal, 2 hours remember-me |
| Watchdog | Client warns + logs out 1 min before expiry |
| Session ID rotation | Every 5 minutes (fixation resistance) |
| Forced logout | Administrator can terminate any session in real time |
| Strict mode | Sessions must be server-generated (no hand-typed SIDs) |

## 4 · Authentication & Backend Credentials

- **Login** — form + LDAP bind; differentiated messages for locked/expired/disabled/invalid to guide support without aiding enumeration.
- **Credentials never leave the server** — bind passwords and Exchange secrets live in the **secure vault** (mounted outside web root), injected at runtime, and **redacted from all logs**.
- **Kerberos for Exchange** — Linux builds a deployment-specific ticket from vault credentials via `kinit`; the PowerShell remote session authenticates with the ticket, not plaintext passwords.
- **PHP strictness** — no dangerous functions in the allowlist, LDAP and session extensions load explicitly, error display disabled in production.

## 5 · Request-Level Protection

- **CSRF** — every non-GET `/api/` and action request must carry `X-CSRF-Token`; the global fetch wrapper auto-injects it and rejects mismatches in constant time.
- **Rate limiting** — failed-login throttling with configurable attempts/window/ban durations; per-route rate caps for the monitoring and scanner endpoints.
- **Input normalization** — all IDs split on `[\s,;]+` and sanitized per backend (LDAP filters and PowerShell args each get their own escape path so injection is structurally impossible).
- **RBAC on sulfur** — every endpoint re-validates the caller's permission key server-side before touching the directory.

## 6 · Data Protection at Rest

- **Secure vault** — JSON state, credentials and license data in `/data/secure` (Linux) or the AppData vault (Windows), both **outside the web root**, excluded from the repository build, and backed up by host policy.
- **Encrypted password store** — the Password Manager encrypts secrets at rest with deployment keys; sharing is opt-in per entry.
- **Redaction** — response adapters whitelist fields; logs never contain passwords, tokens or full bind credentials.

## 7 · Auditability

Two immutable-ish trails give complete accountability:

- **Activity audit** — actor, timestamp, page/action, target, outcome.
- **Operation logs** — per-feature transcripts with full result messages.

Auditors can answer "who did what, when, and what happened" for every identity change.

## 8 · Licensing (code protection + gating)

- **RSA-2048 signed certificates**, cryptographically bound to the deployment (machine ID + site ID + expiry).
- **Vendor-issued only** — `vendor_license_api` refuses signatures for unlicensed machines and logs every attempt.
- **Restricted mode** — expired/missing licenses switch the portal to **read-only** (HTTP 423 on writes) so operations never silently run unlicensed.
- **Public verification key** ships in the open repo; the private signing key lives only in the vendor console.

## 9 · Hardening Checklist (deployer)

1. Change the seeded administrator password immediately.
2. HTTPS from day one; block plain HTTP.
3. Pin the License Center — configure server override, verify expiry, set renewal policy.
4. Use the vault, not files in web root, for production config.
5. Enable IP allowlist in LDAP (role, not DN, binds) for bind accounts.
6. Review both audit trails weekly.
7. Subscribe to vendor security bulletins for this deployment.

## 10 · Security FAQ

**Q: Credentials in source?** No. Credentials and secrets are never committed; the build pipeline scrubs and the internal repo excludes vault files by pattern.

**Q: Can a blocked user even see the site?** No — HTTP 403 empty body at the web edge, before any code runs.

**Q: What happens if the license lapses?** Writes stop (423), reads continue, renewal is surfaced in-app.

**Q: Is PowerShell safe on a web server?** The runner builds white-listed command scripts, injects credentials at runtime only, redacts output, and runs under WinRM on Linux — never as an interactive shell.

© 2026 AccessPilot Engineering