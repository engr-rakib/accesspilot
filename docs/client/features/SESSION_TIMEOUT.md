# AccessPilot — Session Timeout & Auto-Logout

> **Language:** Banglish
> **Purpose:** Kivabe session expire hoy, auto-logout hoy, and kivabe control kora jay.

---

## Ki hoi inactive thakle?

**Normal login:** **15 min** kono button click / keyboard / mouse na korle → **auto-logout hoye login page e chole jabe**.
**Remember Me select korle:** **2 hour (120 min)** inactive thakleo logout hobe na — tarpor auto-logout.

| Time | Ki hobe |
|------|---------|
| 0-14 min | Kichui hoy na — normal use korte paren |
| **14 min** | Browser detect kore inactivity → **redirect to login page** (`?message=session_expired`) |
| 14-15 min | Jodi kono API call koren → server 419 return kore → redirect |
| **15 min** | **Server forcefully session destroy kore** — `$_SESSION['last_activity']` expired |
| **119 min** (remember-me) | Browser redirect (server er 1 min aage) |
| **120 min** (remember-me) | **Server forcefully session destroy kore** |

Kono button click dorkar nai — nijei logout hoye jabe.

---

## Kivabe kaj kore?

### 1. Client-Side Timer (Browser)

```javascript
var rememberMe = <?php echo !empty($_SESSION['remember_me']) ? 'true' : 'false'; ?>;
// Server threshold: plain = 900s (15 min), remember-me = 7200s (2 hrs)
var IDLE_TIMEOUT = rememberMe ? 7140000 : 840000; // 119 min or 14 min
```

- Browser e timer chole — remember-me thakle **2hr**, na thakle **14 min**
- Mouse, keyboard, touch, scroll — **kono activity paile timer reset**
- Timer expire hole → **direct login page e redirect** (`login.php?message=session_expired`)
- Multiple tab thakleo prottek tab e alada timer chole

### 2. Server-Side Check (API)

```php
// api/index.php — CSRF validation er sathe
$maxIdle = !empty($_SESSION['remember_me']) ? 7200 : 900;
if (last_activity > $maxIdle) {
    session_destroy();
    http_response_code(419);
    echo json_encode(['session_expired' => true]);
    exit;
}
```

- Server e `$_SESSION['last_activity']` track kore
- State-changing API call e (POST/PUT/DELETE) server **last_activity check** kore
- 15 min / 2hr (remember_me) expire hole → **session destroy + 419 return**
- Browser 419 paile → **redirect to login page** (`?message=session_expired`)

### 3. Session Guard (Page Load)

```php
// session_guard.php — prottek SPA request e
$maxIdle = !empty($_SESSION['remember_me']) ? 7200 : 900;
```

- Prottek admin page request e server last_activity check kore
- Expire hole → session destroy + `login.php?message=session_expired` e redirect
- Remember-me cookie 5-min session regeneration er poro **re-assert** hoye 2hr thake

### 4. CSRF Token Validation

- API call e CSRF token mismatch holeo → server 419 return kore
- Session destroyed hoye jay → new session e redirect

---

## Remember Me Feature

| Feature | Normal Login | Remember Me |
|---------|-------------|-------------|
| Timeout | **15 min** | **2 hours** |
| Session cookie | Browser close e expire | 2hr (rolling — regenerate er sathe refresh) |
| Use case | Shared computer | Personal device |

`remember_me` option select korle 2hr porjonto inactive thakleo auto-logout hobe na.

---

## Session Expired Message

Auto-logout hole login page e **notice banner show hoy** (`login.php` e `?message=` handle kore):

- `?message=session_expired` → "Your session ended due to inactivity. Please sign in again."
- `?message=session_terminated` → "Your session was terminated by an administrator. Please sign in again."

> XSS-safe: shudhu whitelisted value (`session_expired` / `session_terminated`) accept hoe, onno kichu ignore.

---

## Common Questions

### "Amra kivae janbo session expire hoye geche?"
- **Browser tab e kono kaj na korle 14 min por** → direct login page, tar sathe **"session expired" notice**
- **Kono button click korle** → API 419 + "session expired" notice
- **Page refresh korleo** → session guard check kore redirect kore

### "14 min e keno redirect? Server e to 15 min!"
Client timer (14 min) **server er aage fire** — user jate notification pai or unexpected na hoy. 1 min gap intentional — jodi client timer fail kore, server catch korbe. (Remember-me case e: 119 min client vs 120 min server.)

### "Multiple tab e ki hobe?"
Prottek tab e alada timer. 1 tab a active thakle onno tab o active dhora hobe na. **Jekono tab e inactivity timer fire gele, oi tab redirect hobe.**

### "Ki kora jay jodi timeout customize korte chai?"
Server-side timeout: `session_guard.php:39` e `$maxIdle` value change korte hobe (ar `api/index.php:124` eo same):
```php
$maxIdle = 1800; // 30 min
```
Client-side timer: `master.php` e `IDLE_TIMEOUT` value change:
```javascript
var IDLE_TIMEOUT = 1740000; // 29 min (server er 1 min aage)
```
> **2hr / 7200s / cookie `time()+7200` / `session.gc_maxlifetime` — ei 4 jaygay consistent thakte hobe**, na hole server-client mismatch hobe. Duplicate likha avoid korte ekti config constant banano jete pare.

---

## Summary

```
Normal:  Login ──► 14 min inactive ──► Client redirect to login page
                ► 15 min inactive ──► Server destroy session
Remember: Login ──► 119 min inactive ──► Client redirect to login page
                ► 120 min inactive ──► Server destroy session
                ► Button click er por API e 419 ──► Redirect

Ei 3 layer mileye user ke:
✅ Automatically logout kore (button click charai)
✅ Login page e "Session expired" notice show kore
✅ Direct login page e pathiye dey
```