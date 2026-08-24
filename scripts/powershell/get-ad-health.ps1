<#
    .SYNOPSIS
    Get-ADHealth.ps1 - Domain Controller Health Check Script.

    .DESCRIPTION
    This script performs a list of common health checks to a specific domain, or the entire forest. The results are then compiled into a colour coded HTML report.

    .OUTPUTS
    The results are currently only output to HTML for email or as an HTML report file, or sent as an SMTP message with an HTML body.

    .PARAMETER DomainName
    Perform a health check on a specific Active Directory domain.

    .PARAMETER ReportFile
    Output the report details to a file in the current directory.

    .PARAMETER SendEmail
    Send the report via email. You have to configure the correct SMTP settings.

    .EXAMPLE
    .\Get-ADHealth.ps1 -ReportFile
    Checks all domains and all domain controllers in your current forest and creates a report.

    .EXAMPLE
    .\Get-ADHealth.ps1 -DomainName oneforce.rnd -ReportFile
    Checks all the domain controllers in the specified domain "oneforce.rnd" and creates a report.

    .EXAMPLE
    .\Get-ADHealth.ps1 -DomainName oneforce.rnd -SendEmail
    Checks all the domain controllers in the specified domain "oneforce.rnd" and sends the resulting report as an email message.

    .LINK
    oneforce.rnd/active-directory-health-check-powershell-script

    .NOTES
    Written by: RAKIBUZZAMAN
    Website:    https://engr-rakib.github.io/web/
    LinkedIn:   https://www.linkedin.com/in/rkbzix
 

    .CHANGELOG
    V2.20, 04/02/2025 - Fixed for retrieving a single domain controller
#>

[CmdletBinding()]Param(
    [Parameter( Mandatory = $false)]
    [string]$DomainName,

    [Parameter( Mandatory = $false)]
    [switch]$ReportFile,

    [Parameter( Mandatory = $false)]
    [switch]$SendEmail,

    [Parameter( Mandatory = $false)]
    [string]$OutputReportPath,

    [Parameter( Mandatory = $false)]
    [string]$AppName = "AD Health Monitor",

    [Parameter( Mandatory = $false)]
    [string]$AppLogoPath = "C:\path\to\your\logo.png",  # Update this path to your logo file

    [Parameter( Mandatory = $false)]
    [string]$CopyrightYear = (Get-Date).Year,

    [Parameter( Mandatory = $false)]
    [string]$DeveloperName = "RAKIBUZZAMAN",

    [Parameter( Mandatory = $false)]
    [string]$DeveloperUrl = "https://engr-rakib.github.io/web/",

    [Parameter( Mandatory = $false)]
    [string]$CopyrightMessage = "© $CopyrightYear All Rights Reserved.",

    [Parameter( Mandatory = $false)]
    [string]$ExecutedBy,

    [Parameter( Mandatory = $true)]
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
    Write-Output "ERROR: Failed to load secure configuration. $($_.Exception.Message)"
    exit 1
}

# --- Validate configuration ---
if ($null -eq $Config) {
    Write-Output "ERROR: Secure configuration is empty or invalid."
    exit 1
}
if ($null -eq $Config.AdminCredential) {
    Write-Output "ERROR: Admin credentials not found in secure configuration."
    exit 1
}
if ([string]::IsNullOrEmpty($Config.BaseLogPath)) {
    Write-Output "ERROR: BaseLogPath not found in secure configuration."
    exit 1
}

#...................................
# Global Variables
#...................................

# --- Load Config from PHP file ---
$phpDeveloperName = Get-PhpConfigValue 'developer_name'
if ($phpDeveloperName) { $DeveloperName = $phpDeveloperName }

$phpDeveloperUrl = Get-PhpConfigValue 'developer_url'
if ($phpDeveloperUrl) { $DeveloperUrl = $phpDeveloperUrl }

$phpCopyrightMessage = Get-PhpConfigValue 'copyright_message'
if ($phpCopyrightMessage) { $CopyrightMessage = $phpCopyrightMessage }

$phpCopyrightYear = Get-PhpConfigValue 'copyright_year'
if ($phpCopyrightYear) { $CopyrightYear = $phpCopyrightYear }

# Load App Info
$phpLogoPath = Get-PhpConfigValue 'logo_path'
if ($phpLogoPath) { $AppLogoPath = $phpLogoPath }

$AppVersion = Get-PhpConfigValue 'version'
if (-not $AppVersion) { $AppVersion = "N/A" }


$allTestedDomainControllers = [System.Collections.Generic.List[Object]]::new()
$allDomainControllers = [System.Collections.Generic.List[Object]]::new()
$now = Get-Date
$date = $now.ToShortDateString()
$reportTime = $now.ToString("yyyy-MM-dd hh:mm:ss tt")
$reportFileNameTime = $now.ToString("yyyyMMdd_HHmmss")
$reportemailsubject = "Domain Controller Health Report"
$ForestName = (Get-ADForest -Credential $Config.AdminCredential).Name

$smtpsettings = @{
    To         = 'rakibcse47@gmail.com'
    From       = 'adhealth@yourdomain.com'
    Subject    = "$reportemailsubject - $date"
    SmtpServer = "mail.domain.com"
    Port       = "25"
    #Credential = (Get-Credential)
    #UseSsl     = $true
}

#...................................
# Functions
#...................................

Function Get-ADGroupMemberCount($groupName) {
    try {
        (Get-ADGroupMember -Identity $groupName -Recursive).Count
    } catch {
        0
    }
}

Function Get-ADUserCount($filter) {
    try {
        (Get-ADUser -Filter $filter).Count
    } catch {
        0
    }
}

# --- Function to get replication summary ---
Function Get-ReplicationSummary {
    $summary = @()
    $repadminOutput = repadmin /replsummary
    $inSourceSection = $false
    $inDestinationSection = $false

    foreach ($line in $repadminOutput) {
        if ($line -match "Source DSA") {
            $inSourceSection = $true
            $inDestinationSection = $false
            continue
        }
        if ($line -match "Destination DSA") {
            $inDestinationSection = $true
            $inSourceSection = $false
            continue
        }

        if (($inSourceSection -or $inDestinationSection) -and $line -match "\w") {
            $parts = $line.Split(' ',[System.StringSplitOptions]::RemoveEmptyEntries)
            if ($parts.Count -ge 5) {
                $summary += [PSCustomObject]@{ 
                    Type = if ($inSourceSection) { "Source" } else { "Destination" }
                    DSA = $parts[0]
                    LargestDelta = $parts[1]
                    Fails = $parts[2]
                    Total = $parts[4] # index 3 is "/"
                    Percent = $parts[5]
                    Error = if ($parts.Count -gt 6) { $parts[6..($parts.Count-1)] -join ' ' } else { '' }
                }
            }
        }
    }
    return $summary
}

# --- Function to get FSMO roles ---
Function Get-FSMORoles {
    $schemaMaster = (Get-ADForest).SchemaMaster
    $domainNamingMaster = (Get-ADForest).DomainNamingMaster
    $pdcEmulator = (Get-ADDomain).PDCEmulator
    $ridMaster = (Get-ADDomain).RIDMaster
    $infrastructureMaster = (Get-ADDomain).InfrastructureMaster

    return [PSCustomObject]@{ 
        SchemaMaster = $schemaMaster
        DomainNamingMaster = $domainNamingMaster
        PDCEmulator = $pdcEmulator
        RIDMaster = $ridMaster
        InfrastructureMaster = $infrastructureMaster
    }
}

# --- Function to get security summary ---
Function Get-SecuritySummary {
    try {
        $lockedOutUsers = @(Search-ADAccount -LockedOut -ErrorAction Stop).Count
    } catch {
        $lockedOutUsers = "Error"
    }

    try {
        $inactiveUsers = @(Search-ADAccount -AccountInactive -TimeSpan 90.00:00:00 -UsersOnly -ErrorAction Stop).Count
    } catch {
        $inactiveUsers = "Error"
    }

    try {
        $expiredPasswords = @(Search-ADAccount -PasswordExpired -ErrorAction Stop).Count
    } catch {
        $expiredPasswords = "Error"
    }

    return [PSCustomObject]@{ 
        LockedOutUsers = $lockedOutUsers
        InactiveUsers = $inactiveUsers
        ExpiredPasswords = $expiredPasswords
    }
}

# --- Function to get Trust Information ---
Function Get-TrustInfo {
    try {
        return Get-ADTrust -Filter * -ErrorAction Stop
    } catch {
        Write-Warning "Could not retrieve AD trust information. Error: $_ "
        return $null
    }
}

# --- Function to get AD Backup Status ---
Function Get-ADBackupStatus($ComputerName) {
    Write-Verbose "Running function Get-ADBackupStatus on $ComputerName"
    try {
        $lastBackupTime = Invoke-Command -ComputerName $ComputerName -ScriptBlock {
            if (Get-Module -ListAvailable -Name WindowsServerBackup) {
                (Get-WBSummary).LastBackupTime
            }
        } -ErrorAction Stop
        
        if ($lastBackupTime) {
            return $lastBackupTime.ToString("yyyy-MM-dd HH:mm:ss")
        } else {
            return "Not Available"
        }
    } catch {
        return "Error" # Keep it simple for the card
    }
}

# --- Function to get GPO Summary ---
Function Get-GPOSummary {
    try {
        return @(Get-GPO -All -ErrorAction Stop).Count
    } catch {
        Write-Warning "Could not retrieve GPO count. Error: $_ "
        return "Error"
    }
}

# --- Function to get recent critical events ---
Function Get-RecentCriticalEvents($ComputerName) {
    Write-Verbose "Running function Get-RecentCriticalEvents on $ComputerName"
    try {
        $24h = (Get-Date).AddHours(-24)
        $events = Get-WinEvent -ComputerName $ComputerName -FilterHashtable @{
            LogName = 'Directory Service'
            Level = 1,2 # 1=Critical, 2=Error
            StartTime = $24h
        } -ErrorAction Stop
        return @($events).Count
    } catch {
        return "Error" # Keep it simple for the card
    }
}

# --- Function to parse footer config from app_config.php ---
Function Get-PhpConfigValue($Key) {
    # Dynamically locate app_config.php relative to the script location (..\..\config\app_config.php)
    $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
    $configPath = Join-Path (Split-Path -Parent $scriptDir) "config\app_config.php"
    
    if (-not (Test-Path $configPath -PathType Leaf)) {
        Write-Host "DEBUG ERROR: Config file not found at $configPath." -ForegroundColor Red
        return $null
    }

    try {
        $fileContent = Get-Content -Path $configPath -Raw
        # This regex first finds the 'app_info' array, then captures the value of the specified key within it.
        $pattern = "'app_info'\s*=>\s*\[([^\]]*)\]"
        $appInfoMatch = [regex]::Match($fileContent, $pattern)

        if ($appInfoMatch.Success) {
            $appInfoContent = $appInfoMatch.Groups[1].Value
            $keyPattern = "'$Key'"
            $regexTemplate = @'
{keyPattern}\s*=>\s*['"]([^'"]*)['"]
'@
            $regexPattern = $regexTemplate -replace '{keyPattern}', $keyPattern
            $match = [regex]::Match($appInfoContent, $regexPattern)

            if ($match.Success) {
                Write-Host "DEBUG SUCCESS: Found value '$($match.Groups[1].Value)' for key '$Key'." -ForegroundColor Green
                return $match.Groups[1].Value
            } else {
                Write-Host "DEBUG ERROR: Could not find a match for key '$Key' within the 'app_info' array." -ForegroundColor Red
                return $null
            }
        } else {
            Write-Host "DEBUG ERROR: Could not find the 'app_info' array in the config file." -ForegroundColor Red
            return $null
        }
    } catch {
        Write-Host "DEBUG CRITICAL ERROR: An exception occurred while parsing '$Key'. Error: $_ " -ForegroundColor Magenta
        return $null
    }
}

Function Convert-HoursToDaysHours($totalHours) {
    if ($totalHours -eq "CIM Failure" -or $totalHours -eq "Fail") {
        return $totalHours
    }
    $days = [int]($totalHours / 24)
    $hours = $totalHours % 24
    return "$days days, $hours hours"
}

Function Convert-DeltaToMinutes($delta) {
    if ($delta -match '(\d+)h') { $hours = [int]$matches[1] } else { $hours = 0 }
    if ($delta -match '(\d+)m') { $minutes = [int]$matches[1] } else { $minutes = 0 }
    if ($delta -match '(\d+)s') { $seconds = [int]$matches[1] } else { $seconds = 0 }
    $totalMinutes = $hours * 60 + $minutes + ($seconds / 60)
    return [math]::Round($totalMinutes, 2)
}

Function Get-StatusClass($statusValue) {
    switch ($statusValue) {
        "Success" { return "bg-success text-white" }
        "Passed" { return "bg-success text-white" }
        "Pass" { return "bg-success text-white" }
        "Fail" { return "bg-danger text-white" }
        "Failed" { return "bg-danger text-white" }
        "CIM Failure" { return "bg-danger text-white" }
        "Could not test server uptime." { return "bg-danger text-white" }
        default { return "bg-warning text-dark" }
    }
}

Function Get-DiskSpaceClass($percentage) {
    if ($percentage -ge 30) { return "bg-success text-white" }
    elseif ($percentage -ge 15) { return "bg-warning text-dark" }
    else { return "bg-danger text-white" }
}

# This function gets all the domains in the forest.
Function Get-AllDomains() {
    Write-Verbose "Running function Get-AllDomains"
    $allDomains = (Get-ADForest -Credential $Config.AdminCredential).Domains
    return $allDomains
}

# This function gets all the domain controllers in a specified domain.
Function Get-AllDomainControllers ($ComputerName) {
    Write-Verbose "Running function Get-AllDomainControllers"
    $allDomainControllers = Get-ADDomainController -Filter * -Server $ComputerName -Credential $Config.AdminCredential | Sort-Object HostName
    return $allDomainControllers
}

# This function tests the domain controller against DNS.
Function Get-DomainControllerNSLookup($ComputerName) {
    Write-Verbose "Running function Get-DomainControllerNSLookup"
    try {
        $domainControllerNSLookupResult = Resolve-DnsName $ComputerName -Type A | Select-Object -ExpandProperty IPAddress
        $domainControllerNSLookupResult = 'Success'
    }
    catch {
        $domainControllerNSLookupResult = 'Fail'
    }
    return $domainControllerNSLookupResult
}

# This function tests the connectivity to the domain controller.
Function Get-DomainControllerPingStatus($ComputerName) {
    Write-Verbose "Running function Get-DomainControllerPingStatus"
    if ((Test-Connection $ComputerName -Count 1 -quiet) -eq $True) {
        $domainControllerPingStatus = "Success"
    }
    else {
        $domainControllerPingStatus = 'Fail'
    }
    return $domainControllerPingStatus
}

# This function tests the domain controller uptime.
Function Get-DomainControllerUpTime($ComputerName) {
    Write-Verbose "Running function Get-DomainControllerUpTime"
    if ((Test-Connection $ComputerName -Count 1 -Quiet) -eq $True) {
        try {
            $W32OS = Get-CimInstance -ClassName Win32_OperatingSystem -ComputerName $ComputerName -ErrorAction SilentlyContinue
            $timespan = (Get-Date) - $W32OS.LastBootUpTime
            [int]$uptime = "{0:00}" -f $timespan.TotalHours
        }
        catch {
            $uptime = 'CIM Failure'
        }
    }
    else {
        $uptime = 'Fail'
    }
    return $uptime
}

# This function checks the time synchronization offset.
function Get-TimeDifference($ComputerName) {
    Write-Verbose "Running function Get-TimeDifference"
    if ((Test-Connection $ComputerName -Count 1 -Quiet) -eq $True) {
        try {
            $currentTime, $timeDifference = (& w32tm /stripchart /computer:$ComputerName /samples:1 /dataonly)[-1].Trim("s") -split ',\s*'
            $diff = [double]$timeDifference
            $diffRounded = [Math]::Round($diff, 1, [MidPointRounding]::AwayFromZero)
        }
        catch {
            $diffRounded = 'Fail'
        }
    }
    else {
        $diffRounded = 'Fail'
    }
    return $diffRounded
}

# This function checks the DNS, NTDS and Netlogon services.
Function Get-DomainControllerServices($ComputerName) {
    Write-Verbose "Running function DomainControllerServices"
    $thisDomainControllerServicesTestResult = [PSCustomObject]@{ 
        DNSService      = $null
        NTDSService     = $null
        NETLOGONService = $null
    }

    if ((Test-Connection $ComputerName -Count 1 -quiet) -eq $True) {
        if ((Get-Service -ComputerName $ComputerName -Name DNS -ErrorAction SilentlyContinue).Status -eq 'Running') {
            $thisDomainControllerServicesTestResult.DNSService = 'Success'
        }
        else {
            $thisDomainControllerServicesTestResult.DNSService = 'Fail'
        }
        if ((Get-Service -ComputerName $ComputerName -Name NTDS -ErrorAction SilentlyContinue).Status -eq 'Running') {
            $thisDomainControllerServicesTestResult.NTDSService = 'Success'
        }
        else {
            $thisDomainControllerServicesTestResult.NTDSService = 'Fail'
        }
        if ((Get-Service -ComputerName $ComputerName -Name netlogon -ErrorAction SilentlyContinue).Status -eq 'Running') {
            $thisDomainControllerServicesTestResult.NETLOGONService = 'Success'
        }
        else {
            $thisDomainControllerServicesTestResult.NETLOGONService = 'Fail'
        }
    }
    else {
        $thisDomainControllerServicesTestResult.DNSService = 'Fail'
        $thisDomainControllerServicesTestResult.NTDSService = 'Fail'
        $thisDomainControllerServicesTestResult.NETLOGONService = 'Fail'
    }
    return $thisDomainControllerServicesTestResult
}

# This function runs the DCDiag tests and saves them in a variable for later processing.
Function Get-DomainControllerDCDiagTestResults($ComputerName) {
    Write-Verbose "Running function Get-DomainControllerDCDiagTestResults"

    # Initialize the object with all properties set to null
    $DCDiagTestResults = [PSCustomObject]@{ 
        ServerName         = $ComputerName
        Connectivity       = $null
        Advertising        = $null
        FrsEvent           = $null
        DFSREvent          = $null
        SysVolCheck        = $null
        KccEvent           = $null
        KnowsOfRoleHolders = $null
        MachineAccount     = $null
        NCSecDesc          = $null
        NetLogons          = $null
        ObjectsReplicated  = $null
        Replications       = $null
        RidManager         = $null
        Services           = $null
        SystemLog          = $null
        VerifyReferences   = $null
        CheckSDRefDom      = $null
        CrossRefValidation = $null
        LocatorCheck       = $null
        Intersite          = $null
        FSMOCheck          = $null
    }

    if ((Test-Connection $ComputerName -Count 1 -quiet) -eq $True) {
        # Define an array of parameters for Dcdiag.exe
        $params = @(
            "/s:$ComputerName",
            "/test:Connectivity",
            "/test:Advertising",
            "/test:FrsEvent",
            "/test:DFSREvent",
            "/test:SysVolCheck",
            "/test:KccEvent",
            "/test:KnowsOfRoleHolders",
            "/test:MachineAccount",
            "/test:NCSecDesc",
            "/test:NetLogons",
            "/test:ObjectsReplicated",
            "/test:Replications",
            "/test:RidManager",
            "/test:Services",
            "/test:SystemLog",
            "/test:VerifyReferences",
            "/test:CheckSDRefDom",
            "/test:CrossRefValidation",
            "/test:LocatorCheck",
            "/test:Intersite",
            "/test:FSMOCheck"
        )

        $DCDiagOutput = (Dcdiag.exe @params 2>&1)
        $DCDiagExitCode = $LASTEXITCODE

        if ($DCDiagExitCode -ne 0 -or $DCDiagOutput -match "failed to connect|could not be contacted|failed test") {
            # If DCDiag itself indicates a general failure, mark all tests as Failed
            foreach ($property in $DCDiagTestResults.PSObject.Properties.Name) {
                if ($property -ne "ServerName") {
                    $DCDiagTestResults.$property = "Failed"
                }
            }
        } else {
            $DCDiagTest = $DCDiagOutput -split ('[
]')

            $TestName = $null
            $TestStatus = $null

            $DCDiagTest | ForEach-Object {
                switch -Regex ($_) {
                    "Starting test:" {
                        $TestName = ($_ -replace ".*Starting test:").Trim()
                    }
                    "passed test|failed test" {
                        $TestStatus = if ($_ -match "passed test") { "Passed" } else { "Failed" }
                    }
                }
                if ($TestName -and $TestStatus) {
                    # Set the property value directly
                    $DCDiagTestResults.$TestName = $TestStatus
                    $TestName = $null
                    $TestStatus = $null
                }
            }
        }
    }
    else {
        # If the domain controller is not reachable, set all tests to 'Failed'
        foreach ($property in $DCDiagTestResults.PSObject.Properties.Name) {
            if ($property -ne "ServerName") {
                $DCDiagTestResults.$property = "Failed"
            }
        }
    }
    return $DCDiagTestResults
}

# This function checks the free space in percentage on the OS drive
Function Get-DomainControllerOSDriveFreeSpace ($ComputerName) {
    Write-Verbose "Running function Get-DomainControllerOSDriveFreeSpace"
    if ((Test-Connection $ComputerName -Count 1 -Quiet) -eq $True) {
        try {
            $thisOSDriveLetter = (Get-CimInstance -ClassName Win32_OperatingSystem -ComputerName $ComputerName -ErrorAction Stop).SystemDrive
            $thisOSDiskDrive = Get-CimInstance -ClassName Win32_LogicalDisk -ComputerName $ComputerName -Filter "DeviceID='$thisOSDriveLetter'" -ErrorAction Stop
            $thisOSPercentFree = [math]::Round($thisOSDiskDrive.FreeSpace / $thisOSDiskDrive.Size * 100)
        }
        catch {
            $thisOSPercentFree = 'CIM Failure'
        }
    }
    else {
        $thisOSPercentFree = "Fail"
    }
    return $thisOSPercentFree
}

# This function checks the free disk space on the OS drive in GB
Function Get-DomainControllerOSDriveFreeSpaceGB ($ComputerName) {
    Write-Verbose "Running function Get-DomainControllerOSDriveFreeSpaceGB"
    if ((Test-Connection $ComputerName -Count 1 -Quiet) -eq $True) {
        try {
            $thisOSDriveLetter = (Get-CimInstance -ClassName Win32_OperatingSystem -ComputerName $ComputerName -ErrorAction Stop).SystemDrive
            $thisOSDiskDrive = Get-CimInstance -ClassName Win32_LogicalDisk -ComputerName $ComputerName -Filter "DeviceID='$thisOSDriveLetter'" -ErrorAction Stop
            # Convert bytes to GB, rounding to 2 decimal places
            $freeSpaceGB = [math]::Round($thisOSDiskDrive.FreeSpace / 1GB, 2)
        }
        catch {
            $freeSpaceGB = 'CIM Failure'
        }
    }
    else {
        $freeSpaceGB = 'Fail'
    }
    return $freeSpaceGB
}

# This function generates HTML code from the results of the above functions.
Function New-ServerHealthHTMLTableCell() {
    param( 
        [Parameter(Mandatory=$true)]
        $reportline,
        [Parameter(Mandatory=$true)]
        [string]$lineitem 
    )
    Write-Verbose "Generating HTML cell for: $lineitem"
    try {
        $htmltablecell = $null
        $value = $reportline.$lineitem
        switch ($value) {
            "Success" { $htmltablecell = '<td class="bg-success text-white"><i class="bi bi-check-circle-fill"></i> ' + $value + '</td>' }
            "Passed" { $htmltablecell = '<td class="bg-success text-white"><i class="bi bi-check-circle-fill"></i> ' + $value + '</td>' }
            "Pass" { $htmltablecell = '<td class="bg-success text-white"><i class="bi bi-check-circle-fill"></i> ' + $value + '</td>' }
            "Warn" { $htmltablecell = '<td class="bg-warning"><i class="bi bi-exclamation-triangle-fill"></i> ' + $value + '</td>' }
            "Fail" { $htmltablecell = '<td class="bg-danger text-white"><i class="bi bi-x-circle-fill"></i> ' + $value + '</td>' }
            "Failed" { $htmltablecell = '<td class="bg-danger text-white"><i class="bi bi-x-circle-fill"></i> ' + $value + '</td>' }
            "Could not test server uptime." { $htmltablecell = '<td class="bg-danger text-white">' + $value + '</td>' }
            default { $htmltablecell = '<td class="bg-light">' + $value + '</td>' }
        }
    }
    catch {
        Write-Warning "Error generating HTML cell for $lineitem. Error: $_ "
        $htmltablecell = '<td class="bg-danger text-white">ERROR</td>'
    }
    return $htmltablecell
}

if (!($DomainName)) {
    Write-Host "No domain specified, using all domains in forest" -ForegroundColor Yellow
    $allDomains = Get-AllDomains
    $reportFileName = 'forest_health_report_' + (Get-ADForest -Credential $Config.AdminCredential).name + '_' + $reportFileNameTime + '.html'
}
else {
    Write-Host "Domain name specified on cmdline" -ForegroundColor Cyan
    $allDomains = $DomainName
    $reportFileName = 'dc_health_report_' + $DomainName + '_' + $reportFileNameTime + '.html'
}

# Initialize counters for grand total
$totalDCs = 0
$healthyDCs = 0
$warningDCs = 0
$failedDCs = 0

foreach ($domain in $allDomains) {
    Write-Host "Testing domain" $domain -ForegroundColor Green
    $domainControllersInThisDomain = Get-AllDomainControllers $domain

    # Force array first, then get count
    $domainControllersInThisDomain = @($domainControllersInThisDomain)
    $totalDCs += $domainControllersInThisDomain.Count

    # Initialize counter for display
    $currentDCNumber = 0

    foreach ($domainController in $domainControllersInThisDomain) {
        $currentDCNumber++
        $stopWatch = [system.diagnostics.stopwatch]::StartNew()
        Write-Host "Testing domain controller ($currentDCNumber of $totalDCs) $($domainController.HostName)" -ForegroundColor Cyan
        $DCDiagTestResults = Get-DomainControllerDCDiagTestResults $domainController.HostName

        # Determine overall status for summary counts
        $overallStatus = "Healthy"
        if (($DCDiagTestResults.PSObject.Properties | Where-Object { $_.Name -ne "ServerName" -and $_.Value -eq "Failed" }).Count -gt 0) {
            $overallStatus = "Failed"
        } elseif (($DCDiagTestResults.PSObject.Properties | Where-Object { $_.Name -ne "ServerName" -and $_.Value -eq "Warn" }).Count -gt 0) {
            $overallStatus = "Warning"
        }

        switch ($overallStatus) {
            "Healthy" { $healthyDCs++ }
            "Warning" { $warningDCs++ }
            "Failed" { $failedDCs++ }
        }

                $freeSpace = Get-DomainControllerOSDriveFreeSpace $domainController.HostName
                $usedSpace = if ($freeSpace -is [int]) { 100 - $freeSpace } else { $freeSpace }
        
                $thisDomainController = [PSCustomObject]@{
                    Server                            = ($domainController.HostName).ToLower()
                    Site                              = $domainController.Site
                    "OS Version"                      = $domainController.OperatingSystem
                    "IPv4 Address"                    = $domainController.IPv4Address
                    "Operation Master Roles"          = $domainController.OperationMasterRoles
                    "DNS"                             = Get-DomainControllerNSLookup $domainController.HostName
                    "Ping"                            = Get-DomainControllerPingStatus $domainController.HostName
                    "Uptime (hours)"                  = Get-DomainControllerUpTime $domainController.HostName
                    "OS Free Space (%)"               = $freeSpace
                    "OS Used Space (%)"               = $usedSpace
                    "OS Free Space (GB)"              = Get-DomainControllerOSDriveFreeSpaceGB $domainController.HostName            "Time offset (seconds)"           = Get-TimeDifference $domainController.HostName
            "DNS Service"                     = (Get-DomainControllerServices $domainController.HostName).DNSService
            "NTDS Service"                    = (Get-DomainControllerServices $domainController.HostName).NTDSService
            "NetLogon Service"                = (Get-DomainControllerServices $domainController.HostName).NETLOGONService
            "Last Backup"                     = Get-ADBackupStatus $domainController.HostName
            "Critical Events (24h)"           = Get-RecentCriticalEvents $domainController.HostName
            "DCDIAG: Connectivity"            = $DCDiagTestResults.Connectivity
            "DCDIAG: Advertising"             = $DCDiagTestResults.Advertising
            "DCDIAG: FrsEvent"                = $DCDiagTestResults.FrsEvent
            "DCDIAG: DFSREvent"               = $DCDiagTestResults.DFSREvent
            "DCDIAG: SysVolCheck"             = $DCDiagTestResults.SysVolCheck
            "DCDIAG: KccEvent"                = $DCDiagTestResults.KccEvent
            "DCDIAG: FSMO KnowsOfRoleHolders" = $DCDiagTestResults.KnowsOfRoleHolders
            "DCDIAG: MachineAccount"          = $DCDiagTestResults.MachineAccount
            "DCDIAG: NCSecDesc"               = $DCDiagTestResults.NCSecDesc
            "DCDIAG: NetLogons"               = $DCDiagTestResults.NetLogons
            "DCDIAG: ObjectsReplicated"       = $DCDiagTestResults.ObjectsReplicated
            "DCDIAG: Replications"            = $DCDiagTestResults.Replications
            "DCDIAG: RidManager"              = $DCDiagTestResults.RidManager
            "DCDIAG: Services"                = $DCDiagTestResults.Services
            "DCDIAG: SystemLog"               = $DCDiagTestResults.SystemLog
            "DCDIAG: VerifyReferences"        = $DCDiagTestResults.VerifyReferences
            "DCDIAG: CheckSDRefDom"           = $DCDiagTestResults.CheckSDRefDom
            "DCDIAG: CrossRefValidation"      = $DCDiagTestResults.CrossRefValidation
            "DCDIAG: LocatorCheck"            = $DCDiagTestResults.LocatorCheck
            "DCDIAG: Intersite"               = $DCDiagTestResults.Intersite
            "DCDIAG: FSMO Check"              = $DCDiagTestResults.FSMOCheck
            "Processing Time (seconds)"       = $stopWatch.Elapsed.Seconds
            OverallStatus                     = $overallStatus  # Added missing OverallStatus property
        }

        $allTestedDomainControllers.Add($thisDomainController)
    }
}

# --- Generate Horizontal DCDiag Results Table (Tests as rows, DCs as columns) ---
$dcdiagTable = @"
<h1 class="mt-4 mb-3 text-center">DCDiag Test Results</h1>
<div class="table-responsive">
    <table id="dcdiag-table" class="table table-striped table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Test Name</th>
"@

# Get sorted list of servers
$servers = $allTestedDomainControllers | Sort-Object Server | Select-Object -ExpandProperty Server
foreach ($server in $servers) {
    $dcdiagTable += "<th>$server</th>"
}
$dcdiagTable += "</tr></thead><tbody>"

# DCDiag test properties with display names
$dcdiagProps = @(
    @{Name='DCDIAG: Advertising'; Display='Advertising'},
    @{Name='DCDIAG: CheckSDRefDom'; Display='CheckSDRefDom'},
    @{Name='DCDIAG: Connectivity'; Display='Connectivity'},
    @{Name='DCDIAG: CrossRefValidation'; Display='CrossRefValidation'},
    @{Name='DCDIAG: DFSREvent'; Display='DFSREvent'},
    @{Name='DCDIAG: FSMO Check'; Display='FSMO Check'},
    @{Name='DCDIAG: FSMO KnowsOfRoleHolders'; Display='FSMO KnowsOfRoleHolders'},
    @{Name='DCDIAG: FrsEvent'; Display='FrsEvent'},
    @{Name='DCDIAG: Intersite'; Display='Intersite'},
    @{Name='DCDIAG: KccEvent'; Display='KccEvent'},
    @{Name='DCDIAG: LocatorCheck'; Display='LocatorCheck'},
    @{Name='DCDIAG: MachineAccount'; Display='MachineAccount'},
    @{Name='DCDIAG: NCSecDesc'; Display='NCSecDesc'},
    @{Name='DCDIAG: NetLogons'; Display='NetLogons'},
    @{Name='DCDIAG: ObjectsReplicated'; Display='ObjectsReplicated'},
    @{Name='DCDIAG: Replications'; Display='Replications'},
    @{Name='DCDIAG: RidManager'; Display='RidManager'},
    @{Name='DCDIAG: Services'; Display='Services'},
    @{Name='DCDIAG: SysVolCheck'; Display='SysVolCheck'},
    @{Name='DCDIAG: SystemLog'; Display='SystemLog'},
    @{Name='DCDIAG: VerifyReferences'; Display='VerifyReferences'}
)

foreach ($prop in $dcdiagProps) {
    $dcdiagTable += "<tr><td><strong>$($prop.Display)</strong></td>"
    foreach ($server in $servers) {
        $reportline = $allTestedDomainControllers | Where-Object { $_.Server -eq $server } | Select-Object -First 1
        $statusCell = New-ServerHealthHTMLTableCell -reportline $reportline -lineitem $prop.Name
        $dcdiagTable += $statusCell
    }
    $dcdiagTable += "</tr>"
}

$dcdiagTable += @"
        </tbody>
    </table>
</div>
"@

$summaryDashboardHtml = @"
<div class="row justify-content-center mb-3 g-1">
    <div class="col-4 mb-2">
        <div class="card text-white bg-success text-center h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-check-circle-fill"></i> Healthy DCs</h5>
                <p class="card-text display-6">$healthyDCs</p>
            </div>
        </div>
    </div>
    <div class="col-4 mb-2">
        <div class="card text-white bg-warning text-center h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-exclamation-triangle-fill"></i> Warning DCs</h5>
                <p class="card-text display-6">$warningDCs</p>
            </div>
        </div>
    </div>
    <div class="col-4 mb-2">
        <div class="card text-white bg-danger text-center h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-x-circle-fill"></i> Failed DCs</h5>
                <p class="card-text display-6">$failedDCs</p>
            </div>
        </div>
    </div>
</div>
"@

# Common HTML head and styles with Chart.js and watermark CSS
$htmlhead_part1 = @"
<html>
        <head>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
        <style>
        BODY { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 10pt; }
        H1 { font-size: 20px; }
        H2 { font-size: 16px; }
        H3 { font-size: 14px; }
        TABLE { border: 1px solid #ccc; border-collapse: collapse; font-size: 10pt;}
            TH { border: 1px solid #ccc; background: #f2f2f2; padding: 10px; color: #000000;}
                TD { border: 1px solid #ccc; padding: 10px; }

        @media print {
            /* Force print backgrounds and colors */
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            /* Ensure table borders are visible */
            table, th, td {
                border-color: #dee2e6 !important;
                font-size: 7pt; /* More aggressive font size reduction for print */
                word-wrap: break-word; /* Ensure long text wraps */
                overflow-wrap: break-word;
            }
            table {
                table-layout: auto; /* Allow column widths to adjust based on content */
            }
            /* Adjust font size for print readability */
            h1, h2, h3, h4, h5, h6 {
                font-size: 12pt;
            }
            /* Ensure full width for tables */
            .table-responsive {
                overflow-x: hidden !important;
            }
            #dcdiag-table {
                font-size: 6pt; /* Smaller font size for DCDiag table in print */
            }
        }
            .group-outline {
                border: 2px solid;
                padding: 15px;
                margin-bottom: 20px;
                border-radius: 8px;
            }
            .group-blue { border-color: #007bff; } /* Bootstrap primary blue */
            .group-green { border-color: #28a745; } /* Bootstrap success green */
            .group-orange { border-color: #f07008ff; } /* Bootstrap orange */
            .group-purple { border-color: #6f42c1; } /* Bootstrap purple */
            .group-red { border-color: #dc3545; } /* Bootstrap danger red */
            .group-info { border-color: #119db9ff; } /* Bootstrap info cyan */

            /* Watermark Styles */
            .watermark {
                position: fixed;
                bottom: 20px;
                right: 20px;
                opacity: 0.1;
                z-index: 9999;
                pointer-events: none;
                width: 150px;
                height: auto;
            }
                                    </style>
                                    </head>
                                    <body>
                                    <div class="container-fluid">
                                    
                                    <div class="row align-items-center mb-3">
                                        <div class="col-auto">
                                            <img src="$AppLogoPath" alt="Logo" style="height: 50px;">
                                        </div>
                                        <div class="col">
                                            <h1 class="my-0">$AppName - Domain Controller Health Check Report</h1>
                                            <p class="text-muted mb-0">Organization: $ForestName Server Infrastructure</p>
                                        </div>
                                    </div>
"@

$htmlhead_part2 = @"
                                    <p class="mb-1 text-start">Generated: $reportTime</p>
                                    <p class="mb-1 text-start">Generated by: $DeveloperName</p>
"@




$cardColors = @('bg-primary', 'bg-secondary', 'bg-success', 'bg-danger', 'bg-warning', 'bg-info', 'bg-dark')
$cardColorIndex = 0
$serverhealthhtml = '<div class="row g-2">'
foreach ($reportline in $allTestedDomainControllers) {
    $fsmoRoleHTML = ''
    if (($reportline."Operation Master Roles").Count -gt 0) {
        $fsmoRoleHTML = ($reportline."Operation Master Roles" | ForEach-Object { "<span class='badge bg-light text-dark me-1'>$_</span>" }) -join ''
    } else {
        $fsmoRoleHTML = 'None'
    }

    # --- Determine card color based on status ---
    $cardBgClass = "bg-light text-dark"
    $headerColor = "bg-primary"

    switch ($reportline.OverallStatus) {
        "Healthy" {
            $cardBgClass = "bg-success-subtle text-dark border-success"
            $headerColor = "bg-success text-white"
        }
        "Warning" {
            $cardBgClass = "bg-warning-subtle text-dark border-warning"
            $headerColor = "bg-warning text-dark"
        }
        "Failed" {
            $cardBgClass = "bg-danger-subtle text-dark border-danger"
            $headerColor = "bg-danger text-white"
        }
        default {
            $cardBgClass = "bg-light text-dark border-secondary"
            $headerColor = "bg-secondary text-white"
        }
    }

    # Count DCDiag failures for this DC
    $dcdiagFailures = ($dcdiagProps | Where-Object { $reportline.($_.Name) -eq 'Failed' }).Count

    $serverhealthhtml += @"
        <div class="col-md-6 mb-3">
            <div class="card h-100 border-3 $cardBgClass">
                <div class="card-header text-center fw-bold $headerColor">
                    <h5 class="mb-0">$($reportline.Server)</h5>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>IP Address:</strong> $($reportline.'IPv4 Address')</p>
                    <p class="mb-1"><strong>OS Version:</strong> $($reportline.'OS Version')</p>
                    <p class="mb-1"><strong>Site:</strong> $($reportline.Site)</p>
                    <p class="mb-1"><strong>Uptime:</strong> $(Convert-HoursToDaysHours $reportline.'Uptime (hours)')</p>
                    <p class="mb-1"><strong>Last Backup:</strong> <span class="badge bg-info text-white">$($reportline.'Last Backup')</span></p>
                    <p class="mb-1"><strong>Critical Events (24h):</strong> <span class="badge bg-danger">$($reportline.'Critical Events (24h)')</span></p>
                    <p class="mb-1"><strong>Time Offset:</strong> $($reportline.'Time offset (seconds)') seconds</p>
                    <p class="mb-1"><strong>DNS:</strong> <span class="badge $(Get-StatusClass $reportline.DNS)">$($reportline.DNS)</span></p>
                    <p class="mb-1"><strong>Ping:</strong> <span class="badge $(Get-StatusClass $reportline.Ping)">$($reportline.Ping)</span></p>
                    <p class="mb-1"><strong>Processing Time:</strong> <span class="badge bg-info text-white">$($reportline.'Processing Time (seconds)') seconds</span></p>
                    <p class="mb-1"><strong>DCDiag Failures:</strong> <span class="badge $(if ($dcdiagFailures -gt 0) { 'bg-danger' } else { 'bg-success' }) text-white">$dcdiagFailures</span></p>
                    <p class="mb-1"><strong>FSMO Roles:</strong> $fsmoRoleHTML</p>
                    <p class="mb-1"><strong>DNS Service:</strong> <span class="badge $(Get-StatusClass $reportline.'DNS Service')">$($reportline.'DNS Service')</span></p>
                    <p class="mb-1"><strong>NTDS Service:</strong> <span class="badge $(Get-StatusClass $reportline.'NTDS Service')">$($reportline.'NTDS Service')</span></p>
                    <p class="mb-1"><strong>NetLogon Service:</strong> <span class="badge $(Get-StatusClass $reportline.'NetLogon Service')">$($reportline.'NetLogon Service')</span></p>
                    <p class="mb-1"><strong>OS Free Space:</strong> <span class="badge $(Get-DiskSpaceClass $reportline.'OS Free Space (%)')">$($reportline.'OS Free Space (%)')% ($($reportline.'OS Free Space (GB)') GB)</span></p>
                </div>
            </div>
        </div>
"@
}
$serverhealthhtml += '</div>'


$faultyDCs = $warningDCs + $failedDCs
$faultyDCList = ($allTestedDomainControllers | Where-Object { $_.OverallStatus -ne 'Healthy' } | Select-Object -ExpandProperty Server) -join ", "

# --- Build New Summary Sections ---

# Data Gathering (ensure all data is gathered first)
$gpoCount = Get-GPOSummary
$securitySummary = Get-SecuritySummary
$totalEnterpriseAdmins = (Get-ADGroupMember -Identity "Enterprise Admins" -Recursive).Count
$totalDomainAdmins = (Get-ADGroupMember -Identity "Domain Admins" -Recursive).Count
$totalUsers = (Get-ADUser -Filter *).Count
$enabledUsers = (Get-ADUser -Filter {Enabled -eq $true}).Count
$disabledUsers = (Get-ADUser -Filter {Enabled -eq $false}).Count
$sixtyDays = (Get-Date).AddDays(-60).ToFileTime()
$activeUsers = Get-ADUserCount -filter {lastLogonTimestamp -gt $sixtyDays}
$enterpriseAdmins = (Get-ADGroupMember -Identity "Enterprise Admins" -Recursive | Select-Object -ExpandProperty Name) -join ", "
$domainAdmins = (Get-ADGroupMember -Identity "Domain Admins" -Recursive | Select-Object -ExpandProperty Name) -join ", "

# Determine card colors for DC status
$warnDcCardClass = if ($warningDCs -gt 0) { 'bg-warning text-dark' } else { 'bg-success text-white' }
$failDcCardClass = if ($failedDCs -gt 0) { 'bg-danger text-white' } else { 'bg-success text-white' }

# Determine card colors for admin groups
$eaCardClass = if ($totalEnterpriseAdmins -gt 2) { 'bg-danger text-white' } else { 'bg-success text-white' }
$daCardClass = if ($totalDomainAdmins -gt 5) { 'bg-warning text-dark' } else { 'bg-success text-white' }

# Determine card colors for security cards
$lockedOutCardClass = if ($securitySummary.LockedOutUsers -gt 0) { 'bg-danger text-white' } else { 'bg-success text-white' }
$inactiveCardClass = if ($securitySummary.InactiveUsers -gt 0) { 'bg-warning text-dark' } else { 'bg-success text-white' }
$expiredCardClass = if ($securitySummary.ExpiredPasswords -gt 0) { 'bg-danger text-white' } else { 'bg-success text-white' }


# --- Section 1: Summary Dashboard ---
$summaryDashboardHtml_new = @"
<div class="row d-flex flex-nowrap g-1 mb-2">
            <div class="col">
                <div class="card bg-success text-white text-center h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-check-circle-fill"></i> Healthy DCs</h5>
                        <p class="card-text display-6">$healthyDCs</p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card $($warnDcCardClass) text-center h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-exclamation-triangle-fill"></i> Warning DCs</h5>
                        <p class="card-text display-6">$warningDCs</p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card $($failDcCardClass) text-center h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-x-circle-fill"></i> Failed DCs</h5>
                        <p class="card-text display-6">$failedDCs</p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card $($eaCardClass) text-center h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-people-fill"></i> Enterprise Admins</h5>
                        <p class="card-text">$enterpriseAdmins</p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card $($daCardClass) text-center h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-people-fill"></i> Domain Admins</h5>
                        <p class="card-text">$domainAdmins</p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-white bg-info text-center h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-person-badge-fill"></i> Total Users</h5>
                        <p class="card-text display-6">$totalUsers</p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-white bg-primary text-center h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-journal-bookmark-fill"></i> Total GPOs</h5>
                        <p class="card-text display-6">$gpoCount</p>
                    </div>
                </div>
            </div>
</div>
"@

# --- Section 2: User and Group Information ---
$userAndSecuritySummaryHtml_new = @"
<div class="row d-flex flex-nowrap g-1 mb-2">
    <div class="col">
        <div class="card text-white bg-success text-center h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-person-check-fill"></i> Enabled Users</h5>
                <p class="card-text display-6">$enabledUsers</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-white bg-secondary text-center h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-person-x-fill"></i> Disabled Users</h5>
                <p class="card-text display-6">$disabledUsers</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-white bg-primary text-center h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-person-lines-fill"></i> Active Users (60 days)</h5>
                <p class="card-text display-6">$activeUsers</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card $($lockedOutCardClass) text-center h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-person-fill-lock"></i> Locked Out Accounts</h5>
                <p class="card-text display-6">$($securitySummary.LockedOutUsers)</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card $($inactiveCardClass) text-center h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-person-fill-snooze"></i> Inactive Accounts (90 days)</h5>
                <p class="card-text display-6">$($securitySummary.InactiveUsers)</p>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card $($expiredCardClass) text-center h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-person-fill-exclamation"></i> Expired Passwords</h5>
                <p class="card-text display-6">$($securitySummary.ExpiredPasswords)</p>
            </div>
        </div>
    </div>
</div>
"@



$recommendationsData = @()
foreach ($reportline in $allTestedDomainControllers) {
    if ($reportline.Ping -eq 'Fail') {
        $recommendationsData += [PSCustomObject]@{
            Issue = "Domain Controller Unreachable"
            Server = $reportline.Server
            Cause = "The domain controller is not responding to network requests."
            Action = "Verify network connectivity to the domain controller. Check if the server is powered on and connected to the network."
            Priority = "High"
            Link = "https://learn.microsoft.com/en-us/windows-server/identity/ad-ds/manage/troubleshoot/troubleshooting-domain-controller-deployment"
        }
    }
    if ($reportline.DNS -eq 'Fail') {
        $recommendationsData += [PSCustomObject]@{
            Issue = "DNS Resolution Failed"
            Server = $reportline.Server
            Cause = "DNS name resolution for the domain controller failed."
            Action = "Check DNS server configuration on the domain controller and the client machine."
            Priority = "High"
            Link = "https://learn.microsoft.com/en-us/windows-server/networking/dns/troubleshoot/troubleshoot-dns-server"
        }
    }
    if ($reportline.'DNS Service' -eq 'Fail') {
        $recommendationsData += [PSCustomObject]@{
            Issue = "DNS Service Not Running"
            Server = $reportline.Server
            Cause = "The DNS Server service is not running on the domain controller."
            Action = "Start the DNS Server service on the domain controller and set it to start automatically."
            Priority = "High"
            Link = "https://learn.microsoft.com/en-us/windows-server/networking/dns/troubleshoot/troubleshoot-dns-server"
        }
    }
    if ($reportline.'NTDS Service' -eq 'Fail') {
        $recommendationsData += [PSCustomObject]@{
            Issue = "NTDS Service Not Running"
            Server = $reportline.Server
            Cause = "The Active Directory Domain Services service is not running."
            Action = "Start the Active Directory Domain Services service on the domain controller and set it to start automatically."
            Priority = "High"
            Link = "https://learn.microsoft.com/en-us/windows-server/identity/ad-ds/manage/troubleshoot/troubleshooting-active-directory-related-services"
        }
    }
    if ($reportline.'NetLogon Service' -eq 'Fail') {
        $recommendationsData += [PSCustomObject]@{
            Issue = "NetLogon Service Not Running"
            Server = $reportline.Server
            Cause = "The NetLogon service is not running."
            Action = "Start the NetLogon service on the domain controller and set it to start automatically."
            Priority = "High"
            Link = "https://learn.microsoft.com/en-us/windows-server/identity/ad-ds/manage/troubleshoot/troubleshooting-active-directory-related-services"
        }
    }
    if ($reportline.'OS Free Space (%)' -lt 15) {
        $recommendationsData += [PSCustomObject]@{
            Issue = "Low Disk Space"
            Server = $reportline.Server
            Cause = "The OS drive on the domain controller is low on disk space."
            Action = "Free up disk space on the OS drive."
            Priority = "Medium"
            Link = "https://learn.microsoft.com/en-us/windows-server/administration/performance-tuning/hardware/storage"
        }
    }
    if ($reportline.'Time offset (seconds)' -gt 5) {
        $recommendationsData += [PSCustomObject]@{
            Issue = "Time Synchronization Issue"
            Server = $reportline.Server
            Cause = "The time on the domain controller is not synchronized with the time source."
            Action = "Check the time synchronization configuration on the domain controller."
            Priority = "Medium"
            Link = "https://learn.microsoft.com/en-us/windows-server/networking/windows-time-service/windows-time-service-tools-and-settings"
        }
    }
    foreach ($prop in $reportline.PSObject.Properties) {
        if ($prop.Name -like 'DCDIAG:*' -and $prop.Value -eq 'Failed') {
            $testName = $prop.Name -replace 'DCDIAG: '
            $cause = ""
            $action = ""
            $priority = "Medium"
            $link = "https://learn.microsoft.com/en-us/previous-versions/windows/it-pro/windows-server-2008-R2-and-2008/cc731968(v=ws.10)"

            switch ($testName) {
                "Advertising" {
                    $cause = "The domain controller is not advertising itself as a domain controller or is not registered correctly in DNS."
                    $action = "Verify the Netlogon service is running and check DNS registration. Run 'nltest /dsgetdc:$ForestName' and 'dcdiag /test:dns /s:$($reportline.Server)' for more details."
                    $priority = "High"
                }
                "Connectivity" {
                    $cause = "The domain controller is not reachable or has network connectivity issues."
                    $action = "Check network connectivity to the domain controller (ping, firewall). Ensure the server is online and accessible."
                    $priority = "High"
                }
                "Replications" {
                    $cause = "Active Directory replication is failing or experiencing errors."
                    $action = "Check replication status using 'repadmin /showrepl' and 'dcdiag /test:replications /s:$($reportline.Server)'. Investigate event logs for replication errors."
                    $priority = "High"
                    $link = "https://learn.microsoft.com/en-us/windows-server/identity/ad-ds/manage/troubleshoot/troubleshooting-active-directory-replication-problems"
                }
                "Services" {
                    $cause = "One or more critical Active Directory-related services are not running."
                    $action = "Verify that essential services like KDC, Netlogon, and DNS Server are running on the domain controller. Start any stopped services."
                    $priority = "High"
                }
                "DNS" {
                    $cause = "DNS registration or resolution issues are present on the domain controller."
                    $action = "Run 'dcdiag /test:dns /s:$($reportline.Server)' to diagnose DNS problems. Verify DNS server settings and forwarders."
                    $priority = "High"
                }
                "SysVolCheck" {
                    $cause = "SYSVOL share is not healthy or accessible, potentially impacting Group Policy."
                    $action = "Check the status of the File Replication Service (FRS) or DFS Replication (DFSR) service. Verify SYSVOL and NETLOGON shares are present and accessible."
                    $priority = "High"
                }
                "KccEvent" {
                    $cause = "The Knowledge Consistency Checker (KCC) has encountered errors in building or maintaining the replication topology."
                    $action = "Review Directory Service event logs for KCC errors (Event ID 1925, 1311). Ensure all domain controllers are reachable and healthy."
                    $priority = "Medium"
                }
                default {
                    $cause = "The DCDiag test '$testName' failed on the domain controller."
                    $action = "Run dcdiag /test:$testName on the domain controller to get more information about the failure."
                }
            }

            $recommendationsData += [PSCustomObject]@{
                Issue = "DCDiag Test Failed: $testName"
                Server = $reportline.Server
                Cause = $cause
                Action = $action
                Priority = $priority
                Link = $link
            }
        }
    }
}
$recommendations = '<h1 class="mt-4 mb-3 text-center">Issues and Recommendations</h1>'
if ($recommendationsData.Count -gt 0) {
    $recommendations += '<div class="accordion" id="recommendationsAccordion">'
    $i = 0
    foreach ($rec in $recommendationsData) {
        $priorityClass = switch ($rec.Priority) {
            "High"   { "bg-danger" }
            "Medium" { "bg-warning text-dark" }
            "Low"    { "bg-info" }
            default  { "bg-secondary" }
        }

        $recommendations += @"
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading$i">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse$i" aria-expanded="false" aria-controls="collapse$i">
                    <span class="badge $priorityClass me-2">$($rec.Priority)</span>
                    <strong>$($rec.Issue)</strong> on $($rec.Server)
                </button>
            </h2>
            <div id="collapse$i" class="accordion-collapse collapse" aria-labelledby="heading$i" data-bs-parent="#recommendationsAccordion">
                <div class="accordion-body">
                    <p><strong>Possible Cause:</strong> $($rec.Cause)</p>
                    <p><strong>Recommended Action:</strong> $($rec.Action)</p>
                    <a href="$($rec.Link)" class="btn btn-primary btn-sm" target="_blank">Learn More <i class="bi bi-box-arrow-up-right"></i></a>
                </div>
            </div>
        </div>
"@
        $i++
    }
    $recommendations += '</div>'
}
else {
    $recommendations = '<div class="alert alert-success"><strong>All systems operational:</strong> All domain controllers are healthy.</div>'
}


$diskSpaceChartData = $allTestedDomainControllers | ForEach-Object {
    [PSCustomObject]@{
        server = $_.Server
        ip = $_.'IPv4 Address'
        used = $_.'OS Used Space (%)'
        free = $_.'OS Free Space (%)'
        freeGB = $_.'OS Free Space (GB)'
    }
}




$diskSpaceChartData = $allTestedDomainControllers | ForEach-Object {
    [PSCustomObject]@{
        server = $_.Server
        free = $_.'OS Free Space (%)'
    }
}
$diskSpaceChartJson = $diskSpaceChartData | ConvertTo-Json -Compress




# --- Replication Summary ---
$replicationSummary = Get-ReplicationSummary

$replicationData = @{}
foreach ($item in $replicationSummary) {
    if (-not $replicationData.ContainsKey($item.DSA)) {
        $replicationData[$item.DSA] = @{ SourceDelta = 0; DestDelta = 0 }
    }
    if ($item.Type -eq 'Source') {
        $replicationData[$item.DSA].SourceDelta = Convert-DeltaToMinutes($item.LargestDelta)
    } else {
        $replicationData[$item.DSA].DestDelta = Convert-DeltaToMinutes($item.LargestDelta)
    }
}

$replicationChartLabels = $replicationData.Keys | ConvertTo-Json -Compress
$replicationSourceDeltas = $replicationData.Values.SourceDelta | ConvertTo-Json -Compress
$replicationDestDeltas = $replicationData.Values.DestDelta | ConvertTo-Json -Compress

$replicationSummaryHtml = '<div class="row justify-content-center">'

$sourceSummary = $replicationSummary | Where-Object { $_.Type -eq 'Source' }
$destinationSummary = $replicationSummary | Where-Object { $_.Type -eq 'Destination' }

if ($sourceSummary) {
    # Determine overall status for Source
    $sourceOverallStatus = "Healthy"
    if (($sourceSummary | Where-Object { $_.Fails -gt 0 -or -not [string]::IsNullOrEmpty($_.Error) }).Count -gt 0) {
        $sourceOverallStatus = "Failed"
    }

    # Set card classes based on status
    $cardBgClass = "bg-light text-dark"
    $headerColor = "bg-primary"
    if ($sourceOverallStatus -eq "Failed") {
        $cardBgClass = "bg-danger-subtle text-dark border-danger"
        $headerColor = "bg-danger text-white"
    } else {
        $cardBgClass = "bg-success-subtle text-dark border-success"
        $headerColor = "bg-success text-white"
    }

    $sourceRows = ""
    foreach ($item in $sourceSummary) {
        $rowClass = if ($item.Fails -gt 0 -or -not [string]::IsNullOrEmpty($item.Error)) { "table-danger" } else { "" }
        $sourceRows += "<tr class='$rowClass'><td>$($item.DSA)</td><td>$($item.LargestDelta)</td><td>$($item.Fails)/$($item.Total)</td><td>$($item.Error)</td></tr>"
    }

    $replicationSummaryHtml += @"
        <div class="col-lg-6 mb-3">
            <div class="card h-100 border-3 $cardBgClass">
                <div class="card-header text-center fw-bold $headerColor">
                    <h5 class="mb-0">Source DSAs Replication Status</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>DSA</th>
                                    <th>Largest Delta</th>
                                    <th>Fails/Total</th>
                                    <th>Error</th>
                                </tr>
                            </thead>
                            <tbody>
                                $sourceRows
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
"@
}

if ($destinationSummary) {
    # Determine overall status for Destination
    $destinationOverallStatus = "Healthy"
    if (($destinationSummary | Where-Object { $_.Fails -gt 0 -or -not [string]::IsNullOrEmpty($_.Error) }).Count -gt 0) {
        $destinationOverallStatus = "Failed"
    }

    # Set card classes based on status
    $cardBgClass = "bg-light text-dark"
    $headerColor = "bg-primary"
    if ($destinationOverallStatus -eq "Failed") {
        $cardBgClass = "bg-danger-subtle text-dark border-danger"
        $headerColor = "bg-danger text-white"
    } else {
        $cardBgClass = "bg-success-subtle text-dark border-success"
        $headerColor = "bg-success text-white"
    }

    $destinationRows = ""
    foreach ($item in $destinationSummary) {
        $rowClass = if ($item.Fails -gt 0 -or -not [string]::IsNullOrEmpty($item.Error)) { "table-danger" } else { "" }
        $destinationRows += "<tr class='$rowClass'><td>$($item.DSA)</td><td>$($item.LargestDelta)</td><td>$($item.Fails)/$($item.Total)</td><td>$($item.Error)</td></tr>"
    }

    $replicationSummaryHtml += @"
        <div class="col-lg-6 mb-3">
            <div class="card h-100 border-3 $cardBgClass">
                <div class="card-header text-center fw-bold $headerColor">
                    <h5 class="mb-0">Destination DSAs Replication Status</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>DSA</th>
                                    <th>Largest Delta</th>
                                    <th>Fails/Total</th>
                                    <th>Error</th>
                                </tr>
                            </thead>
                            <tbody>
                                $destinationRows
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
"@
}

$replicationSummaryHtml += '</div>'

$titleRow = @"
<p class="mb-1 text-start"><strong>Domain:</strong> $ForestName</p>
<p class="mb-1 text-start"><strong>Total DCs:</strong> $totalDCs</p>
<p class="mb-1 text-start"><strong>Healthy:</strong> $healthyDCs</p>
<p class="mb-1 text-start"><strong>Faulty:</strong> $faultyDCs</p>
"@
if ($faultyDCs -gt 0) {
    $titleRow += "<p class='mb-1 text-start'><strong>Faulty DCs:</strong> $faultyDCList</p>"
}

# --- Trust Information ---
$trusts = Get-TrustInfo
$trustInfoHtml = ""
if ($trusts) {
    $trustRows = ""
    foreach ($trust in $trusts) {
        $trustRows += "<tr><td>$($trust.Name)</td><td>$($trust.Direction)</td><td>$($trust.Transitive)</td><td>$($trust.TrustType)</td></tr>"
    }

    $trustInfoHtml = @"
<div class="card h-100">
    <div class="card-header text-center fw-bold bg-dark text-white">
        <h5 class="mb-0">Trust Relationships</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Direction</th>
                        <th>Transitive</th>
                        <th>Type</th>
                    </tr>
                </thead>
                <tbody>
                    $trustRows
                </tbody>
            </table>
        </div>
    </div>
</div>
"@
}

# --- System Comments Section ---
$systemComments = @()

# 1. Overall Health
if ($faultyDCs -eq 0) {
    $systemComments += [PSCustomObject]@{ 
        Text = "All domain controllers are in a healthy state."
        Type = "Success"
    }
} else {
    $systemComments += [PSCustomObject]@{ 
        Text = "There are health warnings or failures on $faultyDCs domain controller(s). Please review the 'Issues and Recommendations' section for details."
        Type = "Warning"
    }
}

# 2. Administrator Count Comments
if ($totalEnterpriseAdmins -gt 2) {
    $systemComments += [PSCustomObject]@{ 
        Text = "There are $totalEnterpriseAdmins Enterprise Admins. This is higher than the recommended limit of 2 and poses a potential security risk."
        Type = "Warning"
    }
} else {
    $systemComments += [PSCustomObject]@{ 
        Text = "The number of Enterprise Admins is $totalEnterpriseAdmins, which is within the recommended security limit of 2."
        Type = "Success"
    }
}
if ($totalDomainAdmins -gt 5) {
    $systemComments += [PSCustomObject]@{ 
        Text = "There are $totalDomainAdmins Domain Admins. This is higher than the recommended limit of 5."
        Type = "Warning"
    }
} else {
    $systemComments += [PSCustomObject]@{ 
        Text = "The number of Domain Admins is $totalDomainAdmins, which is within the recommended limit of 5."
        Type = "Success"
    }
}

# 4. Security Summary Comments
if ($securitySummary.LockedOutUsers -gt 0) {
    $systemComments += [PSCustomObject]@{ 
        Text = "There are $($securitySummary.LockedOutUsers) locked out user account(s). These should be investigated."
        Type = "Warning"
    }
}
if ($securitySummary.InactiveUsers -gt 0) {
    $systemComments += [PSCustomObject]@{ 
        Text = "Found $($securitySummary.InactiveUsers) user account(s) that have been inactive for over 90 days. Consider disabling them."
        Type = "Warning"
    }
}
if ($securitySummary.ExpiredPasswords -gt 0) {
    $systemComments += [PSCustomObject]@{ 
        Text = "There are $($securitySummary.ExpiredPasswords) user account(s) with expired passwords."
        Type = "Warning"
    }
}

# 6. Replication Summary Comment
$replicationFailures = ($replicationSummary | Where-Object { $_.Fails -gt 0 -or -not [string]::IsNullOrEmpty($_.Error) }).Count
if ($replicationFailures -gt 0) {
    $systemComments += [PSCustomObject]@{ 
        Text = "Active Directory replication is experiencing $replicationFailures failure(s). Review the 'Replication Summary' section for details."
        Type = "Warning"
    }
} else {
    $systemComments += [PSCustomObject]@{ 
        Text = "Active Directory replication is healthy."
        Type = "Success"
    }
}

# 7. DCDiag Comment
$dcdiagFailureCount = ($allTestedDomainControllers | ForEach-Object { $_.PSObject.Properties } | Where-Object { $_.Name -like 'DCDIAG:*' -and $_.Value -eq 'Failed' } | Measure-Object).Count
if ($dcdiagFailureCount -gt 0) {
     $systemComments += [PSCustomObject]@{ 
        Text = "One or more DCDiag tests failed. Review the 'DCDiag Results' table for details."
        Type = "Warning"
    }
}

# Build the HTML list for domain-wide comments
$systemCommentsListHtml = ""
foreach ($comment in $systemComments) {
    $icon = ""
    $colorClass = ""
    if ($comment.Type -eq "Success") {
        $icon = '<i class="bi bi-check-circle-fill text-success"></i>'
        $colorClass = "text-dark"
    } else {
        $icon = '<i class="bi bi-exclamation-triangle-fill text-danger"></i>'
        $colorClass = "text-danger"
    }
    $systemCommentsListHtml += "<li class='mb-2 $colorClass'>$icon $($comment.Text)</li>"
}

# --- Per-Server Comments ---
$perServerCommentsHtml = ""
foreach ($dc in $allTestedDomainControllers) {
    $fsmoRolesText = if ($dc.'Operation Master Roles'.Count -gt 0) {
        "This server holds the following FSMO roles: $($dc.'Operation Master Roles' -join ', ')"
    } else {
        "This server does not hold any FSMO roles."
    }

    $diskSpaceText = "OS disk space is healthy at $($dc.'OS Free Space (%)')% free."
    if ($dc.'OS Free Space (%)' -lt 15) {
        $diskSpaceText = "<span class='text-danger'>OS disk space is critically low at $($dc.'OS Free Space (%)')% free. Action is required.</span>"
    } elseif ($dc.'OS Free Space (%)' -lt 30) {
        $diskSpaceText = "<span class='text-warning'>OS disk space is getting low at $($dc.'OS Free Space (%)')% free. Please monitor.</span>"
    }

    $perServerCommentsHtml += @"
        <h5 class="mt-3">Server: $($dc.Server)</h5>
        <ul class="list-unstyled ms-3">
            <li>The server is reachable at IP address $($dc.'IPv4 Address') and is running $($dc.'OS Version').</li>
            <li>It has been online for $(Convert-HoursToDaysHours $dc.'Uptime (hours)').</li>
            <li>$diskSpaceText</li>
            <li>$fsmoRolesText</li>
            <li>The health check for this server was completed in $($dc.'Processing Time (seconds)') seconds.</li>
        </ul>
"@
}


$systemCommentsHtml = @"
<div class="mt-4">
    <h3 class="mb-3">System Comments</h3>
"@
if ($systemCommentsListHtml -match "text-danger") {
    $systemCommentsHtml += "<p class='alert alert-danger'><strong>Attention: The following critical and alarming conditions were detected:</strong></p>"
}
$systemCommentsHtml += @"
    <ul class="list-unstyled">
        $systemCommentsListHtml
    </ul>
    <hr>
    <h4 class="mb-3">Per-Server Status</h4>
    $perServerCommentsHtml
</div>
"@

# --- Recommendations Section HTML ---
$recommendationsSectionHtml = ""
if ($recommendationsData.Count -gt 0) {
    $recommendationsSectionHtml = '<div class="group-outline group-red">' + $recommendations + '</div>'
} else {
    $recommendationsSectionHtml = '<div class="mt-4">' + $recommendations + '</div>'
}

# --- Footer HTML ---
$footerHtml = @"
<footer class="text-center mt-5 p-3 bg-light">
    $([char]0x00A9) $CopyrightYear Developed by <a href="$DeveloperUrl" target="_blank">$DeveloperName</a>. $CopyrightMessage | Version: v18.10
</footer>
"@

# --- Executive Summary ---
$overallHealthStatus = "Healthy"
$overallHealthClass = "bg-success"
$overallHealthIcon = "bi-check-circle-fill"
if ($failedDCs -gt 0) {
    $overallHealthStatus = "Critical"
    $overallHealthClass = "bg-danger"
    $overallHealthIcon = "bi-x-octagon-fill"
} elseif ($warningDCs -gt 0) {
    $overallHealthStatus = "Warning"
    $overallHealthClass = "bg-warning text-dark"
    $overallHealthIcon = "bi-exclamation-triangle-fill"
}

$keyFindings = @()
if ($failedDCs -gt 0) {
    $keyFindings += "$failedDCs domain controller(s) have critical failures."
}
if ($warningDCs -gt 0) {
    $keyFindings += "$warningDCs domain controller(s) have warnings."
}
if ($securitySummary.LockedOutUsers -gt 0) {
    $keyFindings += "$($securitySummary.LockedOutUsers) user account(s) are locked out."
}
$replicationFailures = ($replicationSummary | Where-Object { $_.Fails -gt 0 -or -not [string]::IsNullOrEmpty($_.Error) }).Count
if ($replicationFailures -gt 0) {
    $keyFindings += "Active Directory replication is experiencing $replicationFailures failure(s)."
}


$keyFindingsList = ""
if ($keyFindings.Count -eq 0) {
    $keyFindingsList = "<li class=`"list-group-item bg-light`"><i class=`"bi bi-check-circle-fill text-success`"></i> No critical issues found. The system appears to be healthy.</li>"
} else {
    foreach ($finding in $keyFindings) {
        $keyFindingsList += "<li class=`"list-group-item bg-light`"><i class=`"bi bi-caret-right-fill`"></i> $finding</li>"
    }
}

$executiveSummaryHtml = @"
<div class="card mb-4 border-0">
    <div class="card-header text-white $overallHealthClass">
        <h2 class="mb-0"><i class="bi $overallHealthIcon"></i> Executive Summary: <span class="fw-bold">$overallHealthStatus</span></h2>
    </div>
    <div class="card-body bg-light">
        <h4 class="card-title">Key Findings:</h4>
        <ul class="list-group list-group-flush">
        $keyFindingsList
        </ul>
    </div>
</div>
"@




$htmlreport = $htmlhead_part1 + 
@"
<!-- Top Row: 5-Column Layout -->
<div class="row d-flex align-items-center justify-content-center mb-4 g-1">
    <!-- Column 1: Text Summary -->
    <div class="col-md-3">
    $($titleRow)
    $($htmlhead_part2)
    </div>
    <!-- Column 2: Empty Placeholder -->
    <div class="col-md-3"></div>
    <!-- Column 3: Replication Chart -->
    <div class="col-md-2" style="height: 180px;">
        <canvas id="replicationChart"></canvas>
    </div>
    <!-- Column 4: DC Health Chart -->
    <div class="col-md-2" style="height: 180px;">
        <canvas id="dcHealthChart"></canvas>
    </div>
    <!-- Column 5: OS Free Space Chart -->
    <div class="col-md-2" style="height: 180px;">
        <canvas id="osFreeSpaceChart"></canvas>
    </div>
</div>

"@ + $executiveSummaryHtml + '<div class="mb-3"></div>' + 

              '<div class="group-outline group-blue">' + '<h1 class="mt-4 mb-3 text-center">Summary Dashboard</h1>' + $summaryDashboardHtml_new + '</div>' + 

              '<div class="group-outline group-orange">' + '<h1 class="mt-4 mb-3 text-center">Server Informations</h1>' + $serverhealthhtml + '</div>' + 

              '<div class="group-outline group-blue">' + '<h1 class="mt-4 mb-3 text-center">Replication Summary</h1>' + $replicationSummaryHtml + '</div>' + 

              '<div class="group-outline group-green">' + '<h1 class="mt-4 mb-3 text-center">User and Group Information</h1>' + $userAndSecuritySummaryHtml_new + '</div>' + 

              '<div class="group-outline group-info">' + '<h1 class="mt-4 mb-3 text-center">Trust Relationships</h1>' + $trustInfoHtml + '</div>' + 

              '<div class="group-outline group-purple">' + $dcdiagTable + '</div>' + 

              $recommendationsSectionHtml + 

              $systemCommentsHtml + 

              "<img src=""$AppLogoPath"" alt=""Watermark"" class=""watermark"">" +


              '<script>' + 
            '// PASTE THE ENTIRE CONTENT OF BOOTSTRAP.BUNDLE.MIN.JS LIBRARY (e.g., from https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js) HERE' + 
        '</script>' + 

              '<script src="https://code.jquery.com/jquery-3.7.0.js"></script>' + 

              '<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>' + 

              '<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>' + 

              @"
<script>
    `$(document).ready(function() { 
        console.log('Document ready. Initializing scripts.');
        
        try {
            console.log('Initializing DataTable.');
            `$("#dcdiag-table").DataTable(); 
        } catch (e) {
            console.error('DataTable initialization failed:', e);
        }

        try {
            console.log('Attempting to render chart.');
            const ctx = document.getElementById('dcHealthChart');
            if (ctx) {
                console.log('Canvas element #dcHealthChart found.');
                Chart.register(ChartDataLabels);

                // Data is now directly injected from PowerShell
                const chartData = [$healthyDCs, $warningDCs, $failedDCs];
                console.log('Chart Data:', chartData);

                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Healthy', 'Warning', 'Failed'],
                        datasets: [{
                            label: 'DC Health Status',
                            data: chartData,
                            backgroundColor: [
                                'rgb(25, 135, 84)', // Success Green
                                'rgb(255, 193, 7)',  // Warning Yellow
                                'rgb(220, 53, 69)'   // Danger Red
                            ],
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'DC Health Status'
                            },
                            datalabels: {
                                color: '#fff',
                                textAlign: 'center',
                                font: {
                                    weight: 'bold'
                                },
                                formatter: function(value, ctx) {
                                    const datapoints = ctx.chart.data.datasets[0].data;
                                    const total = datapoints.reduce(function(total, datapoint) { return total + datapoint; }, 0);
                                    const percentage = value / total * 100;
                                    const label = ctx.chart.data.labels[ctx.dataIndex];
                                    if (percentage < 5) return ''; // Hide label if too small
                                    return label + '\n' + percentage.toFixed(0) + '%';
                                }
                            }
                        }
                    }
                });
                console.log('Chart object created.');

                // Render OS Free Space Chart
                const osFreeSpaceCtx = document.getElementById('osFreeSpaceChart');
                if (osFreeSpaceCtx) {
                    console.log('Canvas element #osFreeSpaceChart found.');
                    const osFreeSpaceData = $diskSpaceChartJson;
                    console.log('OS Free Space Chart Data:', osFreeSpaceData);

                    new Chart(osFreeSpaceCtx, {
                        type: 'bar',
                        data: {
                            labels: osFreeSpaceData.map(d => d.server.split('.')[0]),
                            datasets: [{
                                label: 'Free Space (%)',
                                data: osFreeSpaceData.map(d => d.free),
                                backgroundColor: osFreeSpaceData.map(d => {
                                    if (d.free < 15) return 'rgb(220, 53, 69)'; // Red
                                    if (d.free < 30) return 'rgb(255, 193, 7)';  // Yellow
                                    return 'rgb(25, 135, 84)'; // Green
                                }),
                                categoryPercentage: 0.9,
                                barPercentage: 0.9
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                title: { 
                                    display: true,
                                    text: 'OS Free Space (%)'
                                 }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    max: 100
                                }
                            }
                        }
                    });
                    console.log('OS Free Space chart object created.');
                }

                // Render Replication Chart
                const replCtx = document.getElementById('replicationChart');
                if(replCtx) {
                    new Chart(replCtx, {
                        type: 'bar',
                        data: {
                            labels: $replicationChartLabels,
                            datasets: [
                                {
                                    label: 'Source Delta (Mins)',
                                    data: $replicationSourceDeltas,
                                    backgroundColor: 'rgb(0, 123, 255)' // Bootstrap Primary Blue
                                },
                                {
                                    label: 'Destination Delta (Mins)',
                                    data: $replicationDestDeltas,
                                    backgroundColor: 'rgb(108, 117, 125)' // Bootstrap Secondary Gray
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Replication Delta (Mins)'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                    console.log('Replication chart object created.');
                }

            } else {
                console.error('Canvas element #dcHealthChart not found!');
            }
        } catch (e) {
            console.error('An error occurred during chart rendering:', e);
        }
    });
</script>
"@ + 

              $footerHtml +

              '</div></body></html>'

if ($ReportFile) {
    if (-not [string]::IsNullOrEmpty($OutputReportPath)) {
        $htmlreport | Out-File $OutputReportPath -Encoding UTF8
    } else {
        $htmlreport | Out-File $reportFileName -Encoding UTF8
    }
    Write-Host "Report saved to: $reportFileName" -ForegroundColor Green
}

if ($SendEmail) {
    try {
        # Send email with both inline HTML and attachment
        $htmlreport | Out-File $reportFileName -Encoding UTF8
        Send-MailMessage @smtpsettings -Body $htmlreport -BodyAsHtml -Attachments $reportFileName -Encoding ([System.Text.Encoding]::UTF8) -ErrorAction Stop
        Write-Host "Email sent successfully." -ForegroundColor Green
    }
    catch {
        Write-Host "Failed to send email. Error: $_ " -ForegroundColor Red
    }
}

# --- Generate Summary Log Directly ---
try {
    $summaryAction = "AD_HEALTH_CHECK"
    $summaryTargetUser = if (-not [string]::IsNullOrEmpty($DomainName)) { $DomainName } else { $ForestName }

    $summaryStatus = "SUCCESS"
    if ($failedDCs -gt 0) {
        $summaryStatus = "FAILED"
    } elseif ($warningDCs -gt 0) {
        $summaryStatus = "WARNING"
    }

    $summaryMessageParts = @()
    $summaryMessageParts += "Total DCs: $($totalDCs)"
    if ($healthyDCs -gt 0) { $summaryMessageParts += "Healthy DCs: $($healthyDCs)" }
    if ($warningDCs -gt 0) { $summaryMessageParts += "Warning DCs: $($warningDCs)" }
    if ($failedDCs -gt 0) { $summaryMessageParts += "Failed DCs: $($failedDCs)" }

    $summaryMessage = "AD Health Check Completed. " + ($summaryMessageParts -join ', ')

    if ([string]::IsNullOrEmpty($ExecutedBy)) { $ExecutedBy = "UNKNOWN" }

    $logEntry = "[$(Get-Date -Format 'yyyy-MM-dd hh:mm:ss tt')] Action: $summaryAction | TargetUser: $summaryTargetUser | Status: $summaryStatus | Message: $summaryMessage | ExecutedBy: $ExecutedBy"

    # Ensure directory exists
    $logDir = Split-Path -Path $logFile -Parent
    if (-not (Test-Path -Path $logDir -PathType Container)) {
        New-Item -ItemType Directory -Path $logDir -Force -ErrorAction Stop | Out-Null
    }

    # Write directly to the file
    Add-Content -Path $logFile -Value $logEntry -ErrorAction Stop
} catch {
    Write-Output "ERROR: Failed to generate summary log. Exception: $($_.Exception.Message)"
}
$summaryAction = "AD_HEALTH_CHECK"
$summaryTargetUser = if (-not [string]::IsNullOrEmpty($DomainName)) { $DomainName } else { $ForestName }

$summaryStatus = "SUCCESS"
if ($failedDCs -gt 0) {
    $summaryStatus = "FAILED"
} elseif ($warningDCs -gt 0) {
    $summaryStatus = "WARNING"
}

$summaryMessageParts = @()
$summaryMessageParts += "Total DCs: $($totalDCs)"
if ($healthyDCs -gt 0) { $summaryMessageParts += "Healthy DCs: $($healthyDCs)" }
if ($warningDCs -gt 0) { $summaryMessageParts += "Warning DCs: $($warningDCs)" }
if ($failedDCs -gt 0) { $summaryMessageParts += "Failed DCs: $($failedDCs)" }

$summaryMessage = "AD Health Check Completed. " + ($summaryMessageParts -join ', ')

Write-Log -Action $summaryAction -TargetUser $summaryTargetUser -Status $summaryStatus -Message $summaryMessage -ExecutedByLog $ExecutedBy

exit 0