# AD Configuration Guide

**How to configure OU Management, Group Management, and User Properties for your Active Directory domain.**

---

## 1. OU Management

Controls how Organizational Units are auto-created from HRMS data fields during user creation.

### 1.1 How It Works

When a user is created from your HRMS, the system reads the HRMS data fields (e.g., `OPERATING_UNIT_TITLE`, `DEPARTMENT_TITLE`) and builds an OU hierarchy automatically. Each level maps to one HRMS field. Existing OUs are reused. The user is placed in the lowest non-skipped level.

### 1.2 The 5-Level Hierarchy

| Level | Default Field | Purpose |
|-------|--------------|---------|
| 1 (Top OU) | `OPERATING_UNIT_TITLE` | Top-level operating unit |
| 2 | `DEPARTMENT_TITLE` | Department |
| 3 | `SECTION_TITLE` | Section |
| 4 | `PRODUCT_TITLE` | Product/Division |
| 5 (User OU) | `SUB_SECTION_TITLE` | Lowest level — user is placed here |

Each level is a child of the level above, forming a tree:

```
OU=OperatingUnit → OU=Department → OU=Section → OU=Product → OU=SubSection (User OU)
```

### 1.3 Enabling Customization

1. Navigate to **System Config → AD Objects** tab
2. Select your domain from the dropdown
3. Toggle **Customize** ON
4. The level dropdowns, prefix/suffix, and root OU fields will appear

### 1.4 Level Field Mapping

Each level dropdown lists available HRMS fields:

- `OPERATING_UNIT_TITLE`
- `DEPARTMENT_TITLE`
- `SECTION_TITLE`
- `PRODUCT_TITLE`
- `SUB_SECTION_TITLE`

**Rules:**
- Each field can be used at **only one level** (selecting a field at one level disables it at others)
- Set a level to **— Skip —** to omit it from the hierarchy
- Level 5 (User OU) is where the user account is placed; if skipped, the user goes to the next level up
- The hierarchy always flows top-down: Level 1 → Level 2 → Level 3 → Level 4 → Level 5

### 1.5 Prefix & Suffix

Optional text prepended/appended to every OU name in the hierarchy.

| Field | Example | Result |
|-------|---------|--------|
| Prefix | `BD_` | `BD_OperatingUnit` |
| Suffix | `_OU` | `OperatingUnit_OU` |
| Both | `BD_` + `_OU` | `BD_OperatingUnit_OU` |

### 1.6 Root OU (Optional Base Container)

Specify a container where all auto-created OUs are placed. For example, if you have a container `OU=CompanyUsers` in your domain, setting **Root OU** to `OU=CompanyUsers` will create the hierarchy as:

```
OU=OperatingUnit → OU=Department → OU=Section → ... 
     (all created under the Root OU you selected)
```

Leave empty to create OUs directly at the domain root.

### 1.7 Preview

As you configure levels, the preview panel updates in real time showing the expected OU tree. Use this to verify your configuration before saving.

### 1.8 Saving

1. Click **Save to Domain** to persist the configuration
2. The settings are saved per-domain (each domain can have its own OU mapping)
3. To revert, click **Reset** to reload the saved settings from the server
4. Toggle Customize OFF to restore the default 5-level hierarchy

---

## 2. Group Management

Controls how security groups are auto-created alongside OUs during user creation.

### 2.1 How It Works

When an OU is created, a matching security group is also created inside that OU. The group name is derived from the OU name. When a user is placed in an OU, they are automatically added to the corresponding group.

### 2.2 Default Behavior

By default (Customize OFF):
- A security group is created for every OU level
- Group name: `{OU Name} Group` (e.g., `OperatingUnit Group`)
- Groups contain users placed in that OU and all child OUs

### 2.3 Enabling Customization

1. In the **Group Management** card, toggle **Customize** ON
2. Configure the options below

### 2.4 Group Options

| Option | Description |
|--------|-------------|
| **Auto-Create Groups** | If enabled, groups are created automatically alongside OUs. If disabled, only OUs are created — no groups. |
| **Group Name Prefix** | Text prepended to the group name (e.g., `ACC_` → `ACC_OperatingUnit Group`) |
| **Group Name Suffix** | Text appended to the group name (e.g., `_SG` → `OperatingUnit Group_SG`) |

### 2.5 Conditional Group Rules

You can define rules that override group naming or auto-create behavior based on HRMS field values.

**Example rules:**

| Condition Field | Value | Group Name Override |
|----------------|-------|-------------------|
| `OPERATING_UNIT_TITLE` | `Corporate` | `Corp_Users` |
| `OPERATING_UNIT_TITLE` | `Retail` | (use default) |

**How rules work:**
- When a user is created, the system checks each rule in order
- If the user's HRMS data matches a rule's field+value, the rule's group name override is applied
- If no rule matches, the default naming (prefix + OU name + suffix + ` Group`) is used

### 2.6 Saving

Same as OU Management: click **Save to Domain** to persist per-domain.

---

## 3. User Properties Configuration

Controls which HRMS data fields map to which Active Directory user attributes during creation and sync.

### 3.1 How It Works

When a user is created or updated, the system takes the HRMS data and writes it to specific AD attributes. The mapping is defined per-domain.

### 3.2 HRMS → AD Field Mapping

| HRMS Field | AD Attribute | Configurable? | Example Value |
|----------------|-------------|---------------|---------------|
| `FULL_NAME` | `DisplayName` | Fixed | `John Doe` |
| `EMPLOYEE_ID` | `EmployeeID` | Fixed | `EMP00123` |
| `FIRST_NAME` | `GivenName` | Fixed | `John` |
| `LAST_NAME` | `SN` (Surname) | Fixed | `Doe` |
| `EMAIL` | `EmailAddress` | Fixed | `john.doe@company.com` |
| `MOBILE` | `Mobile` | Fixed | `+8801712345678` |
| `DESIGNATION` | `Title` | Fixed | `Software Engineer` |
| `DEPARTMENT_TITLE` | `Department` | Fixed | `Engineering` |
| `LOCATION_TITLE` | `Office` | Fixed | `Dhaka` |
| `RANK` | `ExtensionAttribute1` | Fixed | `Senior` |
| `OPERATING_UNIT_TITLE` | `Company` | Fixed | `Corporate` |
| `SECTION_TITLE` | `Description` | Fixed | `Platform Team` |
| `PRODUCT_TITLE` | `Division` | Configurable | `Product X` |

**Fixed:** The mapping is hardcoded and cannot be changed in the UI.
**Configurable:** The target AD attribute can be selected from a list.

### 3.3 User Name Format

| Component | Source | Example |
|-----------|--------|---------|
| **Login ID** (`SamAccountName`) | Auto-generated from name and employee ID | `jdoe` or `jdoe_00123` |
| **UPN** | `SamAccountName` + `@` + domain suffix | `jdoe@company.com` |
| **Full Name** | `FULL_NAME` from HRMS | `John Doe` |

### 3.4 Password & Account Settings

- Password is generated automatically based on domain policy
- User must **change password at next logon** by default
- Account is **enabled** on creation

### 3.5 Post-Creation Actions

After the user is created in AD:
1. A meeting is scheduled (if configured in the domain settings)
2. The system records all actions taken (OU creation, group membership, etc.)
3. A summary report is generated showing success/failure per step

---

## 4. Configuration Checklist

When setting up a new domain:

- [ ] **OU Management:** Configure level mapping (or use defaults)
- [ ] **OU Management:** Set prefix/suffix if needed
- [ ] **OU Management:** Set Root OU if using a custom container
- [ ] **Group Management:** Enable/disable auto-create as needed
- [ ] **Group Management:** Set group prefix/suffix if needed
- [ ] **Group Management:** Define conditional rules if needed
- [ ] **User Properties:** Review HRMS→AD field mapping
- [ ] **Save:** Click Save to Domain for each configuration section
- [ ] **Test:** Create a test user to verify the full flow

---

## 5. Troubleshooting

| Issue | Likely Cause | Solution |
|-------|-------------|----------|
| OU not created | Field value is empty in HRMS data | Check the HRMS data for that field |
| User not in correct OU | Level mapping is wrong | Verify each level's field assignment |
| Group not created | Auto-Create Groups is OFF | Enable auto-create in Group Management |
| Group has wrong name | Conditional rule is matching incorrectly | Check rule field/value and order |
| User creation fails | Required field mapping is missing | Check AD attribute requirements for the domain |
| Preview shows wrong tree | Config not saved | Click Save to Domain, then refresh |

---

*For detailed configuration reference, see the **AD Objects** tab under System Config in the portal.*
