param(
    [Parameter(Mandatory=$true)]
    [string]$SecureConfigPath,

    [Parameter(Mandatory=$true)]
    [string]$ObjectDN,

    [Parameter(Mandatory=$true)]
    [string]$ObjectType,

    [Parameter(Mandatory=$true)]
    [string]$ExecutedBy
)

$ErrorActionPreference = "Stop"

try {
    # 1. Load Secure Configuration
    if (-not (Test-Path $SecureConfigPath)) {
        throw "Secure configuration file not found at path: '$SecureConfigPath'."
    }
    $Config = Import-Clixml -Path $SecureConfigPath

    # 2. Setup Logging
    $BaseLogPath = $Config.BaseLogPath
$activeDomain = 'default'
$activeDomainAdName = $activeDomain
$domainCfgPath = if ($PSScriptRoot) { Join-Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) 'config\shared_config.json' }
if ($domainCfgPath -and (Test-Path $domainCfgPath)) { try { $dc = Get-Content $domainCfgPath -Raw | ConvertFrom-Json; if ($dc.active_domain) { $activeDomain = $dc.active_domain } if ($dc.active_domain_ad_name) { $activeDomainAdName = $dc.active_domain_ad_name } elseif ($dc.domain_name) { $activeDomainAdName = $dc.domain_name } } catch {} }
$logFolder = Join-Path $BaseLogPath "$activeDomainAdName\scripts_logs\Directory_Services\Ou_Group_Mgt"
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
        } catch {}
    }

    Import-Module ActiveDirectory

    # 3. Object Deletion Logic
    if ($ObjectType -eq 'OU') {
        # Check if OU has children
        $children = Get-ADObject -Filter * -SearchBase $ObjectDN -SearchScope OneLevel -Credential $Config.AdminCredential -ErrorAction SilentlyContinue
        if ($children) {
            $response = @{ success = $false; message = "ERROR: Cannot delete OU '$ObjectDN' because it is not empty. Please remove child objects first." }
            Write-Log -Action 'D_OU' -TargetUser $ObjectDN -Status 'FAILED' -Message $response.message -ExecutedByLog $ExecutedBy
            Write-Output ($response | ConvertTo-Json)
            return
        }
        Remove-ADOrganizationalUnit -Identity $ObjectDN -Confirm:$false -Credential $Config.AdminCredential
        $msg = "Successfully deleted Organizational Unit: $ObjectDN"
        Write-Log -Action 'D_OU' -TargetUser $ObjectDN -Status 'SUCCESS' -Message $msg -ExecutedByLog $ExecutedBy
    } elseif ($ObjectType -eq 'Group') {
        Remove-ADGroup -Identity $ObjectDN -Confirm:$false -Credential $Config.AdminCredential
        $msg = "Successfully deleted Security Group: $ObjectDN"
        Write-Log -Action 'D_GRP' -TargetUser $ObjectDN -Status 'SUCCESS' -Message $msg -ExecutedByLog $ExecutedBy
    } else {
        throw "Unsupported Object Type: $ObjectType"
    }

    $response = @{ success = $true; message = $msg }
    Write-Output ($response | ConvertTo-Json)

} catch {
    $err = $_.Exception.Message
    $response = @{ success = $false; message = "PowerShell Error: $err" }
    Write-Log -Action 'D_ERR' -TargetUser $ObjectDN -Status 'ERROR' -Message $err -ExecutedByLog $ExecutedBy
    Write-Output ($response | ConvertTo-Json)
}
