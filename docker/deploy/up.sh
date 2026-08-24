#!/bin/bash
# ==============================================================================
# AccessPilot — Container Startup Script (Manual)
# ==============================================================================
# Ki kore:
#   1. Host er nginx/apache2/httpd stop kore (port 80/443 conflict na hoy)
#   2. Port 80/443 force free kore (ss + fuser)
#   3. docker compose up -d run kore
#   4. Server IP show kore
#
# Kobe use korte hoy:
#   - Server reboot er por: sudo bash docker/deploy/up.sh
#   - docker compose down er por:
#       OR just: docker compose up -d  (direct, but port conflict thakle error dibe)
#
# Khub easy alternative (jodi kono conflict na thake):
#   cd /app/accesspilot/docker && docker compose up -d
# ==============================================================================
set -e

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
COMPOSE_DIR="$PROJECT_ROOT/docker"

cd "$COMPOSE_DIR"

# Stop host nginx/apache if on port 80/443
for svc in nginx apache2 httpd; do
    if systemctl is-active --quiet "$svc" 2>/dev/null; then
        echo "Stopping $svc (conflicts with ports)..."
        systemctl stop "$svc" 2>/dev/null || true
        systemctl disable "$svc" 2>/dev/null || true
    fi
done

# Kill processes on our ports
for port in 80 443; do
    if ss -tlnp | grep -q ":$port "; then
        echo "Port $port in use — freeing..."
        fuser -k "${port}/tcp" 2>/dev/null || true
        sleep 1
    fi
done

docker compose up -d

IP=$(hostname -I | awk '{print $1}')
echo ""
echo "=== AccessPilot ==="
echo "https://${IP}/"
