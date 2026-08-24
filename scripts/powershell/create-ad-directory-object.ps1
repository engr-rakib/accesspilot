param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('OU', 'Group')]
    [string]$ObjectType,
    [Parameter(Mandatory = $true)]
    [string]$ObjectName,
    [Parameter(Mandatory = $true)]
    [string]$ParentOU,
    [Parameter(Mandatory = $false)]
    [string]$Description = '',
    [Parameter(Mandatory = $false)]
    [string]$ExecutedBy = '(Web Application)',
    [Parameter(Mandatory = $true)]
    [string]$SecureConfigPath
)

$Config = $null
try {
    if (-not (Test-Path $SecureConfigPath)) {
        throw "Secure configuration file not found at path: '$SecureConfigPath'."
    }
    $Config = Import-Clixml -Path $SecureConfigPath
} catch {
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: Failed to load secure configuration. $($_.Exception.Message)" })
    exit 1
}

if ($null -eq $Config -or $null -eq $Config.AdminCredential -or [string]::IsNullOrEmpty($Config.BaseLogPath)) {
    Write-Output (ConvertTo-Json @{ success = $false; message = 'ERROR: Secure configuration is invalid or missing required settings (AdminCredential, BaseLogPath).' })
    exit 1
}

Import-Module ActiveDirectory

$BaseLogPath = $Config.BaseLogPath
$activeDomain = 'default'
$activeDomainAdName = $activeDomain
$domainCfgPath = if ($PSScriptRoot) { Join-Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) 'config\shared_config.json' }
if ($domainCfgPath -and (Test-Path $domainCfgPath)) { try { $dc = Get-Content $domainCfgPath -Raw | ConvertFrom-Json; if ($dc.active_domain) { $activeDomain = $dc.active_domain } if ($dc.active_domain_ad_name) { $activeDomainAdName = $dc.active_domain_ad_name } elseif ($dc.domain_name) { $activeDomainAdName = $dc.domain_name } } catch {} }
$logFolder = Join-Path $BaseLogPath "$activeDomainAdName\scripts_logs\Directory_Services\Ou_Group_Mgt"
$logFile = Join-Path $logFolder "audit-$(Get-Date -Format yyyy-MM-dd).log"

if (-not (Test-Path -Path $logFolder -PathType Container)) {
    try {
        New-Item -ItemType Directory -Path $logFolder -Force | Out-Null
    } catch {
    }
}

function Write-Log {
    param (
        [string]$Action,
        [string]$TargetUser,
        [string]$Status,
        [string]$Message,
        [string]$ExecutedByLog
    )

    $Message = $Message -replace '^(SUCCESS|ERROR|FAILED|WARN):\s*', ''
    try {
        $logEntry = "[$(Get-Date -Format 'yyyy-MM-dd hh:mm:ss tt')] Action: $Action | TargetUser: $TargetUser | Status: $Status | Message: $Message | ExecutedBy: $ExecutedByLog"
        Add-Content -Path $logFile -Value $logEntry -ErrorAction SilentlyContinue
    } catch {
    }
}

function Get-SafeName {
    param([string]$Name)

    $clean = $Name.Trim()
    $clean = $clean -replace "[\/\\\[\]:;|=,+*?<>@]", ""
    return $clean
}

try {
    $safeName = Get-SafeName -Name $ObjectName
    if ([string]::IsNullOrWhiteSpace($safeName)) {
        throw 'The provided object name is empty after removing invalid Active Directory characters.'
    }

    $parentObject = Get-ADObject -Identity $ParentOU -Credential $Config.AdminCredential -ErrorAction SilentlyContinue
    if (-not $parentObject) {
        throw "Parent OU '$ParentOU' was not found."
    }

    if ($ObjectType -eq 'OU') {
        $targetDn = "OU=$safeName,$ParentOU"
        $existingOu = Get-ADOrganizationalUnit -Filter "DistinguishedName -eq '$targetDn'" -Credential $Config.AdminCredential -ErrorAction SilentlyContinue
        if ($existingOu) {
            throw "Organizational Unit '$safeName' already exists under the selected parent."
        }

        New-ADOrganizationalUnit -Name $safeName -Path $ParentOU -ProtectedFromAccidentalDeletion $true -Description $Description -Credential $Config.AdminCredential -ErrorAction Stop | Out-Null
        $message = "Organizational Unit '$safeName' created successfully."
        Write-Log -Action 'C_OU' -TargetUser $safeName -Status 'SUCCESS' -Message $message -ExecutedByLog $ExecutedBy
        Write-Output (ConvertTo-Json @{ success = $true; message = $message })
        exit 0
    }

    $existingGroup = Get-ADGroup -Filter "Name -eq '$safeName' -or SamAccountName -eq '$safeName'" -SearchBase $ParentOU -Credential $Config.AdminCredential -ErrorAction SilentlyContinue
    if ($existingGroup) {
        throw "Security group '$safeName' already exists under the selected parent."
    }

    New-ADGroup -Name $safeName -SamAccountName $safeName -GroupCategory Security -GroupScope Global -Path $ParentOU -Description $Description -Credential $Config.AdminCredential -ErrorAction Stop | Out-Null
    $message = "Security group '$safeName' created successfully."
    Write-Log -Action 'C_GRP' -TargetUser $safeName -Status 'SUCCESS' -Message $message -ExecutedByLog $ExecutedBy
    Write-Output (ConvertTo-Json @{ success = $true; message = $message })
    exit 0
} catch {
    $errorMessage = "A detailed error occurred during directory object creation: $($_.Exception.Message)"
    Write-Log -Action "C_$ObjectType" -TargetUser $ObjectName -Status 'FAILED' -Message $errorMessage -ExecutedByLog $ExecutedBy
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: $errorMessage" })
    exit 1
}
