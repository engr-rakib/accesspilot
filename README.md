# AccessPilot — User Management Portal (UM Portal)

AD/Exchange user management, monitoring, and diagnostics tool. Runs on **Linux Docker** (Nginx + PHP 8.2-FPM) and **Windows IIS** (PHP 8.5.4 NTS).

---

## Quick Start (Linux Docker)

```bash
cd /app/accesspilot/docker
docker compose up -d
```

Then: `https://<server-ip>/`

---

## Architecture

```
Browser ──HTTPS──► nginx (accesspilot_web)
                       │
                  FastCGI :9000
                       │
                  php-fpm (accesspilot_php)
                       │
               ┌───────┴────────┐
               ▼                ▼
          AD LDAP :389    Exchange :80
          (user ops)      (mailbox via WinRM/Kerberos)
```

**Hybrid SSR+SPA** admin shell. 3-pane UI: Rail 68px, Assistant 280px, Workspace fluid. Dual backend: PHP LDAP (primary) + PowerShell (Exchange fallback via WinRM).

### Pages

| Page | Path | Purpose |
|------|------|---------|
| Auth (Admin) | `?page=auth/admin` | AD user admin (enable/disable, pwd reset, unlock, group mgmt, info lookup) |
| HRMS | `?page=auth/employee_db` | Employee database lookup (directory info) |
| User Request | `?page=auth/ad_user_request` | New AD user creation request workflow |
| User Management | `?page=auth/user_management` | App-level user roles & permissions |
| Password Manager | `?page=auth/password_manager` | LDAP bind password management |
| Monitoring | `?page=monitoring` | Server monitoring (vCenter VM style) |
| Exchange | `?page=exchange` | Mailbox & group management via PowerShell |
| Diagnostics | `?page=tools` | Ping, DNS, traceroute, whois, mtr |

---

## Dual Platform

| Aspect | Linux Docker | Windows IIS |
|--------|-------------|-------------|
| Web server | Nginx 1.25-alpine | IIS 10 |
| PHP | 8.2-FPM (ext-ldap, ext-gd) | 8.5.4 NTS (php_ldap.dll) |
| HTTPS | Container Nginx terminates SSL | IIS binds certificate |
| Secure vault | `/data/secure` | `C:\inetpub\Desk_secure_files\` |
| Logs | `/data/logs` | `C:\access_pilot_logs\` |
| AD backend | LDAP (primary) + PS WinRM (Exchange) | LDAP + local PS (fallback) |
| Code mount | Docker bind mount (mostly ro) | Direct filesystem |

---

## Key Features

- **AD User Operations**: Create, enable/disable, unlock, password reset, group membership management
- **HRMS Integration**: Employee directory info via LDAP
- **Exchange Mailbox**: Mailbox enable/disable, email addresses, quotas, distribution groups (via PowerShell + WinRM)
- **Server Monitoring**: vCenter-style container monitoring (CPU, MEM, Disk, Net, PHP-FPM workers, Docker stats) with 60-point trend charts
- **Diagnostics**: Ping (single/multi), DNS lookup, traceroute, mtr, whois
- **Multi-ID Support**: Space/comma/semicolon separated inputs for bulk user operations
- **Notifications**: Bell-based notification system with action taken feedback cards
- **NOC Tooltips**: Custom declarative tooltip system (no Bootstrap JS dependency)

---

## Directories

| Path | Purpose |
|------|---------|
| `public/` | Web root ± 14 PHP stubs (IIS fallback). `index.php` = front controller, `api/index.php` = API gateway |
| `app/Application/Http/Controllers/` | 49 snake_case PHP controllers |
| `app/Application/Http/Router/front_controller.php` | Route map (13 routes) |
| `app/Application/Middleware/session_guard.php` | 15-min idle timeout (2h remember-me), session regen every 5 min |
| `app/Ldap/` | PHP LDAP: connection, operations, router, catalog, user writer/repository |
| `app/Ldap/Router/ad_operation_router.php` | Backend router: `powershell`/`ldap`/`auto` |
| `app/Domain/ActiveDirectory/ad_action_service.php` | AD action service (PS fallback execution) |
| `app/Domain/HRMS/directory_info_service.php` | HRMS directory info processing |
| `app/Infrastructure/PowerShell/` | PowerShell runner + Exchange PS runner (710 lines, 40 cmdlet wrappers) |
| `config/` | App config, RBAC, PowerShell script map |
| `resources/views/` | View templates (layouts, pages, components) |
| `resources/views/layouts/master.php` | 3-pane shell with CSRF token, global fetch(), NOC tooltip init |
| `public/resources/frontend/` | Frontend JS (modules, admin tools) + CSS (components) |
| `scripts/` | SSL cert gen, Exchange host resolver, PowerShell templates, cron jobs, monitoring route fix |
| `docker/` | Docker build/deploy files (Nginx + PHP 8.2-FPM + hardening + backup) |
| `docker/nginx/` | Nginx config (default.conf, gzip.conf, security-headers.conf) |
| `docker/deploy/` | Deploy scripts (up, harden, backup, rollback, cleanup) |
| `docs/` | Technical docs + client-facing feature docs |

---

## Security

| Layer | Implementation |
|-------|---------------|
| CSRF | `bin2hex(random_bytes(32))` per session, validated on non-GET non-auth API calls |
| Session | HttpOnly, SameSite=Lax, Secure on HTTPS, regen every 5 min, 15-min idle timeout (2h remember-me) |
| Rate limiting | 5 failed logins → 30-min lockout; Nginx: login 5r/s, api 30r/s |
| Password policy | ≥8 chars, upper+lower+digit+special |
| LDAP injection | `ldap_escape_dn_component()` on all OU fields |
| Command injection | `powershell_build_command()` escaping |
| Avatar upload | GD re-encoding, extension whitelist |
| Password redaction | Regex in PS command logs |
| Nginx | `server_tokens off`, deny sensitive paths, HSTS, XFO, nosniff, CSP-like headers |
| PHP | `expose_php Off`, `disable_functions`, `open_basedir`, `allow_url_* Off` |
| Host | UFW (22-LAN/80/443), fail2ban (3 jails), systemd auto-start, logrotate |

---

## Docker Deployment

See `docker/README.md` for:
- Architecture diagram
- Request lifecycle flow chart
- Container blueprint (all mounts, capabilities, startup seq)
- Security layers (defense in depth)
- Performance (gzip, FastCGI cache, X-Accel-Redirect)
- Deploy scripts blueprint
- Quick commands

---

## Reference

| File | Content |
|------|---------|
| `docker/README.md` | Full Docker deployment guide |
| `docker/deploy/DEPLOY.md` | Deployment steps A-Z |
| `docker/deploy/BACKUP_RESTORE.md` | Operator backup/restore guide |
| `docs/Technical/nginx/01-hardening-implementation.md` | Nginx hardening details |
| `docs/Technical/nginx/02-implementation.md` | Performance implementation |
| `docs/Technical/nginx/01-architecture.md` | Nginx architecture |
| `docs/Technical/docker/TECHNICAL.md` | Docker technical reference |
| `docs/Technical/backup_restore/TECHNICAL.md` | Backup/restore technical details |
| `docs/Technical/exchange/01-architecture.md` | Exchange integration architecture |
| `docs/client/features/` | Client-facing feature docs (Banglish) |
| `AGENTS.md` | AI session quick reference |
| `DEVELOPMENT_GUIDELINES.md` | Full dev guidelines |
