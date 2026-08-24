# Multi-AD Implementation Plan (Final)

## Architecture Decision

**Active Domain Switching** — Not per-operation routing. One active domain at a time, displayed in the Assistant pane. All operations target the active domain. Switching domains updates a config file + shared_config.json (for PowerShell).

## Implementation Status

All 8 phases are complete. See `03-phase-checklist.md` for detailed task status.
See `05-changelog.md` for the full change log.

### Actual File Changes (vs. planned below)

| Actual File | Role |
|-------------|------|
| `app/Ldap/Config/ldap_config_repository.php` | Domain CRUD, active domain, per-domain secrets |
| `app/Ldap/Connection/ldap_connection_factory.php` | No changes needed (domain-aware via repository) |
| `app/Application/Http/Controllers/domain_api.php` | All domain API endpoints (not a separate router) |
| `public/api/index.php` | `domain_api` endpoint registered |
| `resources/views/layouts/master.php` | Domain switcher badge/dropdown in Assistant pane |
| `resources/views/pages/tools/system_config_view.php` | Domain CRUD table + inline form + test/resolve |
| `public/resources/frontend/js/modules/system_config_actions.js` | Status cards, diagnostics caching |
| `public/resources/frontend/css/system_config.css` | Health metric alignment, brand pills |
| `app/Ldap/Support/ldap_helpers.php` | Domain-aware log path (PHP side) |
| `app/Infrastructure/Logging/dashboard_log_reader.php` | Domain-aware log path (PHP side) |
| `app/Application/Http/Controllers/system_config.php` | `sync_shared_config()` includes `active_domain` |
| `app/Domain/Licensing/license_service.php` | `max_domains` in license payload |
| `resources/views/pages/license/license_status_view.php` | Domain entitlement row |
| `scripts/php/generator.php` | `--max-domains` CLI flag |
| `scripts/license_admin_templates/Issue-License.ps1` | Max domains prompt |
| `scripts/license_admin_templates/Renew-License.ps1` | Max domains prompt |

---

## Phase 1: Config Storage — Domain Registry

### Files to Create/Modify

#### 1.1 `app/Ldap/Config/ldap_config_repository.php` — Add domain CRUD functions

After existing `ldap_read_config()` / `ldap_write_config()`, add:

```php
if (!function_exists('ldap_domains_file_path')) {
    function ldap_domains_file_path(): string {
        return ldap_config_dir() . DIRECTORY_SEPARATOR . 'domains.json';
    }
}
if (!function_exists('ldap_active_domain_file_path')) {
    function ldap_active_domain_file_path(): string {
        return ldap_config_dir() . DIRECTORY_SEPARATOR . 'active_domain.txt';
    }
}

// Read all configured domains
if (!function_exists('ldap_get_domains')) {
    function ldap_get_domains(): array {
        $path = ldap_domains_file_path();
        if (!file_exists($path) || !is_readable($path)) return [];
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }
}

// Write all domains
if (!function_exists('ldap_write_domains')) {
    function ldap_write_domains(array $domains): bool {
        ldap_config_dir();
        return file_put_contents(ldap_domains_file_path(),
            json_encode($domains, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
    }
}

// Get single domain by key
if (!function_exists('ldap_get_domain')) {
    function ldap_get_domain(string $key): ?array {
        foreach (ldap_get_domains() as $d) {
            if (($d['key'] ?? '') === $key) return $d;
        }
        return null;
    }
}

// Add or update a domain
if (!function_exists('ldap_upsert_domain')) {
    function ldap_upsert_domain(array $domain): bool {
        $domains = ldap_get_domains();
        $key = $domain['key'] ?? '';
        if ($key === '') return false;
        $found = false;
        foreach ($domains as &$d) {
            if (($d['key'] ?? '') === $key) { $d = $domain; $found = true; break; }
        }
        if (!$found) $domains[] = $domain;
        return ldap_write_domains($domains);
    }
}

// Delete a domain
if (!function_exists('ldap_delete_domain')) {
    function ldap_delete_domain(string $key): bool {
        $domains = ldap_get_domains();
        $filtered = array_values(array_filter($domains, fn($d) => ($d['key'] ?? '') !== $key));
        if (count($filtered) === count($domains)) return false; // not found
        return ldap_write_domains($filtered);
    }
}

// Get active domain key
if (!function_exists('ldap_get_active_domain_key')) {
    function ldap_get_active_domain_key(): string {
        $path = ldap_active_domain_file_path();
        if (file_exists($path)) {
            $key = trim((string) file_get_contents($path));
            if ($key !== '') return $key;
        }
        // Fallback: first domain or "default"
        $domains = ldap_get_domains();
        return $domains[0]['key'] ?? 'default';
    }
}

// Set active domain
if (!function_exists('ldap_set_active_domain_key')) {
    function ldap_set_active_domain_key(string $key): bool {
        // Validate domain exists
        if (ldap_get_domain($key) === null) return false;
        ldap_config_dir();
        return file_put_contents(ldap_active_domain_file_path(), $key) !== false;
    }
}

// Per-domain bind secret
if (!function_exists('ldap_domain_secret_path')) {
    function ldap_domain_secret_path(string $key): string {
        return ldap_config_dir() . DIRECTORY_SEPARATOR . 'secrets' . DIRECTORY_SEPARATOR . $key . '.json';
    }
}
if (!function_exists('ldap_read_domain_secret')) {
    function ldap_read_domain_secret(string $key): string {
        $path = ldap_domain_secret_path($key);
        if (!file_exists($path) || !is_readable($path)) return '';
        $decoded = json_decode((string) file_get_contents($path), true);
        return (string) ($decoded['password'] ?? '');
    }
}
if (!function_exists('ldap_write_domain_secret')) {
    function ldap_write_domain_secret(string $key, string $password): bool {
        $dir = dirname(ldap_domain_secret_path($key));
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $payload = json_encode(['password' => $password], JSON_PRETTY_PRINT);
        return file_put_contents(ldap_domain_secret_path($key), $payload) !== false;
    }
}
```

#### 1.2 Auto-migration: existing `config.json` → "default" domain

In `ldap_read_config()` **or** a new migration function called from `ldap_module.php`:

```php
// In ldap_module.php, after require_once chain:
if (!function_exists('ldap_migrate_legacy_config')) {
    function ldap_migrate_legacy_config(): void {
        $legacyFile = ldap_config_file_path();      // old config.json
        $domainsFile = ldap_domains_file_path();      // new domains.json
        
        if (file_exists($domainsFile)) return;       // already migrated
        
        if (!file_exists($legacyFile)) {
            // Seed with empty domain — user configures via UI
            ldap_write_domains([]);
            return;
        }
        
        $legacy = json_decode((string) file_get_contents($legacyFile), true);
        if (!is_array($legacy)) { ldap_write_domains([]); return; }

        $domain = [
            'key' => 'default',
            'label' => $legacy['base_dn'] ?? 'Default AD',
            'host' => $legacy['host'] ?? '',
            'port' => $legacy['port'] ?? 389,
            'use_tls' => !empty($legacy['use_tls']),
            'base_dn' => $legacy['base_dn'] ?? '',
            'user_search_base' => $legacy['user_search_base'] ?? '',
            'bind_dn' => $legacy['bind_dn'] ?? '',
            'enabled' => !empty($legacy['enabled']),
            'backend' => $legacy['backend'] ?? 'powershell',
        ];
        ldap_upsert_domain($domain);
        
        // Migrate bind password
        $oldPw = ldap_read_bind_password();  // from bind_secret.json
        if ($oldPw !== '') ldap_write_domain_secret('default', $oldPw);
        
        // Set as active
        file_put_contents(ldap_active_domain_file_path(), 'default');
    }
}
ldap_migrate_legacy_config();
```

#### 1.3 Existing BC: `ldap_read_config()` returns active domain

For backward compat, make `ldap_read_config()` read the active domain:

```php
if (!function_exists('ldap_read_config')) {
    function ldap_read_config(): array {
        $defaults = ldap_default_config();
        $activeKey = ldap_get_active_domain_key();
        $domain = ldap_get_domain($activeKey);
        if ($domain === null) return $defaults;
        
        return array_replace($defaults, [
            'enabled' => $domain['enabled'] ?? false,
            'backend' => $domain['backend'] ?? 'powershell',
            'host' => $domain['host'] ?? '',
            'port' => $domain['port'] ?? 389,
            'use_tls' => !empty($domain['use_tls']),
            'base_dn' => $domain['base_dn'] ?? '',
            'bind_dn' => $domain['bind_dn'] ?? '',
            'user_search_base' => $domain['user_search_base'] ?? '',
        ]);
    }
}
```

#### 1.4 Update `ldap_read_bind_password()` to read from active domain secret

```php
if (!function_exists('ldap_read_bind_password')) {
    function ldap_read_bind_password(): string {
        $activeKey = ldap_get_active_domain_key();
        return ldap_read_domain_secret($activeKey);
    }
}
```

---

## Phase 2: Connection — Connect Using Active Domain

### 2.1 `app/Ldap/Connection/ldap_connection_factory.php`

No changes needed to `ldap_connect_and_bind()` — it already calls `ldap_read_config()` which now returns the active domain.

`ldap_run_with_connection()` in `ldap_helpers.php` also calls `ldap_connect_and_bind()` — it automatically uses the active domain.

**No handler code changes needed.** Every LDAP operation receives the correct connection for the active domain.

---

## Phase 3: Log Path — Domain Subdirectory

### 3.1 `app/Ldap/Support/ldap_helpers.php` — `ldap_write_script_log()`

Change line 477:
```php
// BEFORE:
$logDir = rtrim($baseLogPath, '/\\') . DIRECTORY_SEPARATOR . 'scripts_logs' . DIRECTORY_SEPARATOR . $relativePath;

// AFTER:
$activeDomain = function_exists('ldap_get_active_domain_key') ? ldap_get_active_domain_key() : 'default';
$logDir = rtrim($baseLogPath, '/\\') . DIRECTORY_SEPARATOR . $activeDomain . DIRECTORY_SEPARATOR . 'scripts_logs' . DIRECTORY_SEPARATOR . $relativePath;
```

### 3.2 `app/Infrastructure/Logging/dashboard_log_reader.php` — `dashboard_log_base_dir()`

Change line 57-68:
```php
if (!function_exists('dashboard_log_base_dir')) {
    function dashboard_log_base_dir(): string {
        $base = get_external_log_base();
        $activeDomain = function_exists('ldap_get_active_domain_key') ? ldap_get_active_domain_key() : 'default';
        $scriptsLogsDir = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $activeDomain . DIRECTORY_SEPARATOR . 'scripts_logs';
        if (!is_dir($scriptsLogsDir)) @mkdir($scriptsLogsDir, 0775, true);
        return $scriptsLogsDir;
    }
}
```

### 3.3 PowerShell scripts — Read active domain from shared_config.json

PowerShell scripts currently construct log paths like:
```powershell
$logDir = "$BaseLogPath\scripts_logs\$Category\$action"
```

Need to change to:
```powershell
$BaseLogPath = Get-Content "$PSScriptRoot\..\..\config\shared_config.json" | ConvertFrom-Json
$domainName = $shared.active_domain ?? 'default'
$logDir = "$BaseLogPath\$domainName\scripts_logs\$Category\$action"
```

**Key files to update:**
- `scripts/powershell/ldap_ad_helpers.ps1` — shared helper used by all scripts
- `scripts/powershell/export-group-user-list.ps1`
- `scripts/powershell/get-user-report.ps1`
- `scripts/powershell/export-hrms-ad-login-id.ps1`
- `scripts/powershell/check-ad-hrms-status.ps1`
- `scripts/powershell/health-check.ps1`

Update the `Write-Log` function (or equivalent log-writing code) in `ldap_ad_helpers.ps1` to prepend `$domainName` to the path.

---

## Phase 4: Shared Config Sync — Active Domain for PowerShell

### 4.1 `config/shared_config.json`

Add `active_domain` field. This file is written by PHP and read by PowerShell.

Format:
```json
{
    "default_password": "Welcome123!",
    "app_name": "AccessPilot",
    "domain_name": "wgbd.com",
    "org_name": "walton",
    "base_dn": "DC=wgbd, DC=COM",
    "active_domain": "wgbd"
}
```

### 4.2 `app/Application/Http/Controllers/system_config.php`

Update `sync_shared_config()` (line 225) — after writing all other fields, add:
```php
$activeKey = function_exists('ldap_get_active_domain_key') ? ldap_get_active_domain_key() : 'default';
$jsonPayload['active_domain'] = $activeKey;
```

Also create a new function `sync_active_domain_to_shared_config()` that can be called independently when domain switches:
```php
if (!function_exists('sync_active_domain_to_shared_config')) {
    function sync_active_domain_to_shared_config(): void {
        $jsonPath = app_root('config/shared_config.json');
        if (!file_exists($jsonPath)) return;
        $payload = json_decode((string) file_get_contents($jsonPath), true);
        if (!is_array($payload)) return;
        $payload['active_domain'] = ldap_get_active_domain_key();
        file_put_contents($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
```

---

## Phase 5: UI — Active Domain Switcher in Assistant Pane

### 5.1 `resources/views/layouts/master.php` — Add domain badge under Assistant title

After line 337 (`</h4>`), inside the `.d-flex.justify-content-between.align-items-center` div:

```html
<div class="domain-switcher mt-1">
    <div class="domain-switcher-trigger" id="domainSwitcherTrigger">
        <i class="fas fa-server me-1" style="font-size:0.65rem;color:#64748b;"></i>
        <span id="activeDomainLabel" class="domain-label" data-domain="<?= htmlspecialchars(ldap_get_active_domain_key()) ?>">
            <?= htmlspecialchars(ldap_get_active_domain_key()) ?>
        </span>
        <i class="fas fa-chevron-down" style="font-size:0.5rem;opacity:0.4;margin-left:2px;"></i>
    </div>
    <div class="domain-dropdown" id="domainDropdown" style="display:none;">
        <div class="domain-dropdown-header">Switch Active Domain</div>
        <div class="domain-dropdown-list" id="domainList">
            <!-- Populated via AJAX -->
            <div class="text-center py-2 text-muted small">Loading...</div>
        </div>
    </div>
</div>
```

### 5.2 CSS for domain switcher

Add in `master.php` inline `<style>` block (around line 80+):

```css
.domain-switcher { position: relative; margin-bottom: 6px; }
.domain-switcher-trigger {
    display: inline-flex; align-items: center; gap: 4px;
    cursor: pointer; padding: 2px 8px; border-radius: 4px;
    font-size: 0.72rem; font-weight: 600; color: #475569;
    background: rgba(0,0,0,0.03); border: 1px solid transparent;
    transition: all 0.15s; user-select: none;
}
.domain-switcher-trigger:hover {
    background: rgba(0,0,0,0.06); border-color: #e2e8f0;
}
.domain-label { color: #2563eb; }
.domain-dropdown {
    position: absolute; top: calc(100% + 4px); left: 0;
    min-width: 220px; background: #fff; border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12); border: 1px solid #e2e8f0;
    z-index: 9999; overflow: hidden;
}
.domain-dropdown-header {
    padding: 8px 12px; font-size: 0.65rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px;
    color: #94a3b8; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
}
.domain-dropdown-item {
    padding: 8px 12px; font-size: 0.8rem; cursor: pointer;
    display: flex; align-items: center; gap: 8px;
    transition: background 0.1s; border-bottom: 1px solid #f1f5f9;
}
.domain-dropdown-item:last-child { border-bottom: none; }
.domain-dropdown-item:hover { background: #f1f5f9; }
.domain-dropdown-item.active { background: #eff6ff; font-weight: 600; }
.domain-dropdown-item .domain-key { font-family: monospace; color: #2563eb; }
.domain-dropdown-item .domain-check { margin-left: auto; color: #22c55e; }
```

### 5.3 JS for domain switcher

Add in `master.php` script block (around line 330+):

```javascript
(function() {
    const trigger = document.getElementById('domainSwitcherTrigger');
    const dropdown = document.getElementById('domainDropdown');
    const list = document.getElementById('domainList');
    const label = document.getElementById('activeDomainLabel');

    if (!trigger || !dropdown) return;

    // Load domain list
    function loadDomains() {
        fetch('/api/index.php?endpoint=list_domains')
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.domains) return;
                const active = label.dataset.domain;
                list.innerHTML = data.domains.map(d =>
                    `<div class="domain-dropdown-item${d.key === active ? ' active' : ''}" data-key="${d.key}">
                        <i class="fas fa-server" style="font-size:0.65rem;color:#94a3b8;"></i>
                        <span class="domain-key">${d.key}</span>
                        <span style="font-size:0.7rem;color:#64748b;">${d.label || ''}</span>
                        ${d.key === active ? '<span class="domain-check"><i class="fas fa-check-circle"></i></span>' : ''}
                    </div>`
                ).join('');
            })
            .catch(() => {});
    }

    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        const showing = dropdown.style.display === 'block';
        dropdown.style.display = showing ? 'none' : 'block';
        if (!showing) loadDomains();
    });

    document.addEventListener('click', function(e) {
        if (!dropdown.contains(e.target) && e.target !== trigger) {
            dropdown.style.display = 'none';
        }
    });

    list.addEventListener('click', function(e) {
        const item = e.target.closest('.domain-dropdown-item');
        if (!item) return;
        const key = item.dataset.key;
        if (key === label.dataset.domain) { dropdown.style.display = 'none'; return; }

        fetch('/api/index.php?endpoint=switch_domain', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({domain_key: key})
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    label.textContent = key;
                    label.dataset.domain = key;
                    dropdown.style.display = 'none';
                    // Reload page content to reflect new domain
                    if (typeof spaContentUpdated !== 'undefined') {
                        document.dispatchEvent(new CustomEvent('spaContentUpdated'));
                    } else {
                        location.reload();
                    }
                }
            })
            .catch(() => {});
    });
})();
```

### 5.4 API Endpoints

#### `GET /api/index.php?endpoint=list_domains`

Add to appropriate controller (or `api/index.php`):
```php
if ($endpoint === 'list_domains') {
    require_once app_root('app/Ldap/ldap_module.php');
    $domains = ldap_get_domains();
    $activeKey = ldap_get_active_domain_key();
    echo json_encode([
        'success' => true,
        'domains' => array_map(fn($d) => [
            'key' => $d['key'] ?? '',
            'label' => $d['label'] ?? $d['base_dn'] ?? '',
            'is_active' => ($d['key'] ?? '') === $activeKey,
        ], $domains),
        'active_key' => $activeKey,
    ]);
    exit;
}
```

#### `POST /api/index.php?endpoint=switch_domain`

```php
if ($endpoint === 'switch_domain') {
    require_once app_root('app/Ldap/ldap_module.php');
    $input = json_decode(file_get_contents('php://input'), true);
    $key = trim((string) ($input['domain_key'] ?? ''));
    
    if ($key === '' || ldap_get_domain($key) === null) {
        echo json_encode(['success' => false, 'message' => 'Invalid domain key.']);
        exit;
    }
    
    if (!ldap_set_active_domain_key($key)) {
        echo json_encode(['success' => false, 'message' => 'Failed to switch domain.']);
        exit;
    }
    
    // Sync to shared_config.json for PowerShell
    if (function_exists('sync_active_domain_to_shared_config')) {
        sync_active_domain_to_shared_config();
    }
    
    echo json_encode(['success' => true, 'message' => "Switched to domain: {$key}", 'active_key' => $key]);
    exit;
}
```

---

## Phase 6: System Config — Domain List CRUD

### 6.1 `resources/views/pages/tools/system_config_view.php`

Add a new card section after the existing LDAP Connection section:

```
- "Domains" section with add/edit/delete
- Table/list showing all domains with key, host, port, base DN, enabled status
- Each row: Edit button, Delete button, "Set Active" button
- Add Domain form: key, label, host, port, TLS, base DN, user search base, bind DN, bind password, backend mode, enabled
- "Test Connection" per domain
- Validation: key must be unique, host required
```

### 6.2 Controller actions in `system_config.php`

Add new actions:
- `save_domain` — upsert a domain (POST)
- `delete_domain` — remove a domain (POST, prevents deleting active domain)
- `test_domain_connect` — test connection for a specific domain (GET)

### 6.3 API endpoints

- `GET /api/index.php?endpoint=get_domains` — returns all domains with full config (admin only)
- `POST /api/index.php?endpoint=save_domain` — upsert domain
- `POST /api/index.php?endpoint=delete_domain` — delete domain
- `GET /api/index.php?endpoint=test_domain_connect?domain_key=xxx` — test connection

---

## Phase 7: Domain CRUD in system_config.php Controller

Add to `app/Application/Http/Controllers/system_config.php` (inside POST handler):

```php
if ($action === 'save_domain') {
    require_once app_root('app/Ldap/ldap_module.php');
    $domain = [
        'key' => trim((string) ($data['domain_key'] ?? '')),
        'label' => trim((string) ($data['domain_label'] ?? '')),
        'host' => trim((string) ($data['domain_host'] ?? '')),
        'port' => (int) ($data['domain_port'] ?? 389),
        'use_tls' => !empty($data['domain_use_tls']),
        'base_dn' => trim((string) ($data['domain_base_dn'] ?? '')),
        'user_search_base' => trim((string) ($data['domain_user_search_base'] ?? '')),
        'bind_dn' => trim((string) ($data['domain_bind_dn'] ?? '')),
        'enabled' => !empty($data['domain_enabled']),
        'backend' => $data['domain_backend'] ?? 'powershell',
    ];
    if ($domain['key'] === '') {
        echo json_encode(['success' => false, 'message' => 'Domain key is required.']);
        exit;
    }
    if ($domain['host'] === '') {
        echo json_encode(['success' => false, 'message' => 'Host is required.']);
        exit;
    }
    if (!ldap_upsert_domain($domain)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save domain.']);
        exit;
    }
    $newPassword = (string) ($data['domain_bind_password'] ?? '');
    if ($newPassword !== '') {
        ldap_write_domain_secret($domain['key'], $newPassword);
    }
    echo json_encode(['success' => true, 'message' => "Domain '{$domain['key']}' saved."]);
    exit;
}

if ($action === 'delete_domain') {
    require_once app_root('app/Ldap/ldap_module.php');
    $key = trim((string) ($data['domain_key'] ?? ''));
    if ($key === '') {
        echo json_encode(['success' => false, 'message' => 'Domain key is required.']);
        exit;
    }
    $activeKey = ldap_get_active_domain_key();
    if ($key === $activeKey) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete the active domain. Switch to another domain first.']);
        exit;
    }
    if (!ldap_delete_domain($key)) {
        echo json_encode(['success' => false, 'message' => 'Domain not found.']);
        exit;
    }
    echo json_encode(['success' => true, 'message' => "Domain '{$key}' deleted."]);
    exit;
}
```

---

## File Change Summary (All Phases)

| # | File | Change | Phase |
|---|------|--------|-------|
| 1 | `app/Ldap/Config/ldap_config_repository.php` | Add domain CRUD, active domain get/set, per-domain secrets, auto-migration | 1 |
| 2 | `app/Ldap/Connection/ldap_connection_factory.php` | `ldap_read_bind_password()` reads per-domain secret | 1.4 |
| 3 | `app/Ldap/ldap_module.php` | Call `ldap_migrate_legacy_config()` after loading | 1.2 |
| 4 | `app/Ldap/Support/ldap_helpers.php` | `ldap_write_script_log()` prepend domain to log path | 3.1 |
| 5 | `app/Infrastructure/Logging/dashboard_log_reader.php` | `dashboard_log_base_dir()` prepend domain | 3.2 |
| 6 | `scripts/powershell/ldap_ad_helpers.ps1` | `Write-Log` reads active_domain from shared_config | 3.3 |
| 7 | Other `.ps1` scripts | Use domain-aware log path | 3.3 |
| 8 | `app/Application/Http/Controllers/system_config.php` | `sync_shared_config()` adds active_domain; `sync_active_domain_to_shared_config()`; domain CRUD actions | 4.2, 7 |
| 9 | `resources/views/layouts/master.php` | Domain switcher badge + dropdown + CSS + JS | 5.1-5.3 |
| 10 | `public/api/index.php` or new file | `list_domains` + `switch_domain` endpoints | 5.4 |
| 11 | `resources/views/pages/tools/system_config_view.php` | Domain list CRUD section | 6 |

---

## Files NOT Needing Change

| File | Reason |
|------|--------|
| `app/Ldap/Operations/*.php` (all handlers) | Handlers receive `$connection` from caller — no logic change needed |
| `app/Ldap/Router/ad_operation_router.php` | Already calls `ldap_read_config()` / `ldap_connect_and_bind()` which now use active domain |
| `app/Ldap/Services/ldap_config_service.php` | `ldap_save_settings()` will be replaced by domain CRUD, but can remain for BC |
| `app/Ldap/Support/ldap_response_adapter.php` | Response format unchanged |
| `app/Ldap/Diagnostics/ldap_environment.php` | Environment check is PHP-wide |
| `config/ldap/ldap_operations.php` | Operation readiness flags are domain-agnostic |
| `app/Infrastructure/PowerShell/powershell_runner.php` | No change — scripts read shared_config.json directly |

---

## Phase 8: License Bundling — Domain Count Enforcement

### 8.1 License Payload — Add `max_domains` field

**Generator side** — `Issue-License.ps1` ekti new prompt add:
```
Enter max domains (0=unlimited, or 1,2,3,5): 
```

Pass to `generator.php` as `--max-domains=N`. Generator adds `"max_domains": N` to the signed JSON payload.

**`app/Domain/Licensing/license_service.php` — `license_verify_signature()`:**

The signing string is built from these parts:
```php
$parts = [
    $fields['license_id'],
    $fields['product_name'],
    $fields['issued_to'],
    $fields['domain_name'],
    $fields['deployment_id'],
    $fields['expires_on'],
    $fields['issued_at'],
];
```

Append `max_domains` **conditionally** — only if present and non-empty:
```php
if (!empty($fields['max_domains'])) {
    $parts[] = (string) $fields['max_domains'];
}
```

This ensures backward compatibility: old licenses without `max_domains` verify unchanged.

### 8.2 `license_get_status()` — Expose `max_domains` + `domains_used`

Add to the return array of `license_get_status()` (after line 372):
```php
'max_domains' => (int) ($state['max_domains'] ?? 1),
'domains_used' => (function_exists('ldap_get_domains') ? count(ldap_get_domains()) : 1),
'domains_remaining' => (function() use ($state) {
    $max = (int) ($state['max_domains'] ?? 1);
    if ($max === 0) return -1; // unlimited
    $used = function_exists('ldap_get_domains') ? count(ldap_get_domains()) : 1;
    return max(0, $max - $used);
})(),
```

Also add to `license_validate_certificate_payload()` normalized payload (line 497-506):
```php
'max_domains' => isset($payload['max_domains']) ? (int) $payload['max_domains'] : 1,
```

### 8.3 Domain CRUD — Enforce `max_domains` check

**`ldap_upsert_domain()`** — Before adding a NEW domain (not updating existing), check the limit:

```php
if (!function_exists('ldap_upsert_domain')) {
    function ldap_upsert_domain(array $domain): bool {
        $key = $domain['key'] ?? '';
        if ($key === '') return false;
        $domains = ldap_get_domains();
        
        // Check if this is an update or a new domain
        $existing = null;
        foreach ($domains as $d) {
            if (($d['key'] ?? '') === $key) { $existing = $d; break; }
        }
        
        if ($existing === null) {
            // NEW domain — check license limit
            $maxDomains = (int) (license_get_status()['max_domains'] ?? 1);
            if ($maxDomains > 0 && count($domains) >= $maxDomains) {
                return false; // Caller checks return and shows message
            }
        }
        
        $found = false;
        foreach ($domains as &$d) {
            if (($d['key'] ?? '') === $key) { $d = $domain; $found = true; break; }
        }
        if (!$found) $domains[] = $domain;
        return ldap_write_domains($domains);
    }
}
```

Also add a helper to get the human-readable limit message:
```php
if (!function_exists('ldap_domain_limit_message')) {
    function ldap_domain_limit_message(): string {
        $status = function_exists('license_get_status') ? license_get_status() : [];
        $max = (int) ($status['max_domains'] ?? 1);
        $used = (int) ($status['domains_used'] ?? count(ldap_get_domains()));
        if ($max === 0) return 'Unlimited domains (licensed)';
        $remaining = max(0, $max - $used);
        return "Domain limit: {$used} / {$max} used, {$remaining} remaining";
    }
}
```

### 8.4 System Config Domain UI — Show remaining count

**`system_config_view.php`** — Domain list section er upore add:
```html
<div class="domain-license-info">
    <span class="domain-license-badge">
        <i class="fas fa-server me-1"></i>
        <span id="domainLimitStatus"><?= htmlspecialchars(ldap_domain_limit_message()) ?></span>
    </span>
</div>
```

When `max_domains > 0` and `remaining == 0`, disable the "Add Domain" button and show warning:
```html
<div id="domainLimitWarning" class="sys-callout sys-callout-warn <?= ($remaining > 0) ? 'sys-hidden' : '' ?>">
    <i class="fas fa-exclamation-triangle me-1"></i>
    Domain limit reached. Contact vendor to upgrade your license for additional domains.
</div>
```

### 8.5 License Page — Show domain entitlement info

**`resources/views/pages/license/license_status_view.php`** — Certificate card e `max_domains` section add:

After "Deployment ID" row (line 90), add a new row:
```php
<div class="col-sm-6">
    <div class="cert-card-meta">
        <div class="cert-card-label">Domain Entitlement</div>
        <div id="licenseMaxDomains" class="cert-card-value">
            <?php
            $maxDomains = (int) ($licenseStatus['max_domains'] ?? 1);
            $used = (int) ($licenseStatus['domains_used'] ?? 1);
            $remaining = (int) ($licenseStatus['domains_remaining'] ?? 0);
            if ($maxDomains === 0): ?>
                <span style="color:#16a34a;">Unlimited <span style="font-size:0.6rem;color:#64748b;">(<?= $used ?> configured)</span></span>
            <?php else: ?>
                <?= $used ?> / <?= $maxDomains ?> used
                <?php if ($remaining > 0): ?>
                    <span style="font-size:0.6rem;color:#64748b;">(<?= $remaining ?> remaining)</span>
                <?php else: ?>
                    <span style="font-size:0.6rem;color:#dc2626;">(limit reached)</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
```

Also update the JS `applyLicenseState()` to update `maxDomainsEl` dynamically:
```javascript
// Add after line 361:
const maxDomainsEl = document.getElementById('licenseMaxDomains');
// In applyLicenseState():
if (maxDomainsEl && status.max_domains !== undefined) {
    const max = parseInt(status.max_domains) || 1;
    const used = parseInt(status.domains_used) || 1;
    const rem = parseInt(status.domains_remaining) || 0;
    if (max === 0) {
        maxDomainsEl.innerHTML = '<span style="color:#16a34a;">Unlimited <span style="font-size:0.6rem;color:#64748b;">(' + used + ' configured)</span></span>';
    } else {
        let html = used + ' / ' + max + ' used';
        if (rem > 0) html += ' <span style="font-size:0.6rem;color:#64748b;">(' + rem + ' remaining)</span>';
        else html += ' <span style="font-size:0.6rem;color:#dc2626;">(limit reached)</span>';
        maxDomainsEl.innerHTML = html;
    }
}
```

### 8.6 Domain API — Return limit info

In `list_domains` endpoint, add limit info:
```php
echo json_encode([
    'success' => true,
    'domains' => [...],
    'active_key' => $activeKey,
    'license' => [
        'max_domains' => (int) ($licStatus['max_domains'] ?? 1),
        'domains_used' => count($domains),
        'domains_remaining' => (int) ($licStatus['domains_remaining'] ?? 0),
    ],
]);
```

---

## File Change Summary — Additional (Phase 8)

| # | File | Change | Phase |
|---|------|--------|-------|
| 12 | `scripts/license_admin_templates/Issue-License.ps1` | Add `--max-domains` prompt & param | 8.1 |
| 13 | `scripts/license_admin_templates/Renew-License.ps1` | Add `--max-domains` prompt & param | 8.1 |
| 14 | `scripts/license_admin_templates/core/generator.php` | Accept `--max-domains`, add to signed payload | 8.1 |
| 15 | `app/Domain/Licensing/license_service.php` | `license_verify_signature()` conditionally append `max_domains`; `license_get_status()` expose limit; `license_validate_certificate_payload()` normalize | 8.2 |
| 16 | `app/Ldap/Config/ldap_config_repository.php` | `ldap_upsert_domain()` enforce limit; add `ldap_domain_limit_message()` | 8.3 |
| 17 | `resources/views/pages/tools/system_config_view.php` | Domain limit badge + warning banner | 8.4 |
| 18 | `resources/views/pages/license/license_status_view.php` | Domain Entitlement row in cert card + JS update | 8.5 |
| 19 | `public/api/index.php` or domain API controller | Return license limit info in `list_domains` | 8.6 |

---

## Edge Cases & Protections

| Scenario | Handling |
|----------|----------|
| No domains configured | `ldap_get_domains()` returns []; system config prompts to create first domain |
| Active domain deleted | `ldap_delete_domain()` blocks deletion of active domain |
| Active domain becomes unreachable | Operation fails with LDAP error; user can switch to another domain |
| Domain key collision | `ldap_upsert_domain()` overwrites existing domain with same key |
| Empty domain key | `ldap_upsert_domain()` returns false |
| PowerShell falls back | Domain-aware log path still works via shared_config.json |
| Legacy config exists | Auto-migrated to "default" domain on first load |
| Switching while operation in progress | Atomic — active domain read at dispatch time |
