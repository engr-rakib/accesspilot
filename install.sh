#!/usr/bin/env bash
# =============================================================================
# AccessPilot — Linux/Docker installer (token-gated, live-system install)
#
# Usage:
#   ACCESSPILOT_INSTALL_TOKEN=<your-token> bash install.sh
#
# The token is issued by Trendpilot (vendor) and grants install access only.
# The installer clones the product onto THIS machine, removes the clone and
# starts the portal. The code is never distributed as a downloadable archive.
# =============================================================================
set -euo pipefail

ACCESSPILOT_DIST_REPO="${ACCESSPILOT_DIST_REPO:-engr-rakib/accesspilot-dist}"
ACCESSPILOT_DEST="${ACCESSPILOT_DEST:-/opt/accesspilot}"
ACCESSPILOT_SECURE="${ACCESSPILOT_SECURE:-/data/secure}"
ACCESSPILOT_LOGS="${ACCESSPILOT_LOGS:-/data/logs}"

fail() { echo "[accesspilot] ERROR: $*" >&2; exit 1; }

echo "[accesspilot] Live-system installer"

# --- 1. Prerequisites ---
command -v docker >/dev/null 2>&1 || fail "Docker is required (https://docs.docker.com/engine/install/)"
command -v git    >/dev/null 2>&1 || fail "git is required"
command -v rsync  >/dev/null 2>&1 || fail "rsync is required"

# --- 2. Install token ---
TOKEN="${ACCESSPILOT_INSTALL_TOKEN:-}"
if [ -z "$TOKEN" ] && [ -t 0 ]; then
  printf "[accesspilot] Enter your install token: "; read -r TOKEN
fi
[ -n "$TOKEN" ] || fail "no install token. Get one from Trendpilot (rakibcse47@gmail.com)"

# --- 3. Clone the private distribution (live system only) ---
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
echo "[accesspilot] Downloading product onto this machine..."
git clone -q --depth 1 "https://${TOKEN}@github.com/${ACCESSPILOT_DIST_REPO}.git" "$TMP/src" \
  || fail "clone failed — check your install token with the vendor"

# --- 4. Deploy ---
mkdir -p "$ACCESSPILOT_DEST" "$ACCESSPILOT_SECURE" "$ACCESSPILOT_LOGS"
rsync -a --exclude='.git' "$TMP/src/" "$ACCESSPILOT_DEST/"
echo "[accesspilot] Deployed to $ACCESSPILOT_DEST (source clone removed)"

# --- 5. Start ---
cd "$ACCESSPILOT_DEST/docker"
docker compose up -d --build

ip=$(hostname -I 2>/dev/null | awk '{print $1}')
echo
echo "[accesspilot] DONE — starting at https://${ip:-localhost}/ (first boot builds the image: 5-10 min)"
echo "[accesspilot] Portal runs in READ-ONLY EVALUATION mode until a license is applied."
echo "[accesspilot] Activate: License Center -> Apply Certificate (contact Trendpilot)."
