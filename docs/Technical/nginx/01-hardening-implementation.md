# Nginx Security Hardening — Implementation Reference

> Technical documentation for all security hardening applied to the AccessPilot Nginx stack. Covers Nginx-level, container-level, and host-level security configurations.

---

## 1. Architecture — Defense in Depth

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         LAYER 3: HOST LEVEL                             │
│                                                                         │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────────────────┐   │
│  │   UFW    │  │ fail2ban │  │ systemd  │  │      logrotate       │   │
│  │ Firewall │  │ 3 jails  │  │ auto-    │  │ nginx logs, 30d     │   │
│  │ 80/443   │  │          │  │ start    │  │ retention            │   │
│  │ only     │  │          │  │          │  │                      │   │
│  └──────────┘  └──────────┘  └──────────┘  └──────────────────────┘   │
├─────────────────────────────────────────────────────────────────────────┤
│                         LAYER 2: CONTAINER LEVEL                        │
│                                                                         │
│  ┌─────────────────────────────────────┐  ┌─────────────────────────┐  │
│  │        Nginx Container              │  │    PHP Container        │  │
│  │  ┌───────────────────────────────┐  │  │  ┌───────────────────┐  │  │
│  │  │  Read-only volumes (:ro)      │  │  │  │  public/:ro       │  │  │
│  │  │  Healthcheck (nginx -t)       │  │  │  │  resources/:ro    │  │  │
│  │  │  Depends on php               │  │  │  │  App_Data/:rw     │  │  │
│  │  │  Internal network only        │  │  │  │  secure/:rw       │  │  │
│  │  └───────────────────────────────┘  │  │  └───────────────────┘  │  │
│  └─────────────────────────────────────┘  └─────────────────────────┘  │
├─────────────────────────────────────────────────────────────────────────┤
│                         LAYER 1: NGINX LEVEL                            │
│                                                                         │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐ │
│  │ Rate     │  │ Security │  │ Bot      │  │ SSL/TLS  │  │ Request  │ │
│  │ Limiting │  │ Headers  │  │ Blocking │  │ Hardening│  │ Limits   │ │
│  │ 5/30/100 │  │ 6 headers│  │ 20+      │  │ TLSv1.2 │  │ 1M-10M   │ │
│  │ req/s    │  │          │  │ patterns │  │ +1.3    │  │ bodies   │ │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘  └──────────┘ │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐              │
│  │ HTTP/2   │  │ Buffers  │  │ Logging  │  │ Nginx    │              │
│  │ Multi-   │  │ 128k/16  │  │ buffer   │  │ Status   │              │
│  │ plexing  │  │ ×16k/32k │  │ 64k +    │  │ internal │              │
│  │          │  │          │  │ selective│  │          │              │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘              │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Nginx Level

### 2.1 Rate Limiting

**File:** `docker/nginx/default.conf` (http context)

```nginx
limit_req_zone $binary_remote_addr zone=login:10m rate=5r/s;
limit_req_zone $binary_remote_addr zone=api:10m rate=30r/s;
limit_req_zone $binary_remote_addr zone=static:10m rate=100r/s;
limit_conn_zone $binary_remote_addr zone=conn_limit:10m;
```

| Zone | Rate | Burst | Applied To | Purpose |
|------|------|-------|------------|---------|
| `login` | 5 req/s | 3 | `location ~ ^/(login\|auth)` | Brute force prevention |
| `api` | 30 req/s | 20 | `location /`, `~ \.php$`, `~ ^/api/` | API abuse protection |
| `static` | 100 req/s | 50 | `location /resources/`, `/assets/` | Static file DoS protection |
| `conn_limit` | 10/IP | — | Login location | Connection flood prevention |

**Flow:**
```
Request ──► Zone Check ──► Rate OK? ──► YES ──► Pass to backend
                              │
                          Rate exceeded?
                              │
                          Burst available? ──► YES ──► Queue, delay, serve
                              │
                              NO
                              ▼
                   429 Too Many Requests
                   (no Retry-After, no body)
```

### 2.2 Security Headers

**File:** `docker/nginx/security-headers.conf`

```nginx
add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;
add_header X-Frame-Options SAMEORIGIN always;
add_header X-Content-Type-Options nosniff always;
add_header X-XSS-Protection "0" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
```

| Header | Value | Mitigates | Mechanism |
|--------|-------|-----------|-----------|
| `Strict-Transport-Security` | `max-age=2 years, includeSubDomains, preload` | SSL Strip, MITM | Browser remembers HTTPS-only for 2 years |
| `X-Frame-Options` | `SAMEORIGIN` | Clickjacking | Browser refuses to render in iframe from different origin |
| `X-Content-Type-Options` | `nosniff` | MIME sniffing attacks | Browser uses declared Content-Type, never guesses |
| `X-XSS-Protection` | `0` | XSS (legacy) | Disables old browser XSS filter (which had bypasses) |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Referrer leakage | Sends full URL same-origin, origin-only cross-origin, nothing on HTTPS→HTTP |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` | Feature abuse | Blocks browser APIs that the app doesn't need |

**Inheritance:**
- Server block: applies to all responses
- `/resources/`, `/assets/` locations: inherited from server block
- Cannot be overridden by PHP (nginx adds after PHP headers)

### 2.3 Bot & Scanner Blocking

**File:** `docker/nginx/default.conf` (server context)

```nginx
location ~ ^/(app|bootstrap|config|scripts|App_Data)/ { deny all; return 404; }
location ~ /\.                                    { deny all; return 404; }
location ~* (wp-admin|wp-content|wp-includes|xmlrpc\.php|phpmyadmin|adminer|
             \.env|\.git|\.svn|\.hg|composer\.json|package\.json|Gruntfile|
             artisan|Dockerfile|docker-compose\.yml|\.bak|\.swp|\.sql|\.log|
             \.tar\.gz|setup\.php|install\.php|cgi-bin) { deny all; return 404; }
```

**Rules breakdown:**

| Pattern | Blocks | Severity |
|---------|--------|----------|
| `^/(app\|bootstrap\|config\|scripts\|App_Data)/` | Internal app directories | Critical |
| `/\.` | All hidden files (.env, .git, .htaccess) | Critical |
| `wp-admin`, `wp-content`, `wp-includes`, `xmlrpc.php` | WordPress scanners | High |
| `phpmyadmin`, `adminer` | Database admin scanners | High |
| `.env`, `.git`, `.svn`, `.hg` | Source code leak attempts | Critical |
| `composer.json`, `package.json`, `Gruntfile`, `artisan` | Framework fingerprinting | Medium |
| `Dockerfile`, `docker-compose.yml` | Container info leak | Medium |
| `.bak`, `.swp`, `.sql`, `.log`, `.tar.gz` | Backup/source file access | High |
| `setup.php`, `install.php`, `cgi-bin` | Setup scripts, CGI exploits | High |

**Note:** All blocked patterns return **404** (not 403) to prevent attackers from distinguishing between "file exists but denied" vs "file does not exist."

### 2.4 SSL/TLS Configuration

```nginx
listen 443 ssl;
http2 on;

ssl_certificate     /data/secure/ssl/accesspilot.crt;
ssl_certificate_key /data/secure/ssl/accesspilot.key;
ssl_protocols       TLSv1.2 TLSv1.3;
ssl_ciphers         HIGH:!aNULL:!MD5;
ssl_session_cache   shared:SSL:10m;
ssl_session_timeout 10m;
ssl_session_tickets off;
```

| Setting | Value | Why |
|---------|-------|-----|
| `ssl_protocols` | `TLSv1.2 TLSv1.3` | SSLv2/v3, TLSv1.0/v1.1 all deprecated (POODLE, BEAST, LUCKY13) |
| `ssl_ciphers` | `HIGH:!aNULL:!MD5` | Strong ciphers only, exclude anonymous (MITM risk) and MD5 (collision risk) |
| `ssl_session_cache` | `shared:SSL:10m` | Cache SSL sessions for faster reconnects (~10,000 sessions in 10MB) |
| `ssl_session_timeout` | `10m` | Session validity |
| `ssl_session_tickets` | `off` | Prevents session ticket forward secrecy issues |

### 2.5 Request Buffers & Timeouts

```nginx
client_max_body_size 10M;                     # Global max body size
client_body_buffer_size 128k;                 # Body buffer before disk write
client_body_timeout 15s;                      # Max time to read body
client_header_timeout 15s;                    # Max time to read headers
send_timeout 15s;                             # Max time to send response
fastcgi_buffers 16 16k;                       # 16 buffers of 16KB = 256KB total
fastcgi_buffer_size 32k;                      # First response part buffer
fastcgi_busy_buffers_size 128k;               # Busy buffer < total (prevents crash)
```

```
client_body_buffer_size 128k
├─ Body ≤ 128k ──► in-memory buffer (fast)
└─ Body > 128k ──► temp file on disk (slow)

fastcgi_buffers 16 16k  (total = 256KB)
fastcgi_busy_buffers_size 128k
├─ First 128k of response sent to client while rest buffered
└─ Prevents: "upstream sent more data than specified in buffers" error
```

**Per-location overrides:**

| Location | `client_max_body_size` | Purpose |
|----------|----------------------|---------|
| `~ ^/(login\|auth)` | 1M | Login forms only |
| `/` (main app) | 1M | Normal requests |
| Global | 10M | File uploads (employees, etc.) |

### 2.6 Logging Configuration

```nginx
server_tokens off;                              # Hide nginx version

# Static assets — no access log
location /resources/ { access_log off; }
location /assets/   { access_log off; }

# Global access log
access_log /var/log/nginx/access.log combined buffer=64k flush=5s;
error_log  /var/log/nginx/error.log;
```

| Setting | Effect |
|---------|--------|
| `server_tokens off` | Response header `Server: nginx` (no version) |
| `access_log off` for static | ~60% of requests not logged — disk I/O reduced |
| `buffer=64k flush=5s` | Log writes batched — reduces disk writes by ~90% |
| `error_log` levels | Default (error) — only errors and above |

### 2.7 Nginx Status (Internal Monitoring)

```nginx
location /nginx_status {
    stub_status on;
    access_log off;
    allow 172.18.0.0/16;    # Docker internal network
    allow 127.0.0.1;        # Localhost
    deny all;
}
```

**Access restricted to:** Docker internal network + localhost only. External requests blocked.

**Endpoint:** `http://nginx:80/nginx_status` (internal, from monitoring container)

### 2.8 HTTP → HTTPS Redirect

```nginx
server {
    listen 80 default_server;
    location / {
        return 301 https://$host$request_uri;
    }
    # nginx_status also available on port 80 for monitoring
    location /nginx_status {
        stub_status on;
        allow 172.18.0.0/16;
        allow 127.0.0.1;
        deny all;
    }
}
```

- All HTTP traffic → permanent 301 redirect to HTTPS
- `nginx_status` remains accessible on port 80 (for monitoring tools that don't use HTTPS)

---

## 3. Container Level

### 3.1 Docker Compose Configuration

**File:** `docker/docker-compose.yml`

```yaml
nginx:
  image: nginx:1.25-alpine
  volumes:
    - ../public:/var/www/html/public:ro            # Read-only web root
    - ../resources:/var/www/html/resources:ro      # Read-only resources
    - ./nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    - ./nginx/gzip.conf:/etc/nginx/conf.d/gzip.conf:ro
    - ./nginx/security-headers.conf:/etc/nginx/security-headers.conf:ro
    - /data/secure:/data/secure:ro                 # Read-only vault
    - /data/logs/nginx:/var/log/nginx              # Writable logs
  tmpfs:
    - /var/cache/nginx/fastcgi_cache:uid=101,gid=101  # In-memory cache
  depends_on:
    - php                                           # Orderly startup
  healthcheck:
    test: ["CMD", "nginx", "-t"]
    interval: 30s
    timeout: 10s
    retries: 3
    start_period: 10s
  networks:
    - accesspilot_net                                # Internal network only
  restart: always

php:
  build: ../docker/Dockerfile
  volumes:
    - ../public:/var/www/html/public:ro              # Read-only web root
    - ../resources:/var/www/html/resources:ro        # Read-only resources
    - ../App_Data:/var/www/html/App_Data             # Writable runtime data
    - /data/secure:/data/secure                      # Writable vault
    - /data/logs:/data/logs                          # Writable logs
    - /home:/home:ro                                 # Read-only home dirs
    - /tmp:/tmp:rw                                   # Session data
  read_only: false                                   # Not fully read-only (needs /tmp)
```

### 3.2 Volume Security Matrix

| Volume Mount | Nginx | PHP | Why |
|-------------|-------|-----|-----|
| `public/` | `:ro` | `:ro` | Web shell prevention — attacker can't write files even with RCE |
| `resources/` | `:ro` | `:ro` | View files shouldn't change at runtime |
| `App_Data/` | — | `:rw` | Runtime data (cache, uploads) |
| `/data/secure` | `:ro` | `:rw` | Nginx only reads (avatar vault), PHP reads/writes |
| `/data/logs` | `:rw` | `:rw` | Both need log write access |

### 3.3 Container Network

```yaml
networks:
  accesspilot_net:
    driver: bridge
    ipam:
      config:
        - subnet: 172.18.0.0/16
```

- Isolated bridge network
- No ports exposed to host for inter-container communication
- Only Nginx port 80/443 mapped to host
- `nginx_status` accessible only within this network

### 3.4 Health Check

```yaml
healthcheck:
  test: ["CMD", "nginx", "-t"]
  interval: 30s
  timeout: 10s
  retries: 3
  start_period: 10s
```

- Tests nginx config validity every 30s
- If config invalid, container marked unhealthy
- Docker orchestration can act on health status

---

## 4. Host Level

### 4.1 UFW Firewall

**Configured via:** `docker/deploy/harden.sh`

```
Status: active

To                         Action      From
--                         ------      ----
22/tcp                     ALLOW       192.168.0.0/16    # SSH (LAN only)
80/tcp                     ALLOW       Anywhere           # HTTP redirect
443/tcp                    ALLOW       Anywhere           # HTTPS

Anywhere                   DENY        (default incoming)
```

**Ports closed by default:** SSH on WAN, all other ports (MySQL, Redis, custom apps).

**DOCKER-USER chain:** Empty (no iptables rules blocking Docker ports). Direct container port mapping works. UFW handles host-level access.

### 4.2 Fail2ban

**Configured via:** `docker/deploy/harden.sh` — `/etc/fail2ban/jail.local`

| Jail | Log Source | Max Retry | Find Time | Ban Time | Action |
|------|-----------|-----------|-----------|----------|--------|
| `nginx-http-auth` | nginx error.log | 5 | 60s | 10min | Block IP via UFW |
| `nginx-botsearch` | nginx error.log | 10 | 60s | 1hour | Block IP via UFW |
| `nginx-brute-force` | nginx access.log | 10 | 60s | 30min | Block IP via UFW |

**Jail details:**

```
nginx-http-auth:
  - Triggers on: HTTP 401 (auth failed)
  - Protects against: Password guessing, credential stuffing

nginx-botsearch:
  - Triggers on: 404 for known bad patterns (wp-admin, .env, etc.)
  - Protects against: Vulnerability scanners, botnets

nginx-brute-force:
  - Triggers on: Repeated requests to login/auth endpoints
  - Protects against: Brute force login attempts
```

**Ban flow:**
```
Attacker ──► 5 failed logins ──► fail2ban detects ──► ufw deny from $IP
                                                           │
                                                           ▼
                                              All ports blocked for $IP
                                              10min / 30min / 1hour
```

### 4.3 Systemd Service

**File:** `/etc/systemd/system/accesspilot.service`

```ini
[Unit]
Description=AccessPilot Docker Stack
Requires=docker.service
After=docker.service network-online.target

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=/app/accesspilot/docker
ExecStart=/usr/bin/docker compose up -d
ExecStop=/usr/bin/docker compose down
StandardOutput=journal

[Install]
WantedBy=multi-user.target
```

| Behavior | Description |
|----------|-------------|
| **Boot** | Starts after Docker + network ready |
| **Start** | `docker compose up -d` — launches all containers |
| **Stop** | `docker compose down` — graceful shutdown |
| **Restart** | Full containers restart |
| **Crash** | Docker compose handles container restart; systemd handles Docker daemon restart |

### 4.4 Logrotate

**File:** `/etc/logrotate.d/accesspilot-nginx`

```
/data/logs/nginx/*.log {
    daily
    rotate 30
    missingok
    notifempty
    compress
    delaycompress
    postrotate
        /usr/bin/docker exec accesspilot_web nginx -s reopen > /dev/null 2>&1 || true
    endscript
}
```

| Setting | Value | Effect |
|---------|-------|--------|
| `daily` | — | Rotate once per day |
| `rotate 30` | 30 days | Keep 30 days of logs (~1GB at 30MB/day) |
| `compress` | gzip | Old logs compressed |
| `delaycompress` | — | Skip compressing most recent rotated log |
| `postrotate` | `nginx -s reopen` | Nginx reopens log files after rotation (no restart needed) |

---

## 5. Request Flow — Complete Security Pipeline

```
                        ┌─────────────────────────┐
                        │     INTERNET / CLIENT   │
                        └───────────┬─────────────┘
                                    │
                       ┌────────────▼─────────────┐
                       │   UFW FIREWALL (HOST)    │
                       │   Only 80/tcp, 443/tcp    │
                       │   Other ports: DROP       │
                       └────────────┬─────────────┘
                                    │
                       ┌────────────▼─────────────┐
                       │   DOCKER PORT MAPPING     │
                       │   Host:80 → Container:80 │
                       │   Host:443 → Container:443│
                       │   DOCKER-USER: empty      │
                       └────────────┬─────────────┘
                                    │
                       ┌────────────▼─────────────┐
                       │   NGINX PORT 80           │
                       │   HTTP → HTTPS redirect   │
                       │   (301, HSTS preload)     │
                       └────────────┬─────────────┘
                                    │ (HTTPS)
                       ┌────────────▼─────────────┐
                       │   NGINX PORT 443          │
                       │   ┌───────────────────┐  │
                       │   │ SSL Termination   │  │
                       │   │ TLSv1.2 + TLSv1.3  │  │
                       │   │ Session cache     │  │
                       │   └────────┬──────────┘  │
                       │            │              │
                       │   ┌────────▼──────────┐  │
                       │   │ Security Headers   │  │
                       │   │ HSTS, XFO, etc    │  │
                       │   └────────┬──────────┘  │
                       │            │              │
                       │   ┌────────▼──────────┐  │
                       │   │ Rate Limit Check   │  │
                       │   │ login/api/static   │  │
                       │   └────────┬──────────┘  │
                       │            │              │
                       │   ┌────────▼──────────┐  │
                       │   │ Bot Pattern Check  │  │
                       │   │ 20+ patterns → 404│  │
                       │   └────────┬──────────┘  │
                       │            │              │
                       │   ┌────────▼──────────┐  │
                       │   │ Location Routing   │  │
                       │   ├── /resources/ (7d) │  │
                       │   ├── /assets/ (7d)    │  │
                       │   ├── /_xaccel/ (int)  │  │
                       │   ├── /login|auth (5r) │  │
                       │   ├── /health (cached) │  │
                       │   ├── /api/ (cached)   │  │
                       │   └── \.php$ (cached)  │  │
                       └────────────┬─────────────┘
                                    │
                       ┌────────────▼─────────────┐
                       │  NGINX LOGS              │
                       │  ┌───────────────────┐   │
                       │  │ access.log        │   │
                       │  │ (buffered 64k)     │   │
                       │  │ error.log         │   │
                       │  │                    │   │
                       │  └───────┬───────────┘   │
                       │          │                 │
                       │  ┌───────▼───────────┐   │
                       │  │ fail2ban           │   │
                       │  │ monitors logs      │   │
                       │  │ → ban attackers    │   │
                       │  └───────────────────┘   │
                       └──────────────────────────┘
```

---

## 6. Attack Mitigation Matrix

| Attack Vector | Layer | Mitigation | Mechanism |
|--------------|-------|------------|-----------|
| **Brute Force** | Nginx | Rate limit | 5 req/s on login, burst=3 |
| **Brute Force** | Host | fail2ban | 5 failures → 10min ban |
| **DDoS** | Nginx | Rate limit + conn limit | 30 req/s api, 10 conn/IP |
| **SSL Strip** | Nginx | HSTS | 2-year max-age, preload |
| **Clickjacking** | Nginx | X-Frame-Options | SAMEORIGIN |
| **MIME Sniff** | Nginx | X-Content-Type-Options | nosniff |
| **XSS (legacy)** | Nginx | X-XSS-Protection | 0 (disable broken filter) |
| **Referrer Leak** | Nginx | Referrer-Policy | strict-origin-when-cross-origin |
| **Feature Abuse** | Nginx | Permissions-Policy | camera/mic/geo blocked |
| **WordPress Scanner** | Nginx | Bot blocking | 404 for wp-* paths |
| **Source Code Leak** | Nginx | Bot blocking | 404 for .git, .env, composer.json |
| **Path Traversal** | Nginx | Bot blocking | 404 for ../, hidden files |
| **Web Shell Upload** | Container | Read-only volumes | Attacker can't write to public/ |
| **Container Escape** | Container | Internal network | No host network mode |
| **Network Probe** | Host | UFW | Only 80/443 open |
| **Log Flood** | Nginx | Buffered logging | 64k buffer, 5s flush |
| **Log Full Disk** | Host | logrotate | Daily rotation, 30d retention |
| **Service Down** | Host | systemd | Auto-start on boot |
| **TLS Downgrade** | Nginx | ssl_protocols | Only TLSv1.2 + TLSv1.3 |
| **Weak Cipher** | Nginx | ssl_ciphers | HIGH:!aNULL:!MD5 |
| **Session Hijack** | Nginx | ssl_session_tickets | off (PFS preserved) |
| **MITM** | Nginx | HSTS + TLS | HTTPS forced + strong crypto |
| **Fingerprinting** | Nginx | server_tokens off | Nginx version hidden |

---

## 7. Configuration Files Reference

| File | Contents | Layer |
|------|----------|-------|
| `docker/nginx/default.conf` | Rate limits, security headers include, SSL, HTTP/2, buffers, timeouts, bot blocking, nginx_status, HTTP→HTTPS, per-location configs | Nginx |
| `docker/nginx/security-headers.conf` | 6 security headers | Nginx |
| `docker/nginx/gzip.conf` | Gzip compression | Nginx |
| `docker/docker-compose.yml` | Volumes (ro/rw), tmpfs, depends_on, healthcheck, networks, restart policy | Container |
| `docker/deploy/harden.sh` | UFW rules, fail2ban install + jails, systemd service, logrotate | Host |
| `/etc/systemd/system/accesspilot.service` | systemd unit file | Host |
| `/etc/fail2ban/jail.local` | 3 nginx jails configuration | Host |
| `/etc/logrotate.d/accesspilot-nginx` | Nginx log rotation | Host |

---

## 8. Verification Commands

```bash
# === Nginx Level ===

# Check rate limit zones
docker exec accesspilot_web nginx -T 2>&1 | grep -E "limit_req_zone|limit_conn_zone"

# Check security headers
curl -I https://localhost/ -k 2>&1 | grep -E "strict-transport|frame-options|content-type-options|xss|referrer|permissions"

# Test bot blocking
curl -I https://localhost/wp-admin/ -k 2>&1 | head -1   # 404
curl -I https://localhost/.env -k 2>&1 | head -1         # 404
curl -I https://localhost/.git/config -k 2>&1 | head -1  # 404

# Test SSL/TLS
docker exec accesspilot_web nginx -T 2>&1 | grep -E "ssl_protocols|ssl_ciphers|ssl_session"
curl -v --tls-max 1.0 https://localhost/ -k 2>&1 | grep "handshake"  # Should fail (TLSv1.0 disabled)

# Test rate limiting (login)
for i in $(seq 1 10); do curl -s -o /dev/null -w "%{http_code} " https://localhost/login.php -k; done
# Expected: 200 200 200 200 200 200 200 200 429 429
# (5 req/s + 3 burst = 8 allowed, then 429)

# Test server_tokens
curl -I https://localhost/ -k 2>&1 | grep -i server
# Expected: server: nginx (no version)

# Check nginx_status access
curl -I http://localhost/nginx_status -k 2>&1 | head -1  # Should work (local)
curl -I https://localhost/nginx_status -k 2>&1 | head -1 # Should 404 (HTTPS no match)

# === Container Level ===

# Check read-only mounts
docker inspect accesspilot_web --format '{{range .Mounts}}{{.Destination}} {{.Mode}}{{"\n"}}{{end}}'

# Check health status
docker inspect --format='{{json .State.Health.Status}}' accesspilot_web

# Check network
docker inspect accesspilot_web --format '{{json .NetworkSettings.Networks.accesspilot_net.IPAddress}}'

# === Host Level ===

# UFW status
ufw status verbose

# fail2ban status
fail2ban-client status
fail2ban-client status nginx-http-auth
fail2ban-client status nginx-botsearch
fail2ban-client status nginx-brute-force

# systemd service
systemctl status accesspilot.service

# logrotate test
logrotate -d /etc/logrotate.d/accesspilot-nginx
```

---

## 9. Troubleshooting

### Rate limiting too aggressive
```bash
# Check if requests are being rate limited
tail -f /data/logs/nginx/access.log | grep "429"

# Adjust rate (temporarily)
# Edit default.conf: change "rate=5r/s" to "rate=10r/s"
docker compose -f docker/docker-compose.yml up -d --no-deps --force-recreate nginx
```

### fail2ban false positives
```bash
# Check banned IPs
fail2ban-client status nginx-brute-force

# Unban an IP
fail2ban-client set nginx-brute-force unbanip 192.168.1.100

# Check log for matches
tail -f /var/log/fail2ban.log
```

### SSL certificate expired
```bash
# Check expiry
openssl x509 -in /data/secure/ssl/accesspilot.crt -noout -dates

# Replace cert and reload
cp new-cert.crt /data/secure/ssl/accesspilot.crt
cp new-key.key /data/secure/ssl/accesspilot.key
docker exec accesspilot_web nginx -s reload
```

### Container not starting
```bash
# Check logs
docker logs accesspilot_web

# Check config
docker run --rm nginx:1.25-alpine nginx -t

# Check port conflict
ss -tlnp | grep -E "(:80|:443)"
```
