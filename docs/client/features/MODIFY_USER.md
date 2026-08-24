# Modify User — Full Control, One Form

## Imagine This

An employee gets promoted. They move to a new department. Their username needs to change. Their OU needs to move. They need access to new groups. Their old permissions need to be removed.

Before: this means opening ADUC, finding the user, changing the username (which breaks if you forget to update the UPN), moving the OU (drag-drop in ADUC which is slow with large directories), manually adding to each new group, manually removing from old groups, and resetting their password if needed. That's 5-6 separate operations. 10-15 minutes of clicking.

With AccessPilot: open the Modify form. Everything is already filled in. Change what you need. Click Update. Done in 10 seconds.

---

## What You Can Change

The Modify form puts every AD attribute that matters into one clean interface. It pre-fills everything from the user's current state — you only change what needs to change.

```
┌─────────────────────────────────────────────────────────────┐
│  Modify Active Directory User                                │
│                                                              │
│  Display Name:  [John Doev                        ]         │
│  Username:      [jdoe                             ]         │
│  Description:   [Senior Engineer                   ]         │
│  OU:            [Search... Users > Engineering     ]  ▾     │
│  Groups:        [Search... Engineering_Team,       ]  ▾     │
│                 [          VPN_Access,             ]         │
│                 [          Project_X               ]         │
│                                                              │
│  ─── Password Options ───                                    │
│  ☐ Reset Password                                            │
│      ☑ Force password change on next login                   │
│      ☐ Use default password                                  │
│      Temporary Password: [                    ]              │
│                                                              │
│  ☐ User must change password at next login                   │
│  ☐ User cannot change password                               │
│  ☑ Password never expires                                    │
│                                                              │
│  [Cancel]  [Update User]                                     │
└─────────────────────────────────────────────────────────────┘
```

### Identity Fields

| Field | What It Changes | What Happens Automatically |
|-------|----------------|---------------------------|
| **Display Name** | `displayName` attribute | Updates the CN (Common Name) in LDAP automatically |
| **Username** | `sAMAccountName` attribute | `userPrincipalName` (UPN) is also updated to match the new domain — no manual UPN fix needed |
| **Description** | `description` attribute | Simple text update |

### OU Migration

Moving a user from one OU to another is a single dropdown selection:

1. The form shows the user's **current OU path**
2. Type to search for the **new OU** — the tree search widget finds matching OUs instantly
3. Select the new OU
4. On update, the system calls `ldap_rename()` which moves the user object to the new parent OU
5. The CN is automatically updated to match the Display Name

**What happens technically:** The user's Distinguished Name changes from `CN=John Doe,OU=Engineering,DC=domain,DC=com` to `CN=John Doe,OU=Marketing,DC=domain,DC=com`. All group memberships remain intact — groups store references by SID, not DN.

| Scenario | Before | After |
|----------|--------|-------|
| Employee transferred Engineering → Marketing | OU: Engineering | OU: Marketing |
| Employee renamed (marriage/legal name change) | CN: Jane Smith | CN: Jane Johnson |
| Reorganization — new OU structure | OU: Old/Path | OU: New/Path |

### Group Membership Management

Groups are managed through a **multi-select tag interface**:

1. **Current groups** are loaded automatically when the form opens — all existing memberships shown as tags
2. **Type to search** for new groups — the system shows matching groups from AD
3. **Click a group** to add it — it appears as a tag
4. **Click the X on a tag** to remove it — the user will be removed from that group

**What happens technically:**

| Action | LDAP Operation |
|--------|---------------|
| Add to group | `LDAP_MODIFY_BATCH_ADD` — user's DN is added to the group's `member` attribute |
| Remove from group | `LDAP_MODIFY_BATCH_REMOVE` — user's DN is removed from the group's `member` attribute |

The system compares the desired groups (what you've selected) against the current groups (what the user already has). It only performs the adds and removes that are needed — no unnecessary writes.

### Password Operations

Three distinct password controls:

| Option | What It Does | Technical Detail |
|--------|-------------|-----------------|
| **Reset Password** | Sets a new password for the user | Writes `unicodePwd` attribute (UTF-16LE encoded) via secure LDAP |
| **Force change on next login** | User must set a new password at next logon | Sets `pwdLastSet = 0` |
| **Use default password** | Uses system default instead of generating random | Reads from `config_get('default_password')` |
| **Temporary Password** (custom field) | Lets you set a specific password | Leave blank for auto-generated (12-char random with uppercase, lowercase, digits, special chars) |

### Password Policy Flags

These three checkboxes are **independent** — they work on the user's password policy without resetting their password:

| Checkbox | What It Does | AD Mechanism |
|----------|-------------|-------------|
| **User must change password at next login** | Forces a password change on next logon (no reset needed) | Sets `pwdLastSet = 0` |
| **User cannot change password** | Prevents the user from changing their own password | Sets `userAccountControl` bit 64 (`PASSWD_CANT_CHANGE`) |
| **Password never expires** | Bypass domain password expiry policy | Sets `userAccountControl` bit 65536 (`DONT_EXPIRE_PASSWD`) |

The form **pre-checks** these boxes based on the user's current AD state. If the user already has "Password never expires" enabled, the box is checked when the form opens. You can see the current state at a glance.

> **When to use each:**
> - **Must change**: First-time logins, password policy compliance, security resets
> - **Cannot change**: Kiosk accounts, shared mailboxes, application service accounts
> - **Never expires**: Service accounts, automated process accounts, long-term system accounts

---

## Three Ways to Access Modify

| Method | Where | How |
|--------|-------|-----|
| **Sidebar Button** | Main action bar — "Modify" button | Type username in the search bar, click Modify |
| **Info Card Action** | After viewing user info | Click Modify directly from the info results |
| **Group Manager Edit** | Group manager's membership table | Click the edit icon next to any user in a group member list |

All three open the same form, pre-populated with the user's current data.

---

## Real Use Cases

### Employee Transfer (OU Migration)

```
Scenario: Sarah moves from Engineering to Marketing.
Old OU: Users > Engineering
New OU: Users > Marketing

Process:
  1. Type "ssmith" → Click [Modify]
  2. Form shows: OU = "Users > Engineering"
  3. Type "Market" in OU search → select "Users > Marketing"
  4. Click [Update User]
  Total time: 8 seconds
  
Result: Sarah's AD account moves to Marketing OU.
No group memberships are lost. UPN stays intact.
```

### Department-Wide Restructuring

```
Scenario: Your company reorganizes. 30 users need new OUs and new groups.
Old structure: Company A > Department X
New structure: Company B > Division Y > Team Z

Process per user:
  1. Type username → [Modify]
  2. Search and select new OU
  3. Remove old group tag (click X)
  4. Search and select new group
  5. [Update User]
  6. 8 seconds × 30 users = 4 minutes total

Without this: 15 minutes per user × 30 = 7.5 hours of ADUC work.
```

### Service Account Password Policy

```
Scenario: A monitoring service needs an account that:
  - Cannot be locked out by password expiry
  - The operator should not be able to change the password

Process:
  1. Type "svc_monitor" → [Modify]
  2. Check ☑ Password never expires
  3. Check ☑ User cannot change password
  4. [Update User]

Result: Two UAC flags set. Account stays active indefinitely.
Only domain admins can change the password. Service runs uninterrupted.
```

### New Hire — Forced Password Change

```
Scenario: New employee "rahman" is created but needs to set their own
password on first login.

Process:
  1. Type "rahman" → [Modify]
  2. Check ☑ User must change password at next login
  3. [Update User]
  
Result: pwdLastSet = 0. First login forces password creation.
```

### Bulk Access Revocation

```
Scenario: Contractor "jane_contract" finished project.
Remove from all project groups in one action.

Process:
  1. Type "jane_contract" → [Modify]
  2. Click X on each project group tag
  3. [Update User]

Result: User is removed from all deselected groups in a single operation.
Account remains active (if needed) or can be disabled separately.
```

---

## What Happens When You Click Update

```
You click [Update User]
        │
        ▼
  ┌──────────────────────────────────────────────┐
  │  1. Display Name changed? → Update it        │
  │  2. Description changed? → Update it          │
  │  3. Username changed? → Update sAMAccountName │
  │  4. OU changed? → Move user (ldap_rename)    │
  │  5. UPN updated? → Auto-syncs to new username │
  │  6. Password reset? → Set unicodePwd         │
  │  7. Groups changed? → Add/Remove memberships  │
  │  8. Policy flags? → Set UAC bits / pwdLastSet │
  └──────────────────────────────────────────────┘
        │
        ▼
  Instant feedback: "SUCCESS: User 'ssmith' updated successfully"
```

**Only what changed is applied.** If you only change the description, nothing else is touched. No unnecessary writes. No performance impact.

---

## Safety & Intelligence

| Feature | How It Protects You |
|---------|-------------------|
| **Pre-populated form** | See the current state before making changes — no guesswork |
| **No-op detection** | If nothing changed, the system tells you instead of writing empty updates |
| **Change confirmation** | Success/failure feedback with details of what was modified |
| **OU validation** | The system verifies the target OU exists before attempting the move |
| **Group validation** | Groups are checked against AD before membership operations |
| **Error resilience** | Individual attribute changes can fail without aborting the entire operation |
| **Audit trail** | Every modify operation is logged with operator, timestamp, and result |
| **Permission control** | RBAC guards — only authorized roles can modify users |
| **CSRF protection** | All modification requests require a valid security token |

---

## What You Get

| Benefit | Impact |
|---------|--------|
| **One form, all changes** | Replace 5-6 separate ADUC operations with one form |
| **Instant OU migration** | Move users between OUs in seconds, not minutes |
| **Bulk group sync** | Add and remove groups atomically — no partial states |
| **Auto-UPN update** | Rename username without breaking UPN — no manual fix needed |
| **Password policy control** | Must change, can't change, never expires — all in one place |
| **Visual group management** | See all current groups as tags. Add/remove by clicking. |
| **Pre-populated data** | Every field shows current AD state — see before you change |
| **Error handling** | Individual field failures don't stop the whole operation |

---

## Summary

One form. Every attribute that matters. Instant execution.

Username, display name, description, OU migration, group membership sync, password reset, and password policy flags — all in a single interface that pre-fills from the user's current state.

No ADUC. No PowerShell. No context-switching between different tools for different changes.

---

*AccessPilot — Modify Users, Complete Control, One Form.*
