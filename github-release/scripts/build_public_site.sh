#!/usr/bin/env bash
# =============================================================================
# build_public_site.sh — stage the PUBLIC repo (docs + installer ONLY, no code)
# Output: /opt/git-accesspilot/site  → github.com/engr-rakib/accesspilot
# Code ships via private accesspilot-dist (see build_public_repo.sh + install.sh)
# =============================================================================
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$HERE/.." && pwd)"
SITE="${SITE_DIR:-/opt/git-accesspilot/site}"
TPL="$ROOT/templates"

# wipe staged content but PRESERVE .git (push history)
if [ -d "$SITE/.git" ]; then
  find "$SITE" -mindepth 1 -maxdepth 1 -not -name '.git' -exec rm -rf {} +
  cd "$SITE" && git checkout -q . 2>/dev/null || true
else
  rm -rf "$SITE"; mkdir -p "$SITE"
  git init -b main -q "$SITE"
  (cd "$SITE" && git config user.name "engr-rakib" && git config user.email "rakibcse47@gmail.com")
fi
mkdir -p "$SITE/docs"

# Curated product docs (same sources as before)
cp "$ROOT/public-docs/README.md"           "$SITE/README.md"
for f in APPLICATION_BOOK ARCHITECTURE FEATURES LIFECYCLE SECURITY; do
  cp "$ROOT/public-docs/$f.md" "$SITE/docs/$f.md"
done

# Client feature docs (scrubbed copies)
cp -r "$ROOT/client-docs-clean" "$SITE/docs/client"

# Installer + license + logo asset (README needs it)
cp "$TPL/install.sh"  "$SITE/install.sh";  chmod +x "$SITE/install.sh"
cp "$TPL/install.ps1" "$SITE/install.ps1"
cp "$TPL/LICENSE"     "$SITE/LICENSE"
mkdir -p "$SITE/assets"
cp /app/accesspilot/public/assets/images/logo_icon.png "$SITE/assets/logo_icon.png"

echo "[site] staged at $SITE"
