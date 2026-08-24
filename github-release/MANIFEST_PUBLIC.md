# Public Repository Manifest — exclusion rules

Patterns below are applied by `build_public_repo.sh` (rsync `--exclude-from`).
Anything listed is NEVER shipped to the public repo. Keep this file in sync
every time a new internal-only asset is added.

## Version control & environment
.git/
.gitignore
.*   <-- ALL dotfiles ignored in public (htaccess, dockerignore, env*, etc.)
.env
docker/.env
docs/   <-- INTERNAL DOCS: NEVER public (public gets curated copies from github-release/public-docs + client-docs-clean)

## Secrets & credentials
App_Data/
data/
dist_release_lic/
scripts/license_admin_templates/   <-- VENDOR-ONLY (license issuance tooling): NEVER public
scripts/license_admin_templates/vault/
scripts/license_admin_templates/vendor_vault/
app/Application/Http/Controllers/vendor_license_api.php   <-- VENDOR ONLY
resources/views/pages/license/vendor_view.php              <-- VENDOR ONLY
public/resources/frontend/js/modules/vendor_actions.js     <-- VENDOR ONLY
scripts/prepare-client-release.ps1                         <-- VENDOR ONLY
vault/
*.key
*.pem
*.pfx
*.p12
*.crt
*.jks
*.keystore
*.secret

## Logs & runtime state
logs/
log/
*.log
sessions/
opcache.ini

## Internal / vendor-only docs
AGENTS.md
DEVELOPMENT_GUIDELINES.md
docs/Agents/
docs/internal/
docs/Technical/
docs/issues/
docs/manifest.json
docs/INDEX.md
docs/DOCUMENT_MANAGEMENT_SPEC.md
docs/docker/
github-release/
app/**/*.md
config/**/*.md
scripts/**/*.md

## Ops tooling
docker/deploy/ IS SHIPPED (client backup/restore/rollback tooling — sanitizer scrubs infra names)
docker/README.md

## Tooling & archives
opencode/
.opencode/
dist/
node_modules/
*.tar.gz
*.zip
*.rar
*.7z
*.bak
*.tmp
*.swp

## Ops scripts that reference real infra
scripts/fix-monitoring-routes.sh
scripts/backup_vault.sh
scripts/generate_salt.php
opc_reset.php
_icons/

NOTE: `config/license_public.pem` (the PUBLIC verification key) is intentionally
RESTORED after the copy — it is public material required for license verification.