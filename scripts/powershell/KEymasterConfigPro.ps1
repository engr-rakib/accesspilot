# masterConfigPro.ps1 - Secure Configuration and Credential Setup Tool

# This script should be run during initial deployment.
# It creates the deployment identity XML for domain, AD credential, and app runtime settings.
# License material is now handled separately via the AccessPilot License Center.

$SecureConfigDirPath = "C:\inetpub\Desk_secure_files"
$SecureConfigFilePath = Join-Path $SecureConfigDirPath "accesspilot_deployment_identity.xml"

if (-not (Test-Path $SecureConfigDirPath)) {
    New-Item -Path $SecureConfigDirPath -ItemType Directory -Force | Out-Null
    Write-Host "Created secure configuration directory: $SecureConfigDirPath" -ForegroundColor Green
}

# Grant IIS user group permissions
try {
    $Acl = Get-Acl $SecureConfigDirPath
    $Ar = New-Object System.Security.AccessControl.FileSystemAccessRule("IIS_IUSRS", "Modify", "ContainerInherit,ObjectInherit", "None", "Allow")
    $Acl.AddAccessRule($Ar)
    Set-Acl $SecureConfigDirPath $Acl
    Write-Host "Granted Modify permissions to IIS_IUSRS on '$SecureConfigDirPath'." -ForegroundColor Green
} catch {
    Write-Host "Warning: Failed to set IIS permissions on '$SecureConfigDirPath'." -ForegroundColor Yellow
}

Write-Host "--- AccessPilot Secure Setup ---" -ForegroundColor Cyan

function Read-RequiredText {
    param([string]$Prompt)
    while ($true) {
        $value = Read-Host $Prompt
        if (-not [string]::IsNullOrWhiteSpace($value)) { return $value.Trim() }
        Write-Host "Value cannot be empty." -ForegroundColor Red
    }
}

# 1. Core deployment data
$Domain = Read-RequiredText "Enter Active Directory Domain (e.g., AccessPilot.com)"
$BaseDN = Read-RequiredText "Enter Active Directory BaseDN (e.g., DC=AccessPilot,DC=com)"
$DefaultPassword = Read-RequiredText "Enter default password for new users"
$AppName = "AccessPilot"
$BaseLogPath = Read-RequiredText "Enter base path for application logs (e.g., C:\access_pilot_logs)"

if (-not (Test-Path $BaseLogPath)) {
    New-Item -Path $BaseLogPath -ItemType Directory -Force | Out-Null
    Write-Host "Created base log directory: $BaseLogPath" -ForegroundColor Green
}

# 2. AD credential
Write-Host "`nEnter administrative credentials for Active Directory operations." -ForegroundColor Yellow
$AdminUsername = Read-RequiredText "Enter Admin Username"
$AdminPassword = Read-Host "Enter Admin Password" -AsSecureString
$AdminCredential = New-Object System.Management.Automation.PSCredential ($AdminUsername, $AdminPassword)

# 4. Create deployment identity XML
$Config = [PSCustomObject]@{
    Domain = $Domain
    BaseDN = $BaseDN
    DefaultPassword = $DefaultPassword
    AppName = $AppName
    BaseLogPath = $BaseLogPath
    AdminCredential = $AdminCredential
}

try {
    $Config | Export-Clixml $SecureConfigFilePath -Force
    Write-Host "`nSUCCESS! Deployment identity XML saved to '$SecureConfigFilePath'." -ForegroundColor Green
} catch {
    Write-Host "ERROR: Failed to save deployment identity XML. $_" -ForegroundColor Red
    exit 1
}

Write-Host "`nNext Step: Log in to the AccessPilot web portal and apply your signed license in the License Center." -ForegroundColor Yellow
