param (
    [string[]]$Usernames,
    [string]$ExecutedBy,
    [Parameter(Mandatory=$true)] # Make SecureConfigPath mandatory
    [string]$SecureConfigPath,
    [string]$SharedConfigPath = '',  # Optional: vault-independent config mirror
    [string]$OuConfigJson = '',      # Optional: JSON ou_config from domain API
    [string]$GroupConfigJson = ''    # Optional: JSON group_config from domain API
)

# --- Robustness: Handle comma-separated string if passed as a single argument ---
if ($Usernames.Count -eq 1 -and $Usernames[0].Contains(',')) {
    $Usernames = $Usernames[0].Split(',') | ForEach-Object { $_.Trim() }
}

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
if ([string]::IsNullOrEmpty($Config.BaseDN)) {
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: BaseDN not found in secure configuration." })
    exit 1
}
# Vault-independent fallback: read DefaultPassword from shared_config.json first
if (-not [string]::IsNullOrEmpty($SharedConfigPath) -and (Test-Path $SharedConfigPath)) {
    try {
        $SharedConfig = Get-Content -Raw -Path $SharedConfigPath | ConvertFrom-Json
        if (-not [string]::IsNullOrEmpty($SharedConfig.default_password)) {
            $Config.DefaultPassword = $SharedConfig.default_password
        }
    } catch {
        # Silent fallback to CLIXML value
    }
}

if ([string]::IsNullOrEmpty($Config.DefaultPassword)) {
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: DefaultPassword not found in secure configuration." })
    exit 1
}

# --- Parse optional OU/Group customization configs (backward compatible: null = hardcoded behavior) ---
$Script:OuConfig = $null
if (-not [string]::IsNullOrEmpty($OuConfigJson)) {
    try {
        $Script:OuConfig = $OuConfigJson | ConvertFrom-Json
    } catch {
        Write-Host "WARNING: Failed to parse OuConfigJson, falling back to defaults. Error: $($_.Exception.Message)"
    }
}
$Script:GroupConfig = $null
if (-not [string]::IsNullOrEmpty($GroupConfigJson)) {
    try {
        $Script:GroupConfig = $GroupConfigJson | ConvertFrom-Json
    } catch {
        Write-Host "WARNING: Failed to parse GroupConfigJson, falling back to defaults. Error: $($_.Exception.Message)"
    }
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
$logFolder = Join-Path $BaseLogPath "$activeDomainAdName\scripts_logs\User_Management\NewUser"
$timestamp = Get-Date -Format "yyyy-MM-dd hh:mm:ss tt"
$logFile = Join-Path $logFolder "audit-$(Get-Date -Format yyyy-MM-dd).log"

# Create log folder if it doesn't exist
if (!(Test-Path -Path $logFolder -PathType Container)) {
    try {
        New-Item -ItemType Directory -Path $logFolder -Force -ErrorAction Stop | Out-Null
        Write-Host "Created log directory at $logFolder"
    } catch {
        Write-Host "ERROR: Failed to create log directory at $logFolder. $_"
        exit
    }
}

# Transcript log setup (detailed log of all operations)
$transcriptLogFolder = Join-Path $logFolder "New_user_transcript_logs"
$transcriptLogFile = Join-Path $transcriptLogFolder "audit-$(Get-Date -Format yyyy-MM-dd).log"

# Create transcript log folder if it doesn't exist
if (!(Test-Path -Path $transcriptLogFolder -PathType Container)) {
    try {
        New-Item -ItemType Directory -Path $transcriptLogFolder -Force -ErrorAction Stop | Out-Null
        Write-Host "Created transcript log directory at $transcriptLogFolder"
    } catch {
        Write-Host "WARNING: Failed to create transcript log directory at $transcriptLogFolder. $_"
    }
}

# --- Counters and Results ---
$userResults = @() # Stores results for each user
$overallSuccess = $true # Tracks overall script success
$moveCount = 0
$resetCount = 0
$enableCount = 0

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
    
    # Add to userResults array
    $script:userResults += @{
        action = $Action;
        targetUser = $TargetUser;
        status = $Status;
        message = $Message;
        executedBy = $ExecutedByLog;
        timestamp = (Get-Date -Format 'yyyy-MM-dd hh:mm:ss tt')
    }
    try {
        Add-Content -Path $logFile -Value $logEntry -ErrorAction Stop
    } catch {
        # If file logging fails, just write to host (which will be captured by PHP)
        Write-Host "ERROR: Failed to write to log file at $logFile. $_"
        $script:overallSuccess = $false
    }
    # Also write to transcript log (best-effort via .NET for reliability)
    try {
        if (-not (Test-Path -Path $transcriptLogFolder -PathType Container)) {
            [System.IO.Directory]::CreateDirectory($transcriptLogFolder) | Out-Null
        }
        [System.IO.File]::AppendAllText($transcriptLogFile, $logEntry + [Environment]::NewLine)
    } catch {
        Write-Host "WARNING: Transcript log write failed: $_"
        try { Add-Content -Path $logFile -Value "WARNING: Transcript log write failed: $_" -ErrorAction Stop } catch {}
    }
}

# Write-Output "================================= Automated Response by 'sysAdmin14' ================================"

        
# Function to check if an OU exists, create it if necessary, and create a security group for it
Function Get-OrCreateOU {
    param (
        [string]$OUName,
        [string]$ParentOU,
        [PSObject]$GroupConfigParam = $null  # Optional: group customization config
    )

    $OUName = $OUName.Trim() # Trim whitespace from OU name

    # Remove invalid AD characters
    $SafeOUName = $OUName -replace "[\/\\\[\]:;|=,+*?<>@]", ""
    $SafeOUName = $SafeOUName -replace "&", "and"  # Standardize '&' to 'and'

    $OUPath = "OU=$SafeOUName,$ParentOU"

    # Determine group naming (backward compatible: if no config, use default)
    $grpPrefix = ''
    $grpSuffix = ''
    $shouldAutoCreate = $true  # Default: create groups
    if ($GroupConfigParam -and (-not [string]::IsNullOrEmpty($GroupConfigParam.customized)) -and $GroupConfigParam.customized -eq $true) {
        $grpPrefix = if ($GroupConfigParam.prefix) { $GroupConfigParam.prefix } else { '' }
        $grpSuffix = if ($GroupConfigParam.suffix) { $GroupConfigParam.suffix } else { '' }
        if ($GroupConfigParam.auto_create -eq $false) {
            $shouldAutoCreate = $false
        }
    }

    # Check if OU exists, if not, create it
    if (-not (Get-ADOrganizationalUnit -Filter "DistinguishedName -eq '$OUPath'" -ErrorAction SilentlyContinue)) {
        try {
            New-ADOrganizationalUnit -Name $SafeOUName -Path $ParentOU -ProtectedFromAccidentalDeletion $true | Out-Null
            Write-Log -Action "CREATE_OU" -TargetUser $SafeOUName -Status "SUCCESS" -Message "Created OU: $SafeOUName" -ExecutedByLog $ExecutedBy
        } catch {
            Write-Log -Action "CREATE_OU" -TargetUser $SafeOUName -Status "FAILED" -Message "Failed to create OU '$SafeOUName'. $($_.Exception.Message)" -ExecutedByLog $ExecutedBy
            $script:overallSuccess = $false
        }
    }

    # Create/check security group only if auto-create is enabled
    if ($shouldAutoCreate) {
        $GroupName = "$grpPrefix$SafeOUName$grpSuffix Group"

        # Check if the security group exists, create if necessary
        if (-not (Get-ADGroup -Filter {Name -eq $GroupName} -ErrorAction SilentlyContinue)) {
            try {
                New-ADGroup -Name $GroupName -SamAccountName $GroupName -GroupCategory Security -GroupScope Global -Path $OUPath | Out-Null
                Write-Log -Action "CREATE_GROUP" -TargetUser $GroupName -Status "SUCCESS" -Message "Created Group: $GroupName in $SafeOUName" -ExecutedByLog $ExecutedBy
            } catch {
                Write-Log -Action "CREATE_GROUP" -TargetUser $GroupName -Status "FAILED" -Message "Failed to create security group '$GroupName'. $($_.Exception.Message)" -ExecutedByLog $ExecutedBy
                $script:overallSuccess = $false
            }
        }

        # Add child OU group to its parent OU group >updated
        if ($ParentOU -ne $Global:BaseDN) {
            $ParentOUName = ($ParentOU -split ",")[0] -replace "OU=", ""
            $ParentGroupName = "$grpPrefix$ParentOUName$grpSuffix Group"
        
            $ParentGroup = Get-ADGroup -Filter {Name -eq $ParentGroupName} -ErrorAction SilentlyContinue
            $ChildGroup = Get-ADGroup -Filter {Name -eq $GroupName} -ErrorAction SilentlyContinue
        
            if ($ParentGroup -and $ChildGroup) {
                $IsAlreadyMember = Get-ADGroupMember -Identity $ParentGroup -ErrorAction SilentlyContinue | Where-Object {
                    $_.DistinguishedName -eq $ChildGroup.DistinguishedName
                }
        
                if (-not $IsAlreadyMember) {
                    try {
                        Add-ADGroupMember -Identity $ParentGroup -Members $ChildGroup -ErrorAction Stop
                        Write-Log -Action "ADD_GRP_MMBR" -TargetUser $GroupName -Status "SUCCESS" -Message "Added group '$GroupName' as a member of '$ParentGroupName'" -ExecutedByLog $ExecutedBy
                    } catch {
                        Write-Log -Action "ADD_GRP_MMBR" -TargetUser $GroupName -Status "FAILED" -Message "Failed to add '$GroupName' to '$ParentGroupName'. $($_.Exception.Message)" -ExecutedByLog $ExecutedBy
                        $script:overallSuccess = $false
                    }
                }
            }
        }
    }
    
    return $OUPath
}

# Function to add user to their OU security group
Function Add-UserToGroup {
    param (
        [string]$UserName,
        [string]$OUName,
        [PSObject]$GroupConfigParam = $null  # Optional: group customization config
    )

    # Determine group prefix/suffix from config (backward compatible)
    $grpPrefix = ''
    $grpSuffix = ''
    if ($GroupConfigParam -and (-not [string]::IsNullOrEmpty($GroupConfigParam.customized)) -and $GroupConfigParam.customized -eq $true) {
        $grpPrefix = if ($GroupConfigParam.prefix) { $GroupConfigParam.prefix } else { '' }
        $grpSuffix = if ($GroupConfigParam.suffix) { $GroupConfigParam.suffix } else { '' }
    }

    $GroupName = "$grpPrefix$OUName$grpSuffix Group" -replace "&", "and"
    $Group = Get-ADGroup -Filter {Name -eq $GroupName} -ErrorAction SilentlyContinue

    if ($Group) {
        $IsAlreadyMember = (Get-ADGroupMember -Identity $Group -Recursive | Where-Object { $_.SamAccountName -eq $UserName })

        if (-not $IsAlreadyMember) {
            try {
                Add-ADGroupMember -Identity $Group -Members $UserName
                Write-Log -Action "ADDED GRP" -TargetUser $UserName -Status "SUCCESS" -Message "Added user $UserName to group $GroupName" -ExecutedByLog $ExecutedBy
            } catch {
                Write-Log -Action "ADDED GRP" -TargetUser $UserName -Status "FAILED" -Message "Failed to add user $UserName to $GroupName. $($_.Exception.Message)" -ExecutedByLog $ExecutedBy
                $script:overallSuccess = $false
            }
        }
    } else {
        Write-Log -Action "ADDED GRP" -TargetUser $UserName -Status "WARNING" -Message "Group $GroupName not found for user $UserName" -ExecutedByLog $ExecutedBy
    }
}

# Function to add user to conditional groups based on HRMS field values
Function Add-UserToConditionalGroups {
    param (
        [string]$UserName,
        [PSObject]$EmpData,
        [PSObject]$GroupConfigParam = $null,
        [string]$ExecutedByLog
    )

    if (-not $GroupConfigParam -or (-not [string]::IsNullOrEmpty($GroupConfigParam.customized)) -and $GroupConfigParam.customized -ne $true) { return }
    if (-not $GroupConfigParam.rules -or @($GroupConfigParam.rules).Count -eq 0) { return }

    foreach ($rule in $GroupConfigParam.rules) {
        $field = if ($rule.field) { $rule.field } else { '' }
        $value = if ($rule.value) { $rule.value } else { '' }
        $targetGroup = if ($rule.group) { $rule.group } else { '' }

        if ([string]::IsNullOrEmpty($field) -or [string]::IsNullOrEmpty($value) -or [string]::IsNullOrEmpty($targetGroup)) { continue }

        $fieldValue = $EmpData.$field
        if ([string]::IsNullOrEmpty($fieldValue)) { continue }

        if ($fieldValue -eq $value) {
            $group = Get-ADGroup -Filter {Name -eq $targetGroup} -ErrorAction SilentlyContinue
            if ($group) {
                $isMember = Get-ADGroupMember -Identity $group -Recursive | Where-Object { $_.SamAccountName -eq $UserName }
                if (-not $isMember) {
                    try {
                        Add-ADGroupMember -Identity $group -Members $UserName -ErrorAction Stop
                        Write-Log -Action "ADDED_COND_GRP" -TargetUser $UserName -Status "SUCCESS" -Message "Added user to conditional group '$targetGroup' (rule: $field=$value)" -ExecutedByLog $ExecutedByLog
                    } catch {
                        Write-Log -Action "ADDED_COND_GRP" -TargetUser $UserName -Status "FAILED" -Message "Failed to add user to conditional group '$targetGroup'. $($_.Exception.Message)" -ExecutedByLog $ExecutedByLog
                        $script:overallSuccess = $false
                    }
                }
            } else {
                Write-Log -Action "ADDED_COND_GRP" -TargetUser $UserName -Status "WARNING" -Message "Conditional group '$targetGroup' not found (rule: $field=$value)" -ExecutedByLog $ExecutedByLog
            }
        }
    }
}

# Function to create a new AD user
Function Create-ADUser {
    param ([string]$EmpID)

    # Fetch employee details from API
    $APIUrl = "https://hrms.example.com/info/emp_info.php?emp_id=$EmpID"
    try {
        $EmpData = Invoke-RestMethod -Uri $APIUrl -Method Get
    } catch {
        $errorMessage = "Failed to retrieve employee data for EmpID $EmpID. $($_.Exception.Message)"
        Write-Log -Action "CREATE" -TargetUser $EmpID -Status "FAILED" -Message $errorMessage -ExecutedByLog $ExecutedBy
        $script:overallSuccess = $false
        return [PSCustomObject]@{ username = $EmpID; status = "FAILED"; message = $errorMessage }
    }

    # Checking employee active status on HRMS database
    if (-not $EmpData -or $EmpData.EMP_STS -ne "ACTIVE") {
        $errorMessage = "Employee $EmpID is inactive in HRMS."
        Write-Log -Action "HRMS_INC" -TargetUser $EmpID -Status "TRIGGERED" -Message $errorMessage -ExecutedByLog "$ExecutedBy,sysAdmin4"
        $script:overallSuccess = $false

        # Path to disable script
        $DisableScriptPath = "$PSScriptRoot\disable-user.ps1"

        if (Test-Path $DisableScriptPath) {
            try {
                $disableResult = powershell.exe -ExecutionPolicy Bypass -File $DisableScriptPath -Username $EmpID -ExecutedBy $ExecutedBy -SecureConfigPath $script:SecureConfigPath -NoFileLog | ConvertFrom-Json
                if ($disableResult.success) {
                    Write-Log -Action "DSBL_ATO" -TargetUser $EmpID -Status "SUCCESS" -Message "Successfully ran disable-user.ps1 for $EmpID. Message: $($disableResult.message)" -ExecutedByLog "sysAdmin14"
                } else {
                    Write-Log -Action "DSBL_ATO" -TargetUser $EmpID -Status "FAILED" -Message "Failed to run disable-user.ps1 for $EmpID. Message: $($disableResult.message)" -ExecutedByLog "sysAdmin14"
                    $script:overallSuccess = $false
                }
            } catch {
                $errorMessage = "Failed to run disable-user.ps1 for $EmpID. $($_.Exception.Message)"
                Write-Log -Action "DSBL_ATO" -TargetUser $EmpID -Status "FAILED" -Message $errorMessage -ExecutedByLog "sysAdmin14"
                # Ensure the error message is captured in the return object
                return [PSCustomObject]@{ username = $EmpID; status = "FAILED"; message = $errorMessage }
            }
        } else {
            $errorMessage = "disable-user.ps1 not found at path: $DisableScriptPath"
            Write-Log -Action "DSBL_ATO" -TargetUser $EmpID -Status "FAILED" -Message $errorMessage -ExecutedByLog "sysAdmin14"
            # Ensure the error message is captured in the return object
            return [PSCustomObject]@{ username = $EmpID; status = "FAILED"; message = $errorMessage }
        }
        
        # Explicitly return the result from disable-user.ps1 processing
        $status = if ($disableResult.success) { "SKIPPED" } else { "FAILED" }
        $detailedMessageFromDisableScript = ""
        if ($disableResult.userResults -and $disableResult.userResults.Count -gt 0) {
            $detailedMessageFromDisableScript = $disableResult.userResults[0].message
        } else {
            $detailedMessageFromDisableScript = $disableResult.message # Fallback to summary message if no userResults
        }
        
        $finalUserActionMessage = "CAUTION!!: Employee '$EmpID' is inactive in HRMS. `n CURRENT ACTION: $detailedMessageFromDisableScript"
        return [PSCustomObject]@{ username = $EmpID; status = $status; message = $finalUserActionMessage }
    }

    # Extract required fields
    $UserName = $EmpData.EMP_CODE
    $FullName = $EmpData.EMP_NAME
    $Email = $EmpData.EMAIL
    $Mobile = $EmpData.MOBILE
    $Designation = $EmpData.DESIGNATION
    $Department = $EmpData.DEPARTMENT_TITLE
    $Office = $EmpData.LOCATION_TITLE
    $Rank = $EmpData.RANK
    $OperatingUnit = $EmpData.OPERATING_UNIT_TITLE

    # Split Full Name into First Name and Last Name
    $NameParts = $FullName -split "\s+", 2
    $FirstName = $NameParts[0]
    $LastName = if ($NameParts.Length -gt 1) { $NameParts[1] } else { "" }

    # Define OU hierarchy (from config if customized, otherwise hardcoded defaults)
    $OUHierarchy = @()
    if ($Script:OuConfig -and (-not [string]::IsNullOrEmpty($Script:OuConfig.customized)) -and $Script:OuConfig.customized -eq $true) {
        # Build from config: iterate levels 1..5, skip if field is 'Skip' or empty
        for ($i = 1; $i -le 5; $i++) {
            $level = $Script:OuConfig.levels."$i"
            if (-not $level) { continue }
            $field = if ($level.field) { $level.field } else { '' }
            if ($field -eq 'Skip' -or [string]::IsNullOrEmpty($field)) { continue }
            $rawValue = $EmpData.$field
            if ($rawValue -and $rawValue -ne "N/A") {
                $prefix = if ($Script:OuConfig.prefix) { $Script:OuConfig.prefix } else { '' }
                $suffix = if ($Script:OuConfig.suffix) { $Script:OuConfig.suffix } else { '' }
                $ouName = $prefix + $rawValue + $suffix
                $OUHierarchy += $ouName
            }
        }
    } else {
        # Legacy hardcoded behavior (backward compatible)
        $OUHierarchy = @(
            $OperatingUnit, 
            $Department, 
            $EmpData.SECTION_TITLE, 
            $EmpData.PRODUCT_TITLE, 
            $EmpData.SUB_SECTION_TITLE
        )
    }

    $BaseDN = $Config.BaseDN

    # If a root OU is configured, use it as the base container
    $rootOUPath = $BaseDN
    if ($Script:OuConfig -and $Script:OuConfig.root_ou) {
        $rootOu = $Script:OuConfig.root_ou.Trim()
        if ($rootOu -ne '') {
            $rootOUPath = "$rootOu,$BaseDN"
        }
    }

    # Build OU structure dynamically
    $OUPath = $rootOUPath
    $UserOUs = @()
    foreach ($OU in $OUHierarchy) {
        if ($OU -and $OU -ne "N/A") {
            $OUPath = Get-OrCreateOU -OUName $OU -ParentOU $OUPath -GroupConfigParam $Script:GroupConfig
            $UserOUs += $OU
        }
    }

    $ExistingUser = Get-ADUser -Filter {SamAccountName -eq $UserName} -Properties DistinguishedName, Enabled -ErrorAction SilentlyContinue
    if ($ExistingUser) {
        $overallUserActionStatus = "SKIPPED" # Default status if nothing else changes it
        $overallUserActionMessage = "" # Will be built dynamically

        $actionsTaken = @() # To collect messages for actions performed
        $isMoved = $false # Flag to track if user was moved

        # User exists, verify OU location and move user on right placement OU
        $CurrentOU = ($ExistingUser.DistinguishedName -split ",", 2)[1]
        if ($CurrentOU -ne $OUPath) {
            try {
                Move-ADObject -Identity $ExistingUser.DistinguishedName -TargetPath $OUPath
                $moveMessage = "Moved to correct OU Path: " + ($OUPath -replace "OU=", "" -replace ",", " > ")
                Write-Log -Action "MV_ATO" -TargetUser $UserName -Status "SUCCESS" -Message $moveMessage -ExecutedByLog $ExecutedBy
                $actionsTaken += "ACTION: " + $moveMessage
                $overallUserActionStatus = "SUCCESS" # Consider move a success for overall status
                $script:moveCount++
                $isMoved = $true
            } catch {
                $errorMessage = "Failed to move existing user '$UserName' to new OU. $($_.Exception.Message)"
                Write-Log -Action "MV_ATO" -TargetUser $UserName -Status "FAILED" -Message $errorMessage -ExecutedByLog $ExecutedBy
                $script:overallSuccess = $false
            }
        } else {
            # No explicit action, but record the status for message construction
            # $actionsTaken += "User already in correct OU: " + ($OUPath -replace "OU=", "" -replace ",", " > ")
        }
    
        # Check if user is disabled and enable if needed
        if (-not $ExistingUser.Enabled) {
            try {
                Enable-ADAccount -Identity $UserName
                $enableMessage = "Enabled previously disabled account."
                Write-Log -Action "ENBL_ATO" -TargetUser $UserName -Status "SUCCESS" -Message $enableMessage -ExecutedByLog $ExecutedBy
                $actionsTaken += $enableMessage
                $overallUserActionStatus = "SUCCESS" # Update status if enabled
                $script:enableCount++
            } catch {
                $errorMessage = "Failed to enable user account '$UserName'. $($_.Exception.Message)"
                Write-Log -Action "ENBL_ATO" -TargetUser $UserName -Status "FAILED" -Message $errorMessage -ExecutedByLog $ExecutedBy
                $script:overallSuccess = false
            }
        }
    
        # Ensure user is added to group (this is a sub-task, doesn't change overall status)
        $AssignedOU = $UserOUs[-1]
        Add-UserToGroup -UserName $UserName -OUName $AssignedOU -GroupConfigParam $Script:GroupConfig
        Add-UserToConditionalGroups -UserName $UserName -EmpData $EmpData -GroupConfigParam $Script:GroupConfig -ExecutedByLog $ExecutedBy
    
        # Always trigger password reset
        $ResetScriptPath = "$PSScriptRoot\reset-unlock-user.ps1"
        if (Test-Path $ResetScriptPath) {
            try {
                $resetResult = powershell.exe -ExecutionPolicy Bypass -File $ResetScriptPath -Username $UserName -ExecutedBy $ExecutedBy -SecureConfigPath $script:SecureConfigPath -NoFileLog | ConvertFrom-Json
                if ($resetResult.success) {
                    $tempPassword = $Config.DefaultPassword # Assuming default password is the temporary one
                    $resetActionMessage = "Quick Action:> Password reset triggered. User '$UserName' has been reset and Unlocked With the Temporary Password '$tempPassword'"
                    Write-Log -Action "RSET_PASSWD" -TargetUser $UserName -Status "SUCCESS" -Message $resetActionMessage -ExecutedByLog $ExecutedBy
                    $actionsTaken += "SUCCESS: " + $resetActionMessage
                    $overallUserActionStatus = "SUCCESS" # Update status if reset successful
                    $script:resetCount++
                } else {
                    $resetFailureMessage = "Failed to trigger password reset. Message: $($resetResult.message)"
                    Write-Log -Action "RSET_PASSWD" -TargetUser $UserName -Status "FAILED" -Message $resetFailureMessage -ExecutedByLog $ExecutedBy
                    $script:overallSuccess = false
                }
            } catch {
                $errorMessage = "Failed to trigger password reset for '$UserName'. $($_.Exception.Message)"
                Write-Log -Action "RSET_PASSWD" -TargetUser $UserName -Status "FAILED" -Message $errorMessage -ExecutedByLog $ExecutedBy
                $script:overallSuccess = false
            }
        } else {
            Write-Log -Action "RESET_PASSWD" -TargetUser $UserName -Status "FAILED" -Message "Password reset script not found." -ExecutedByLog $ExecutedBy
            $script:overallSuccess = false
        }

        # Construct the final message based on actions taken
        if ($isMoved) {
            $overallUserActionMessage = "Warning!!: User '$UserName' already exists With older object location."
        } else {
            $overallUserActionMessage = "Warning!!: User '$UserName' already exists With preferred object location."
        }

        if ($actionsTaken.Count -gt 0) {
            $overallUserActionMessage += "`n" + ($actionsTaken -join "`n")
        } else {
            $overallUserActionMessage += "`nNo further actions required."
        }
    
        return [PSCustomObject]@{ username = $UserName; status = "SKIPPED"; message = $overallUserActionMessage }
    }

    # Ensure Default Password is configured
    if ([string]::IsNullOrEmpty($Config.DefaultPassword)) {
        throw "DefaultPassword is not configured. Set it in the portal Settings before creating users."
    }

    # Create AD User with First Name, Last Name, Full Name, Email, and other properties
    try {
        # Get domain DNS name dynamically
        $DomainDNS = (Get-ADDomain).DNSRoot
        $UserPrincipal = "$UserName@$DomainDNS"

        New-ADUser -SamAccountName $UserName `
            -UserPrincipalName $UserPrincipal `
            -GivenName $FirstName `
            -Surname $LastName `
            -DisplayName $FullName `
            -Name $FullName `
            -EmailAddress $Email `
            -MobilePhone $Mobile `
            -Title $Designation `
            -Department $Department `
            -Office $Office `
            -Path $OUPath `
            -AccountPassword (ConvertTo-SecureString $Config.DefaultPassword -AsPlainText -Force) `
            -ChangePasswordAtLogon $true `
            -Enabled $true `
            -Credential $Config.AdminCredential | Out-Null

        # Update the Description with OU location and rank
        $Description = "Rank: $Rank | OU: $(($UserOUs -join ' > '))"
        Set-ADUser -Identity $UserName -Description $Description | Out-Null

        # Update Department and Company (Organization tab)
        Set-ADUser -Identity $UserName -Department $Department -Company $OperatingUnit | Out-Null

        # Add user to their OU group
        $AssignedOU = $UserOUs[-1]  # Get the lowest level OU
        Add-UserToGroup -UserName $UserName -OUName $AssignedOU -GroupConfigParam $Script:GroupConfig

        # Add user to conditional groups based on HRMS field rules
        Add-UserToConditionalGroups -UserName $UserName -EmpData $EmpData -GroupConfigParam $Script:GroupConfig -ExecutedByLog $ExecutedBy

        $GroupName = "$AssignedOU Group" -replace "&", "and"

        # Construct the desired success message
        $successMessage ="Success: User id '$UserName' Display Name '$FullName' created successfully. Temporay pass will be provided separately. in OU: '$AssignedOU'. Group: '$GroupName'."
        Write-Log -Action "CREATE" -TargetUser $UserName -Status "SUCCESS" -Message $successMessage -ExecutedByLog $ExecutedBy
        return [PSCustomObject]@{ username = $UserName; status = "SUCCESS"; message = $successMessage }

    } catch {
        $errorMessage = "Failed to create user '$FullName' ($UserName). $($_.Exception.Message)"
        Write-Log -Action "CREATE" -TargetUser $UserName -Status "FAILED" -Message $errorMessage -ExecutedByLog $ExecutedBy
        $script:overallSuccess = $false
        return [PSCustomObject]@{ username = $UserName; status = "FAILED"; message = $errorMessage }
    }
} # Added missing closing brace for Function Create-ADUser

# If no usernames provided, ask interactively
if (-not $Usernames) {
    $inputString = Read-Host "Enter Employee IDs (comma-separated)"
    $Usernames = $inputString -split ",\s*"
}

$mainUserCreationResults = New-Object System.Collections.ArrayList # Stores primary results for each user creation attempt

# Loop through each user ID and create users
foreach ($UserID in $Usernames) {
    $userCreationResult = Create-ADUser -EmpID $UserID
    if ($userCreationResult) {
        $mainUserCreationResults.Add($userCreationResult) | Out-Null
    }
}

# --- Final Output ---
$finalSuccessCount = @($mainUserCreationResults | Where-Object {$_.status -eq 'SUCCESS'}).Count
$finalSkippedCount = @($mainUserCreationResults | Where-Object {$_.status -eq 'SKIPPED'}).Count
$finalFailedCount = @($mainUserCreationResults | Where-Object {$_.status -eq 'FAILED'}).Count

# Combine all detailed messages from the main user creation results
$allDetailedMessages = ($mainUserCreationResults | ForEach-Object { $_.message }) -join "`n`n"

$overallMessage = "Processed: $($Usernames.Count) | Success: $finalSuccessCount | Skipped: $finalSkippedCount | Failed: $finalFailedCount"

$outputObject = [PSCustomObject]@{
    success = $script:overallSuccess # This will be true if any action was successful
    detailedActionMessage = $allDetailedMessages # Correctly include all messages
    summaryMessage = $overallMessage # This is the summary line
    processed = $Usernames.Count
    successCount = $finalSuccessCount
    skippedCount = $finalSkippedCount
    failedCount = $finalFailedCount
    moveCount = $script:moveCount
    resetCount = $script:resetCount
    enableCount = $script:enableCount
    userResults = $script:userResults # All detailed logs
    mainUserCreationResults = $mainUserCreationResults # Primary outcomes
    transcriptLogPath = $transcriptLogFile
    transcriptLogExists = [System.IO.File]::Exists($transcriptLogFile)
}
$outputObject | ConvertTo-Json -Compress -Depth 100
