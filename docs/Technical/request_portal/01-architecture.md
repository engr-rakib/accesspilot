# Request Portal — Technical Architecture

## Overview

The Request Portal is a standalone public page (`request_portal.php`) that allows **unauthenticated users** to submit AD and Exchange operation requests. These requests are stored as JSON and approved/executed by administrators through the admin dashboard.

## File Map

| Layer | File | Role |
|-------|------|------|
| **Entry** | `public/request_portal.php` | Sets `$_GET['route'] = 'request_portal'`, bootstraps via `index.php` |
| **View** | `resources/views/pages/ad_user_request/request_portal_standalone.php` | Portal HTML, domain dropdown, extra fields JS |
| **Public API** | `app/Application/Http/Controllers/ad_user_request_public.php` | Handles POST: `submit`, `track_requests`, `get_broadcast` |
| **Admin API** | `app/Application/Http/Controllers/ad_user_request_admin.php` | Handles admin: `approve`, `deny`, `prepare`, `finalize`, `get_pending` |
| **Service** | `app/Domain/AdUserRequest/ad_user_request_service.php` | All business logic: type map, validate, submit, approve, deny |
| **Admin JS** | `public/resources/frontend/js/modules/ad_user_request_admin.js` | Renders pending requests table, approve/deny UI, bulk operations |
| **Exchange Runner** | `app/Infrastructure/PowerShell/ExchangePsRunner.php` | Exchange cmdlet wrappers (mailbox, group, archive, etc.) |
| **Storage** | `{secure_path}/requests/ad_user_requests.json` | JSON file storing all requests |

## Data Flow

```
USER (unauthenticated)
  │
  ├── POST /ad_user_request_public.php  { request_type, target_username, ... }
  │     │
  │     ▼
  │   ad_user_request_validate()
  │     ├── Normalizes domain (ad_user_request_normalize_target)
  │     ├── Validates per-type requirements (email, quota, group name, etc.)
  │     └── Returns validated data array
  │     │
  │     ▼
  │   ad_user_request_submit()
  │     ├── Generates unique ID (aur_YYYYMMDDHHmmss_random)
  │     ├── Reads/writes ad_user_requests.json
  │     └── Returns success + request ID
  │     │
  │     ▼
  └── Response: { success: true, request_id: "aur_..." }

ADMIN (authenticated)
  │
  ├── Sees pending request in admin_card.php table
  │     └── JS polls via get_pending_ad_user_requests every 10s
  │
  ├── Clicks Approve
  │     │
  │     ├── runApproveThroughQuickAction(requestId)
  │     │     ├── prepare_ad_user_request → returns execution action + target
  │     │     ├── If action=manualCreateCustomUser → opens manual create form
  │     │     └── Else → tries quick-action button, falls back to approve API
  │     │
  │     └── ad_user_request_approve()
  │           ├── Reads request, switches domain via ldap_set_active_domain()
  │           ├── IF needs_exchange:
  │           │     └── match(action) → ExchangePsRunner function
  │           │           ├── mailbox_enable/disable/add_address/remove_address/...
  │           │           ├── group_create/add_member/remove_member
  │           │           └── Returns {success, message}
  │           └── ELSE:
  │                 └── executeADAction(target, action, operator)
  │                       └── Runs PowerShell script or LDAP operation
  │
  └── Request status updated to completed/failed in JSON

```

## Request Type Map

Defined in `ad_user_request_type_map()` (`ad_user_request_service.php:106-272`).

### AD Types (15)
`unlock`, `reset_unlock`, `enable`, `disable`, `new_user`, `modify_user`, `create_custom_user`, `create_service_account`

### Exchange Types (15)

| Key | Action | Extra Fields |
|-----|--------|-------------|
| `exchange_enable_mailbox` | `mailbox_enable` | target_username |
| `exchange_disable_mailbox` | `mailbox_disable` | target_username |
| `exchange_add_email` | `mailbox_add_address` | exchange_email |
| `exchange_remove_email` | `mailbox_remove_address` | exchange_email |
| `exchange_set_primary_smtp` | `mailbox_set_primary_smtp` | exchange_email |
| `exchange_set_forward` | `mailbox_set_forward` | exchange_email |
| `exchange_set_quota` | `mailbox_set_quota` | exchange_extra |
| `exchange_hide_gal` | `mailbox_set_hidden_gal` | target_username |
| `exchange_enable_archive` | `mailbox_enable_archive` | target_username |
| `exchange_disable_archive` | `mailbox_disable_archive` | target_username |
| `exchange_set_litigation_hold` | `mailbox_set_litigation_hold` | target_username |
| `exchange_set_mail_tip` | `mailbox_set_mail_tip` | exchange_extra |
| `exchange_group_create` | `group_create` | group_name, group_alias, group_description |
| `exchange_group_add_member` | `group_add_member` | exchange_extra (member identity) |
| `exchange_group_remove_member` | `group_remove_member` | exchange_extra (member identity) |

### Type Flags
- `needs_target` — requires a target username/account ID
- `needs_hrms_id` — requires HRMS ID (only `new_user`)
- `needs_exchange` — routes to ExchangePsRunner instead of executeADAction
- `extra_fields` — additional form fields for specific types (e.g., `exchange_group_create`)

## Domain Handling

### Allowed Domains (`ad_user_request_allowed_domains`)
Dynamically built from `ldap_get_domains()`:
```php
['wgbd' => ['wgbd', 'wgbd.com'], 'whildc' => ['whildc', 'whildc.com']]
```

### Normalization (`ad_user_request_normalize_target`)
- Accepts `domain\user` or bare `user` format
- Domain prefix normalized via `ad_user_request_normalize_domain()`
- Falls back to `selected_domain` from dropdown
- Final fallback: `'wgbd'`

### Domain Switching on Approve
When `ad_user_request_approve()` runs, it switches the active domain to the request's `target_domain` before executing:
```php
$targetDomain = (string)($request['target_domain'] ?? '');
$normalizedDomain = ad_user_request_normalize_domain($targetDomain);
if ($normalizedDomain !== null && function_exists('ldap_set_active_domain')) {
    ldap_set_active_domain($normalizedDomain);
}
```

## Exchange Execution

Exchange requests are routed via PHP 8 `match` expression in `ad_user_request_approve()`:
```php
$result = match ($exchangeAction) {
    'mailbox_enable' => exchange_enable_mailbox($target),
    'mailbox_disable' => exchange_disable_mailbox($target),
    'mailbox_add_address' => exchange_add_email_address($target, $exchangeEmail),
    // ... 12 more actions
    default => throw new \RuntimeException("Unsupported exchange action: $exchangeAction"),
};
```

The ExchangePsRunner functions use PowerShell Core (`pwsh`) on Linux with Kerberos auth, or Windows PowerShell with Basic auth on Windows.

## Admin Approval Flow

### Frontend (JS)

`ad_user_request_admin.js` implements `runApproveThroughQuickAction()`:
1. `prepare_ad_user_request` — returns execution action + target
2. For `manualCreateCustomUser` → opens manual creation form
3. For other actions → tries to find matching `.action-button` on dashboard
4. If not found → falls back to direct `approve_ad_user_request` API call

### Backend (PHP)

`ad_user_request_approve()` (`ad_user_request_service.php:552-658`):
1. Reads request from JSON
2. Validates status is `pending`
3. Switches to target domain
4. If `needs_exchange` → Exchange match block
5. Else → `executeADAction()`
6. Updates request status to `completed` or `failed`
7. Writes back to JSON

## Data Storage

**File:** `{secure_path}/requests/ad_user_requests.json`

**Structure per request:**
```json
{
    "id": "aur_20260726164052_5e2c303d",
    "request_type": "exchange_enable_mailbox",
    "request_type_label": "Enable Mailbox",
    "requester_name": "Rakibuzzaman",
    "requester_email": "rakib@example.com",
    "requester_contact": "",
    "target_username": "66684",
    "target_domain": "whildc",
    "target_display_username": "whildc.com\\66684",
    "target_warning": "",
    "hrms_id": "",
    "requested_name": "",
    "custom_username": "",
    "custom_display_name": "",
    "justification": "",
    "exchange_email": "",
    "exchange_extra": "",
    "group_name": "",
    "group_alias": "",
    "group_description": "",
    "status": "pending",
    "timestamp": "2026-07-26 04:40:52 PM",
    "processed_at": "",
    "processed_by": "",
    "execution_message": "",
    "process_note": ""
}
```

## Frontend JS Architecture

The portal JS (`request_portal_standalone.php`) handles:
- **Domain filtering**: Exchange types only show exchange-enabled domains in dropdown
- **Extra fields toggle**: Shows/hides email, quota, group fields based on selected request type
- **Form validation**: Client-side validation before submit
- **Request history tracking**: Users can look up their previous requests by email

The admin JS (`ad_user_request_admin.js`) handles:
- **Pending requests table**: Renders with domain-based row coloring
- **Approve flow**: Quick-action or direct approve API
- **Bulk operations**: Multi-select approve/deny
- **Auto-refresh**: Polls every 10 seconds
- **Manual create listener**: Handles `manualUserCreateCompleted` custom event
