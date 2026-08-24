# AccessPilot — IIS Client Deployment Guide

## 1. Prerequisites

- **Windows Server** 2016 / 2019 / 2022 with IIS enabled
- **PHP 8.2+** (with extensions: ldap, mbstring, zip, openssl, curl, json)
- **PowerShell 5.1+** (for AD operations fallback)
- **IIS URL Rewrite Module** (optional, for clean URLs)

## 2. IIS Setup

### 2.1 Create IIS Site

1. Open **IIS Manager**
2. Right-click **Sites** → **Add Website**
   - Site name: `AccessPilot`
   - Physical path: `C:\inetpub\wwwroot\UM-portal`
   - Port: `80` (or `443` for HTTPS)
3. Set Application Pool to **No Managed Code** (Classic Pipeline)

### 2.2 Configure PHP Handler

1. In IIS Manager → **Handler Mappings** → **Add Module Mapping**
   - Request path: `*.php`
   - Module: `FastCgiModule`
   - Executable: `C:\PHP8\php-cgi.exe` (adjust to your PHP path)
   - Name: `PHP via FastCGI`
2. Set **Application Pool** → **Advanced Settings**:
   - Enable 32-Bit Applications: `False`
   - Identity: `ApplicationPoolIdentity` (or custom service account)

### 2.3 Set Directory Permissions

```powershell
# App_Data — writable for sessions, locks
icacls C:\inetpub\wwwroot\UM-portal\App_Data /grant "IIS AppPool\AccessPilot:(CI)(OI)(M)"

# Secure files vault — must exist and be writable
# Default: C:\inetpub\Desk_secure_files
# Create if missing:
New-Item -ItemType Directory -Path "C:\inetpub\Desk_secure_files\appusers" -Force
New-Item -ItemType Directory -Path "C:\inetpub\Desk_secure_files\requests" -Force
New-Item -ItemType Directory -Path "C:\inetpub\Desk_secure_files\passwd" -Force
New-Item -ItemType Directory -Path "C:\inetpub\Desk_secure_files\monitoring" -Force
New-Item -ItemType Directory -Path "C:\inetpub\Desk_secure_files\deployment_active_license" -Force
New-Item -ItemType Directory -Path "C:\inetpub\Desk_secure_files\vendor_issued_licenses" -Force
New-Item -ItemType Directory -Path "C:\inetpub\Desk_secure_files\vendor_signing_keys" -Force
New-Item -ItemType Directory -Path "C:\inetpub\Desk_secure_files\app_notifications" -Force

icacls C:\inetpub\Desk_secure_files /grant "IIS AppPool\AccessPilot:(CI)(OI)(M)"

# Logs directory
New-Item -ItemType Directory -Path "C:\access_pilot_logs" -Force
icacls C:\access_pilot_logs /grant "IIS AppPool\AccessPilot:(CI)(OI)(M)"
```

## 3. First-Time Setup

### 3.1 Clear Production Defaults (CRITICAL)

The codebase comes with production organization settings. On your new server, you MUST clear these before first run:

Edit `config/app.php` and clear these values:

```php
'domain_name' => '',              // Your domain, e.g. 'mycompany.local'
'org_name' => '',                  // Your org name, e.g. 'My Company Ltd.'
'base_dn' => '',                   // Your LDAP base, e.g. 'DC=mycompany,DC=local'
'deployment_id' => '',             // Will be auto-generated
```

> **Why?** The production values (domain: wgbd.com, org: Walton) are from the vendor's own deployment. Your organization must register with your own domain and org name.

### 3.2 Delete setup_complete.lock (if exists)

```powershell
Remove-Item C:\inetpub\wwwroot\UM-portal\App_Data\setup_complete.lock -ErrorAction SilentlyContinue
```

This ensures bootstrap creates a fresh admin vault on first request.

### 3.3 Restart IIS

```powershell
iisreset
```

## 4. First Login

### 4.1 Default Credentials

| Field | Value |
|-------|-------|
| URL | `http://your-server/` |
| Username | `admin` |
| Password | `accesspilot@123` |

### 4.2 Forced Password Change

1. Login with default credentials
2. System prompts: **"Mandatory password change required"**
3. Set a **strong password**:
   - Minimum 8 characters
   - At least 1 uppercase letter
   - At least 1 lowercase letter
   - At least 1 digit
   - At least 1 special character
4. After successful change → login again with new password

### 4.3 License Restriction

After login, you will see the **License** page. The application is in restricted mode because no license is configured. You can still access:

| Page | URL | Status |
|------|-----|--------|
| **System Configuration** | `index.php?page=system_config` | ✅ Fully working |
| **License** | `index.php?page=license` | ✅ Fully working |
| **Profile** | `index.php?page=profile` | ✅ Read-only |
| All other pages | — | 🔒 Locked (visible but disabled) |

## 5. Organization Registration

### 5.1 Navigate to System Configuration

Go to `index.php?page=system_config`

### 5.2 Register Your Organization

In the **Organization Setup** section:

| Field | Description |
|-------|-------------|
| **Organization name** | Your company name (e.g., "Acme Corporation") |
| **Primary domain** | Your AD domain (e.g., "acme.local") |

Click **Register**.

### 5.3 Get Deployment ID

After registration, the **Deployment ID** field will show a unique identifier (e.g., `a1b2c3d4-e5f6-7890-abcd-ef1234567890`).

**Copy this ID** — you will need it for license activation.

## 6. License Activation

### 6.1 Send Deployment ID to Vendor

Email the following to your vendor:
- **Deployment ID** (from step 5.3)
- **Organization name**
- **Primary domain**

### 6.2 Receive License PEM

The vendor will return a signed license certificate (PEM format):

```
-----BEGIN LICENSE-----
base64encodeddata...
...
-----END LICENSE-----
```

### 6.3 Upload License

1. Go to `index.php?page=license`
2. Paste the **entire PEM** into the text area
3. Click **Synchronize Renewal**
4. On success → all features are unlocked

## 7. Domain Configuration

### 7.1 Navigate to System Configuration

`index.php?page=system_config` → **Domain** tab

### 7.2 Choose Backend

| Option | When to Use |
|--------|-------------|
| **LDAP** | PHP LDAP extension can reach the domain controller directly |
| **PowerShell** | AD operations via PowerShell (requires RSAT tools on server) |

### 7.3 Add Domain

Click **Add Domain** and fill:

| Field | Description |
|-------|-------------|
| **Domain Key** | Short identifier (e.g., `mycorp`) |
| **Domain Label** | Display name (e.g., `My Corporation`) |
| **LDAP Host** | Domain controller hostname (e.g., `dc01.acme.local`) |
| **Port** | `389` (LDAP) or `636` (LDAPS) |
| **Base DN** | e.g., `DC=acme,DC=local` |
| **Bind DN** | Service account DN (e.g., `CN=svc_ap,OU=Service Accounts,DC=acme,DC=local`) |
| **Bind Password** | Service account password |

Click **Test Connection** → then **Save Domain**

### 7.4 Set as Active Domain

Click **Switch** on the domain row to make it the active domain.

## 8. API Integration

### 8.1 Configure HRMS API

`index.php?page=system_config` → **Application** tab → **API Integration**

| Field | Description |
|-------|-------------|
| **HRMS API Endpoint** | Employee data source (e.g., `https://hrms.acme.com/api/employee`) |
| **HRMS Image Base URL** | Employee photo base URL |
| **Status Endpoint URL** | (Optional) Employee status endpoint |

Click **Save Changes**.

### 8.2 Test API

Enter an Employee ID and click **Test** to verify the API connection.

## 9. AD Object Naming

### 9.1 User Properties

`index.php?page=system_config` → **AD Objects** tab

Configure how HRMS names map to AD fields:
- **sAMAccountName Mode**: How usernames are generated
- **Given Name / Surname Mode**: Name part extraction
- **Display Name Format**: AD display name format

Enable **Customize** to configure per-domain.

### 9.2 OU Management

Configure the Organizational Unit hierarchy based on API fields:
- Default: `OPERATING_UNIT_TITLE → DEPARTMENT_TITLE → SECTION_TITLE → PRODUCT_TITLE → SUB_SECTION_TITLE`
- Enable **Customize** to reorder or skip levels

## 10. Codebase Updates

### 10.1 Update via WinCP

1. Upload entire project root via WinCP (overwrite all)
2. Run cleanup script:
   ```powershell
   # Remove runtime files brought from Windows
   Remove-Item C:\inetpub\wwwroot\UM-portal\App_Data\setup_complete.lock -ErrorAction SilentlyContinue
   Remove-Item C:\inetpub\wwwroot\UM-portal\App_Data\*.json -Exclude internal_admin.json -ErrorAction SilentlyContinue
   Remove-Item C:\inetpub\wwwroot\UM-portal\logs\* -Recurse -ErrorAction SilentlyContinue
   Remove-Item C:\inetpub\wwwroot\UM-portal\tmp\* -Recurse -ErrorAction SilentlyContinue
   ```
3. Clear PHP cache:
   ```powershell
   iisreset
   ```
4. Hard refresh browser (Ctrl+F5)

### 10.2 Important: config/app.php

After codebase update, your custom values (org_name, domain_name, etc.) in `config/app.php` may be overwritten. **Before deploying**, ensure your organization's values are restored in this file.

**Recommended workflow:**
1. Before update: backup `config/app.php` + `config/license_public.pem`
2. Upload new code
3. Restore backed-up `config/app.php` and `config/license_public.pem`
4. `iisreset`

## 11. Troubleshooting

### 11.1 "Login allowed, but license restriction redirected"

The login succeeds but redirects to License page. This is expected when no valid license PEM is configured. Complete steps 5-6 (register org → get license).

### 11.2 System configuration page not loading

Check browser console. If "Tracking Prevention blocked access to storage":
- Add the site to browser's allowed storage list
- Or use a different browser in private/incognito mode

If page is completely blank:
- Check PHP error log
- Verify `resources/views/pages/tools/system_config_view.php` exists

### 11.3 "Operation restricted" on POST actions

This appears when license restriction blocks write operations. Complete license activation (step 6).

### 11.4 Menu items missing

If only Home, Profile, License, Vendor License show:
- Admin user must have `core_admin` role (check vault's users.json)
- After fresh deploy, first login forces password change → then full menu is available

### 11.5 Permanent Redirect to Login Page

Clear browser cookies/cache or use a private window.

---

*Document version: 1.0 — June 2026*
