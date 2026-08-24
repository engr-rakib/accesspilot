# Architecture — Multi-AD Management

## Decision Record

### ADR-1: Active Domain Switching (not per-operation routing)

**Status**: Accepted

**Context**: Two approaches to multi-AD: (a) per-operation routing — every operation specifies which AD to use, or (b) active domain switching — one AD is active at a time.

**Decision**: Use **active domain switching**. Simpler code changes, no need to modify individual operation handlers, same UX as most directory management tools.

**Consequences**:
- All LDAP operations automatically use the active domain
- Switching is an explicit user action with page reload
- No concurrent multi-AD operations
- No handler code changes needed

### ADR-2: Domain Config in Separate File (domains.json)

**Status**: Accepted

**Context**: Current single-AD stores all config in `ldap/config.json`. Multi-AD needs per-domain settings.

**Decision**: Store domains array in `ldap/domains.json`. Each domain entry contains its own host, port, base_dn, bind_dn, user_search_base, use_tls, backend, enabled. The existing `ldap/config.json` holds only base config (active_domain, acknowledged).

**Consequences**:
- `ldap_read_config()` merges base config + active domain settings
- Auto-migration migrates existing config.json to a "default" domain
- Backward compatible — existing configs work unchanged

### ADR-3: Per-Domain Bind Secrets

**Status**: Accepted

**Context**: Each AD domain has its own bind credentials.

**Decision**: Store secrets in `ldap/secrets/{domain_key}.json`. Each file contains `{"password": "..."}`.

**Consequences**:
- `ldap_read_bind_password()` reads from active domain's secret file
- Falls back to legacy `ldap/bind_secret.json` for backward compat
- `ldap_write_bind_password()` writes to active domain's secret

### ADR-4: License max_domains as Optional Field

**Status**: Accepted

**Context**: Need to enforce domain count limits via license while maintaining backward compatibility.

**Decision**: `max_domains` field appended to end of signing string only when present. Field defaults to 1 if absent.

**Consequences**:
- Old licenses (without max_domains) verify unchanged (default to 1)
- New licenses with max_domains have it cryptographically bound
- `max_domains = 0` means unlimited

## Data Flow

### Connection Flow (single-AD → multi-AD)

```
ldap_connect_and_bind()
  └─ ldap_read_config()
       ├─ Read config.json (base config)
       ├─ Read active_domain from config.json
       └─ Read domains.json → find active domain → merge settings
  └─ ldap_read_bind_password()
       ├─ Read secrets/{active_key}.json
       └─ Fallback: bind_secret.json (legacy)
  └─ ldap_build_uri() / ldap_bind() — unchanged
```

### Switch Domain Flow

```
User clicks domain in Assistant pane dropdown
  └─ POST /api/switch_domain { domain_key }
       └─ ldap_set_active_domain(key)
            └─ Write active_domain to config.json
       └─ sync_active_domain_to_shared_config()
            └─ Write active_domain to shared_config.json
       └─ Response → page reload
```

### Log Path Flow

```
PowerShell script writes log
  └─ Reads shared_config.json → gets active_domain
  └─ Constructs path: {base_log_path}/{active_domain}/scripts_logs/{category}/{action}.log

PHP reads logs for dashboard
  └─ dashboard_log_base_dir()
       └─ Reads active_domain from config
       └─ Returns {base_log_path}/{active_domain}/scripts_logs/
```

## File Structure (Secure Config)

```
{secure_base}/ldap/
├── config.json              # Base config: active_domain, acknowledged, etc.
├── domains.json             # Array of domain objects with connection settings
├── secrets/                 # Per-domain bind passwords
│   ├── wgbd.json
│   └── other_domain.json
├── bind_secret.json         # Legacy — fallback only
└── last_test.json           # Last connection test result (unchanged)
```

## Domain Object Schema

```json
{
    "key": "wgbd",
    "label": "Walton Group (wgbd.com)",
    "host": "192.168.1.10",
    "port": 389,
    "use_tls": false,
    "base_dn": "DC=wgbd,DC=com",
    "user_search_base": "OU=Users,DC=wgbd,DC=com",
    "bind_dn": "CN=admin,CN=Users,DC=wgbd,DC=com",
    "enabled": true,
    "backend": "powershell"
}
```
