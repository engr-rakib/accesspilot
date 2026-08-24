#!/usr/bin/env bash
# =============================================================================
# tag_release.sh — produce a tagged GitHub release for the PUBLIC repo
# (node_exporter-style release management).
#
# Usage:
#   TAG=v7.42.0 bash tag_release.sh          # normal
#   TAG=v7.42.1 bash tag_release.sh          # hotfix
#
# Builds release assets under OUT_ROOT/release-assets/:
#   accesspilot-<TAG>.tar.gz   (source = built public repo)
#   install.sh                 (Linux/Docker one-liner)
#   install.ps1                (Windows/IIS one-liner)
#   SHA256SUMS                 (checksums for all assets)
#
# Then creates + pushes the git tag to the PUBLIC remote.
# =============================================================================
set -euo pipefail

TAG="${TAG:?Usage: TAG=v7.42.0 bash tag_release.sh}"
OWNER="${ACCESSPILOT_GH_OWNER:?set in VERSIONING.md or export it}"
REPO="${ACCESSPILOT_GH_REPO:-accesspilot}"
PUBLIC_DIR="/opt/git-accesspilot/accesspilot"
OUT_ROOT="${OUT_ROOT:-/opt/git-accesspilot}"
ASSETS="$OUT_ROOT/release-assets"
TPL="$(cd "$(dirname "${BASH_SOURCE[0]}")/../templates" && pwd)"

echo "==> Building release $TAG for $OWNER/$REPO"
mkdir -p "$ASSETS"

# 0. Public repo must exist and be clean
if [ ! -d "$PUBLIC_DIR/.git" ]; then
  echo "!! Public repo not built. Run build_public_repo.sh first." >&2; exit 1
fi
cd "$PUBLIC_DIR"
if [ -n "$(git status --porcelain)" ]; then
  echo "!! Public repo has uncommitted changes — commit or stash before tagging." >&2; exit 1
fi

# 1. Version stamp (keep app version in sync)
if [ "$TAG" != "$(\git describe --tags --abbrev=0 2>/dev/null || echo v0)" ]; then
  echo "Tagging $PUBLIC_DIR"
fi
git tag -a "$TAG" -m "AccessPilot $TAG"

# 2. Source archives (version-INDEPENDENT names so latest/download/<name> works)
git archive --format=tar.gz -o "$ASSETS/accesspilot.tar.gz" "$TAG"
git archive --format=zip    -o "$ASSETS/accesspilot.zip" "$TAG"

# 3. Installers (public one-liners). Edit ONLY the header value lines so
#    runtime var references ($ACCESSPILOT_GH_OWNER) stay intact.
sed -e "s|^ACCESSPILOT_GH_OWNER=.*|ACCESSPILOT_GH_OWNER=\"$OWNER\"|" \
    -e "s|^ACCESSPILOT_GH_REPO=.*|ACCESSPILOT_GH_REPO=\"$REPO\"|" \
    "$TPL/install.sh" > "$ASSETS/install.sh"
# PowerShell template seeds use a quoted literal token:  $script:Owner = 'ACCESSPILOT_GH_OWNER'
sed -e "s|'ACCESSPILOT_GH_OWNER'|'$OWNER'|g" \
    -e "s|'ACCESSPILOT_GH_REPO'|'$REPO'|g" \
    "$TPL/install.ps1" > "$ASSETS/install.ps1"
chmod +x "$ASSETS/install.sh" 2>/dev/null || true

# 4. Checksums
cd "$ASSETS"
sha256sum accesspilot.tar.gz accesspilot.zip install.sh install.ps1 > SHA256SUMS

echo
echo "==> Release assets ready in $ASSETS:"
ls -1 "$ASSETS"
echo
echo "Next (manual, in GitHub):"
echo "  1. Push:  git push origin $TAG"
echo "  2. Create a Release from tag $TAG and attach all files in $ASSETS."
echo "  3. Write release notes from github-release/RELEASING.md template."