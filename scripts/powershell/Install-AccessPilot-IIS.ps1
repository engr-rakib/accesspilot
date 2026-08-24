[CmdletBinding()]
param(
    [string]$SiteName = 'AccessPilot',
    [string]$AppPoolName = 'AccessPilotPool',
    [string]$HostHeader = '',
    [int]$Port = 80,
    [string]$DeploymentRoot,
    [string]$PhpCgiPath,
    [string]$SecureBasePath = 'C:\inetpub\Desk_secure_files',
    [string]$LogBasePath = 'C:\access_pilot_logs',
    [string]$Domain,
    [string]$BaseDN,
    [string]$DefaultPassword,
    [string]$AdminUser,
    [string]$AdminPassword
)

$ErrorActionPreference = 'Stop'

function Assert-Administrator {
    $currentIdentity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($currentIdentity)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw 'Run this script from an elevated PowerShell session.'
    }
}

function Read-RequiredValue {
    param(
        [string]$Prompt,
        [string]$DefaultValue = ''
    )

    while ($true) {
        $fullPrompt = if ($DefaultValue) { "$Prompt [$DefaultValue]" } else { $Prompt }
        $value = Read-Host $fullPrompt
        if ([string]::IsNullOrWhiteSpace($value)) {
            $value = $DefaultValue
        }

        if (-not [string]::IsNullOrWhiteSpace($value)) {
            return $value.Trim()
        }

        Write-Host "Value cannot be empty." -ForegroundColor Red
    }
}

function Resolve-PhpCgiPath {
    param(
        [string]$ProvidedPath
    )

    $candidates = @(
        $ProvidedPath,
        'C:\php8.5.4_nts\php-cgi.exe',
        'C:\php\php-cgi.exe'
    )

    foreach ($candidate in $candidates) {
        if ($candidate -and (Test-Path $candidate)) {
            return (Resolve-Path $candidate).Path
        }
    }

    throw 'php-cgi.exe not found. Pass -PhpCgiPath explicitly.'
}

function Ensure-Directory {
    param([string]$Path)
    if (-not (Test-Path $Path)) {
        New-Item -Path $Path -ItemType Directory -Force | Out-Null
    }
}

function Grant-DirectoryAccess {
    param(
        [string]$Path,
        [string]$Identity = 'IIS_IUSRS',
        [string]$Rights = 'Modify'
    )

    $acl = Get-Acl $Path
    $rule = New-Object System.Security.AccessControl.FileSystemAccessRule(
        $Identity,
        $Rights,
        'ContainerInherit,ObjectInherit',
        'None',
        'Allow'
    )
    $acl.SetAccessRule($rule)
    Set-Acl -Path $Path -AclObject $acl
}

function Ensure-IisFeature {
    if (-not (Get-Command Install-WindowsFeature -ErrorAction SilentlyContinue)) {
        return
    }

    $features = @('Web-Server', 'Web-CGI')
    foreach ($feature in $features) {
        $state = Get-WindowsFeature -Name $feature -ErrorAction SilentlyContinue
        if ($state -and -not $state.Installed) {
            Install-WindowsFeature -Name $feature | Out-Null
        }
    }
}

function Ensure-FastCgiRegistration {
    param(
        [string]$PhpCgiExecutable
    )

    $appcmd = Join-Path $env:WINDIR 'System32\inetsrv\appcmd.exe'
    if (-not (Test-Path $appcmd)) {
        throw "IIS appcmd not found at $appcmd"
    }

    $fastCgiConfig = & $appcmd list config /section:system.webServer/fastCgi
    if ($fastCgiConfig -notmatch [regex]::Escape($PhpCgiExecutable)) {
        & $appcmd set config /section:system.webServer/fastCgi "/+[fullPath='$PhpCgiExecutable']" | Out-Null
    }
}

function Ensure-PhpHandler {
    param(
        [string]$Site,
        [string]$PhpCgiExecutable
    )

    $handlerName = 'AccessPilotPHP'
    $existing = Get-WebConfigurationProperty -PSPath "IIS:\Sites\$Site" -Filter 'system.webServer/handlers' -Name '.' |
        Where-Object { $_.name -eq $handlerName }

    if (-not $existing) {
        Add-WebConfigurationProperty -PSPath "IIS:\Sites\$Site" -Filter 'system.webServer/handlers' -Name '.' -Value @{
            name = $handlerName
            path = '*.php'
            verb = 'GET,HEAD,POST'
            modules = 'FastCgiModule'
            scriptProcessor = $PhpCgiExecutable
            resourceType = 'Either'
            requireAccess = 'Script'
        } | Out-Null
    }
}

function Ensure-AppPool {
    param([string]$Name)

    if (-not (Test-Path "IIS:\AppPools\$Name")) {
        New-WebAppPool -Name $Name | Out-Null
    }

    Set-ItemProperty "IIS:\AppPools\$Name" -Name managedRuntimeVersion -Value ''
    Set-ItemProperty "IIS:\AppPools\$Name" -Name managedPipelineMode -Value 'Integrated'
    Set-ItemProperty "IIS:\AppPools\$Name" -Name processModel.identityType -Value 4
}

function Ensure-Site {
    param(
        [string]$Name,
        [string]$PhysicalPath,
        [string]$PoolName,
        [int]$BindingPort,
        [string]$BindingHostHeader
    )

    $existingSite = Get-Website -Name $Name -ErrorAction SilentlyContinue
    if (-not $existingSite) {
        $conflict = Get-WebBinding -Protocol 'http' | Where-Object {
            $_.bindingInformation -eq "*:$BindingPort:$BindingHostHeader"
        }

        if ($conflict) {
            throw "HTTP binding *:$BindingPort:$BindingHostHeader is already in use."
        }

        New-Website -Name $Name -PhysicalPath $PhysicalPath -ApplicationPool $PoolName -Port $BindingPort -HostHeader $BindingHostHeader | Out-Null
    } else {
        Set-ItemProperty "IIS:\Sites\$Name" -Name physicalPath -Value $PhysicalPath
        Set-ItemProperty "IIS:\Sites\$Name" -Name applicationPool -Value $PoolName

        $binding = Get-WebBinding -Name $Name -Protocol 'http' -ErrorAction SilentlyContinue |
            Where-Object { $_.bindingInformation -eq "*:$BindingPort:$BindingHostHeader" }
        if (-not $binding) {
            New-WebBinding -Name $Name -Protocol 'http' -Port $BindingPort -HostHeader $BindingHostHeader | Out-Null
        }
    }
}

function Update-StorageConfig {
    param(
        [string]$RootPath,
        [string]$SecureRoot,
        [string]$LogRoot
    )

    $storageConfigPath = Join-Path $RootPath 'config\storage.php'
    $content = @"
<?php
/**
 * config/storage.php
 * 
 * Consolidated filesystem and data mapping configuration.
 * This file manages both internal app paths and external dynamic anchors.
 */

\$appRoot = dirname(__DIR__);

return [
    // 1. Internal Application Paths (Static)
    'paths' => [
        'app_root' => \$appRoot,
        'config_root' => __DIR__,
        'scripts_root' => \$appRoot . '/scripts',
        'powershell_root' => \$appRoot . '/scripts/powershell',
        'app_data_root' => \$appRoot . '/App_Data',
    ],

    // 2. External Data Anchors (Managed via System Configuration UI)
    'storage' => [
        'secure_base_path' => '$(($SecureRoot -replace '\\','/'))',
        'log_base_path' => '$(($LogRoot -replace '\\','/'))',
        'secure_xml_config' => '$(($SecureRoot -replace '\\','/'))/accesspilot_deployment_identity.xml',
    ],

    // 3. Emergency Recovery Administrator
    'fail_safe' => [
        'enabled' => true,
        'path' => \$appRoot . '/App_Data/internal_admin.json',
    ]
];
"@

    Set-Content -Path $storageConfigPath -Value $content -Encoding UTF8
}

function Ensure-InternalAdminProfile {
    param([string]$RootPath)

    $internalAdminPath = Join-Path $RootPath 'App_Data\internal_admin.json'
    $content = @'
{
    "admin": {
        "username": "admin",
        "password": "$2y$12$MPbJH.1uNxFcAiIUuheFJeItKiTSjY8t087IcF2n3uUfJufseEf0.",
        "role": "core_admin",
        "system_access": true,
        "is_internal": true
    }
}
'@
    Set-Content -Path $internalAdminPath -Value $content -Encoding UTF8
}

function Ensure-FreshSetupLockState {
    param(
        [string]$RootPath,
        [string]$SecureRoot
    )

    $setupLockPath = Join-Path $RootPath 'App_Data\setup_complete.lock'
    $usersPath = Join-Path $SecureRoot 'appusers\users.json'

    if ((Test-Path $setupLockPath) -and -not (Test-Path $usersPath)) {
        Remove-Item -LiteralPath $setupLockPath -Force
        Write-Host "Removed stale setup lock to allow first-run initialization." -ForegroundColor Yellow
    }
}

Assert-Administrator
Ensure-IisFeature
Import-Module WebAdministration -ErrorAction Stop

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$appRoot = if ($DeploymentRoot) { $DeploymentRoot } else { Split-Path -Parent (Split-Path -Parent $scriptRoot) }
$appRoot = (Resolve-Path $appRoot).Path
$publicRoot = Join-Path $appRoot 'public'

if (-not (Test-Path $publicRoot)) {
    throw "Public web root not found: $publicRoot"
}

$PhpCgiPath = Resolve-PhpCgiPath -ProvidedPath $PhpCgiPath
$Domain = if ($Domain) { $Domain } else { Read-RequiredValue -Prompt 'Enter Active Directory Domain (e.g., client.com)' }
$BaseDN = if ($BaseDN) { $BaseDN } else { Read-RequiredValue -Prompt 'Enter Active Directory BaseDN (e.g., DC=client,DC=com)' }
$DefaultPassword = if ($DefaultPassword) { $DefaultPassword } else { Read-RequiredValue -Prompt 'Enter default password for new users' }
$AdminUser = if ($AdminUser) { $AdminUser } else { Read-RequiredValue -Prompt 'Enter AD admin username' }
$AdminPassword = if ($AdminPassword) { $AdminPassword } else { Read-RequiredValue -Prompt 'Enter AD admin password' }

Ensure-Directory -Path $SecureBasePath
Ensure-Directory -Path $LogBasePath
Ensure-Directory -Path (Join-Path $SecureBasePath 'appusers')

Grant-DirectoryAccess -Path $SecureBasePath -Rights 'Modify'
Grant-DirectoryAccess -Path $LogBasePath -Rights 'Modify'

Update-StorageConfig -RootPath $appRoot -SecureRoot $SecureBasePath -LogRoot $LogBasePath
Ensure-InternalAdminProfile -RootPath $appRoot
Ensure-FreshSetupLockState -RootPath $appRoot -SecureRoot $SecureBasePath

$secureConfigPath = Join-Path $SecureBasePath 'accesspilot_deployment_identity.xml'
$licenseStatePath = Join-Path $SecureBasePath 'license_state.json'
$deployConfigScript = Join-Path $scriptRoot 'Web-Deploy-Config.ps1'

& $deployConfigScript `
    -Domain $Domain `
    -BaseDN $BaseDN `
    -AdminUser $AdminUser `
    -AdminPass $AdminPassword `
    -AppName 'AccessPilot' `
    -DefaultPassword $DefaultPassword `
    -BaseLogPath $LogBasePath `
    -SecureConfigPath $secureConfigPath `
    -LicenseStatePath $licenseStatePath

if ($LASTEXITCODE -ne 0) {
    throw 'Web-Deploy-Config.ps1 failed.'
}

Ensure-FastCgiRegistration -PhpCgiExecutable $PhpCgiPath
Ensure-AppPool -Name $AppPoolName
Ensure-Site -Name $SiteName -PhysicalPath $publicRoot -PoolName $AppPoolName -BindingPort $Port -BindingHostHeader $HostHeader
Ensure-PhpHandler -Site $SiteName -PhpCgiExecutable $PhpCgiPath

Start-Website -Name $SiteName | Out-Null

$bindingTarget = if ([string]::IsNullOrWhiteSpace($HostHeader)) { "localhost:$Port" } else { $HostHeader }

Write-Host "`nAccessPilot installation complete." -ForegroundColor Green
Write-Host "Site Name: $SiteName" -ForegroundColor White
Write-Host "App Pool: $AppPoolName" -ForegroundColor White
Write-Host "Public Root: $publicRoot" -ForegroundColor White
Write-Host "URL Hint: http://$bindingTarget/" -ForegroundColor White
Write-Host "Next Step: open the portal and apply the signed license JSON in the License page." -ForegroundColor Yellow
