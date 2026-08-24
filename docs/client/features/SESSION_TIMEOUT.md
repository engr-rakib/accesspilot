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
| **14 min** | Browser detect kore inactivity → **redirect to login page** ("session expired" notice) |
| 14-15 min | Jodi kono action koren → server session expire detect kore → redirect |
| **15 min** | **Server forcefully session end kore** |
| **119 min** (remember-me) | Browser redirect (server er 1 min aage) |
| **120 min** (remember-me) | **Server forcefully session end kore** |

Kono button click dorkar nai — nijei logout hoye jabe.

---

## Kivabe kaj kore?

### 1. Browser-Side Timer
- Browser e timer chole — remember-me thakle **2hr**, na thakle **14 min**
- Mouse, keyboard, touch, scroll — **kono activity paile timer reset**
- Timer expire hole → **direct login page e redirect** ("session expired" notice)
- Multiple tab thakleo prottek tab e alada timer chole

### 2. Server-Side Check
- Server e "last activity" track kore
- Kono action perform korle server **last activity check** kore
- 15 min / 2hr (remember_me) expire hole → **session end + redirect**
- Browser session expired signal paile → **redirect to login page** ("session expired" notice)

### 3. Session Check on Every Page
- Prottek page request e server last activity check kore
- Expire hole → session end hoye "session expired" notice shoho login page e redirect
- Remember-me thakle 2hr porjonto re-assert hoy

### 4. Security Token Validation
- Security token mismatch holeo → server session invalid kore dey
- Session end hoye jay → new session e redirect

---

## Remember Me Feature

| Feature | Normal Login | Remember Me |
|---------|-------------|-------------|
| Timeout | **15 min** | **2 hours** |
| Session cookie | Browser close e expire | 2hr (rolling — refresh er sathe reset) |
| Use case | Shared computer | Personal device |

`remember_me` option select korle 2hr porjonto inactive thakleo auto-logout hobe na.

---

## Session Expired Message

Auto-logout hole login page e **notice banner show hoy**:

- "session_expired" → "Your session ended due to inactivity. Please sign in again."
- "session_terminated" → "Your session was terminated by an administrator. Please sign in again."

> XSS-safe: shudhu whitelisted message accept hoe, onno kichu ignore.

---

## Common Questions

### "Amra kivae janbo session expire hoye geche?"
- **Browser tab e kono kaj na korle 14 min por** → direct login page, tar sathe **"session expired" notice**
- **Kono button click korle** → server session expiry + "session expired" notice
- **Page refresh korleo** → session check expire pabe → redirect

### "14 min e keno redirect? Server e to 15 min!"
Client timer (14 min) **server er aage fire** — user jate notification pai or unexpected na hoy. 1 min gap intentional — jodi client timer fail kore, server catch korbe. (Remember-me case e: 119 min client vs 120 min server.)

### "Multiple tab e ki hobe?"
Prottek tab e alada timer. 1 tab a active thakle onno tab o active dhora hobe na. **Jekono tab e inactivity timer fire gele, oi tab redirect hobe.**

### "Ki kora jay jodi timeout customize korte chai?"
Timeout values portal er setting theke control kora jay — administrator er jonno ekta central setting. (Browser timer 1 min aage fire — server er sathe consistent rakha hoy.)

> **Jekono change e client ar server er timeout consistent rakhte hobe**, na hole mismatch hobe. Single central setting e change korlei problem nei.

---

## Summary

```
Normal:  Login ──► 14 min inactive ──► Browser redirect to login page
                ► 15 min inactive ──► Server ends session
Remember: Login ──► 119 min inactive ──► Browser redirect to login page
                ► 120 min inactive ──► Server ends session
                ► Button click korleo expire hole → Redirect

Ei 3 layer mileye user ke:
✅ Automatically logout kore (button click charai)
✅ Login page e "Session expired" notice show kore
✅ Direct login page e pathiye dey
```