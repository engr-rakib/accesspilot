# AccessPilot — Application Lifecycle

> A complete, start-to-end walkthrough of how AccessPilot behaves — from the moment the server boots to the moment a user logs out. Written for technical reviewers (security engineers, IT architects, evaluators).

---

## Stage 0 — Deployment Model

AccessPilot ships as one codebase that runs two ways:

- **Linux Docker**: `nginx:1.25` + `php:8.2-fpm` (with `ext-ldap`, GD, zip, mbstring, and PowerShell Core `pwsh` + `PSWSMan` + Kerberos client for Exchange). Persistent data lives in an external **JSON vault** and a **log store**, both mounted read-write; the code is mounted read-only.
- **Windows IIS**: the same codebase behind `web.config` URL rewrites; PHP 8.5 NTS; vault under a Windows AppData path, logs on a dedicated log volume.

All durable configuration, credentials and state live **outside the web root** in a protected vault — never in the codebase.

---

## Stage 1 — Boot & Bootstrap

1. **Request arrives** at `public/index.php`.
2. **IP security filter** runs first: if the source address is in the active blocklist, the server answers **HTTP 403 with an empty body** — the attacker can't even fingerprint the app.
3. The front controller resolves the route (`?route=`, `PATH_INFO`, or `REQUEST_URI`) and dispatches toward a page view or a controller.
4. **Bootstrap** loads configuration from a layered stack and merges runtime overrides from the secure vault.
5. On **first run** the system seeds a default administrator, default roles and empty data stores, then drops a `setup_complete` marker. A secure session starts: `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS, with a per-session CSRF token.

---

## Stage 2 — Session & Security Gate

Every protected request passes the session guard:

- Requires an authenticated username + role in the session; otherwise redirects to login.
- Validates RBAC: loads the user's permission set (274 permission keys across 19 categories).
- Enforces **idle timeout**: 15 minutes for normal sessions, 2 hours when "remember me" is used; the client watchdog warns and logs you out one minute early.
- Regenerates the session identifier every 5 minutes (resists fixation) and re-asserts the remember-me cookie.
- Enforces forced-logout lists (an administrator can terminate any active session in real time).
- Logs the page view to the audit trail.

---

## Stage 3 — Page Routing & Rendering

The page registry maps `?page=` to a view plus its scripts and styles. AccessPilot shows a clean **unauthorized** page when the logged-in role lacks the page permission. The shell renders:

- A compact **rail** for primary navigation,
- An **assistant panel** for search and quick actions,
- A fluid **workspace** for the active page.

Theme and typography tokens are injected as CSS custom properties, so the whole application re-skins instantly from seven built-in themes.

---

## Stage 4 — SPA Content Loading

Navigation is a **hybrid SSR + SPA** flow:

1. Full loads render the shell server-side.
2. In-page navigation requests `index.php?page=…` with an SPA header; the server returns structured JSON.
3. The SPA loader swaps the workspace content, fires an update event, and the page module re-initializes — charts, lists and handlers bind without a full page refresh.

The shell also instruments global `fetch()` to attach the CSRF header to every `/api/` call and to redirect gracefully on session expiry.

---

## Stage 5 — API Request Flow

All AJAX goes through one gateway: `api/index.php`.

1. Blocklist re-check (defense in depth).
2. **License middleware** — unlicensed (expired/missing) deployments switch to **restricted read-only mode** (HTTP 423 on write actions).
3. **CSRF validation** on every non-GET call (`X-CSRF-Token`, constant-time comparison).
4. **Session-idle re-check**, then the session is closed for writes so concurrent AJAX calls don't block each other.
5. Dispatch to the matching controller (50+ endpoints grouped by feature area).

---

## Stage 6 — Backend Resolution (LDAP ↔ PowerShell)

AccessPilot is **dual-backend**:

- **LDAP (primary)** — in-process PHP `ext-ldap`: user create/modify/delete, group & OU management, lookups, health data. TLS via StartTLS / LDAPS, with connection retry resilience and full diagnostic capture on failures.
- **PowerShell (fallback / Exchange)** — an execution runner builds a sanitized command (credentials injected securely, passwords redacted in logs), runs it via WinRM (remote) or locally (IIS), and parses JSON from stdout.
- **Auto mode** tries LDAP first and falls back to PowerShell per operation.

Exchange on Linux authenticates via a **Kerberos ticket** generated from the vault bind credentials and opens a PowerShell session to the Exchange server over HTTP — no plaintext password in transit.

---

## Stage 7 — Action Execution

- Inputs may be **single or bulk**: comma, space or semicolon-separated IDs are split and processed per user.
- Each action returns structured per-user results with clear semantics:
  - **Success** — the state actually changed.
  - **Skipped** — already in the desired state (e.g., already enabled).
  - **Failed** — the change was rejected (with the real AD diagnostic when available).
- Intelligence hooks enrich simple actions: HRMS lookups merge employee data into results; group assignment follows OU conventions; service accounts honor their dedicated lifecycle (disabled → password set → enabled with never-expire policy).

---

## Stage 8 — Result Rendering & Feedback

Every action writes a human-readable **feedback card**:

- Color-coded by outcome (success / error / info),
- Summary line with processed/success/skipped/failed counts,
- Copy-to-clipboard for sharing,
- Auto-dismisses after 45 seconds so queues keep moving.

Lookups render **tabbed info cards** — one tab per user for bulk results, with identity sections (logon name, principal ID / employee code) and related-ID suggestions that auto-load as additional tabs.

---

## Stage 9 — Audit & Logs

Two complementary trails:

- **Activity audit** — every authenticated page view and API action, with actor, timestamp, action, target and outcome.
- **Operation logs** — per-feature script logs (user creation, disable, unlock, group changes, mailbox ops) written to the log store, with full transcripts and emitted result messages.

Logs survive container restarts (host volume) and are rotated by the host.

---

## Stage 10 — Logout & Termination

Three exit paths, all clean and fully logged:

1. **Explicit logout** — session destroyed, cookies cleared, active-session store updated.
2. **Idle timeout** — client watchdog or server check detects inactivity and redirects to login with an explicit message.
3. **Forced termination** — an administrator terminates the session from the console; the user is logged out on their next request.

---

## End of Journey

The lifecycle is designed to be **observable at every step** — the same instrumentation that powers the audit trail also feeds the dashboards, the monitoring cards and the health checks, so the portal is as transparent to its operators as it is to its administrators.

© 2026 AccessPilot Engineering