param (
    [string]$Username,
    [string]$DisplayName,
    [string]$OU,
    [string]$Description = '',
    [string[]]$GroupMembers = @(),
    [switch]$IsServiceAccount,
    [string]$ServerOperation = '',
    [switch]$PasswordNeverExpires,
    [string]$ExecutedBy,
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
if ([string]::IsNullOrEmpty($Config.DefaultPassword)) {
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: DefaultPassword not found in secure configuration." })
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
$logFolder = Join-Path $BaseLogPath "$activeDomainAdName\scripts_logs\User_Management\ManualCreate"
$logFile = Join-Path $logFolder "audit-$(Get-Date -Format yyyy-MM-dd).log"

# Create log folder if it doesn't exist
if (!(Test-Path -Path $logFolder -PathType Container)) {
    try {
        New-Item -ItemType Directory -Path $logFolder -Force -ErrorAction Stop | Out-Null
    } catch {
        $errorMessage = "Failed to create log directory at $logFolder. $($_.Exception.Message)"
        Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: $errorMessage" })
        exit 1
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
    if ([string]::IsNullOrEmpty($ExecutedByLog)) { $ExecutedByLog = "UNKNOWN" }
    $Message = $Message -replace '^(SUCCESS|ERROR|FAILED|WARN):\s*', ''
    $logEntry = "[$(Get-Date -Format 'yyyy-MM-dd hh:mm:ss tt')] Action: $Action | TargetUser: $TargetUser | Status: $Status | Message: $Message | ExecutedBy: $ExecutedByLog"
    try {
        Add-Content -Path $logFile -Value $logEntry -ErrorAction Stop
    } catch {
        # If logging fails, just write to host
        Write-Host "ERROR: Failed to write to log file: $_"
    }
}

# Validate input
if ([string]::IsNullOrEmpty($Username) -or [string]::IsNullOrEmpty($DisplayName) -or [string]::IsNullOrEmpty($OU)) {
    $errorMessage = "Username, Display Name, and OU are required parameters."
    Write-Log -Action "M_CREATE" -TargetUser $Username -Status "FAILED" -Message $errorMessage -ExecutedByLog $ExecutedBy
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: $errorMessage" })
    exit 1
}

# The $OU parameter now directly receives the DistinguishedName from the frontend
$FullOUPath = $OU

# Extract simple OU name
$SimpleOUName = ($FullOUPath -split ',')[0] -replace 'OU=', ''

# Check if OU exists
if (-not (Get-ADOrganizationalUnit -Filter "DistinguishedName -eq '$FullOUPath'" -Credential $Config.AdminCredential -ErrorAction SilentlyContinue)) {
    $errorMessage = "Organizational Unit '$FullOUPath' does not exist."
    Write-Log -Action "M_CREATE" -TargetUser $Username -Status "FAILED" -Message $errorMessage -ExecutedByLog $ExecutedBy
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: $errorMessage" })
    exit 1
}

# Check if SamAccountName already exists
$existingSamAccountUser = Get-ADUser -Filter {SamAccountName -eq $Username} -Credential $Config.AdminCredential -ErrorAction SilentlyContinue
if ($existingSamAccountUser) {
    $errorMessage = "Username '$Username' already exists."
    Write-Log -Action "M_CREATE" -TargetUser $Username -Status "FAILED" -Message $errorMessage -ExecutedByLog $ExecutedBy
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: $errorMessage" })
    exit 1
}

# Check if DisplayName already exists (optional, but good for preventing conflicts)
$existingDisplayNameUser = Get-ADUser -Filter {DisplayName -eq $DisplayName} -Credential $Config.AdminCredential -ErrorAction SilentlyContinue
if ($existingDisplayNameUser) {
    $errorMessage = "Display name '$DisplayName' already exists."
    Write-Log -Action "M_CREATE" -TargetUser $Username -Status "FAILED" -Message $errorMessage -ExecutedByLog $ExecutedBy
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: $errorMessage" })
    exit 1
}

# Check if an object with the same Name (CN) already exists in the target OU
$existingCnInOu = Get-ADObject -Filter {Name -eq $DisplayName} -SearchBase $FullOUPath -Credential $Config.AdminCredential -ErrorAction SilentlyContinue
if ($existingCnInOu) {
    $errorMessage = "User creation failed. An object with the name '$DisplayName' already exists in the selected Organizational Unit. Please choose a different Display Name as it must be unique within the OU."
    Write-Log -Action "M_CREATE" -TargetUser $Username -Status "FAILED" -Message $errorMessage -ExecutedByLog $ExecutedBy
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: $errorMessage" })
    exit 1
}

# Create AD User
try {
    # Get domain DNS name dynamically
    $DomainDNS = (Get-ADDomain -Credential $Config.AdminCredential).DNSRoot
    $UserPrincipal = "$Username@$DomainDNS"

    # Parse DisplayName into FirstName and LastName
    $NameParts = $DisplayName -split "\s+", 2
    $FirstName = $NameParts[0]
    $LastName = if ($NameParts.Length -gt 1) { $NameParts[1] } else { $FirstName }

    # Resolve description for service accounts
    $finalDescription = $Description
    if ($IsServiceAccount -and [string]::IsNullOrEmpty($finalDescription) -and -not [string]::IsNullOrEmpty($ServerOperation)) {
        $finalDescription = "Service Account for $ServerOperation"
    }

    # Create a hashtable for the user parameters to avoid parameter passing issues
    $userParams = @{
        SamAccountName = $Username
        Name = $DisplayName
        DisplayName = $DisplayName
        GivenName = $FirstName
        Surname = $LastName
        UserPrincipalName = $UserPrincipal
        Path = $FullOUPath
        AccountPassword = (ConvertTo-SecureString $Config.DefaultPassword -AsPlainText -Force)
        ChangePasswordAtLogon = (-not $IsServiceAccount)
        PasswordNeverExpires = $IsServiceAccount -and $PasswordNeverExpires
        Enabled = $true
        Credential = $Config.AdminCredential
        PassThru = $true
        Description = $finalDescription
    }

    $newUser = New-ADUser @userParams | Out-Null # Suppress default output

    $groupAddMessages = @()
    $assignedGroups = @()
    if (-not [string]::IsNullOrEmpty($GroupMembers)) {
        $GroupMembersArray = $GroupMembers -split ';' | ForEach-Object { $_.Trim() }
        foreach ($groupDn in $GroupMembersArray) {
            try {
                Add-ADGroupMember -Identity $groupDn -Members $Username -Credential $Config.AdminCredential -ErrorAction Stop
                $groupAddMessages += "Added to group '$groupDn'"
                $assignedGroups += (Get-ADGroup -Identity $groupDn -Credential $Config.AdminCredential).Name
            } catch {
                $groupAddMessages += "Failed to add to group '$groupDn': $($_.Exception.Message)"
            }
        }
    }

    # Construct the desired success message
    $successMessage = "User id '$Username' Display name '$DisplayName' created successfully with the Temporary Password: '$($Config.DefaultPassword)' in OU '$SimpleOUName'"

    # If groups were assigned, append them to the message
    if ($assignedGroups.Count -gt 0) {
        $groupList = "'" + ($assignedGroups -join "', '") + "'"
        $successMessage += " and Members of $groupList"
    }

    $logMessage = "User created in OU '$FullOUPath'. Group assignments: $($groupAddMessages -join '; ')"
    Write-Log -Action "M_CREATE" -TargetUser $Username -Status "SUCCESS" -Message $logMessage -ExecutedByLog $ExecutedBy
    
    Write-Output (ConvertTo-Json @{ success = $true; message = $successMessage })

} catch {
    $fullError = $_ | Out-String
    $errorMessage = "A detailed error occurred during user creation. Inputs: (Username: $Username, DisplayName: $DisplayName, OU: $OU). Full error details: $fullError"
    Write-Log -Action "M_CREATE" -TargetUser $Username -Status "FAILED" -Message $errorMessage -ExecutedByLog $ExecutedBy
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: $errorMessage" })
    exit 1
}

exit 0
