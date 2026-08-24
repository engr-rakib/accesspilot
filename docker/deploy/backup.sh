#!/bin/bash
# ==============================================================================
# AccessPilot — Backup Script
# ==============================================================================
# Backup kore: Code base (/app/accesspilot) + Data (/data/secure + /data/logs)
# Save kore:  /bkp/ (alada LV te)
# Rakhe:      Last 5 versions, older auto-delete
#
# Usage:
#   sudo bash docker/deploy/backup.sh              # Code + Data backup
#   sudo bash docker/deploy/backup.sh --code-only  # Shudhu code
#   sudo bash docker/deploy/backup.sh --data-only  # Shudhu data
#   sudo bash docker/deploy/backup.sh --help       # Help
# ==============================================================================

set -euo pipefail

BACKUP_DIR="/bkp"
PROJECT_ROOT="/app/accesspilot"
PARENT_DIR="/app"
PROJECT_NAME="accesspilot"
NOW=$(date +%Y%m%d_%H%M)
MODE="${1:-both}"
KEEP_LAST=5

show_help() {
    sed -n '2,14p' "$0"
    exit 0
}

case "$MODE" in
    --help|-h)    show_help ;;
    --code-only)  MODE="code" ;;
    --data-only)  MODE="data" ;;
    *)            MODE="both" ;;
esac

sudo mkdir -p "$BACKUP_DIR"

echo "============================================"
echo " AccessPilot Backup — $(date)"
echo " Backup dir: $BACKUP_DIR"
echo " Keep last:  $KEEP_LAST versions"
echo " Mode:       $MODE"
echo "============================================"

# ── 1. Code Backup ──────────────────────────────────────
if [ "$MODE" = "both" ] || [ "$MODE" = "code" ]; then
    CODE_FILE="${BACKUP_DIR}/AccessPilot_Code_${NOW}.tar.gz"
    echo ""
    echo "📦 Creating code backup..."
    echo "   Source: $PROJECT_ROOT"
    echo "   Output: $(basename $CODE_FILE)"

    sudo tar -czf "$CODE_FILE" \
        --exclude="$PROJECT_NAME/.git" \
        --exclude="$PROJECT_NAME/.opencode" \
        -C "$PARENT_DIR" "$PROJECT_NAME"

    echo "   ✅ Done: $(ls -lh "$CODE_FILE" | awk '{print $5}')"

    # Cleanup: keep last KEEP_LAST code backups
    echo ""
    echo "🧹 Cleaning old code backups (keep $KEEP_LAST)..."
    ls -t "$BACKUP_DIR"/AccessPilot_Code_*.tar.gz 2>/dev/null | tail -n +$((KEEP_LAST + 1)) | xargs -r sudo rm -f
    echo "   Remaining: $(ls -1 "$BACKUP_DIR"/AccessPilot_Code_*.tar.gz 2>/dev/null | wc -l) versions"
fi

# ── 2. Data Backup ──────────────────────────────────────
if [ "$MODE" = "both" ] || [ "$MODE" = "data" ]; then
    DATA_FILE="${BACKUP_DIR}/AccessPilot_Data_${NOW}.tar.gz"
    echo ""
    echo "📦 Creating data backup..."
    echo "   Source: /data/secure + /data/logs"
    echo "   Output: $(basename $DATA_FILE)"

    if [ -d "/data/secure" ] || [ -d "/data/logs" ]; then
        sudo tar -czf "$DATA_FILE" \
            /data/secure /data/logs 2>/dev/null || {
            echo "   ⚠️  Some data paths missing, backup partial"
        }
        echo "   ✅ Done: $(ls -lh "$DATA_FILE" | awk '{print $5}')"
    else
        echo "   ⚠️  /data/secure or /data/logs not found — skipping data backup"
    fi

    # Cleanup: keep last KEEP_LAST data backups
    echo ""
    echo "🧹 Cleaning old data backups (keep $KEEP_LAST)..."
    ls -t "$BACKUP_DIR"/AccessPilot_Data_*.tar.gz 2>/dev/null | tail -n +$((KEEP_LAST + 1)) | xargs -r sudo rm -f
    echo "   Remaining: $(ls -1 "$BACKUP_DIR"/AccessPilot_Data_*.tar.gz 2>/dev/null | wc -l) versions"
fi

echo ""
echo "============================================"
echo " Backup complete!"
echo " Location: $BACKUP_DIR"
echo "============================================"
ls -lh "$BACKUP_DIR"/AccessPilot_*.tar.gz 2>/dev/null || echo "   (empty)"
