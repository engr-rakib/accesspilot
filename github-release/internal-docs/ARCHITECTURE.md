# AccessPilot — Architecture

> The full technical blueprint: how the code is organized, how requests flow, and how the dual backends talk to Active Directory and Exchange.

---

## 1 · Technology Stack

| Layer | Linux (Docker) | Windows (IIS) |
|-------|----------------|----------------|
| HTTP / TLS | Nginx 1.25 (alpine) | IIS 10 + certificate binding |
| Language | PHP 8.2-FPM | PHP 8.5.x NTS |
| PHP extensions | `ext-ldap` (primary), GD, zip, mbstring | `php_ldap`, GD |
| Windows admin | PowerShell Core + `PSWSMan` + `krb5-user` over WinRM (HTTP on the Exchange virtual dir) | Windows PowerShell + WinRM |
| Data stores | JSON vault `/data/secure` + log volume | Windows AppData vault + log directory |
| Session store | Server-side file sessions (strict mode) | same |

The only cross-platform dependencies are LDAP and file storage — everything else is +1/0 pure PHP with no Composer runtime.

---

## 2 · Code Organization

```
accesspilot/
├── app/
│   ├── Application/
│   │   └── Http/
│   │       └── Controllers/   ← 50+ endpoint handlers, grouped by feature
│   ├── Ldap/
│   │   ├── Operations/        ← per-op LDAP implementations (raw LDAP, no ORM)
│   │   ├── Router/            ← operation routing + catalog maps
│   │   └── Support/           ← helpers, adapters, feedback formatting, discovery
│   ├── Domain/
│   │   ├── ActiveDirectory/   ← AD-specific logic, PowerShell bridge
│   │   ├── HRMS/              ← employee/HR integration
│   │   ├── Security/          ← blocklists, rate limiting, session mgmt
│   │   ├── License/           ← certificate crypto, signing, verification
│   │   └── ...
│   ├── Infrastructure/
│   │   ├── PowerShell/        ← WinRM runner, cmdlet wrappers
│   │   └── Vault/             ← JSON vault I/O, paths
│   └── Core/                  ← bootstrap, router, session, auth, CSRF
├── config/                    ← layered configuration (+ licence keys)
├── public/                    ← web root (index.php, api/index.php, resources)
├── resources/
│   ├── views/                 ← layouts, pages, partials, components
│   └── ... 
├── scripts/                   ← ops tooling (route fix, backup, salt gen, license admin)
└── docker/                    ← compose + Dockerfile (nginx+php, pwsh)
```

Key design rule: **controllers stay thin**, domain logic lives under `app/Domain`, and **all directory access funnels through `app/Ldap/Operations`** so backends can swap without touching views.

---

## 3 · Request Lifecycle

See [LIFECYCLE.md](LIFECYCLE.md) for the stage-by-stage walk. At HTTP level:

```
public/index.php                       public/api/index.php
 └── block.enforce()                  └── block.enforce()
 └── router.resolve()                 └── license.middleware()
 └── session.start()                  └── csrf.verify()
 └── view.dispatch(page)              └── session.recheck()
     └── shell + SPA module           └── controller.dispatch(endpoint)
                                          └── action.run(backend)
```

---

## 4 · Hybrid SSR + SPA Shell

- **Full loads** → server renders the complete shell (rail, assistant, workspace).
- **SPA loads** → `index.php?page=…` with the SPA header returns structured data; the client module swaps the workspace and re-initializes handlers.
- Global `fetch()` wrapper auto-attaches `X-CSRF-Token` on `/api/` and handles session-expiry redirects.
- Rendering utilities (feedback cards, tabbed info cards, tooltips) are reusable modules with no Bootstrap JS dependency.

---

## 5 · Backend Architecture: LDAP ⇄ PowerShell

```
                 ┌─────────────────────────────────────────────┐
 Action ───────► │ Router (ad_operation_router)                 │
                 │   └ maps action → op → backend preference    │
                 ├──────────────┬──────────────────────────────┤
                 │  LDAP (LDAP) │   PowerShell (PS)            │
                 │  ext-ldap    │   runner + cmdlet wrappers   │
                 │  direct ops  │   sanitized scripts, JSON     │
                 │  TLS/StartTLS│   WinRM (remote) or local    │
                 └──────┬───────┴──────────┬───────────────────┘
                        ▼                  ▼
                  AD on LDAP         Exchange (Kerberos ticket)
                                       + AD via WinRM fallback
```

- **Preference is per-deployment** (config), **fallback is per-operation** (`ldap→ps` or `ps→ldap`).
- Every backend returns a **normalized result**: success/skipped/failed semantics, per-user outcomes, redacted diagnostics.
- The response adapter whitelists fields so downstream consumers never see raw AD internals.

---

## 6 · Data Flow — Example: Create User from HRMS

1. User types an **employee ID** and presses **New User**.
2. `execute_action` → `create_user_action`.
3. **HRMS lookup** resolves the employee record from the HR API.
4. Create op surfaces OU + group conventions; service-account path applies if the ID starts `svc_`.
5. **LDAP create** runs; result is normalized (success/skipped/failed).
6. **LDAP response adapter** whitelists result fields; feedback card renders with summary + copy button.
7. **Disable/unlock/reset** sub-actions chain (for service accounts: disable → set password → enable with never-expire).
8. Every step is logged to the operation log + activity audit.

---

## 7 · Multi-User & Bulk Semantics

- Any ID input is split on `[\s,;]+` → N users.
- LDAP path loops per user inside the transaction scope; PowerShell path passes comma-separated usernames and each script `foreach`s them.
- Aggregators **strip the inline per-user summary line** from outputs and re-emit one combined summary: `Processed: N | Success: X | Skipped: Y | Failed: Z`.
- Status counts obey strict semantics — "already in desired state" is **skipped (0 success)**, not a false success.

---

## 8 · Monitoring & Telemetry

- **Heartbeat**: 10s interval `get_status` + `refresh` pings; stale nodes get re-pinged.
- **Runtime history (rH)**: per-IP RTT windows (max 500, trimmed to 300), seeded from server history + live pushes.
- **60-point sliding windows**: CPU/MEM/Disk/Net (KB/s sum of non-lo interfaces), FPM workers (active/idle/total), Docker CPU/MEM.
- **Trend charts**: SVG/Canvas line+area renderer, one shared renderer for all trend series; dedicated worker chart for FPM.
- **Node summaries**: 24-block hourly grid, 7-day table, uptime % and downtime history (`down_at/up_at/duration_seconds`).
- **Event logs**: auto-refresh 30s, color-coded with status icons, RTT and loss %.

Routes are auto-fixed at boot by a systemd oneshot that discovers every monitored subnet from the node manifest and maintains the correct gateway routes on the Linux host.

---

## 9 · Licensing Architecture

```
 Vendor Console                    Deployment
 ┌──────────────┐                 ┌──────────────────────┐
 │ RSA-2048 key │  signed cert    │ license.middleware   │
 │ vault        │ ───────────────►│  verify + expiry     │
 │ issue/sign   │                 │  read-only mode      │
 │ log attempts │                 │ public key verify    │
 └──────────────┘                 └──────────────────────┘
```

- Certificates bind **machine ID + site ID + expiry**; verification uses the **public key** that ships in this repo.
- The **private key** exists only in the vendor console. Unlicensed machines cannot produce valid certs.
- Expiry → restricted **read-only** mode (HTTP 423 on writes). Renewal policy is configurable via the License Center.

---

## 10 · Security Boundaries (what lives where)

| Asset | Home |
|-------|------|
| Code | image/read-only mount (Linux), IIS app pool |
| Vault (secrets, license, node manifests) | `/data/secure` or AppData — never in web root |
| Logs | host log volume — survives container restarts |
| Sessions | server file sessions, strict mode |
| Blocklist/allowlist | vault JSON, enforced at edge before routing |

---

## 11 · Cross-Platform Parity

The architecture guarantees **feature parity on both platforms** by isolating every OS difference into three seams:

1. **Configuration** (paths, PHP settings) via layered config.
2. **Backend execution** (LDAP via PHP vs PS runner) via the operation router.
3. **Web layer** (Nginx vs IIS rewrite modules) via equivalent rewrite rules.

Teams move between Docker and IIS without re-training, and the same audit/feedback behavior holds regardless of host.

© 2026 AccessPilot Engineering