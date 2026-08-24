param(
    [Parameter(Mandatory=$true)]
    [string]$OriginalSamAccountName,
    [Parameter(Mandatory=$true)]
    [string]$NewSamAccountName,
    [Parameter(Mandatory=$true)]
    [string]$DisplayName,
    [Parameter(Mandatory=$true)]
    [string]$OU,
    [Parameter(Mandatory=$false)]
    [string]$Description = '',
    [Parameter(Mandatory=$false)]
    [string]$GroupMembers = '', # Semicolon-separated DistinguishedNames
    [Parameter(Mandatory=$false)]
    [switch]$ResetPassword,
    [Parameter(Mandatory=$false)]
    [switch]$ForcePasswordChange,
    [string]$ExecutedBy = '(Web Application)', # New parameter for logged-in user
    [Parameter(Mandatory=$false)]
    [string]$Title = '',
    [Parameter(Mandatory=$false)]
    [string]$Department = '',
    [Parameter(Mandatory=$false)]
    [string]$Company = '',
    [Parameter(Mandatory=$false)]
    [string]$PhysicalDeliveryOfficeName = '',
    [Parameter(Mandatory=$false)]
    [string]$TelephoneNumber = '',
    [Parameter(Mandatory=$true)]
    [string]$SecureConfigPath
)

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
if ($null -eq $Config -or $null -eq $Config.AdminCredential -or [string]::IsNullOrEmpty($Config.BaseLogPath) -or [string]::IsNullOrEmpty($Config.DefaultPassword)) {
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: Secure configuration is invalid or missing required settings (AdminCredential, BaseLogPath, DefaultPassword)." })
    exit 1
}

# Logging setup
$BaseLogPath = $Config.BaseLogPath
if ([string]::IsNullOrEmpty($BaseLogPath)) {
    $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
    $BaseLogPath = Join-Path (Split-Path -Parent (Split-Path -Parent $scriptDir)) "logs"
}
$activeDomain = 'default'
$activeDomainAdName = $activeDomain
$domainCfgPath = if ($PSScriptRoot) { Join-Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) 'config\shared_config.json' }
if ($domainCfgPath -and (Test-Path $domainCfgPath)) { try { $dc = Get-Content $domainCfgPath -Raw | ConvertFrom-Json; if ($dc.active_domain) { $activeDomain = $dc.active_domain } if ($dc.active_domain_ad_name) { $activeDomainAdName = $dc.active_domain_ad_name } elseif ($dc.domain_name) { $activeDomainAdName = $dc.domain_name } } catch {} }
$logFolder = Join-Path $BaseLogPath "$activeDomainAdName\scripts_logs\User_Management\UserModify"
$logFile = Join-Path $logFolder "audit-$(Get-Date -Format yyyy-MM-dd).log"

# Create log folder if it doesn't exist
if (!(Test-Path -Path $logFolder -PathType Container)) {
    try {
        New-Item -ItemType Directory -Path $logFolder -Force | Out-Null
    } catch {
        # Non-critical, just can't log.
    }
}

Function Write-Log {
    param (
        [string]$Action,
        [string]$TargetUser,
        [string]$Status,
        [string]$Message,
        [string]$ExecutedByLog
    )
    $Message = $Message -replace '^(SUCCESS|ERROR|FAILED|WARN):\s*', ''
    # If logging fails, don't let it stop the script.
    try {
        $logEntry = "[$(Get-Date -Format 'yyyy-MM-dd hh:mm:ss tt')] Action: $Action | TargetUser: $TargetUser | Status: $Status | Message: $Message | ExecutedBy: $ExecutedByLog"
        Add-Content -Path $logFile -Value $logEntry -ErrorAction SilentlyContinue
    } catch {}
}

Function Convert-DNToFriendlyPath {
    param (
        [string]$DistinguishedName
    )
    $components = ($DistinguishedName -split ',') | Where-Object { $_ -like 'OU=*' -or $_ -like 'DC=*' }
    $names = $components | ForEach-Object { $_ -replace '^(OU|DC)=', '' }
    return $names -join '>'
}

Import-Module ActiveDirectory

$logAction = "MODIFY"
if ($ResetPassword) {
    $logAction = "MODIFY_RESET_PW"
}

try {
    # Find the user with an exact match on the original name.
    $user = Get-ADUser -Filter "SamAccountName -eq '$OriginalSamAccountName'" -Properties DisplayName, Description, DistinguishedName, MemberOf, UserPrincipalName, Title, Department, Company, physicalDeliveryOfficeName, telephoneNumber -Credential $Config.AdminCredential -ErrorAction SilentlyContinue

    if (-not $user) {
        throw "User '$OriginalSamAccountName' not found in Active Directory."
    }

    # This variable will hold the current, valid SamAccountName for the user throughout the script.
    $currentSamAccountName = $user.SamAccountName
    $changeMessages = @()

    # --- Handle Rename Operation ---
    if ($OriginalSamAccountName -ne $NewSamAccountName) {
        # Check if the new username already exists
        $existingUser = Get-ADUser -Filter "SamAccountName -eq '$NewSamAccountName'" -Credential $Config.AdminCredential -ErrorAction SilentlyContinue
        if ($existingUser) {
            throw "Cannot rename user. A user with SamAccountName '$NewSamAccountName' already exists."
        }
        
        # Also update the UserPrincipalName to match the new SamAccountName
        $originalUPN = $user.UserPrincipalName
        $upnSuffix = ($originalUPN -split '@', 2)[1]
        $newUPN = "$NewSamAccountName@$upnSuffix"

        # Perform the rename
        Set-ADUser -Identity $user -SamAccountName $NewSamAccountName -UserPrincipalName $newUPN -Credential $Config.AdminCredential -ErrorAction Stop
        
        $changeMessages += "Username (SamAccountName) changed from '$OriginalSamAccountName' to '$NewSamAccountName'."
        $changeMessages += "User Principal Name changed from '$originalUPN' to '$newUPN'."
        
        # Update the current SamAccountName for subsequent operations
        $currentSamAccountName = $NewSamAccountName
    }

    # Store original values for comparison
    $originalDisplayName = $user.DisplayName
    $originalDescription = $user.Description
    if ($null -eq $originalDescription) { $originalDescription = '' } # Normalize null to empty string for comparison

    # Store original org field values
    $originalTitle = if ($null -eq $user.Title) { '' } else { $user.Title }
    $originalDepartment = if ($null -eq $user.Department) { '' } else { $user.Department }
    $originalCompany = if ($null -eq $user.Company) { '' } else { $user.Company }
    $originalOffice = if ($null -eq $user.physicalDeliveryOfficeName) { '' } else { $user.physicalDeliveryOfficeName }
    $originalPhone = if ($null -eq $user.telephoneNumber) { '' } else { $user.telephoneNumber }

    # Prepare parameters for Set-ADUser
    $setUserParams = @{}
    if ($DisplayName -ne $originalDisplayName) {
        $setUserParams.Add('DisplayName', $DisplayName)
    }
    if ($Description -ne $originalDescription) {
        # If the description is being changed, decide whether to set it or clear it.
        if ([string]::IsNullOrEmpty($Description)) {
            $setUserParams.Add('Description', $null) # Pass $null to Set-ADUser to clear the attribute
        } else {
            $setUserParams.Add('Description', $Description) # Pass the new string value
        }
    }
    if ($Title -ne '' -and $Title -ne $originalTitle) {
        $setUserParams.Add('Title', $Title)
    }
    if ($Department -ne '' -and $Department -ne $originalDepartment) {
        $setUserParams.Add('Department', $Department)
    }
    if ($Company -ne '' -and $Company -ne $originalCompany) {
        $setUserParams.Add('Company', $Company)
    }
    if ($PhysicalDeliveryOfficeName -ne '' -and $PhysicalDeliveryOfficeName -ne $originalOffice) {
        $setUserParams.Add('physicalDeliveryOfficeName', $PhysicalDeliveryOfficeName)
    }
    if ($TelephoneNumber -ne '' -and $TelephoneNumber -ne $originalPhone) {
        $setUserParams.Add('telephoneNumber', $TelephoneNumber)
    }

    # Update fields if changes are detected
    if ($setUserParams.Count -gt 0) {
        Set-ADUser -Identity $currentSamAccountName @setUserParams -ErrorAction Stop -Credential $Config.AdminCredential
    }

    # Get the user's current DN after potential rename
    $updatedUser = Get-ADUser -Identity $currentSamAccountName -Properties DistinguishedName -Credential $Config.AdminCredential -ErrorAction Stop
    $currentUserParentDN = ($updatedUser.DistinguishedName -replace "^CN=[^,]+,")

    $ouChanged = $false
    if ($currentUserParentDN -ne $OU) {
        Move-ADObject -Identity $updatedUser.DistinguishedName -TargetPath $OU -ErrorAction Stop -Credential $Config.AdminCredential
        $ouChanged = $true
    }

    # Handle Group Memberships
    $currentGroupDNs = $user.MemberOf # Use original user object for initial group state
    $desiredGroupDNs = @()
    if (-not [string]::IsNullOrEmpty($GroupMembers)) {
        $desiredGroupDNs = $GroupMembers.Split(';', [System.StringSplitOptions]::RemoveEmptyEntries)
    }

    $groupsToAdd = $desiredGroupDNs | Where-Object { $_ -notin $currentGroupDNs }
    $groupsToRemove = $currentGroupDNs | Where-Object { $_ -notin $desiredGroupDns }

    foreach ($groupDn in $groupsToAdd) {
        Add-ADGroupMember -Identity $groupDn -Members $currentSamAccountName -ErrorAction Stop -Credential $Config.AdminCredential
    }

    foreach ($groupDn in $groupsToRemove) {
        Remove-ADGroupMember -Identity $groupDn -Members $currentSamAccountName -Confirm:$false -ErrorAction Stop -Credential $Config.AdminCredential
    }

    # Build detailed success message
    if ($DisplayName -ne $originalDisplayName) {
        $changeMessages += "Display Name changed from '$originalDisplayName' to '$DisplayName'."
    }
    if ($Description -ne $originalDescription) {
        $changeMessages += "Description changed from '$originalDescription' to '$Description'."
    }
    if ($ouChanged) {
        $friendlyOriginalOU = Convert-DNToFriendlyPath -DistinguishedName $originalOU
        $friendlyNewOU = Convert-DNToFriendlyPath -DistinguishedName $OU
        $changeMessages += "OU changed from '$friendlyOriginalOU' to '$friendlyNewOU'."
    }
    if ($groupsToAdd.Count -gt 0) {
        $groupNamesToAdd = ($groupsToAdd | ForEach-Object { ($_ -split ',')[0] -replace 'CN=', '' }) -join ', '
        $changeMessages += "Added to groups: $groupNamesToAdd."
    }
    if ($groupsToRemove.Count -gt 0) {
        $groupNamesToRemove = ($groupsToRemove | ForEach-Object { ($_ -split ',')[0] -replace 'CN=', '' }) -join ', '
        $changeMessages += "Removed from groups: $groupNamesToRemove."
    }

    if ($ResetPassword.IsPresent) {
        $newPassword = $Config.DefaultPassword
        $newPassword | Set-ADAccountPassword -Identity $currentSamAccountName -Credential $Config.AdminCredential
        if ($ForcePasswordChange.IsPresent) {
            Set-ADUser -Identity $currentSamAccountName -ChangePasswordAtLogon $true -Credential $Config.AdminCredential
        }
        # Clear PASSWD_CANT_CHANGE and DONT_EXPIRE_PASSWORD via proper cmdlet
        Set-ADUser -Identity $currentSamAccountName -CannotChangePassword $false -Credential $Config.AdminCredential
        Set-ADUser -Identity $currentSamAccountName -PasswordNeverExpires $false -Credential $Config.AdminCredential
        $changeMessages += "Password has been reset. New temporary password: $newPassword"
    }

    $finalMessage = ""
    if ($changeMessages.Count -gt 0) {
        $finalMessage = "User '$OriginalSamAccountName' updated successfully: " + ($changeMessages -join ' ')
    } else {
        $finalMessage = "User '$OriginalSamAccountName' checked successfully (no changes detected)."
    }

    $successMessage = "SUCCESS: " + $finalMessage

    Write-Log -Action $logAction -TargetUser $OriginalSamAccountName -Status "SUCCESS" -Message $finalMessage -ExecutedByLog $ExecutedBy
    Write-Output (ConvertTo-Json @{ success = $true; message = $successMessage })
    exit 0

} catch {
    $exceptionMessage = $_.Exception.Message
    $finalErrorMessage = ""

    if ($exceptionMessage -like "*The specified directory service attribute or value already exists*") {
        $finalErrorMessage = "The new username or another attribute conflicts with an existing user. Please choose a different value."
    } elseif ($exceptionMessage -like "*access is denied*") {
        $finalErrorMessage = "The action was blocked by Active Directory permissions. The user might be in a protected OU and cannot be modified."
    } elseif ($exceptionMessage -like "*object cannot be moved*") {
        $finalErrorMessage = "The user could not be moved to the new Organizational Unit. This may be due to permissions or object protection settings."
    } else {
        $finalErrorMessage = "A detailed error occurred during user modification: $exceptionMessage"
    }

    # Log the full technical error for administrative review
    $errorMessageForLog = "A detailed error occurred during user modification. $($_.Exception.Message)"
    Write-Log -Action $logAction -TargetUser $OriginalSamAccountName -Status "FAILED" -Message $errorMessageForLog -ExecutedByLog $ExecutedBy
    
    # Return the user-friendly error message to the UI
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: $finalErrorMessage" })
    exit 1
}
