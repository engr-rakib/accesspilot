# Implementation Guide — Email Analysis Tools

## Adding a New Tool

To add a new feature to Email Analysis Tools, follow these steps:

### Step 1: Domain Function

Add the core logic in the appropriate `app/Domain/Email/` file, or create a new one. Wrap in `if (!function_exists(...))`.

Example pattern:
```php
if (!function_exists('mt_new_check')) {
    function mt_new_check(string $input): array
    {
        // Logic here
        return ['success' => true, 'result' => $data];
    }
}
```

### Step 2: Controller Handler

In `email_tools.php`:

1. Add `require_once` for new domain file (if created)
2. Add `case 'new_action':` in the switch
3. Add `handle_new_action()` function

```php
function handle_new_action(array $input): void
{
    $param = trim((string)($input['param'] ?? ''));
    if ($param === '') {
        echo json_encode(['success' => false, 'message' => 'Param is required.']);
        return;
    }
    $result = mt_new_check($param);
    $result['success'] = true;
    echo json_encode($result);
}
```

### Step 3: View Tab Content

In `resources/views/pages/email/view.php`:

1. Add tab button in the tab bar:
```html
<div class="noc-tab-item" data-tab="newtool"><i class="fas fa-icon me-1"></i> New Tool</div>
```

2. Add tab pane:
```html
<div class="tab-pane" id="tab-newtool" style="display:none;">
    <!-- UI elements -->
</div>
```

### Step 4: JS Functions

In `email_actions.js`:

1. Add `bindNewTool()` in `init()`
2. Add `bindNewTool()`, `doNewTool()`, and `renderNewTool()` functions
3. Follow existing patterns for fetch + render + error handling

## DNS Resolution Behavior

`dns_get_record()` uses system resolver (Docker → host → systemd-resolved → internal DNS). For public domains like `gmail.com`, the internal DNS forwards to upstream resolvers which may or may not have the records.

The `dig` fallback queries public DNS servers directly:

```php
function dns_dig_query(string $domain, string $type = 'MX'): array
{
    $servers = ['8.8.8.8', '1.1.1.1', '9.9.9.9'];
    foreach ($servers as $ns) {
        $cmd = sprintf(
            'dig @%s %s %s +short 2>/dev/null',
            escapeshellarg($ns),
            escapeshellarg($type),
            escapeshellarg($domain)
        );
        exec($cmd, $output, $rc);
        if ($rc === 0 && !empty($output)) return $output;
    }
    return [];
}
```

Note: `escapeshellarg()` is essential to prevent shell injection.

## RBL Check Optimization

The original implementation used `gethostbyname()` which blocks indefinitely on unresponsive RBL servers. The fix uses `dig` with explicit timeouts:

- `+time=3`: Wait 3 seconds for response
- `+tries=1`: Single attempt, no retry
- `@8.8.8.8`: Bypass slow internal DNS

If more RBLs are added to `rbl_get_blacklists()`, the total scan time scales linearly (~3-5s per RBL worst case). Consider parallelizing with `proc_open()` if the list grows significantly.

## Testing

Run individual functions from CLI:

```bash
# Test DNS lookup
php -r "require 'app/Domain/Dns/dns_resolver.php'; print_r(dns_resolve_mx('example.com'));"

# Test SMTP
php -r "require 'app/Domain/Email/mail_tools.php'; print_r(mt_smtp_test('gmail-smtp-in.l.google.com', 25));"

# Test RBL (takes ~15-30s)
php -r "require 'app/Domain/Email/rbl_lookup.php'; print_r(rbl_check_ip('8.8.8.8'));"
```

Browser testing requires:
- Hard refresh (Ctrl+F5) after JS/CSS changes
- OPcache reset after PHP changes: `docker exec accesspilot_php php -r 'opcache_reset();'`
- Check Docker logs for PHP-FPM errors: `docker logs accesspilot_php 2>&1 | grep "email_tools\|500"`

## Common Issues

| Symptom | Cause | Fix |
|---------|-------|-----|
| 500 empty response | Include path wrong | Use `__DIR__ . '/../../../'` not `../../` |
| Timeout on blacklist | `gethostbyname` hang | Use `dig` with `+time=3 +tries=1` |
| HTML error response | PHP fatal error during require | Check Docker logs for trace |
| 419 CSRF error | Token mismatch | Hard refresh page, clear session cookies |
| MX records empty | Internal DNS missing public records | `dig` fallback handles this automatically |

## OPcache Note

Since the `app/` directory is bind-mounted `ro` (read-only) in the Docker compose config, PHP file changes are visible immediately after write. However, OPcache caches compiled files — always run `opcache_reset()` after PHP changes.

The `public/` directory is also `ro` — JS/CSS changes take effect on browser refresh since nginx serves them directly (not through PHP).
