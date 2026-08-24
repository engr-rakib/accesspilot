#!/bin/bash
# ==============================================================================
# AccessPilot — Rollback Script (Interactive)
# ==============================================================================
# backup.sh diye neya backup theke interactive restore.
# Kon version, kon component, specific file — sob choose kora jay.
#
# Usage:
#   sudo bash docker/deploy/rollback.sh        # Interactive mode
#   sudo bash docker/deploy/rollback.sh --list # Available backups dekhabe
# ==============================================================================

set -euo pipefail

BACKUP_DIR="/bkp"
PROJECT_ROOT="/app/accesspilot"
PARENT_DIR="/app"
COMPOSE_FILE="$PROJECT_ROOT/docker/docker-compose.yml"
CODE_PATTERN="AccessPilot_Code_*.tar.gz"
DATA_PATTERN="AccessPilot_Data_*.tar.gz"

RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
NC='\033[0m'

info()    { echo -e "${CYAN}[INFO]${NC}  $1"; }
ok()      { echo -e "${GREEN}[OK]${NC}    $1"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $1"; }
fail()    { echo -e "${RED}[FAIL]${NC}  $1"; exit 1; }

# ── Show available backups ─────────────────────────────────
list_backups() {
    echo ""
    echo -e "${CYAN}╔════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║     Available Backups in $BACKUP_DIR ${NC}"
    echo -e "${CYAN}╚════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${YELLOW}Code backups (AccessPilot_Code_YYYYMMDD_HHMM.tar.gz):${NC}"
    ls -1t "$BACKUP_DIR"/$CODE_PATTERN 2>/dev/null | while read f; do
        size=$(ls -lh "$f" | awk '{print $5}')
        ts=$(date -r "$f" "+%Y-%m-%d %H:%M" 2>/dev/null)
        echo "  $(basename $f)  (${size})  ${ts}"
    done || echo "  (none)"
    echo ""
    echo -e "${YELLOW}Data backups (AccessPilot_Data_YYYYMMDD_HHMM.tar.gz):${NC}"
    ls -1t "$BACKUP_DIR"/$DATA_PATTERN 2>/dev/null | while read f; do
        size=$(ls -lh "$f" | awk '{print $5}')
        ts=$(date -r "$f" "+%Y-%m-%d %H:%M" 2>/dev/null)
        echo "  $(basename $f)  (${size})  ${ts}"
    done || echo "  (none)"
    echo ""
}

# ── Pick a backup file ────────────────────────────────────
pick_file() {
    local prefix="$1"
    local label="$2"
    local files=()

    local pattern="${prefix}"  # "code" or "data"
    if [ "$pattern" = "code" ]; then
        pattern="$CODE_PATTERN"
    else
        pattern="$DATA_PATTERN"
    fi
    while IFS= read -r f; do
        files+=("$f")
    done < <(ls -t "$BACKUP_DIR"/$pattern 2>/dev/null)

    if [ ${#files[@]} -eq 0 ]; then
        echo ""
        echo -e "${YELLOW}No ${label} backups found in $BACKUP_DIR${NC}"
        return 1
    fi

    echo ""
    echo -e "${CYAN}Available ${label} backups:${NC}"
    for i in "${!files[@]}"; do
        name=$(basename "${files[$i]}")
        size=$(ls -lh "${files[$i]}" | awk '{print $5}')
        created=$(date -r "${files[$i]}" "+%Y-%m-%d %H:%M" 2>/dev/null || echo "")
        echo "  [$((i+1))] $name  (${size})  ${created}"
    done

    echo ""
    read -p "Select ${label} backup [1-${#files[@]}] or 0 to skip: " choice

    if [[ "$choice" == "0" ]]; then
        return 1
    fi

    if ! [[ "$choice" =~ ^[0-9]+$ ]] || [ "$choice" -lt 1 ] || [ "$choice" -gt "${#files[@]}" ]; then
        warn "Invalid choice. Skipping ${label} restore."
        return 1
    fi

    SELECTED="${files[$((choice-1))]}"
    return 0
}

# ── Show tar contents ─────────────────────────────────────
show_contents() {
    local file="$1"
    echo ""
    echo -e "${CYAN}Contents of $(basename $file):${NC}"
    tar -tzf "$file" 2>/dev/null | head -30
    local total=$(tar -tzf "$file" 2>/dev/null | wc -l)
    if [ "$total" -gt 30 ]; then
        echo "  ... and $((total - 30)) more files"
    fi
    echo "  Total: $total entries"
}

# ── Pick specific files from tar ──────────────────────────
pick_subdirs() {
    local file="$1"
    echo ""
    echo -e "${YELLOW}Restore options:${NC}"
    echo "  [1] All files (full restore)"
    echo "  [2] Choose specific directories/files"
    echo ""
    read -p "Choose [1/2]: " restore_mode

    if [ "$restore_mode" = "2" ]; then
        echo ""
        echo -e "${CYAN}Available top-level directories in backup:${NC}"
        tar -tzf "$file" 2>/dev/null | grep -o '^[^/]*/[^/]*/' | sort -u | nl -w2 -s') '

        echo ""
        read -p "Enter exact path(s) to restore (space separated, e.g., 'app/accesspilot/app app/accesspilot/config'): " -a paths

        if [ ${#paths[@]} -eq 0 ]; then
            warn "No paths selected. Restoring all."
            return ""
        fi
        echo "${paths[@]}"
        return
    fi
    echo ""
}

# ── Main ──────────────────────────────────────────────────
echo -e "${CYAN}╔════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║     AccessPilot Rollback — Interactive  ${NC}"
echo -e "${CYAN}╚════════════════════════════════════════╝${NC}"

# Handle --list
if [ "${1:-}" = "--list" ]; then
    list_backups
    exit 0
fi

list_backups

# ── Step 1: Choose code backup ───────────────────────────
echo -e "${YELLOW}━━━ Step 1: Code Restore ━━━${NC}"
CODE_FILE=""
if pick_file "code" "code"; then
    CODE_FILE="$SELECTED"
    show_contents "$CODE_FILE"
    CODE_PATHS=$(pick_subdirs "$CODE_FILE")
fi

# ── Step 2: Choose data backup ──────────────────────────
echo ""
echo -e "${YELLOW}━━━ Step 2: Data Restore ━━━${NC}"
DATA_FILE=""
if pick_file "data" "data"; then
    DATA_FILE="$SELECTED"
    show_contents "$DATA_FILE"
    DATA_PATHS=$(pick_subdirs "$DATA_FILE")
fi

# ── Step 3: Confirm ─────────────────────────────────────
if [ -z "$CODE_FILE" ] && [ -z "$DATA_FILE" ]; then
    fail "Kichu select kora hoy nai. Cancelling."
fi

echo ""
echo -e "${RED}══════════════════════════════════════════${NC}"
echo -e "${RED}  ⚠️  WARNING: Overwrite will happen!     ${NC}"
echo -e "${RED}══════════════════════════════════════════${NC}"
[ -n "$CODE_FILE" ] && echo "  Code: $(basename $CODE_FILE)"
[ -n "$DATA_FILE" ] && echo "  Data: $(basename $DATA_FILE)"
echo ""
read -p "Continue with restore? (yes/no): " CONFIRM
if [ "$CONFIRM" != "yes" ]; then
    echo "❌ Rollback cancelled"
    exit 0
fi

# ── Step 4: Stop containers ─────────────────────────────
echo ""
info "Stopping containers..."
sudo docker compose -f "$COMPOSE_FILE" down 2>/dev/null || true
ok "Containers stopped"

# ── Step 5: Restore code ────────────────────────────────
if [ -n "$CODE_FILE" ]; then
    echo ""
    info "Restoring code from $(basename $CODE_FILE)..."
    if [ -n "$CODE_PATHS" ]; then
        # Restore specific paths
        sudo tar -xzf "$CODE_FILE" -C "$PARENT_DIR" $CODE_PATHS
        ok "Specific paths restored"
    else
        # Full restore
        sudo tar -xzf "$CODE_FILE" -C "$PARENT_DIR"
        ok "Full code restored to $PROJECT_ROOT"
    fi
fi

# ── Step 6: Restore data ────────────────────────────────
if [ -n "$DATA_FILE" ]; then
    echo ""
    info "Restoring data from $(basename $DATA_FILE)..."
    if [ -n "$DATA_PATHS" ]; then
        sudo tar -xzf "$DATA_FILE" -C / $DATA_PATHS
    else
        sudo tar -xzf "$DATA_FILE" -C /
    fi
    sudo chown -R 33:33 /data/secure /data/logs 2>/dev/null || true
    ok "Data restored"
fi

# ── Step 7: Start containers ────────────────────────────
echo ""
info "Starting containers..."
sudo docker compose -f "$COMPOSE_FILE" up -d
ok "Containers started"

# ── Step 8: Verify ──────────────────────────────────────
echo ""
info "Verifying..."
sleep 3
if sudo docker compose -f "$COMPOSE_FILE" ps 2>/dev/null | grep -q "Up"; then
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -k https://localhost 2>/dev/null || echo "000")
    echo "   HTTP status: $HTTP_CODE"
    if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "302" ] || [ "$HTTP_CODE" = "000" ]; then
        echo -e "${GREEN}✅ Rollback successful${NC}"
    else
        warn "Containers running but HTTP status is $HTTP_CODE"
    fi
else
    fail "Containers failed to start"
fi

echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN} Rollback complete!${NC}"
echo -e "${GREEN}============================================${NC}"
