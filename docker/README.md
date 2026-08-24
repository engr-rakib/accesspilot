# AccessPilot — Docker Deployment Guide

AccessPilot runs as a **two-container Docker stack** on Linux (Nginx 1.25-alpine + PHP 8.2-FPM). No host-level reverse proxy — Docker Nginx directly terminates HTTPS on ports 80/443.

Same codebase runs on **Windows IIS** (PHP 8.5.4 NTS) for the AD-site deployment.

---

## 1. Architecture Overview

```
                     ┌──────────────────────────────────────────────┐
                     │               INTERNET / LAN                 │
                     │              Browser → HTTPS:443             │
                     └──────────────────┬───────────────────────────┘
                                        │
                          ┌─────────────▼─────────────────────┐
                          │      HOST: UFW Firewall           │
                          │  Ports: 22(LAN), 80, 443 only     │
                          │  fail2ban: 3 jails (auth, bot,    │
                          │    brute-force)                    │
                          │  systemd: auto-start on boot       │
                          └─────────────┬─────────────────────┘
                                        │
                          ┌─────────────▼─────────────────────┐
                          │   accesspilot_net (bridge)         │
                          │   172.18.x.x /16                   │
                          │                                    │
                          │  ┌────────────────────────┐        │
                          │  │  nginx (accesspilot_web)│        │
                          │  │  Ports: 80→443 redirect │        │
                          │  │         443 → HTTPS     │        │
                          │  │  Config: default.conf    │        │
                          │  │          gzip.conf       │        │
                          │  │          security-headers │        │
                          │  │  Cache: FastCGI (tmpfs)  │        │
                          │  │  X-Accel: avatar serving │        │
                          │  │  cap_drop=ALL, read_only │        │
                          │  └───────────┬──────────────┘        │
                          │              │ FastCGI :9000          │
                          │  ┌───────────▼──────────────┐        │
                          │  │  php (accesspilot_php)    │        │
                          │  │  PHP 8.2-FPM + pwsh       │        │
                          │  │  Ext: ldap, gd, pdo, zip  │        │
                          │  │  PSWSMan + krb5-user      │        │
                          │  │  Self-signed SSL @ boot   │        │
                          │  │  cap_drop=ALL, read_only  │        │
                          │  └───────────────────────────┘        │
                          │            │            │             │
                          │     ┌──────┘            └──────┐      │
                          │     ▼                         ▼      │
                          │  AD DC :389          Exchange :80    │
                          │  (LDAP ops)     (PowerShell WinRM    │
                          │                    via Kerberos)     │
                          └──────────────────────────────────────┘
                                        │
                    ┌───────────────────┼───────────────────────┐
                    │    Host Bind Mounts                      │
                    │  /data/secure   ← Vault (users, config,  │
                    │                   bind secrets, SSL,     │
                    │                   notifications)         │
                    │  /data/logs     ← Audit, LDAP ops, PHP   │
                    │                   errors, nginx access    │
                    │  /home          ← Disk monitoring (ro)   │
                    │  /sys/fs/cgroup ← Container stats (ro)   │
                    │  /app/accesspilot ← Code base (mostly ro)│
                    └──────────────────────────────────────────┘
```

**Key characteristics:**
- No host-level Nginx — container Nginx terminates SSL directly
- Container security: `cap_drop=ALL`, `read_only=true`, `no-new-privileges=true`
- Host hardening: UFW, fail2ban, systemd auto-start, logrotate
- Self-signed SSL (no public domain available for Let's Encrypt)
- **Performance**: gzip, FastCGI cache (tmpfs), X-Accel-Redirect for avatars

---

## 2. Request Lifecycle (Flow Chart)

```
Browser ──HTTPS──► nginx:443
                      │
                      ├─► Location /resources/    → Static file, 7d immutable cache
                      ├─► Location /assets/       → Static file, 7d immutable cache
                      ├─► Location /health        → FastCGI cache (5s), no log
                      ├─► Location ~ \.php$        → FastCGI cache (5s, respects headers)
                      ├─► Location /api/index.php$ → FastCGI cache (5s, forced)
                      ├─► Location ~ ^/(login|auth) → NO cache, strict rate limit (5r/s)
                      ├─► Location /_xaccel/avatar/ → Internal (X-Accel-Redirect only)
                      ├─► Location /_xaccel/avatar/ → 7d immutable cache
                      └─► Location /               → try_files → index.php
                                                           │
                      nginx ───FastCGI :9000──────────► php-fpm
                                                           │
                      ┌────────────────────────────────────┐
                      │  PHP Request Handling               │
                      │                                    │
                      │  public/index.php                   │
                      │    → bootstrap/app.php              │
                      │      → include_path() autoload      │
                      │        → Route to Controller        │
                      │           → Internal API:            │
                      │              /api/index.php          │
                      │                → endpoint=action     │
                      │                  → LDAP / PS / HRMS  │
                      │        → Response JSON/HTML          │
                      │                                    │
                      │  External calls:                    │
                      │    LDAP AD DC :389 (user ops)       │
                      │    WinRM Exchange :80 (mailbox)     │
                      └────────────────────────────────────┘
                                                           │
                      php-fpm ──FastCGI response────────► nginx
                                                           │
                      nginx ───HTTPS response──────────► Browser
```

### FastCGI Cache Decision Flow

```
                    ┌─────────────┐
                    │  GET request?│
                    └──────┬──────┘
                           │
                      ┌────▼────┐
                      │  Yes     │
                      └────┬────┘
                           │
              ┌────────────▼────────────┐
              │  Is it login/auth path? │
              └────────────┬────────────┘
                    ┌──────┴──────┐
                    │  No         │ Yes → Skip cache (bypass)
                    └──────┬──────┘
                           │
              ┌────────────▼────────────┐
              │  Has Pragma/Authorization│
              │  header?                 │
              └────────────┬────────────┘
                    ┌──────┴──────┐
                    │  No         │ Yes → Bypass cache
                    └──────┬──────┘
                           │
              ┌────────────▼────────────┐
              │  Cache key:             │
              │  $scheme$method$host    │
              │  $uri?$cache_args       │
              │  (_=timestamp stripped) │
              └────────────┬────────────┘
                           │
              ┌────────────▼────────────┐
              │  Hit?  ◄─── fcgi:10m   │
              └────────────┬────────────┘
                    ┌──────┴──────┐
               Hit  │             │  Miss
                    │             │
              ┌─────▼────┐  ┌────▼─────┐
              │Return    │  │Pass to   │
              │cached    │  │PHP-FPM   │
              │response  │  │Cache     │
              │(5s TTL)  │  │response  │
              └──────────┘  │(5s TTL)  │
                            └──────────┘
```

---

## 3. Container Blueprint

### 3.1 nginx (accesspilot_web)

| Property | Value |
|----------|-------|
| Image | `nginx:1.25-alpine` |
| Ports | 80 (→HTTPS redirect), 443 (SSL) |
| Capabilities | `cap_drop=ALL`, `cap_add=NET_BIND_SERVICE,NET_RAW` |
| Security | `no-new-privileges=true`, `read_only=true` |
| tmpfs | `/var/cache/nginx/fastcgi_cache` (uid 101) |
| Healthcheck | `nginx -t` every 30s |

**Mounted configs:**

| Host Path | Container Path | Mode | Purpose |
|-----------|---------------|------|---------|
| `./nginx/default.conf` | `/etc/nginx/conf.d/default.conf` | ro | Main config (SSL, cache, locations, rate limits, security) |
| `./nginx/gzip.conf` | `/etc/nginx/conf.d/gzip.conf` | ro | Gzip compression (level 5, all text types) |
| `./nginx/security-headers.conf` | `/etc/nginx/security-headers.conf` | ro | 6 security headers (HSTS, XFO, CSP, etc.) |
| `../public` | `/var/www/html/public` | ro | Web root |
| `../resources` | `/var/www/html/resources` | ro | CSS/JS/font assets |
| `/data/secure` | `/data/secure` | ro | SSL certs, profile images |
| `/data/logs/nginx` | `/var/log/nginx` | rw | Access + error logs |

**Waiting for SSL:** Container waits for `php` to generate SSL cert:
```sh
while [ ! -f /data/secure/ssl/accesspilot.crt ]; do sleep 1; done && exec nginx -g "daemon off;"
```

### 3.2 php (accesspilot_php)

| Property | Value |
|----------|-------|
| Build | `docker/Dockerfile` (php:8.2-fpm-bookworm) |
| PHP Ext | ldap, gd, pdo_mysql, mbstring, zip |
| System | pwsh (PowerShell 7), PSWSMan, krb5-user, ping, traceroute, mtr, whois, dnsutils, cron |
| Capabilities | `cap_drop=ALL`, `cap_add=NET_BIND_SERVICE,NET_RAW,DAC_OVERRIDE` |
| Security | `no-new-privileges=true`, `read_only=true` |

**Writable mounts:**

| Host Path | Container Path | Purpose |
|-----------|---------------|---------|
| `/data/secure` | `/data/secure` | Vault (users, configs, bind secrets, SSL, notifications, profile images) |
| `/data/logs` | `/data/logs` | All logs (PHP errors, LDAP ops, audit, monitoring) |
| `/tmp` | `/tmp` | Host temp (disk/memory stats) |
| `../App_Data` | `/var/www/html/App_Data` | Setup lock, session state |
| `../config` | `/var/www/html/config` | app.php (org registration) |

**Read-only mounts:**

| Host Path | Container Path | Purpose |
|-----------|---------------|---------|
| `/home` | `/home` | Host home (disk utilization) |
| `../app` | `/var/www/html/app` | Core PHP logic (Application, Domain, Ldap, Infrastructure) |
| `../bootstrap` | `/var/www/html/bootstrap` | Bootstrap + router |
| `../public` | `/var/www/html/public` | Web root |
| `../resources` | `/var/www/html/resources` | View templates, CSS |
| `../scripts` | `/var/www/html/scripts` | Cron, PS templates, SSL gen, Exchange host resolver |
| `/sys/fs/cgroup` | `/host-cgroup` | Host cgroup stats (monitoring) |

**Startup sequence:**
```
1. install -d -o www-data  /data/secure, /data/logs, /data/logs/php_error_logs
2. chown www-data  /data/secure/app_notifications
3. chown + chmod 644 config/app.php  (org registration support)
4. php generate-ssl-cert.php          (creates self-signed cert if missing)
5. php resolve_exchange_hosts.php     (discovers Exchange FQDN → /etc/hosts)
6. exec php-fpm                       (main process)
```

### 3.3 Network

```
accesspilot_net (bridge)
  ├── 172.18.x.x  nginx
  └── 172.18.x.x  php

Outbound from php container:
  ├── AD Domain Controllers  :389   (LDAP)
  ├── Exchange Server        :80    (PowerShell WinRM via Kerberos)
  └── Monitored servers      :443   (HTTPS monitoring pings)
```

### 3.4 opcache

| Setting | Value |
|---------|-------|
| jit | tracing |
| memory_consumption | 256M |
| max_accelerated_files | 20000 |
| revalidate_freq | 300 (5 min) |

---

## 4. Security Layers (Defense in Depth)

```
Layer 1: Host (harden.sh)
  ├── UFW firewall          → Only 22(LAN), 80, 443 open
  ├── fail2ban              → 3 jails (auth, botsearch, brute-force)
  ├── logrotate             → Nginx logs rotate daily, 30 day retention
  └── systemd service       → Auto-start on reboot

Layer 2: Container (docker-compose.yml)
  ├── cap_drop=ALL          → No unnecessary capabilities
  ├── read_only=true        → No writes except bind mounts
  ├── no-new-privileges     → No privilege escalation
  └── tmpfs                 → Cache/runtime in memory, not disk

Layer 3: Nginx (default.conf + security-headers.conf)
  ├── server_tokens off     → Hide nginx version
  ├── Rate limits           → login: 5r/s, api: 30r/s, static: 100r/s
  ├── Conn limits           → 5 for login, 10 for app
  ├── Deny sensitive paths  → app/, bootstrap/, .git, wp-admin, etc → 404
  ├── Deny hidden files     → /\. → 404
  ├── HSTS                  → max-age=63072000
  ├── X-Frame-Options       → SAMEORIGIN
  ├── X-Content-Type-Options → nosniff
  ├── XSS-Protection        → 0
  ├── Referrer-Policy       → strict-origin-when-cross-origin
  └── Permissions-Policy    → camera=(), microphone=(), geolocation=()

Layer 4: PHP (php-security.ini)
  ├── expose_php Off
  ├── disable_functions     → system, passthru, proc_open, popen, phpinfo
  ├── open_basedir          → Restricted to app paths
  ├── allow_url_fopen Off
  ├── allow_url_include Off
  └── Session hardening     → httponly, secure, samesite=Strict

Layer 5: Application
  ├── RBAC                  → Per-action, per-page permissions
  ├── CSRF protection       → Token validation on all state-changing requests
  ├── SPA routing           → Frontend route validation
  ├── LDAP bind password    → Stored in vault, not code
  └── Exchange credentials  → In vault, auto-renew Kerberos tickets
```

---

## 5. Performance Optimizations

### gzip (gzip.conf)

| Setting | Value |
|---------|-------|
| Compression level | 5 |
| Min length | 256 bytes |
| Vary header | Accept-Encoding |
| Types | text/*, application/json, font/*, image/svg+xml |

Result: CSS 112KB → 20KB (82% reduction)

### FastCGI Cache (default.conf)

| Setting | Value |
|---------|-------|
| Cache path | `/var/cache/nginx/fastcgi_cache` (tmpfs) |
| Cache zone | `fcgi:10m` |
| TTL | 5 seconds |
| Cache key | `$scheme$method$host$uri?$cache_args` |
| Cache args | `_=timestamp` stripped from key (monitoring polling) |
| Cached locations | `/health`, `/api/index.php$` (forced), `\.php$` (respects headers) |
| Bypass conditions | Pragma/Authorization headers, login/auth paths |

### X-Accel-Redirect (Avatar Serving)

```
PHP reads avatar path from vault
  → Sets header: X-Accel-Redirect: /_xaccel/avatar/{filename}
  → Nginx internal location /_xaccel/avatar/
    → aliases to /data/secure/profile_img/
    → Sets Cache-Control: public, immutable, 7 days
    → Serves file directly (no PHP readfile overhead)
```

---

## 6. Deploy Scripts Blueprint

```
docker/deploy/
├── up.sh              # Startup: stop host services, free ports, docker compose up
├── harden.sh           # One-time: UFW, fail2ban, systemd, logrotate, DOCKER-USER
├── backup.sh           # Backup to /bkp/: code + data, keep last 5, date+time naming
├── rollback.sh         # Interactive restore: pick version, component, specific files
├── post_upload_cleanup.sh  # After WinCP: remove Windows runtime files, fix perms
├── DEPLOY.md           # Full deployment guide (steps A-Z)
└── BACKUP_RESTORE.md   # Operator's backup guide (Banglish commands)
```

### Relationship Diagram

```
┌──────────┐     ┌──────────┐     ┌────────────┐
│ up.sh    │────►│ docker   │────►│ App Live   │
│ (start)  │     │ compose  │     │            │
└──────────┘     │ up -d    │     └─────┬──────┘
                 └──────────┘           │
                                        │
          ┌─────────────────────────────┤
          │                │            │
          ▼                ▼            ▼
   ┌──────────┐    ┌──────────┐  ┌──────────────┐
   │harden.sh │    │backup.sh │  │rollback.sh   │
   │(once)    │    │(cron)    │  │(manual)      │
   │          │    │          │  │              │
   │UFW       │    │Code→/bkp/│  │List backups  │
   │fail2ban  │    │Data→/bkp/│  │Pick version  │
   │systemd   │    │Keep 5    │  │Pick files    │
   │logrotate │    │          │  │Restore       │
   └──────────┘    └──────────┘  └──────────────┘
```

---

## 7. Directory Structure (Current)

```
/app/accesspilot/
├── app/                        # Core PHP (Application, Domain, Ldap, Infrastructure)
├── bootstrap/                  # App bootstrap + router
├── config/                     # App configuration (app.php writable for org registration)
├── public/                     # Web root (index.php, login.php, health.php, assets/)
├── resources/                  # Views, CSS, frontend JS
├── scripts/                    # SSL gen, Exchange host resolver, cron, PS templates
├── App_Data/                   # Session, setup lock
├── docker/
│   ├── .env                    # APP_PORT=80, APP_PORT_SSL=443, TZ=Asia/Dhaka
│   ├── docker-compose.yml      # Service definitions (77 lines)
│   ├── Dockerfile              # PHP 8.2-FPM + pwsh + PSWSMan (55 lines)
│   ├── php-security.ini        # PHP security hardening (19 lines)
│   ├── php-error-logging.ini   # PHP error log config (5 lines)
│   ├── nginx/
│   │   ├── default.conf        # Main Nginx config (194 lines)
│   │   ├── gzip.conf           # Gzip compression (20 lines)
│   │   └── security-headers.conf  # 6 security headers (6 lines)
│   ├── deploy/
│   │   ├── up.sh               # Container startup script
│   │   ├── harden.sh           # Host-level hardening (339 lines)
│   │   ├── backup.sh           # Code + data backup
│   │   ├── rollback.sh         # Interactive restore
│   │   ├── post_upload_cleanup.sh  # WinCP cleanup
│   │   ├── back.sh/restore.sh  # (deprecated)
│   │   ├── DEPLOY.md           # Full deployment guide
│   │   ├── COMMANDS.md         # All commands reference
│   │   └── BACKUP_RESTORE.md   # Operator backup guide
│   └── README.md               # This file
├── docs/
│   ├── Technical/
│   │   ├── nginx/              # Architecture, hardening, performance docs
│   │   ├── docker/             # Docker technical reference
│   │   ├── backup_restore/     # Backup/restore technical detail
│   │   ├── server/             # Server hardening, port mapping
│   │   └── exchange/           # Exchange integration docs
│   └── client/features/        # Client-facing docs (Banglish)
├── AGENTS.md                   # AI session quick reference
└── DEVELOPMENT_GUIDELINES.md   # Full dev guidelines
```

---

## 8. IIS Coexistence (Windows Deployment)

| Aspect | Linux Docker | Windows IIS |
|--------|-------------|-------------|
| Web server | Nginx 1.25-alpine (container) | IIS 10 |
| PHP | 8.2-FPM (ext-ldap, ext-gd) | 8.5.4 NTS (php_ldap.dll) |
| HTTPS | Container Nginx terminates SSL | IIS binds certificate |
| Secure path | `/data/secure` | `C:\inetpub\Desk_secure_files\` |
| Log path | `/data/logs` | `C:\access_pilot_logs\` |
| File transfer | scp/rsync | WinCP from dev machine |
| Code mount | Host bind (ro for code) | Direct filesystem |

---

## 9. Quick Commands

```bash
# Start stack
sudo bash docker/deploy/up.sh

# Or directly:
cd /app/accesspilot/docker && docker compose up -d

# View logs
docker compose logs -f php
docker compose logs -f nginx

# Clear OPcache after PHP changes
docker exec accesspilot_php php -r 'opcache_reset();'

# Shell access
docker exec -it accesspilot_php bash

# Restart (required after WinCP — inode change)
docker compose restart php
docker compose restart nginx

# Full backup
sudo bash docker/deploy/backup.sh

# Interactive restore
sudo bash docker/deploy/rollback.sh

# Host hardening (run once)
sudo bash docker/deploy/harden.sh
```

---

## 10. Troubleshooting

| Problem | Cause | Fix |
|---------|-------|-----|
| App doesn't start after reboot | No systemd | `docker compose up -d` or `up.sh` |
| PHP changes not reflecting | OPcache | `docker exec accesspilot_php php -r 'opcache_reset();'` |
| JS/CSS not updating | Browser cache | Ctrl+F5 hard refresh |
| Cannot reach AD | DNS | Add `extra_hosts` in docker-compose.yml |
| Exchange fails (401) | Kerberos ticket | Auto-renews via LDAP bind password |
| Exchange host not found | /etc/hosts missing | Check resolve_exchange_hosts.php |
| www-data can't write | Mount ownership | `chown 33:33 /data/secure /data/logs` |
| Nginx won't start | SSL cert missing | Check php container logs (generate-ssl-cert.php) |
| WinCP changes not visible | Inode change | `docker compose restart php && restart nginx` |

---

## 11. Reference Documents

| Document | Path |
|----------|------|
| Docker technical reference | `docs/Technical/docker/TECHNICAL.md` |
| Nginx hardening implementation | `docs/Technical/nginx/01-hardening-implementation.md` |
| Nginx performance implementation | `docs/Technical/nginx/02-implementation.md` |
| Nginx architecture | `docs/Technical/nginx/01-architecture.md` |
| Backup/restore technical | `docs/Technical/backup_restore/TECHNICAL.md` |
| Server hardening | `docs/Technical/server/SERVER_HARDENING_GUIDE.md` |
| Exchange architecture | `docs/Technical/exchange/01-architecture.md` |
| Client features | `docs/client/features/` |
| Development guidelines | `DEVELOPMENT_GUIDELINES.md` |
| External storage mapping | `docs/internal/application/external-storage-mapping.md` |
