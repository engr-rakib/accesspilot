# PLAN: OU & Group Management Configuration (AD Objects Tab)

> **Important:** This is a CUSTOMIZATION feature, NOT a default AD behavior.
> Each domain admin configures their own OU/Group creation rules from the System Config UI.
> Domains without configuration get the default hardcoded behavior (zero change).

---

## 1. Overview — How OU & Group Are Currently Auto-Created

### OU Auto-Structure (Current Hardcoded Behavior)

When `create-user-core.ps1` creates a user, the AD OU path is built from HRMS API fields in this fixed order:

```
OPERATING_UNIT_TITLE  →  DEPARTMENT_TITLE  →  SECTION_TITLE  →  PRODUCT_TITLE  →  SUB_SECTION_TITLE
     (Level 1)              (Level 2)            (Level 3)         (Level 4)           (Level 5)
```

- If an OU already exists → reused (idempotent)
- If an OU doesn't exist → auto-created
- User is placed in the **lowest-level OU** (Level 5 / SUB_SECTION_TITLE)
- The user's AD **Description** field stores: `Rank: 27 | OU: Dept > Section > Product > Sub-Section`

### Group Auto-Creation (Current Hardcoded Behavior)

- For each OU level in the hierarchy, a **security group** is auto-created
- Example naming: `"Server Administration Group"`, `"IT Department Group"`
- The user is added to each auto-created group along the OU path
- Existing groups are reused (idempotent)

---

## 2. What the Cards Will Show & Allow

### Card A: OU Management

**Purpose:** Admin visualizes how OU path is built from API fields, and **customizes** the mapping.

#### Visual Context (Always Visible — Toggle OFF State)

```
┌─ OU Management ──────────────────────────────────────────────┐
│ Controls how Organizational Units are auto-created from       │
│ HRMS API fields during user creation.                        │
│                                                               │
│ ℹ️ OU path is auto-built from API fields:                     │
│                                                               │
│   OPERATING_UNIT_TITLE  →  DEPARTMENT_TITLE  →  SECTION_TITLE │
│   →  PRODUCT_TITLE  →  SUB_SECTION_TITLE                     │
│                                                               │
│   Existing OUs are reused. User placed in lowest-level OU.    │
│                                                               │
│ [Customize] — toggle OFF                                      │
│ [Save to Domain] [Reset] (disabled)                           │
└───────────────────────────────────────────────────────────────┘
```

#### Customization Options (Toggle ON)

| # | Field | Control | Default | Description |
|---|-------|---------|---------|-------------|
| 1 | **Level 1 (Top OU)** | Dropdown | `OPERATING_UNIT_TITLE` | Which API field provides the top-level OU name |
| 2 | **Level 2** | Dropdown | `DEPARTMENT_TITLE` | Second-level OU field |
| 3 | **Level 3** | Dropdown | `SECTION_TITLE` | Third-level OU field |
| 4 | **Level 4** | Dropdown | `PRODUCT_TITLE` | Fourth-level OU field |
| 5 | **Level 5 (User OU)** | Dropdown | `SUB_SECTION_TITLE` | Lowest-level OU where the user is placed |
| 6 | **OU Prefix** | Text input | `""` (empty) | Static prefix added to every OU name (e.g., `BD_`) |
| 7 | **OU Suffix** | Text input | `""` (empty) | Static suffix added to every OU name (e.g., `_OU`) |
| 8 | **Skip Level** | Empty option in dropdown | — | Select `"— Skip —"` to exclude a level from the path |

**Dropdown options (for all levels):**
- `OPERATING_UNIT_TITLE`
- `DEPARTMENT_TITLE`
- `SECTION_TITLE`
- `PRODUCT_TITLE`
- `SUB_SECTION_TITLE`
- `"— Skip —"` (removes this level)

**Preview (live update as user changes fields):**
```
OU=BD_OperatingUnit_OU → OU=BD_Department_OU → OU=BD_Section_OU → OU=BD_SubSection_OU
```

---

### Card B: Group Management

**Purpose:** Admin visualizes how security groups are auto-created per OU level, and configures **naming patterns** and **conditional assignment rules**.

#### Visual Context (Always Visible — Toggle OFF State)

```
┌─ Group Management ────────────────────────────────────────────┐
│ Controls how security groups are auto-created and how users   │
│ are assigned to groups during creation.                       │
│                                                               │
│ ℹ️ For each OU level in the hierarchy, a security group is    │
│   auto-created (e.g., "Server Administration Group").         │
│   The user is added to each auto-created group along the      │
│   OU path. Existing groups are reused.                        │
│                                                               │
│ [Customize] — toggle OFF                                      │
│ [Save to Domain] [Reset] (disabled)                           │
└───────────────────────────────────────────────────────────────┘
```

#### Customization Options (Toggle ON)

##### Section 1: Auto-Creation Settings

| # | Field | Control | Default | Description |
|---|-------|---------|---------|-------------|
| 1 | **Auto-Create Groups** | Radio (Enabled/Disabled) | `Enabled` | Master switch for auto-group creation |
| 2 | **Group Name Prefix** | Text input | `""` (empty) | Prefix for auto-created group names (e.g., `GRP_`) |
| 3 | **Group Name Suffix** | Text input | `""` (empty) | Suffix for auto-created group names (e.g., `_Group`) |

##### Section 2: Conditional Group Assignment Rules

Each rule = `[API Field] = [Value] → Add to [Group Name]`

**Example rules:**
| API Field | Value | Target Group |
|-----------|-------|-------------|
| `DEPARTMENT_TITLE` | `Information Technology` | `IT_Support` |
| `SECTION_TITLE` | `Human Resources` | `HR_Team` |
| `LOCATION_TITLE` | `Dhaka` | `Dhaka_Office` |
| `DESIGNATION` | `Manager` | `Managers_All` |

**Behavior:**
- All matching rules apply (user can be added to multiple groups)
- If multiple rules match, user is added to ALL matching groups
- Rules are evaluated during user creation
- Existing groups are NOT removed — only ADDED

**Preview (live update):**
```
Auto: [Section_Group, Dept_Group, ...]
Conditional: (dept=IT → IT_Support), (loc=Dhaka → Dhaka_Office)
```

---

## 3. Configuration Schema (Per-Domain)

Extend the existing domain config structure:

```json
{
  "key": "whildc",
  "label": "WHILD Corporate",
  "naming": { ... },
  "ou_config": {
    "customized": true,
    "levels": {
      "1": { "field": "OPERATING_UNIT_TITLE" },
      "2": { "field": "DEPARTMENT_TITLE" },
      "3": { "field": "SECTION_TITLE" },
      "4": { "field": "PRODUCT_TITLE" },
      "5": { "field": "SUB_SECTION_TITLE" }
    },
    "prefix": "",
    "suffix": ""
  },
  "group_config": {
    "customized": true,
    "auto_create": true,
    "prefix": "",
    "suffix": "",
    "rules": [
      { "field": "DEPARTMENT_TITLE", "value": "Information Technology", "group": "IT_Support" },
      { "field": "LOCATION_TITLE", "value": "Dhaka", "group": "Dhaka_Office" }
    ]
  }
}
```

### Storage
- **Same as `naming`** — stored in the domain JSON object
- File: `App_Data/domains.json` (or whatever stores domain data)
- `domain_api.php` `update_domain` action already saves the full domain object as JSON
- No new storage layer needed — `ou_config` and `group_config` are just new keys in the existing domain object

---

## 4. Implementation Steps Checklist

### Phase A: Frontend (UI Cards)

- [ ] **A1** — Add `Customize` toggle to OU Management card header
- [ ] **A2** — Add OU default hint block (visible when toggle OFF)
- [ ] **A3** — Add OU custom fields block (hidden when toggle OFF):
  - [ ] Level 1–5 dropdowns with all API field options + `— Skip —`
  - [ ] OU Prefix text input
  - [ ] OU Suffix text input
  - [ ] Live Preview section
- [ ] **A4** — Add `Customize` toggle to Group Management card header
- [ ] **A5** — Add Group default hint block (visible when toggle OFF)
- [ ] **A6** — Add Group custom fields block (hidden when toggle OFF):
  - [ ] Auto-Create radio (Enabled/Disabled)
  - [ ] Group Prefix text input
  - [ ] Group Suffix text input
  - [ ] Conditional Rules section:
    - [ ] Rule row template (field dropdown + value input + target group input + remove button)
    - [ ] `"+ Add Rule"` button
    - [ ] Live Preview section
- [ ] **A7** — Add inline JavaScript (inside the existing IIFE):
  - [ ] `ouSetMode(customizing)` — toggle show/hide for OU card
  - [ ] `ouUpdatePreview()` — live preview based on current selections
  - [ ] `grpSetMode(customizing)` — toggle show/hide for Group card
  - [ ] `grpUpdatePreview()` — live preview based on current selections
  - [ ] Rule row add/remove handlers
  - [ ] Save/Reset button handlers (disable/enable based on toggle)
- [ ] **A8** — Wire `adoDomainSelector` change event → load OU/Group config for selected domain

### Phase B: Backend (API + Config)

- [ ] **B1** — Extend `domain_api.php` to handle `ou_config` and `group_config` in save/load
- [ ] **B2** — Ensure `domain_api?action=update_domain` saves `ou_config` and `group_config` as part of domain object
- [ ] **B3** — Ensure `domain_api?action=list_domains` returns `ou_config` and `group_config` per domain
- [ ] **B4** — (If needed) Create new API action `save_ou_config` / `save_group_config`

### Phase C: LDAP/PowerShell Integration

- [ ] **C1** — Modify PowerShell `create-user-core.ps1` to accept dynamic OU field mapping instead of hardcoded
- [ ] **C2** — Modify PowerShell `create-user-core.ps1` to accept group prefix/suffix and conditional rules
- [ ] **C3** — Add API endpoint or config passing mechanism to send `ou_config` + `group_config` to PowerShell
- [ ] **C4** — Fallback to hardcoded defaults when config is missing

### Phase D: Testing

- [ ] **D1** — Test OU Management with default config (no customization) — verify backward compat
- [ ] **D2** — Test OU Management with customized field mapping
- [ ] **D3** — Test OU Management with prefix/suffix
- [ ] **D4** — Test OU Management with skipped levels
- [ ] **D5** — Test Group Management with auto-create OFF
- [ ] **D6** — Test Group Management with prefix/suffix
- [ ] **D7** — Test Conditional Rules (single rule, multiple rules, no matching rule)
- [ ] **D8** — Test Save/Load round-trip on page reload

---

## 5. Files to Modify

| # | File | Phase | Change |
|---|------|-------|--------|
| 1 | `resources/views/pages/tools/system_config_view.php` | A | Add OU & Group cards HTML with toggle, fields, preview |
| 2 | `public/resources/frontend/js/modules/system_config_actions.js` | A | (If needed) Add shared OU/Group config save logic |
| 3 | `app/Application/Http/Controllers/domain_api.php` | B | Handle `ou_config` + `group_config` in save/load |
| 4 | `app/Infrastructure/PowerShell/Scripts/create-user-core.ps1` | C | Accept dynamic OU/group config from API |
| 5 | `docs/operator/AD_OBJECTS_CONFIG.md` | D | Operator documentation |

---

## 6. UI Mockup — Final Layout

### AD Objects Tab (Right Column)

```
┌─────────────────────────────────────────────────────────────────┐
│  AD Objects                                                      │
│                                                                  │
│  ┌─────────────────────────────────────┐  ┌───────────────────┐  │
│  │ User Properties Configuration       │  │ Per-Domain info   │  │
│  │ (existing — naming modes, preview)  │  │                   │  │
│  │                                     │  ├───────────────────┤  │
│  │ [Customize] toggle                  │  │ OU Management     │  │
│  │ Fields, Preview, Save/Reset         │  │ [Customize] toggle│  │
│  └─────────────────────────────────────┘  │ Fields, Preview   │  │
│                                            │ Save/Reset        │  │
│                                            ├───────────────────┤  │
│                                            │ Group Management  │  │
│                                            │ [Customize] toggle│  │
│                                            │ Fields, Rules     │  │
│                                            │ Preview           │  │
│                                            │ Save/Reset        │  │
│                                            └───────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 7. Risk & Compatibility

- **✅ Backward compatible** — No `ou_config` / `group_config` → hardcoded defaults
- **✅ Existing users unaffected** — Only new creation
- **✅ No DB migration** — `domains.json` already free-form JSON
- **✅ No new API endpoint needed** — `domain_api` already supports save/load of arbitrary domain keys
- **⚠️ PowerShell changes needed** — `create-user-core.ps1` must be updated to accept dynamic config

---

## 8. Decision Log

| Date | Decision |
|------|----------|
| 2026-06-15 | OU/Group config stored per-domain (same as `naming`) |
| 2026-06-15 | Conditional rules stored as array of `{field, value, group}` objects |
| 2026-06-15 | UI follows same pattern as User Properties Configuration (Customize toggle) |
| 2026-06-15 | Phase C (PowerShell) deferred until Phase A+B are implemented and tested |

---

## 9. Open Questions

1. Should conditional rules support **AND/OR** logic between multiple conditions? (Currently: single field = single value)
2. Should auto-created groups have a configurable **scope** (Global/DomainLocal/Universal)?
3. Should there be a "default groups" list — groups EVERY new user is added to regardless of conditions?
