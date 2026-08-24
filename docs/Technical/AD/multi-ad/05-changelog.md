# Changelog — Multi-AD Implementation

## 2026-06-07

### Phase 8: License Bundling (Complete)

- **`license_service.php`** — `license_verify_signature()` now conditionally appends `max_domains` to signing string (BC-safe — only signed when present). `license_get_status()` exposes `max_domains`, `domains_used`, `domains_remaining`. `license_validate_certificate_payload()` normalizes `max_domains` (default 1).
- **`generator.php`** — Accepts `--max-domains` CLI option. Adds `max_domains` to payload and signing string conditionally.
- **`Issue-License.ps1`** — Prompts `"Enter max domains (0=unlimited, or 1,2,3,5; press Enter for default 1)"`, passes to generator.
- **`Renew-License.ps1`** — Same prompt added.
- **`license_status_view.php`** — Certificate card now has "Domain Entitlement" row showing `used / max used (remaining)` or `Unlimited (N configured)`. JS `applyLicenseState()` dynamically updates it.
- **`ldap_config_repository.php`** — `ldap_upsert_domain()` enforces `max_domains` before adding new domains. `ldap_domain_limit_message()` helper added.

### Phase 1: Config Storage — Domain Registry (Complete)

- **`ldap_config_repository.php`** — Complete domain CRUD: `ldap_domains_file_path()`, `ldap_get_domains()`, `ldap_write_domains()`, `ldap_get_domain(key)`, `ldap_upsert_domain()`, `ldap_delete_domain()`.
- `ldap_read_config()` now merges base `config.json` with active domain's settings from `domains.json`. Active domain takes priority.
- Per-domain secrets: `ldap_domain_secret_path()`, `ldap_read_domain_secret()`, `ldap_write_domain_secret()`.
- `ldap_read_bind_password()` / `ldap_write_bind_password()` / `ldap_has_bind_password()` now use active domain secret, fall back to legacy `bind_secret.json`.
- Auto-migration (`ldap_migrate_legacy_config()`) runs on first load — creates "default" domain from existing `config.json` + migrates bind password.

### Phase 2: Connection (Complete)

- No code changes needed. `ldap_connect_and_bind()` already calls `ldap_read_config()` + `ldap_read_bind_password()`, both now domain-aware.

### Documentation (This Directory)

- `plan/multi-ad/README.md` — Project overview
- `plan/multi-ad/01-architecture.md` — Architecture decisions and data flow
- `plan/multi-ad/02-implementation-plan.md` — Detailed 8-phase implementation plan
- `plan/multi-ad/03-phase-checklist.md` — Phase-by-phase progress tracking
- `plan/multi-ad/04-migration-analysis.md` — Migration considerations
- `plan/multi-ad/05-changelog.md` — This file

### Phase 3: Log Path — Domain Subdirectory (PHP Complete)

- **`ldap_helpers.php`** — `ldap_write_script_log()` now prepends `{active_domain}/` to log path using `ldap_active_domain_key()`.
- **`dashboard_log_reader.php`** — `dashboard_log_base_dir()` prepends active domain to log directory.
- All 29 PowerShell scripts read `active_domain` from `shared_config.json` and write logs to domain subdirectories.

### Phase 4: Shared Config Sync (Complete)

- **`domain_api.php`** — Added `sync_active_domain_to_shared_config()` — writes `active_domain` to `config/app.php` and `config/shared_config.json`. Called on domain switch.
- **`system_config.php`** — `sync_shared_config()` now accepts and writes `active_domain` key to both `config/app.php` and `config/shared_config.json`.

### Phase 5: UI — Domain Switcher in Assistant Pane (Complete)

- **`master.php`** — Domain badge (green dot + key pill + "ACTIVE" label) under Assistant title. Dropdown with all domains, click-to-switch with backdrop, domain limit footer.

### Phase 6: System Config — Domain CRUD Section (Complete)

- **`system_config_view.php`** — Domain table (Key, Label, Host, IP, Status, Actions). Inline add/edit form with all fields (key, label, host, IP, port, TLS, base DN, user search base, bind DN, bind password). Test Connection button, Resolve Host button + auto-resolve on blur. Delete with confirmation (blocks active domain). Switch button for non-active domains. Domain limit badge + warning.
- **`system_config_actions.js`** — Status cards (`ldap_card_extension`, `ldap_card_last_test`, `ldap_card_backend`) now populated instantly from config API via `updateStatusCardsFromConfig()`. Diagnostics sessionStorage caching for instant health guide display.
- **`system_config.css`** — Added `.sys-health-metric` for metrics alignment. Brand pills for LDAP/PS/Auto backend badges.

### Phase 7: API — Domain Endpoints (Complete)

- **`domain_api.php`** — Full 321-line controller: `list_domains` (with `bind_password_stored` flag), `switch_domain`, `add_domain`, `update_domain` (partial merge), `delete_domain`, `test_connection` (DNS + LDAP bind), `resolve_host`. Auth + RBAC checks. Activity logging.
- **`api/index.php`** — `'domain_api' => 'domain_api.php'` endpoint registered.

## Notes

| # | Item | Impact |
|---|------|--------|
| 1 | `ldap_active_domain_key()` reads from `config.json`, not separate `active_domain.txt` | Works but differs from initial plan |
| 2 | `ldap_set_active_domain()` writes entire `config.json` | Minor concurrent-write risk on rapid switching |
