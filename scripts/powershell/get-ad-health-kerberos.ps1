<#
    .SYNOPSIS
    Get-ADHealth-Kerberos.ps1 — Remote DC health check via WinRM Kerberos.

    .DESCRIPTION
    Connects to a domain controller via WinRM using Kerberos auth (cached ticket),
    runs AD cmdlets and system diagnostics on the DC, generates an HTML report locally.

    .PARAMETER TargetDC
    Hostname of the domain controller to connect to.

    .PARAMETER OutputReportPath
    Full path to write the HTML report.

    .PARAMETER AppName
    Application name for the report header.

    .PARAMETER AppLogoPath
    Path/URL to a logo image.

    .PARAMETER CopyrightYear
    Year for the copyright notice.

    .PARAMETER DeveloperName
    Name of the developer/organization.

    .PARAMETER DeveloperUrl
    URL of the developer/organization.

    .PARAMETER CopyrightMessage
    Custom copyright message.

    .PARAMETER ExecutedBy
    Username who initiated the check.
#>

[CmdletBinding()]Param(
    [Parameter(Mandatory = $true)]
    [string]$TargetDC,

    [Parameter(Mandatory = $true)]
    [string]$OutputReportPath,

    [Parameter(Mandatory = $false)]
    [string]$AppName = "AD Health Monitor",

    [Parameter(Mandatory = $false)]
    [string]$AppLogoPath = "",

    [Parameter(Mandatory = $false)]
    [string]$CopyrightYear = (Get-Date).Year,

    [Parameter(Mandatory = $false)]
    [string]$DeveloperName = "RAKIBUZZAMAN",

    [Parameter(Mandatory = $false)]
    [string]$DeveloperUrl = "https://engr-rakib.github.io/web/",

    [Parameter(Mandatory = $false)]
    [string]$CopyrightMessage = "© $CopyrightYear All Rights Reserved.",

    [Parameter(Mandatory = $false)]
    [string]$ExecutedBy = "UNKNOWN"
)

$now = Get-Date
$reportTime = $now.ToString("yyyy-MM-dd hh:mm:ss tt")

$htmlHead = @"
<html>
<head>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
BODY { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 10pt; }
H1 { font-size: 20px; }
H2 { font-size: 16px; }
H3 { font-size: 14px; }
.watermark { position: fixed; bottom: 20px; right: 20px; opacity: 0.1; z-index: 9999; pointer-events: none; width: 150px; }
.group-outline { border: 2px solid; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
.group-blue { border-color: #007bff; }
.group-green { border-color: #28a745; }
.group-orange { border-color: #f07008; }
.group-purple { border-color: #6f42c1; }
.group-red { border-color: #dc3545; }
.group-info { border-color: #119db9; }
@media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>
<div class="container-fluid">
<div class="row align-items-center mb-3">
    <div class="col-auto"><img src="$AppLogoPath" alt="Logo" style="height: 50px;"></div>
    <div class="col">
        <h1 class="my-0">$AppName - Domain Controller Health Check Report</h1>
    </div>
</div>
"@

try {
    # PSWSMan required for WinRM on Linux
    $wsmanLoaded = $false
    try { Import-Module PSWSMan -ErrorAction Stop; $wsmanLoaded = $true } catch { }

    Write-Host "Connecting to $TargetDC via WinRM Kerberos..."

    $session = New-PSSession -ComputerName $TargetDC -Authentication Kerberos -ErrorAction Stop
    Write-Host "Session established."

    $forestData = Invoke-Command -Session $session -ScriptBlock {
        Import-Module ActiveDirectory -ErrorAction Stop

        $forest = Get-ADForest
        $domain = Get-ADDomain

        $dcs = Get-ADDomainController -Filter * | Sort-Object HostName

        $lockedOut = @(Search-ADAccount -LockedOut -ErrorAction SilentlyContinue).Count
        $inactive = @(Search-ADAccount -AccountInactive -TimeSpan 90.00:00:00 -UsersOnly -ErrorAction SilentlyContinue).Count
        $expired = @(Search-ADAccount -PasswordExpired -ErrorAction SilentlyContinue).Count

        $eaCount = @(Get-ADGroupMember -Identity "Enterprise Admins" -Recursive -ErrorAction SilentlyContinue).Count
        $daCount = @(Get-ADGroupMember -Identity "Domain Admins" -Recursive -ErrorAction SilentlyContinue).Count

        $eaMembers = @(Get-ADGroupMember -Identity "Enterprise Admins" -Recursive -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Name) -join ", "
        $daMembers = @(Get-ADGroupMember -Identity "Domain Admins" -Recursive -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Name) -join ", "

        $totalUsers = @(Get-ADUser -Filter * -ErrorAction SilentlyContinue).Count
        $enabledUsers = @(Get-ADUser -Filter {Enabled -eq $true} -ErrorAction SilentlyContinue).Count
        $disabledUsers = @(Get-ADUser -Filter {Enabled -eq $false} -ErrorAction SilentlyContinue).Count

        $sixtyDays = (Get-Date).AddDays(-60).ToFileTime()
        $activeUsers = @(Get-ADUser -Filter {lastLogonTimestamp -gt $sixtyDays} -ErrorAction SilentlyContinue).Count

        try { $gpoCount = @(Get-GPO -All -ErrorAction Stop).Count } catch { $gpoCount = "Error" }

        try { $trusts = Get-ADTrust -Filter * -ErrorAction Stop } catch { $trusts = $null }

        $dcDetails = @()
        foreach ($dc in $dcs) {
            $name = $dc.HostName
            $ping = if (Test-Connection $name -Count 1 -Quiet) { "Success" } else { "Fail" }

            $dcdiag = @{}
            if ($ping -eq "Success") {
                $params = @(
                    "/s:$name", "/test:Connectivity", "/test:Advertising",
                    "/test:FrsEvent", "/test:DFSREvent", "/test:SysVolCheck",
                    "/test:KccEvent", "/test:KnowsOfRoleHolders", "/test:MachineAccount",
                    "/test:NCSecDesc", "/test:NetLogons", "/test:ObjectsReplicated",
                    "/test:Replications", "/test:RidManager", "/test:Services",
                    "/test:VerifyReferences", "/test:CheckSDRefDom",
                    "/test:CrossRefValidation", "/test:LocatorCheck", "/test:Intersite",
                    "/test:FSMOCheck"
                )
                $diagOut = Dcdiag.exe @params 2>&1
                $diagExit = $LASTEXITCODE

                foreach ($line in $diagOut) {
                    if ($line -match "Starting test:\s*(.+)") { $t = $matches[1] }
                    if ($line -match "passed test|failed test") {
                        $status = if ($line -match "passed test") { "Passed" } else { "Failed" }
                        $dcdiag[$t] = $status
                    }
                }
                if ($diagExit -ne 0 -or $diagOut -match "failed to connect") {
                    foreach ($p in @("Connectivity","Advertising","FrsEvent","DFSREvent","SysVolCheck","KccEvent","KnowsOfRoleHolders","MachineAccount","NCSecDesc","NetLogons","ObjectsReplicated","Replications","RidManager","Services","VerifyReferences","CheckSDRefDom","CrossRefValidation","LocatorCheck","Intersite","FSMOCheck")) {
                        $dcdiag[$p] = "Failed"
                    }
                }
            }

            $uptime = "Fail"
            $freeSpace = "Fail"
            $freeSpaceGB = "Fail"
            $timeDiff = "Fail"
            $dnsService = "Fail"
            $ntdsService = "Fail"
            $netlogonService = "Fail"
            $backupStatus = "Not Available"
            $criticalEvents = 0
            $dcEvents = @()

            if ($ping -eq "Success") {
                try {
                    $w32os = Get-CimInstance -ClassName Win32_OperatingSystem -ComputerName $name -ErrorAction SilentlyContinue
                    if ($w32os) {
                        $ts = (Get-Date) - $w32os.LastBootUpTime
                        $uptime = "{0:0}" -f $ts.TotalHours
                    }
                } catch { $uptime = "CIM Failure" }

                try {
                    $drive = (Get-CimInstance -ClassName Win32_OperatingSystem -ComputerName $name -ErrorAction Stop).SystemDrive
                    $disk = Get-CimInstance -ClassName Win32_LogicalDisk -ComputerName $name -Filter "DeviceID='$drive'" -ErrorAction Stop
                    $freeSpace = [math]::Round($disk.FreeSpace / $disk.Size * 100)
                    $freeSpaceGB = [math]::Round($disk.FreeSpace / 1GB, 2)
                } catch { $freeSpace = "CIM Failure"; $freeSpaceGB = "CIM Failure" }

                try {
                    $diff = (& w32tm /stripchart /computer:$name /samples:1 /dataonly)[-1].Trim("s") -split ',\s*'
                    $timeDiff = [Math]::Round([double]$diff[1], 1)
                } catch { $timeDiff = "Fail" }

                try {
                    $svcs = Get-Service -ComputerName $name -Name DNS, NTDS, netlogon -ErrorAction SilentlyContinue
                    $dnsService = if ($svcs[0].Status -eq 'Running') { "Success" } else { "Fail" }
                    $ntdsService = if ($svcs[1].Status -eq 'Running') { "Success" } else { "Fail" }
                    $netlogonService = if ($svcs[2].Status -eq 'Running') { "Success" } else { "Fail" }
                } catch { }

                try {
                    $backupResult = Invoke-Command -ComputerName $name -ScriptBlock {
                        if (Get-Module -ListAvailable -Name WindowsServerBackup) {
                            try {
                                $wb = Get-WBSummary -ErrorAction Stop
                                if ($wb -and $wb.LastBackupTime) { $wb.LastBackupTime.ToString("yyyy-MM-dd HH:mm:ss") } else { "No backup record" }
                            } catch { "Not Available" }
                        } else { "Not Available" }
                    } -ErrorAction SilentlyContinue
                    if ($backupResult) {
                        if ($backupResult -is [string]) { $backupStatus = $backupResult }
                        elseif ($backupResult.value) { $backupStatus = $backupResult.value }
                        else { $backupStatus = "$($backupResult)" }
                    }
                } catch { $backupStatus = "Error" }

                try {
                    $sysEvts = @(Get-WinEvent -ComputerName $name -LogName System -MaxEvents 10 -FilterXPath "*[System[(Level=1 or Level=2) and TimeCreated[timediff(@SystemTime) <= 86400000]]]" -ErrorAction SilentlyContinue)
                    $dsEvts = @(Get-WinEvent -ComputerName $name -LogName "Directory Service" -MaxEvents 10 -FilterXPath "*[System[(Level=1 or Level=2) and TimeCreated[timediff(@SystemTime) <= 86400000]]]" -ErrorAction SilentlyContinue)
                    $criticalEvents = $sysEvts.Count + $dsEvts.Count
                    foreach ($e in $sysEvts) {
                        $m = if ($e.Message) { $e.Message.Substring(0, [Math]::Min(200, $e.Message.Length)).Replace("`r`n", " ").Replace("`n", " ") } else { "" }
                        $dcEvents += [PSCustomObject]@{ Log = "System"; Id = $e.Id; Time = $e.TimeCreated.ToString("yyyy-MM-dd HH:mm:ss"); Message = $m }
                    }
                    foreach ($e in $dsEvts) {
                        $m = if ($e.Message) { $e.Message.Substring(0, [Math]::Min(200, $e.Message.Length)).Replace("`r`n", " ").Replace("`n", " ") } else { "" }
                        $dcEvents += [PSCustomObject]@{ Log = "DS"; Id = $e.Id; Time = $e.TimeCreated.ToString("yyyy-MM-dd HH:mm:ss"); Message = $m }
                    }
                } catch { $criticalEvents = 0 }
            }

            $dcDetails += [PSCustomObject]@{
                HostName = $name.ToLower()
                Site = $dc.Site
                OSVersion = $dc.OperatingSystem
                IPv4Address = $dc.IPv4Address
                OperationMasterRoles = $dc.OperationMasterRoles -join ", "
                Ping = $ping
                UptimeHours = $uptime
                FreeSpacePct = $freeSpace
                FreeSpaceGB = $freeSpaceGB
                TimeOffset = $timeDiff
                DNSService = $dnsService
                NTDSService = $ntdsService
                NetlogonService = $netlogonService
                LastBackup = $backupStatus
                CriticalEvents = $criticalEvents
                DCDiag = $dcdiag
                SystemEvents = $dcEvents
            }
        }

        $replSummary = @()
        $repadminOut = repadmin /replsummary
        $src = $false; $dst = $false
        foreach ($line in $repadminOut) {
            if ($line -match "Source DSA") { $src = $true; $dst = $false; continue }
            if ($line -match "Destination DSA") { $dst = $true; $src = $false; continue }
            if (($src -or $dst) -and $line -match "\w") {
                $parts = $line.Split(' ',[StringSplitOptions]::RemoveEmptyEntries)
                if ($parts.Count -ge 5) {
                    $replSummary += [PSCustomObject]@{
                        Type = if ($src) { "Source" } else { "Destination" }
                        DSA = $parts[0]
                        LargestDelta = $parts[1]
                        Fails = $parts[2]
                        Total = $parts[4]
                        Error = if ($parts.Count -gt 6) { $parts[6..($parts.Count-1)] -join ' ' } else { '' }
                    }
                }
            }
        }

        Write-Output "---DATA_START---"
        $payload = @{
            ForestName = $forest.Name
            ForestMode = $forest.ForestMode
            DomainName = $domain.Name
            DomainMode = $domain.DomainMode
            SchemaMaster = $forest.SchemaMaster
            DomainNamingMaster = $forest.DomainNamingMaster
            PDCEmulator = $domain.PDCEmulator
            RIDMaster = $domain.RIDMaster
            InfrastructureMaster = $domain.InfrastructureMaster
            TotalDCs = @($dcs).Count
            DCs = $dcDetails
            LockedOutUsers = $lockedOut
            InactiveUsers = $inactive
            ExpiredPasswords = $expired
            EnterpriseAdmins = $eaCount
            EnterpriseAdminsList = $eaMembers
            DomainAdmins = $daCount
            DomainAdminsList = $daMembers
            TotalUsers = $totalUsers
            EnabledUsers = $enabledUsers
            DisabledUsers = $disabledUsers
            ActiveUsers = $activeUsers
            GpoCount = $gpoCount
            Trusts = $trusts
            ReplicationSummary = $replSummary
        }
        $payload | ConvertTo-Json -Compress -Depth 5
        Write-Output "---DATA_END---"
    }

    Remove-PSSession $session
    Write-Host "Remote data collection complete."

    $rawOutput = "$forestData"
    $dataStart = $rawOutput.IndexOf("---DATA_START---")
    $dataEnd = $rawOutput.IndexOf("---DATA_END---")
    if ($dataStart -eq -1 -or $dataEnd -eq -1) { throw "Could not find data markers in output." }
    $dataStart += "---DATA_START---".Length
    $jsonStr = $rawOutput.Substring($dataStart, $dataEnd - $dataStart).Trim()
    $h = $jsonStr | ConvertFrom-Json

    $allDCs = @($h.DCs)
    $totalDCs = $h.TotalDCs
    $healthyDCs = 0; $warningDCs = 0; $failedDCs = 0

    foreach ($dc in $allDCs) {
        $failCount = @($dc.DCDiag.PSObject.Properties | Where-Object { $_.Value -eq "Failed" }).Count
        if ($failCount -gt 0) { $failedDCs++ }
        elseif (0 -gt 0) { $warningDCs++ }
        else { $healthyDCs++ }
    }

    $faultyDCs = $warningDCs + $failedDCs
    $faultyDCList = ($allDCs | Where-Object { @($_.DCDiag.PSObject.Properties | Where-Object { $_.Value -eq "Failed" }).Count -gt 0 } | Select-Object -ExpandProperty HostName) -join ", "

    $eaCardClass = if ($h.EnterpriseAdmins -gt 2) { 'bg-danger text-white' } else { 'bg-success text-white' }
    $daCardClass = if ($h.DomainAdmins -gt 5) { 'bg-warning text-dark' } else { 'bg-success text-white' }
    $lockedOutCardClass = if ($h.LockedOutUsers -gt 0) { 'bg-danger text-white' } else { 'bg-success text-white' }
    $inactiveCardClass = if ($h.InactiveUsers -gt 0) { 'bg-warning text-dark' } else { 'bg-success text-white' }
    $expiredCardClass = if ($h.ExpiredPasswords -gt 0) { 'bg-danger text-white' } else { 'bg-success text-white' }

    $titleRow = @"
<p class="mb-1 text-start"><strong>Domain:</strong> $($h.DomainName)</p>
<p class="mb-1 text-start"><strong>Total DCs:</strong> $totalDCs</p>
<p class="mb-1 text-start"><strong>Healthy:</strong> $healthyDCs</p>
<p class="mb-1 text-start"><strong>Faulty:</strong> $faultyDCs</p>
$(if ($faultyDCs -gt 0) { "<p class='mb-1 text-start'><strong>Faulty DCs:</strong> $faultyDCList</p>" })
<p class="mb-1 text-start"><strong>Generated:</strong> $reportTime</p>
<p class="mb-1 text-start"><strong>Generated by:</strong> $DeveloperName</p>
"@

    $summaryDashboard = @"
<div class="group-outline group-blue">
<h1 class="mt-4 mb-3 text-center">Summary Dashboard</h1>
<div class="row d-flex flex-nowrap g-1 mb-2">
    <div class="col"><div class="card bg-success text-white text-center h-100"><div class="card-body"><h5 class="card-title">Healthy DCs</h5><p class="card-text display-6">$healthyDCs</p></div></div></div>
    <div class="col"><div class="card bg-warning text-dark text-center h-100"><div class="card-body"><h5 class="card-title">Warning DCs</h5><p class="card-text display-6">$warningDCs</p></div></div></div>
    <div class="col"><div class="card bg-danger text-white text-center h-100"><div class="card-body"><h5 class="card-title">Failed DCs</h5><p class="card-text display-6">$failedDCs</p></div></div></div>
    <div class="col"><div class="card $eaCardClass text-center h-100"><div class="card-body"><h5 class="card-title">Enterprise Admins</h5><p class="card-text">$($h.EnterpriseAdminsList)</p></div></div></div>
    <div class="col"><div class="card $daCardClass text-center h-100"><div class="card-body"><h5 class="card-title">Domain Admins</h5><p class="card-text">$($h.DomainAdminsList)</p></div></div></div>
    <div class="col"><div class="card bg-info text-white text-center h-100"><div class="card-body"><h5 class="card-title">Total Users</h5><p class="card-text display-6">$($h.TotalUsers)</p></div></div></div>
    <div class="col"><div class="card bg-primary text-white text-center h-100"><div class="card-body"><h5 class="card-title">Total GPOs</h5><p class="card-text display-6">$($h.GpoCount)</p></div></div></div>
</div>
</div>
"@

    $userInfoHtml = @"
<div class="group-outline group-orange">
<h1 class="mt-4 mb-3 text-center">User and Group Information</h1>
<div class="row d-flex flex-nowrap g-1 mb-2">
    <div class="col"><div class="card bg-success text-white text-center h-100"><div class="card-body"><h5 class="card-title">Enabled Users</h5><p class="card-text display-6">$($h.EnabledUsers)</p></div></div></div>
    <div class="col"><div class="card bg-secondary text-white text-center h-100"><div class="card-body"><h5 class="card-title">Disabled Users</h5><p class="card-text display-6">$($h.DisabledUsers)</p></div></div></div>
    <div class="col"><div class="card bg-primary text-white text-center h-100"><div class="card-body"><h5 class="card-title">Active Users (60d)</h5><p class="card-text display-6">$($h.ActiveUsers)</p></div></div></div>
    <div class="col"><div class="card $lockedOutCardClass text-center h-100"><div class="card-body"><h5 class="card-title">Locked Out</h5><p class="card-text display-6">$($h.LockedOutUsers)</p></div></div></div>
    <div class="col"><div class="card $inactiveCardClass text-center h-100"><div class="card-body"><h5 class="card-title">Inactive (90d)</h5><p class="card-text display-6">$($h.InactiveUsers)</p></div></div></div>
    <div class="col"><div class="card $expiredCardClass text-center h-100"><div class="card-body"><h5 class="card-title">Expired Passwords</h5><p class="card-text display-6">$($h.ExpiredPasswords)</p></div></div></div>
</div>
</div>
"@

    $dcServersHtml = '<div class="group-outline group-green"><h1 class="mt-4 mb-3 text-center">Server Informations</h1><div class="row g-2">'
    foreach ($dc in $allDCs) {
        $roles = $dc.OperationMasterRoles
        if ([string]::IsNullOrWhiteSpace($roles)) { $roles = "None" }
        $statusColor = if ($dc.Ping -eq "Success") { "bg-success" } else { "bg-danger" }
        $uptimeDisplay = "N/A"
        if ($dc.UptimeHours -match "^\d+") {
            $u = [int]$dc.UptimeHours
            $days = [math]::Floor($u / 24)
            $hours = $u % 24
            $uptimeDisplay = "$days days, $hours hours"
        } else { $uptimeDisplay = $dc.UptimeHours }
        $freeSpacePct = $dc.FreeSpacePct
        $diskClass = "bg-success"
        if ($freeSpacePct -match "^\d+") {
            $pct = [int]$freeSpacePct
            if ($pct -lt 15) { $diskClass = "bg-danger" }
            elseif ($pct -lt 30) { $diskClass = "bg-warning" }
        }
        $dcdiagFailCount = @($dc.DCDiag.PSObject.Properties | Where-Object { $_.Value -eq "Failed" }).Count
        $dcdiagBadge = if ($dcdiagFailCount -gt 0) { "bg-danger" } else { "bg-success" }

        $dcServersHtml += @"
<div class="col-md-6 mb-3">
<div class="card h-100 border-3 $statusColor-subtle">
<div class="card-header text-center fw-bold $statusColor text-white"><h5 class="mb-0">$($dc.HostName)</h5></div>
<div class="card-body">
<p class="mb-1"><strong>IP:</strong> $($dc.IPv4Address)</p>
<p class="mb-1"><strong>OS:</strong> $($dc.OSVersion)</p>
<p class="mb-1"><strong>Site:</strong> $($dc.Site)</p>
<p class="mb-1"><strong>Uptime:</strong> $uptimeDisplay</p>
<p class="mb-1"><strong>Backup:</strong> <span class="badge bg-info text-white">$(if($dc.LastBackup -is [PSCustomObject] -and $dc.LastBackup.Value){$dc.LastBackup.Value}elseif($dc.LastBackup -is [string]){$dc.LastBackup}else{$dc.LastBackup})</span></p>
<p class="mb-1"><strong>Critical Events (24h):</strong> <span class="badge bg-danger">$(if($dc.CriticalEvents -is [PSCustomObject] -and $dc.CriticalEvents.Value){$dc.CriticalEvents.Value}else{$dc.CriticalEvents})</span></p>
<p class="mb-1"><strong>Time Offset:</strong> $($dc.TimeOffset) sec</p>
<p class="mb-1"><strong>Ping:</strong> <span class="badge $statusColor">$($dc.Ping)</span></p>
<p class="mb-1"><strong>DCDiag Failures:</strong> <span class="badge $dcdiagBadge text-white">$dcdiagFailCount</span></p>
<p class="mb-1"><strong>FSMO Roles:</strong> $roles</p>
<p class="mb-1"><strong>DNS Service:</strong> <span class="badge $(if($dc.DNSService -eq 'Success'){'bg-success'}else{'bg-danger'})">$($dc.DNSService)</span></p>
<p class="mb-1"><strong>NTDS Service:</strong> <span class="badge $(if($dc.NTDSService -eq 'Success'){'bg-success'}else{'bg-danger'})">$($dc.NTDSService)</span></p>
<p class="mb-1"><strong>NetLogon Service:</strong> <span class="badge $(if($dc.NetlogonService -eq 'Success'){'bg-success'}else{'bg-danger'})">$($dc.NetlogonService)</span></p>
<p class="mb-1"><strong>OS Free Space:</strong> <span class="badge $diskClass text-white">$($dc.FreeSpacePct)% ($($dc.FreeSpaceGB) GB)</span></p>
</div></div></div>
"@
    }
    $dcServersHtml += '</div></div>'

    $dcdiagTable = '<div class="group-outline group-purple"><h1 class="mt-4 mb-3 text-center">DCDiag Test Results</h1><div class="table-responsive"><table class="table table-striped table-bordered table-hover"><thead class="table-dark"><tr><th>Test Name</th>'
    $sortedDCs = $allDCs | Sort-Object HostName
    foreach ($sdc in $sortedDCs) { $dcdiagTable += "<th>$($sdc.HostName)</th>" }
    $dcdiagTable += '</tr></thead><tbody>'
    $dcdiagTests = @("Advertising","CheckSDRefDom","Connectivity","CrossRefValidation","DFSREvent","FSMOCheck","FrsEvent","Intersite","KccEvent","LocatorCheck","MachineAccount","NCSecDesc","NetLogons","ObjectsReplicated","Replications","RidManager","Services","SysVolCheck","VerifyReferences","KnowsOfRoleHolders")
    foreach ($test in $dcdiagTests) {
        $dcdiagTable += "<tr><td><strong>$test</strong></td>"
        foreach ($sdc in $sortedDCs) {
            $val = $sdc.DCDiag.$test
            if (-not $val) { $val = "N/A" }
            $cls = if ($val -eq "Passed" -or $val -eq "Success") { "bg-success text-white" } elseif ($val -eq "Failed") { "bg-danger text-white" } else { "bg-warning text-dark" }
            $dcdiagTable += "<td class='$cls'>$val</td>"
        }
        $dcdiagTable += "</tr>"
    }
    $dcdiagTable += '</tbody></table></div></div>'

    $eventsSection = '<div class="group-outline group-red"><h1 class="mt-4 mb-3 text-center">System & Directory Service Events (24h)</h1>'
    $hasAnyEvents = $false
    foreach ($edc in $sortedDCs) {
        $evts = @($edc.SystemEvents)
        if ($evts.Count -eq 0) { continue }
        $hasAnyEvents = $true
        $eventsSection += "<div class='mb-3'><h5 class='mb-2'>$($edc.HostName) ($($evts.Count) events)</h5><div class='table-responsive'><table class='table table-sm table-bordered table-hover'><thead class='table-light'><tr><th>Log</th><th>ID</th><th>Time</th><th>Message</th></tr></thead><tbody>"
        $shown = 0
        foreach ($evt in $evts) {
            $logVal = if ($evt.Log -is [PSCustomObject] -and $evt.Log.Value) { $evt.Log.Value } else { "$($evt.Log)" }
            $idVal = if ($evt.Id -is [PSCustomObject] -and $evt.Id.Value) { $evt.Id.Value } else { "$($evt.Id)" }
            $timeVal = if ($evt.Time -is [PSCustomObject] -and $evt.Time.Value) { $evt.Time.Value } else { "$($evt.Time)" }
            $msgVal = if ($evt.Message -is [PSCustomObject] -and $evt.Message.Value) { $evt.Message.Value } else { "$($evt.Message)" }
            $eventsSection += "<tr><td>$logVal</td><td>$idVal</td><td>$timeVal</td><td style='max-width:400px;word-break:break-word'>$msgVal</td></tr>"
            $shown++
            if ($shown -ge 20) { break }
        }
        if ($evts.Count -gt 20) { $eventsSection += "<tr><td colspan='4' class='text-muted'>... and $($evts.Count - 20) more events</td></tr>" }
        $eventsSection += '</tbody></table></div></div>'
    }
    if (-not $hasAnyEvents) {
        $eventsSection += "<p class='text-center text-muted'>No critical events found in the last 24 hours.</p>"
    }
    $eventsSection += '</div>'

    $replHtml = '<div class="group-outline group-info"><h1 class="mt-4 mb-3 text-center">Replication Summary</h1><div class="row justify-content-center">'
    $srcSummary = @($h.ReplicationSummary | Where-Object { $_.Type -eq "Source" })
    $dstSummary = @($h.ReplicationSummary | Where-Object { $_.Type -eq "Destination" })

    function BuildReplTable($items, $title) {
        $hasErrors = @($items | Where-Object { $_.Fails -gt 0 -or $_.Error }).Count -gt 0
        $hdr = if ($hasErrors) { "bg-danger text-white" } else { "bg-success text-white" }
        $html = "<div class='col-lg-6 mb-3'><div class='card h-100 border-3'><div class='card-header text-center fw-bold $hdr'><h5 class='mb-0'>$title</h5></div><div class='card-body p-0'><div class='table-responsive'><table class='table table-sm table-bordered table-hover mb-0'><thead class='table-light'><tr><th>DSA</th><th>Largest Delta</th><th>Fails/Total</th><th>Error</th></tr></thead><tbody>"
        foreach ($item in $items) {
            $rc = if ($item.Fails -gt 0 -or $item.Error) { "table-danger" } else { "" }
            $html += "<tr class='$rc'><td>$($item.DSA)</td><td>$($item.LargestDelta)</td><td>$($item.Fails)/$($item.Total)</td><td>$($item.Error)</td></tr>"
        }
        $html += "</tbody></table></div></div></div></div>"
        return $html
    }

    if ($srcSummary) { $replHtml += BuildReplTable $srcSummary "Source DSAs Replication Status" }
    if ($dstSummary) { $replHtml += BuildReplTable $dstSummary "Destination DSAs Replication Status" }
    $replHtml += '</div></div>'

    $trustHtml = ""
    if ($h.Trusts) {
        $trustRows = ""
        foreach ($t in $h.Trusts) {
            $trustRows += "<tr><td>$($t.Name)</td><td>$($t.Direction)</td><td>$($t.Transitive)</td><td>$($t.TrustType)</td></tr>"
        }
        $trustHtml = "<div class='group-outline group-blue'><h1 class='mt-4 mb-3 text-center'>Trust Relationships</h1><div class='table-responsive'><table class='table table-sm table-bordered table-hover'><thead class='table-light'><tr><th>Name</th><th>Direction</th><th>Transitive</th><th>Type</th></tr></thead><tbody>$trustRows</tbody></table></div></div>"
    }

    $systemComments = @()
    if ($faultyDCs -eq 0) { $systemComments += [PSCustomObject]@{Text="All domain controllers are in a healthy state.";Type="Success"} }
    else { $systemComments += [PSCustomObject]@{Text="There are health warnings or failures on $faultyDCs domain controller(s).";Type="Warning"} }
    if ($h.EnterpriseAdmins -gt 2) { $systemComments += [PSCustomObject]@{Text="There are $($h.EnterpriseAdmins) Enterprise Admins. This is higher than the recommended limit of 2.";Type="Warning"} }
    else { $systemComments += [PSCustomObject]@{Text="The number of Enterprise Admins is $($h.EnterpriseAdmins), within the recommended security limit of 2.";Type="Success"} }
    if ($h.DomainAdmins -gt 5) { $systemComments += [PSCustomObject]@{Text="There are $($h.DomainAdmins) Domain Admins. Higher than recommended limit of 5.";Type="Warning"} }
    else { $systemComments += [PSCustomObject]@{Text="The number of Domain Admins is $($h.DomainAdmins), within the recommended limit of 5.";Type="Success"} }
    if ($h.LockedOutUsers -gt 0) { $systemComments += [PSCustomObject]@{Text="There are $($h.LockedOutUsers) locked out user account(s).";Type="Warning"} }
    if ($h.InactiveUsers -gt 0) { $systemComments += [PSCustomObject]@{Text="Found $($h.InactiveUsers) inactive accounts over 90 days.";Type="Warning"} }
    if ($h.ExpiredPasswords -gt 0) { $systemComments += [PSCustomObject]@{Text="There are $($h.ExpiredPasswords) accounts with expired passwords.";Type="Warning"} }
    $replFailures = @($h.ReplicationSummary | Where-Object { $_.Fails -gt 0 -or $_.Error }).Count
    if ($replFailures -gt 0) { $systemComments += [PSCustomObject]@{Text="AD replication experiencing $replFailures failure(s).";Type="Warning"} }
    else { $systemComments += [PSCustomObject]@{Text="Active Directory replication is healthy.";Type="Success"} }

    $commentsHtml = "<div class='mt-4'><h3 class='mb-3'>System Comments</h3>"
    $hasWarnings = @($systemComments | Where-Object { $_.Type -eq "Warning" }).Count -gt 0
    if ($hasWarnings) { $commentsHtml += "<p class='alert alert-danger'><strong>Attention: The following conditions were detected:</strong></p>" }
    $commentsHtml += "<ul class='list-unstyled'>"
    foreach ($c in $systemComments) {
        $icon = if ($c.Type -eq "Success") { '<i class="bi bi-check-circle-fill text-success"></i>' } else { '<i class="bi bi-exclamation-triangle-fill text-danger"></i>' }
        $commentsHtml += "<li class='mb-2'>$icon $($c.Text)</li>"
    }
    $commentsHtml += "</ul></div>"

    $overallHealth = if ($failedDCs -gt 0) { "Critical"; $ohClass = "bg-danger"; $ohIcon = "bi-x-octagon-fill" }
    elseif ($warningDCs -gt 0) { "Warning"; $ohClass = "bg-warning text-dark"; $ohIcon = "bi-exclamation-triangle-fill" }
    else { "Healthy"; $ohClass = "bg-success"; $ohIcon = "bi-check-circle-fill" }

    $keyFindings = @()
    if ($failedDCs -gt 0) { $keyFindings += "$failedDCs DC(s) have critical failures." }
    if ($warningDCs -gt 0) { $keyFindings += "$warningDCs DC(s) have warnings." }
    if ($h.LockedOutUsers -gt 0) { $keyFindings += "$($h.LockedOutUsers) account(s) are locked out." }
    if ($replFailures -gt 0) { $keyFindings += "AD replication has $replFailures failure(s)." }
    $kfHtml = ""
    if ($keyFindings.Count -eq 0) { $kfHtml = "<li class='list-group-item bg-light'><i class='bi bi-check-circle-fill text-success'></i> No critical issues found.</li>" }
    else { foreach ($f in $keyFindings) { $kfHtml += "<li class='list-group-item bg-light'><i class='bi bi-caret-right-fill'></i> $f</li>" } }

    $execSummary = @"
<div class="card mb-4 border-0">
<div class="card-header text-white $ohClass"><h2 class="mb-0"><i class="bi $ohIcon"></i> Executive Summary: <span class="fw-bold">$overallHealth</span></h2></div>
<div class="card-body bg-light"><h4 class="card-title">Key Findings:</h4><ul class="list-group list-group-flush">$kfHtml</ul></div>
</div>
"@

    $footer = "<footer class='text-center mt-5 p-3 bg-light'>© $CopyrightYear $DeveloperName. $CopyrightMessage</footer>"
    $watermark = if ($AppLogoPath) { "<img src='$AppLogoPath' alt='Watermark' class='watermark'>" }

    $html = $htmlHead + $titleRow + $execSummary + $summaryDashboard + $dcServersHtml + $replHtml + $userInfoHtml + $trustHtml + $dcdiagTable + $eventsSection + $commentsHtml + $footer + $watermark + "</div></body></html>"

    Set-Content -Path $OutputReportPath -Value $html -Encoding UTF8
    Write-Output "OK"
    exit 0

} catch {
    Write-Output "ERROR: $($_.Exception.Message)"
    exit 1
}
