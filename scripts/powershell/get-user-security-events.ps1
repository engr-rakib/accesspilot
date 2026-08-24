[CmdletBinding()]Param(
    [string]$TargetDC = '',
    [string]$Username = '',
    [string]$EventIDs = '',
    [int]$DaysBack = 7,
    [int]$MaxResults = 200,
    [string]$DateFrom = '',
    [string]$DateTo = '',
    [string]$Workstation = ''
)

$ErrorActionPreference = 'Stop'
$startTime = Get-Date

try {
    Import-Module PSWSMan -ErrorAction Stop

    if ([string]::IsNullOrEmpty($TargetDC)) { throw "TargetDC is required." }

    $session = New-PSSession -ComputerName $TargetDC -Authentication Kerberos -ErrorAction Stop

    $raw = Invoke-Command -Session $session -ArgumentList $Username, $EventIDs, $DaysBack, $MaxResults, $DateFrom, $DateTo, $Workstation -ScriptBlock {
        param($u, $eid, $db, $mr, $df, $dt, $ws)

        $results = @()
        $userNames = @()
        $userSid = ''

        if (-not [string]::IsNullOrEmpty($u)) {
            try {
                Import-Module ActiveDirectory -ErrorAction Stop
                $userObj = Get-ADUser -Identity $u -Properties SamAccountName, DistinguishedName, UserPrincipalName -ErrorAction Stop
                $userSid = $userObj.SID.Value
                $userNames = @($u, $userObj.SamAccountName, $userObj.UserPrincipalName) | Where-Object { $_ }
            } catch {
                $userNames = @($u)
            }
        }

        if (-not [string]::IsNullOrEmpty($df)) {
            $startDate = Get-Date $df
            $neededDays = [int][math]::Ceiling(((Get-Date) - $startDate).TotalDays)
            if ($neededDays -gt $db) { $db = $neededDays }
        } else {
            $startDate = (Get-Date).AddDays(-$db)
        }

        if (-not [string]::IsNullOrEmpty($dt)) {
            $endDate = (Get-Date $dt).AddDays(1)
        } else {
            $endDate = Get-Date
        }

        $defaultIds = '4624,4625,4634,4647,4648,4672,4720,4722,4723,4724,4725,4726,4738,4740,4767'
        $xpathIdsStr = if ($eid -ne '') { $eid } else { $defaultIds }
        $idArray = $xpathIdsStr -split ',' | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne '' }
        $idOrClauses = ($idArray | ForEach-Object { "EventID=$_" }) -join ' or '
        $tsIdOrClauses = (18644,18645,18646,21,22,23,24,25 | ForEach-Object { "EventID=$_" }) -join ' or '

        $filterXml = @"
<QueryList>
  <Query Id="0" Path="Security">
    <Select Path="Security">*[System[($idOrClauses) and TimeCreated[timediff(@SystemTime) &lt;= $($db * 86400 * 1000)]]]</Select>
  </Query>
  <Query Id="1" Path="Microsoft-Windows-TerminalServices-LocalSessionManager/Operational">
    <Select Path="Microsoft-Windows-TerminalServices-LocalSessionManager/Operational">*[System[($tsIdOrClauses) and TimeCreated[timediff(@SystemTime) &lt;= $($db * 86400 * 1000)]]]</Select>
  </Query>
  <Query Id="2" Path="ForwardedEvents">
    <Select Path="ForwardedEvents">*[System[TimeCreated[timediff(@SystemTime) &lt;= $($db * 86400 * 1000)]]]</Select>
  </Query>
</QueryList>
"@

        $allEvents = Get-WinEvent -FilterXml $filterXml -MaxEvents ($mr * 3) -ErrorAction SilentlyContinue
        if (-not $allEvents) { $allEvents = @() }

        $eventIdLabels = @{
            4624 = 'Logon'; 4625 = 'Failed Logon'; 4634 = 'Logoff'
            4647 = 'Initiated Logoff'; 4648 = 'Explicit Credentials'; 4672 = 'Admin Logon'
            4720 = 'User Created'; 4722 = 'User Enabled'; 4723 = 'Password Change'
            4724 = 'Password Reset'; 4725 = 'User Disabled'; 4726 = 'User Deleted'
            4738 = 'User Changed'; 4740 = 'User Locked'; 4767 = 'User Unlocked'
            18644 = 'RDP Session'; 18645 = 'RDP Reconnect'; 18646 = 'RDP Disconnect'
            21 = 'RDP Logon'; 22 = 'RDP Shell'; 23 = 'RDP Shutdown'
            24 = 'RDP Disconnect'; 25 = 'RDP Reconnect'
        }

        $targetEventIds = @(4624, 4625, 4634, 4647, 4648, 4672, 4720, 4722, 4723, 4724, 4725, 4726, 4738, 4740, 4767, 18644, 18645, 18646, 21, 22, 23, 24, 25)
        if ($eid -ne '') {
            $targetEventIds = $eid -split ',' | ForEach-Object { [int]$_.Trim() }
        }

        $matchedCount = 0
        foreach ($event in $allEvents) {
            if ($matchedCount -ge $mr) { break }
            if ($targetEventIds -contains $event.ID) {
                $xml = [xml]$event.ToXml()
                $eventData = @{}
                if ($xml.Event.EventData.Data) {
                    foreach ($data in $xml.Event.EventData.Data) {
                        $eventData[$data.Name] = $data.'#text'
                    }
                }

                $matched = $false
                $targetUser = ''; $targetDomain = ''; $workstationName = ''
                $sourceIp = ''; $logonType = ''; $logonTypeDesc = ''
                $status = ''; $subStatus = ''

                switch ($event.ID) {
                    4624 {
                        $targetUser = $eventData['TargetUserName']; $targetDomain = $eventData['TargetDomainName']
                        $workstationName = $eventData['WorkstationName']; $sourceIp = $eventData['IpAddress']
                        $logonType = $eventData['LogonType']
                        $logonTypeDesc = switch ($logonType) { '2' { 'Interactive' } '3' { 'Network' } '4' { 'Batch' } '5' { 'Service' } '7' { 'Unlock' } '8' { 'NetworkCleartext' } '9' { 'NewCredentials' } '10' { 'RemoteInteractive' } '11' { 'CachedInteractive' } default { "Type $logonType" } }
                    }
                    4625 {
                        $targetUser = $eventData['TargetUserName']; $targetDomain = $eventData['TargetDomainName']
                        $workstationName = $eventData['WorkstationName']; $sourceIp = $eventData['IpAddress']
                        $logonType = $eventData['LogonType']; $status = $eventData['Status']; $subStatus = $eventData['SubStatus']
                    }
                    4634 { $targetUser = $eventData['TargetUserName']; $targetDomain = $eventData['TargetDomainName']; $logonType = $eventData['LogonType'] }
                    4647 { $targetUser = $eventData['TargetUserName']; $targetDomain = $eventData['TargetDomainName'] }
                    4648 { $targetUser = $eventData['TargetUserName']; $targetDomain = $eventData['TargetDomainName']; $workstationName = $eventData['WorkstationName']; $sourceIp = $eventData['IpAddress'] }
                    4672 { $targetUser = $eventData['TargetUserName']; $targetDomain = $eventData['TargetDomainName'] }
                    4720 { $targetUser = $eventData['SamAccountName']; $targetDomain = $eventData['TargetDomainName'] }
                    4722 { $targetUser = $eventData['SamAccountName']; $targetDomain = $eventData['TargetDomainName'] }
                    4723 { $targetUser = $eventData['SamAccountName']; $targetDomain = $eventData['TargetDomainName'] }
                    4724 { $targetUser = $eventData['SamAccountName']; $targetDomain = $eventData['TargetDomainName'] }
                    4725 { $targetUser = $eventData['SamAccountName']; $targetDomain = $eventData['TargetDomainName'] }
                    4726 { $targetUser = $eventData['SamAccountName']; $targetDomain = $eventData['TargetDomainName'] }
                    4738 { $targetUser = $eventData['SamAccountName']; $targetDomain = $eventData['TargetDomainName'] }
                    4740 { $targetUser = $eventData['TargetUserName']; $targetDomain = $eventData['TargetDomainName'] }
                    4767 { $targetUser = $eventData['TargetUserName']; $targetDomain = $eventData['TargetDomainName'] }
                    18644 { $targetUser = $eventData['User']; $workstationName = $eventData['ClientName']; $sourceIp = $eventData['ClientAddress'] }
                    18645 { $targetUser = $eventData['User']; $workstationName = $eventData['ClientName'] }
                    18646 { $targetUser = $eventData['User']; $workstationName = $eventData['ClientName'] }
                    21 { $targetUser = $eventData['User']; $workstationName = $eventData['ClientName']; $sourceIp = $eventData['Address'] }
                    22 { $targetUser = $eventData['User']; $sourceIp = $eventData['Address'] }
                    23 { $targetUser = $eventData['User']; $sourceIp = $eventData['Address'] }
                    24 { $targetUser = $eventData['User']; $workstationName = $eventData['ClientName']; $sourceIp = $eventData['Address'] }
                    25 { $targetUser = $eventData['User']; $workstationName = $eventData['ClientName']; $sourceIp = $eventData['Address'] }
                }

                if ($targetUser -and $userNames.Count -gt 0) {
                    foreach ($un in $userNames) {
                        if ($targetUser -eq $un) { $matched = $true; break }
                        if ($targetUser -like "*$un*") { $matched = $true; break }
                    }
                } elseif ($userNames.Count -eq 0) {
                    $matched = $true
                }

                if ($matched -and -not [string]::IsNullOrEmpty($ws)) {
                    if ([string]::IsNullOrEmpty($workstationName)) { $matched = $false }
                    elseif ($workstationName -notlike "*$ws*" -and $workstationName -ne $ws) { $matched = $false }
                    else { $matched = $true }
                }

                if ($matched) {
                    $matchedCount++
                    $entry = @{
                        TimeCreated    = $event.TimeCreated.ToString('dd-MM-yyyy hh:mm:ss tt')
                        EventId        = $event.ID
                        EventLabel     = $eventIdLabels[$event.ID] -as [string]
                        TargetUser     = $targetUser -as [string]
                        TargetDomain   = $targetDomain -as [string]
                        Workstation    = $workstationName -as [string]
                        SourceIp       = $sourceIp -as [string]
                        LogonType      = $logonType -as [string]
                        LogonTypeDesc  = $logonTypeDesc -as [string]
                    }
                    if ($event.ID -eq 4625) {
                        $entry['Status'] = $status -as [string]
                        $entry['SubStatus'] = $subStatus -as [string]
                    }
                    $results += $entry
                }
            }
        }

        $uniqueWs = @()
        $uniqueIps = @()
        foreach ($e in $results) {
            $w = $e.Workstation
            $ip = $e.SourceIp
            if ($w -and $w -ne '' -and $w -ne '-' -and $w -notlike '*$') {
                if ($uniqueWs -notcontains $w) { $uniqueWs += $w }
            }
            if ($ip -and $ip -ne '' -and $ip -ne '-' -and $ip -notmatch '^127\.|^0\.|^::1$') {
                if ($uniqueIps -notcontains $ip) { $uniqueIps += $ip }
            }
        }
        # Resolve IPs to hostnames
        $ipWs = @()
        foreach ($ip in $uniqueIps) {
            try {
                $hostEntry = [System.Net.Dns]::GetHostEntry($ip)
                $hostname = $hostEntry.HostName.ToLower()
                if ($hostname -and $hostname -ne $ip -and $uniqueWs -notcontains $hostname) {
                    $ipWs += "$hostname ($ip)"
                }
            } catch { $ipWs += "$ip" }
        }

        $adWs = @()
        if ($u) {
            try {
                Import-Module ActiveDirectory -ErrorAction Stop
                $adUser = Get-ADUser -Identity $u -Properties userWorkstations, SamAccountName -ErrorAction SilentlyContinue
                if ($adUser -and $adUser.userWorkstations) {
                    $adWs = $adUser.userWorkstations -split ',' | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne '' }
                }
                $comps = Get-ADComputer -Filter "description -like '*$u*'" -Properties Name -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Name
                foreach ($c in $comps) {
                    if ($uniqueWs -notcontains $c -and $adWs -notcontains $c) {
                        $adWs += $c
                    }
                }
            } catch { }
        }

        $allWs = @($uniqueWs + $ipWs + $adWs | Sort-Object -Unique)
        $payload = @{ success = $true; events = $results; total = $results.Count; username = $u; workstations = $allWs }
        Write-Output "---DATA_START---"
        $payload | ConvertTo-Json -Compress -Depth 5
        Write-Output "---DATA_END---"
    }

    Remove-PSSession $session

    $rawStr = "$raw"
    $dataStart = $rawStr.IndexOf("---DATA_START---")
    $dataEnd = $rawStr.IndexOf("---DATA_END---")
    if ($dataStart -eq -1 -or $dataEnd -eq -1) { throw "Could not find data markers in output." }
    $dataStart += "---DATA_START---".Length
    $jsonStr = $rawStr.Substring($dataStart, $dataEnd - $dataStart).Trim()
    $decoded = $jsonStr | ConvertFrom-Json

    $elapsed = [math]::Round(((Get-Date) - $startTime).TotalSeconds, 2)

    $output = @{}
    $decoded.PSObject.Properties | ForEach-Object { $output[$_.Name] = $_.Value }
    $output['queryTime'] = "$elapsed sec"
    $output['domainController'] = $TargetDC

    Write-Output ($output | ConvertTo-Json -Depth 5 -Compress)
    exit 0

} catch {
    $elapsed = [math]::Round(((Get-Date) - $startTime).TotalSeconds, 2)
    $output = @{
        success     = $false
        username    = $Username
        error       = $_.Exception.Message
        queryTime   = "$elapsed sec"
        events      = @()
    }
    Write-Output ($output | ConvertTo-Json -Depth 3 -Compress)
    exit 1
}
