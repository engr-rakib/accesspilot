param (
    [Parameter(Mandatory=$true)]
    [string[]]$Username,         # Comma, semicolon, or space separated
    [Parameter(Mandatory=$true)]
    [string]$ExecutedBy,          # Add ExecutedBy parameter for logging consistency
    [Parameter(Mandatory=$true)] # Make SecureConfigPath mandatory
    [string]$SecureConfigPath
)

# --- Clean ExecutedBy ---
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
if ([string]::IsNullOrEmpty($Config.Domain)) {
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: Domain not found in secure configuration." })
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
$logFolder = Join-Path $BaseLogPath "$activeDomainAdName\scripts_logs\User_Management\UserInfo"
$logFile   = Join-Path $logFolder "audit-$(Get-Date -Format yyyy-MM-dd).log"

if (!(Test-Path -Path $logFolder -PathType Container)) {
    try {
        New-Item -ItemType Directory -Path $logFolder -Force -ErrorAction Stop | Out-Null
    } catch {
        Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: Failed to create log directory at $logFolder. $_" })
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

# Action for logging
$logAction = "INFO"

# Normalize usernames as an array, splitting by common delimiters
$userList = @()
foreach ($u in $Username) {
    $userList += $u -split '[,; ]+'
}
$userList = $userList | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }

# Exit if no valid usernames provided after normalization
if (-not $userList) {
    Write-Output (ConvertTo-Json @{ success = $false; message = "Error: No valid usernames provided." })
    Write-Log -Action $logAction -TargetUser "N/A" -Status "FAILED" -Message "No valid usernames provided." -ExecutedByLog $ExecutedBy
    exit 1
}


# --- Counters for Summary Log ---
$successCount = 0
$failedCount  = 0
$notFoundCount = 0
$ambiguousCount = 0
$totalProcessed = 0
$userResults = @() # Initialize array to store results for each user

# --- Process each user ---
foreach ($userIdentifier in $userList) {
    $totalProcessed++
    try {
        $userIdentifier = $userIdentifier.Trim()

        $foundUser = $null
        $msg = ""

        # Perform a broad wildcard search first
        $allMatchingUsers = Get-ADUser -Filter "SamAccountName -like '*$userIdentifier*'" -Credential $Config.AdminCredential -Properties DisplayName, SamAccountName, DistinguishedName, Department, Company, Manager, LockedOut, title, logonWorkstation, logonCount, badPasswordTime, lockoutTime, badPwdCount, WhenCreated, createTimeStamp, lastLogon, LastLogonDate, lastLogoff, PasswordLastSet, Enabled, EmailAddress, mobile, MemberOf, msDS-UserPasswordExpiryTimeComputed, PasswordNeverExpires, AccountExpirationDate, HomeDirectory, HomeDrive, ProfilePath, ScriptPath, Description, userAccountControl -Server $Config.Domain -ErrorAction SilentlyContinue

        if ($allMatchingUsers) {
            # Prioritize exact match
            $exactUser = $allMatchingUsers | Where-Object { $_.SamAccountName -eq $userIdentifier }
            if ($exactUser) {
                $foundUser = $exactUser
            } else {
                # If no exact match, check for substring matches
                if ($allMatchingUsers.Count -gt 1) {
                    $msg = "Multiple users found containing '$userIdentifier' in their SamAccountName. Please specify a unique identifier. Found: $($allMatchingUsers.SamAccountName -join ', ')"
                    $userResults += @{ username = $userIdentifier; success = $false; message = $msg }
                    # Write-Log -Action $logAction -TargetUser $userIdentifier -Status "FAILED" -Message $msg -ExecutedByLog $ExecutedBy
                    $ambiguousCount++
                    $failedCount++
                    continue # Skip to next user
                } else {
                    # Single substring match
                    $foundUser = $allMatchingUsers | Select-Object -First 1
                }
            }
        }

        if (-not $foundUser) {
            $msg = "Warning!: Your provided User id not found in Domain server"
            $userResults += @{ username = $userIdentifier; success = $false; message = $msg }
            # Write-Log -Action $logAction -TargetUser $userIdentifier -Status "FAILED" -Message $msg -ExecutedByLog $ExecutedBy
            $notFoundCount++
            $failedCount++
            continue # Skip to next user
        }

        # --- Resolve Manager Name ---
        $managerName = "N/A"
        if ($foundUser.Manager) {
            try {
                $mgr = Get-ADUser -Identity $foundUser.Manager -Credential $Config.AdminCredential -Server $Config.Domain -ErrorAction SilentlyContinue
                if ($mgr) { $managerName = $mgr.DisplayName }
            } catch { $managerName = "Linked (Cannot Resolve)" }
        }

        # --- Parse UserAccountControl Flags (Troubleshooting Gold) ---
        $uac = $foundUser.userAccountControl
        $uacFlags = @()
        if ($uac -band 0x10) { $uacFlags += "LOCKOUT" }
        if ($uac -band 0x20) { $uacFlags += "PASSWD_NOTREQD" }
        if ($uac -band 0x40) { $uacFlags += "PASSWD_CANT_CHANGE" }
        if ($uac -band 0x10000) { $uacFlags += "DONT_EXPIRE_PASSWD" }
        if ($uac -band 0x40000) { $uacFlags += "SMARTCARD_REQUIRED" }
        if ($uac -band 0x80000) { $uacFlags += "TRUSTED_FOR_DELEGATION" }
        if ($uac -band 0x100000) { $uacFlags += "NOT_DELEGATED (Sensitive)" }
        $uacDisplay = if ($uacFlags) { $uacFlags -join ", " } else { "Normal Account" }

        # --- Password Expiration Logic ---
        $passwordExpiryDate = "N/A"
        $passwordStatus = "N/A"
        if ($foundUser.PasswordNeverExpires -eq $true) {
            $passwordStatus = "Never Expires"
        } else {
            $expiryTime = $foundUser.'msDS-UserPasswordExpiryTimeComputed'
            if ($expiryTime -and $expiryTime -ne 0 -and $expiryTime -ne 9223372036854775807) {
                $expiryDateTime = [DateTime]::FromFileTime($expiryTime)
                $passwordExpiryDate = $expiryDateTime.ToString('dd-MM-yyyy hh:mm tt')
                if ($expiryDateTime -lt (Get-Date)) {
                    $passwordStatus = "EXPIRED"
                } else {
                    $passwordStatus = "Valid"
                }
            } else {
                $passwordStatus = "N/A"
            }
        }

        # --- Multi-DC Attribute Sync (Professional Accuracy) ---
        # Some attributes (lastLogon, logonCount, badPwdCount) are NOT replicated.
        # We query available DCs to find the most recent/accurate data.
        $realLastLogon = 0
        $aggregateLogonCount = 0
        $maxBadPwdCount = 0
        
        try {
            $allDCs = Get-ADDomainController -Filter * -Credential $Config.AdminCredential -Server $Config.Domain -ErrorAction SilentlyContinue
            foreach ($dc in $allDCs) {
                $dcUser = Get-ADUser -Identity $foundUser.DistinguishedName -Server $dc.HostName -Properties lastLogon, logonCount, badPwdCount -Credential $Config.AdminCredential -ErrorAction SilentlyContinue
                if ($dcUser) {
                    if ($dcUser.lastLogon -gt $realLastLogon) { $realLastLogon = $dcUser.lastLogon }
                    if ($dcUser.badPwdCount -gt $maxBadPwdCount) { $maxBadPwdCount = $dcUser.badPwdCount }
                    $aggregateLogonCount += $dcUser.logonCount
                }
            }
        } catch {
            # Fallback to the initial foundUser if DC iteration fails
            $realLastLogon = $foundUser.lastLogon
            $aggregateLogonCount = $foundUser.logonCount
            $maxBadPwdCount = $foundUser.badPwdCount
        }

        # Safely get date/time properties
        $lockoutTime = if ($foundUser.lockoutTime -and $foundUser.lockoutTime -ne 0) { [DateTime]::FromFileTime($foundUser.lockoutTime) } else { $null }
        $badPwdTime = if ($foundUser.badPasswordTime -and $foundUser.badPasswordTime -ne 0) { [DateTime]::FromFileTime($foundUser.badPasswordTime) } else { $null }
        
        # Use the most recent logon found across all DCs
        $lastLogonTime = if ($realLastLogon -ne 0) { [DateTime]::FromFileTime($realLastLogon) } else { $null }

        $whenCreatedTime = if ($foundUser.WhenCreated) { $foundUser.WhenCreated } else { $null } 
        $passwordLastSetTime = if ($foundUser.PasswordLastSet) { $foundUser.PasswordLastSet } else { $null }

        # Time threshold for 12 hours
        $timeThreshold = (Get-Date).AddHours(-12)

        # OU Location
        $dn = $foundUser.DistinguishedName
        $ouComponents = ($dn -split ',') | Where-Object { $_ -like 'OU=*' }
        [array]::Reverse($ouComponents)
        $ouNames = $ouComponents | ForEach-Object { $_ -replace '^OU=', '' }
        $ouPath = $ouNames -join ' > '

        # Account Creator
        $creatorName = "N/A" # Default value
        try {
            # Use Get-ADObject which is more flexible for retrieving specific, non-standard attributes.
            $adObject = Get-ADObject -Identity $foundUser.DistinguishedName -Credential $Config.AdminCredential -Properties msDS-CreatorSID -Server $Config.Domain -ErrorAction Stop
            
            if ($adObject -and $adObject.IsMember('msDS-CreatorSID')) {
                $creatorSid = $adObject.'msDS-CreatorSID'
                if ($creatorSid) {
                    # Now resolve the SID to a user account
                    $creatorUser = Get-ADUser -Identity $creatorSid -Credential $Config.AdminCredential -Server $Config.Domain -ErrorAction SilentlyContinue
                    if ($creatorUser) {
                        $creatorName = $creatorUser.SamAccountName
                    } else {
                        # The SID might belong to a now-deleted user or a user in a different domain.
                        $creatorName = "SID exists but could not be resolved to a user."
                    }
                }
            }
        } catch {
            # This will catch errors from Get-ADObject if the property truly doesn't exist or there's a permission issue.
            $creatorName = "Could not retrieve creator SID. It may not be available in this AD environment."
        }

        # --- User Found: Format Output ---
        $thumbnailPhotoDataUri = $null
        if ($foundUser.thumbnailPhoto) {
            try {
                $thumbnailBase64 = [System.Convert]::ToBase64String($foundUser.thumbnailPhoto)
                if ($thumbnailBase64) {
                    $thumbnailPhotoDataUri = "data:image/jpeg;base64,$thumbnailBase64"
                }
            } catch {
                $thumbnailPhotoDataUri = $null
            }
        }

        $userInfo = [PSCustomObject]@{
            userPrincipalName = $foundUser.userPrincipalName
            thumbnailPhotoDataUri = $(if ($thumbnailPhotoDataUri) { $thumbnailPhotoDataUri } else { '' })
            
            # current user conditions
            accountStatus = $(if ($foundUser.Enabled) {'ENABLE'} else {'DISABLED'})
            accountLockStatus = $(if ($foundUser.LockedOut -eq $true) {'LOCKED'} else {'UNLOCKED'})
            passwordStatus = $passwordStatus
            passwordExpiryDate = $passwordExpiryDate
            accountExpirationDate = $(if ($foundUser.AccountExpirationDate) { $foundUser.AccountExpirationDate.ToString('dd-MM-yyyy hh:mm tt') } else { 'Never' })
            lockoutTime = $(if ($lockoutTime) {$lockoutTime.ToString('dd-MM-yyyy hh:mm tt')} else {'N/A'})
            lastPasswordReset = $(if ($passwordLastSetTime) {$passwordLastSetTime.ToString('dd-MM-yyyy hh:mm tt')} else {'Not Set'})
            securityFlags = $uacDisplay

            # Assigned Privileges
            assignedPrivileges = $(if ($foundUser.MemberOf) { $foundUser.MemberOf | ForEach-Object { $_ -replace '^CN=|,.*$', '' } } else {'No privileges are assigned.'})

            # User Activity
            totalWrongPassAttemptCount = $maxBadPwdCount
            wrongPassAttemptCountLast12h = $(if ($badPwdTime -and $badPwdTime -ge $timeThreshold) {$maxBadPwdCount} else {0})
            lastPasswordAttemptDateTime = $(if ($badPwdTime) {$badPwdTime.ToString('dd-MM-yyyy hh:mm tt')} else {'N/A'})
            totalLogonCount = $aggregateLogonCount
            lastLogin = $(if ($lastLogonTime) {$lastLogonTime.ToString('dd-MM-yyyy hh:mm tt')} else {'N/A'})
            lastLogoffTime = $(if ($foundUser.lastLogoff -and $foundUser.lastLogoff -ne 0) { ([DateTime]::FromFileTime($foundUser.lastLogoff)).ToString('dd-MM-yyyy hh:mm tt') } else {'N/A'})
            logonWorkstation = $(if ($foundUser.logonWorkstation) {$foundUser.logonWorkstation} else {'N/A'})

            # Infrastructure Information
            homeDirectory = $(if ($foundUser.HomeDirectory) { $foundUser.HomeDirectory } else { 'N/A' })
            homeDrive     = $(if ($foundUser.HomeDrive) { $foundUser.HomeDrive } else { 'N/A' })
            profilePath   = $(if ($foundUser.ProfilePath) { $foundUser.ProfilePath } else { 'N/A' })
            logonScript   = $(if ($foundUser.ScriptPath) { $foundUser.ScriptPath } else { 'N/A' })
            description   = $(if ($foundUser.Description) { $foundUser.Description } else { 'N/A' })

            # User Profiling Information
            accountCreatedOn = $(if ($whenCreatedTime) {$whenCreatedTime.ToString('dd-MM-yyyy hh:mm tt')} else {'N/A'})
            accountCreatedBy = $creatorName
            provisionOperatorName = "N/A" # Placeholder for future use

            # User information
            fullName = $foundUser.Name
            displayName = $foundUser.DisplayName
            jobTitle = $(if ($foundUser.title) {$foundUser.title} else {'N/A'})
            phoneNumber = $(if ($foundUser.mobile) {$foundUser.mobile} else {'N/A'})
            emailAddress = $(if ($foundUser.EmailAddress) {$foundUser.EmailAddress} else {'N/A'})
            manager = $managerName
            company = $(if ($foundUser.Company) { $foundUser.Company } else { 'N/A' })
            ouLocation = $(if ($ouPath) {$ouPath} else {'N/A'})
        }
        $userResults += @{ username = $foundUser.SamAccountName; success = $true; data = $userInfo; message = "Successfully retrieved info." }

    } catch {
        $exceptionMessage = $_.Exception.Message
        $finalErrorMessage = ""

        if ($exceptionMessage -like "*server has rejected the client credentials*" -or $exceptionMessage -like "*logon attempt failed*") {
            $finalErrorMessage = "Authentication to Active Directory failed. Please verify the credentials in the secure configuration file."
        } else {
            $finalErrorMessage = "An unhandled error occurred in Get-ADUser: $exceptionMessage"
        }
        
        $userResults += @{ username = $userIdentifier; success = $false; message = "ERROR: $finalErrorMessage" }
    }
}

# --- Summary ---
$summaryTargetUser = if ($userList.Count -eq 1) { [string]($userList | Select-Object -First 1) } else { "Multiple" }
$summaryStatus = if ($failedCount -gt 0 -or $notFoundCount -gt 0 -or $ambiguousCount -gt 0) { "FAILED" } else { "SUCCESS" }
$summaryMessageParts = @()
$summaryMessageParts += "Processed: $($totalProcessed)"
if ($successCount -gt 0) { $summaryMessageParts += "Success: $($successCount)" }
if ($failedCount -gt 0) { $summaryMessageParts += "Failed: $($failedCount)" }
if ($notFoundCount -gt 0) { $summaryMessageParts += "Not Found: $($notFoundCount)" }
if ($ambiguousCount -gt 0) { $summaryMessageParts += "Ambiguous: $($ambiguousCount)" }

$summaryMessage = "Summary: " + ($summaryMessageParts -join ', ')

Write-Log -Action $logAction -TargetUser $summaryTargetUser -Status $summaryStatus -Message $summaryMessage -ExecutedByLog $ExecutedBy

# --- Output JSON for API consumption ---
$overallSuccess = ($failedCount -eq 0 -and $notFoundCount -eq 0 -and $ambiguousCount -eq 0)
$overallMessage = $summaryMessage # Default to summary

if (-not $overallSuccess) {
    # If there's an overall failure
    if ($userList.Count -eq 1 -and $userResults.Count -eq 1 -and -not $userResults[0].success) {
        # If a single user was processed and failed, use its specific message
        $overallMessage = $userResults[0].message
    } else {
        # Otherwise, if multiple users or other complex failures, use the summary message or a general error
        # For now, keep it as $summaryMessage.
        # If we wanted a more generic "Multiple users not found/failed", we'd construct it here.
    }
}

$outputObject = [PSCustomObject]@{
    success = $overallSuccess
    message = $overallMessage
    processed = $totalProcessed
    successCount = $successCount
    failedCount = $failedCount
    notFoundCount = $notFoundCount
    ambiguousCount = $ambiguousCount
    userResults = $userResults # Include individual user results
}
$outputObject | ConvertTo-Json -Compress -Depth 100

exit 0
