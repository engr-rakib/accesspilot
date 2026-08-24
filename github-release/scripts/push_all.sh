#!/usr/bin/env bash
# =============================================================================
# push_all.sh — Push ALL AccessPilot repos to GitHub in one command
#
# Usage:
#   bash push_all.sh [-m "message"] [--rebuild]       # push all three repos
#   bash push_all.sh --site-only                      # docs changes only (skip dist)
#
# Auth: $ACCESSPILOT_GH_TOKEN or ~/.accesspilot_gh_token
# =============================================================================
set -euo pipefail

OWNER="${ACCESSPILOT_GH_OWNER:-engr-rakib}"
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$HERE/.." && pwd)"
SITE_DIR="${SITE_DIR:-/opt/git-accesspilot/site}"
DIST_DIR="${DIST_DIR:-/opt/git-accesspilot/accesspilot}"
INTERNAL_DIR="$(cd "$HERE/../.." && pwd)"

SITE_ONLY=0; REBUILD=0; MSG=""
while [ $# -gt 0 ]; do
  case "$1" in
    --site-only) SITE_ONLY=1 ;;
    --rebuild)   REBUILD=1 ;;
    -m)          shift; MSG="${1:-}" ;;
    *) echo "unknown arg: $1" >&2; exit 1 ;;
  esac
  shift
done

# Resolve token: env var first, then keyring file
TOKEN="${ACCESSPILOT_GH_TOKEN:-}"
[ -z "$TOKEN" ] && [ -f "$HOME/.accesspilot_gh_token" ] && TOKEN="$(tr -d '[:space:]' < "$HOME/.accesspilot_gh_token")"
[ -n "$TOKEN" ] || { echo "[push] ERROR: no token found" >&2; exit 1; }
URL="https://${TOKEN}@github.com/"

echo "==> AccessPilot multi-repo push — $(date '+%Y-%m-%d %H:%M:%S')"

# ---- push one repo -------------------------------------------------------
push_repo() {
  local dir="$1" label="$2"
  cd "$dir"
  git config user.name >/dev/null 2>&1 || git config user.name "engr-rakib"
  git config user.email >/dev/null 2>&1 || git config user.email "rakibcse47@gmail.com"

  # Pull latest from remote main (if exists) so we don't diverge
  git fetch -q "$URL${OWNER}/$(basename "$dir").git" main 2>/dev/null && \
    git reset --soft FETCH_HEAD || true

  git add -A
  if [ -n "$(git status --porcelain)" ]; then
    git commit -qm "${MSG:-Update (automated push)}"
    echo "[${label}] committed: $(git log --oneline -1)"
  else
    echo "[${label}] nothing to commit"
  fi
  git push -q "$URL${OWNER}/$(basename "$dir").git" main:main && echo "[${label}] pushed"
  git remote remove origin 2>/dev/null || true
}

# ---- 1. DIST repo --------------------------------------------------------
if [ "$SITE_ONLY" = "0" ]; then
  [ "$REBUILD" = "1" ] && bash "$HERE/build_public_repo.sh" >/dev/null && echo "[dist] rebuilt from source"
  echo "--- [DIST] ${OWNER}/accesspilot-dist (private) ---"
  push_repo "$DIST_DIR" "dist"
fi

# ---- 2. INTERNAL repo ----------------------------------------------------
echo "--- [INTERNAL] ${OWNER}/accesspilot-internal (private) ---"
push_repo "$INTERNAL_DIR" "internal"

# ---- 3. PUBLIC site ------------------------------------------------------
echo "--- [PUBLIC] ${OWNER}/accesspilot ---"
bash "$HERE/build_public_site.sh" >/dev/null
push_repo "$SITE_DIR" "public"

echo
echo "==> DONE"
echo "    PUBLIC:   https://github.com/${OWNER}/accesspilot"
echo "    DIST:     https://github.com/${OWNER}/accesspilot-dist (private)"
echo "    INTERNAL: https://github.com/${OWNER}/accesspilot-internal (private)"
