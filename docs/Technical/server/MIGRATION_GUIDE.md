# AccessPilot — Full Server Migration Guide

> **Migrate the entire application (images, data, config) from one host to another.**  
> Covers Docker image export/import, persistent data transfer, host config replication, and post-migration verification.

---

## 1. Migration Overview

### What Migrates

| Component | Method | Size Estimate |
|-----------|--------|---------------|
| PHP Docker image (custom) | `docker save` / `docker load` | ~400-600 MB |
| nginx Docker image | Pulled from registry (no export needed) | — |
| Persistent data (`/data/secure`) | `tar` / `rsync` | ~1-50 MB |
| Persistent data (`/data/logs`) | `tar` / `rsync` | ~10 MB - 1 GB |
| App_Data (`/opt/accesspilot/App_Data`) | `tar` / `rsync` | ~10 KB |
| Codebase (`/opt/accesspilot/`) | `git clone` or `rsync` | ~20-50 MB |
| Docker compose + configs | Included in codebase | — |
| Host Nginx configs | Manual replication | 3 files |
| Firewall rules | Manual replication | ~10 commands |
| SSL certificates | File copy | 2 files |

### What Stays the Same

- AD domain controllers (unchanged — DCs are external)
- DNS configuration (update if server IP changes)
- Client browsers (update URL if hostname/IP changes)

---

## 2. Migration Strategy

### Option A: Air-Gapped / No Registry (Recommended)

Full export on old server → transfer tar files → import on new server.  
Works when both servers can share files via SCP/rsync or USB drive.

```
OLD SERVER                            NEW SERVER
┌───────────────┐                   ┌───────────────┐
│ docker save   │─── php.tar ──────▶│ docker load   │
│ tar /data/*   │─── data.tar ────▶│ tar -xf       │
│ tar codebase  │─── code.tar ───▶│ extract       │
└───────────────┘                   └───────────────┘
```

### Option B: Network Transfer (Fast Network)

Use `rsync` over SSH when both servers are on the same LAN.

```
rsync -avz /data/ user@new-server:/data/
rsync -avz /opt/accesspilot/ user@new-server:/opt/accesspilot/
```

### Option C: Registry-Based (Docker Hub / Private Registry)

Push custom PHP image to a registry, pull on new server.

```
docker tag accesspilot_php:latest registry.example.com/accesspilot_php:latest
docker push registry.example.com/accesspilot_php:latest
```

---

## 3. Step-by-Step: Full Migration (Air-Gapped)

### Phase 1: On the OLD Server — Prepare Backup

#### 1a. Export Docker Images

```bash
# Set timestamp for backup naming
TS=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR=/opt/accesspilot/migration_${TS}
mkdir -p "$BACKUP_DIR"

# Export the custom PHP image
docker save accesspilot_php:latest -o "$BACKUP_DIR/accesspilot_php.tar"
# Or if using the docker-compose build image name:
docker save $(docker images --format '{{.Repository}}:{{.Tag}}' | grep accesspilot) -o "$BACKUP_DIR/accesspilot_php.tar"

# Also save the alpine nginx image (or pull it fresh on new server)
# nginx:1.25-alpine is from Docker Hub — can be pulled fresh
# But save it too for air-gapped environments:
docker pull nginx:1.25-alpine
docker save nginx:1.25-alpine -o "$BACKUP_DIR/nginx.tar"

echo "Images exported to $BACKUP_DIR"
ls -lh "$BACKUP_DIR/"
```

#### 1b. Export Persistent Data

```bash
# Backup secure vault and logs
cd "$BACKUP_DIR"
tar -czf data_secure.tar.gz -C /data/secure .
tar -czf data_logs.tar.gz -C /data/logs .
tar -czf app_data.tar.gz -C /opt/accesspilot/App_Data .

echo "Data exported:"
ls -lh *.tar.gz
```

#### 1c. Backup Host Configs (for reference)

```bash
# Nginx configs
cp /etc/nginx/sites-available/accesspilot "$BACKUP_DIR/host_nginx.conf"
cp /etc/nginx/nginx.conf "$BACKUP_DIR/host_nginx_main.conf"

# SSL certificates
cp /etc/ssl/certs/accesspilot-selfsigned.crt "$BACKUP_DIR/"
cp /etc/ssl/private/accesspilot-selfsigned.key "$BACKUP_DIR/"

# iptables rules
iptables-save > "$BACKUP_DIR/iptables-rules.v4"

# UFW rules
cp /etc/ufw/user.rules "$BACKUP_DIR/ufw-user.rules" 2>/dev/null || true

# Systemd service
cp /etc/systemd/system/accesspilot.service "$BACKUP_DIR/" 2>/dev/null || true

# Environment info
echo "OLD_SERVER_IP=$(hostname -I | awk '{print $1}')" > "$BACKUP_DIR/env.txt"
echo "OLD_HOSTNAME=$(hostname)" >> "$BACKUP_DIR/env.txt"
cat /etc/os-release | grep -E "^NAME|^VERSION" >> "$BACKUP_DIR/env.txt"
docker exec accesspilot_php php -r 'echo "PHP_VERSION=" . PHP_VERSION . "\n";' >> "$BACKUP_DIR/env.txt"

echo "Host configs exported to $BACKUP_DIR"
```

#### 1d. Show Migration Summary

```bash
echo ""
echo "============================================"
echo "  MIGRATION PACKAGE READY"
echo "============================================"
echo "  Location: $BACKUP_DIR"
echo ""
echo "  Files to transfer:"
du -sh "$BACKUP_DIR"/*
echo ""
echo "  Transfer command:"
echo "  scp -r $BACKUP_DIR user@NEW_SERVER_IP:/opt/accesspilot/migration/"
```

### Phase 2: Transfer to NEW Server

```bash
# On the OLD server, run:
scp -r /opt/accesspilot/migration_20260623_120000 user@192.168.1.200:/opt/accesspilot/migration/

# Or using rsync over LAN (faster for large files):
rsync -avzP /opt/accesspilot/migration_20260623_120000/ user@192.168.1.200:/opt/accesspilot/migration/
```

### Phase 3: On the NEW Server — Restore

#### 3a. Install Prerequisites

```bash
# Ubuntu 22.04+ recommended
sudo apt update && sudo apt upgrade -y

# Install Docker
sudo apt install -y ca-certificates curl gnupg lsb-release
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list
sudo apt update && sudo apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin

# Install host Nginx, iptables-persistent, SSL tools
sudo apt install -y nginx ssl-cert iptables-persistent certbot python3-certbot-nginx
```

#### 3b. Restore Docker Images

```bash
cd /opt/accesspilot/migration

# Load the PHP image
docker load -i accesspilot_php.tar

# Load nginx image (or pull from registry)
docker load -i nginx.tar
# OR: docker pull nginx:1.25-alpine

# Verify images
docker images | grep -E "accesspilot|nginx"
```

#### 3c. Restore Persistent Data

```bash
# Create storage directories
sudo mkdir -p /data/secure /data/logs
sudo chown 33:33 /data/secure /data/logs
sudo chmod 770 /data/secure /data/logs

# Restore vault data
sudo tar -xzf data_secure.tar.gz -C /data/secure/
sudo tar -xzf data_logs.tar.gz -C /data/logs/

# Restore App_Data
sudo mkdir -p /opt/accesspilot/App_Data
sudo tar -xzf app_data.tar.gz -C /opt/accesspilot/App_Data/

# Fix ownership
sudo chown -R 33:33 /data/secure /data/logs /opt/accesspilot/App_Data
```

#### 3d. Deploy Codebase

```bash
# Option A: The codebase might be in the migration directory
# If you copied it from the old server as part of the code tar:
# (codebase backup is not in the automated tar above — copy separately)

# Option B: rsync the codebase
rsync -avz old-server:/opt/accesspilot/ /opt/accesspilot/ --exclude='.git' --exclude='App_Data/*' --exclude='node_modules'

# Option C: git clone from repository
cd /opt/accesspilot
git clone <repository-url> .

# Verify key files exist
ls -la docker/docker-compose.yml docker/Dockerfile public/index.php
```

#### 3e. Configure Host Nginx (Reverse Proxy)

```bash
# Copy SSL certificates
sudo cp migration/accesspilot-selfsigned.crt /etc/ssl/certs/
sudo cp migration/accesspilot-selfsigned.key /etc/ssl/private/

# Copy host nginx config
sudo cp migration/host_nginx.conf /etc/nginx/sites-available/accesspilot

# Update server_name in config if IP has changed
sudo sed -i 's/server_name .*/server_name NEW_SERVER_IP;/' /etc/nginx/sites-available/accesspilot

# Enable site
sudo rm -f /etc/nginx/sites-enabled/default
sudo ln -sf /etc/nginx/sites-available/accesspilot /etc/nginx/sites-enabled/

# Test and reload
sudo nginx -t && sudo systemctl reload nginx
```

#### 3f. Configure Firewall

```bash
# UFW
sudo ufw --force reset
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow from 192.168.0.0/16 to any port 22 proto tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw deny 8080/tcp
sudo ufw --force enable

# iptables DOCKER-USER (block direct 8080)
sudo iptables -I DOCKER-USER -p tcp --dport 8080 ! -s 127.0.0.1 -j DROP
sudo netfilter-persistent save
```

#### 3g. Fix docker-compose.yml for New Env

```bash
cd /opt/accesspilot

# If the new server IP is different, update the server_name in host nginx
# (already done in step 3e)

# Verify docker-compose.yml has loopback binding and security settings
grep "127.0.0.1" docker/docker-compose.yml || echo "⚠️ Update ports to 127.0.0.1:8080:80"

# If the new server has different AD DNS, add extra_hosts
# docker-compose.yml php section:
# extra_hosts:
#   - "dcpri4.wgbd.com:192.168.20.7"
#   - "DC-AD1.WHILDC.COM:192.168.119.169"
```

#### 3h. Start Containers

```bash
cd /opt/accesspilot/docker

# Build any remaining images (if code changed)
# docker compose build

# Start containers
docker compose up -d

# Verify
docker compose ps
curl -k https://localhost
```

#### 3i. Create Systemd Service

```bash
sudo tee /etc/systemd/system/accesspilot.service > /dev/null << 'EOF'
[Unit]
Description=AccessPilot Docker Stack
Requires=docker.service
After=docker.service network-online.target

[Service]
Type=oneshot
RemainAfterExit=yes
ExecStart=/usr/bin/docker compose -f /opt/accesspilot/docker/docker-compose.yml up -d
ExecStop=/usr/bin/docker compose -f /opt/accesspilot/docker/docker-compose.yml down
StandardOutput=journal

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable accesspilot
sudo systemctl start accesspilot
```

---

## 4. One-Shot Migration Script

Run this on the **old server** to create a complete migration package:

```bash
#!/bin/bash
set -e
TS=$(date +%Y%m%d_%H%M%S)
DIR=/opt/accesspilot/migration_${TS}
mkdir -p "$DIR"

echo "=== Exporting Docker images ==="
docker save $(docker images --format '{{.Repository}}:{{.Tag}}' | grep -E "accesspilot|nginx") -o "$DIR/images.tar" 2>/dev/null || {
    docker save accesspilot_php:latest -o "$DIR/accesspilot_php.tar"
    docker pull nginx:1.25-alpine && docker save nginx:1.25-alpine -o "$DIR/nginx.tar"
}

echo "=== Exporting persistent data ==="
tar -czf "$DIR/data_secure.tar.gz" -C /data/secure .
tar -czf "$DIR/data_logs.tar.gz" -C /data/logs .
tar -czf "$DIR/app_data.tar.gz" -C /opt/accesspilot/App_Data .

echo "=== Exporting host configs ==="
cp /etc/nginx/sites-available/accesspilot "$DIR/" 2>/dev/null || true
cp /etc/nginx/nginx.conf "$DIR/nginx.conf" 2>/dev/null || true
cp /etc/ssl/certs/accesspilot-selfsigned.crt "$DIR/" 2>/dev/null || true
cp /etc/ssl/private/accesspilot-selfsigned.key "$DIR/" 2>/dev/null || true
iptables-save > "$DIR/iptables-rules.v4" 2>/dev/null || true
cp /etc/systemd/system/accesspilot.service "$DIR/" 2>/dev/null || true
hostname -I | awk '{print "OLD_IP="$1}' > "$DIR/env.txt"
echo "OLD_HOSTNAME=$(hostname)" >> "$DIR/env.txt"

echo ""
echo "============================================"
echo "  MIGRATION PACKAGE: $DIR"
echo "============================================"
du -sh "$DIR"/*
echo ""
echo "  Transfer to new server:"
echo "  scp -r $DIR user@NEW_SERVER_IP:/tmp/migration"
```

---

## 5. Post-Migration Verification

```bash
# On the NEW server, run:

echo "=== 1. CONTAINERS RUNNING ==="
docker ps --filter "name=accesspilot" --format "table {{.Names}}\t{{.Status}}"

echo "=== 2. HTTPS ACCESS ==="
curl -k -s -o /dev/null -w "HTTPS: HTTP %{http_code}\n" https://localhost/

echo "=== 3. HTTP REDIRECT ==="
curl -s -o /dev/null -w "HTTP → %{redirect_url}\n" http://localhost/

echo "=== 4. PORT 8080 BLOCKED ==="
curl -s --max-time 3 http://localhost:8080/ 2>&1 || echo "✅ 8080 blocked from external"

echo "=== 5. PHP ERROR LOGGING ==="
docker exec accesspilot_php php -r 'echo "error_log: " . ini_get("error_log") . "\n";'

echo "=== 6. LDAP CONNECTIVITY ==="
docker exec accesspilot_php php -r '
$c = json_decode(file_get_contents("/data/secure/ldap/config.json"), true);
$l = ldap_connect($c["host"], $c["port"]);
ldap_set_option($l, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($l, LDAP_OPT_NETWORK_TIMEOUT, 5);
echo @ldap_bind($l,"","") ? "✅ LDAP OK\n" : "❌ LDAP FAIL: ".ldap_error($l)."\n";
'

echo "=== 7. SYSTEMD SERVICE ==="
systemctl is-active accesspilot

echo "=== 8. UFW STATUS ==="
ufw status verbose | head -8
```

---

## 6. What NOT to Migrate

These items should NOT be copied from the old server — they are regenerated automatically:

| Item | Why |
|------|-----|
| `.env` file | Docker env vars set in compose |
| `config/app.php` (vault) | Restored via `/data/secure/config/app_overrides.php` |
| `config/shared_config.json` | Regenerated by `sync_shared_config()` |
| `App_Data/setup_complete.lock` | OK to copy (prevents re-initialization) |
| Session files in `App_Data/` | Can be deleted — sessions are ephemeral |
| `docker/php-error-logging.ini` | Included in codebase — copy via rsync |
| OPcache files | Ephemeral — regenerated on first request |
| Docker build cache | Not needed — image export includes compiled state |

---

## 7. Rollback Plan

If migration fails, revert to old server:

```bash
# 1. Point DNS back to old server IP
# 2. On new server:
sudo systemctl stop accesspilot
docker compose -f /opt/accesspilot/docker/docker-compose.yml down

# 3. Verify old server still running
curl -k https://OLD_SERVER_IP/
```

No data loss on old server — data was only **copied**, not moved.

---

## 8. Network Changes (if IP Changes)

If the new server has a different IP:

### Update DNS / Host File

```bash
# If using internal DNS, update A record
# If using /etc/hosts on client machines:
# 192.168.1.200  accesspilot.local

# Regenerate self-signed cert for new IP
openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
  -keyout /etc/ssl/private/accesspilot-selfsigned.key \
  -out /etc/ssl/certs/accesspilot-selfsigned.crt \
  -subj "/CN=192.168.1.200"
```

### Update Host Nginx Server Name

```bash
sudo sed -i 's/server_name .*/server_name 192.168.1.200;/' /etc/nginx/sites-available/accesspilot
sudo nginx -t && sudo systemctl reload nginx
```

### Verify AD Domain Resolution

```bash
# On new server, ensure AD DCs are resolvable
docker exec accesspilot_php getent hosts dcpri4.wgbd.com
docker exec accesspilot_php getent hosts DC-AD1.WHILDC.COM

# If not resolving, add to docker-compose.yml extra_hosts
```

---

## 9. Migration Checklist

### Pre-Migration
- [ ] Note old server IP, hostname, OS version
- [ ] Note current AD domain controllers and their IPs
- [ ] Verify all AD domains have `use_tls: true`
- [ ] Download codebase to new server or prepare git clone
- [ ] Ensure new server meets minimum requirements (2 CPU, 4 GB RAM)
- [ ] Install Docker + Nginx on new server

### Migration
- [ ] Export Docker images (`docker save`)
- [ ] Backup persistent data (`/data/secure`, `/data/logs`, `App_Data`)
- [ ] Backup host configs (nginx, SSL, iptables, systemd)
- [ ] Transfer all files to new server
- [ ] Load Docker images on new server
- [ ] Restore persistent data
- [ ] Deploy codebase
- [ ] Configure host Nginx with SSL
- [ ] Configure UFW + iptables
- [ ] Create systemd service
- [ ] Start containers

### Post-Migration
- [ ] Verify HTTPS access
- [ ] Verify HTTP → HTTPS redirect
- [ ] Verify 8080 blocked
- [ ] Verify LDAP connectivity to all AD domains
- [ ] Verify PHP error logging
- [ ] Verify auto-start (systemd)
- [ ] Test login and a few operations
- [ ] Point DNS to new server IP
- [ ] Monitor logs for 24 hours
