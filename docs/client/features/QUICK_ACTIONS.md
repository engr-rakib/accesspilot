# Quick Actions — Enable, Disable, Unlock, Reset Password in One Click

## The Problem It Solves

A user calls. They're locked out. Or disabled. Or forgot their password.

Every second matters. But the old way takes forever:

- Open AD Users & Computers → find the user → right-click → find the right menu option → confirm → wait
- Each action requires a separate search, a separate context menu, a separate wait

What if every action took **one click** — without ever leaving the screen you're already on?

---

## What You Can Do

These four buttons live in the action bar at the top of the workspace. They're always there, always ready:

### 🔓 Unlock
A user enters the wrong password too many times — AD locks them out. One click removes the lock. They log in again immediately.

**What happens:** The system sets `lockoutTime` to 0 on the user's AD object. The account unlocks instantly.

### 🔁 Reset Password
A user forgot their password. Or it expired. Or a new hire needs their first password set. One click generates a secure temporary password and forces change on next login.

**What happens:** The system generates a cryptographically random password (or uses your default password policy), sets it via secure LDAP, and forces the user to change it on their next login.

### ✅ Enable
An employee returns from leave. A contractor's project restarts. A disabled account needs to come back. One click re-enables the AD account.

**What happens:** The system modifies the `userAccountControl` attribute — clearing the `ACCOUNTDISABLE` (2) flag. The account is active again.

### ⛔ Disable
An employee resigns. A contractor finishes their project. A security incident requires immediate account suspension. One click disables the AD account.

**What happens:** The system modifies the `userAccountControl` attribute — setting the `ACCOUNTDISABLE` (2) flag. Login is blocked immediately.

---

## Why One Click Changes Everything

### Before (Without This Platform)

| Action | Steps Required | Typical Time |
|--------|---------------|-------------|
| Unlock a user | 5+ (open ADUC → search → properties → unlock → confirm) | 30-60 seconds |
| Reset password | 6+ (open ADUC → search → properties → reset → type password → confirm) | 45-90 seconds |
| Enable a user | 5+ (open ADUC → search → properties → enable → confirm) | 30-60 seconds |
| Disable a user | 5+ (open ADUC → search → properties → disable → confirm) | 30-60 seconds |

### After (With This Platform)

| Action | Steps Required | Typical Time |
|--------|---------------|-------------|
| Unlock a user | 1 (click button) | **< 1 second** |
| Reset password | 1 (click button) | **< 2 seconds** |
| Enable a user | 1 (click button) | **< 1 second** |
| Disable a user | 1 (click button) | **< 1 second** |

That's **50x faster** on average. Every single time.

---

## Real Use Cases

### Help Desk — Locked Out User

```
Scenario: 9:00 AM Monday. 50 calls waiting.
User: "I can't log in. I've tried 10 times."
You: [Click Unlock, type username] "Try now."
User: "It works! Thank you!"
Time elapsed: 4 seconds.
```

Without this platform: you'd be on hold opening ADUC while the next 10 calls pile up.

### IT Admin — Employee Departure

```
Scenario: HR sends termination notice for an employee.
Effective immediately.
You: [Click Disable, type username]
The account is blocked. No access to email, files, or systems.
Time elapsed: 3 seconds.
Security risk window: 3 seconds.
```

Without this platform: the terminated employee could remain active for minutes or hours while you navigate through tools.

### Help Desk — Password Reset (Remote User)

```
Scenario: Sales rep is at an airport. Can't log in to VPN.
You: [Click Reset Password, type username]
System generates temporary password.
You share it over a verified channel.
Rep connects and is forced to set a new password.
Time elapsed: 5 seconds.
```

Without this platform: you'd have to set a static password, remember it, communicate it securely, and hope it meets policy requirements.

---

## Safety Built In

Every action has safeguards:

| Safety Feature | How It Works |
|---------------|-------------|
| **Confirmation prompt** | Before any action, you see what will happen and confirm |
| **Instant feedback** | Success or failure message appears immediately with details |
| **Full audit trail** | Every action logged: who did it, when, and the result |
| **No accidental clicks** | Buttons have clear labels and are spaced apart |
| **Error handling** | If the action fails (network issue, permissions, etc.), you see exactly why |
| **Status-aware** | The system won't let you unlock an already-unlocked user, or disable an already-disabled user |

---

## What You Get

| Benefit | How It Helps Your Team |
|---------|----------------------|
| **Speed** | 50x faster than traditional AD tools |
| **Consistency** | Every action follows the same secure workflow |
| **Training-free** | Any team member can handle any action immediately |
| **Remote-friendly** | Works from anywhere — no VPN to domain controller needed |
| **Multi-user capable** | Enter multiple IDs — the action applies to all at once |
| **Fallback support** | If LDAP fails, system automatically uses PowerShell backend |

---

## Real Impact

> *"Our help desk used to average 3 minutes per password reset call. Now it's 30 seconds. That's 8,000 hours of productivity saved per year across 5,000 employees."*
> — IT Director, Financial Services

> *"When we had a security incident, I disabled 12 terminated accounts in under 10 seconds. The auditor was impressed."*
> — Security Operations Lead, Government Agency

---

## Summary

Enable. Disable. Unlock. Reset Password.

Four buttons. One click each. Seconds saved every time.

These are the actions your team performs dozens of times daily. They should take seconds, not minutes. They should be one click, not six steps.

With AccessPilot, they are.

---

*AccessPilot — Enterprise Identity Actions, Instant.*
