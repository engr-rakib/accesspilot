#!/usr/bin/env bash
# =============================================================================
# build_public_repo.sh — build the PUBLIC "accesspilot" repo.
# Produces a clean repo at OUT_ROOT/accesspilot with:
#   - all application source
#   - sanitized configs (no secrets)
#   - client-facing docs + generated product docs
#   - git initialized (no remote push; see PLAN.md Section 9)
# =============================================================================
set -euo pipefail

SRC="/app/accesspilot"
OUT_ROOT="${OUT_ROOT:-/opt/git-accesspilot}"
OUT="$OUT_ROOT/accesspilot"
TPL="$(cd "$(dirname "${BASH_SOURCE[0]}")/../templates" && pwd)"
DOCS_OUT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../public-docs" && pwd)"
CLIENT_DOCS="$(cd "$(dirname "${BASH_SOURCE[0]}")/../client-docs-clean" && pwd)"

echo "==> Building public repo at $OUT"

rm -rf "$OUT"
mkdir -p "$OUT"

# --- 1. Copy application source (exclusions below) ---------------------------
rsync -a --exclude-from="$DOCS_OUT/../MANIFEST_PUBLIC.md" "$SRC/" "$OUT/" \
  --exclude='.git' \
  --exclude='.*' \
  --exclude='data' \
  --exclude='App_Data' \
  --exclude='logs' \
  --exclude='opencode' \
  --exclude='.opencode' \
  --exclude='docs' \
  --exclude='AGENTS.md' \
  --exclude='DEVELOPMENT_GUIDELINES.md' \
  --exclude='scripts/license_admin_templates' \
  --exclude='scripts/license_admin_templates/vault' \
  --exclude='scripts/license_admin_templates/vendor_vault' \
  --exclude='.env' \
  --exclude='docker/.env' \
  --exclude='dist_release_lic' \
  --exclude='opc_reset.php' \
  --exclude='_run_elevated.ps1' \
  --exclude='*.key' \
  --exclude='*.pem' \
  --exclude='*.pfx' \
  --exclude='*.crt' \
  --exclude='*.tar.gz' \
  --exclude='*.zip' \
  --exclude='*.bak'

# Allow the PUBLIC license verification key back in (needed at runtime).
mkdir -p "$OUT/config"
if [ -f "$SRC/config/license_public.pem" ]; then
  cp "$SRC/config/license_public.pem" "$OUT/config/license_public.pem"
  echo "==> Public key restored: config/license_public.pem"
fi

# --- 2. Client-facing docs (BEFORE scrub so they get cleaned too) -------------
mkdir -p "$OUT/docs"
# client docs = the SCRUBBED staging copy (backend terms removed per FEATURE_DOCUMENT_RULES)
rm -rf "$OUT/docs/client"
cp -r "$CLIENT_DOCS" "$OUT/docs/client"
cp -r "$DOCS_OUT/." "$OUT/docs/"
# Trim any dev-readme that leaks internals
rm -f "$OUT/docs/README.md" 2>/dev/null || true

# Root-level product docs
cp "$DOCS_OUT/README.md"        "$OUT/README.md"
cp "$DOCS_OUT/APPLICATION_BOOK.md" "$OUT/APPLICATION_BOOK.md"
cp "$DOCS_OUT/LIFECYCLE.md"     "$OUT/LIFECYCLE.md"
cp "$DOCS_OUT/FEATURES.md"      "$OUT/FEATURES.md"
cp "$DOCS_OUT/SECURITY.md"      "$OUT/SECURITY.md"
cp "$DOCS_OUT/ARCHITECTURE.md"  "$OUT/ARCHITECTURE.md"
cp "$TPL/LICENSE"               "$OUT/LICENSE"
cp "$TPL/install.sh"            "$OUT/install.sh"
cp "$TPL/install.ps1"           "$OUT/install.ps1"
chmod +x "$OUT/install.sh" 2>/dev/null || true

# --- 3. Sanitize secrets + full-tree scrub ------------------------------------
bash "$(dirname "${BASH_SOURCE[0]}")/sanitize_configs.sh" "$OUT" full

# --- 3b. Strip the vendor license console from the public tree ----------------
python3 "$(dirname "${BASH_SOURCE[0]}")/strip_vendor_from_public.py" "$OUT"

# --- 4. Git init ---------------------------------------------------------------
cd "$OUT"
if [ ! -d .git ]; then
  git init -b main >/dev/null
  git config user.name "accesspilot-release"
  git config user.email "release@accesspilot.local"
fi
printf "\n[DONE] Public repo staged at %s\n" "$OUT"
echo "Next: (1) MANUAL REVIEW of 'git status', (2) run the secret gate (PLAN.md s9), (3) commit + push."