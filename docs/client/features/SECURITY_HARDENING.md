# Security Hardening — Improvements & Benefits

> Ei document e hardening er shob improvement gula listed. User/admin ke ki benefit pabe, kon layer e ki kora hoise — shegula eikhane banglish e likha ache.

---

## Layer 1: Web Gateway

### 1.1 Rate Limiting
**Ki kora hoise:**
- Login page: 5 request per second (burst 3)
- Portal data: 30 request per second (burst 20)
- Static files: 100 request per second (burst 50)
- Connection limit: 10 concurrent per IP

**Benefit:**
- Brute force attack hole — login attempt auto block hoy
- Automated abuse prevent kore
- DDoS er basic level protection
- Akta user 1000 request/sec pathaileo — amar server safe

```
Attack scenario:     Normal scenario:
   Hacker ──► 100r/s      User ──► 1r/s
              │                     │
              ▼                     ▼
         ❌ BLOCKED            ✅ ALLOWED
         (too many reqs)      (normal response)
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
**Ki kora hoise:** 20+ known attack and scanner patterns block kora hoise:
- Common WordPress admin and login paths — WordPress attackers
- `phpmyadmin`, `adminer` — DB tool scanners
- `.env`, `.git`, `.svn`, `.hg` — Source code leak prevention
- `composer.json`, `package.json`, `artisan` — Development-file detection
- `Dockerfile`, `docker-compose.yml` — Container info leak
- `.bak`, `.swp`, `.sql`, `.log`, `.tar.gz` — Backup file access
- `cgi-bin` — Exploit scanners
- Hidden files (`.`) — Full deny

**Benefit:**
- Internet e rotating bots amader site scan korleo — 404 pabe (not 403, so they can't distinguish valid paths)
- Requests are stopped at the front door before any application work happens

### 1.4 Request Size Limits
**Ki kora hoise:**
| Location | Max Body Size |
|----------|--------------|
| Login/Auth | 1 MB |
| Main app | 1 MB |
| Portal data | 10 MB (global) |
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
| Session resumption | shared:SSL:10m |
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
| Client body buffer | 128 KB |
| Response buffers | 16 × 16 KB |
| Primary response buffer | 32 KB |
| Busy response buffer | 128 KB |

**Benefit:**
- Slow client attack (slow read/post) prevent kore
- Memory usage optimized
- Buffer settings keep stalled connections from tying up resources

### 1.7 HTTP/2
**Ki kora hoise:** HTTP/2 enabled on the secure port.

**Benefit:**
- Multiplexing: akta connection e multiple request parallel pathano jay
- Header compression: less bandwidth

### 1.8 Logging Hardening
**Ki kora hoise:**
- Access logs buffered (flush every few seconds) — disk I/O kombe
- Static file requests log kore na — log size kombe
- Version info hide kore

**Benefit:**
- Log flood attack e disk full hobe na
- Attacker version info detect korte parbe na

---

## Layer 2: Container Level

### 2.1 Web Gateway Container Changes
| Change | Why |
|--------|-----|
| Read-only files | Attacker container break korleo files change korte parbe na |
| Healthcheck | Container running ache ki na monitor kora jay |
| In-memory serving | Recent results served from fast memory (fast + secure) |
| Ordered startup | Components start in the right order |

### 2.2 Application Container Changes
| Area | Read-only | Purpose |
|------|-----------|---------|
| Web content | ✅ | Served pages — read-only |
| Interface materials | ✅ | Screens, styles, scripts |
| Runtime data | ❌ writable | Live data |
| Secure vault | ❌ writable | Accounts, settings, logs |

**Benefit:** Attacker web shell upload korleo — run korte parbe na (web content read-only).

---

## Layer 3: Host Level (Docker Host)

### 3.1 Firewall
| Port | Protocol | Allow From | Reason |
|------|----------|------------|--------|
| 22 | TCP | LAN only | Remote admin access |
| 80 | TCP | Any | HTTP redirect |
| 443 | TCP | Any | HTTPS |

- Default: **deny incoming**, allow outgoing
- Container ports: **empty** (direct mapping only)

### 3.2 Automated Attacker Blocking
| Rule | Action | Max Retry | Ban Time |
|------|--------|-----------|----------|
| Login abuse | Block IP | 5 | 10 min |
| Bot probing | Block IP | 10 | 1 hour |
| Brute force | Block IP | 10 | 30 min |

**Benefit:** Automated IP blocking for repeated attack attempts.

### 3.3 Auto-Start
**Ki kora hoise:** A system service keeps the portal running.
- Server reboot hole auto start hobe
- Infrastructure ready na hoye thakle wait kore

**Benefit:** Kono manual intervention lagbe na — server restart holeo app automatic up hoy.

### 3.4 Log Rotation
**Ki kora hoise:** Logs rotate daily.
- Daily rotation
- 30 days retention
- Clean reopen after rotation

**Benefit:** Disk full hobe na log diye. Old log auto delete hoy.

### 3.5 Docker Network
- Internal network: isolated subnet
- Health stats: internal only
- No ports exposed between containers

**Benefit:** Containers isolated. Direct container access possible na.

---

## Real Life Attack Scenarios

### Scenario 1: Brute Force Attack
```
Before Hardening:
  Attacker ──► 1000 login/min ──► portal busy ──► Server CPU 100%
                                      │
                                      ▼
                              ❌ Server crash

After Hardening:
  Attacker ──► 1000 login/min ──► rate limit
                                      │
                                      ▼
                              ❌ 429 Too Many Requests
                              (5 req/sec only allowed)
                              ✅ Portal stays responsive for real users
```

### Scenario 2: WordPress Scanner Bot
```
Bot ──► /wp-admin/ ──► Portal ──► 404 (blocked)
Bot ──► /wp-content/ ──► Portal ──► 404 (blocked)
Bot ──► /xmlrpc ──► Portal ──► 404 (blocked)
Bot ──► /.env ──► Portal ──► 404 (blocked)
                              │
                              ▼
                      ✅ automated detection → ban IP
```

### Scenario 3: Slow Connection Attack
```
Attacker ──► Slow HTTP headers ──► Portal
                                      │
                                      ▼
                          stalled connections
                          are closed quickly
                                      │
                                      ▼
                          ❌ Connection closed
                          ✅ Gateway stays free for real users
```

---

## Summary

| Layer | Feature | Threat Mitigated |
|-------|---------|-----------------|
| Web gateway | Rate limiting | Brute force, automated abuse |
| Web gateway | Security headers | Clickjacking, XSS, MITM |
| Web gateway | Bot blocking | Scanners, known attackers |
| Web gateway | Request limits | Buffer overflow, memory exhaustion |
| Web gateway | SSL/TLS hardening | Weak cipher, outdated-version downgrade |
| Web gateway | Request buffering | Slow client attacks |
| Web gateway | HTTP/2 | Performance (not security directly) |
| Container | Read-only files | Web shell upload |
| Host | Firewall | Network level access control |
| Host | Automated blocking | Repeated attacker blocking |
| Host | Auto-start service | Service availability |
| Host | Log rotation | Disk space management |
| Host | Docker network | Container isolation |

---

## References

These protections are built into the standard deployment. To apply or verify them on your own servers, see the portal admin guide.