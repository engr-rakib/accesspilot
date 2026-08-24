# Port Mapping & HTTPS Guide — AccessPilot

> **How the application is accessed, how HTTPS works, and how to change ports.**

---

## 1. Architecture Overview

```
                    ┌──────────────────────────────────────────┐
                    │           Browser                         │
                    │   https://192.168.1.172                   │
                    └──────────────┬───────────────────────────┘
                                  │
                    ┌─────────────▼─────────────────────────────┐
                    │    Host Nginx (port 80 + 443)             │
                    │    /etc/nginx/sites-available/accesspilot │
                    │                                          │
                    │    Port 80 → 301 redirect → Port 443      │
                    │    Port 443 → proxy_pass → 127.0.0.1:8080 │
                    └─────────────┬─────────────────────────────┘
                                  │
                    ┌─────────────▼─────────────────────────────┐
                    │    Docker Nginx (container port 80)       │
                    │    Host mapping: 127.0.0.1:8080:80        │
                    │    Serves static files, passes .php → FPM │
                    └─────────────┬─────────────────────────────┘
                                  │ FastCGI :9000
                    ┌─────────────▼─────────────────────────────┐
                    │    PHP-FPM (container)                    │
                    │    Executes app logic, LDAP queries       │
                    └───────────────────────────────────────────┘
```

### Port Flow Table

| From | To | Protocol | Purpose | Where Defined |
|------|----|----------|---------|---------------|
| Browser | Host Nginx :443 | HTTPS | User-facing access | `https://192.168.1.172` |
| Browser | Host Nginx :80 | HTTP | Auto-redirects to HTTPS | `http://192.168.1.172` |
| Host Nginx | Docker Nginx :8080 | HTTP (internal) | Reverse proxy | `/etc/nginx/sites-available/accesspilot` |
| Docker Nginx | PHP-FPM :9000 | FastCGI | PHP execution | `docker/nginx/default.conf` |
| PHP-FPM | AD DC :389/636 | LDAP/LDAPS | Directory operations | `domains.json` |
| SSH client | Host :22 | SSH | Admin management | `ufw allow 22` |

---

## 2. HTTPS Implementation

### 2.1 Current Setup (Self-Signed Certificate)

A self-signed SSL certificate was generated for immediate HTTPS access:

```
Certificate:  /etc/ssl/certs/accesspilot-selfsigned.crt
Private Key:  /etc/ssl/private/accesspilot-selfsigned.key
Valid For:    10 years
Subject:      CN=192.168.1.172
```

**Browser warning**: Self-signed certs show "Not Secure" in the browser. This is safe for internal/LAN use. Click "Advanced" → "Proceed" to access.

### 2.2 SSL Configuration (Host Nginx)

From `/etc/nginx/sites-available/accesspilot`:

```nginx
server {
    listen 443 ssl http2;
    server_name 192.168.1.172 accesspilot.local;

    ssl_certificate     /etc/ssl/certs/accesspilot-selfsigned.crt;
    ssl_certificate_key /etc/ssl/private/accesspilot-selfsigned.key;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Security headers
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
}
```

### 2.3 HTTPS Redirect (HTTP → HTTPS)

```nginx
server {
    listen 80;
    server_name 192.168.1.172 accesspilot.local;
    return 301 https://$host$request_uri;
}
```

### 2.4 How to Get a Proper SSL Certificate (Let's Encrypt)

If the server has a public domain name:

```bash
# Install certbot
apt install -y certbot python3-certbot-nginx

# Get certificate (auto-configures nginx)
certbot --nginx -d accesspilot.yourcompany.com

# Auto-renewal (certbot adds systemd timer automatically)
certbot renew --dry-run
```

If internal network only, keep the self-signed cert. To trust it, add it to your device:

- **Windows**: Double-click `.crt` → Install Certificate → Trusted Root Certification Authorities
- **Linux**: `cp cert.pem /usr/local/share/ca-certificates/ && update-ca-certificates`
- **macOS**: Double-click `.crt` → Add to Keychain → Trust → Always Trust

---

## 3. Port Mapping Details

### 3.1 Docker Port Mapping

Defined in `docker/docker-compose.yml`:

```yaml
services:
  nginx:
    ports:
      - "127.0.0.1:8080:80"
```

| Part | Meaning |
|------|---------|
| `127.0.0.1` | **Host IP** — bind to loopback only (not accessible from outside) |
| `8080` | **Host port** — what the host sees |
| `80` | **Container port** — what nginx inside the container listens on |

### 3.2 Host Nginx Port Mapping

Defined in `/etc/nginx/sites-available/accesspilot`:

| Host Port | Destination | Purpose |
|-----------|-------------|---------|
| 80 | 301 → HTTPS | Force encryption |
| 443 | `proxy_pass http://127.0.0.1:8080` | Reverse proxy to Docker |

### 3.3 Firewall Port Mapping

| Port | UFW Rule | iptables DOCKER-USER | Purpose |
|------|----------|---------------------|---------|
| 22 | ALLOW from 192.168.0.0/16 | — | SSH management |
| 80 | ALLOW Anywhere | — | HTTP (redirects to HTTPS) |
| 443 | ALLOW Anywhere | — | HTTPS (main access) |
| 8080 | DENY Anywhere | DROP if not 127.0.0.1 | Block direct Docker access |

### 3.4 Access Paths Summary

| URL | Behavior | Security |
|-----|----------|----------|
| `https://192.168.1.172` | ✅ Works — main access point | HTTPS with cert |
| `http://192.168.1.172` | ✅ Redirects to HTTPS | 301 redirect |
| `http://192.168.1.172:8080` | ❌ Blocked from outside | UFW deny + iptables drop |
| `http://127.0.0.1:8080` | ✅ Works from localhost | Internal only |

---

## 4. Changing Ports

### 4.1 Change the Web Access Port (e.g., 8080 → 8443)

If you want users to access via `https://192.168.1.172:8443`:

**Step 1**: Update host Nginx — change `listen 443 ssl http2` to `listen 8443 ssl http2`:

```bash
sed -i 's/listen 443 ssl http2;/listen 8443 ssl http2;/' /etc/nginx/sites-available/accesspilot
# Also update the HTTP server block redirect port if needed
nginx -t && systemctl reload nginx
```

**Step 2**: Update UFW:

```bash
ufw delete allow 443/tcp
ufw allow 8443/tcp comment 'HTTPS (custom port)'
```

### 4.2 Change the Docker Internal Port (e.g., 8080 → 8081)

If you want Docker Nginx on port 8081 instead of 8080:

**Step 1**: Update Docker compose:

```bash
sed -i 's/"127.0.0.1:8080:80"/"127.0.0.1:8081:80"/' /opt/accesspilot/docker/docker-compose.yml
```

**Step 2**: Update host Nginx proxy target:

```bash
sed -i 's/proxy_pass http:\/\/127.0.0.1:8080/proxy_pass http:\/\/127.0.0.1:8081/' /etc/nginx/sites-available/accesspilot
nginx -t && systemctl reload nginx
```

**Step 3**: Update UFW + iptables:

```bash
ufw delete deny 8080
ufw deny 8081/tcp comment 'Block Docker direct'
iptables -D DOCKER-USER -p tcp --dport 8080 ! -s 127.0.0.1 -j DROP
iptables -I DOCKER-USER -p tcp --dport 8081 ! -s 127.0.0.1 -j DROP
netfilter-persistent save
```

**Step 4**: Restart Docker:

```bash
docker compose -f /opt/accesspilot/docker/docker-compose.yml down
docker compose -f /opt/accesspilot/docker/docker-compose.yml up -d
```

### 4.3 Remove Host Nginx (Use Docker Directly)

If you want to access Docker Nginx directly without host reverse proxy:

```bash
# 1. Change Docker port to 80 (not 127.0.0.1 binding)
sed -i 's/"127.0.0.1:8080:80"/"80:80"/' /opt/accesspilot/docker/docker-compose.yml

# 2. Update UFW
ufw delete deny 8080
ufw allow 80/tcp
ufw delete allow 443/tcp

# 3. Remove iptables rule
iptables -D DOCKER-USER -p tcp --dport 8080 ! -s 127.0.0.1 -j DROP
netfilter-persistent save

# 4. Disable host Nginx
rm /etc/nginx/sites-enabled/accesspilot
systemctl stop nginx
systemctl disable nginx

# 5. Restart Docker
docker compose -f /opt/accesspilot/docker/docker-compose.yml down
docker compose -f /opt/accesspilot/docker/docker-compose.yml up -d
```

**⚠️ Warning**: This removes HTTPS. The app will be accessible via `http://192.168.1.172` without encryption.

### 4.4 Add Multiple Ports (e.g., Both 8080 and 8443)

For debugging or migration:

```yaml
# docker-compose.yml
ports:
  - "127.0.0.1:8080:80"
  - "127.0.0.1:8443:80"   # Same service on two host ports
```

Then UFW-allow the additional port if needed.

---

## 5. SSL Certificate Management

### 5.1 Files

| File | Purpose | Renewal |
|------|---------|---------|
| `/etc/ssl/certs/accesspilot-selfsigned.crt` | Self-signed cert (10 year) | Every 10 years |
| `/etc/ssl/private/accesspilot-selfsigned.key` | Private key | Same |
| `/etc/letsencrypt/live/...` | Let's Encrypt certs | Auto (90 days) |

### 5.2 Generate a New Self-Signed Cert

```bash
openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
  -keyout /etc/ssl/private/accesspilot-selfsigned.key \
  -out /etc/ssl/certs/accesspilot-selfsigned.crt \
  -subj "/CN=192.168.1.172/O=AccessPilot/OU=IT"
nginx -t && systemctl reload nginx
```

### 5.3 Switch from Self-Signed to Let's Encrypt

```bash
certbot --nginx -d accesspilot.yourcompany.com

# certbot auto-updates /etc/nginx/sites-available/accesspilot
# No manual nginx changes needed
nginx -t && systemctl reload nginx
```

---

## 6. Verify HTTPS Configuration

```bash
# Check SSL cert details
openssl x509 -in /etc/ssl/certs/accesspilot-selfsigned.crt -text -noout | grep -E "Subject:|Not Before|Not After"

# Check nginx SSL config
nginx -T 2>/dev/null | grep -A 10 "listen.*443"

# Check ports listening
ss -tlnp | grep -E "nginx|:80 |:443 |:8080"

# Verify HTTPS works
curl -k -I https://192.168.1.172/

# Verify HTTP redirect
curl -I http://192.168.1.172/ 2>&1 | grep -i location

# Verify 8080 blocked from external
curl -s --max-time 3 http://192.168.1.172:8080/ 2>&1 || echo "✅ Blocked"
```

---

## 7. Key Files Reference

| File | Role |
|------|------|
| `/etc/nginx/sites-available/accesspilot` | Host reverse proxy config (HTTP + HTTPS) |
| `/etc/nginx/nginx.conf` | Main nginx config (rate limiting zone defined here) |
| `/opt/accesspilot/docker/docker-compose.yml` | Docker port mapping |
| `/opt/accesspilot/docker/nginx/default.conf` | Container nginx config |
| `/etc/ssl/certs/accesspilot-selfsigned.crt` | SSL certificate |
| `/etc/ssl/private/accesspilot-selfsigned.key` | SSL private key |
| `/etc/ufw/user.rules` | UFW persistent rules |
| `/etc/iptables/rules.v4` | iptables persistent rules |
