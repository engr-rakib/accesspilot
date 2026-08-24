param(
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

# Requires Active Directory module. Ensure it's installed and available.
Import-Module ActiveDirectory

try {
    # Get the domain
    $domain = Get-ADDomain -Credential $Config.AdminCredential

    # Get all OUs and other containers, including the Builtin container
    $containers = Get-ADObject -SearchBase $domain.DistinguishedName -LDAPFilter "(|(objectClass=organizationalUnit)(objectClass=container)(objectClass=builtinDomain))" -Credential $Config.AdminCredential | Select-Object -Property Name, DistinguishedName, ObjectClass

    # Get all groups and include their parent container
    $groups = Get-ADGroup -Filter * -Properties Name, DistinguishedName -Credential $Config.AdminCredential | ForEach-Object {
        # Extract the parent container from the DistinguishedName
        $parentContainer = $_.DistinguishedName -replace '^CN=[^,]+,'

        [PSCustomObject]@{
            Name = $_.Name
            DistinguishedName = $_.DistinguishedName
            Type = "Group"
            Parent = $parentContainer # Add the parent container distinguished name
        }
    } | Sort-Object Name

    # Combine containers and Groups into a single structure for the frontend
    $allADObjects = @()

    # Add the domain object
    $allADObjects += [PSCustomObject]@{
        Name = $domain.Name;
        DistinguishedName = $domain.DistinguishedName;
        Type = "Domain";
        Parent = $null
    }

    $allADObjects += $containers | ForEach-Object { 
        [PSCustomObject]@{ 
            Name = $_.Name; 
            DistinguishedName = $_.DistinguishedName; 
            Type = if ($_.ObjectClass -eq 'organizationalUnit') { 'OU' } else { 'Container' };
            Parent = ($_.DistinguishedName -split '(?<!\\),', 2)[1]
        } 
    }
    $allADObjects += $groups

    # Convert to JSON and output
    Write-Output (ConvertTo-Json @{ success = $true; data = $allADObjects } -Compress -Depth 10)
    exit 0
} catch {
    $errorMessage = $_.Exception.Message
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: Failed to retrieve Active Directory groups. Details: $errorMessage" })
    exit 1
}