# Document Index

> **Quick orientation for any engineer or AI.** Read in order below.

## Recommended Reading Order

1. `Agents/project_blueprint/current_codebase_blueprint.md` — Directory and file-level overview
2. `Agents/project_blueprint/current_architecture.md` — High-level system layering and request flow
3. `Agents/project_blueprint/application_a_to_z.md` — Full operational lifecycle (Client → Server → Client)
4. `internal/application/external-storage-mapping.md` — Vault, logs, Docker volumes, IIS storage architecture
5. `internal/application/logs_organization/LOG_ORGANIZATION.md` — Log path mapping, action names, message format
6. `Technical/AD/multi-ad/README.md` — Multi-AD domain management
7. `internal/application/security/VENDOR_SECURITY_AND_DEPLOYMENT.md` — Security architecture
8. `internal/license/DEPLOYMENT_ORDER.md` — Deployment order & infrastructure
9. `Technical/server/SERVER_HARDENING_GUIDE.md` — Firewall, Docker, PHP, LDAP/AD security hardening
10. `Technical/server/PORT_MAPPING_HTTPS_GUIDE.md` — Port mapping, HTTPS, SSL, how to change ports
11. `Technical/server/MIGRATION_GUIDE.md` — Full server migration, image export/import, data transfer

## Core System Map

| Component | Path | Role |
|-----------|------|------|
| Web Entry | `public/index.php` | Delegates to `admin_portal.php` |
| API Gateway | `public/api/index.php` | Unified endpoint for all AJAX actions |
| Shell | `resources/views/layouts/master.php` | 3-pane WhatsApp-inspired UI |
| Page Router | `app/Application/Routing/page_registry.php` | Maps `?page=` to views |
| Automation Driver | `app/Infrastructure/PowerShell/powershell_runner.php` | Executes AD scripts |

## Current Architecture Summary

- **UI:** 3-pane administrative shell with 52px fixed header and Bold Purple branding
- **Typography:** All font sizes centralized in `config/ui.php → typography.font_sizes` (16 tokens: xs→xxl, table, info, feedback, h1→h6). Injected as CSS custom properties (`--font-*`) in `master.php:root`. HTML base font-size = 15px, body line-height = 1.6
- **Backend:** PHP 8.x on IIS/Linux, stateless application logic, data persisted in external JSON vault
- **Transition Model:** Hybrid SSR + SPA for smooth, animated page updates
- **Security:** Session-guarded with 15m idle timeout (2h remember-me) and RSA-2048 signed license enforcement
- **Multi-AD:** Domains stored in `Desk_secure_files/ldap/domains.json` with per-domain secrets. AD domain name extracted from `base_dn` (e.g., `DC=wgbd,DC=COM` → `wgbd.com`) for display and log paths. Active domain persisted as both `active_domain` (key) and `active_domain_ad_name` in `config.json`

## Rules for Future Work

- ALWAYS follow the UI consistency rules in `DEVELOPMENT_GUIDELINES.md`
- Prefer `resolve_secure_path()` for vault files and `resolved_log_path()` for audit logs
- Trigger UI re-initialization on `spaContentUpdated` JS events
- AD domain name extraction: `preg_match_all('/DC\s*=\s*([^,]+)/i', $baseDn, $parts)` → `strtolower(implode('.', $parts[1]))` — used for display, log directories, and dashboard domain badge
- Log paths use `active_domain_ad_name` (e.g., `wgbd.com`) not config key (`default`). Both PHP (`ldap_write_script_log()`) and 15 PowerShell scripts consume this
- Dashboard reader falls back to key-based directory if AD-named dir doesn't exist
- OPcache clearing: Visit `https://accesspilot.wgbd.com/oc.php` when PHP file changes don't reflect
