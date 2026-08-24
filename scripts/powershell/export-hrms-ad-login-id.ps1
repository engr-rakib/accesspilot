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
$logFolder = Join-Path $BaseLogPath "$activeDomainAdName\scripts_logs\Integration\FindLogonID"
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

# --- Normalize HRMS IDs ---
$hrmsIds = @()
foreach ($u in $Usernames) { $hrmsIds += $u -split ',' | ForEach-Object { $_.Trim() } }
$hrmsIds = $hrmsIds | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }
$isSingleUser = ($hrmsIds.Count -eq 1)

# --- Results Collection ---
$results = @()
$totalProcessed = 0
$exactMatchCount = 0
$substringMatchCount = 0
$totalNotFound = 0
$totalErrors = 0

# --- Initialize AD DirectorySearcher once for all lookups ---
$searcher = $null
try {
    Add-Type -AssemblyName System.DirectoryServices -ErrorAction Stop
    $searcher = New-Object DirectoryServices.DirectorySearcher
    $searcher.SearchRoot = New-Object DirectoryServices.DirectoryEntry("LDAP://example.com")
    $searcher.PropertiesToLoad.AddRange(@("samAccountName", "displayName"))
    $searcher.PageSize = 1000
} catch {}

# --- Process each HRMS ID ---
foreach ($hrmsId in $hrmsIds) {
    $totalProcessed++
    try {
        if ($null -eq $searcher) { throw "DirectorySearcher not initialized" }

        $searcher.Filter = "(&(objectClass=user)(samAccountName=*$hrmsId*))"
        $adResults = $searcher.FindAll()

        $adUserList = @()
        foreach ($adResult in $adResults) {
            $adUserList += [PSCustomObject]@{
                SamAccountName = $adResult.Properties["samaccountname"][0]
                DisplayName    = if ($adResult.Properties["displayname"]) { $adResult.Properties["displayname"][0] } else { "N/A" }
            }
        }

        $exactUser = $adUserList | Where-Object { $_.SamAccountName -eq $hrmsId }

        if ($exactUser) {
            $exactMatchCount++
            $results += [PSCustomObject]@{ HRMS_ID=$hrmsId; DisplayName=$exactUser.DisplayName; LogonID=$exactUser.SamAccountName; Status="SUCCESS"; Message="Exact match found for HRMS ID $hrmsId."; CheckedBy=$ExecutedBy }
        } else {
            $substringUsers = $adUserList | Where-Object { $_.SamAccountName -match [regex]::Escape($hrmsId) }
            if ($substringUsers) {
                if ($substringUsers.Count -gt 1) {
                    $totalErrors++
                    $results += [PSCustomObject]@{ HRMS_ID=$hrmsId; DisplayName="Multiple matches found"; LogonID="Multiple matches found"; Status="ERROR"; Message="Multiple users found containing HRMS ID $hrmsId. Please verify manually."; CheckedBy=$ExecutedBy }
                } else {
                    $substringMatchCount++
                    $results += [PSCustomObject]@{ HRMS_ID=$hrmsId; DisplayName=$substringUsers.DisplayName; LogonID=$substringUsers.SamAccountName; Status="SUCCESS"; Message="Substring match found for HRMS ID $hrmsId."; CheckedBy=$ExecutedBy }
                }
            } else {
                $totalNotFound++
                $results += [PSCustomObject]@{ HRMS_ID=$hrmsId; DisplayName="Not found"; LogonID="Not found"; Status="NOT_FOUND"; Message="No match found for HRMS ID $hrmsId."; CheckedBy=$ExecutedBy }
            }
        }
    } catch {
        $totalErrors++
        $results += [PSCustomObject]@{ HRMS_ID=$hrmsId; DisplayName="Error"; LogonID="Error"; Status="ERROR"; Message="Error processing HRMS ID $hrmsId : $($_.Exception.Message)"; CheckedBy=$ExecutedBy }
    }
}

# --- Output CSV to stdout ---
$results | Select-Object HRMS_ID, DisplayName, LogonID, Status, Message, CheckedBy | ConvertTo-Csv -NoTypeInformation

# --- Summary Log ---
$summaryStatus = if ($totalErrors -gt 0) { "FAILED" } else { "SUCCESS" }
$summaryMessageParts = @("Processed: $($totalProcessed)")
if ($exactMatchCount -gt 0) { $summaryMessageParts += "Exact match: $($exactMatchCount)" }
if ($substringMatchCount -gt 0) { $summaryMessageParts += "Substring match: $($substringMatchCount)" }
if ($totalNotFound -gt 0) { $summaryMessageParts += "No match: $($totalNotFound)" }
if ($totalErrors -gt 0) { $summaryMessageParts += "Errors: $($totalErrors)" }
$summaryMessage = "Summary: " + ($summaryMessageParts -join ', ')
$summaryTargetUser = if ($hrmsIds.Count -eq 1) { [string]($hrmsIds | Select-Object -First 1) } else { "Multiple" }
Write-Log -Action "LOGONID" -TargetUser $summaryTargetUser -Status $summaryStatus -Message $summaryMessage -ExecutedByLog $ExecutedBy

exit 0
