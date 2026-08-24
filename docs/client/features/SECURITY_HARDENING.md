# Security Hardening — Improvements & Benefits

> Ei document e hardening er shob improvement gula listed. User/admin ke ki benefit pabe, kon layer e ki kora hoise — shegula eikhane banglish e likha ache.

---

## Layer 1: Nginx Level

### 1.1 Rate Limiting
**Ki kora hoise:**
- Login page: 5 request per second (burst 3)
- API: 30 request per second (burst 20)
- Static files: 100 request per second (burst 50)
- Connection limit: 10 concurrent per IP

**Benefit:**
- Brute force attack hole — login attempt auto block hoy
- API滥用 (abuse) prevent kore
- DDoS er basic level protection
- Akta user 1000 request/sec pathaileo — amar server secure

```
Attack scenario:     Normal scenario:
   Hacker ──► 100r/s      User ──► 1r/s
              │                     │
              ▼                     ▼
         ❌ BLOCKED            ✅ ALLOWED
         (429 Too Many)       (normal response)
```

### 1.2 Security Headers
**Ki kora hoise:**
| Header | Value | Ki kore |
|--------|-------|---------|
| `Strict-Transport-Security` | max-age=2 years | HTTP → HTTPS force kore |
| `X-Frame-Options` | SAMEORIGIN | Clickjacking prevent kore |
| `X-Content-Type-Options` | nosniff | MIME sniffing bandh kore |
| `X-XSS-Protection` | 0 | XSS filter (modern browsers e redundant, old browsers er jonno) |
| `Referrer-Policy` | strict-origin | Referrer header control kore |
| `Permissions-Policy` | camera, mic, geo = () | Browser features restrict kore |

**Benefit:**
- HTTPS bypass kora jabe na (HSTS)
- Amader site ke iframe e embed kora jabe na (clickjacking protection)
- XSS attack hard hobe
- Third party site amader URL leak pabe na

### 1.3 Bot Blocking
**Ki kora hoise:** 20+ known bot/wp-admin/attacker pattern block kora hoise:
- `wp-admin`, `wp-content`, `wp-includes`, `xmlrpc.php` — WordPress attackers
- `phpmyadmin`, `adminer` — DB tool scanners
- `.env`, `.git`, `.svn`, `.hg` — Source code leak prevention
- `composer.json`, `package.json`, `artisan` — Framework fingerprinting
- `Dockerfile`, `docker-compose.yml` — Container info leak
- `.bak`, `.swp`, `.sql`, `.log`, `.tar.gz` — Backup file access
- `cgi-bin` — CGI exploit scanners
- Hidden files (`.`) — Full deny

**Benefit:**
- Internet e rotating bots amader site scan korleo — 404 pabe (not 403, so they can't distinguish valid paths)
- PHP-FPM e request e pochhay na — nginx level e block hoy

### 1.4 Request Size Limits
**Ki kora hoise:**
| Location | Max Body Size |
|----------|--------------|
| Login/Auth | 1 MB |
| Main app | 1 MB |
| API | 10 MB (global) |
| File upload (employees) | 10 MB |

**Benefit:**
- Large payload attack prevent kore
- Buffer overflow attack hard hobe
- Memory exhaustion protect kore

### 1.5 SSL/TLS Hardening
**Ki kora hoise:**
| Setting | Value |
|---------|-------|
| Protocols | TLSv1.2 + TLSv1.3 |
| Ciphers | HIGH:!aNULL:!MD5 |
| Session cache | shared:SSL:10m |
| Session timeout | 10 min |
| Session tickets | off |

**Benefit:**
- Old insecure protocols (SSLv2, SSLv3, TLSv1.0, TLSv1.1) disabled
- Weak ciphers (MD5, anonymous) removed
- Perfect Forward Secrecy ensured

### 1.6 Request Buffering
**Ki kora hoise:**
| Buffer | Size |
|--------|------|
| `client_body_buffer_size` | 128 KB |
| `fastcgi_buffers` | 16 × 16 KB |
| `fastcgi_buffer_size` | 32 KB |
| `fastcgi_busy_buffers_size` | 128 KB |

**Benefit:**
- Slow client attack (slow read/post) prevent kore
- Memory usage optimized
- PHP-FPM dirty buffer problem fixed (busy_buffers_size < total buffers)

### 1.7 HTTP/2
**Ki kora hoise:** HTTP/2 enabled on port 443.

**Benefit:**
- Multiplexing: akta connection e multiple request parallel pathano jay
- Header compression: less bandwidth
- Server push: future e static assets push kora jabe

### 1.8 Logging Hardening
**Ki kora hoise:**
- `access_log` buffered (64 KB, flush every 5s) — disk I/O kombe
- Static assets (`/resources/`, `/assets/`) log kore na — log size kombe
- `server_tokens off` — nginx version hide kore

**Benefit:**
- Log flood attack e disk full hobe na
- Attacker nginx version detect korte parbe na

---

## Layer 2: Container Level

### 2.1 Nginx Container Changes
| Change | Why |
|--------|-----|
| Read-only volumes (`:ro`) | Attacker container break korleo files change korte parbe na |
| Healthcheck (`nginx -t`) | Container running ache ki na monitor kora jay |
| tmpfs for cache | FastCGI cache in-memory (fast + secure) |
| Depends on php | PHP container age start hote hobe |

### 2.2 PHP Container Changes
| File | Read-only | Purpose |
|------|-----------|---------|
| `public/` | ✅ :ro | Web root — read-only |
| `resources/` | ✅ :ro | Views, CSS, JS |
| `App_Data/` | ❌ writable | Runtime data |
| `secure path` | ❌ writable | Vault, config, logs |

**Benefit:** Attacker web shell upload korleo — execute korte parbe na (public directory read-only).

---

## Layer 3: Host Level (Docker Host)

### 3.1 UFW Firewall
| Port | Protocol | Allow From | Reason |
|------|----------|------------|--------|
| 22 | TCP | LAN only | SSH access |
| 80 | TCP | Any | HTTP redirect |
| 443 | TCP | Any | HTTPS |

- Default: **deny incoming**, allow outgoing
- DOCKER-USER chain: **empty** (direct port mapping)

### 3.2 Fail2ban
| Jail | Action | Max Retry | Ban Time |
|------|--------|-----------|----------|
| nginx-http-auth | Block IP | 5 | 10 min |
| nginx-botsearch | Block IP | 10 | 1 hour |
| nginx-brute-force | Block IP | 10 | 30 min |

**Benefit:** Automated IP blocking for repeated attack attempts.

### 3.3 Systemd Auto-Start
**Ki kora hoise:** 
```
/etc/systemd/system/accesspilot.service
```
- Server reboot hole auto start hobe
- Docker daemon ready na hoye thakle wait kore

**Benefit:** Kono manual intervention lagbe na — server restart holeo app automatic up hoy.

### 3.4 Logrotate
**Ki kora hoise:** `/etc/logrotate.d/accesspilot-nginx`
- Daily rotation
- 30 days retention
- `nginx -s reopen` after rotation

**Benefit:** Disk full hobe na log diye. Old log auto delete hoy.

### 3.5 Docker Network
- Internal network: `172.18.0.0/16`
- nginx_status: internal only (172.18.0.0/16 + 127.0.0.1)
- No ports exposed between containers

**Benefit:** Containers isolated. Direct container access possible na.

---

## Real Life Attack Scenarios

### Scenario 1: Brute Force Attack
```
Before Hardening:
  Attacker ──► 1000 login/min ──► PHP-FPM busy ──► Server CPU 100%
                                      │
                                      ▼
                              ❌ Server crash

After Hardening:
  Attacker ──► 1000 login/min ──► Nginx rate limit
                                      │
                                      ▼
                              ❌ 429 Too Many Requests
                              (5 req/sec only allowed)
                              ✅ PHP-FPM free for real users
```

### Scenario 2: WordPress Scanner Bot
```
Bot ──► /wp-admin/ ──► Nginx ──► 404 (blocked)
Bot ──► /wp-content/ ──► Nginx ──► 404 (blocked)
Bot ──► /xmlrpc.php ──► Nginx ──► 404 (blocked)
Bot ──► /.env ──► Nginx ──► 404 (blocked)
                              │
                              ▼
                      ✅ fail2ban detects → ban IP
```

### Scenario 3: Slowloris Attack
```
Attacker ──► Slow HTTP headers ──► Nginx
                                      │
                                      ▼
                          client_body_timeout 15s
                          client_header_timeout 15s
                          send_timeout 15s
                                      │
                                      ▼
                          ❌ Connection closed
                          ✅ Nginx worker free
```

---

## Summary

| Layer | Feature | Threat Mitigated |
|-------|---------|-----------------|
| Nginx | Rate limiting | Brute force, API abuse |
| Nginx | Security headers | Clickjacking, XSS, MITM |
| Nginx | Bot blocking | Scanners, known attackers |
| Nginx | Request limits | Buffer overflow, memory exhaustion |
| Nginx | SSL/TLS hardening | Protocol downgrade, weak cipher |
| Nginx | Request buffering | Slow client attacks |
| Nginx | HTTP/2 | Performance (not security directly) |
| Container | Read-only volumes | Web shell upload |
| Host | UFW | Network level access control |
| Host | fail2ban | Automated attacker blocking |
| Host | systemd | Service availability |
| Host | logrotate | Disk space management |
| Host | Docker network | Container isolation |

---

## Files Referenced

| File | Purpose |
|------|---------|
| `docker/nginx/default.conf` | All nginx-level hardening |
| `docker/nginx/security-headers.conf` | 6 security headers |
| `docker/nginx/gzip.conf` | Gzip compression |
| `docker/docker-compose.yml` | Container settings, read-only mounts |
| `docker/deploy/harden.sh` | Host-level hardening script |
| `/etc/systemd/system/accesspilot.service` | Systemd auto-start |
| `/etc/fail2ban/jail.local` | fail2ban config |
| `/etc/logrotate.d/accesspilot-nginx` | Log rotation |
