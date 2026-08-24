# UM Portal — AGENTS.md

> **Quick reference for AI session context.** Full development guidelines: `/opt/accesspilot/DEVELOPMENT_GUIDELINES.md`

## Communication
- User prefers **Banglish** (Bangla + English mixed, written in English/Latin script only)
- NEVER use Bangla/Bengali unicode script — terminal does not support it
- Keep replies short and to the point
- **Laravel-style PHP** (no framework — custom routing via `include_path()`)
- **Dual backend**: LDAP (primary) + PowerShell fallback for AD operations
- **Dual platform**: Linux Docker (Nginx + PHP 8.2-FPM) + Windows IIS (PHP 8.5.4 NTS)
- **Two card types**: feedback (`actionTakenCard`) and info cards (`serverUserInfoDisplay`, `employeeInfoDisplay`)
- **Tabbed info cards** for multi-user results (single user gets 1 tab, multi gets N)

## Key Files
| Area | Path |
|------|------|
| Monitoring JS | `public/resources/frontend/js/modules/monitoring_actions.js` |
| Monitoring view | `resources/views/pages/monitoring/view.php` |
| Monitoring controller | `app/Application/Http/Controllers/monitoring.php` |
| AD action service | `app/Domain/ActiveDirectory/ad_action_service.php` |
| LDAP user operations | `app/Ldap/Operations/ldap_user_writer.php` |
| LDAP user lookup | `app/Ldap/Operations/ldap_user_repository.php` |
| LDAP router | `app/Ldap/Router/ad_operation_router.php` |
| Operation catalog | `app/Ldap/Operations/ldap_operation_catalog.php` |
| LDAP helpers (feedback msg, JSON result) | `app/Ldap/Support/ldap_helpers.php` |
| Controller for actions | `app/Application/Http/Controllers/execute_action.php` |
| Directory info service | `app/Domain/HRMS/directory_info_service.php` |
| Frontend action processor | `public/resources/frontend/js/admin/action_processor.js` |
| Feedback card template | `resources/views/components/global/action_taken_card.php` |
| Info card template | `resources/views/components/global/info_cards.php` |
| UI card wrapper | `resources/views/components/global/ui_card.php` |
| Clipboard utility | `public/resources/frontend/js/admin/clipboard_utility.js` |
| Utils (feedback styling) | `public/resources/frontend/js/admin/utils.js` |
| Components CSS | `public/resources/frontend/css/components.css` |
| Full guidelines | `/opt/accesspilot/DEVELOPMENT_GUIDELINES.md` |

## Critical Conventions (see DEVELOPMENT_GUIDELINES.md for full details)

### Multi-ID support
- Input split by `[\s,;]+` everywhere
- PowerShell path: `ad_action_service.php:214` splits `$username`, implodes with comma, PS scripts `foreach` each
- LDAP path: `ldap_directory_writer.php:378-435` loops per user
- `ldap_user_repository_find_many()` is the multi-user LDAP handler for `get_user_info_bulk`

### Feedback message format
- `ldap_feedback_message()` outputs: `$badge\n\nProcessed: N | Success: X | Skipped: Y | Failed: Z`
- Per-user summary (inline `Processed: 1 | ...`) must be STRIPPED in multi-ID aggregator (`ldap_directory_writer.php:409-410`)
- `ldap_ad_action_message()` prefixes SUCCESS:/ERROR: and appends summary
- `directory_info_service.php` strips lines matching `>> Processed:` from info output

### Status counts (Success/Skipped/Failed) — CRITICAL
- **Already enabled/disabled/unlocked** → `successCount: 0, skippedCount: 1` (NOT 1/1)
- **Actual change** → `successCount: 1, skippedCount: 0`
- Check `ldap_user_writer.php` for enable/disable/unlock skip branches

### Suggestions (related IDs)
- LDAP backend returns `suggestions: { lookedUpUser: [id1, id2, ...] }` in response
- Frontend parses structured suggestions OR falls back to text parsing of "Multiple matching IDs" / "Nearby IDs" in infoOutput
- Suggestion IDs are auto-fetched and shown as additional tabs in server info card only

### Frontend JS conventions
- `styleFeedbackMessage(msg)` in `utils.js` converts `Processed:` line to colored `.status-badge` spans
- `clipboard_utility.js` strips status line via regex `/\n?(>>?\s*)?Processed:[\s\S]*$/i`
- `#actionTakenMessageDisplay` has `display: block !important` overrides `.alert`'s `display: flex`
- Info cards use `buildTabbedCard()` with tab switching via click delegation
- `renderServerHtml()` extracts identity fields (Logon Name from `AD Account:`, Principal ID from `User Principal ID:`)
- Server info output labels: `AD Account:` (sAMAccountName), `User Principal ID:` (userPrincipalName)

### Info card Identity sections
- **Server card**: Identity shows Logon Name + Principal ID
- **Employee card**: Identity shows Employee ID (EMP_CODE) + EMP Code (EMP_ID)

### Scrollbar
- Hidden via `scrollbar-width: none`, `-ms-overflow-style: none`, `::-webkit-scrollbar { display: none }`
- Scrolling still enabled; `scrollbar-gutter: stable` prevents layout shift

## Container Card (vCenter VM Monitor style)
- **Title bar**: CONTAINER label | Image | Status | Name | ID
- **Left col-6**: PHP-FPM WORKERS header + stats + live trend chart (active/idle/total lines) + Listening + Disk bar + Volumes
- **Right col-6**: CPU gauge (70px) + current % + CPU utilization trend chart (48px) | MEM gauge (70px) + usage + MEM utilization trend chart (48px) | NETWORK THROUGHPUT trend chart (45px) + current rate

## System History (sH) — 60 points sliding window
| Array | Source | Content |
|-------|--------|---------|
| `sH.cpu` | `d.cpu.usage` | `{time, value}` % |
| `sH.mem` | `d.memory.used/total` | `{time, value}` % |
| `sH.disk` | `d.disk_overall.used/total` | `{time, value}` % |
| `sH.net` | sum of non-lo `rx_rate` | `{time, value}` KB/s |
| `sH.fpm` | `d.php_fpm` | `{time, active, idle, total}` |
| `sH.dkrCPU` | `d.docker.cpu_usage_pct` | `{time, value}` % |
| `sH.dkrMEM` | `d.docker.memory_usage/memory_limit` | `{time, value}` % |

Trend charts use `renderSysTrendChart(canvasId, datasets)` for line+area charts.
FPM worker chart uses custom `renderFpmWorkerChart()` (3-line: active blue, idle green, total subtle).

## Monitoring Architecture
- **Heartbeat (hb)**: 10s interval — fetches `get_status` + POST `refresh` to ping stale nodes
- **rH object**: Runtime RTT history keyed by IP, populated from server `history` + live 10s pushes (max 500, trimmed to 300)
- **Charts**: `multiNodeChart` (all nodes), `mainStreamChart` + `secondaryStreamChart` (selected node) — all use `rH[ai]` runtime data
- **RTT display**: `rttLabel()` — `<1ms` for values < 1, `Nms` for >= 1, `--` for undefined
- **Chart null handling**: 0 RTT → `null` in line datasets (`spanGaps: false`); bar charts keep 0
- **Uptime**: Calculated from `assigned_at` (node creation timestamp) minus total downtime seconds
- **Downtime tracking**: `downtime_history[]` — `{down_at, up_at, duration_seconds}` per transition
- **Node summary**: `get_node_summary` action — hourly (24-block grid), daily (7-day table), uptime %, down count
- **Event logs**: Auto-refresh every 30s (`logTimer`); color-coded entries with status icon, RTT, loss %

## Tooltip System (Centralized)
- **JS module**: `public/resources/frontend/js/admin/noc_tooltip.js` — loaded globally in `master.php`
- **Declarative**: Add `data-noc-tip="your message"` to any element
- **No Bootstrap JS dependency** — pure JS with `position:fixed`, no `::after` pseudo-element conflicts
- **Configurable**: Set `window.NocTooltipConfig` before script loads (bg, color, fontSize, padding, gap, arrowSize, etc.)
- **Available globally**: `NocTooltip.init()` to re-bind, `NocTooltip.config` for current settings
- **Auto-init**: On DOMContentLoaded, spaContentUpdated, and via MutationObserver
- **Migration**: Replace `data-bs-toggle="tooltip" data-bs-container="body" title="..."` with `data-noc-tip="..."`

### Files already migrated
| File | Status |
|------|--------|
| `views/components/sidebar_actions.php` | Done (all 15 buttons) |
| `views/partials/vertical_rail.php` | Done (all 7 items) |
| `views/pages/monitoring/view.php` | Done (11 NOC buttons) |
| `views/components/global/action_taken_card.php` | Done (copy button) |
| `views/pages/auth/employee_db_view.php` | Done (5 buttons: search, add, modal) |
| `views/pages/auth/user_management_view.php` | Done (5 buttons: role, user, approve/deny) |
| `views/pages/password_manager/view.php` | Done (create password button) |
| `views/partials/header.php` | Done (notification bell, theme swatches) |
| `views/layouts/master.php` | Done (6 buttons: header, modals) |
| `views/components/ad_user_request/admin_card.php` | Done (2 buttons: bulk approve/deny) |
| `js/modules/employee_db_actions.js` | Done (dynamic edit/delete buttons) |

### All tooltips migrated — no remaining `data-bs-toggle="tooltip"` in views or modules.

## Testing
- Hard refresh (Ctrl+F5) required after JS/CSS changes to clear browser cache
- OPcache clear after PHP changes: `docker exec accesspilot_php php -r 'opcache_reset();'`
- Check browser console for `[INFO] refreshInfoCards: users = [...]` to verify multi-user flow
- Test with browser DevTools network tab to verify LDAP vs PowerShell backend

## Network Route Fix
- `scripts/fix-monitoring-routes.sh` auto-fixes routes at boot (systemd: `monitoring-routes.service`)
- Removes dead routes (via `linkdown` interfaces like `virbr`) and adds correct routes via gateway
- Persistent via netplan + systemd oneshot
- **Auto-discovers all /24 subnets** from `/data/secure/monitoring/monitored_servers.json` — no manual config needed
- Each monitored IP's subnet is checked; dead routes cleaned; route added via default gateway

## Multi-Ping (Monitoring) — removed, merged into DIAGNOSTIC PING
- MULTI-PING card + backend removed
- DIAGNOSTIC PING now handles multiple IPs (comma/space separated)
- Single IP → detailed `-c 4` ping output
- Multiple IPs → summary table (reachable + latency per target)
- Shared `#manualPingIp` input accepts comma/space separated IPs

## DIAGNOSTIC Card (PING + DNS merged)
- **Mode switch**: PING/DNS toggle via `.mode-toggle` buttons
- **PING mode**: Live polling every 3s with `-c 1` per target; left: IP list with latest status; right: live response table; STOP button to end polling
- **DNS mode**: Same DNS lookup as before, moved into the same card
- Old DNS LOOKUP card removed
- Backend: `manual_ping` action accepts optional `count` param (1=quick, 4=detailed)

## Exchange Integration (page=exchange)
- **URL:** `index.php?page=exchange`, **API:** `POST /api/index.php?endpoint=exchange`
- **42 actions** — mailbox read/write, groups, monitoring, settings
- **Dual backend:** LDAP (mailbox attrs, server discovery) + PowerShell (write ops via WinRM)
- **Controller:** `app/Application/Http/Controllers/exchange.php` (1401 lines, switch/router)
- **PS runner:** `app/Infrastructure/PowerShell/ExchangePsRunner.php` (710 lines, 40 cmdlet wrappers)
- **JS:** `public/resources/frontend/js/modules/exchange_actions.js` (1736 lines)
- **View:** `resources/views/pages/exchange/view.php` (4-tab: Mailbox/Groups/Monitoring/Settings)
- **RBAC:** 12 permission keys in `config/components_config.php:399-422`
- **Docker:** `pwsh` (PowerShell Core) + `PSWSMan` module + `krb5-user` installed in image via `docker/Dockerfile`
- **Linux auth:** Kerberos (primary) — `exchange_ensure_kerberos_ticket()` creates keytab from LDAP bind password via `ktutil`, then runs `kinit` to cache ticket. `New-PSSession` uses `-Authentication Kerberos` without explicit credentials (uses cached ticket).
- **Transport:** HTTP port 80 to IIS-hosted Exchange PowerShell virtual directory (`/PowerShell/`)
- **Exchange server discovery:** LDAP Config NC → database discovery → mailbox user `msExchHomeServerName` fallback
- **LDAP exchange attrs:** `msExchMailboxGUID`, `proxyAddresses`, `mail`, `mailNickname`, `msExchRecipientTypeDetails`, `msExchRecipientDisplayType`, `msExchUserAccountControl`, quota attrs (read from AD)
- **Host mapping:** Exchange server hostname (`DC-EX-MBX01.WHILDC.COM`) added via `extra_hosts` in `docker/docker-compose.yml` (not in DNS)
- **pwsh binary:** Configured as `/usr/bin/pwsh` on Linux in `config/powershell.php:12` (conditional via `PHP_OS_FAMILY`)
- **Documentation:** `/opt/accesspilot/analysis/mail_solution/exchange/` — TECHNICAL.md, CLIENT.md, AGENT.md

### Exchange config in LDAP domain JSON
```json
"exchange": {
    "enabled": true,
    "server_override": "",
    "ps_uri_override": "http://DC-EX-MBX01.WHILDC.COM/PowerShell/",
    "ps_use_https": false,
    "ps_username": ""
}
```
- `ps_username` empty = falls back to LDAP bind user
- `ps_password` stored in vault: `{secure_path}/ldap/{domain_key}/exchange_ps_password`
- `ps_uri_override` must use hostname (not IP) for Kerberos SPN resolution
- Exchange server hostname MUST resolve in container (DNS or `/etc/hosts`)

### Key Exchange files
| File | Purpose |
|------|---------|
| `exchange.php` | 42 action handlers, RBAC map, audit logging |
| `ExchangePsRunner.php` | PS cmdlet wrappers, inline script builder, Kerberos ticket mgmt (`exchange_ensure_kerberos_ticket()`) |
| `exchange_actions.js` | All UI rendering + event bindings + fetch calls |
| `exchange/view.php` | 3-tab page structure (Recipients/Monitoring/Settings) — Mailbox + Groups merged into one tab |
| `exchange.css` | Minimal page-specific styles (38 lines) |
| `ldap_helpers.php:823-932` | Exchange server/database discovery, proxy parsing |
| `ldap_response_adapter.php:179-200` | `exchange_mailbox` sub-array in API response |
| `ldap_user_writer.php:972-989` | Auto-provision mailbox during AD user create |

### Combined Search (Mailbox + Group)
- **Tab "Mailboxes & Groups"** (`#tab-recipients`) merges old Mailbox + Groups tabs into one.
- Two inputs side-by-side: `#exchangeMailboxIdentity` (mailbox/AD user) + `#exchangeGroupKeyword` (group name).
- **Mutual exclusion**: `input` event toggles `disabled + opacity:0.5` on the other field — only one search type at a time.
- Single `#exchangeCombinedSearchGo` button routes to `loadMailboxList()` or `doGroupSearch()` based on which field has content.
- `showExchangeAction(type, message)` function shows the feedback card (`#exchangeActionCard`) with color-coded border (Error=red, Success=green, Info=blue, default=yellow).
- A single result card (`#exchangeResultCard`) with dynamic `#exchangeResultBody` and `#exchangeResultTitle` shows either mailbox detail or group results based on which search was used.
- Mailbox list table, mailbox detail (edit panel, email addresses, size, quota, action cards), group list table, and group detail all render into `#exchangeResultBody.innerHTML`.
- Enable/Disable buttons sit in `#exchangeResultActions` in the card header, shown/hidden by `renderMailboxResult` based on `mb.has_mailbox`.

### Create Forms (New User / New Group)
- **Search card header** has `btn-outline-primary #exchangeMailboxUserCreateBtn` (New User) and `btn-outline-success #exchangeGroupCreateBtn` (New Group).
- **Create Mailbox User** (`#exchangeMailboxUserCreateForm`): First/Last name, username, display name, primary SMTP, OU dropdown. Backend creates user via LDAP (`ldap_add`) then enables mailbox via Exchange PS (`Enable-Mailbox`). `userAccountControl` = 544 (enabled + password not required).
- **Create Distribution Group** (`#exchangeGroupCreateForm`): Name, alias, description, OU dropdown. Backend calls `New-DistributionGroup` with optional `-OrganizationalUnit` parameter.
- **OU dropdowns** populated from `GET /api/index.php?endpoint=get_ous` via `loadExchangeOus(selectId)` which fetches on form open (lazy-loaded). |

## IP Blocking (Guest Monitor → Blocked IPs)
- **Feature**: block attacker source IPs so the site appears unreachable (HTTP 403, empty body) from that IP.
- **Service**: `app/Domain/Security/ip_block_service.php` — `ip_block_enforce()` runs at the very top of `public/index.php` AND `public/api/index.php` BEFORE routing/login; blocked → `http_response_code(403)` + `exit` (no body).
- **Storage**: `/data/secure/security/blocked_ips.json` via `resolve_secure_path('security', ...)`; `App_Data/` fallback (App_Data is root-owned/read-only in Docker — www-data CANNOT write there; `/data/secure` is the writable vault).
- **Structure**: `{ "enabled": bool, "allowlist": [ip...], "blocklist": [ip|cidr...] }`. Allowlist overrides blocklist (prevents admin lockout). Exact + IPv4/IPv6 CIDR matching (`ip_block_matches()`).
- **API**: `endpoint=ip_block` → `app/Application/Http/Controllers/ip_block.php`; actions `list/add/remove/toggle`; POSTs need `X-CSRF-Token` (auto-injected by master.php fetch wrapper for `/api/`); guarded by `has_permission('page_application_events')`.
- **UI**: "Blocked IPs" button in guest card header (`#guestBlockedIpsBtn`) toggles panel `#guestBlockedIpsPanel` (add IP/CIDR input, Blocking-enabled checkbox, chips with unblock ✕). JS in `dashboard_logic.js` (`loadBlockedIps/addBlockedIp/removeBlockedIp/toggleBlocking`).
- **Admin guard**: controller does `load_user_permissions($_SESSION['role'])` then `has_permission()` — session role key is `role` (`core_admin` → `['*']`), NOT `user_permissions` directly.
- **Notes**: nginx caches `/api/` GETs 5s (`fastcgi_cache_valid 200 5s`) + `use_stale ... http_403` — after unblocking, purge `/var/cache/nginx/fastcgi_cache/*` in `accesspilot_web`. Main `location /` (index.php) is NOT cached.
- **Testing**: OPcache reset (`docker exec accesspilot_php php -r 'opcache_reset();'`) + `kill -USR2 1` after PHP changes, else stale fatal from old code. Session files in `/tmp/sess_*`, `session.use_strict_mode=On` (must generate SID via PHP, not hand-typed).
