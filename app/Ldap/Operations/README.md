# LDAP Operations

Implement Active Directory behavior here (replaces PowerShell script logic over time).

| File | Phase | Status |
|------|-------|--------|
| `ldap_operation_catalog.php` | — | Maps operations → handlers |
| `ldap_response_adapter.php` | 1 | JSON shape parity with PS |
| `ldap_user_repository.php` | 1 | **Implemented** — `get_user_info` |
| `ldap_group_repository.php` | 1 | **Implemented** — resolve, members, group list |
| `ldap_directory_writer.php` | 1 | **Implemented** — `get_ous` |
| `ldap_user_writer.php` | 2–3 | Writes — stub |
| `../Support/ldap_helpers.php` | 1 | Connection helpers, search, DN parse |

After parity tests, set `true` in `config/ldap_operations.php` for that operation.
