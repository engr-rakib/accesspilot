param(
    [Parameter(Mandatory = $true)]
    [string]$GroupIdentity,
    
    [Parameter(Mandatory = $false)]
    [string]$DesiredMembers = '',
    
    [Parameter(Mandatory = $false)]
    [string]$MembersToAdd = '',
    
    [Parameter(Mandatory = $false)]
    [string]$MembersToRemove = '',

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

function Resolve-Group {
    param([string]$Identity, $Credential)
    $resolved = Get-ADGroup -Identity $Identity -Properties DistinguishedName, Name, SamAccountName -Credential $Credential -ErrorAction SilentlyContinue
    if ($null -ne $resolved) { return $resolved }
    $escaped = $Identity.Replace("'", "''")
    $matches = @(Get-ADGroup -Filter "Name -eq '$escaped' -or SamAccountName -eq '$escaped'" -Credential $Credential -ErrorAction SilentlyContinue)
    if ($matches.Count -eq 1) { return $matches[0] }
    return $null
}

function Resolve-Principal {
    param([string]$Identity, $Credential)
    $trimmed = $Identity.Trim()
    if ([string]::IsNullOrWhiteSpace($trimmed)) { return $null }
    
    # Try direct DN/GUID/SID first
    $obj = Get-ADObject -Identity $trimmed -Properties DistinguishedName, Name -Credential $Credential -ErrorAction SilentlyContinue
    if ($null -ne $obj) {
        if ($obj.ObjectClass -eq 'user') {
            return Get-ADUser -Identity $obj.DistinguishedName -Properties DistinguishedName, Name, SamAccountName -Credential $Credential -ErrorAction SilentlyContinue
        }
        if ($obj.ObjectClass -eq 'group') {
            return Get-ADGroup -Identity $obj.DistinguishedName -Properties DistinguishedName, Name, SamAccountName -Credential $Credential -ErrorAction SilentlyContinue
        }
        return $obj
    }

    # Try User search
    $user = Get-ADUser -Filter "SamAccountName -eq '$trimmed' -or UserPrincipalName -eq '$trimmed'" -Properties DistinguishedName, Name, SamAccountName -Credential $Credential -ErrorAction SilentlyContinue
    if ($null -ne $user) { return $user }

    # Try Group search
    $group = Get-ADGroup -Filter "SamAccountName -eq '$trimmed' -or Name -eq '$trimmed'" -Properties DistinguishedName, Name, SamAccountName -Credential $Credential -ErrorAction SilentlyContinue
    if ($null -ne $group) { return $group }

    return $null
}

$BaseLogPath = $Config.BaseLogPath
$activeDomain = 'default'
$activeDomainAdName = $activeDomain
$domainCfgPath = if ($PSScriptRoot) { Join-Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) 'config\shared_config.json' }
if ($domainCfgPath -and (Test-Path $domainCfgPath)) { try { $dc = Get-Content $domainCfgPath -Raw | ConvertFrom-Json; if ($dc.active_domain) { $activeDomain = $dc.active_domain } if ($dc.active_domain_ad_name) { $activeDomainAdName = $dc.active_domain_ad_name } elseif ($dc.domain_name) { $activeDomainAdName = $dc.domain_name } } catch {} }
$logFolder = Join-Path $BaseLogPath "$activeDomainAdName\scripts_logs\Directory_Services\GroupMgmt"
if (-not (Test-Path $logFolder)) { New-Item -ItemType Directory -Path $logFolder -Force | Out-Null }
$logFile = Join-Path $logFolder "audit-$(Get-Date -Format yyyy-MM-dd).log"

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

try {
    $group = Resolve-Group -Identity $GroupIdentity -Credential $Config.AdminCredential
    if (-not $group) { throw "Group '$GroupIdentity' not found." }

    $toAdd = @()
    $toRemove = @()

    # DECISION LOGIC: Incremental (Add/Remove) vs Sync (Desired)
    if (-not [string]::IsNullOrWhiteSpace($MembersToAdd) -or -not [string]::IsNullOrWhiteSpace($MembersToRemove)) {
        if (-not [string]::IsNullOrWhiteSpace($MembersToAdd)) {
            $toAdd = $MembersToAdd.Split(';', [System.StringSplitOptions]::RemoveEmptyEntries) | ForEach-Object { Resolve-Principal -Identity $_.Trim() -Credential $Config.AdminCredential } | Where-Object { $_ -ne $null }
        }
        if (-not [string]::IsNullOrWhiteSpace($MembersToRemove)) {
            $toRemove = $MembersToRemove.Split(';', [System.StringSplitOptions]::RemoveEmptyEntries) | ForEach-Object { Resolve-Principal -Identity $_.Trim() -Credential $Config.AdminCredential } | Where-Object { $_ -ne $null }
        }
    } elseif (-not [string]::IsNullOrWhiteSpace($DesiredMembers)) {
        $currentMembers = @(Get-ADGroupMember -Identity $group.DistinguishedName -Credential $Config.AdminCredential)
        $desiredList = $DesiredMembers.Split(';', [System.StringSplitOptions]::RemoveEmptyEntries) | ForEach-Object { Resolve-Principal -Identity $_.Trim() -Credential $Config.AdminCredential } | Where-Object { $_ -ne $null }
        
        $currentDns = $currentMembers.DistinguishedName.ToLower()
        $desiredDns = $desiredList.DistinguishedName.ToLower()

        $toAdd = $desiredList | Where-Object { $desiredDns -contains $_.DistinguishedName.ToLower() -and $currentDns -notcontains $_.DistinguishedName.ToLower() }
        $toRemove = $currentMembers | Where-Object { $currentDns -contains $_.DistinguishedName.ToLower() -and $desiredDns -notcontains $_.DistinguishedName.ToLower() }
    }

    # Execute Actions
    $actualAdded = @()
    foreach ($p in $toAdd) {
        if ($p.DistinguishedName -ne $group.DistinguishedName) {
            Add-ADGroupMember -Identity $group.DistinguishedName -Members $p.DistinguishedName -Credential $Config.AdminCredential -ErrorAction Stop
            if ($p.SamAccountName) { $actualAdded += $p.SamAccountName } else { $actualAdded += $p.Name }
        }
    }

    $actualRemoved = @()
    foreach ($p in $toRemove) {
        Remove-ADGroupMember -Identity $group.DistinguishedName -Members $p.DistinguishedName -Confirm:$false -Credential $Config.AdminCredential -ErrorAction Stop
        if ($p.SamAccountName) { $actualRemoved += $p.SamAccountName } else { $actualRemoved += $p.Name }
    }

    $msg = "Group '$($group.Name)' updated successfully."
    if ($actualAdded.Count -gt 0) { $msg += " Added: $($actualAdded -join ', ')." }
    if ($actualRemoved.Count -gt 0) { $msg += " Removed: $($actualRemoved -join ', ')." }
    if ($actualAdded.Count -eq 0 -and $actualRemoved.Count -eq 0) { $msg = "No changes made to group '$($group.Name)'." }

    Write-Log -Action 'G_UPD' -TargetUser $group.Name -Status 'SUCCESS' -Message $msg -ExecutedByLog $ExecutedBy
    Write-Output (ConvertTo-Json @{ success = $true; message = $msg })

} catch {
    $errorMessage = $_.Exception.Message
    Write-Log -Action 'G_UPD_ERR' -TargetUser $GroupIdentity -Status 'ERROR' -Message $errorMessage -ExecutedByLog $ExecutedBy
    Write-Output (ConvertTo-Json @{ success = $false; message = "A detailed error occurred during group membership update: $errorMessage" })
}
