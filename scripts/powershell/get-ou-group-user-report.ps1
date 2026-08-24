param (
    [string]$OUName,
    [string]$GroupName,
    [string]$ExecutedBy
)

$ExecutedBy = if ($ExecutedBy) { $ExecutedBy.Trim() } else { "UNKNOWN" }

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
. "$scriptDir\ldap_ad_helpers.ps1"

$results = @()
$totalUsers = 0
$enabledCount = 0
$disabledCount = 0
$active60Count = 0
$inactive60Count = 0
$domainAdminCount = 0
$enterpriseAdminCount = 0

try {
    $props = @("samAccountName","displayName","distinguishedName","userAccountControl","memberOf","whenCreated","lastLogonTimestamp","description")
    $adUsers = @()

    if (-not [string]::IsNullOrEmpty($GroupName)) {
        $targetGroup = Get-ADGroupViaLDAP -GroupName $GroupName
        if (-not $targetGroup -or $targetGroup.Count -eq 0) {
            $outputObject = [ordered]@{ success = $false; message = "ERROR: Group '$GroupName' not found."; users = @() }
            $outputObject | ConvertTo-Json -Compress -Depth 10
            exit 1
        }
        $groupDn = $targetGroup[0].distinguishedName

        init-ldap
        if (-not $script:LdapInitDone) {
            $outputObject = [ordered]@{ success = $false; message = "ERROR: LDAP initialization failed."; users = @() }
            $outputObject | ConvertTo-Json -Compress -Depth 10
            exit 1
        }

        $grpAdsi = [ADSI]"LDAP://$groupDn"
        $memberDNs = @($grpAdsi.Properties["member"])
        $totalUsers = $memberDNs.Count

        if ($totalUsers -eq 0) {
            $outputObject = [ordered]@{ success = $true; message = "Group '$GroupName' has no members."; users = @(); totalUsers = 0; sourceType = "Group"; sourceName = $GroupName }
            $outputObject | ConvertTo-Json -Compress -Depth 10
            exit 0
        }

        $chunkSize = 500
        for ($i = 0; $i -lt $memberDNs.Count; $i += $chunkSize) {
            $chunk = $memberDNs[$i..([Math]::Min($i + $chunkSize - 1, $memberDNs.Count - 1))]
            $filterParts = $chunk | ForEach-Object { "(distinguishedName=$_)" }
            $filter = "(&(objectClass=user)(objectCategory=person)(|$($filterParts -join '')))"
            $raw = ldap-search -Filter $filter -Props $props
            $adUsers += ldap-result $raw $props
        }
    } elseif (-not [string]::IsNullOrEmpty($OUName)) {
        $raw = ldap-search -Filter "(&(objectClass=user)(objectCategory=person))" -Props $props -SearchBase $OUName
        $adUsers = ldap-result $raw $props
    } else {
        $raw = ldap-search -Filter "(&(objectClass=user)(objectCategory=person))" -Props $props
        $adUsers = ldap-result $raw $props
    }

    # Privilege detection — match CN in memberOf against known admin groups
    $privCns = @(
        @{ Name = "Domain Admin";       CNs = @("Domain Admins") }
        @{ Name = "Enterprise Admin";   CNs = @("Enterprise Admins") }
        @{ Name = "Builtin Admin";      CNs = @("Administrators") }
        @{ Name = "Schema Admin";       CNs = @("Schema Admins") }
        @{ Name = "Backup Operator";    CNs = @("Backup Operators") }
        @{ Name = "Account Operator";   CNs = @("Account Operators") }
        @{ Name = "Server Operator";    CNs = @("Server Operators") }
        @{ Name = "Print Operator";     CNs = @("Print Operators") }
        @{ Name = "WSAdmin";            CNs = @("Workstation Admins","WSAdmins","Workstation Administrators","WorkstaionAdmin") }
        @{ Name = "Helpdesk";           CNs = @("Helpdesk") }
        @{ Name = "Delegated Admin";    CNs = @("DelegatedAdminsGroup") }
    )

    $sourceType = if ($GroupName) { "Group" } elseif ($OUName) { "OU" } else { "All Users" }
    $sourceName = if ($GroupName) { if ($GroupName -match '^CN=([^,]+)') { $matches[1] } else { $GroupName } } elseif ($OUName) { if ($OUName -match '^OU=([^,]+)') { $matches[1] } else { $OUName } } else { "All AD Users" }

    $foundUsers = 0
    foreach ($user in $adUsers) {
        $foundUsers++
        $sam = $user.samAccountName
        $disp = $user.displayName
        $dn = $user.distinguishedName
        $uac = $user.userAccountControl
        $memberOfList = if ($user.memberOf -is [array]) { $user.memberOf } elseif ($user.memberOf) { @($user.memberOf) } else { @() }

        # Enabled/Disabled
        $isEnabled = ($uac -band 2) -eq 0
        if ($isEnabled) { $enabledCount++ } else { $disabledCount++ }

        # Last Logon
        $lastLogonDate = ""
        $activityStatus = "N/A"
        $llTS = $user.lastLogonTimestamp
        if ($llTS -and $llTS -is [long] -and $llTS -gt 0) {
            $dt = [DateTime]::FromFileTime($llTS)
            $lastLogonDate = $dt.ToString('yyyy-MM-dd HH:mm')
            $daysSince = (New-TimeSpan -Start $dt -End (Get-Date)).Days
            if ($daysSince -le 60) { $activityStatus = "Active"; $active60Count++ }
            else { $activityStatus = "Inactive"; $inactive60Count++ }
        } elseif ($llTS -and $llTS -is [DateTime]) {
            $lastLogonDate = $llTS.ToString('yyyy-MM-dd HH:mm')
            $daysSince = (New-TimeSpan -Start $llTS -End (Get-Date)).Days
            if ($daysSince -le 60) { $activityStatus = "Active"; $active60Count++ }
            else { $activityStatus = "Inactive"; $inactive60Count++ }
        }

        # WhenCreated
        $whenCreated = ""
        if ($user.whenCreated) {
            try { $whenCreated = ([DateTime]$user.whenCreated).ToString('yyyy-MM-dd') } catch { $whenCreated = "" }
        }

        # Privilege detection — match CN against known admin group names
        $privList = @()
        foreach ($m in $memberOfList) {
            if ($m -match '^CN=([^,]+)') {
                $cn = $matches[1]
                foreach ($pc in $privCns) {
                    foreach ($c in $pc.CNs) {
                        if ($cn -ieq $c) { $privList += $pc.Name }
                    }
                }
            }
        }
        $privilege = if ($privList.Count -gt 0) { ($privList | Select-Object -Unique) -join ', ' } else { "" }
        foreach ($p in ($privilege -split ', ' | ForEach-Object { $_.Trim() })) {
            if ($p -eq "Domain Admin") { $domainAdminCount++ }
            if ($p -eq "Enterprise Admin") { $enterpriseAdminCount++ }
        }

        # MemberOf friendly names
        $memberOfFriendly = @()
        foreach ($m in $memberOfList) {
            if ($m -match '^CN=([^,]+)') { $memberOfFriendly += $matches[1] }
        }
        $memberOfStr = $memberOfFriendly -join '; '

        # OU path
        $ouPathParts = ($dn -split ',') | Where-Object { $_ -like 'OU=*' } | ForEach-Object { $_.Substring(3) }
        if ($ouPathParts) { [array]::Reverse($ouPathParts); $ouPath = $ouPathParts -join '/' } else { $ouPath = "" }

        # Simple OU name (last OU in DN)
        $ouNameSimple = ""
        $ouParts = @(($dn -split ',') | Where-Object { $_ -like 'OU=*' })
        if ($ouParts.Count -gt 0) { $ouNameSimple = $ouParts[0].Substring(3) }

        $results += [PSCustomObject]@{
            SamAccountName = $sam
            DisplayName = $disp
            Enabled = $isEnabled
            OU = $ouPath
            OUName = $ouNameSimple
            SourceType = $sourceType
            SourceName = $sourceName
            MemberOf = $memberOfStr
            ActivityStatus = $activityStatus
            LastLogonDate = $lastLogonDate
            WhenCreated = $whenCreated
            Privilege = $privilege
        }
    }

    if ($totalUsers -eq 0) { $totalUsers = $foundUsers }

    $outputObject = [ordered]@{
        success = $true
        message = "SUCCESS: Found $($results.Count) users from $sourceType '$sourceName'. Enabled: $enabledCount, Disabled: $disabledCount."
        users = @($results)
        totalUsers = $totalUsers
        sourceType = $sourceType
        sourceName = $sourceName
    }
    $outputObject | ConvertTo-Json -Compress -Depth 10

} catch {
    $fullError = $_ | Out-String
    $outputObject = [ordered]@{ success = $false; message = "ERROR: $fullError"; users = @(); totalUsers = 0 }
    $outputObject | ConvertTo-Json -Compress -Depth 10
    exit 1
}

exit 0
