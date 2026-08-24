# AccessPilot — First Login, Security & Anti-Theft Protection

> **A complete look at the first-run flow, the device lock, the administrator lifecycle, and how the application protects itself from theft.**

---

## Table of Contents

1. [How Your Data Is Stored](#1-how-your-data-is-stored)
2. [The Setup Lock (Device Lock)](#2-the-setup-lock-device-lock)
3. [Initial State (Fresh Server)](#3-initial-state-fresh-server)
4. [First-Run Setup Flow](#4-first-run-setup-flow)
5. [Administrator Lifecycle](#5-administrator-lifecycle)
6. [The Built-In Fallback Admin](#6-the-built-in-fallback-admin)
7. [Anti-Theft Protection](#7-anti-theft-protection)
8. [Recovering Full Admin Access](#8-recovering-full-admin-access)
9. [Complete Workflow Diagram](#9-complete-workflow-diagram)
10. [Security Model Summary](#10-security-model-summary)
11. [Attack Scenarios & Protections](#11-attack-scenarios--protections)
12. [Where Your Data Lives (Summary)](#12-where-your-data-lives-summary)

---

## 1. How Your Data Is Stored

### 1.1 Two Storage Layers

The application uses **two separate storage layers** with different security profiles:

```
┌─────────────────────────────────────────────────────────────────────┐
│                     STORAGE ARCHITECTURE                              │
│                                                                       │
│  ┌─────────────────────────────────────┐   ┌───────────────────────┐ │
│  │  PRODUCT FILES (travel with the     │   │  SECURE VAULT         │ │
│  │  product when copied)               │   │  (Docker-managed)     │ │
│  │                                     │   │                       │ │
│  │  App folder                         │   │  Protected storage    │ │
│  │    ├── Setup lock           ◄─ LOCK │   │    ├── User accounts  │ │
│  │    └── Fallback admin       ◄─ KEY  │   │    ├── Roles          │ │
│  │                                     │   │    └── ...            │ │
│  │  This is the product itself.        │   │    ├── License        │ │
│  │  Copied with any file transfer.     │   │    └── Directory      │ │
│  │                                     │   │       connections    │ │
│  │  ⚠ Less protected (file access)     │   │                       │ │
│  │  → Holds only setup data            │   │  This storage lives in│ │
│  │  → No real user credentials         │   │  a Docker-managed     │ │
│  │  → No directory connections         │   │  area, NOT copied     │ │
│  │  → No license data                  │   │  with the product.    │ │
│  │                                     │   │                       │ │
│  └─────────────────────────────────────┘   │  ✅ Secure (volume)   │ │
│                                            │  → All real users     │ │
│                                            │  → All credentials    │ │
│                                            │  → Directory settings │ │
│                                            │  → License files      │ │
│                                            └───────────────────────┘ │
└─────────────────────────────────────────────────────────────────────┘
```

### 1.2 Key Principle

> **The setup lock and the secure vault are created together on the first run, and together they bind the application to a specific server instance.**
>
> Copying the product alone gives you an empty shell with no users, no data, and no configuration.

### 1.3 What Lives Where

| Item | Location | Created By | Purpose | Copied With the Product? |
|------|----------|-----------|---------|-------------|
| Setup lock | App folder | First-run setup | Device lock — prevents re-initialization | ❌ Should NOT be copied |
| Fallback admin | App folder | Product team | Fail-safe admin (default sign-in) | ✅ Part of product |
| User accounts | Secure vault | Setup or admin | Real user credentials | ❌ Docker-managed |
| Roles | Secure vault | Setup | Role definitions | ❌ Docker-managed |
| License | Secure vault | Admin (web UI) | License certificate | ❌ Docker-managed |
| Directory connections | Secure vault | Admin (web UI) | Your AD server info | ❌ Docker-managed |

---

## 2. The Setup Lock (Device Lock)

### 2.1 What Is It?

A simple timestamp record created by the first-run setup on the very first visit:

```
Device lock created: 2026-06-19 10:30:00
```

### 2.2 How It Works as a Device Lock

The lock binds the application to a specific server by controlling two critical behaviors:

```
┌─────────────────────────────────────────────────────────────────┐
│                    DEVICE LOCK — GATEKEEPER                       │
│                                                                   │
│  ┌──────────────────────┐         ┌───────────────────────────┐  │
│  │  First-run setup     │         │  Account sign-in service  │  │
│  │                      │         │                           │  │
│  │  if (no lock) {      │         │  if (vault empty) {       │  │
│  │    create vault      │         │    load fallback admin    │  │
│  │    create lock       │         │  }                        │  │
│  │  }                   │         │  (lock alone does not     │  │
│  │                      │         │   block sign-in)          │  │
│  │  LOCK → Prevents     │         │                           │  │
│  │         re-creation   │         │  LOCK → Does NOT block    │  │
│  │                      │         │         fallback sign-in  │  │
│  └──────────────────────┘         └───────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

### 2.3 The Lock-Vault Binding

```
State: Setup complete
       │
       ├── Setup lock EXISTS
       │
       └── Secure vault has an admin user

These two are CREATED TOGETHER and TOGETHER they form the
"this server is set up" assertion. Neither one alone is sufficient.
```

### 2.4 Lock States and Meanings

| State | Lock | Vault | Meaning |
|-------|------|-------|---------|
| A | Missing | Empty | Fresh server — first run pending |
| B | Exists | Has admin | Normal operation — server is bound |
| C | Exists | Empty | Broken state — vault deleted but lock remains |
| D | Missing | Has admin | Re-initialization allowed; existing data is never overwritten |
| E | Missing | Empty | Full reset — setup will recreate both |

---

## 3. Initial State (Fresh Server)

### 3.1 What Exists and What Doesn't

**Before the first visit:**

```
Product files                         Secure vault (Docker-managed)
  ├── Fallback admin  ✅             (empty storage)
  ├── Setup lock      ❌
  └── ...             ...

(Other product files are present,
 but no runtime data yet)
```

### 3.2 When the First-Run Setup Runs

The first-run setup runs only when a user first opens the portal home page. It does not run on:
- Visits to the sign-in page
- Simply loading the portal without opening the home page

This means: if someone opens the sign-in page directly before the home page has been visited, the first-run setup has not run yet. In that case, the fallback admin handles sign-in until setup completes.

### 3.3 The Complete First-Visit Sequence

```
User opens browser → portal address
       │
       ├── Portal receives the request
       │   └── opens the home page → triggers first-run setup
       │       └── No lock? → CREATE admin in vault + CREATE lock
       │
       ├── Not signed in yet → redirect to sign-in page
       │
       └── User sees the sign-in page
```

---

## 4. First-Run Setup Flow

### 4.1 What Happens

```
First-run setup
       │
       ├── Lock missing?
       │       │
       │       YES ─▶ Create vault admin + create lock
       │       │        Then ensure roles, sessions, registrations exist
       │       │        (these are safe to refresh every time)
       │       │
       │       NO ──▶ Skip admin creation (already done)
       │              Still ensure core files exist (safe, repeatable)
       │
       └── Done — portal is ready for first sign-in
```

### 4.2 What Gets Created

```
AFTER FIRST-RUN SETUP:

Secure vault:
  ├── Default admin account (password change required on first sign-in)
  ├── Default roles (full access, standard user)
  ├── Empty lists for authenticated users and registration requests
  └── Empty slots for directory connections and license
      (both configured later from the web UI)

App folder:
  └── Setup lock (timestamp)
```

### 4.3 Safety on Repeat Runs

- If a file already exists, the setup does **not** overwrite it.
- **Second run:** Lock exists → admin is not recreated; everything else already exists.
- **Vault deleted but lock exists:** Lock exists → admin is not recreated; vault stays empty.
- **Lock deleted but vault exists:** Lock missing → setup runs → vault data already exists → nothing overwritten → lock is recreated.

---

## 5. Administrator Lifecycle

### 5.1 Phase 1: Default Administrator

```
State: Fresh server, first-run setup just ran
       │
       ├── Vault has "admin" signed in with the default password
       ├── Password change is required
       ├── Full system access
       │
       └── This admin exists ONLY in the secure vault
           (the fallback admin is a separate, static record)
```

### 5.2 Phase 2: First Sign-In

```
Administrator signs in: admin / <default password>
       │
       ├── Vault has "admin" → credentials match
       ├── Password change required? → YES
       │
       └── Result: sign-in succeeds, user is taken to the password
           change screen immediately
```

### 5.3 Phase 3: Password Change

```
Administrator sets a new password
       │
       ├── Current password verified → OK
       ├── New password is stored securely (never as plain text)
       ├── Password-change requirement is cleared
       └── The vault is updated with the new password
```

### 5.4 Phase 4: Fallback Admin Locked

```
AFTER PASSWORD CHANGE:

  Fallback admin still holds the DEFAULT password
  Vault admin now holds the NEW password

  These two passwords are DIFFERENT.
       │
       ▼
  If the vault is deleted later and the fallback admin activates:
    the default password no longer matches → sign-in FAILS

  The fallback admin is PERMANENTLY LOCKED.
  The only way back is a recovery procedure (see Section 8).
```

### 5.5 Phase 5: Production Use

```
After the password change, administrator:
       │
       ├── Connects the portal to your Active Directory (web UI)
       ├── Uploads the license
       ├── Creates additional admin/user accounts
       ├── Sets up your AD domains
       │
       └── Vault now has:
           ├── "admin" with the new password
           ├── "john" (real admin)
           ├── "jane" (regular user)
           └── ... (multiple users)

  Fallback admin → NEVER used again (vault is never empty)
  Setup lock → Prevents setup from overwriting the vault
```

### 5.6 Complete Administrator Lifecycle Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        ADMIN LIFECYCLE                                    │
│                                                                           │
│  ┌────────────┐     ┌──────────┐     ┌──────────────┐     ┌──────────┐  │
│  │ First-run  │────▶│  First    │────▶│   Password   │────▶│ Fallback │  │
│  │ setup      │     │  Sign-in  │     │   Change     │     │ Admin    │  │
│  │ creates    │     │          │     │              │     │ Locked   │  │
│  │ admin in   │     │ admin /  │     │ New password │     │           │  │
│  │ vault      │     │ default  │     │ stored —     │     │ fallback │  │
│  │            │     │ password │     │ requirement  │     │ no longer│  │
│  │ must       │     │          │     │ cleared      │     │ matches  │  │
│  │ change     │     │ Forced   │     │              │     │           │  │
│  │ password   │     │ redirect │     │              │     │           │  │
│  └────────────┘     └──────────┘     └──────────────┘     └──────────┘  │
│                                                                           │
│                                  │                                        │
│                                  ▼                                        │
│                        ┌──────────────────┐                              │
│                        │  Production Use   │                              │
│                        │                   │                              │
│                        │  - Add users      │                              │
│                        │  - Connect AD     │                              │
│                        │  - Set license    │                              │
│                        │  - Create admins  │                              │
│                        └──────────────────┘                              │
│                                                                           │
│              Fallback admin → PERMANENTLY BYPASSED                        │
│              (vault has users now, so it never activates)                 │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 6. The Built-In Fallback Admin

### 6.1 What Is It?

A static, read-only fallback record that ships with the product. It:
- Uses the **default password** (changeable only by recovery steps)
- Holds full system access
- Is clearly marked as an internal, setup-only account

### 6.2 When It Activates

The fallback activates **only** when the secure vault is empty. In normal operation the vault always has users, so the fallback is never consulted.

**The setup lock does NOT block this.** Only an empty vault matters.

### 6.3 When It Becomes Permanently Locked

**Condition:** Once the vault has at least one user account.

```
BEFORE:
  vault = empty → fallback admin is loaded

AFTER FIRST PASSWORD CHANGE:
  vault = has admin with the NEW password
  → vault is not empty → fallback is NEVER USED AGAIN
```

**Even if the vault is deleted later:**
```
  vault = empty → fallback admin is loaded again
  → BUT: the default password no longer matches (password was changed)
  → Sign-in with the default password FAILS
```

**Why it's locked:**
```
Fallback admin password  →  default value
Admin's new password     →  something entirely different

These are DIFFERENT. So the fallback is permanently locked out.
```

### 6.4 The Three-Stage Lock

```
Stage 1: Setup creates admin
         ├── vault admin uses the default password
         ├── fallback admin uses the same default password
         └── BOTH match

Stage 2: Admin changes password
         ├── vault password changes → matches the NEW password
         ├── fallback password STAYS → still the default
         └── PASSWORDS DIVERGE → fallback is now "locked"

Stage 3: Real users created
         ├── vault now has multiple users
         ├── fallback is NEVER consulted
         └── (vault not empty → fallback bypassed)
```

---

## 7. Anti-Theft Protection

### 7.1 What Can Be Stolen

| Item | Stolen? | What thief gets |
|------|---------|-----------------|
| Product files (file transfer) | ✅ | Empty application shell |
| Container image | ✅ | Same as product files |
| Setup lock | ✅ | Lock record (harmless alone) |
| Fallback admin | ✅ | Default credentials only |
| User accounts | ❌ | Secure vault — not accessible |
| License | ❌ | Secure vault — not accessible |
| Directory connections | ❌ | Secure vault — not accessible |
| Real user passwords | ❌ | Secure vault — not accessible |

### 7.2 Theft Scenario Analysis

#### Scenario A: Thief copies only the product files (no lock, no vault)

```
Thief runs on a new server:
       │
       ├── No setup lock → first-run setup creates admin + lock
       ├── Admin: admin / default password
       ├── No license → portal shows "license required"
       ├── No directory connections → cannot reach your AD
       └── No real users → only the default admin exists

Severity: LOW
Mitigation: Thief has an empty shell with default credentials only.
            The license requirement blocks full usage.
```

#### Scenario B: Thief copies product files + lock (common file-transfer mistake)

```
Thief runs on a new server:
       │
       ├── Setup lock EXISTS
       ├── Vault is EMPTY (fresh Docker storage)
       │
       ├── Sign-in handler: vault empty → fallback admin ACTIVATED
       │   ├── admin / default password works
       │   └── But: forced password change → new password stored → vault created
       │
       └── Either way: NO REAL DATA is compromised

Severity: LOW
Mitigation: Thief gets only default credentials. No license, no directory
            connections, no users.
```

#### Scenario C: Thief copies product files + fallback admin

```
Thief runs on a new server:
       │
       ├── No lock → setup creates admin in vault
       ├── Vault admin uses the default password
       ├── Fallback admin uses the same default password
       │
       ├── Sign-in works with admin / default password
       ├── Forced password change
       ├── No license → restricted mode
       └── No directory connections → can't reach your AD

Severity: LOW
Mitigation: Same as a fresh install. Thief gets an empty shell.
```

#### Scenario D: Thief gains container access and tries to read the vault

```
Thief reads the secure vault:
       │
       ├── Passwords: stored scrambled (never plain text)
       ├── Directory bind credentials: encrypted
       ├── License: encrypted
       │
       └── Attacker must crack the scrambling to get passwords
           — extremely slow, effectively impractical

Severity: MEDIUM
Mitigation: Passwords are never stored in plain text; sensitive data is
            encrypted. Even with the vault in hand, an attacker cannot
            directly sign in as real users without cracking each value.
```

### 7.3 The Lock as a Theft Deterrent

```
PRODUCT FILES TRANSFERRED WITH THE LOCK:
  ┌──────────────────────────────────────────────┐
  │  Thief copies all product files to Server B  │
  │                                              │
  │  Server B state:                             │
  │    ├── Setup lock            ✅ Copied       │
  │    └── Secure vault          EMPTY           │
  │                                              │
  │  Sign-in service:                           │
  │    ├── Vault empty?          YES            │
  │    ├── Load fallback admin                 │
  │    ├── admin / default works               │
  │    └── Forced password change              │
  │                                              │
  │  RESULT: Thief gets a default shell.         │
  │  No license, no directory connections,       │
  │  no real users. Fallback locked after        │
  │  the first password change.                  │
  └──────────────────────────────────────────────┘
```

### 7.4 Why the Default Password Is Not a Real Threat

```
Common concern: "the default password is known — anyone can sign in!"

Reality:
1. First sign-in → FORCED password change
   → The admin MUST set a new password immediately

2. After the change → the fallback admin is LOCKED
   → Its default password no longer matches the vault
   → Even if a deleted vault triggers the fallback, sign-in fails

3. Even if a thief signs in with the default credentials:
   → NO license → restricted mode (AD features unavailable)
   → NO directory connections → can't reach your systems
   → NO users → nothing to steal
   → Rate limited → 5 attempts = 30 min lockout

4. License requirement:
   → Without a valid license, only the license upload page is available
   → Full features stay blocked
```

---

## 8. Recovering Full Admin Access

### 8.1 When Would You Need Recovery?

| Situation | Reason | Method |
|-----------|--------|--------|
| Forgotten admin password | Cannot sign in with the new password | Method A or B |
| Vault damage | User data damaged or deleted | Method C |
| Server migration | Move the portal to a new server cleanly | Method D |
| Testing | Need to reset to a known state | Method B |

### 8.2 Method A: Full Reset

**Use when:** The admin password is forgotten and a clean start is acceptable.

- Stops the portal
- Removes the setup lock and vault data
- Restarts the portal
- On first visit, a fresh default admin is created and a password change is forced

**Result:** A fresh admin is created. All previous users, directory connections, and license are **LOST**. Use this only when saving data is not possible.

### 8.3 Method B: Replace the Fallback Admin's Password

**Use when:** You need a working sign-in again without putting other data at risk.

- A known temporary password is prepared by the support team
- The fallback record is updated to use it
- The vault is cleared to trigger the fallback, then setup recreates the vault
- Sign in with the temporary password, then change it

**Risk:** The clear is required, so this is a last resort. The support/seller team performs it — see the portal admin guide.

### 8.4 Method C: Repair a Specific Account

**Use when:** A specific user's data is damaged but other users must be preserved.

- The vault's admin entry is corrected with a known password and a forced-change flag
- Sign in with the temporary password (change forced)

**Result:** Only the admin password is reset. All other users, directory connections, and license are preserved.

### 8.5 Method D: Clean Server Migration

**Use when:** Moving the entire portal to a new server while preserving all data.

- Back up the app folder and the secure vault data
- Transfer the backup to the new server
- Remove the setup lock on the new server (it is recreated automatically)
- Start the portal

**Result:** All users, settings, and license are migrated. The admin password stays unchanged.

> **These procedures involve server-level access.** Contact your implementation team or follow the portal admin guide — no guessing needed.

---

## 9. Complete Workflow Diagram

### 9.1 Fresh Install → Production

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     COMPLETE WORKFLOW                                    │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                           │
│  ┌──────────────┐                                                        │
│  │ DEPLOY        │                                                        │
│  │ Copy product │                                                        │
│  │ files to the │                                                        │
│  │ server       │                                                        │
│  └──────┬───────┘                                                        │
│         │                                                                 │
│         ▼                                                                 │
│  ┌──────────────┐                                                        │
│  │ BUILD & START │                                                        │
│  │ start portal │                                                        │
│  └──────┬───────┘                                                        │
│         │                                                                 │
│         ▼                                                                 │
│  ┌──────────────────────────────────┐                                   │
│  │ FIRST VISIT (home page)          │                                   │
│  │                                  │                                   │
│  │ First-run setup:                 │                                   │
│  │   ├── Lock missing? → YES       │                                   │
│  │   ├── Create admin account      │                                   │
│  │   │   (admin / default)         │                                   │
│  │   ├── Create setup lock         │                                   │
│  │   └── Create roles, lists, etc. │                                   │
│  └──────────────┬───────────────────┘                                   │
│                 │                                                        │
│                 ▼                                                        │
│  ┌──────────────────────────────────┐                                   │
│  │ REDIRECT TO SIGN-IN              │                                   │
│  │                                  │                                   │
│  │ User sees the sign-in form       │                                   │
│  └──────────────┬───────────────────┘                                   │
│                 │                                                        │
│                 ▼                                                        │
│  ┌──────────────────────────────────┐                                   │
│  │ FIRST SIGN-IN                    │                                   │
│  │                                  │                                   │
│  │ admin / default password         │                                   │
│  │                                  │                                   │
│  │   ├── Credentials verified       │                                   │
│  │   ├── Password change required?  │                                   │
│  │   │   → YES                     │                                   │
│  │   └── Redirect to change screen │                                   │
│  └──────────────┬───────────────────┘                                   │
│                 │                                                        │
│                 ▼                                                        │
│  ┌──────────────────────────────────┐                                   │
│  │ FORCED PASSWORD CHANGE           │                                   │
│  │                                  │                                   │
│  │ Admin sets a new password        │                                   │
│  │   ├── Current password verified │                                   │
│  │   ├── New password stored       │                                   │
│  │   └── Requirement cleared       │                                   │
│  └──────────────┬───────────────────┘                                   │
│                 │                                                        │
│                 ▼                                                        │
│  ┌──────────────────────────────────┐  ┌──────────────────────────────┐ │
│  │ DASHBOARD (full access)          │  │ FALLBACK ADMIN LOCKED        │ │
│  │                                  │  │                              │ │
│  │ Can now:                         │  │ fallback password no longer  │ │
│  │   ├── Connect Active Directory  │  │ matches the vault            │ │
│  │   ├── Upload license            │  │ Default password disabled    │ │
│  │   ├── Create users              │  │                              │ │
│  │   └── Manage the portal         │  │ PERMANENTLY LOCKED           │ │
│  └──────────────────────────────────┘  └──────────────────────────────┘ │
│                                                                           │
│  ┌────────────────────────────────────────────────────────────────────┐ │
│  │ PRODUCTION STATE                                                    │ │
│  │                                                                      │ │
│  │  Vault: has users, roles, settings                                   │ │
│  │  Lock: exists (prevents re-initialization)                           │ │
│  │  License: uploaded (full features)                                  │ │
│  │  Directory: connected to Active Directory                           │ │
│  │  Fallback admin: existing but NEVER consulted                       │ │
│  └────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘
```

### 9.2 Theft Scenario Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     THEFT FLOW                                           │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                           │
│  ┌──────────────────┐                                                     │
│  │ THIEF COPIES      │                                                     │
│  │ product files     │                                                     │
│  │ from Server A to  │                                                     │
│  │ Server B          │                                                     │
│  └────────┬─────────┘                                                      │
│           │                                                               │
│           ▼                                                               │
│  ┌──────────────────────────────────┐                                    │
│  │ Server B State:                  │                                    │
│  │                                  │                                    │
│  │  App folder:                     │                                    │
│  │    ├── setup lock  ✅            │  ← Copied from Server A           │
│  │    └── fallback admin ✅         │  ← Part of the product            │
│  │                                  │                                    │
│  │  Secure vault:         EMPTY     │  ← NOT copied (Docker-managed)    │
│  │  License:              MISSING   │  ← NOT copied (Docker-managed)    │
│  │  Directory connections: MISSING  │  ← NOT copied (Docker-managed)    │
│  │  Real users:           MISSING   │  ← NOT copied (Docker-managed)    │
│  └────────┬─────────────────────────┘                                    │
│           │                                                               │
│           ▼                                                               │
│  ┌──────────────────────────────────┐                                    │
│  │ SIGN-IN ATTEMPT                  │                                    │
│  │                                  │                                    │
│  │ Sign-in service:                 │                                    │
│  │   ├── Vault empty → YES         │                                    │
│  │   ├── Load fallback admin       │                                    │
│  │   └── Return admin with default │                                    │
│  │       password                  │                                    │
│  │                                  │                                    │
│  │ Sign-in succeeds: admin /       │                                    │
│  │ default password                │                                    │
│  │                                  │                                    │
│  │ BUT:                            │                                    │
│  │   ├── No license → restricted   │                                    │
│  │   ├── No directory connections  │                                    │
│  │   └── No users → nothing to use │                                    │
│  └────────┬─────────────────────────┘                                    │
│           │                                                               │
│           ▼                                                               │
│  ┌──────────────────────────────────┐                                    │
│  │ THIEF GETS ONLY:                 │                                    │
│  │                                  │                                    │
│  │  ✅ Empty app shell              │                                    │
│  │  ✅ Default credentials only     │                                    │
│  │  ❌ No real user data            │                                    │
│  │  ❌ No directory credentials     │                                    │
│  │  ❌ No license                   │                                    │
│  │  ❌ No configuration             │                                    │
│  └──────────────────────────────────┘                                    │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 10. Security Model Summary

### 10.1 Six Layers of Defense

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    DEFENSE IN DEPTH                                       │
│                                                                           │
│  Layer 1: Forced Password Change                                         │
│  ─────────────────────────────                                            │
│  First sign-in redirects to a mandatory password change.                 │
│  The default password is single-use only.                                │
│                                                                           │
│  Layer 2: Secure Vault Storage                                           │
│  ──────────────────────────                                               │
│  Real user credentials live in a Docker-managed storage area              │
│  that does NOT travel with the product.                                  │
│                                                                           │
│  Layer 3: Device Lock (setup lock)                                       │
│  ───────────────────────────────────────                                  │
│  The lock prevents vault re-creation.                                     │
│  If the product is copied WITH the lock → vault missing →                │
│  fallback with the default password only (safe).                         │
│                                                                           │
│  Layer 4: Fallback Admin Isolation                                       │
│  ──────────────────────────────────────                                   │
│  The fallback is a read-only reference with a static password.            │
│  After the password change, vault and fallback diverge.                   │
│  The fallback is PERMANENTLY LOCKED.                                     │
│                                                                           │
│  Layer 5: Rate Limiting                                                  │
│  ──────────────────────                                                   │
│  5 failed sign-in attempts → 30-minute lockout.                           │
│  Prevents brute-force attacks on default or guessed credentials.          │
│                                                                           │
│  Layer 6: License Enforcement                                            │
│  ────────────────────────                                                 │
│  Without a valid license, the portal runs in restricted mode and          │
│  only the license upload page is accessible.                              │
│                                                                           │
└─────────────────────────────────────────────────────────────────────────┘
```

### 10.2 What Travels With Your Data

```
┌─────────────────────────────┬──────────┬──────────┬──────────────────┐
│ Item                        │ Product? │ Vault?   │ Travels?         │
├─────────────────────────────┼──────────┼──────────┼──────────────────┤
│ Fallback admin record       │ ✅ Yes   │ ❌ No   │ ✅ With product  │
│ Setup lock                  │ ❌ No    │ ❌ No   │ Should NOT be    │
│                             │ (runtime)│          │ copied           │
│ User accounts               │ ❌ No    │ ✅ Yes  │ ❌ Docker-only   │
│ License                     │ ❌ No    │ ✅ Yes  │ ❌ Docker-only   │
│ Directory connections       │ ❌ No    │ ✅ Yes  │ ❌ Docker-only   │
│ Activity logs               │ ❌ No    │ ✅ Yes  │ ❌ Docker-only   │
└─────────────────────────────┴──────────┴──────────┴──────────────────┘
```

---

## 11. Attack Scenarios & Protections

### Scenario 1: Default credentials exposed

```
Attacker knows: admin / default password
       │
       ├── If the admin HAS NOT changed the password:
       │   ├── Sign-in works
       │   ├── Forced password change immediately
       │   ├── Attacker must set a new password → locks themselves out
       │   └── Rate limiting: 5 attempts → 30 min lockout
       │
       └── If the admin HAS changed the password:
           ├── The default password no longer matches → rejected
           └── "Invalid username or password"

Protection: Forced password change + rate limiting + never plain text.
Even a successful sign-in forces an immediate password reset.
```

### Scenario 2: Vault deleted (attack or damage)

```
Attacker deletes the vault's user data
       │
       ├── Sign-in service: vault empty → fallback admin loads
       │
       ├── Fallback password = default
       ├── Admin's vault password = the NEW password
       │
       ├── If the admin changed the password:
       │   ├── Default password still works for the fallback
       │   └── FAIL-SAFE sign-in succeeds with the default password
       │
       └── After fail-safe sign-in, the admin restores the vault
           from a backup

Protection:
- The fallback gives access but only with the default credentials
- After the password change, vault and fallback no longer match
- This is intentional: the fallback provides emergency access
- The admin can then restore the vault from a backup
```

### Scenario 3: Full theft (product files + lock copied)

```
Thief copies the entire product to a new server
       │
       ├── Lock exists + vault empty → fallback ACTIVATED
       │   ├── Sign-in with admin / default password
       │   ├── No license → restricted mode
       │   ├── No directory connections → can't reach your AD
       │   └── No users → nothing usable
       │
       └── Protection:
           ├── Vault NOT transferred (Docker-managed)
           ├── License NOT transferred (Docker-managed)
           ├── Directory connections NOT transferred (Docker-managed)
           └── Thief gets an empty shell with default credentials only
```

### Scenario 4: All files deleted + fresh start

```
Attacker deletes everything: vault + lock + fallback admin
       │
       └── Next visit → first-run setup detects no lock
           → Creates a fresh admin with the default password
           → Attacker can sign in with admin / default password

Protection: This is equivalent to a fresh install.
The admin should immediately change the password on first sign-in.
No real data is compromised (it was all deleted).
```

### Scenario 5: Brute-force attack

```
Attacker tries 1000 passwords per minute
       │
       ├── After 5 failures → locked for 30 minutes
       ├── Changing IP delays the attack but doesn't bypass protection
       ├── Each check is deliberately slow
       └── Portal restarts don't reset the lockout

Protection: Rate limiting + deliberate one-way scrambling.
5 attempts = 30-minute lockout per IP. 1000 passwords would take
100+ seconds even without rate limiting (each check is intentionally slow).
```

### Scenario 6: Container escape / volume access

```
Attacker gains host access and reads the Docker volumes
       │
       ├── User data
       │   └── Passwords: scrambled (not plain text)
       │
       ├── License file
       │   └── License file (application-bound)
       │
       └── Directory connection settings
           └── Connect credentials are encrypted

Protection:
- Never plain text: slow to crack, salted
- Application-level encryption for sensitive fields
- Host access required (not reachable from the web)

Severity: HIGH (host compromised)
Mitigation: Host security, regular patching, filesystem permissions
```

---

## 12. Where Your Data Lives (Summary)

### Storage Summary

```
Secure vault (Docker-managed storage):
  User accounts          (Docker volume)
  Roles                  (Docker volume)
  Authenticated users    (Docker volume)
  Registration requests  (Docker volume)
  License                (Docker volume)
  Directory connections  (Docker volume)

App folder (ships with the product):
  Setup lock
  Fallback admin record
```

### Volume Map (Docker)

```
┌─────────────────────────────────────────────────────────────────┐
│                    DOCKER VOLUME MAP                              │
│                                                                   │
│  Host Filesystem                       Storage                    │
│  ┌──────────────────────┐              ┌──────────────────────┐  │
│  │ Product folder       │──mapped────▶│  App files           │  │
│  │  (app, settings, web)│              │  (code and runtime)  │  │
│  └──────────────────────┘              └──────────────────────┘  │
│                                                                   │
│  Docker Volume (secure)                 Storage                   │
│  ┌──────────────────────┐              ┌──────────────────────┐  │
│  │ Secure storage        │──mounted───▶│ Secure vault         │  │
│  │                      │              │  ├── user accounts   │  │
│  │ NOT accessible from   │              │  ├── license         │  │
│  │ host without root     │              │  └── connections     │  │
│  └──────────────────────┘              └──────────────────────┘  │
│                                                                   │
│  Docker Volume (logs)                   Storage                   │
│  ┌──────────────────────┐              ┌──────────────────────┐  │
│  │ Activity logs        │──mounted───▶│ Log storage          │  │
│  └──────────────────────┘              └──────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

---

> **Document Version:** 2.0 | **Last Updated:** June 2026
> **Related:** See the deployment guide for architecture and fresh-install steps.