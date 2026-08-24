param(
    [Parameter(Mandatory = $true)]
    [string]$GroupIdentity,
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
$logFolder = Join-Path $BaseLogPath "$activeDomainAdName\scripts_logs\Directory_Services\GroupMembership"
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
        [string]$TargetGroup,
        [string]$Status,
        [string]$Message,
        [string]$ExecutedByLog
    )

    $Message = $Message -replace '^(SUCCESS|ERROR|FAILED|WARN):\s*', ''
    try {
        $logEntry = "[$(Get-Date -Format 'yyyy-MM-dd hh:mm:ss tt')] Action: $Action | TargetGroup: $TargetGroup | Status: $Status | Message: $Message | ExecutedBy: $ExecutedByLog"
        Add-Content -Path $logFile -Value $logEntry -ErrorAction SilentlyContinue
    } catch {
    }
}

function Resolve-Group {
    param(
        [string]$Identity,
        $Credential
    )

    $trimmed = $Identity.Trim()
    if ([string]::IsNullOrWhiteSpace($trimmed)) {
        return $null
    }

    $resolved = Get-ADGroup -Identity $trimmed -Properties Description, SamAccountName, DistinguishedName -Credential $Credential -ErrorAction SilentlyContinue
    if ($resolved) {
        return $resolved
    }

    $escaped = $trimmed.Replace("'", "''")
    $matches = Get-ADGroup -Filter "Name -eq '$escaped' -or SamAccountName -eq '$escaped'" -Properties Description, SamAccountName, DistinguishedName -Credential $Credential -ErrorAction SilentlyContinue

    if (@($matches).Count -gt 1) {
        throw "Multiple groups matched '$trimmed'. Use the exact distinguished name or samAccountName."
    }

    return @($matches)[0]
}

function Find-SimilarGroups {
    param(
        [string]$Identity,
        $Credential
    )

    $trimmed = $Identity.Trim()
    if ([string]::IsNullOrWhiteSpace($trimmed)) {
        return @()
    }

    $escaped = $trimmed.Replace("'", "''")
    $likePattern = "*$escaped*"

    $matches = @(Get-ADGroup -Filter "Name -like '$likePattern' -or SamAccountName -like '$likePattern'" -Properties Description, SamAccountName, DistinguishedName -Credential $Credential -ErrorAction SilentlyContinue |
        Sort-Object Name |
        Select-Object -First 12)

    return $matches | ForEach-Object {
        [PSCustomObject]@{
            Name              = $_.Name
            SamAccountName    = $_.SamAccountName
            DistinguishedName = $_.DistinguishedName
            Description       = $_.Description
        }
    }
}

try {
    $group = Resolve-Group -Identity $GroupIdentity -Credential $Config.AdminCredential
    if (-not $group) {
        $suggestions = @(Find-SimilarGroups -Identity $GroupIdentity -Credential $Config.AdminCredential)
        $message = "Group '$GroupIdentity' was not found in Active Directory."
        if ($suggestions.Count -gt 0) {
            $message += " Similar groups were found. Please choose the correct one."
        }

        Write-Log -Action 'GROUP_MEMBERSHIP_READ' -TargetGroup $GroupIdentity -Status 'FAILED' -Message $message -ExecutedByLog $ExecutedBy

        Write-Output ((@{
            success = $false
            message = $message
            group = $null
            members = @()
            suggestions = $suggestions
        }) | ConvertTo-Json -Depth 6)
        exit 1
    }

    $members = @(Get-ADGroupMember -Identity $group.DistinguishedName -Credential $Config.AdminCredential -ErrorAction Stop)
    $results = @()

    foreach ($member in $members) {
        if ($member.objectClass -eq 'user') {
            $user = Get-ADUser -Identity $member.DistinguishedName -Properties DisplayName, SamAccountName, DistinguishedName -Credential $Config.AdminCredential -ErrorAction SilentlyContinue
            if ($user) {
                $results += [PSCustomObject]@{
                    Name              = if ([string]::IsNullOrWhiteSpace($user.DisplayName)) { $user.SamAccountName } else { $user.DisplayName }
                    DisplayName       = $user.DisplayName
                    SamAccountName    = $user.SamAccountName
                    DistinguishedName = $user.DistinguishedName
                    ObjectClass       = 'user'
                    Identifier        = $user.SamAccountName
                }
            }
            continue
        }

        if ($member.objectClass -eq 'group') {
            $childGroup = Get-ADGroup -Identity $member.DistinguishedName -Properties Name, SamAccountName, DistinguishedName -Credential $Config.AdminCredential -ErrorAction SilentlyContinue
            if ($childGroup) {
                $results += [PSCustomObject]@{
                    Name              = $childGroup.Name
                    DisplayName       = $childGroup.Name
                    SamAccountName    = $childGroup.SamAccountName
                    DistinguishedName = $childGroup.DistinguishedName
                    ObjectClass       = 'group'
                    Identifier        = if ([string]::IsNullOrWhiteSpace($childGroup.SamAccountName)) { $childGroup.Name } else { $childGroup.SamAccountName }
                }
            }
        }
    }

    $sortedResults = $results | Sort-Object ObjectClass, Name
    $output = @{
        success = $true
        message = "Loaded direct members for group '$($group.Name)'."
        group = @{
            Name              = $group.Name
            SamAccountName    = $group.SamAccountName
            DistinguishedName = $group.DistinguishedName
            Description       = $group.Description
        }
        members = @($sortedResults)
        suggestions = @()
    }

    Write-Log -Action 'GROUP_MEMBERSHIP_READ' -TargetGroup $group.Name -Status 'SUCCESS' -Message "Loaded direct members. Count: $(@($sortedResults).Count)." -ExecutedByLog $ExecutedBy
    Write-Output ($output | ConvertTo-Json -Depth 6)
    exit 0
} catch {
    $errorMessage = "ERROR: Failed to retrieve group members. $($_.Exception.Message)"
    Write-Log -Action 'GROUP_MEMBERSHIP_READ' -TargetGroup $GroupIdentity -Status 'FAILED' -Message $errorMessage -ExecutedByLog $ExecutedBy
    Write-Output (ConvertTo-Json @{ success = $false; message = $errorMessage })
    exit 1
}
