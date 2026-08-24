# ldap_ad_helpers.ps1
# Shared AD lookup functions — work with AD module (when admin credential provided)
# or fall back to .NET DirectoryServices LDAP (when running under IIS without credential)
#
# Usage: . "$PSScriptRoot\ldap_ad_helpers.ps1"

$script:LdapInitDone = $false
$script:LdapDomainDN = $null
$script:LdapSearcher = $null

function init-ldap {
    if ($script:LdapInitDone) { return }
    try {
        Add-Type -AssemblyName System.DirectoryServices -ErrorAction Stop
        $script:LdapDomainDN = ([ADSI]"LDAP://rootDSE").defaultNamingContext
        $script:LdapSearcher = New-Object DirectoryServices.DirectorySearcher
        $script:LdapSearcher.SearchRoot = [ADSI]"LDAP://$($script:LdapDomainDN)"
        $script:LdapSearcher.PageSize = 1000
        $script:LdapInitDone = $true
    } catch { $script:LdapInitDone = $false }
}

function ldap-search {
    param([string]$Filter, [string[]]$Props, [string]$SearchBase)
    init-ldap
    if (-not $script:LdapInitDone) { return @() }
    $searcher = if ($SearchBase) {
        $s = New-Object DirectoryServices.DirectorySearcher
        $s.SearchRoot = [ADSI]"LDAP://$SearchBase"
        $s.PageSize = 1000; $s
    } else { $script:LdapSearcher }
    $searcher.Filter = $Filter
    $searcher.PropertiesToLoad.Clear()
    foreach ($p in $Props) { $null = $searcher.PropertiesToLoad.Add($p) }
    try { return $searcher.FindAll() } catch { return @() }
}

function ldap-result($raw, [string[]]$Props) {
    $out = @()
    foreach ($r in $raw) {
        $o = [PSCustomObject]@{}
        foreach ($p in $Props) {
            $k = $p.ToLower()
            if ($r.Properties[$k]) {
                $v = if ($r.Properties[$k].Count -gt 1) { @($r.Properties[$k]) } else { $r.Properties[$k][0] }
            } else {
                $v = $null
            }
            $o | Add-Member -NotePropertyName $p -NotePropertyValue $v -Force
        }
        $out += $o
    }
    try { if ($raw -and $raw.Dispose) { $raw.Dispose() } } catch {}
    return $out
}

function Get-ADUserViaLDAP {
    param([string]$SamAccountName, [string]$OUPath, [string[]]$Properties = @("samAccountName","displayName","distinguishedName","userAccountControl","mail","department","enabled"), [System.Management.Automation.PSCredential]$Credential)
    if ($Credential -and (Get-Module -Name ActiveDirectory)) {
        $f = @{ Filter = "SamAccountName -eq '$SamAccountName'"; Properties = $Properties; ErrorAction = 'SilentlyContinue'; Credential = $Credential }
        return Get-ADUser @f
    }
    $f = "(&(objectClass=user)(objectCategory=person)"
    if ($SamAccountName) { $f += "(samAccountName=$SamAccountName)" }
    $f += ")"
    $raw = if ($OUPath) { ldap-search -Filter $f -Props $Properties -SearchBase $OUPath } else { ldap-search -Filter $f -Props $Properties }
    return ldap-result $raw $Properties
}

function Get-ADGroupViaLDAP {
    param([string]$GroupName, [string[]]$Properties = @("name","distinguishedName","samAccountName","description","groupType"), [System.Management.Automation.PSCredential]$Credential)
    if ($Credential -and (Get-Module -Name ActiveDirectory)) {
        if ($GroupName -match '^CN=') {
            $f = @{ Filter = "DistinguishedName -eq '$GroupName'"; Properties = $Properties; ErrorAction = 'SilentlyContinue'; Credential = $Credential }
        } else {
            $f = @{ Filter = "Name -eq '$GroupName'"; Properties = $Properties; ErrorAction = 'SilentlyContinue'; Credential = $Credential }
        }
        return Get-ADGroup @f
    }
    if ($GroupName -match '^CN=') {
        try {
            $adsi = [ADSI]"LDAP://$GroupName"
            $o = [PSCustomObject]@{}
            foreach ($p in $Properties) { $k=$p.ToLower(); $v=if($adsi.Properties[$k]){$adsi.Properties[$k][0]}else{$null}; $o | Add-Member -NotePropertyName $p -NotePropertyValue $v -Force }
            return @($o)
        } catch { return @() }
    }
    $f = "(&(objectClass=group)(|(name=$GroupName)(samAccountName=$GroupName)))"
    return ldap-result (ldap-search -Filter $f -Props $Properties) $Properties
}

function Get-ADOrganizationalUnitViaLDAP {
    param([string]$OUName, [string[]]$Properties = @("name","distinguishedName"), [System.Management.Automation.PSCredential]$Credential)
    if ($Credential -and (Get-Module -Name ActiveDirectory)) {
        try { return Get-ADOrganizationalUnit -Identity $OUName -Properties $Properties -Credential $Credential -ErrorAction SilentlyContinue } catch { return @() }
    }
    $f = "(objectClass=organizationalUnit)"
    if ($OUName) { $f = "(&(objectClass=organizationalUnit)(distinguishedName=$OUName))" }
    return ldap-result (ldap-search -Filter $f -Props $Properties) $Properties
}

function Get-ADGroupMemberViaLDAP {
    param([string]$GroupDN, [string[]]$Properties = @("samAccountName","displayName","distinguishedName","objectClass"), [System.Management.Automation.PSCredential]$Credential)
    if ($Credential -and (Get-Module -Name ActiveDirectory)) {
        $f = @{ Identity = $GroupDN; Properties = $Properties; ErrorAction = 'SilentlyContinue'; Credential = $Credential }
        return Get-ADGroupMember @f
    }
    init-ldap
    if (-not $script:LdapInitDone) { return @() }
    $members = @()
    try {
        $grpAdsi = [ADSI]"LDAP://$GroupDN"
        $memberDNs = @($grpAdsi.Properties["member"])
        if ($memberDNs.Count -eq 0) { return @() }
        foreach ($dn in $memberDNs) {
            try {
                $adsi = [ADSI]"LDAP://$dn"
                $o = [PSCustomObject]@{}
                foreach ($p in $Properties) { $k = $p.ToLower(); $v = if ($adsi.Properties[$k]) { $adsi.Properties[$k][0] } else { $null }; $o | Add-Member -NotePropertyName $p -NotePropertyValue $v -Force }
                $members += $o
            } catch {}
        }
    } catch { return @() }
    return $members
}
