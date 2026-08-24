param (
    [string]$SecureConfigPath,
    [Parameter(Mandatory=$true)]
    [string]$Status,
    [string]$Days = "30",
    [string]$ExecutedBy = "System"
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
. "$scriptDir\ldap_ad_helpers.ps1"

$Config = $null
$ConfigLoaded = $false
if (-not [string]::IsNullOrEmpty($SecureConfigPath)) {
    try {
        if (Test-Path $SecureConfigPath) {
            $Config = Import-Clixml -Path $SecureConfigPath -ErrorAction Stop
            if ($null -ne $Config -and $null -ne $Config.AdminCredential) { $ConfigLoaded = $true }
        }
    } catch {}
}

try {
    $daysInt = [int]$Days
    $dateThreshold = (Get-Date).AddDays(-$daysInt)
    $thresholdFileTime = $dateThreshold.ToFileTime()

    $filter = ""
    if ($Status -eq "disabled") {
        $filter = "(&(objectClass=user)(objectCategory=person)(userAccountControl:1.2.840.113556.1.4.803:=2))"
    } elseif ($Status -eq "inactive") {
        $filter = "(&(objectClass=user)(objectCategory=person)(!(userAccountControl:1.2.840.113556.1.4.803:=2))(lastLogonTimestamp<=$thresholdFileTime))"
    } else {
        $filter = "(&(objectClass=user)(objectCategory=person)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))"
    }

    $searcher = [adsisearcher]$filter
    $searcher.PageSize = 2000
    $searcher.PropertiesToLoad.AddRange(@("sAMAccountName", "displayName", "distinguishedName", "lastLogonTimestamp"))

    $candidates = $searcher.FindAll()

    if ($null -eq $candidates -or $candidates.Count -eq 0) {
        $out = @{ success = $true; users = @() }
        Write-Output ($out | ConvertTo-Json -Compress)
        exit
    }

    $userMap = @{}
    foreach ($res in $candidates) {
        $props = $res.Properties
        $dn = $props["distinguishedName"][0]
        $latest = 0
        if ($props.Contains("lastLogonTimestamp")) { $latest = $props["lastLogonTimestamp"][0] }

        $userMap[$dn] = @{
            SamAccountName = $props["sAMAccountName"][0]
            DisplayName    = if ($props.Contains("displayName")) { $props["displayName"][0] } else { "" }
            LatestTicks    = $latest
            DistinguishedName = $dn
        }
    }

    if ($Status -ne "disabled" -and $userMap.Count -le 500) {
        try {
            $domain = [System.DirectoryServices.ActiveDirectory.Domain]::GetCurrentDomain()
            $dcs = $domain.DomainControllers | Select-Object -ExpandProperty Name
            foreach ($dc in $dcs) {
                try {
                    $dcSearcher = New-Object System.DirectoryServices.DirectorySearcher([ADSI]"LDAP://$dc")
                    $dcSearcher.Filter = $filter
                    $dcSearcher.PropertiesToLoad.AddRange(@("distinguishedName", "lastLogon"))
                    $dcSearcher.PageSize = 500

                    $dcResults = $dcSearcher.FindAll()
                    foreach ($res in $dcResults) {
                        $dn = $res.Properties["distinguishedName"][0]
                        if ($userMap.ContainsKey($dn)) {
                            $ll = if ($res.Properties.Contains("lastLogon")) { $res.Properties["lastLogon"][0] } else { 0 }
                            if ($ll -gt $userMap[$dn].LatestTicks) { $userMap[$dn].LatestTicks = $ll }
                        }
                    }
                } catch { continue }
            }
        } catch {}
    }

    $finalUsers = New-Object System.Collections.Generic.List[PSCustomObject]
    foreach ($u in $userMap.Values) {
        $logonDate = if ($u.LatestTicks -gt 0) { [DateTime]::FromFileTime($u.LatestTicks) } else { $null }

        if ($Status -eq "active" -and ($null -ne $logonDate -and $logonDate -lt $dateThreshold)) { continue }

        $dnStr = $u.DistinguishedName
        $ouParts = ($dnStr -split '(?<!\\),') | Where-Object { $_ -like 'OU=*' } | ForEach-Object { $_ -replace 'OU=', '' }
        if ($ouParts) {
            [array]::Reverse($ouParts)
            $ouPath = $ouParts -join ' > '
        } else {
            $ouPath = "N/A"
        }

        $finalUsers.Add([PSCustomObject]@{
            SamAccountName = $u.SamAccountName
            DisplayName    = $u.DisplayName
            LastLogonDate  = if ($logonDate) { $logonDate.ToString("yyyy-MM-dd HH:mm") } else { "Never" }
            Enabled        = ($Status -ne "disabled")
            OU             = $ouPath
        })
    }

    $finalOut = @{ success = $true; users = $finalUsers }
    Write-Output ($finalOut | ConvertTo-Json -Depth 2 -Compress)

} catch {
    $errOut = @{ success = $false; message = $_.Exception.Message }
    Write-Output ($errOut | ConvertTo-Json -Compress)
}
