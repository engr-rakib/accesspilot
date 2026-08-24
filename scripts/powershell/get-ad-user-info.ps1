param(
    [Parameter(Mandatory=$true)]
    [string]$Username,
    [Parameter(Mandatory=$false)]
    [string]$ExecutedBy,
    [Parameter(Mandatory=$true)]
    [string]$SecureConfigPath
)

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
if ($null -eq $Config -or $null -eq $Config.AdminCredential) {
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: Secure configuration is invalid or missing admin credentials." })
    exit 1
}

Import-Module ActiveDirectory

try {
    $adDomain = Get-ADDomain -Credential $Config.AdminCredential
    $adDomainDN = $adDomain.DistinguishedName

    # Find the user with an exact match.
    $foundUser = Get-ADUser -Filter "SamAccountName -eq '$Username'" -Credential $Config.AdminCredential -Properties DisplayName, Description, MemberOf, DistinguishedName -ErrorAction SilentlyContinue

    if ($foundUser) {
        # Then, select the required properties from the basic user object
        $user = $foundUser | Select-Object SamAccountName, DisplayName, DistinguishedName, Description, @{Name='OU'; Expression={($_.DistinguishedName -split ',OU=' | Select-Object -Last 1) -replace ',DC=.*'}}, @{Name='MemberOf'; Expression={$_.MemberOf -join ';'}}

        # Wrap the user object in a success object
        $finalOutput = @{
            success = $true
            user = $user
        }
        $finalOutput | ConvertTo-Json -Depth 3
    } else {
        Write-Output (ConvertTo-Json @{ success = $false; message = "User '$Username' not found." })
    }
} catch {
    Write-Output (ConvertTo-Json @{ success = $false; message = "Error retrieving user information: $($_.Exception.Message)" })
}