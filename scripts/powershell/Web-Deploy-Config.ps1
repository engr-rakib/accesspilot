# Web-Deploy-Config.ps1 - Non-interactive Configuration Deployment
param(
    [string]$Domain,
    [string]$BaseDN,
    [string]$AdminUser,
    [string]$AdminPass,
    [string]$AppName = "AccessPilot",
    [string]$DefaultPassword,
    [string]$BaseLogPath,
    [string]$SecureConfigPath,
    [string]$LicenseStatePath
)

$ErrorActionPreference = "Stop"

try {
    # 1. Prepare Directory and Permissions
    $ConfigDir = Split-Path $SecureConfigPath -Parent
    if (-not (Test-Path $ConfigDir)) {
        New-Item -Path $ConfigDir -ItemType Directory -Force | Out-Null
    }

    # Grant IIS user group permissions
    try {
        $Acl = Get-Acl $ConfigDir
        $Ar = New-Object System.Security.AccessControl.FileSystemAccessRule("IIS_IUSRS", "Modify", "ContainerInherit,ObjectInherit", "None", "Allow")
        $Acl.AddAccessRule($Ar)
        Set-Acl $ConfigDir $Acl
    } catch {
        Write-Warning "Failed to set IIS permissions."
    }

    # 2. Handle AD Credentials (Support "Keep Existing")
    $Cred = $null
    $hasNewPass = ($null -ne $AdminPass -and $AdminPass.Trim().Length -gt 0)
    
    if ($hasNewPass) {
        # New password provided
        $SecPass = ConvertTo-SecureString $AdminPass -AsPlainText -Force
        $Cred = New-Object System.Management.Automation.PSCredential($AdminUser, $SecPass)
    } elseif (Test-Path $SecureConfigPath) {
        # Try to preserve existing credential
        try {
            $OldConfig = Import-Clixml $SecureConfigPath
            if ($null -ne $OldConfig.AdminCredential) {
                # Update username if it changed, but keep the secured password
                $Cred = New-Object System.Management.Automation.PSCredential($AdminUser, $OldConfig.AdminCredential.Password)
            }
        } catch {
            Write-Warning "Could not load old config to preserve password."
        }
    }

    if ($null -eq $Cred) {
        throw "Administrative Password is required for initial setup or credential rotation."
    }

    # 3. Create/Update Secure XML
    $Config = [PSCustomObject]@{
        Domain = $Domain
        BaseDN = $BaseDN
        DefaultPassword = $DefaultPassword
        AppName = $AppName
        BaseLogPath = $BaseLogPath
        AdminCredential = $Cred
    }

    $Config | Export-Clixml $SecureConfigPath -Force

    # 4. Update Log Directory
    if (-not (Test-Path $BaseLogPath)) {
        New-Item -Path $BaseLogPath -ItemType Directory -Force | Out-Null
    }

    # 5. Update/Maintain License JSON
    # If a full license certificate exists, we preserve it WITHOUT modification (signature protection)
    $WriteLicense = $true
    if (Test-Path $LicenseStatePath) {
        $CurrentJSON = Get-Content -Path $LicenseStatePath -Raw | ConvertFrom-Json
        if ($CurrentJSON.signature) {
            # DO NOT update or rewrite the file if it has a signature.
            # Rewriting can add a BOM or change formatting, which might break PHP parsing.
            $WriteLicense = $false
        } else {
            # Re-create simple state for non-signed licenses (legacy fallback)
            $CurrentJSON = [ordered]@{
                product_name = $AppName
                issued_to = $Domain
                domain_name = $Domain
            }
        }
    } else {
        $CurrentJSON = [ordered]@{
            product_name = $AppName
            issued_to = $Domain
            domain_name = $Domain
        }
    }

    if ($WriteLicense) {
        $CurrentJSON | ConvertTo-Json -Depth 4 | Set-Content -Path $LicenseStatePath -Encoding UTF8
    }

    Write-Host "DEPLOY_OK"
} catch {
    Write-Host "DEPLOY_ERROR: $($_.Exception.Message)"
    exit 1
}
