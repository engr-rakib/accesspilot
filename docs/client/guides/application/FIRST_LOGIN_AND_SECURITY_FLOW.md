# AccessPilot — First Login, Security & Anti-Theft Architecture

> **Complete architecture of the first-run flow, device lock mechanism, admin lifecycle, and how the application protects itself from theft.**

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [The Lock File as a Device Lock](#2-the-lock-file-as-a-device-lock)
3. [Initial State (Fresh Server)](#3-initial-state-fresh-server)
4. [Bootstrap Initialization Flow](#4-bootstrap-initialization-flow)
5. [Admin Lifecycle](#5-admin-lifecycle)
6. [Internal Admin Lock Mechanism](#6-internal-admin-lock-mechanism)
7. [Anti-Theft Protection](#7-anti-theft-protection)
8. [How to Unlock Internal Admin](#8-how-to-unlock-internal-admin)
9. [Complete Workflow Diagram](#9-complete-workflow-diagram)
10. [Security Model Summary](#10-security-model-summary)
11. [Attack Scenarios & Protections](#11-attack-scenarios--protections)

---

## 1. Architecture Overview

### 1.1 The Two-Storage Design

The application uses **two separate storage layers** with different security profiles:

```
┌─────────────────────────────────────────────────────────────────────┐
│                     STORAGE ARCHITECTURE                              │
│                                                                       │
│  ┌─────────────────────────────────────┐   ┌───────────────────────┐ │
│  │  CODE (App_Data/ is web-accessible)  │   │  VAULT (Docker volume) │ │
│  │                                     │   │                       │ │
│  │  App_Data/                          │   │  /data/secure/        │ │
│  │    ├── setup_complete.lock   ◄── LOCK│   │    ├── appusers/     │ │
│  │    └── internal_admin.json  ◄── KEY │   │    │   ├── users.json│ │
│  │                                     │   │    │   ├── roles.json│ │
│  │  This directory IS part of the      │   │    │   └── ...       │ │
│  │  codebase. Transferred with WinCP.  │   │    ├── license/      │ │
│  │                                     │   │    ├── ldap/         │ │
│  │  ⚠ Vulnerable (file access)         │   │    └── ...           │ │
│  │  → Stores only bootstrap data       │   │                       │ │
│  │  → No real user credentials         │   │  This directory is in │ │
│  │  → No LDAP config                   │   │  a Docker named volume│ │
│  │  → No license data                  │   │  NOT transferred with │ │
│  │                                     │   │  the code.           │ │
│  └─────────────────────────────────────┘   │                       │ │
│                                            │  ✅ Secure (volume)    │ │
│                                            │  → All real users      │ │
│                                            │  → All credentials     │ │
│                                            │  → LDAP config         │ │
│                                            │  → License files       │ │
│                                            └───────────────────────┘ │
└─────────────────────────────────────────────────────────────────────┘
```

### 1.2 Key Principle

> **The setup lock + vault are created together on first run and together they bind the application to a specific server instance.**
>
> Copying the code alone gives you an empty shell with no users, no data, and no configuration.

### 1.3 Files and Their Roles

| File | Location | Created By | Purpose | Transferred? |
|------|----------|-----------|---------|-------------|
| `setup_complete.lock` | `App_Data/` | Bootstrap (first run) | Device lock — prevents re-init | ❌ Should NOT be transferred |
| `internal_admin.json` | `App_Data/` | Developer | Fail-safe admin (default password) | ✅ Part of code |
| `users.json` | `/data/secure/appusers/` | Bootstrap or admin | Real user credentials | ❌ Docker volume |
| `roles.json` | `/data/secure/appusers/` | Bootstrap | Role definitions | ❌ Docker volume |
| License PEM | `/data/secure/license/` | Admin (web UI) | License certificate | ❌ Docker volume |
| LDAP config | `/data/secure/ldap/` | Admin (web UI) | AD server info | ❌ Docker volume |

---

## 2. The Lock File as a Device Lock

### 2.1 What is `setup_complete.lock`?

A simple timestamp file created by bootstrap on first-ever request:

```
File: App_Data/setup_complete.lock
Content: "2026-06-19 10:30:00"
```

### 2.2 How It Functions as a Device Lock

The lock file binds the application to a specific server by controlling two critical behaviors:

```
┌─────────────────────────────────────────────────────────────────┐
│                   LOCK FILE — GATEKEEPER                         │
│                                                                   │
│  ┌──────────────────────┐         ┌───────────────────────────┐  │
│  │  bootstrap/app.php    │         │  user_management_service  │  │
│  │                      │         │                           │  │
│  │  if (!lock exists) { │         │  if (vault empty) {       │  │
│  │    create vault      │         │    load internal_admin    │  │
│  │    create lock       │         │  }                        │  │
│  │  }                   │         │  (lock check removed —    │  │
│  │                      │         │   vault emptiness only)   │  │
│  │  LOCK → Prevents     │         │                           │  │
│  │         re-creation   │         │  LOCK → Does NOT block    │  │
│  │                      │         │         login fallback    │  │
│  └──────────────────────┘         └───────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

### 2.3 The Lock-Vault Binding

```
State: Bootstrap complete
       │
       ├── setup_complete.lock EXISTS
       │
       └── /data/secure/appusers/users.json EXISTS (has admin user)
       
These two files are CREATED TOGETHER and TOGETHER they form the
"this server is set up" assertion. Neither one alone is sufficient.
```

### 2.4 Lock States and Meanings

| State | Lock | Vault | Meaning |
|-------|------|-------|---------|
| A | Missing | Empty | Fresh server — first run pending |
| B | Exists | Has admin | Normal operation — server is bound |
| C | Exists | Empty | Broken state — vault deleted but lock remains |
| D | Missing | Has admin | Re-init allowed — bootstrap will skip because `repo_ensure_json_file` won't overwrite existing file |
| E | Missing | Empty | Full reset — bootstrap will recreate both |

---

## 3. Initial State (Fresh Server)

### 3.1 What Exists and What Doesn't

**Before first HTTP request:**
```
App_Data/                              /data/secure/ (Docker volume)
  ├── internal_admin.json  ✅           (empty directory)
  ├── setup_complete.lock  ❌
  └── ...                  ...

(Other code files are present,
 but no runtime data yet)
```

### 3.2 The Bootstrap Trigger

The bootstrap (`core_admin_ensure_runtime_files()`) runs ONLY when a user visits the admin portal root URL (`/`). It does NOT run on:
- Login API calls (`POST /api/index.php?endpoint=auth_api`)
- Login page loads (`GET /login.php`)
- Static file requests

This means: **if a user navigates directly to `/login.php` without first visiting `/`, the bootstrap has not run yet.** In that case, `internal_admin.json` handles the login via the fail-safe path.

### 3.3 The Complete First-Request Sequence

```
User opens browser → http://server:8080/
       │
       ├── Nginx receives request
       │   └── try_files → index.php → route = '' → fallback to admin_portal.php
       │
       ├── admin_portal.php
       │   └── core_admin_bootstrap()
       │       └── core_admin_ensure_runtime_files()
       │           └── Lock missing → CREATE admin in vault + CREATE lock
       │
       ├── Auth check → no session → redirect to login.php
       │
       └── User sees login page
```

---

## 4. Bootstrap Initialization Flow

### 4.1 Code Path

```
core_admin_ensure_runtime_files()      [bootstrap/app.php:47-91]
       │
       ├── file_exists($setupLock)?
       │       │
       │       YES ──▶ Skip admin creation (lines 54-70 skipped)
       │       │        Still ensure roles.json, authenticated_users.json,
       │       │        registration_requests.json, passwords/global.json
       │       │        (these are safe to run every time — idempotent)
       │       │
       │       NO ──▶ Create vault + lock:
       │                 1. repo_ensure_json_file(
       │                      repo_users_path(),          → /data/secure/appusers/users.json
       │                      ['admin' => [...]]
       │                    )
       │                    Admin data:
       │                    {
       │                      "password": "$2y$12$MPbJH...",    ← bcrypt of "accesspilot@123"
       │                      "email": "admin@accesspilot.com",
       │                      "role": "core_admin",
       │                      "system_access": true,
       │                      "full_name": "Default Administrator",
       │                      "must_change_password": true
       │                    }
       │
       │                 2. file_put_contents($setupLock, date('Y-m-d H:i:s'))
       │                    → App_Data/setup_complete.lock created
       │
       ├── repo_ensure_json_file(repo_roles_path(),     ['core_admin' => [...], 'user' => [...]])
       ├── repo_ensure_json_file(repo_auth_users_path(), [])
       ├── repo_ensure_json_file(repo_registration_path(), [])
       └── repo_ensure_json_file(repo_passwords_path(), [])
```

### 4.2 What Gets Created

```
AFTER FIRST BOOTSTRAP RUN:

/data/secure/appusers/users.json
{
    "admin": {
        "password": "$2y$12$MPbJH.1uNxFcAiIUuheFJeItKiTSjY8t087IcF2n3uUfJufseEf0.",
        "email": "admin@accesspilot.com",
        "role": "core_admin",
        "system_access": true,
        "full_name": "Default Administrator",
        "must_change_password": true
    }
}

/data/secure/appusers/roles.json
{
    "core_admin": {
        "permissions": ["*"],
        "description": "Full system access"
    },
    "user": {
        "permissions": ["read"],
        "description": "Standard user"
    }
}

/data/secure/appusers/authenticated_users.json → []
/data/secure/appusers/registration_requests.json → []
/data/secure/ldap/ → empty (configured from web UI)
/data/secure/license/ → empty (configured from web UI)

App_Data/setup_complete.lock → "2026-06-19 10:30:00"
```

### 4.3 Idempotency

`repo_ensure_json_file()` only creates the file if it doesn't exist. If the file already exists (e.g., restart after crash), it does NOT overwrite. This means:

- **Second bootstrap run:** Lock exists → skip admin creation → roles.json etc. already exist → no-op
- **Vault deleted but lock exists:** Lock exists → skip admin creation → vault stays empty
- **Lock deleted but vault exists:** Lock missing → bootstrap runs `repo_ensure_json_file()` → vault file already exists → no overwrite → lock file re-created

---

## 5. Admin Lifecycle

### 5.1 Phase 1: Bootstrap Admin (Default Credentials)

```
State: Fresh server, bootstrap just ran
       │
       ├── users.json has "admin" with password hash of "accesspilot@123"
       ├── must_change_password = true
       ├── system_access = true
       │
       └── This admin exists ONLY in the vault
           (internal_admin.json is a separate, static file)
```

### 5.2 Phase 2: First Login

```
User enters: admin / accesspilot@123
       │
       ▼
auth_handle_login()                         [auth_service.php:32-101]
       │
       ├── readUsers()
       │   └── Vault has "admin" → returns vault users
       │
       ├── isset($users['admin'])? → YES
       ├── password_verify('accesspilot@123', hash)? → YES
       ├── system_access === true? → YES
       ├── must_change_password === true? → YES
       │
       └── Response: { success: true, must_change: true }
           → Frontend JS redirects to password change page
```

### 5.3 Phase 3: Password Change

```
Admin submits new password
       │
       ▼
Password change handler                    [user_management_actions.php:375-399]
       │
       ├── Verify current_password matches hash → YES
       ├── Hash new password with password_hash()
       ├── Set must_change_password = false
       ├── Save to users.json
       │
       └── users.json after change:
           {
             "admin": {
               "password": "$2y$12$NEW_HASH...",      ← New password hash
               "must_change_password": false,          ← Flag cleared
               ...
             }
           }
```

### 5.4 Phase 4: Internal Admin Locked

```
AFTER PASSWORD CHANGE:

internal_admin.json still has:
  "password": "$2y$12$MPbJH..."     ← hash of "accesspilot@123"

users.json now has:
  "password": "$2y$12$NEW_HASH..."  ← hash of new password

These two hashes are DIFFERENT.
       │
       ▼
If vault is deleted later and internal_admin.json activates:
  password_verify("accesspilot@123", "$2y$12$NEW_HASH...") → FALSE
  → Login FAILS

The internal admin is PERMANENTLY LOCKED.
The only way back is manual intervention (see Section 8).
```

### 5.5 Phase 5: Production Use

```
After password change, admin:
       │
       ├── Configures LDAP servers (web UI)
       ├── Uploads license certificates
       ├── Creates additional admin/user accounts
       ├── Sets up AD domains
       │
       └── Vault now has:
           ├── "admin" with new password hash
           ├── "john" (real admin)
           ├── "jane" (regular user)
           └── ... (multiple users)

internal_admin.json → NEVER checked again (vault is non-empty)
setup_complete.lock → Prevents bootstrap from overwriting vault
```

### 5.6 Complete Admin Lifecycle Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        ADMIN LIFECYCLE                                    │
│                                                                           │
│  ┌────────────┐     ┌──────────┐     ┌──────────────┐     ┌──────────┐  │
│  │ Bootstrap  │────▶│  First    │────▶│   Password   │────▶│ Internal  │  │
│  │ creates    │     │  Login    │     │   Change     │     │  Admin    │  │
│  │ admin in   │     │          │     │              │     │  Locked   │  │
│  │ vault      │     │ admin/   │     │ New password │     │           │  │
│  │            │     │ access-   │     │ → vault hash │     │ internal_ │  │
│  │ must_change│     │ pilot@123 │     │   changes    │     │ admin.json│  │
│  │ = true     │     │          │     │ must_change  │     │ hash no   │  │
│  │            │     │ Forced   │     │ = false      │     │ longer    │  │
│  │ Default    │     │ redirect │     │              │     │ matches   │  │
│  │ password   │     │ to change│     │              │     │           │  │
│  └────────────┘     └──────────┘     └──────────────┘     └──────────┘  │
│                                                                           │
│                                  │                                        │
│                                  ▼                                        │
│                        ┌──────────────────┐                              │
│                        │  Production Use   │                              │
│                        │                   │                              │
│                        │  - Add users      │                              │
│                        │  - Configure LDAP │                              │
│                        │  - Set license    │                              │
│                        │  - Create admins  │                              │
│                        └──────────────────┘                              │
│                                                                           │
│              internal_admin.json → PERMANENTLY BYPASSED                   │
│              (vault now has users, so fail-safe never activates)          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 6. Internal Admin Lock Mechanism

### 6.1 What is `internal_admin.json`?

A static, read-only fallback file stored in `App_Data/`:

```json
{
    "admin": {
        "username": "admin",
        "password": "$2y$12$MPbJH.1uNxFcAiIUuheFJeItKiTSjY8t087IcF2n3uUfJufseEf0.",
        "role": "core_admin",
        "system_access": true,
        "is_internal": true
    }
}
```

- **Password hash:** `$2y$12$MPbJH.1uNxFcAiIUuheFJeItKiTSjY8t087IcF2n3uUfJufseEf0.` = bcrypt of `accesspilot@123`
- **Role:** `core_admin` — full system access
- **`is_internal: true`** — marks this as a bootstrap-only account

### 6.2 When It Activates

The fail-safe logic in `readUsers()` (`user_management_service.php:7-27`):

```php
function readUsers() {
    $externalUsers = repo_read_users();              // Read from Docker vault

    if (empty($externalUsers)) {                     // Vault is empty?
        if ((bool) config_get('fail_safe.enabled', false)) {
            $internalPath = (string) config_get('fail_safe.path', '');
            if (file_exists($internalPath)) {
                $internalAdmin = json_decode(file_get_contents($internalPath), true);
                if (is_array($internalAdmin)) {
                    return $internalAdmin;            // ← FAIL-SAFE ACTIVATED
                }
            }
        }
    }

    return $externalUsers;                           // Normal: return vault
}
```

**The lock file (`setup_complete.lock`) does NOT block this.** Only vault emptiness matters.

### 6.3 When It Becomes Permanently Locked

**Condition:** Once the vault (`/data/secure/appusers/users.json`) has at least one user.

```
BEFORE:
  vault = [] → empty($externalUsers) = true → internal_admin.json loaded

AFTER FIRST PASSWORD CHANGE:
  vault = {"admin": { "password": "$2y$12$NEW...", ... }}
  → empty($externalUsers) = false
  → internal_admin.json NEVER CHECKED AGAIN
```

**Even if vault is deleted later:**
```
  vault = [] → empty($externalUsers) = true → internal_admin.json loaded
  → BUT: password hash in internal_admin.json still matches "accesspilot@123"
  → If admin changed password: hash mismatch → "Invalid username or password"
```

**Why it's locked:**
```
internal_admin.json password hash  →  "accesspilot@123"
Admin's new password               →  "MyNewSecurePass@456"

These are DIFFERENT strings with DIFFERENT bcrypt hashes.
password_verify("accesspilot@123", "$2y$12$NEW_HASH...") → FALSE
```

### 6.4 The Three-Stage Lock

```
Stage 1: Bootstrap creates admin
         ├── vault has "admin" with default hash
         ├── internal_admin.json has same default hash
         └── BOTH match "accesspilot@123"

Stage 2: Admin changes password
         ├── vault hash changes → matches NEW password
         ├── internal_admin.json hash STAYS → matches "accesspilot@123"
         └── HASHES DIVERGE → internal admin is now "locked"

Stage 3: Real users created
         ├── vault now has multiple users
         ├── internal_admin.json is NEVER consulted
         └── (vault non-empty → fail-safe bypassed)
```

---

## 7. Anti-Theft Protection

### 7.1 What Can Be Stolen

| Item | Stolen? | What thief gets |
|------|---------|-----------------|
| Code (WinCP transfer) | ✅ | Empty application shell |
| Docker image | ✅ | Same as code |
| `App_Data/setup_complete.lock` | ✅ | Lock file (harmless alone) |
| `App_Data/internal_admin.json` | ✅ | Default credentials only |
| `/data/secure/appusers/users.json` | ❌ | Docker named volume — not accessible |
| `/data/secure/license/` | ❌ | Docker named volume — not accessible |
| `/data/secure/ldap/` | ❌ | Docker named volume — not accessible |
| License PEM files | ❌ | Docker named volume — not accessible |
| LDAP credentials | ❌ | Docker named volume — not accessible |
| Real user hashes | ❌ | Docker named volume — not accessible |

### 7.2 Theft Scenario Analysis

#### Scenario A: Thief copies ONLY the code (no lock, no vault)

```
Thief runs on new server:
       │
       ├── No setup_complete.lock → bootstrap creates admin + lock
       ├── Admin: admin / accesspilot@123
       ├── No license → app shows "license required" page
       ├── No LDAP config → cannot connect to AD
       └── No real users → only default admin exists

Severity: LOW
Mitigation: Thief has empty shell with default credentials only.
            License requirement blocks full usage.
```

#### Scenario B: Thief copies code + lock file (common WinCP mistake)

```
Thief runs on new server:
       │
       ├── setup_complete.lock EXISTS
       ├── Vault is EMPTY (different Docker volume)
       │
       ├── OLD CODE (before fix):
       │   ├── readUsers() → vault empty + lock exists → fail-safe BLOCKED
       │   ├── Returns [] → NO USER CAN LOG IN
       │   └── Application is completely inaccessible
       │
       ├── NEW CODE (with fix):
       │   ├── readUsers() → vault empty → fail-safe ACTIVATED
       │   ├── Returns internal_admin.json
       │   ├── admin / accesspilot@123 works
       │   └── But: forced password change → new hash in vault → vault created
       │
       └── Either way: NO REAL DATA is compromised

Severity: LOW (code fix) / MEDIUM (old code — app unusable, needs manual fix)
Mitigation: Thief gets only default credentials. No license, no LDAP, no users.
```

#### Scenario C: Thief copies code + internal_admin.json

```
Thief runs on new server:
       │
       ├── No lock → bootstrap creates admin in vault
       ├── Vault has "admin" with default hash
       ├── internal_admin.json has same hash
       │
       ├── Login works with admin / accesspilot@123
       ├── Forced password change
       ├── No license → restricted mode
       └── No LDAP → can't access AD

Severity: LOW
Mitigation: Same as fresh install. Thief gets empty shell.
```

#### Scenario D: Thief gets container access + dumps vault volume

```
Thief reads /data/secure/appusers/users.json:
       │
       ├── User hashes: bcrypt (NOT plain text)
       ├── LDAP bind credentials: encrypted
       ├── License PEM: encrypted
       │
       └── Attacker must crack bcrypt hashes to get passwords

Severity: MEDIUM
Mitigation: bcrypt hashing (slow to crack), encrypted sensitive data.
            Even with vault dump, attacker cannot directly login as users
            without cracking each hash.
```

### 7.3 The Lock as a Theft Deterrent

```
CODE TRANSFER WITH LOCK (OLD CODE — no fix applied):
  ┌──────────────────────────────────────────────┐
  │  Thief copies all files to Server B          │
  │                                              │
  │  Server B state:                             │
  │    ├── App_Data/setup_complete.lock  ✅      │
  │    └── /data/secure/                EMPTY   │
  │                                              │
  │  readUsers():                                │
  │    ├── Vault empty?                         │
  │    ├── Lock exists?                         │
  │    ├── Fail-safe blocked?                   │
  │    └── Returns [] → LOGIN IMPOSSIBLE        │
  │                                              │
  │  RESULT: Application is bricked.             │
  │  Thief gets nothing usable.                  │
  └──────────────────────────────────────────────┘

CODE TRANSFER WITH LOCK (NEW CODE — fix applied):
  ┌──────────────────────────────────────────────┐
  │  Thief copies all files to Server B          │
  │                                              │
  │  Server B state:                             │
  │    ├── App_Data/setup_complete.lock  ✅      │
  │    └── /data/secure/                EMPTY   │
  │                                              │
  │  readUsers():                                │
  │    ├── Vault empty?                         │
  │    ├── Load internal_admin.json             │
  │    ├── admin / accesspilot@123 works        │
  │    └── Forced password change               │
  │                                              │
  │  RESULT: Thief gets default shell.           │
  │  No license, no LDAP, no real users.        │
  │  Internal admin locked after first change.   │
  └──────────────────────────────────────────────┘
```

### 7.4 Why The Default Password Is Not A Real Threat

```
Common concern: "accesspilot@123 is hardcoded, anyone can login!"

Reality:
1. First login → FORCED password change
   → Admin MUST set a new password immediately

2. After change → internal admin is LOCKED
   → internal_admin.json hash no longer matches vault hash
   → Even if deleted vault triggers fail-safe, hash mismatch blocks login

3. Even if thief logs in with default credentials:
   → NO license → restricted mode (can't use AD features)
   → NO LDAP config → can't connect to directories
   → NO users → nothing to steal
   → Rate limited → 5 attempts = 30 min lockout

4. License requirement:
   → Without valid license PEM in /data/secure/license/
   → is_restricted = true
   → Only license upload page is accessible
   → Full features blocked
```

---

## 8. How to Unlock Internal Admin

### 8.1 When Would You Need to Unlock?

| Situation | Reason | Method |
|-----------|--------|--------|
| Forgot admin password | Cannot login with new password | Method A or B |
| Vault corruption | users.json corrupted or deleted | Method C |
| Server migration | Move app to new server cleanly | Method D |
| Developer testing | Need to reset to known state | Method B |

### 8.2 Method A: Delete Lock → Bootstrap Recreates Vault

**Use when:** Admin forgot password but vault still exists (admin user is there).

```bash
# This does NOT unlock internal admin.
# This resets the vault admin to default.

# Step 1: Stop containers
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml down

# Step 2: Delete the lock file + vault
sudo rm -f /opt/accesspilot/App_Data/setup_complete.lock
sudo rm -f /data/secure/appusers/users.json
sudo rm -f /data/secure/appusers/roles.json

# Step 3: Restart
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml up -d

# Step 4: Visit http://server:8080/ → bootstrap creates fresh admin
# Step 5: Login: admin / accesspilot@123
# Step 6: Change password (forced)
```

**Result:** Fresh admin created. All previous users, LDAP config, and license **LOST**.

### 8.3 Method B: Edit `internal_admin.json` Hash Directly

**Use when:** You need to bypass the admin lock without losing vault data.

```bash
# Step 1: Generate a bcrypt hash for a known password
# On Linux with PHP CLI:
php -r "echo password_hash('MyTempPass@123', PASSWORD_DEFAULT);"
# Output: $2y$12$SOMEHASH...

# Step 2: Update internal_admin.json with the new hash
sudo tee /opt/accesspilot/App_Data/internal_admin.json << 'EOF'
{
    "admin": {
        "username": "admin",
        "password": "$2y$12$SOMEHASH...",
        "role": "core_admin",
        "system_access": true,
        "is_internal": true
    }
}
EOF

# Step 3: Delete vault to force fail-safe activation
sudo rm -f /data/secure/appusers/users.json

# Step 4: Delete lock to allow bootstrap to recreate vault
sudo rm -f /opt/accesspilot/App_Data/setup_complete.lock

# Step 5: Restart PHP
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml restart php

# Step 6: Login with admin / MyTempPass@123
# Step 7: Change password again
```

**Risk:** All vault users lost (user data deletion is required). Only use as last resort.

### 8.4 Method C: Manual Vault Repair

**Use when:** Specific user data corruption, need to preserve other users.

```bash
# Step 1: Read current vault
sudo cat /data/secure/appusers/users.json

# Step 2: Fix the admin entry with a known hash
# Generate hash: php -r "echo password_hash('NewPass@123', PASSWORD_DEFAULT);"
sudo docker exec -i accesspilot_php php -r "
    \$users = json_decode(file_get_contents('/data/secure/appusers/users.json'), true);
    \$users['admin']['password'] = '\$2y\$12\$NEWHASH...';
    \$users['admin']['must_change_password'] = true;
    file_put_contents('/data/secure/appusers/users.json', json_encode(\$users, JSON_PRETTY_PRINT));
"

# Step 3: Login with admin / NewPass@123 (forced change)
```

**Result:** Only admin password reset. All other users, LDAP config, license preserved.

### 8.5 Method D: Clean Server Migration

**Use when:** Moving the entire application to a new server while preserving all data.

```bash
# On OLD server — backup everything
tar -czf accesspilot_backup.tar.gz \
    /opt/accesspilot/App_Data/ \
    /data/secure/ \
    /data/logs/

# Transfer backup to new server via WinCP/SCP

# On NEW server
sudo tar -xzf accesspilot_backup.tar.gz -C /

# IMPORTANT: Delete setup_complete.lock on new server
# (it will be recreated by bootstrap)
sudo rm -f /opt/accesspilot/App_Data/setup_complete.lock

# Build and start
cd /opt/accesspilot
sudo docker compose -f docker/docker-compose.yml up -d --build

# Login with EXISTING credentials (not default)
# Admin's password from old server still works
```

**Result:** All users, config, license migrated. Admin password unchanged.

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
│  │ Copy code via │                                                        │
│  │ WinCP to      │                                                        │
│  │ /opt/access-  │                                                        │
│  │ pilot/        │                                                        │
│  └──────┬───────┘                                                        │
│         │                                                                 │
│         ▼                                                                 │
│  ┌──────────────┐                                                        │
│  │ BUILD & START │                                                        │
│  │ docker compose│                                                        │
│  │ up -d --build │                                                        │
│  └──────┬───────┘                                                        │
│         │                                                                 │
│         ▼                                                                 │
│  ┌──────────────────────────────────┐                                   │
│  │ FIRST HTTP REQUEST (/index.php)  │                                   │
│  │                                  │                                   │
│  │ bootstrap/app.php:               │                                   │
│  │   core_admin_ensure_runtime_     │                                   │
│  │   files()                        │                                   │
│  │                                  │                                   │
│  │   ├── Lock file missing? → YES  │                                   │
│  │   ├── Create users.json         │                                   │
│  │   │   (admin / accesspilot@123) │                                   │
│  │   ├── Create setup_complete.lock│                                   │
│  │   └── Create roles/auth/etc.    │                                   │
│  └──────────────┬───────────────────┘                                   │
│                 │                                                        │
│                 ▼                                                        │
│  ┌──────────────────────────────────┐                                   │
│  │ REDIRECT TO LOGIN PAGE           │                                   │
│  │                                  │                                   │
│  │ User sees login form             │                                   │
│  └──────────────┬───────────────────┘                                   │
│                 │                                                        │
│                 ▼                                                        │
│  ┌──────────────────────────────────┐                                   │
│  │ FIRST LOGIN                      │                                   │
│  │                                  │                                   │
│  │ POST /api/auth (admin /          │                                   │
│  │ accesspilot@123)                 │                                   │
│  │                                  │                                   │
│  │ auth_service.php:                │                                   │
│  │   ├── readUsers() → vault        │                                   │
│  │   ├── password_verify → OK      │                                   │
│  │   ├── must_change_password?→YES│                                   │
│  │   └── Return {must_change:true} │                                   │
│  └──────────────┬───────────────────┘                                   │
│                 │                                                        │
│                 ▼                                                        │
│  ┌──────────────────────────────────┐                                   │
│  │ FORCED PASSWORD CHANGE           │                                   │
│  │                                  │                                   │
│  │ Admin sets new password          │                                   │
│  │                                  │                                   │
│  │ user_management_actions.php:     │                                   │
│  │   ├── Verify old password       │                                   │
│  │   ├── Hash new password         │                                   │
│  │   ├── Save to users.json        │                                   │
│  │   └── must_change_password=false│                                   │
│  └──────────────┬───────────────────┘                                   │
│                 │                                                        │
│                 ▼                                                        │
│  ┌──────────────────────────────────┐  ┌──────────────────────────────┐ │
│  │ DASHBOARD (full access)          │  │ INTERNAL ADMIN LOCKED        │ │
│  │                                  │  │                              │ │
│  │ Can now:                         │  │ internal_admin.json hash     │ │
│  │   ├── Configure LDAP             │  │ no longer matches vault      │ │
│  │   ├── Upload license            │  │ Default password disabled    │ │
│  │   ├── Create users              │  │                              │ │
│  │   └── Manage system             │  │ PERMANENTLY LOCKED           │ │
│  └──────────────────────────────────┘  └──────────────────────────────┘ │
│                                                                           │
│  ┌────────────────────────────────────────────────────────────────────┐ │
│  │ PRODUCTION STATE                                                    │ │
│  │                                                                      │ │
│  │  Vault: has users, roles, config                                     │ │
│  │  Lock: exists (prevents re-init)                                     │ │
│  │  License: uploaded (full features)                                   │ │
│  │  LDAP: configured (AD connected)                                    │ │
│  │  internal_admin.json: EXISTING but NEVER CONSULTED                  │ │
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
│  │ THIEF COPIES CODE │                                                     │
│  │ from Server A to  │                                                     │
│  │ Server B via WinCP│                                                     │
│  └────────┬─────────┘                                                      │
│           │                                                               │
│           ▼                                                               │
│  ┌──────────────────────────────────┐                                    │
│  │ Server B State:                  │                                    │
│  │                                  │                                    │
│  │  /opt/accesspilot/App_Data/      │                                    │
│  │    ├── setup_complete.lock  ✅   │  ← Copied from Server A           │
│  │    └── internal_admin.json ✅    │  ← Part of code                   │
│  │                                  │                                    │
│  │  /data/secure/         EMPTY     │  ← NOT copied (Docker volume)     │
│  │                                  │                                    │
│  │  LICENSE PEM           MISSING   │  ← NOT copied (Docker volume)     │
│  │  LDAP CONFIG           MISSING   │  ← NOT copied (Docker volume)     │
│  │  REAL USERS            MISSING   │  ← NOT copied (Docker volume)     │
│  └────────┬─────────────────────────┘                                    │
│           │                                                               │
│           ▼                                                               │
│  ┌──────────────────────────────────┐                                    │
│  │ LOGIN ATTEMPT                    │                                    │
│  │                                  │                                    │
│  │ readUsers():                     │                                    │
│  │   ├── Vault empty → YES         │                                    │
│  │   ├── Load internal_admin.json  │                                    │
│  │   └── Return admin with default │                                    │
│  │       password hash              │                                    │
│  │                                  │                                    │
│  │ Login succeeds: admin /          │                                    │
│  │ accesspilot@123                  │                                    │
│  │                                  │                                    │
│  │ BUT:                             │                                    │
│  │   ├── No license → restricted   │                                    │
│  │   ├── No LDAP → can't connect   │                                    │
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
│  │  ❌ No LDAP credentials          │                                    │
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
│  First login redirects to mandatory password change.                     │
│  Default password "accesspilot@123" is single-use only.                  │
│  File: bootstrap/app.php:64  |  Flag: must_change_password: true        │
│                                                                           │
│  Layer 2: Vault-Based Storage                                            │
│  ──────────────────────────                                               │
│  Real user credentials stored in Docker named volume                     │
│  (/data/secure/appusers/). NOT accessible from host filesystem.          │
│  Code transfer does NOT include vault data.                              │
│  File: config/storage.php:9-10  |  Volume: accesspilot_secure            │
│                                                                           │
│  Layer 3: Device Lock (setup_complete.lock)                              │
│  ───────────────────────────────────────                                  │
│  Lock file prevents vault re-creation.                                   │
│  If code is stolen WITH lock → vault missing → app unusable (old code)   │
│  or failsafe with default password only (new code).                      │
│  File: bootstrap/app.php:54  |  Path: App_Data/setup_complete.lock      │
│                                                                           │
│  Layer 4: Internal Admin Isolation                                       │
│  ──────────────────────────────────────                                   │
│  internal_admin.json is a READ-ONLY reference with a static hash.        │
│  After password change: vault hash diverges from internal_admin hash.    │
│  The internal admin is PERMANENTLY LOCKED.                               │
│  File: user_management_service.php:7-27  |  Path: App_Data/internal_admin.json │
│                                                                           │
│  Layer 5: Rate Limiting                                                  │
│  ──────────────────────                                                   │
│  5 failed login attempts → 30-minute IP lockout.                         │
│  Prevents brute force attacks on default or guessed credentials.         │
│  File: auth_service.php:38-54                                            │
│                                                                           │
│  Layer 6: License Enforcement                                            │
│  ────────────────────────                                                 │
│  Without valid license PEM in /data/secure/license/,                     │
│  is_restricted = true → only license upload page accessible.             │
│  File: license_service.php  |  Config: config/license.php               │
│                                                                           │
└─────────────────────────────────────────────────────────────────────────┘
```

### 10.2 File Protection Matrix

```
┌─────────────────────────────┬──────────┬──────────┬──────────────────┐
│ File                        │ Code?    │ Volume?  │ Transferred?     │
├─────────────────────────────┼──────────┼──────────┼──────────────────┤
│ bootstrap/app.php           │ ✅ Yes   │ ❌ No   │ ✅ With code     │
│ config/storage.php          │ ✅ Yes   │ ❌ No   │ ✅ With code     │
│ App_Data/internal_admin.json│ ✅ Yes   │ ❌ No   │ ✅ With code     │
│ App_Data/setup_complete.lock│ ❌ No    │ ❌ No   │ Should NOT be    │
│                             │ (runtime)│          │ transferred      │
│ /data/secure/appusers/      │ ❌ No    │ ✅ Yes  │ ❌ Docker only   │
│ /data/secure/license/       │ ❌ No    │ ✅ Yes  │ ❌ Docker only   │
│ /data/secure/ldap/          │ ❌ No    │ ✅ Yes  │ ❌ Docker only   │
│ /data/logs/                 │ ❌ No    │ ✅ Yes  │ ❌ Docker only   │
└─────────────────────────────┴──────────┴──────────┴──────────────────┘
```

---

## 11. Attack Scenarios & Protections

### Scenario 1: Default credentials exposed ("accesspilot@123" is known)

```
Attacker knows: admin / accesspilot@123
       │
       ├── If admin HAS NOT changed password:
       │   ├── Login works
       │   ├── Forced password change immediately
       │   ├── Attacker must set new password → locks themselves out
       │   └── Rate limiting: 5 attempts → 30 min IP lockout
       │
       └── If admin HAS changed password:
           ├── password_verify("accesspilot@123", vault_hash) → FALSE
           └── "Invalid username or password"

Protection: Forced password change + rate limiting + bcrypt hashing.
Even successful login forces immediate password reset.
```

### Scenario 2: Vault deleted (attack or corruption)

```
Attacker deletes /data/secure/appusers/users.json
       │
       ├── readUsers(): vault empty → load internal_admin.json
       │
       ├── internal_admin.json hash = "accesspilot@123"
       ├── Admin's vault hash = NEW password
       │
       ├── If admin changed password:
       │   ├── password_verify("accesspilot@123", internal_hash) → TRUE
       │   └── FAIL-SAFE LOGIN succeeds with default password
       │
       └── If admin changed password AND vault only had admin:
           ├── password_verify("accesspilot@123", internal_hash) → TRUE
           ├── Login succeeds, forced password change
           └── New vault created with new hash

Protection: 
- Fail-safe gives access but only with default credentials
- If admin changed password, vault hash and internal hash are DIFFERENT
- But fail-safe always uses internal_admin.json hash (default password)
- So even after password change, fail-safe still uses "accesspilot@123"
- This is intentional: fail-safe provides emergency access
- After fail-safe login, admin can restore vault from backup
```

### Scenario 3: Full theft (code + lock file stolen)

```
Thief copies entire project to new server
       │
       ├── Old code (no readUsers fix):
       │   ├── Lock exists + vault empty → fail-safe BLOCKED
       │   ├── Returns [] → NO LOGIN POSSIBLE
       │   └── Application is bricked
       │
       ├── New code (with readUsers fix):
       │   ├── Lock exists + vault empty → fail-safe ACTIVATED
       │   ├── Login with admin / accesspilot@123
       │   ├── No license → restricted mode
       │   ├── No LDAP → can't connect
       │   └── No users → nothing usable
       │
       └── Protection:
           ├── Vault data NOT transferred (Docker volume)
           ├── License NOT transferred (Docker volume)
           ├── LDAP config NOT transferred (Docker volume)
           └── Thief gets empty shell with default credentials only
```

### Scenario 4: All files deleted + fresh start

```
Attacker deletes everything: vault + lock + internal_admin.json
       │
       └── Next request → bootstrap detects no lock
           → Creates fresh admin with default password
           → Attacker can login with admin / accesspilot@123

Protection: This is equivalent to a fresh install.
Admin should immediately change password on first login.
No real data is compromised (it was all deleted).
```

### Scenario 5: Brute force attack

```
Attacker tries 1000 passwords per minute
       │
       ├── After 5 failures → IP locked for 30 minutes
       ├── Changing IP delays attack but doesn't bypass bcrypt
       ├── Each password_verify() takes ~100ms (bcrypt cost 12)
       └── Container restarts don't reset lockout (session-based)

Protection: Rate limiting + slow bcrypt hashing.
5 attempts = 30 minute lockout per IP. 1000 passwords would take 100+ seconds
even without rate limiting (bcrypt cost 12 is intentionally slow).
```

### Scenario 6: Container escape / volume access

```
Attacker gains host access, reads Docker volumes
       │
       ├── /data/secure/appusers/users.json
       │   └── Passwords: bcrypt hashes (not plaintext)
       │
       ├── /data/secure/license/accesspilot.pem
       │   └── License file (application-bound)
       │
       ├── /data/secure/ldap/config.json
       │   └── LDAP bind credentials (config-dependent encryption)
       │
       └── Protection:
           ├── bcrypt hashing: slow to crack, salted
           ├── Application-level encryption for sensitive fields
           └── Host access required (not from web)

Severity: HIGH (host compromised)
Mitigation: Host security, regular patching, filesystem permissions (uid 33)
```

---

## Appendix A: Key Code References

| Component | File | Lines | Purpose |
|-----------|------|-------|---------|
| Bootstrap init | `bootstrap/app.php` | 47-91 | Creates admin + lock on first run |
| Bootstrap call | `app/Application/Http/admin_portal.php` | 14 | Only place bootstrap is invoked |
| User reader | `user_management_service.php` | 7-27 | Reads users with fail-safe fallback |
| Login handler | `auth_service.php` | 32-101 | Password verify + must_change check |
| Password change | `user_management_actions.php` | 375-399 | Self password update |
| Vault path | `repositories.php` | 34-37 | `resolve_secure_path('appusers', 'users.json')` |
| Config | `config/storage.php` | 25-28 | `fail_safe.enabled` + internal_admin.json path |
| Sessions | `auth_session_service.php` | 5-15 | Session creation |
| Rate limiting | `auth_service.php` | 38-54 | 5 attempts → 30 min lockout |
| License check | `license_service.php` | 433-569 | `is_restricted` determination |
| API gateway | `public/api/index.php` | 1-117 | Auth endpoint routing |
| Fallback file | `App_Data/internal_admin.json` | - | Fail-safe admin credentials |
| Lock file | `App_Data/setup_complete.lock` | - | First-run device lock |

## Appendix B: File Paths Summary

```
Storage Paths:
  Secure vault:    /data/secure/appusers/users.json        (Docker volume)
  Roles:           /data/secure/appusers/roles.json        (Docker volume)
  Auth users:      /data/secure/appusers/authenticated_users.json (Docker volume)
  Registration:    /data/secure/appusers/registration_requests.json (Docker volume)
  License:         /data/secure/license/                   (Docker volume)
  LDAP config:     /data/secure/ldap/config.json           (Docker volume)
  
Web-Accessible Paths (App_Data bind mount):
  Lock:            /var/www/html/App_Data/setup_complete.lock
  Internal admin:  /var/www/html/App_Data/internal_admin.json

Code Paths (bind mounted in docker-compose):
  bootstrap:       /var/www/html/bootstrap/app.php
  config:          /var/www/html/config/storage.php
  app:             /var/www/html/app/
  public:          /var/www/html/public/
```

## Appendix C: Docker Volume Isolation

```
┌─────────────────────────────────────────────────────────────────┐
│                    DOCKER VOLUME MAP                              │
│                                                                   │
│  Host Filesystem                    Container Filesystem          │
│  ┌──────────────────────┐          ┌──────────────────────────┐  │
│  │ /opt/accesspilot/    │──bind──▶│ /var/www/html/            │  │
│  │   ├── App_Data/      │──bind──▶│   ├── App_Data/          │  │
│  │   ├── bootstrap/     │──bind──▶│   ├── bootstrap/          │  │
│  │   ├── config/        │──bind──▶│   ├── config/             │  │
│  │   ├── app/           │──bind──▶│   ├── app/                │  │
│  │   ├── public/        │──bind──▶│   ├── public/             │  │
│  │   └── docker/        │──bind──▶│   └── docker/             │  │
│  └──────────────────────┘          └──────────────────────────┘  │
│                                                                   │
│  Docker Volumes                     Container Filesystem          │
│  ┌──────────────────────┐          ┌──────────────────────────┐  │
│  │ accesspilot_secure   │──mount──▶│ /data/secure/            │  │
│  │  (/var/lib/docker/   │          │   ├── appusers/          │  │
│  │   volumes/...)       │          │   ├── license/           │  │
│  │                      │          │   ├── ldap/              │  │
│  │ NOT accessible from  │          │   └── ...                │  │
│  │ host without root    │          └──────────────────────────┘  │
│  └──────────────────────┘                                        │
│                                                                   │
│  ┌──────────────────────┐          ┌──────────────────────────┐  │
│  │ accesspilot_logs     │──mount──▶│ /data/logs/              │  │
│  └──────────────────────┘          └──────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

---

> **Document Version:** 2.0 | **Last Updated:** June 2026
> **Related:** [Docker Architecture & Reference](../docker/README.md) | [Deployment Guide](../docker/DEPLOY_LINUX_FRESH.md)
