#!/usr/bin/env bash
# =============================================================================
# push_both.sh — push ALL AccessPilot repos to GitHub in one shot
#
#   PUBLIC   github.com/engr-rakib/accesspilot            docs + installer only
#   DIST     github.com/engr-rakib/accesspilot-dist       sanitized product code
#            (private — installer clones this)            (built by build_public_repo.sh)
#   INTERNAL github.com/engr-rakib/accesspilot-internal   vendor full backup
#
# Usage:
#   bash push_both.sh [-m "message"] [--rebuild]      # rebuild dist from source
#   bash push_both.sh --site-only                     # docs changes only
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

TOKEN="${ACCESSPILOT_GH_TOKEN:-}"
[ -z "$TOKEN" ] && [ -f "$HOME/.accesspilot_gh_token" ] && TOKEN="$(tr -d '[:space:]' < "$HOME/.accesspilot_gh_token")"
[ -n "$TOKEN" ] || { echo "[push] ERROR: no token" >&2; exit 1; }
URL="https://${TOKEN}@github.com/"

echo "==> AccessPilot triple push — $(date '+%Y-%m-%d %H:%M:%S')"

push_repo() { # dir url label msg
  cd "$1"; git config user.name >/dev/null 2>&1 || git config user.name "engr-rakib"
  git config user.email >/dev/null 2>&1 || git config user.email "rakibcse47@gmail.com"
  git fetch -q "$2" main 2>/dev/null && git reset --soft FETCH_HEAD || true
  git add -A
  if [ -n "$(git status --porcelain)" ]; then
    git commit -qm "${4:-Update (automated push)}"
    echo "[$3] committed: $(git log --oneline -1)"
  else
    echo "[$3] nothing to commit"
  fi
  git push -q "$2" main:main && echo "[$3] pushed"
  git remote remove origin 2>/dev/null || true
}

# 1. DIST (sanitized product code — installer clone target)
if [ "$SITE_ONLY" = "0" ]; then
  [ "$REBUILD" = "1" ] && bash "$HERE/build_public_repo.sh" >/dev/null && echo "[dist] rebuilt from source"
  echo "--- [DIST] ${OWNER}/accesspilot-dist (private) ---"
  push_repo "$DIST_DIR" "${URL}${OWNER}/accesspilot-dist.git" "dist" "${MSG:-Product update (dist)}"

  # 2. INTERNAL (vendor backup)
  echo "--- [INTERNAL] ${OWNER}/accesspilot-internal (private) ---"
  push_repo "$INTERNAL_DIR" "${URL}${OWNER}/accesspilot-internal.git" "internal" "${MSG:-Project backup (internal)}"
fi

# 3. PUBLIC site (docs + installer)
echo "--- [PUBLIC] ${OWNER}/accesspilot ---"
bash "$HERE/build_public_site.sh" >/dev/null
push_repo "$SITE_DIR" "${URL}${OWNER}/accesspilot.git" "public" "${MSG:-Docs update (public)}"

echo
echo "==> DONE"
echo "    PUBLIC:   https://github.com/${OWNER}/accesspilot"
echo "    DIST:     https://github.com/${OWNER}/accesspilot-dist (private)"
echo "    INTERNAL: https://github.com/${OWNER}/accesspilot-internal (private)"
