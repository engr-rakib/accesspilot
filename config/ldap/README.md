# LDAP configuration files

Static defaults and feature flags for the LDAP module. **Implementation code lives in `app/Ldap/`.**

| File | Location | Purpose |
|------|----------|---------|
| Defaults | `../ldap.php` | Merged in `config/app_config.php` |
| Operation flags | `ldap_operations.php` (this directory) | `ldap_ready` per endpoint |
| Runtime + secrets | `{secure_base_path}/ldap/` | Created by app (not in git) |

## Runtime vault layout

```
{secure_base_path}/ldap/
  config.json       ← host, port, backend, bind_dn, etc.
  bind_secret.json  ← service account password
  last_test.json    ← last connection test result
```

## Developer entry point

```php
require_once app_root('app/Ldap/ldap_module.php');
```

See `app/Ldap/README.md` for the full module map.
