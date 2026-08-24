#!/bin/bash
set -e

# ============================================================
#  AccessPilot — Host-Level Hardening Script
# ============================================================
#  Ei script ta run korar por — server secure hoy, auto-start
#  set hoy, attacker der against protection hoy.
#
#  Run ONCE per server after `docker compose up -d`
#  Prottek step aage user ke prompt kore: apply korbe ki na.
# ============================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
NC='\033[0m'

info()    { echo -e "${CYAN}[INFO]${NC}    $1"; }
ok()      { echo -e "${GREEN}[OK]${NC}      $1"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}    $1"; }
fail()    { echo -e "${RED}[FAIL]${NC}    $1"; exit 1; }
section() { echo ""; echo -e "${YELLOW}━━━ $1 ━━━${NC}"; }

# ── Root check ─────────────────────────────────────────────
# Ei script root diye run korte hobe, cause system level
# change kore (UFW, systemd, fail2ban etc).
if [[ $EUID -ne 0 ]]; then
    fail "Root permission lagbe. 'sudo bash $0' diye run korun."
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_FILE="$PROJECT_DIR/docker/docker-compose.yml"

# ── Interactive prompt helper ──────────────────────────────
# User ke спрашивает, apply korbe ki na. Default Y.
ask() {
    local desc="$1"
    local default="$2"
    local prompt
    if [[ "$default" == "Y" ]]; then
        prompt="Y/n"
    else
        prompt="y/N"
    fi
    echo ""
    echo -e "${YELLOW}➡  $desc${NC}"
    read -r -p "Apply this step? [$prompt]: " answer
    if [[ -z "$answer" ]]; then
        answer="$default"
    fi
    [[ "$answer" =~ ^[Yy]$ ]]
}

echo ""
echo -e "${CYAN}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║     AccessPilot Host-Level Hardening Script     ║${NC}"
echo -e "${CYAN}║     Prottek step aage prompt asbe — Y/N         ║${NC}"
echo -e "${CYAN}║     Skip korle step ta apply hobe na.           ║${NC}"
echo -e "${CYAN}╚══════════════════════════════════════════════════╝${NC}"
echo -e "${YELLOW}Project root: $PROJECT_DIR${NC}"
echo ""

# ── 1. UFW Firewall ────────────────────────────────────────
section "UFW FIREWALL"

echo -e "${CYAN}Ki kore:${NC}"
echo -e "  - Default incoming block kore (DENY)"
echo -e "  - Shudhu matro 80/tcp (HTTP), 443/tcp (HTTPS) allow kore"
echo -e "  - SSH (22/tcp) allow kore LAN er moddhe (192.168.0.0/16)"
echo -e "  - Baki sob port bandh — attacker direct access pabe na"
echo ""
echo -e "${CYAN}Keno dorkar:${NC}"
echo -e "  - Docker container e vulnerability thakleo host level e"
echo -e "    firewall theke protect thakbe"
echo -e "  - Unwanted port scan / probe block hoy"
echo ""

if ask "Apply UFW firewall rules?" "Y"; then
    info "Configuring UFW firewall..."
    ufw --force reset 2>/dev/null || true
    ufw default deny incoming
    ufw default allow outgoing
    ufw allow from 192.168.0.0/16 to any port 22 proto tcp comment 'SSH (LAN)'
    ufw allow 80/tcp  comment 'HTTP -> HTTPS redirect'
    ufw allow 443/tcp comment 'HTTPS'
    ufw --force enable
    ok "UFW enabled: SSH(LAN), HTTP, HTTPS allowed"
else
    warn "UFW not applied. Server firewall chara unprotected thakbe."
fi

# ── 2. iptables DOCKER-USER ─────────────────────────────────
section "DOCKER-USER IPTABLES CHAIN"

echo -e "${CYAN}Ki kore:${NC}"
echo -e "  - DOCKER-USER iptables chain clear kore"
echo -e "  - Docker er direct-port mapping kaaj kore (80/443 direct)"
echo -e "  - Persist kore (reboot por o thakbe)"
echo ""
echo -e "${CYAN}Keno dorkar:${NC}"
echo -e "  - Pura project e host-level reverse proxy nai"
echo -e "  - Nginx container direct 80/443 expose kore"
echo -e "  - Age DOCKER-USER e loopback restriction chilo —"
echo -e "    sheta 'ERR_CONNECTION_TIMED_OUT' ditchhe"
echo -e "  - Ei step ta sei bug fix kore"
echo ""

if ask "Clear DOCKER-USER chain + persist iptables?" "Y"; then
    info "Clearing DOCKER-USER chain..."
    iptables -F DOCKER-USER 2>/dev/null
    if ! command -v netfilter-persistent &>/dev/null; then
        echo "iptables-persistent iptables-persistent/autosave_v4 boolean true" | debconf-set-selections
        echo "iptables-persistent iptables-persistent/autosave_v6 boolean true" | debconf-set-selections
        DEBIAN_FRONTEND=noninteractive apt-get install -y iptables-persistent
    fi
    netfilter-persistent save
    ok "DOCKER-USER cleared + iptables saved"
else
    warn "DOCKER-USER not cleared. Port mapping issue thakle deploy break korbe."
fi

# ── 3. systemd Service ──────────────────────────────────────
section "SYSTEMD AUTO-START SERVICE"

echo -e "${CYAN}Ki kore:${NC}"
echo -e "  - /etc/systemd/system/accesspilot.service create kore"
echo -e "  - Server reboot korle automatic docker compose up hobe"
echo -e "  - Docker daemon ready na hoye thakle wait kore"
echo ""
echo -e "${CYAN}Keno dorkar:${NC}"
echo -e "  - Server突然 power loss / reboot korleo"
echo -e "  - Manual intervention charai app up hoy jabe"
echo -e "  - Production server er jonno MUST have"
echo ""

if ask "Create systemd auto-start service?" "Y"; then
    info "Creating systemd service..."
    cat > /etc/systemd/system/accesspilot.service << 'SERVICE'
[Unit]
Description=AccessPilot Docker Stack
Requires=docker.service
After=docker.service network-online.target

[Service]
Type=oneshot
RemainAfterExit=yes
ExecStart=/usr/bin/docker compose -f COMPOSE_FILE_PLACEHOLDER up -d
ExecStop=/usr/bin/docker compose -f COMPOSE_FILE_PLACEHOLDER down
ExecReload=/usr/bin/docker compose -f COMPOSE_FILE_PLACEHOLDER restart nginx
Restart=on-failure

[Install]
WantedBy=multi-user.target
SERVICE
    sed -i "s|COMPOSE_FILE_PLACEHOLDER|$COMPOSE_FILE|g" /etc/systemd/system/accesspilot.service
    systemctl daemon-reload
    systemctl enable accesspilot
    systemctl start accesspilot 2>/dev/null || true
    ok "systemd service created and enabled"
else
    warn "Auto-start not configured. Reboot korle manual 'docker compose up' lagbe."
fi

# ── 4. fail2ban ─────────────────────────────────────────────
section "FAIL2BAN INTRUSION PREVENTION"

echo -e "${CYAN}Ki kore:${NC}"
echo -e "  - fail2ban install kore (intrusion prevention tool)"
echo -e "  - 3 ta jail (rule) set kore nginx er jonno:"
echo ""
echo -e "  ${YELLOW}1. nginx-http-auth${NC}"
echo -e "     Login fail korle (401) → 5 try er por 10min ban"
echo ""
echo -e "  ${YELLOW}2. nginx-botsearch${NC}"
echo -e "     wp-admin, .env, .git etc scan korle → 10 hit er por 24hr ban"
echo ""
echo -e "  ${YELLOW}3. nginx-brute-force${NC}"
echo -e "     Repeated request → 10 hit er por 1hr ban"
echo ""
echo -e "${CYAN}Keno dorkar:${NC}"
echo -e "  - Internet e bots constantly scan kore"
echo -e "  - fail2ban auto detect kore IP block kore dey"
echo -e "  - Manual intervention charai attacker der prevent kore"
echo ""

if ask "Install & configure fail2ban?" "Y"; then
    info "Installing and configuring fail2ban..."
    apt-get install -y fail2ban

    cat > /etc/fail2ban/jail.local << 'FAIL2BAN'
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5
ignoreip = 127.0.0.1/8 ::1 192.168.0.0/16

[nginx-http-auth]
enabled = true
port = http,https
logpath = /data/logs/nginx/error.log
maxretry = 5

[nginx-botsearch]
enabled = true
port = http,https
logpath = /data/logs/nginx/access.log
maxretry = 10
findtime = 60
bantime = 86400

[nginx-brute-force]
enabled = true
port = http,https
logpath = /data/logs/nginx/access.log
maxretry = 10
findtime = 60
bantime = 3600
FAIL2BAN

    systemctl enable fail2ban
    systemctl restart fail2ban
    ok "fail2ban installed and configured"
else
    warn "fail2ban not installed. Brute force + bot attacks unprotected."
fi

# ── 5. logrotate ────────────────────────────────────────────
section "LOGROTATE — NGINX LOG ROTATION"

echo -e "${CYAN}Ki kore:${NC}"
echo -e "  - Nginx access/error log daily rotation set kore"
echo -e "  - 30 days por auto delete kore"
echo -e "  - Old logs compress kore (gzip)"
echo ""
echo -e "${CYAN}Keno dorkar:${NC}"
echo -e "  - Nginx log daily ~30MB+ hoy"
echo -e "  - Rotation na thakle disk full hoye jabe"
echo -e "  - Tarpor app crash korbe, log o save thakbe na"
echo ""

if ask "Set up nginx log rotation (daily, 30 day retention)?" "Y"; then
    info "Setting up nginx log rotation..."
    cat > /etc/logrotate.d/accesspilot-nginx << 'LOGROTATE'
/data/logs/nginx/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 0640 root root
    sharedscripts
    postrotate
        docker exec accesspilot_web nginx -s reopen 2>/dev/null || true
    endscript
}
LOGROTATE
    ok "logrotate configured (daily, 30 day retention)"
else
    warn "Log rotation not configured. Monitor disk space manually."
fi

# ── 6. Ensure log directory exists ──────────────────────────
section "LOG DIRECTORY"

echo -e "${CYAN}Ki kore:${NC}"
echo -e "  - /data/logs/nginx directory create kore"
echo -e "  - Nginx container e bind mount kora ache"
echo ""
echo -e "${CYAN}Keno dorkar:${NC}"
echo -e "  - Directory na thakle nginx start korbe na"
echo ""

if ask "Ensure /data/logs/nginx exists?" "Y"; then
    mkdir -p /data/logs/nginx
    ok "Log directory /data/logs/nginx ready"
else
    warn "Directory not created. Nginx log bind mount fail korbe."
fi

# ── 7. Final Summary ────────────────────────────────────────
section "VERIFICATION"

echo ""
echo -e "${GREEN}══════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  Hardening Complete — Summary                     ${NC}"
echo -e "${GREEN}══════════════════════════════════════════════════${NC}"
echo ""

# Check UFW
if command -v ufw &>/dev/null && ufw status | grep -q "Status: active"; then
    ok "UFW: ACTIVE (80, 443, SSH-LAN)"
else
    warn "UFW: NOT active"
fi

# Check DOCKER-USER
if iptables -L DOCKER-USER -n 2>/dev/null | grep -q "ACCEPT"; then
    ok "DOCKER-USER: Empty chain (direct port mode)"
else
    warn "DOCKER-USER: Non-empty or missing — check iptables"
fi

# Check systemd
if systemctl is-enabled accesspilot &>/dev/null; then
    ok "systemd: ENABLED (auto-start on boot)"
else
    warn "systemd: NOT configured"
fi

# Check fail2ban
if systemctl is-active fail2ban &>/dev/null; then
    ok "fail2ban: ACTIVE (3 jails)"
else
    warn "fail2ban: NOT running"
fi

# Check logrotate
if [[ -f /etc/logrotate.d/accesspilot-nginx ]]; then
    ok "logrotate: CONFIGURED (daily, 30 days)"
else
    warn "logrotate: NOT configured"
fi

echo ""
echo -e "${CYAN}Recommendation:${NC} System reboot korben auto-start verify korar jonno."
echo -e "${CYAN}  sudo reboot${NC}"
echo -e "${CYAN}  Then check: systemctl status accesspilot${NC}"
echo ""

# ── Optional: Ask for reboot ───────────────────────────────
if ask "System reboot kore verify korte chan?" "n"; then
    info "Rebooting in 5 seconds..."
    sleep 5
    reboot
fi
