# Server Hardening Guide — AccessPilot (Docker on Linux)

> **Live audit-based security guide.**  
> Every section reflects the actual state of this server (`rakiblab`, IP `192.168.1.172`, Ubuntu 20.04 LTS, Docker 28.1.1) and prescribes commands to close each gap.

---

## 1. Current State Audit Summary

| Area | Status | Risk Level |
|------|--------|-----------|
| **UFW firewall** | ❌ Inactive — ALL ports open to internet | **CRITICAL** |
| **Docker port 8080** | ⚠️ Bound to `0.0.0.0:8080` (all interfaces) | **HIGH** |
| **DOCKER-USER iptables** | ⚠️ Exists but only `RETURN` — no filtering | **HIGH** |
| **Host Nginx reverse proxy** | ❌ Not installed | **MEDIUM** |
| **HTTPS** | ❌ No SSL certificate | **HIGH** |
| **Container CapDrop** | ❌ Not set — default capabilities | **MEDIUM** |
| **Container ReadonlyRootfs** | ❌ Not set — writable filesystem | **MEDIUM** |
| **Container SecurityOpt** | ❌ Not set — no seccomp or no-new-privileges | **MEDIUM** |
| **PHP expose_php** | ❌ On — leaks PHP version in headers | **LOW** |
| **PHP disable_functions** | ❌ Empty — all functions available | **HIGH** |
| **PHP allow_url_fopen** | ❌ On — SSRF risk | **MEDIUM** |
| **PHP open_basedir** | ❌ Not set — any file accessible | **MEDIUM** |
| **PHP session cookie security** | ❌ httponly=Off, secure=Off, strict=Off | **MEDIUM** |
| **SSH PermitRootLogin** | ❌ Yes — root can SSH directly | **HIGH** |
| **SSH PasswordAuthentication** | ⚠️ Not explicitly denied | **MEDIUM** |
| **fail2ban** | ❌ Not installed | **MEDIUM** |
| **Docker daemon.json** | ❌ Not configured | **LOW** |
| **Systemd service** | ❌ No auto-start on boot | **MEDIUM** |
| **AD LDAP** | ✅ use_tls=true on all domains | **OK** |
| **AppArmor** | ✅ Active, docker-default profile applied | **OK** |
| **Password policy** | ❌ PASS_MAX_DAYS 99999 (never expires) | **HIGH** |

---

## 2. Network Architecture (Current)

```
                    INTERNET
                        │
                        ▼
    ┌───────────────────────────────────────────┐
    │         Host: rakiblab                    │
    │         IP: 192.168.1.172/24              │
    │         Gateway: 192.168.1.1              │
    │         DNS: systemd-resolved 127.0.0.53  │
    │         Search: wgbd.com                  │
    │                                           │
    │  LISTENING PORTS (ss -tlnp):              │
    │  ┌──────────┬──────┬────────────────────┐ │
    │  │ Interface│ Port │ Service            │ │
    │  ├──────────┼──────┼────────────────────┤ │
    │  │ 0.0.0.0  │ 8080 │ Docker Nginx       │ │
    │  │ 0.0.0.0  │ 22   │ SSH (sshd)         │ │
    │  │ 0.0.0.0  │ 9090 │ systemd (cockpit?) │ │
    │  │ 127.0.0.1│ 5900 │ QEMU (libvirt)     │ │
    │  │ 127.0.0.1│ 5901 │ QEMU (libvirt)     │ │
    │  │ 192.168. │ 53   │ dnsmasq (libvirt)  │ │
    │  │ 122.1/150│      │                    │ │
    │  └──────────┴──────┴────────────────────┘ │
    │                                           │
    │  ┌─── Docker Bridge: accesspilot_net ──┐  │
    │  │ 172.18.0.3  nginx (accesspilot_web) │  │
    │  │ 172.18.0.2  php-fpm (accesspilot_php)│  │
    │  └─────────────────────────────────────┘  │
    │                                           │
    │  ┌─── Libvirt Networks ────────────────┐  │
    │  │ virbr0: 192.168.122.0/24 (active)   │  │
    │  │ virbr1: 192.168.150.0/24 (down)     │  │
    │  └─────────────────────────────────────┘  │
    │                                           │
    │  AD Domain Controllers:                   │
    │  dcpri4.wgbd.com      → 192.168.20.7:389 │
    │  DC-AD1.WHILDC.COM     → 192.168.119.169:389 │
    └───────────────────────────────────────────┘
```

---

## 3. Immediate Fixes (Execute in Order)

### 3.1 Enable UFW Firewall

```bash
# Default deny all incoming, allow outbound
ufw default deny incoming
ufw default allow outgoing

# SSH — restrict to your management subnet
ufw allow from 192.168.0.0/16 to any port 22 proto tcp comment 'SSH from LAN'

# Allow HTTP/HTTPS from anywhere (for web app)
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'

# Explicitly DENY direct Docker port
ufw deny 8080/tcp comment 'Block direct Docker nginx access'

# Allow libvirt DNS/DHCP on internal networks
ufw allow in on virbr0 to any port 53 proto udp comment 'libvirt DNS'
ufw allow in on virbr0 to any port 67 proto udp comment 'libvirt DHCP'

# Enable
ufw --force enable
ufw status verbose
```

### 3.2 Block Direct Docker Port Access at iptables Level

UFW does not control Docker-published ports. Use iptables DOCKER-USER chain:

```bash
# Allow only loopback to 8080 (host nginx can reach it)
iptables -I DOCKER-USER -p tcp --dport 8080 ! -s 127.0.0.1 -j DROP
iptables -I DOCKER-USER -p tcp --dport 8080 ! -s 172.18.0.0/16 -j DROP

# Save rules
apt install -y iptables-persistent
netfilter-persistent save
```

### 3.3 Harden SSH

```bash
# /etc/ssh/sshd_config — change these lines:
sed -i 's/^PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config
sed -i 's/^#PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
sed -i 's/^#MaxAuthTries.*/MaxAuthTries 3/' /etc/ssh/sshd_config
sed -i 's/^#ClientAliveInterval.*/ClientAliveInterval 300/' /etc/ssh/sshd_config
sed -i 's/^#ClientAliveCountMax.*/ClientAliveCountMax 0/' /etc/ssh/sshd_config

# Add specific user allowlist
echo "AllowUsers $(whoami)" >> /etc/ssh/sshd_config

# Restart SSH
systemctl restart sshd
```

### 3.4 Fix Docker Container Security

Apply to `/opt/accesspilot/docker/docker-compose.yml`:

```yaml
services:
  nginx:
    image: nginx:1.25-alpine
    container_name: accesspilot_web
    ports:
      - "127.0.0.1:8080:80"           # ← Bind to loopback ONLY
    volumes:
      - ../public:/var/www/html/public:ro
      - ../resources:/var/www/html/resources:ro
      - ./nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    networks:
      - accesspilot_net
    cap_drop:
      - ALL
    cap_add:
      - NET_BIND_SERVICE
      - NET_RAW
    security_opt:
      - no-new-privileges:true
    read_only: true
    tmpfs:
      - /var/run
      - /var/cache/nginx
    restart: always

  php:
    build:
      context: ..
      dockerfile: docker/Dockerfile
    container_name: accesspilot_php
    environment:
      ACCESSPILOT_SECURE_BASE_PATH: /data/secure
      ACCESSPILOT_LOG_BASE_PATH: /data/logs
      AD_EXECUTION_MODE: remote
    volumes:
      - /data/secure:/data/secure
      - /data/logs:/data/logs
      - ../App_Data:/var/www/html/App_Data
      - ../app:/var/www/html/app:ro
      - ../bootstrap:/var/www/html/bootstrap:ro
      - ../config:/var/www/html/config
      - ../public:/var/www/html/public:ro
      - ../resources:/var/www/html/resources:ro
    networks:
      - accesspilot_net
    cap_drop:
      - ALL
    cap_add:
      - NET_BIND_SERVICE
      - NET_RAW
      - DAC_OVERRIDE
    security_opt:
      - no-new-privileges:true
    read_only: true
    tmpfs:
      - /tmp
      - /var/www/html/App_Data
    command: sh -c "chown www-data:www-data /data/secure /data/logs && chmod 666 /var/www/html/config/app.php && exec php-fpm"
    restart: always

networks:
  accesspilot_net:
    driver: bridge
    ipam:
      config:
        - subnet: 172.18.0.0/16
```

Then restart:

```bash
docker compose -f /opt/accesspilot/docker/docker-compose.yml down
docker compose -f /opt/accesspilot/docker/docker-compose.yml up -d
```

### 3.5 Harden PHP Inside Container

Create a custom `php-security.ini` in the Docker build:

```bash
# On host, create this file:
cat > /opt/accesspilot/docker/php-security.ini << 'EOF'
expose_php = Off
disable_functions = exec, system, passthru, shell_exec, proc_open, popen, pcntl_exec, phpinfo
allow_url_fopen = Off
allow_url_include = Off
open_basedir = /var/www/html:/data/secure:/data/logs:/tmp
session.use_strict_mode = 1
session.use_only_cookies = 1
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = Strict
session.gc_maxlifetime = 7200
session.sid_length = 48
session.sid_bits_per_character = 6
date.timezone = Asia/Dhaka
max_execution_time = 120
max_input_time = 60
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 10M
EOF
```

Add to Dockerfile:

```dockerfile
FROM php:8.2-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libldap2-dev libzip-dev libonig-dev \
    && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install ldap pdo pdo_mysql mbstring zip \
    && rm -rf /var/lib/apt/lists/*

# Copy security config
COPY docker/php-security.ini /usr/local/etc/php/conf.d/security.ini

WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

# Remove unnecessary PHP tools
RUN rm -f /usr/bin/phpdbg /usr/local/bin/phpdbg
```

Rebuild:

```bash
docker compose -f /opt/accesspilot/docker/docker-compose.yml build --no-cache php
docker compose -f /opt/accesspilot/docker/docker-compose.yml up -d
```

### 3.6 Harden Container Nginx

Update `/opt/accesspilot/docker/nginx/default.conf`:

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php login.php;
    server_tokens off;

    client_max_body_size 10M;
    client_body_timeout 30s;
    client_header_timeout 30s;
    keepalive_timeout 30s;
    send_timeout 30s;

    location /resources/ {
        root /var/www/html/public;
        expires 7d;
        add_header Cache-Control "public, immutable";
        add_header X-Content-Type-Options "nosniff";
    }
    location /assets/ {
        expires 7d;
        add_header Cache-Control "public, immutable";
    }

    location ~ ^/(app|bootstrap|config|scripts|App_Data)/ {
        deny all;
        return 404;
    }
    location ~ /\. {
        deny all;
        return 404;
    }
    location ~* (wp-admin|wp-content|wp-includes|xmlrpc\.php|phpmyadmin|adminer|\.env) {
        deny all;
        return 404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param PHP_VALUE "display_errors=off";
        fastcgi_read_timeout 120s;
    }

    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;
}
```

---

## 4. LDAP & AD Domain Security

### 4.1 Current Configuration

From `domains.json`:

| Domain | Host | IP | Port | TLS |
|--------|------|----|------|-----|
| wgbd.com | dcpri4.wgbd.com | 192.168.20.7 | 389 | ✅ yes |
| whildc.com | DC-AD1.WHILDC.COM | 192.168.119.169 | 389 | ✅ yes |

### 4.2 DNS Verification

```bash
# Test resolution from container
docker exec accesspilot_php getent hosts dcpri4.wgbd.com
docker exec accesspilot_php getent hosts DC-AD1.WHILDC.COM

# If not resolving, add extra_hosts to docker-compose.yml:
# extra_hosts:
#   - "dcpri4.wgbd.com:192.168.20.7"
#   - "DC-AD1.WHILDC.COM:192.168.119.169"
```

### 4.3 LDAP Reachability Test

```bash
docker exec accesspilot_php php -r '
$domains = json_decode(file_get_contents("/data/secure/ldap/domains.json"), true);
foreach ($domains as $d) {
    $l = @ldap_connect($d["host"], $d["port"]);
    ldap_set_option($l, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($l, LDAP_OPT_NETWORK_TIMEOUT, 5);
    $ok = @ldap_bind($l, "", "");
    echo $d["key"] . " (" . $d["host"] . ":" . $d["port"] . "): " . ($ok ? "OK" : "FAIL: " . ldap_error($l)) . "\n";
}
'
```

### 4.4 LDAP Firewall Rules

Restrict outbound LDAP to only the AD DCs:

```bash
# Create a script for strict outbound rules
cat > /usr/local/bin/apply-ldap-rules.sh << 'SCRIPT'
#!/bin/bash
# Allow DNS
iptables -A OUTPUT -p udp --dport 53 -j ACCEPT
# Allow established connections
iptables -A OUTPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
# Allow loopback
iptables -A OUTPUT -o lo -j ACCEPT
# Allow LDAP/LDAPS to specific AD DCs
iptables -A OUTPUT -d 192.168.20.7 -p tcp --dport 389 -j ACCEPT
iptables -A OUTPUT -d 192.168.20.7 -p tcp --dport 636 -j ACCEPT
iptables -A OUTPUT -d 192.168.119.169 -p tcp --dport 389 -j ACCEPT
iptables -A OUTPUT -d 192.168.119.169 -p tcp --dport 636 -j ACCEPT
# Block all other LDAP outbound
iptables -A OUTPUT -p tcp --dport 389 -j DROP
iptables -A OUTPUT -p tcp --dport 636 -j DROP
echo "LDAP firewall rules applied"
SCRIPT
chmod +x /usr/local/bin/apply-ldap-rules.sh
```

---

## 5. Docker Daemon & Host Hardening

### 5.1 Docker Daemon Config

```bash
cat > /etc/docker/daemon.json << 'EOF'
{
  "icc": false,
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "10m",
    "max-file": "3"
  },
  "live-restore": true,
  "no-new-privileges": true,
  "userland-proxy": false
}
EOF

systemctl restart docker
```

**Note**: `userland-proxy: false` requires the `DOCKER-USER` iptables rule from §3.2 to be in place, because it switches from docker-proxy (userland) to direct iptables DNAT.

### 5.2 Systemd Service for Auto-Start

```bash
cat > /etc/systemd/system/accesspilot.service << 'EOF'
[Unit]
Description=AccessPilot Docker Stack
Requires=docker.service
After=docker.service

[Service]
Type=oneshot
RemainAfterExit=yes
ExecStart=/usr/bin/docker compose -f /opt/accesspilot/docker/docker-compose.yml up -d
ExecStop=/usr/bin/docker compose -f /opt/accesspilot/docker/docker-compose.yml down
StandardOutput=journal

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable accesspilot
systemctl start accesspilot
```

### 5.3 Password Policy

```bash
# Enforce password expiration for system users
chage -M 90 -m 7 -W 14 $(whoami)

# Update /etc/login.defs
sed -i 's/^PASS_MAX_DAYS.*/PASS_MAX_DAYS 90/' /etc/login.defs
sed -i 's/^PASS_MIN_DAYS.*/PASS_MIN_DAYS 7/' /etc/login.defs
sed -i 's/^PASS_WARN_AGE.*/PASS_WARN_AGE 14/' /etc/login.defs

# Install and enforce password quality
apt install -y libpam-pwquality
# /etc/pam.d/common-password: add minlen=12 minclass=4
```

### 5.4 Automatic Security Updates

```bash
apt install -y unattended-upgrades
dpkg-reconfigure --priority=low unattended-upgrades
```

---

## 6. Monitoring & Intrusion Detection

### 6.1 Install fail2ban

```bash
apt install -y fail2ban

# Create filter for AccessPilot
cat > /etc/fail2ban/filter.d/accesspilot-nginx.conf << 'EOF'
[Definition]
failregex = ^<HOST> - - .* "(GET|POST).*(wp-admin|phpmyadmin|\.env|\.git|adminer|xmlrpc\.php|\.\./|/etc/passwd)"
ignoreregex =
EOF

# Create jail config
cat > /etc/fail2ban/jail.local << 'EOF'
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 10

[sshd]
enabled = true
port = ssh
maxretry = 5

[accesspilot-nginx]
enabled = true
port = http,https
filter = accesspilot-nginx
logpath = /var/log/nginx/accesspilot_access.log
maxretry = 10
EOF

systemctl enable fail2ban
systemctl restart fail2ban
```

### 6.2 Health Check Script

```bash
cat > /usr/local/bin/check-accesspilot.sh << 'SCRIPT'
#!/bin/bash
# Checks web + LDAP connectivity every 5 min
WEB=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080 2>/dev/null)
if [[ "$WEB" != "200" && "$WEB" != "302" ]]; then
    logger -t accesspilot "ALERT: Web unreachable (HTTP $WEB)"
    systemctl restart accesspilot
fi

# LDAP check
docker exec accesspilot_php php -r '
$c = json_decode(file_get_contents("/data/secure/ldap/config.json"), true);
$l = ldap_connect($c["host"], $c["port"]);
ldap_set_option($l, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($l, LDAP_OPT_NETWORK_TIMEOUT, 5);
echo @ldap_bind($l,"","") ? "OK" : "FAIL";
' | grep -q FAIL && logger -t accesspilot "ALERT: LDAP connection failed"
SCRIPT
chmod +x /usr/local/bin/check-accesspilot.sh

# Cron: every 5 min
echo "*/5 * * * * root /usr/local/bin/check-accesspilot.sh" > /etc/cron.d/accesspilot-health
```

### 6.3 Kernel Security (sysctl)

```bash
cat > /etc/sysctl.d/99-accesspilot.conf << 'EOF'
# IP Spoofing protection
net.ipv4.conf.all.rp_filter = 1
net.ipv4.conf.default.rp_filter = 1

# Ignore ICMP redirects
net.ipv4.conf.all.accept_redirects = 0
net.ipv4.conf.default.accept_redirects = 0
net.ipv6.conf.all.accept_redirects = 0
net.ipv6.conf.default.accept_redirects = 0

# Ignore source-routed packets
net.ipv4.conf.all.accept_source_route = 0
net.ipv6.conf.all.accept_source_route = 0

# SYN flood protection
net.ipv4.tcp_syncookies = 1
net.ipv4.tcp_syn_retries = 2
net.ipv4.tcp_synack_retries = 2

# Ignore ICMP echo (disable ping)
net.ipv4.icmp_echo_ignore_all = 1

# Increase backlog
net.core.somaxconn = 1024
net.ipv4.tcp_max_syn_backlog = 2048
EOF

sysctl --system
```

---

## 7. Complete Hardening Script

Run this in one shot:

```bash
#!/bin/bash
set -e

echo "=== 1. UFW Firewall ==="
ufw --force reset
ufw default deny incoming
ufw default allow outgoing
ufw allow from 192.168.0.0/16 to any port 22 proto tcp comment 'SSH'
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'
ufw deny 8080/tcp comment 'Block Docker direct'
ufw --force enable

echo "=== 2. iptables DOCKER-USER ==="
iptables -I DOCKER-USER -p tcp --dport 8080 ! -s 127.0.0.1 -j DROP
netfilter-persistent save

echo "=== 3. SSH Hardening ==="
sed -i 's/^PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config
sed -i 's/^#PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
echo "AllowUsers $(whoami)" >> /etc/ssh/sshd_config
systemctl restart sshd

echo "=== 4. Docker Daemon ==="
cat > /etc/docker/daemon.json << 'DAEMON'
{
  "icc": false,
  "log-driver": "json-file",
  "log-opts": {"max-size": "10m", "max-file": "3"},
  "live-restore": true,
  "no-new-privileges": true,
  "userland-proxy": false
}
DAEMON
systemctl restart docker || echo "WARN: docker restart needed"

echo "=== 5. Systemd Service ==="
if [ ! -f /etc/systemd/system/accesspilot.service ]; then
    cat > /etc/systemd/system/accesspilot.service << 'SVC'
[Unit]
Description=AccessPilot Docker Stack
Requires=docker.service
After=docker.service
[Service]
Type=oneshot
RemainAfterExit=yes
ExecStart=/usr/bin/docker compose -f /opt/accesspilot/docker/docker-compose.yml up -d
ExecStop=/usr/bin/docker compose -f /opt/accesspilot/docker/docker-compose.yml down
StandardOutput=journal
[Install]
WantedBy=multi-user.target
SVC
    systemctl daemon-reload
    systemctl enable accesspilot
fi

echo "=== 6. fail2ban ==="
apt install -y fail2ban
cat > /etc/fail2ban/jail.local << 'JAIL'
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 10
[sshd]
enabled = true
maxretry = 5
JAIL
systemctl restart fail2ban

echo "=== 7. Password Policy ==="
apt install -y libpam-pwquality
sed -i 's/^PASS_MAX_DAYS.*/PASS_MAX_DAYS 90/' /etc/login.defs
sed -i 's/^PASS_MIN_DAYS.*/PASS_MIN_DAYS 7/' /etc/login.defs

echo "=== 8. Automatic Updates ==="
apt install -y unattended-upgrades
dpkg-reconfigure --priority=low unattended-upgrades

echo "=== 9. sysctl ==="
cat > /etc/sysctl.d/99-accesspilot.conf << 'SYSCTL'
net.ipv4.conf.all.rp_filter = 1
net.ipv4.conf.all.accept_redirects = 0
net.ipv4.conf.all.accept_source_route = 0
net.ipv6.conf.all.accept_redirects = 0
net.ipv6.conf.all.accept_source_route = 0
net.ipv4.tcp_syncookies = 1
net.ipv4.tcp_syn_retries = 2
net.ipv4.tcp_synack_retries = 2
net.ipv4.icmp_echo_ignore_all = 1
net.core.somaxconn = 1024
net.ipv4.tcp_max_syn_backlog = 2048
SYSCTL
sysctl --system

echo "=== 10. Health Check Cron ==="
cat > /usr/local/bin/check-accesspilot.sh << 'HC'
#!/bin/bash
WEB=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080 2>/dev/null)
if [[ "$WEB" != "200" && "$WEB" != "302" ]]; then
    logger -t accesspilot "ALERT: Web unreachable (HTTP $WEB)"
    systemctl restart accesspilot
fi
HC
chmod +x /usr/local/bin/check-accesspilot.sh
echo "*/5 * * * * root /usr/local/bin/check-accesspilot.sh" > /etc/cron.d/accesspilot-health

echo ""
echo "=== HARDENING COMPLETE ==="
echo "Next steps:"
echo "  1. Update docker-compose.yml with container security fixes (§3.4)"
echo "  2. Create php-security.ini and rebuild container (§3.5)"
echo "  3. Update container nginx config (§3.6)"
echo "  4. Rebuild and restart: docker compose build php && docker compose up -d"
echo "  5. Verify: docker exec accesspilot_php php -i | grep -E 'expose_php|disable_functions|open_basedir|allow_url_fopen'"
```

---

## 8. Verification Checklist

Run after hardening:

```bash
# Firewall
echo "=== FIREWALL ===" && ufw status verbose

# No direct 8080 access from external
echo "=== EXTERNAL 8080 ===" && curl -s -o /dev/null -w "%{http_code}" http://192.168.1.172:8080 2>/dev/null || echo "(blocked)"

# Container security
for c in accesspilot_web accesspilot_php; do
    echo "=== $c ==="
    docker inspect "$c" --format 'CapDrop={{range .HostConfig.CapDrop}}{{.}} {{end}} | Readonly={{.HostConfig.ReadonlyRootfs}} | no-new-priv={{.HostConfig.SecurityOpt}}'
done

# PHP security
echo "=== PHP ===" && docker exec accesspilot_php php -i | grep -E "expose_php|disable_functions|open_basedir|allow_url_fopen|session.cookie_httponly|session.cookie_secure"

# LDAP
echo "=== LDAP ===" && docker exec accesspilot_php php -r '
$c = json_decode(file_get_contents("/data/secure/ldap/config.json"), true);
$l = ldap_connect($c["host"], $c["port"]);
ldap_set_option($l, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($l, LDAP_OPT_NETWORK_TIMEOUT, 5);
echo @ldap_bind($l,"","") ? "OK\n" : "FAIL: ".ldap_error($l)."\n";
'
```

---

## 9. Reference: Key File Paths

| File | Purpose |
|------|---------|
| `/opt/accesspilot/docker/docker-compose.yml` | Container definitions, ports, volumes, security options |
| `/opt/accesspilot/docker/nginx/default.conf` | Container nginx config |
| `/opt/accesspilot/docker/Dockerfile` | PHP container build |
| `/opt/accesspilot/docker/php-security.ini` | PHP security settings (to be created) |
| `/data/secure/ldap/config.json` | Default LDAP connection config |
| `/data/secure/ldap/domains.json` | All AD domain definitions |
| `/data/secure/ldap/secrets/*.json` | Per-domain encrypted bind credentials |
| `/etc/ssh/sshd_config` | SSH server config |
| `/etc/docker/daemon.json` | Docker daemon config |
| `/etc/systemd/system/accesspilot.service` | Auto-start service |
| `/etc/fail2ban/jail.local` | fail2ban rules |
| `/etc/sysctl.d/99-accesspilot.conf` | Kernel hardening |
