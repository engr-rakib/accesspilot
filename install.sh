#!/usr/bin/env bash
# =============================================================================
# AccessPilot — Linux/Docker one-line installer
#   curl -fsSL https://github.com/<OWNER>/<REPO>/releases/latest/download/install.sh | bash
#
# Downloads the tagged source tarball, deploys to /opt/accesspilot,
# configures the vault/log paths, and starts Docker. The app boots in
# READ-ONLY EVALUATION mode until a license certificate is applied.
# =============================================================================
set -euo pipefail

# --- Edit these once if you self-host this script -----------------------------
ACCESSPILOT_GH_OWNER="ACCESSPILOT_GH_OWNER"
ACCESSPILOT_GH_REPO="ACCESSPILOT_GH_REPO"
ACCESSPILOT_REF="${ACCESSPILOT_REF:-latest}"          # or a tag like v7.43.0
ACCESSPILOT_DEST="${ACCESSPILOT_DEST:-/opt/accesspilot}"
ACCESSPILOT_SECURE="${ACCESSPILOT_SECURE:-/data/secure}"
ACCESSPILOT_LOGS="${ACCESSPILOT_LOGS:-/data/logs}"

GH="https://github.com/$ACCESSPILOT_GH_OWNER/$ACCESSPILOT_GH_REPO"
if [ "$ACCESSPILOT_REF" = "latest" ]; then
  BASE="$GH/releases/latest"
else
  BASE="$GH/releases/download/$ACCESSPILOT_REF"
fi

fail() { echo "[accesspilot] ERROR: $*" >&2; exit 1; }

echo "[accesspilot] Installing from $GH ($ACCESSPILOT_REF)"

# --- 1. Prerequisites ----------------------------------------------------------
command -v docker >/dev/null 2>&1 || fail "Docker is required (https://docs.docker.com/engine/install/)"
command -v curl  >/dev/null 2>&1 || fail "curl is required"
command -v tar   >/dev/null 2>&1 || fail "tar is required"

# --- 2. Fetch + verify release tarball ------------------------------------------
mkdir -p /tmp/accesspilot-install
cd /tmp/accesspilot-install
# Asset name is version-independent: releases/latest/download/accesspilot.tar.gz
curl -fsSL "$BASE/download/accesspilot.tar.gz" -o accesspilot.tar.gz \
  || fail "could not download release tarball"

curl -fsSL "$BASE/download/SHA256SUMS" -o SHA256SUMS 2>/dev/null && {
  grep -q "$(sha256sum accesspilot.tar.gz | awk '{print $1}')" SHA256SUMS \
    || fail "checksum mismatch — aborting"
  echo "[accesspilot] checksum OK"
}

# --- 3. Deploy -------------------------------------------------------------------
mkdir -p "$ACCESSPILOT_DEST"
tar -xzf accesspilot.tar.gz -C "$ACCESSPILOT_DEST"
mkdir -p "$ACCESSPILOT_SECURE" "$ACCESSPILOT_LOGS"

# --- 4. Environment ---------------------------------------------------------------
cd "$ACCESSPILOT_DEST"
if [ -f .env.example ]; then cp .env.example .env; fi
if [ -f docker/.env.example ]; then cp docker/.env.example docker/.env; fi
sed -i "s#^ACCESSPILOT_SECURE_BASE_PATH=.*#ACCESSPILOT_SECURE_BASE_PATH=$ACCESSPILOT_SECURE#" \
  docker/.env 2>/dev/null || true
sed -i "s#^ACCESSPILOT_LOG_BASE_PATH=.*#ACCESSPILOT_LOG_BASE_PATH=$ACCESSPILOT_LOGS#" \
  docker/.env 2>/dev/null || true

# --- 5. Start ------------------------------------------------------------------------
cd "$ACCESSPILOT_DEST/docker"
docker compose up -d --build

echo
ip=$(hostname -I 2>/dev/null | awk '{print $1}')
echo "[accesspilot] DONE — AccessPilot is starting at http://${ip:-localhost}/"
echo "[accesspilot] First log in uses the SEEDED ADMIN — change it immediately (SECURITY.md)."
echo "[accesspilot] Portal is in READ-ONLY EVALUATION mode until a license is applied."
echo "[accesspilot] To unlock operations: purchase a license, then License Center -> Apply Certificate."