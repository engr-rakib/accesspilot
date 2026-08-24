#!/usr/bin/env bash
# =============================================================================
# sanitize_configs.sh — strip real secrets from a copy of the AccessPilot tree.
# Usage:
#   bash sanitize_configs.sh /path/to/repo                  # full scrub (PUBLIC)
#   bash sanitize_configs.sh /path/to/repo --config-only    # configs only (INTERNAL)
# Idempotent: uses templates from github-release/templates.
# =============================================================================
set -euo pipefail

ROOT="${1:?Usage: $0 /path/to/repo [--config-only]}"
MODE="${2:-full}"
TPL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../templates" && pwd)"

echo "[sanitize] repo root: $ROOT  (mode: $MODE)"

# 1. Configured app.php -> placeholder template
if [ -f "$TPL_DIR/config_app_public.php" ]; then
  cp "$TPL_DIR/config_app_public.php" "$ROOT/config/app.php"
  echo "[sanitize] config/app.php <- template"
fi

# 2. .env.example (no real env ever shipped)

# 3. license.php -> generic policy (strip contact/identity)
if [ -f "$TPL_DIR/license_public.php" ]; then
  cp "$TPL_DIR/license_public.php" "$ROOT/config/license.php"
  echo "[sanitize] config/license.php <- generic template"
fi

# 4. Config token scrub (all config *.php) — low-signal patterns from PLAN D.
sed -i \
  -e 's/b7e6d48e9c10a3b5f2d8c4a7e8b9f0d1c3a5b7e8d9f0a1b2c3d4e5f6a7b8c9d0/CHANGE_ME_64_HEX_ENC_KEY/g' \
  -e 's/Welcome123!/CHANGE_ME_DEFAULT_PASSWORD/g' \
  -e 's/accesspilot@123/CHANGE_ME_ADMIN_PASSWORD/g' \
  -e 's/40fabf5491b9c596542a7bd1ccfe9b46:0a844ad32f2bb6b876434815271d95e9/CHANGE_ME_DEPLOYMENT_ID/g' \
  -e 's/wgbd\.com/example.com/g' \
  -e 's/whildc\.com/example.com/g' \
  -e 's/DC{whildc/Walton/example/g' \
  -e 's|/data/secure|VAR_SECURE_BASE_PATH|g' \
  -e 's|C:\\\\inetpub\\\\Desk_secure_files|VAR_SECURE_BASE_PATH|g' \
  -e 's|C:\\inetpub\\Desk_secure_files|VAR_SECURE_BASE_PATH|g' \
  "$ROOT/config/"*.php 2>/dev/null || true

if [ "$MODE" = "config-only" ]; then
  echo "[sanitize] config-only mode — DONE."
  exit 0
fi

# 5. FULL-TREE scrub (PUBLIC only) — replace every real internal token.
#    Order matters: combined tokens first, then bare words.
find "$ROOT" -type f ! -name '*.pem' ! -name '*.jar' ! -name '*.png' \
     ! -name '*.jpg' ! -name '*.jpeg' ! -name '*.gif' ! -name '*.ico' \
     ! -name '*.svg' ! -name '*.woff*' ! -name '*.ttf' -print0 \
  | xargs -0 sed -i \
     -e 's/dc01\.whildc\.com/dc01.example.local/g' \
     -e 's/dc-ad1\.whildc\.com/dc-ad1.example.local/g' \
     -e 's/DC-AD1\.WHILDC\.COM/dc-ad1.example.local/g' \
     -e 's/dcpri4\.wgbd\.com/dcpri4.example.local/g' \
     -e 's/whrmsapi\.waltonbd\.com/hrms.example.com/g' \
     -e 's/waltonbd\.com/example.com/g' \
     -e 's/Walton Group Ltd\./ExampleOrg/g' \
     -e 's/Walton Group/ExampleOrg/g' \
     -e 's/wgbd\.com/example.com/g' \
     -e 's/whildc\.com/example.com/g' \
     -e 's/WGBD/EXAMPLE/g' \
     -e 's/WHILDC/EXAMPLE/g' \
     -e 's/wgbd/example/g' \
     -e 's/whildc/example/g' 2>/dev/null || true

echo "[sanitize] full-tree scrub DONE."
echo "Next: run the secret gate from PLAN.md Section D."