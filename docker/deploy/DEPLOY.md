# AccessPilot — Deployment Guide

> Fresh Linux server → fully running application (Ubuntu 22.04+ / Debian 12)

---

## 1. Prerequisites

| Package | Purpose |
|---------|---------|
| Docker | Container runtime |
| chrony | NTP sync |
| ufw | Firewall (installed by harden.sh) |
| fail2ban | Intrusion prevention (installed by harden.sh) |

### Ports

| Port | Direction | Purpose |
|------|-----------|---------|
| 22/tcp | LAN only | SSH |
| 80/tcp | Any | HTTP → HTTPS redirect |
| 443/tcp | Any | HTTPS |
| 389/tcp | Outbound | LDAP → Domain Controllers |
| 80/tcp | Outbound | Exchange WinRM (optional) |
| 53/udp | Outbound | DNS |

### Time Sync

```bash
sudo timedatectl set-timezone Asia/Dhaka
sudo apt install -y chrony && sudo systemctl enable --now chrony
```

---

## 2. Deploy

### Step 1: Install Docker

```bash
curl -fsSL https://get.docker.com | sudo bash
```

### Step 2: Set Variables

```bash
# Set your project root — wherever you want the code
PROJECT_ROOT="/app/accesspilot"    # or /opt/accesspilot, /srv/portal, etc.
PARENT_DIR="$(dirname "$PROJECT_ROOT")"
PROJECT_NAME="$(basename "$PROJECT_ROOT")"
```

### Step 3: Create Directories

```bash
sudo mkdir -p "$PROJECT_ROOT" /data/secure /data/logs
sudo chown 33:33 /data/secure /data/logs
sudo chmod 770 /data/secure /data/logs
```

### Step 4: Transfer Code + Data

Old server e (project + data path jekhane thakuk):

```bash
# 1. Code tarball
cd /opt   # your old server project parent dir
tar -czf /tmp/accesspilot_code.tar.gz \
  --exclude='accesspilot/.git' --exclude='accesspilot/.opencode' \
  accesspilot

# 2. Data tarball (vault + logs — license, LDAP config, audit)
sudo tar -czf /tmp/accesspilot_data.tar.gz \
  /data/secure /data/logs

# 3. SCP to new server
scp /tmp/accesspilot_code.tar.gz root@NEW_SERVER_IP:/tmp/
scp /tmp/accesspilot_data.tar.gz root@NEW_SERVER_IP:/tmp/
```

### Step 5: Extract + Start

```bash
# Code
sudo tar -xzf /tmp/accesspilot_code.tar.gz -C "$PARENT_DIR"

# Data (vault + logs)
sudo tar -xzf /tmp/accesspilot_data.tar.gz -C /
sudo chown -R 33:33 /data/secure /data/logs

# Start
cd "$PROJECT_ROOT/docker"
sudo docker compose up -d       # Nginx + PHP containers start
```

> ⏱ First start: Nginx waits ~10s for SSL cert generation, then starts.

---

## 3. Post-Deploy Hardening

**Run this ONCE after `docker compose up -d`:**

```bash
sudo bash "$PROJECT_ROOT/docker/deploy/harden.sh"
```

This script sets up:

| Component | What It Does |
|-----------|-------------|
| **UFW** | Opens 80/tcp + 443/tcp + SSH(LAN), default deny incoming |
| **DOCKER-USER chain** | Clears blocking rules (direct port mapping works) |
| **fail2ban** | 3 jails: `nginx-http-auth`, `nginx-botsearch`, `nginx-brute-force` |
| **systemd service** | `/etc/systemd/system/accesspilot.service` — auto-start on boot |
| **logrotate** | `/etc/logrotate.d/accesspilot-nginx` — daily, 30 day retention |

### Verify Hardening

```bash
ufw status verbose          # Firewall rules
fail2ban-client status      # All jails
systemctl status accesspilot.service  # Auto-start
logrotate -d /etc/logrotate.d/accesspilot-nginx  # Log rotation
```

---

## 4. Nginx Feature Summary

After deploy, these are active:

| Feature | Config File | Benefit |
|---------|-------------|---------|
| Gzip compression | `docker/nginx/gzip.conf` | 77% bandwidth reduction |
| FastCGI cache (5s) | `docker/nginx/default.conf` | Monitoring API served from cache |
| X-Accel-Redirect avatars | `docker/nginx/default.conf` + `get_avatar.php` | No PHP readfile overhead |
| Rate limiting | `docker/nginx/default.conf` | Brute force + API abuse protection |
| Security headers | `docker/nginx/security-headers.conf` | HSTS, XFO, CSP-adjacent headers |
| Bot blocking | `docker/nginx/default.conf` | 20+ known attacker patterns blocked |
| SSL/TLS hardening | `docker/nginx/default.conf` | TLSv1.2 + 1.3, strong ciphers |
| HTTP/2 | `docker/nginx/default.conf` | Multiplexed connections |

---

## 5. Verify

```bash
docker compose ps
curl -k https://localhost/
curl -k https://localhost/health.php      # Health check
curl -I -k https://localhost/ | grep -i strict-transport  # HSTS header
```

Open `https://SERVER_IP/` in browser.

> HTTP → HTTPS auto redirect, self-signed SSL. No host nginx needed.

---

## 6. Maintenance

```bash
# Backup
sudo bash "$PROJECT_ROOT/docker/deploy/backup.sh"
sudo bash "$PROJECT_ROOT/docker/deploy/backup.sh" --data-only

# Update containers after config changes
cd "$PROJECT_ROOT/docker"
sudo docker compose up -d --no-deps --force-recreate nginx
sudo docker compose up -d --no-deps --force-recreate php

# View logs
docker logs accesspilot_web --tail 50
docker logs accesspilot_php --tail 50

# Clear nginx cache (after stale data issues)
docker exec accesspilot_web find /var/cache/nginx/fastcgi_cache/ -type f -delete
```

### Cron

```bash
0 2 * * * /app/accesspilot/docker/deploy/backup.sh --data-only
0 3 * * * docker exec accesspilot_web find /var/cache/nginx/fastcgi_cache/ -type f -delete 2>/dev/null  # Daily cache cleanup
# Replace /app/accesspilot with your actual PROJECT_ROOT
```

---

## 7. Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| Nginx `Restarting` | SSL cert not yet generated | Wait 10s |
| `ERR_CONNECTION_TIMED_OUT` | DOCKER-USER iptables blocking | `bash harden.sh` clears it |
| `404` on page | HRMS config missing | System Config → `api_paths` |
| PHP connection refused | PHP-FPM crashed | `docker compose restart php` |
| Port 80 conflict | Host nginx running | `systemctl stop nginx; docker compose up -d` |
| Cache not hitting | `_=timestamp` still breaking key | Already fixed in nginx config |
| Login slow / 429 | Rate limit hit | Check `docker logs accesspilot_web \| grep 429` |
| Avatar not loading | Vault path mismatch | Check `/data/secure/profile_img/` exists |
