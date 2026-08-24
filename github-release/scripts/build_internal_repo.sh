#!/usr/bin/env bash
# =============================================================================
# build_internal_repo.sh — build the PRIVATE "accesspilot-internal" repo.
# Full project tree + full docs + agent guidelines. Real secrets STILL excluded.
# =============================================================================
set -euo pipefail

SRC="/app/accesspilot"
OUT_ROOT="${OUT_ROOT:-/opt/git-accesspilot}"
OUT="$OUT_ROOT/accesspilot-internal"

echo "==> Building internal repo at $OUT"

rm -rf "$OUT"
mkdir -p "$OUT"

rsync -a "$SRC/" "$OUT/" \
  --exclude='.git' \
  --exclude='data' \
  --exclude='App_Data' \
  --exclude='logs' \
  --exclude='opencode' \
  --exclude='.opencode' \
  --exclude='.env' \
  --exclude='docker/.env' \
  --exclude='dist_release_lic' \
  --exclude='scripts/license_admin_templates/vault' \
  --exclude='scripts/license_admin_templates/vendor_vault' \
  --exclude='*.key' \
  --exclude='*.pfx' \
  --exclude='*.p12' \
  --exclude='*.tar.gz' \
  --exclude='*.zip' \
  --exclude='*.bak'

# --- Secret sanitization (configs ONLY — internal docs keep real names) ---------
bash "$(dirname "${BASH_SOURCE[0]}")/sanitize_configs.sh" "$OUT" config-only

# Internal-only guidance
mkdir -p "$OUT/internal-release-notes"
cat > "$OUT/internal-release-notes/README.md" <<'EOF'
# Internal Release Notes
Placeholder baseline. Vendor team maintain release notes here for each version.
EOF

cd "$OUT"
if [ ! -d .git ]; then
  git init -b main >/dev/null
fi
printf "\n[DONE] Internal repo staged at %s\n" "$OUT"
echo "Next: manual review, secret gate, commit + push to the PRIVATE remote only."