param(
    [Parameter(Mandatory = $true)]
    [string]$Identity,
    [Parameter(Mandatory = $false)]
    [string]$ExecutedBy = '(Web Application)',
    [Parameter(Mandatory = $true)]
    [string]$SecureConfigPath
)

$Config = $null
try {
    if (-not (Test-Path $SecureConfigPath)) {
        throw "Secure configuration file not found at path: '$SecureConfigPath'."
    }
    $Config = Import-Clixml -Path $SecureConfigPath
} catch {
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: Failed to load secure configuration. $($_.Exception.Message)"; member = $null; suggestions = @() })
    exit 1
}

if ($null -eq $Config -or $null -eq $Config.AdminCredential) {
    Write-Output (ConvertTo-Json @{ success = $false; message = 'ERROR: Secure configuration is invalid or missing admin credentials.'; member = $null; suggestions = @() })
    exit 1
}

Import-Module ActiveDirectory

function New-MemberPayload {
    param(
        $Principal,
        [string]$ObjectClass
    )

    $displayName = if ($ObjectClass -eq 'user') { $Principal.DisplayName } else { $Principal.Name }
    $name = if ([string]::IsNullOrWhiteSpace($displayName)) { $Principal.SamAccountName } else { $displayName }

    [PSCustomObject]@{
        Name              = $name
        DisplayName       = $displayName
        SamAccountName    = $Principal.SamAccountName
        DistinguishedName = $Principal.DistinguishedName
        ObjectClass       = $ObjectClass
        Identifier        = if ([string]::IsNullOrWhiteSpace($Principal.SamAccountName)) { $Principal.Name } else { $Principal.SamAccountName }
    }
}

function Resolve-ExactPrincipal {
    param(
        [string]$Lookup,
        $Credential
    )

    $trimmed = $Lookup.Trim()
    if ([string]::IsNullOrWhiteSpace($trimmed)) {
        return $null
    }

    $directUser = Get-ADUser -Identity $trimmed -Properties DisplayName, SamAccountName, DistinguishedName, EmployeeID -Credential $Credential -ErrorAction SilentlyContinue
    if ($directUser) {
        return New-MemberPayload -Principal $directUser -ObjectClass 'user'
    }

    $directGroup = Get-ADGroup -Identity $trimmed -Properties Name, SamAccountName, DistinguishedName -Credential $Credential -ErrorAction SilentlyContinue
    if ($directGroup) {
        return New-MemberPayload -Principal $directGroup -ObjectClass 'group'
    }

    $escaped = $trimmed.Replace("'", "''")
    $userMatches = @(Get-ADUser -Filter "SamAccountName -eq '$escaped' -or UserPrincipalName -eq '$escaped' -or Name -eq '$escaped' -or EmployeeID -eq '$escaped'" -Properties DisplayName, SamAccountName, DistinguishedName, EmployeeID -Credential $Credential -ErrorAction SilentlyContinue)
    if ($userMatches.Count -eq 1) {
        return New-MemberPayload -Principal $userMatches[0] -ObjectClass 'user'
    }
    if ($userMatches.Count -gt 1) {
        throw "Multiple users matched '$trimmed'. Use the exact username or distinguished name."
    }

    $groupMatches = @(Get-ADGroup -Filter "SamAccountName -eq '$escaped' -or Name -eq '$escaped'" -Properties Name, SamAccountName, DistinguishedName -Credential $Credential -ErrorAction SilentlyContinue)
    if ($groupMatches.Count -eq 1) {
        return New-MemberPayload -Principal $groupMatches[0] -ObjectClass 'group'
    }
    if ($groupMatches.Count -gt 1) {
        throw "Multiple groups matched '$trimmed'. Use the exact group name or distinguished name."
    }

    return $null
}

function Find-Suggestions {
    param(
        [string]$Lookup,
        $Credential
    )

    $trimmed = $Lookup.Trim()
    if ([string]::IsNullOrWhiteSpace($trimmed)) {
        return @()
    }

    $escaped = $trimmed.Replace("'", "''")
    $likePattern = "*$escaped*"
    $suggestions = @()

    $userSuggestions = @(Get-ADUser -Filter "SamAccountName -like '$likePattern' -or Name -like '$likePattern' -or EmployeeID -like '$likePattern'" -Properties DisplayName, SamAccountName, DistinguishedName, EmployeeID -Credential $Credential -ErrorAction SilentlyContinue | Select-Object -First 6)
    foreach ($user in $userSuggestions) {
        $payload = New-MemberPayload -Principal $user -ObjectClass 'user'
        if ($user.EmployeeID) {
            $payload | Add-Member -NotePropertyName EmployeeID -NotePropertyValue $user.EmployeeID -Force
        }
        $suggestions += $payload
    }

    $groupSuggestions = @(Get-ADGroup -Filter "SamAccountName -like '$likePattern' -or Name -like '$likePattern'" -Properties Name, SamAccountName, DistinguishedName -Credential $Credential -ErrorAction SilentlyContinue | Select-Object -First 6)
    foreach ($group in $groupSuggestions) {
        $suggestions += New-MemberPayload -Principal $group -ObjectClass 'group'
    }

    return $suggestions | Sort-Object ObjectClass, Name -Unique
}

try {
    $member = Resolve-ExactPrincipal -Lookup $Identity -Credential $Config.AdminCredential
    if ($member) {
        Write-Output ((@{
            success = $true
            message = "Resolved '$Identity' successfully."
            member = $member
            suggestions = @()
        }) | ConvertTo-Json -Depth 6)
        exit 0
    }

    $suggestions = @(Find-Suggestions -Lookup $Identity -Credential $Config.AdminCredential)
    $message = "No exact directory principal matched '$Identity'."
    if ($suggestions.Count -gt 0) {
        $message += " Similar matches were found."
    }

    Write-Output ((@{
        success = $false
        message = $message
        member = $null
        suggestions = $suggestions
    }) | ConvertTo-Json -Depth 6)
    exit 1
} catch {
    Write-Output ((@{
        success = $false
        message = "ERROR: Failed to resolve directory principal. $($_.Exception.Message)"
        member = $null
        suggestions = @()
    }) | ConvertTo-Json -Depth 6)
    exit 1
}
