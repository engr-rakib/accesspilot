param(
    [Parameter(Mandatory=$true)]
    [string[]]$Username,
    [Parameter(Mandatory=$true)]
    [string]$ExecutedBy,
    [Parameter(Mandatory=$true)] # Make SecureConfigPath mandatory
    [string]$SecureConfigPath
)

# --- Clean input ---
$ExecutedBy = $ExecutedBy.Trim()
if ([string]::IsNullOrEmpty($ExecutedBy)) { $ExecutedBy = "UNKNOWN" }

# --- Import secure configuration ---
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

# --- Validate configuration ---
if ($null -eq $Config) {
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: Secure configuration is empty or invalid." })
    exit 1
}
if ($null -eq $Config.AdminCredential) {
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: Admin credentials not found in secure configuration." })
    exit 1
}
if ([string]::IsNullOrEmpty($Config.BaseLogPath)) {
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: BaseLogPath not found in secure configuration." })
    exit 1
}

# --- Logging setup ---
$BaseLogPath = $Config.BaseLogPath
if ([string]::IsNullOrEmpty($BaseLogPath)) {
    $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
    $BaseLogPath = Join-Path (Split-Path -Parent (Split-Path -Parent $scriptDir)) "logs"
}
$activeDomain = 'default'
$activeDomainAdName = $activeDomain
$domainCfgPath = if ($PSScriptRoot) { Join-Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) 'config\shared_config.json' }
if ($domainCfgPath -and (Test-Path $domainCfgPath)) { try { $dc = Get-Content $domainCfgPath -Raw | ConvertFrom-Json; if ($dc.active_domain) { $activeDomain = $dc.active_domain } if ($dc.active_domain_ad_name) { $activeDomainAdName = $dc.active_domain_ad_name } elseif ($dc.domain_name) { $activeDomainAdName = $dc.domain_name } } catch {} }
$logFolder = Join-Path $BaseLogPath "$activeDomainAdName\scripts_logs\User_Management\UserEnable"
$logFile = Join-Path $logFolder "audit-$(Get-Date -Format yyyy-MM-dd).log"

if (!(Test-Path -Path $logFolder -PathType Container)) {
    try {
        New-Item -ItemType Directory -Path $logFolder -Force -ErrorAction Stop | Out-Null
    } catch {
        Write-Output "ERROR: Failed to create log directory at $logFolder. $_"
        exit 1
    }
}

# --- Logging function ---
Function Write-Log {
    param (
        [string]$Action,
        [string]$TargetUser,
        [string]$Status,
        [string]$Message,
        [string]$ExecutedByLog
    )
    
    if ([string]::IsNullOrEmpty($ExecutedByLog)) { $ExecutedByLog = "UNKNOWN" }

    $Message = $Message -replace '^(SUCCESS|ERROR|FAILED|WARN):\s*', ''
    $logEntry = "[$(Get-Date -Format 'yyyy-MM-dd hh:mm:ss tt')] Action: $Action | TargetUser: $TargetUser | Status: $Status | Message: $Message | ExecutedBy: $ExecutedByLog"
    try {
        Add-Content -Path $logFile -Value $logEntry -ErrorAction Stop
    } catch {
        Write-Output "ERROR: Failed to write to log file: $_"
    }
}

# --- Import AD module ---
Import-Module ActiveDirectory

$logAction = "ENABLE"

# --- Normalize usernames ---
$allUsers = @()
foreach ($u in $Username) {
    $allUsers += $u -split '[,; ]+'
}
$allUsers = $allUsers | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }

# --- Counters ---
$successCount = 0
$skippedCount = 0
$failedCount  = 0
$userResults = @() # Initialize array to store results for each user

# --- Process each user ---
foreach ($userToEnable in $allUsers) {
    try {
        $userToEnable = $userToEnable.Trim()
        
        # Perform a broad wildcard search first
        $allMatchingUsers = Get-ADUser -Filter "SamAccountName -like '*$userToEnable*'" -Credential $Config.AdminCredential -ErrorAction SilentlyContinue

        $foundUser = $null
        $msg = ""

        if ($allMatchingUsers) {
            # Prioritize exact match
            $exactUser = $allMatchingUsers | Where-Object { $_.SamAccountName -eq $userToEnable }
            if ($exactUser) {
                $foundUser = $exactUser
            } else {
                # If no exact match, check for substring matches
                if ($allMatchingUsers.Count -gt 1) {
                    $msg = "Multiple users found containing '$userToEnable'. Specify unique identifier."
                    $userResults += @{ username = $userToEnable; success = $false; message = $msg }
                    Write-Log -Action $logAction -TargetUser $userToEnable -Status "FAILED" -Message $msg -ExecutedByLog $ExecutedBy
                    $failedCount++
                    continue
                } else {
                    # Single substring match
                    $foundUser = $allMatchingUsers | Select-Object -First 1
                }
            }
        }

        if (-not $foundUser) {
            $msg = "User '$userToEnable' not found in Active Directory."
            $userResults += @{ username = $userToEnable; success = $false; message = $msg }
            Write-Log -Action $logAction -TargetUser $userToEnable -Status "FAILED" -Message $msg -ExecutedByLog $ExecutedBy
            $failedCount++
            continue
        }

        # Already enabled?
        if ($foundUser.Enabled) {
            $msg = "User '$($foundUser.SamAccountName)' is already enabled."
            $userResults += @{ username = $foundUser.SamAccountName; success = $true; message = $msg; skipped = $true }
            Write-Log -Action $logAction -TargetUser $foundUser.SamAccountName -Status "SKIPPED" -Message $msg -ExecutedByLog $ExecutedBy
            $skippedCount++
            continue
        }

        # Enable account
        Enable-ADAccount -Identity $foundUser -Credential $Config.AdminCredential -ErrorAction Stop

        $msg = "User '$($foundUser.SamAccountName)' account has been enabled."
        $userResults += @{ username = $foundUser.SamAccountName; success = $true; message = $msg }
        Write-Log -Action $logAction -TargetUser $foundUser.SamAccountName -Status "SUCCESS" -Message $msg -ExecutedByLog $ExecutedBy
        $successCount++

    } catch {
        $exceptionMessage = $_.Exception.Message
        $finalErrorMessage = ""

        if ($exceptionMessage -like "*server has rejected the client credentials*" -or $exceptionMessage -like "*logon attempt failed*") {
            $finalErrorMessage = "Authentication to Active Directory failed. Please verify the credentials in the secure configuration file."
        } else {
            $finalErrorMessage = "An unhandled error occurred while enabling '$userToEnable': $exceptionMessage"
        }
        
        $userResults += @{ username = $userToEnable; success = $false; message = "ERROR: $finalErrorMessage" }
        Write-Log -Action $logAction -TargetUser $userToEnable -Status "FAILED" -Message "ERROR: $finalErrorMessage" -ExecutedByLog $ExecutedBy
        $failedCount++
    }
}

# --- Summary ---
$overallSuccess = ($failedCount -eq 0)
$overallMessage = "Processed: $($allUsers.Count) | Success: $successCount | Skipped: $skippedCount | Failed: $failedCount"

$outputObject = [PSCustomObject]@{
    success = $overallSuccess
    message = $overallMessage
    processed = $allUsers.Count
    successCount = $successCount
    skippedCount = $skippedCount
    failedCount = $failedCount
    userResults = $userResults # Include individual user results
}
$outputObject | ConvertTo-Json -Compress -Depth 100

exit 0
