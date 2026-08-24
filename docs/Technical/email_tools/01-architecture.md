# Architecture — Email Analysis Tools

## Overview

Email Analysis Tools provides DNS record inspection, header analysis, blacklist checking, email validation, SMTP testing, BIMI/MTA-STS checks, and mail port scanning through a unified tabbed UI.

## System Components

```
┌─────────────────────────────────────────────────────────────────────┐
│  Browser (email_actions.js)                                        │
│  Tab: DNS Lookup | Header Analysis | Blacklist | Email Validate    │
│  Tab: SMTP Test (SMTP | Port Scan | BIMI | MTA-STS)                │
└──────────────────────────┬──────────────────────────────────────────┘
                           │ POST /api/index.php?endpoint=email_tools
                           │ X-CSRF-Token header (auto-injected)
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│  api/index.php — Router                                             │
│  • CSRF validation via session token                                │
│  • Routes: 'email_tools' → email_tools.php                         │
└──────────────────────────┬──────────────────────────────────────────┘
                           │ require_once
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│  email_tools.php — Controller (218 lines)                           │
│  • Switch/router by `action` parameter                              │
│  • 9 action handlers: dns_lookup, header_parse, blacklist_check,    │
│    email_validate, smtp_test, bimi_check, mta_sts_check, port_scan  │
│  • Exception → JSON error response                                  │
└──────┬──────────┬──────────┬──────────────┬─────────────────────────┘
       │          │          │              │
       ▼          ▼          ▼              ▼
┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────────┐
│dns_      │ │email_    │ │header_   │ │rbl_lookup.php│
│resolver  │ │validator │ │parser.php│ │• 39 RBLs     │
│.php      │ │.php      │ │• Parse   │ │• dig +short  │
│• DNS     │ │• Syntax  │ │  envelope │ │  +time=3     │
│  lookup  │ │• SMTP    │ │• Auth    │ │  +tries=1    │
│• dig     │ │  verify  │ │  results │ │  @8.8.8.8    │
│  @8.8.8.8│ │• Dispo-  │ │• Received│ │              │
│  fallback│ │  sable   │ │  chain   │ │              │
└──────────┘ └──────────┘ └──────────┘ └──────────────┘
                                        ┌──────────────┐
                                        │mail_tools.php│
                                        │• SMTP test   │
                                        │• BIMI check  │
                                        │• MTA-STS     │
                                        │• Port scan   │
                                        └──────────────┘
```

## Key Design Decisions

### ADR-1: Unified API Endpoint (not separate endpoints per tool)

**Status**: Accepted

**Context**: Each tab could have its own API endpoint (`dns_lookup.php`, `blacklist_check.php`, etc.) or share one.

**Decision**: Single endpoint `email_tools` with `action` parameter. Simpler routing, single CSRF boundary, consistent error handling.

**Consequences**:
- One CSRF check per request (shared with all other API endpoints)
- All handlers share the same `try/catch` block
- Adding a new tool = adding one `case` in switch + one handler function

### ADR-2: Public DNS Fallback via dig (not dns_get_record only)

**Status**: Accepted

**Context**: `dns_get_record()` uses system resolver which may be an internal DNS server missing public records. Waltonbd.com MX records were not found in internal AD DNS.

**Decision**: Every DNS lookup function tries `dns_get_record()` first, then falls back to `dig @8.8.8.8` with 3-second timeout.

**Consequences**:
- Requires `dig` binary in container (pre-installed in `docker-php` image)
- Public DNS servers configurable via `dns_public_servers()` (default: 8.8.8.8, 1.1.1.1, 9.9.9.9)
- RBL checks use `dig` exclusively (with `+time=3 +tries=1`) to avoid long hangs
- `dig` is NOT in PHP's disabled functions list

### ADR-3: RBL Check Using dig (not gethostbyname)

**Status**: Accepted

**Context**: `gethostbyname()` blocks on the system resolver with no timeout control. 39 sequential RBL queries could hang for minutes.

**Decision**: Use `dig +short +time=3 +tries=1 A <query> @8.8.8.8` with `exec()`. Each query times out in 3 seconds.

**Consequences**:
- Total RBL scan completes in ~15-30 seconds worst case
- Unreachable RBL servers don't block the scan
- Wrapped in `rbl_dig_lookup()` helper function

## Flow Details

### DNS Lookup Flow

1. `doDnsLookup(domain)` in JS calls `POST /api/index.php?endpoint=email_tools` with `{action: "dns_lookup", domain: "..."}`
2. `handle_dns_lookup()` runs 6 DNS checks in sequence:
   - `dns_resolve_mx()` → system, fallback dig
   - `dns_check_spf()` → via `dns_resolve_txt()`
   - `dns_check_dkim()` → via `dns_resolve_txt()` for 7 selectors
   - `dns_check_dmarc()` → via `dns_resolve_txt()`
   - `mt_check_bimi()` → `default._bimi.{domain}`
   - `mt_check_mta_sts()` → `_mta-sts.{domain}` + HTTPS policy fetch
3. MX IPs resolved via `dns_resolve_a()`
4. Response includes all 6 categories + duration

### Header Analysis Flow

1. User pastes raw email headers
2. `header_full_analysis()` in `header_parser.php` parses:
   - Envelope (From, To, Date, Subject, Message-ID, Return-Path, Reply-To)
   - Authentication results (SPF, DKIM, DMARC status per line)
   - Received chain (each hop: from, by, with, date)
3. Does NOT modify headers — pure analysis

### Blacklist Check Flow

1. Enter IP or domain
2. Domain is resolved to IP via `gethostbyname()` (system resolver)
3. `rbl_check_ip(ip)` iterates 39 RBLs:
   - Reverse IP → `{revIp}.{rbl_host}`
   - `dig @8.8.8.8 A {query} +short +time=3 +tries=1`
   - Response IP = listed, no response = clear
4. Returns total checked, listed count, per-RBL results with latency

### SMTP Test Flow

1. `mt_smtp_test(host, port, timeout=10)` opens `fsockopen()`
2. Reads SMTP banner
3. Sends `EHLO`, collects multiline response
4. Detects STARTTLS support from EHLO capabilities
5. Sends `QUIT`, closes connection
6. Returns reachable, banner, EHLO lines, STARTTLS flag, latency

### Port Scan Flow

1. `mt_port_scan(host, timeout=5)` iterates 8 predefined ports
2. `fsockopen()` each port with 5s timeout
3. Returns open/closed status, banner, latency per port

### BIMI Check Flow

1. `mt_check_bimi(domain)` queries `default._bimi.{domain}` TXT
2. Looks for `v=BIMI1` prefix
3. Extracts `l=` URL for brand logo
4. Returns has_bimi, records, logo_url

### MTA-STS Check Flow

1. `mt_check_mta_sts(domain)` queries `_mta-sts.{domain}` TXT
2. Looks for `v=STSv1` prefix
3. Fetches `https://mta-sts.{domain}/.well-known/mta-sts.txt`
4. Extracts `mode:` from policy content
5. Returns has_sts, DNS records, policy content, mode

## Error Handling

- All controller handlers wrapped in `try/catch (Throwable)`
- Invalid/missing inputs return `{success: false, message: "..."}`
- DNS failures return empty arrays (not errors) — no MX = "No MX records found"
- Connection failures in SMTP/port scan return `reachable: false` with error message
- JS `.catch()` shows error in the relevant result div

## Security

- CSRF token required (auto-injected by fetch wrapper in master.php, or via `getCsrfToken()`)
- POST requests only (GET requests to the endpoint are rejected at router level)
- `file_get_contents()` for MTA-STS policy uses 5-second timeout
- `escapeshellarg()` used in all `dig` command construction
- `exec()` is available but `system`, `passthru`, `proc_open`, `popen`, `pcntl_exec` are disabled

## Files Reference

| File | Purpose | Lines |
|------|---------|-------|
| `app/Domain/Dns/dns_resolver.php` | DNS lookup functions with dig fallback | 196 |
| `app/Domain/Email/email_validator.php` | Syntax validation, MX check, SMTP verify, disposable/role detection | 114 |
| `app/Domain/Email/header_parser.php` | Email header parsing (envelope, auth, received chain) | 145 |
| `app/Domain/Email/rbl_lookup.php` | 39 RBL definitions + check via dig | 120 |
| `app/Domain/Email/mail_tools.php` | SMTP test, BIMI, MTA-STS, port scan | 175 |
| `app/Application/Http/Controllers/email_tools.php` | Controller with 9 action handlers | 266 |
| `public/resources/frontend/js/modules/email_actions.js` | All frontend logic, rendering, fetch | 691 |
| `resources/views/pages/email/view.php` | 5-tab UI (DNS, Header, Blacklist, Validate, SMTP Test) | 285 |

## Related

- Monitoring DNS: `manual_ping` action in `monitoring.php` (separate system, not related)
- Exchange operations use PowerShell/WinRM, not DNS tools
- Controller path convention: `__DIR__ . '/../../../Domain/...'` (3 levels up from Controllers/)
