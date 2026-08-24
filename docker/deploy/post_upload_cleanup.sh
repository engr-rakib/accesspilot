#!/bin/bash
# ==============================================================================
# AccessPilot — Post-Upload Cleanup Script
# ==============================================================================
# Run this AFTER transferring the entire project root via WinCP.
# It removes runtime files that should NOT be overwritten from Windows:
#   - setup_complete.lock     ← server-specific lock (bootstrap recreates)
#   - App_Data/*.json         ← session/store files (server-specific)
#   - .env                    ← server-specific environment
#   - logs/, tmp/             ← not needed in production
#
# Usage:
#   sudo bash docker/deploy/post_upload_cleanup.sh
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"

echo "============================================"
echo " AccessPilot — Post-Upload Cleanup"
echo "============================================"

# ── 1. Remove setup_complete.lock (bootstrap recreates it) ──
if [ -f "$PROJECT_ROOT/App_Data/setup_complete.lock" ]; then
    sudo rm -f "$PROJECT_ROOT/App_Data/setup_complete.lock"
    echo "  ✅ Removed: App_Data/setup_complete.lock (will be recreated by bootstrap)"
else
    echo "  ⏭️  Skipped: App_Data/setup_complete.lock (not found)"
fi

# ── 2. Remove session/store JSON files (keep internal_admin.json) ──
if [ -d "$PROJECT_ROOT/App_Data" ]; then
    # Remove session files
    sudo find "$PROJECT_ROOT/App_Data" -maxdepth 1 -name '*.json' ! -name 'internal_admin.json' -exec rm -f {} \; -exec echo "  ✅ Removed: App_Data/{}" \;
    
    # Remove non-JSON runtime files (sessions, stores)
    sudo find "$PROJECT_ROOT/App_Data" -maxdepth 1 -type f ! -name '*.json' ! -name 'internal_admin.json' ! -name 'setup_complete.lock' -exec rm -f {} \; -exec echo "  ✅ Removed: App_Data/{}" \; 2>/dev/null || true
fi

# ── 3. Remove .env (server-specific, keep .env.example) ──
if [ -f "$PROJECT_ROOT/docker/.env" ]; then
    echo "  ⚠️  WARNING: .env file found from Windows. Keeping it but you may need to update variables."
    echo "  🔍 Current .env contents (without values):"
    grep -o '^[^=]*=' "$PROJECT_ROOT/docker/.env" 2>/dev/null || true
fi

# ── 4. Remove logs/, tmp/ if they exist ──
for DIR in "logs" "tmp" "php_logs"; do
    if [ -d "$PROJECT_ROOT/$DIR" ]; then
        sudo rm -rf "$PROJECT_ROOT/$DIR"
        echo "  ✅ Removed: $DIR/"
    fi
done

# ── 5. Ensure permissions are correct ──
if [ -d "$PROJECT_ROOT/App_Data" ]; then
    sudo chown -R 33:33 "$PROJECT_ROOT/App_Data"
    sudo chmod -R 775 "$PROJECT_ROOT/App_Data"
    echo "  ✅ Permissions set: App_Data/ (uid 33, mode 775)"
fi

echo "============================================"
echo " Cleanup complete!"
echo ""
 echo " Next steps:"
 echo "   1. If .env was overwritten: check variables"
 echo "   2. sudo docker compose -f $PROJECT_ROOT/docker/docker-compose.yml restart php"
 echo "   3. sudo docker compose -f $PROJECT_ROOT/docker/docker-compose.yml restart nginx"
 echo "      (Required after WinCP - inode change fix)"
 echo "   4. Verify: sudo docker exec accesspilot_web ls /var/www/html/public/resources/frontend/css/"
 echo "   5. Test: curl -k https://localhost/"
 echo "============================================"
