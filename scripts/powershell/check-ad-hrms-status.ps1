param (
    [string[]]$Usernames,
    [string]$ExecutedBy,
    [string]$SecureConfigPath = ''
)

$ExecutedBy = $ExecutedBy.Trim()
if ([string]::IsNullOrEmpty($ExecutedBy)) { $ExecutedBy = "UNKNOWN" }

# --- Load secure config for log path ---
$Config = $null
if (-not [string]::IsNullOrEmpty($SecureConfigPath) -and (Test-Path $SecureConfigPath)) {
    try { $Config = Import-Clixml -Path $SecureConfigPath -ErrorAction Stop } catch {}
}
$BaseLogPath = if ($Config -and $Config.BaseLogPath) { $Config.BaseLogPath } else { $env:TEMP }

# --- Logging setup ---
$activeDomain = 'default'
$activeDomainAdName = $activeDomain
$domainCfgPath = if ($PSScriptRoot) { Join-Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) 'config\shared_config.json' }
if ($domainCfgPath -and (Test-Path $domainCfgPath)) { try { $dc = Get-Content $domainCfgPath -Raw | ConvertFrom-Json; if ($dc.active_domain) { $activeDomain = $dc.active_domain } if ($dc.active_domain_ad_name) { $activeDomainAdName = $dc.active_domain_ad_name } elseif ($dc.domain_name) { $activeDomainAdName = $dc.domain_name } } catch {} }
$logFolder = Join-Path $BaseLogPath "$activeDomainAdName\scripts_logs\Integration\EmpStsChk"
$logFile   = Join-Path $logFolder "audit-$(Get-Date -Format yyyy-MM-dd).log"
if (!(Test-Path -Path $logFolder -PathType Container)) {
    try { New-Item -ItemType Directory -Path $logFolder -Force -ErrorAction Stop | Out-Null } catch {}
}

Function Write-Log {
    param ([string]$Action, [string]$TargetUser, [string]$Status, [string]$Message, [string]$ExecutedByLog)
    if ([string]::IsNullOrEmpty($ExecutedByLog)) { $ExecutedByLog = "UNKNOWN" }
    $Message = $Message -replace '^(SUCCESS|ERROR|FAILED|WARN):\s*', ''
    $logEntry = "[$(Get-Date -Format 'yyyy-MM-dd hh:mm:ss tt')] Action: $Action | TargetUser: $TargetUser | Status: $Status | Message: $Message | ExecutedBy: $ExecutedByLog"
    try { Add-Content -Path $logFile -Value $logEntry -ErrorAction Stop } catch {}
}

# --- Normalize usernames ---
$allUsers = @()
foreach ($u in $Usernames) { $allUsers += $u -split ',' | ForEach-Object { $_.Trim() } }
$allUsers = $allUsers | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }
$isSingleUser = ($allUsers.Count -eq 1)

# --- Results Collection ---
$results = @()
$hrmsActiveCount = 0
$hrmsInactiveCount = 0
$hrmsApiNotFoundCount = 0
$adEnabledCount = 0
$adDisabledCount = 0
$adNotCreatedCount = 0
$adAmbiguousCount = 0
$totalProcessed = 0
$totalErrors = 0

# --- Initialize .NET PrincipalContext once (fast: explicit domain skips DC discovery) ---
$adContext = $null
try {
    Add-Type -AssemblyName System.DirectoryServices.AccountManagement -ErrorAction Stop
    $adContext = New-Object DirectoryServices.AccountManagement.PrincipalContext(
        [DirectoryServices.AccountManagement.ContextType]::Domain, 'wgbd.com'
    )
} catch {}

# --- Process each employee ID ---
foreach ($empID in $allUsers) {
    $totalProcessed++
    try {
        $apiURL = "https://whrmsapi.waltonbd.com/info/emp_info.php?emp_id=$empID"
        $currentResult = [PSCustomObject]@{ EMP_ID=$empID; EMP_NAME="N/A"; HRMS_STATUS="N/A"; AD_STATUS="N/A"; CheckedBy=$ExecutedBy }
        $empSamForAD = $empID
        $adStatus = "N/A"
        $hrmsStatus = "N/A"
        $empName = "N/A"

        # --- HRMS API Lookup ---
        try {
            $response = Invoke-RestMethod -Uri $apiURL -Method Get
            if ($response.EMP_ID) {
                $empName = $response.EMP_NAME
                $hrmsStatus = $response.EMP_STS
                $empSamForAD = $response.EMP_CODE
                if ($hrmsStatus -eq "ACTIVE") { $hrmsActiveCount++ } else { $hrmsInactiveCount++ }
            } else {
                $hrmsApiNotFoundCount++
                $hrmsStatus = "Not Found"
                if (-not $isSingleUser) { Write-Log -Action "STS_CHK" -TargetUser $empID -Status "FAILED" -Message "HRMS API did not return valid EMP_ID for '$empID'." -ExecutedByLog $ExecutedBy }
            }
        } catch {
            $hrmsStatus = "Error"; $totalErrors++
            if (-not $isSingleUser) { Write-Log -Action "STS_CHK" -TargetUser $empID -Status "ERROR" -Message "HRMS API call failed for '$empID': $($_.Exception.Message)" -ExecutedByLog $ExecutedBy }
        }

        # --- Active Directory Lookup ---
        try {
            if ($null -eq $adContext) { throw "PrincipalContext not initialized" }
            $adUserFound = [DirectoryServices.AccountManagement.UserPrincipal]::FindByIdentity($adContext, $empSamForAD)
            if ($adUserFound) {
                $adStatus = if ($adUserFound.Enabled) { "Enabled" } else { "Disabled" }
            } else {
                $adStatus = "Not Created"
            }
        } catch {
            $adStatus = "Not Checked"; $totalErrors++
            if (-not $isSingleUser) { Write-Log -Action "STS_CHK" -TargetUser $empID -Status "ERROR" -Message "AD search failed for '$empSamForAD': $($_.Exception.Message)" -ExecutedByLog $ExecutedBy }
        }

        if ($adStatus -eq "Enabled") { $adEnabledCount++ }
        elseif ($adStatus -eq "Disabled") { $adDisabledCount++ }
        elseif ($adStatus -eq "Not Created") { $adNotCreatedCount++ }

        $currentResult.EMP_NAME = $empName
        $currentResult.HRMS_STATUS = $hrmsStatus
        $currentResult.AD_STATUS = $adStatus
    } catch {
        $totalErrors++
        $currentResult = [PSCustomObject]@{ EMP_ID=$empID; EMP_NAME="Error"; HRMS_STATUS="Error"; AD_STATUS="Error"; CheckedBy=$ExecutedBy }
    }
    $results += $currentResult
}

# --- Output CSV to stdout ---
$results | Select-Object EMP_ID, EMP_NAME, HRMS_STATUS, AD_STATUS, CheckedBy | ConvertTo-Csv -NoTypeInformation

# --- Summary Log ---
$summaryStatus = if ($totalErrors -gt 0) { "FAILED" } else { "SUCCESS" }
$summaryMessageParts = @("Processed: $($totalProcessed)")
if ($hrmsActiveCount -gt 0) { $summaryMessageParts += "HRMS Active: $($hrmsActiveCount)" }
if ($hrmsInactiveCount -gt 0) { $summaryMessageParts += "HRMS Inactive: $($hrmsInactiveCount)" }
if ($hrmsApiNotFoundCount -gt 0) { $summaryMessageParts += "HRMS API Not Found: $($hrmsApiNotFoundCount)" }
if ($adEnabledCount -gt 0) { $summaryMessageParts += "AD Enabled: $($adEnabledCount)" }
if ($adDisabledCount -gt 0) { $summaryMessageParts += "AD Disabled: $($adDisabledCount)" }
if ($adNotCreatedCount -gt 0) { $summaryMessageParts += "AD Not Created: $($adNotCreatedCount)" }
if ($totalErrors -gt 0) { $summaryMessageParts += "Errors: $($totalErrors)" }
$summaryMessage = "Summary: " + ($summaryMessageParts -join ', ')
$summaryTargetUser = if ($allUsers.Count -eq 1) { [string]($allUsers | Select-Object -First 1) } else { "Multiple" }
Write-Log -Action "STS_CHK" -TargetUser $summaryTargetUser -Status $summaryStatus -Message $summaryMessage -ExecutedByLog $ExecutedBy

exit 0
