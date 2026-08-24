# Implementation Guide — Exchange Management

## Adding a New Action

To add a new Exchange action, follow 6 steps:

### Step 1: PS Wrapper in ExchangePsRunner.php

Add a function calling `exchange_run_cmdlet()`:

```php
function exchange_your_action(string $identity, string $param = ''): array
{
    $params = ['Identity' => $identity];
    if ($param !== '') {
        $params['ParamName'] = $param;
    }
    return exchange_run_cmdlet('Your-Cmdlet', $params);
}
```

Helper functions for parameter building:
- `exchange_ps_quote($value)` — wraps string in single quotes, escapes inner quotes
- `exchange_ps_param_value($value)` — auto-detects type: bool → `$true`/`$false`, int → raw, array with `__exchange_raw` key → raw expression, string → `exchange_ps_quote()`
- `exchange_raw($expression)` — pass raw PowerShell expression (e.g., `"(100MB)"`)

### Step 2: Handler in exchange.php

Add case + handler function:

```php
case 'your_new_action':
    handle_your_new_action($input);
    break;
```

Handler pattern:

```php
function handle_your_new_action(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity is required.']);
        return;
    }
    $result = exchange_your_action($identity);
    $output = exchange_parse_result($result);
    exchange_audit_response('your_new_action', $input, json_encode($output));
    echo json_encode($output);
}
```

Key helpers:
- Check RBAC via `has_permission('action_exchange_*')` — handled automatically by permission map (exchange.php:28-75) if you add the mapping there
- `exchange_audit_response($action, $input, $responseBody)` at line 238 — writes CSV + structured log
- `exchange_parse_result($result)` — standard result parser (handles PS error format)

### Step 3: Permission in components_config.php

If the action needs a new permission:
```php
'action_exchange_your_perm' => ['name' => 'Your Permission', 'icon' => 'fas fa-icon'],
```

Then add mapping in `exchange.php:28-75`:
```php
'your_new_action' => 'action_exchange_your_perm',
```

### Step 4: JS Function in exchange_actions.js

```javascript
function bindYourAction() {
    document.getElementById('yourBtn').addEventListener('click', function() {
        var identity = activeIdentity;
        if (!identity) {
            showExchangeAction('Error', 'Select a mailbox first.');
            return;
        }
        doMailboxAction('your_new_action', identity);
    });
}
```

Use existing patterns:
- `doMailboxAction(action, identity, database, extraParams)` — generic POST wrapper (line 1732)
- `showExchangeAction(type, message)` — plain text feedback (line 1702)
- `showExchangeFeedback(success, title, message)` — HTML feedback (line 1719)
- `parseJsonResponse(response)` — fetch response wrapper (line 2092)
- `getCsrfToken()` — CSRF token from `<meta name="csrf-token">` (line 2088)
- `htmlspecialchars(str)` — XSS-safe output (line 2105)

### Step 5: UI in view.php

Add elements in the appropriate tab:
- `#tab-recipients` — Mailboxes & Groups
- `#tab-monitoring` — Monitoring
- `#tab-settings` — Settings

### Step 6: Logging (automatic)

If action name starts with `mailbox_` → routes to `ExchangeMailbox`
If action name starts with `group_` → routes to `ExchangeGroup`
Add to `$mutatingActions` array (exchange.php:240-272) if action mutates data
Add action code to `ldap_script_log_action()` (ldap_helpers.php:531) if new prefix

## PowerShell Script Construction

### exchange_build_inline_script() (ExchangePsRunner.php:119)

Builds inline PowerShell script:

```php
$lines = [];
$lines[] = 'try {';
if (Basic auth) {
    $lines[] = "    \$sec = ConvertTo-SecureString '{$password}' -AsPlainText -Force";
    $lines[] = "    \$cred = New-Object PSCredential('{$username}', \$sec)";
    $lines[] = "    \$session = New-PSSession -ConfigurationName Microsoft.Exchange -ConnectionUri '{$uri}' -Authentication Basic -Credential \$cred -AllowRedirection -ErrorAction Stop";
} else {
    // Kerberos — no explicit credentials (cached TGT)
    $lines[] = "    \$session = New-PSSession -ConfigurationName Microsoft.Exchange -ConnectionUri '{$uri}' -Authentication Kerberos -ErrorAction Stop";
}
$lines[] = '    Import-PSSession $session -AllowClobber -DisableNameChecking | Out-Null';
$lines[] = "    \$result = {$cmdlet} {$paramStr}";
$lines[] = '    Remove-PSSession $session';
$lines[] = '    if ($result -is [array] -or $result -is [System.Collections.IEnumerable]) {';
$lines[] = '        $result | ConvertTo-Json -Compress -Depth 3 -WarningAction SilentlyContinue';
$lines[] = '    } elseif ($result -ne $null) {';
$lines[] = '        $result | ConvertTo-Json -Compress -Depth 3 -WarningAction SilentlyContinue';
$lines[] = '    } else {';
$lines[] = "        '{\"success\":true}'";
$lines[] = '    }';
$lines[] = '} catch {';
$lines[] = '    if ($session -ne $null) { Remove-PSSession $session -ErrorAction SilentlyContinue }';
$lines[] = '    [pscustomobject]@{success=$false;message=$_.Exception.Message} | ConvertTo-Json -Compress -WarningAction SilentlyContinue';
$lines[] = '}';
```

### Parameter Building (exchange_run_cmdlet:254)

`exchange_run_cmdlet()` converts PHP array to PS `-ParameterName Value`:

```php
foreach ($params as $key => $value) {
    if (is_bool($value)) {
        $paramParts[] = "-{$key}:" . exchange_ps_param_value($value);
    } else {
        $paramParts[] = "-{$key} " . exchange_ps_param_value($value);
    }
}
```

- Booleans: `-Flag:$true` or `-Flag:$false`
- Strings: `-Identity 'user@domain.com'`
- Integers: `-Size 100`
- Raw: Use `exchange_raw('(100MB)')` → `-Quota (100MB)`

## Cmdlet Wrapper Patterns

### Read Cmdlet

```php
function exchange_get_something(string $identity): array
{
    $params = ['Identity' => $identity];
    return exchange_run_cmdlet('Get-Something', $params);
}
```

### Write Cmdlet

```php
function exchange_set_something(string $identity, string $value): array
{
    $params = [
        'Identity' => $identity,
        'Value' => $value,
    ];
    return exchange_run_cmdlet('Set-Something', $params);
}
```

### Write with Boolean Switch

```php
function exchange_toggle_something(string $identity, bool $enabled): array
{
    $params = [
        'Identity' => $identity,
        'Enabled' => $enabled,   // automatically → -Enabled:$true/$false
    ];
    return exchange_run_cmdlet('Set-Something', $params);
}
```

### Cmdlet with try/catch in script block (complex)

For cmdlets needing inline logic, use a script block callback with `exchange_run_cmdlet()`'s third parameter. See `exchange_get_all_mailbox_data()` (line 767) as reference.

## Handler Patterns

### Simple Read Handler

```php
function handle_your_read(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity required.']);
        return;
    }
    $result = exchange_get_something($identity);
    $output = exchange_parse_result($result);
    echo json_encode($output);
}
```

### Mutating Handler with Audit

```php
function handle_your_mutate(array $input): void
{
    $identity = trim((string)($input['identity'] ?? ''));
    if ($identity === '') {
        echo json_encode(['success' => false, 'message' => 'Identity required.']);
        return;
    }
    $result = exchange_set_something($identity, $input['value'] ?? '');
    $output = exchange_parse_result($result);
    exchange_audit_response('your_action', $input, json_encode($output));
    echo json_encode($output);
}
```

### exchange_parse_result()

Standard result parser (used across all handlers):

```php
function exchange_parse_result(array $result): array
{
    $decoded = $result['decoded'] ?? null;
    if ($decoded === null) {
        return ['success' => false, 'message' => 'PowerShell did not return valid JSON.'];
    }
    // Some cmdlets return array directly; others wrap in {success: [...]}
    if (isset($decoded['success']) && is_array($decoded['success'])) {
        return ['success' => true, 'data' => $decoded['success']];
    }
    if (!empty($decoded['success'])) {
        return $decoded;
    }
    if (!empty($decoded[0]['Error'])) {
        return ['success' => false, 'message' => $decoded[0]['Error']];
    }
    return ['success' => true, 'data' => $decoded];
}
```

## Kerberos Ticket Management

### Ticket Flow

```php
exchange_ensure_kerberos_ticket()  // ExchangePsRunner.php:216
  1. exec('klist -s 2>&1', $out, $exitCode)
  2. If $exitCode === 0 → ticket valid, return true
  3. exchange_get_credential() → username, password
  4. Build keytab via ktutil:
       add_entry -password -p user@REALM -k 1 -e aes256-cts-hmac-sha1-96
       write_kt /tmp/exchange_krb5.keytab
  5. kinit -k -t /tmp/exchange_krb5.keytab user@REALM
  6. unlink /tmp/exchange_krb5.keytab
  7. exec('klist -s') → verify
```

TGT lifetime: 10 hours (AD default), renewable 7 days.

### Manual Check

```bash
docker exec accesspilot_php klist -s && echo "Valid" || echo "Expired"
docker exec accesspilot_php klist
```

### Manual Renewal

```bash
docker exec accesspilot_php php -r '
require "/var/www/html/app/Infrastructure/PowerShell/ExchangePsRunner.php";
exchange_ensure_kerberos_ticket();
'
```

## Exchange Server Discovery

```php
exchange_discover_server()  // ExchangePsRunner.php:6
```

Three levels, auto-fallback:

### Level 1: Config NC
```php
$configNC = ldap_exchange_get_config_nc($ldapConn);
$query = "(&(objectClass=msExchExchangeServer)(msExchCurrentServerRoles:1.2.840.113556.1.4.804:=2))";
// Requires Enterprise read access to Configuration partition
```

### Level 2: Database
```php
$query = "objectClass=msExchMDB";
// Extract server name from each database's msExchOwningServer attribute
```

### Level 3: Mailbox Fallback
```php
$query = "(&(msExchMailboxGuid=*)(msExchHomeServerName=*))";
// Parse CN=Servers/CN=SERVERNAME from msExchHomeServerName DN
// No special permissions needed
```

The discovered FQDN must resolve in the container — handled by `resolve_exchange_hosts.php` at boot.

## Secret Storage

### Exchange Password Vault

Path: `/data/secure/ldap/exchange_secrets/{domain_key}.json`

```json
{
    "password": "enc:b850aa2b8...:8b49241b8d..."
}
```

PHP functions in `ldap_config_repository.php`:
- `ldap_exchange_secret_path(string $key): string` — builds file path (line 134)
- `ldap_read_exchange_secret(string $key): string` — reads + decrypts (line 176)
- `ldap_write_exchange_secret(string $key, string $password): bool` — encrypts + writes (line 202)
- `ldap_has_exchange_password(string $key): bool` — checks existence (line 220)

### Credential Resolution (exchange_get_credential, line 91)

1. Read `exchange.ps_username` from domain JSON
2. Empty → fallback to `bind_dn`
3. Read `ps_password` from `ldap_read_exchange_secret()`
4. Empty → fallback to `ldap_read_bind_password()` (LDAP bind password)
5. Return `['username' => ..., 'password' => ...]`

## Monitoring Data Format

All monitoring handlers return raw arrays from `exchange_run_cmdlet()`.

### Quota Handler Pattern

Quota values from PS format: `"10485760 (10 GB)"` — parsed in JS:
- `parseQuotaTriple(warn, send, recv)` → `{issueWarning, prohibitSend, prohibitReceive, status}` (line 1638)
- `renderAppliedQuota(mb)` → quota bar visualization (line 1603)

### Message Tracking

Search fields: sender, recipient, startDate, endDate (default last 7 days).

## JS Patterns

### Fetch Wrapper

```javascript
function parseJsonResponse(response) {
    return response.text().then(function(text) {
        try { return JSON.parse(text); }
        catch(e) {
            throw new Error('Invalid JSON response (' + response.status + '): ' + text.slice(0, 200));
        }
    });
}
```

### CSRF Token

```javascript
function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}
```

### Generic Action POST

```javascript
function doMailboxAction(action, identity, database, extraParams) {
    var body = { action: action, identity: identity };
    if (database) body.database = database;
    if (extraParams) Object.assign(body, extraParams);

    fetch('/api/index.php?endpoint=exchange', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': getCsrfToken()
        },
        body: JSON.stringify(body)
    })
    .then(parseJsonResponse)
    .then(function(data) {
        showExchangeFeedback(data.success, data.success ? 'Success' : 'Error', data.message || '');
        // refresh UI on success
        if (data.success && activeIdentity) doMailboxSearch(activeIdentity);
    });
}
```

## Testing

### PowerShell Connectivity

```bash
docker exec accesspilot_php pwsh -Command '
$uri = "http://DC-EX-MBX01.WHILDC.COM/PowerShell/"
$session = New-PSSession -ConnectionUri $uri -Authentication Kerberos
Invoke-Command -Session $session -ScriptBlock {
    Get-Mailbox -Identity "helpdesk" | Select-Object DisplayName,PrimarySmtpAddress | ConvertTo-Json
}
Remove-PSSession $session
'
```

### After Code Changes

```bash
# PHP changes
docker exec accesspilot_php php -r 'opcache_reset();'

# JS/CSS changes
# Hard refresh (Ctrl+F5) required — browser cache

# Kerberos ticket
docker exec accesspilot_php klist

# Exchange reachable
curl -v http://DC-EX-MBX01.WHILDC.COM/PowerShell/  # expect 401
```

### Logs

```bash
# Structured logs
ls /data/logs/{domain}/scripts_logs/Exchange/Mailbox/
ls /data/logs/{domain}/scripts_logs/Exchange/Group/
ls /data/logs/{domain}/scripts_logs/Exchange/Settings/

# CSV audit
tail -f /data/logs/app_audit_logs/audit-*.csv | grep exchange
```

## Common Issues

| Symptom | Cause | Fix |
|---------|-------|-----|
| "Cannot find PSSession" / 401 | Kerberos ticket expired | `exchange_ensure_kerberos_ticket()` auto-renews; check `klist` |
| "Access Denied" | Bind user lacks Exchange RBAC role | Grant `View-Only Recipients` (read) or `Recipient Management` (write) |
| "Server not found in Kerberos database" | IP in URI instead of FQDN | Always use FQDN `http://HOST.DOMAIN/PowerShell/` |
| JSON truncated / PS warning | `ConvertTo-Json` depth < 3 | Use `-Depth 3 -WarningAction SilentlyContinue` |
| "Could not resolve hostname" | Exchange server not in container DNS | Check `resolve_exchange_hosts.php` ran at boot, verify `/etc/hosts` |
| "Unexpected end of JSON input" (419) | CSRF token mismatch | Hard refresh page to get new session CSRF token |
| WARNING prefix in JSON output | PowerShell truncation warning | Already suppressed via `-WarningAction SilentlyContinue` |
| OU dropdown clipped | Parent container has `overflow:hidden` | Card must have `overflow-visible-card` class |
| Feedback card not showing | `.alert { display: flex }` overrides `#exchangeActionCard` | `#exchangeActionCard` has `display: block !important` |
| Exchange config missing | Domain JSON has no `exchange` block | Check System Config → Edit Domain → enable Exchange |

## OPcache

Reset after every PHP change:

```bash
docker exec accesspilot_php php -r 'opcache_reset();'
```
