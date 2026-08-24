param(
    [Parameter(Mandatory=$true)]
    [string[]]$Username,
    [Parameter(Mandatory=$true)]
    [string]$SecureConfigPath
)

$SecureConfigPath = $SecureConfigPath.Trim("`"").Trim("'")
if (-not (Test-Path $SecureConfigPath)) {
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: Secure config not found." })
    exit 1
}
$Config = Import-Clixml -Path $SecureConfigPath
if ($null -eq $Config.AdminCredential) {
    exit 1
}

$allUsers = @()
foreach ($u in $Username) { $allUsers += $u -split '[,; ]+' }
$allUsers = $allUsers | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }

$results = @()
foreach ($user in $allUsers) {
    try {
        $found = Get-ADUser -Filter "SamAccountName -eq '$($user.Trim())'" -Credential $Config.AdminCredential -ErrorAction SilentlyContinue
        if (-not $found) {
            $results += @{ username = $user; success = $false; message = "User not found" }
            continue
        }
        Set-ADUser -Identity $found.DistinguishedName -CannotChangePassword $false -Credential $Config.AdminCredential
        Set-ADUser -Identity $found.DistinguishedName -PasswordNeverExpires $false -Credential $Config.AdminCredential
        $results += @{ username = $found.SamAccountName; success = $true; message = "UAC flags cleared" }
    } catch {
        $results += @{ username = $user; success = $false; message = $_.Exception.Message }
    }
}

$success = ($results | Where-Object { -not $_.success }).Count -eq 0
$output = @{ success = $success; userResults = $results }
$output | ConvertTo-Json -Compress -Depth 10
