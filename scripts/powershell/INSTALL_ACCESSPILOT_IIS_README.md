# AccessPilot IIS Installer Guide

This guide explains how to host the current AccessPilot application on a client Windows Server using the automated IIS installer.

Primary script:
- `Install-AccessPilot-IIS.ps1`

## 1. What The Installer Does

The installer:
- checks for elevated PowerShell
- ensures IIS features needed by the app are available
- configures PHP FastCGI for `php-cgi.exe`
- creates or updates an IIS application pool
- creates or updates an IIS website
- points the site to `public/` as the web root
- creates secure vault and log directories
- updates `config/storage.php`
- initializes `accesspilot_deployment_identity.xml` and runtime metadata through `Web-Deploy-Config.ps1`
- prepares the internal recovery admin profile
- removes a stale setup lock only when it detects a fresh deployment with no external user database yet

## 2. What It Does Not Do

- it does not issue a license
- it does not apply a signed license JSON
- it does not install PHP for you
- it does not copy the app from another machine
- it does not configure HTTPS certificates

## 3. Before You Run It

You need:
- Windows Server with elevated PowerShell access
- application files already present on the server
- PHP installed, including `php-cgi.exe`
- permission to create/update IIS sites and app pools
- intended values for:
  - AD domain
  - BaseDN
  - default password
  - AD admin username
  - AD admin password

Typical app location:
- `C:\inetpub\wwwroot\UM-portal`

Typical PHP CGI path:
- `C:\php8.5.4_nts\php-cgi.exe`

## 4. Interactive Install

From the app root:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\powershell\Install-AccessPilot-IIS.ps1
```

The script will prompt for the required runtime values.

## 5. Non-Interactive Install Example

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\powershell\Install-AccessPilot-IIS.ps1 `
  -SiteName "AccessPilot" `
  -AppPoolName "AccessPilotPool" `
  -Port 80 `
  -HostHeader "portal.client.local" `
  -DeploymentRoot "C:\inetpub\wwwroot\UM-portal" `
  -PhpCgiPath "C:\php8.5.4_nts\php-cgi.exe" `
  -SecureBasePath "C:\inetpub\Desk_secure_files" `
  -LogBasePath "C:\access_pilot_logs" `
  -Domain "client.com" `
  -BaseDN "DC=client,DC=com" `
  -DefaultPassword "TempPass@123" `
  -AdminUser "administrator@client.com" `
  -AdminPassword "SecretPass"
```

## 6. Parameters

### IIS / hosting
- `-SiteName`
- `-AppPoolName`
- `-HostHeader`
- `-Port`
- `-DeploymentRoot`
- `-PhpCgiPath`

### Storage
- `-SecureBasePath`
- `-LogBasePath`

### Runtime identity / AD metadata
- `-Domain`
- `-BaseDN`
- `-DefaultPassword`
- `-AdminUser`
- `-AdminPassword`

## 7. Files And Paths It Touches

### Inside the application
- `config/storage.php`
- `App_Data/internal_admin.json`
- possibly `App_Data/setup_complete.lock`

### External paths
- secure vault root, default:
  - `C:\inetpub\Desk_secure_files`
- log root, default:
  - `C:\access_pilot_logs`
- deployment XML:
  - `C:\inetpub\Desk_secure_files\accesspilot_deployment_identity.xml`
- license state:
  - `C:\inetpub\Desk_secure_files\license_state.json`

### IIS objects
- website
- application pool
- FastCGI handler registration

## 8. After Install

After the installer finishes:

1. browse to the site URL
2. confirm the site loads
3. log in with the recovery admin only if this is a fresh deployment
4. open System Configuration and confirm storage looks writable
5. open the License page
6. apply the signed vendor license JSON

## 9. Recovery Admin Behavior

The installer prepares:
- `App_Data/internal_admin.json`

Fresh deployment behavior:
- if there is no external `users.json` yet, the app can initialize the default admin path

Stale deployment protection:
- if a setup lock exists but no external user database exists, the installer removes the stale lock so first-run initialization can proceed

## 10. Common Failure Points

### `php-cgi.exe` not found
- pass `-PhpCgiPath` explicitly

### IIS binding already in use
- change `-Port` or `-HostHeader`

### access denied / site creation failure
- run PowerShell as Administrator

### app loads but PHP does not execute
- confirm FastCGI registration
- confirm handler mapping exists for `*.php`
- confirm the site points to `public/`

### app loads but licensing is restricted
- this is expected until a signed license JSON is applied

## 11. Short SOP

1. copy app files to server
2. install PHP if needed
3. run `Install-AccessPilot-IIS.ps1` as Administrator
4. verify site opens
5. apply signed license JSON

## 12. Related Files

- `Install-AccessPilot-IIS.ps1`
- `Web-Deploy-Config.ps1`
- `KEymasterConfigPro.ps1`
- `README.md`
