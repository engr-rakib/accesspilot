# Phase Checklist — Multi-AD Implementation

## Phase 1: Config Storage — Domain Registry

**Files**: `app/Ldap/Config/ldap_config_repository.php`

| Task | Status |
|------|--------|
| Add `ldap_domains_file_path()` | ✅ Done |
| Add `ldap_get_domains()` / `ldap_write_domains()` | ✅ Done |
| Add `ldap_get_domain(key)` | ✅ Done |
| Add `ldap_upsert_domain()` with license limit check | ✅ Done |
| Add `ldap_delete_domain()` (blocks active domain deletion) | ✅ Done |
| Add `ldap_active_domain_key()` / `ldap_set_active_domain()` | ✅ Done |
| Add per-domain secret: `ldap_domain_secret_path()`, `ldap_read_domain_secret()`, `ldap_write_domain_secret()` | ✅ Done |
| Update `ldap_read_config()` to return active domain settings | ✅ Done |
| Update `ldap_read_bind_password()` / `ldap_write_bind_password()` / `ldap_has_bind_password()` to use active domain secret | ✅ Done |
| Add auto-migration: `ldap_migrate_legacy_config()` → seeds "default" domain on first load | ✅ Done |

## Phase 2: Connection — Domain-Aware LDAP Connect

**Files**: `app/Ldap/Connection/ldap_connection_factory.php`

| Task | Status |
|------|--------|
| Verify `ldap_connect_and_bind()` (calls `ldap_read_config()` + `ldap_read_bind_password()` — both now domain-aware) | ✅ No changes needed |
| Verify `ldap_test_connection()` (same pattern) | ✅ No changes needed |
| Verify `ldap_run_with_connection()` in ldap_helpers.php (calls `ldap_connect_and_bind()`) | ✅ No changes needed |

## Phase 3: Log Path — Domain Subdirectory

**Files**: `app/Ldap/Support/ldap_helpers.php`, `app/Infrastructure/Logging/dashboard_log_reader.php`, PowerShell scripts

| Task | Status |
|------|--------|
| Update `ldap_write_script_log()` in ldap_helpers.php — prepend `{active_domain}` to log path | ✅ Done |
| Update `dashboard_log_base_dir()` in dashboard_log_reader.php — prepend `{active_domain}` | ✅ Done |
| Update `Write-Log` in `ldap_ad_helpers.ps1` — read active_domain from shared_config.json | ✅ Done |
| Update other `.ps1` scripts with hardcoded log paths | ✅ Done |

## Phase 4: Shared Config Sync — Active Domain for PowerShell

**Files**: `config/shared_config.json`, `app/Application/Http/Controllers/system_config.php`

| Task | Status |
|------|--------|
| Add `active_domain` field to `shared_config.json` schema | ✅ Done |
| Add `sync_active_domain_to_shared_config()` in domain_api.php | ✅ Done |
| Call sync on domain switch and system config save | ✅ Done |

## Phase 5: UI — Domain Switcher in Assistant Pane

**Files**: `resources/views/layouts/master.php`

| Task | Status |
|------|--------|
| Add domain badge under "Assistant" title | ✅ Done |
| Add dropdown with list of configured domains | ✅ Done |
| CSS styling for badge (key pill, active indicator) | ✅ Done |
| JS: AJAX domain switch + page reload | ✅ Done |
| Show domain limit info (remaining count) | ✅ Done |

## Phase 6: System Config — Domain CRUD Section

**Files**: `resources/views/pages/tools/system_config_view.php`, `system_config_actions.js`, `system_config.css`

| Task | Status |
|------|--------|
| Build domain list table with key, label, host, IP, status, actions columns | ✅ Done |
| "Add Domain" form (key, label, host, IP, port, TLS, base_dn, bind_dn, password) | ✅ Done |
| "Edit Domain" — pre-filled form with password stored badge | ✅ Done |
| "Delete Domain" — with confirmation (blocks active domain) | ✅ Done |
| "Switch Domain" button for non-active domains | ✅ Done |
| Domain limit badge + warning banner when limit reached | ✅ Done |
| Test Connection button (DNS lookup + LDAP bind test) | ✅ Done |
| Resolve Host / auto-resolve IP on hostname blur | ✅ Done |
| Health guide status cards show live backend/extension/bind info | ✅ Done |

## Phase 7: API — Domain Endpoints

**Files**: `public/api/index.php`, `app/Application/Http/Controllers/domain_api.php`

| Task | Status |
|------|--------|
| `list_domains` — returns all domains + active_key + license limit info + bind_password_stored | ✅ Done |
| `switch_domain` — POST, sets active domain, syncs shared_config | ✅ Done |
| `add_domain` — POST, validates license limit | ✅ Done |
| `update_domain` — POST, updates domain settings (partial merge) | ✅ Done |
| `delete_domain` — POST, blocks active domain | ✅ Done |
| `test_connection` — POST, DNS resolve + LDAP connect/bind | ✅ Done |
| `resolve_host` — POST, DNS hostname resolution | ✅ Done |
| CSRF protection + auth checks | ✅ Done |

## Phase 8: License Bundling — Domain Count Enforcement

**Files**: Multiple (see below)

| Task | Status |
|------|--------|
| `license_service.php` — `license_verify_signature()` conditionally appends `max_domains` | ✅ Done |
| `license_service.php` — `license_get_status()` exposes `max_domains`, `domains_used`, `domains_remaining` | ✅ Done |
| `license_service.php` — `license_validate_certificate_payload()` normalizes `max_domains` | ✅ Done |
| `ldap_config_repository.php` — `ldap_upsert_domain()` enforces `max_domains` limit | ✅ Done |
| `ldap_config_repository.php` — `ldap_domain_limit_message()` helper | ✅ Done |
| `license_status_view.php` — "Domain Entitlement" row in certificate card | ✅ Done |
| `license_status_view.php` — JS dynamic update for domain entitlement | ✅ Done |
| `generator.php` — accepts `--max-domains`, adds to signed payload | ✅ Done |
| `Issue-License.ps1` — prompts for max domains | ✅ Done |
| `Renew-License.ps1` — prompts for max domains | ✅ Done |
| `system_config_view.php` — domain limit badge (deferred — no CRUD section yet) | ⬜ Deferred |
| Domain API — return license limit in list_domains (deferred — endpoint not built) | ⬜ Deferred |

## Legend

- ✅ Done — Implemented and committed
- ⬜ Pending — Not yet started
- ⬜ Deferred — Blocked by another phase
