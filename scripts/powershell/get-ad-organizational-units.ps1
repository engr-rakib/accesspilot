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

try {
    # Define system OUs to exclude (case-insensitive matching on DistinguishedName)
    $SystemOUPatterns = @(
        "CN=Builtin",
        "CN=Computers",
        "CN=Users",
        "OU=Domain Controllers",
        "CN=ForeignSecurityPrincipals",
        "CN=Managed Service Accounts",
        "CN=Program Data",
        "CN=System",
        "CN=LostAndFound"
    )

    $Domain = Get-ADDomain -Credential $Config.AdminCredential
    $DomainDN = $Domain.DistinguishedName
    $DomainName = $Domain.Name

    # Add the domain root as the first entry
    $TreeData = @(
        [PSCustomObject]@{
            Name = $DomainName
            DistinguishedName = $DomainDN
            Parent = $null
            Type = "Domain"
        }
    )

    # Get all OUs with proper error handling
    $AllOUs = Get-ADOrganizationalUnit -Filter * -Properties DistinguishedName, Name, Created, Modified -Credential $Config.AdminCredential -ErrorAction Stop | 
               Sort-Object Name

    foreach ($ou in $AllOUs) {
        $isSystemOU = $false
        foreach ($pattern in $SystemOUPatterns) {
            if ($ou.DistinguishedName -like "*,$pattern,*" -or 
                $ou.DistinguishedName -like "$pattern,*" -or
                $ou.DistinguishedName -like "*,$pattern") {
                $isSystemOU = $true
                break
            }
        }
        
        if (-not $isSystemOU) {
            # Remove the first component to get parent DN
            if ($ou.DistinguishedName -match "^[^,]+,(.+)$") {
                $parentDN = $matches[1]
            } else {
                $parentDN = $null  # This should only happen for the domain root
            }
            
            $TreeData += [PSCustomObject]@{
                Name = $ou.Name
                DistinguishedName = $ou.DistinguishedName
                Parent = $parentDN
                Type = "OU"
                Created = $ou.Created
                Modified = $ou.Modified
            }
        }
    }
    
    # Convert to JSON and output
    Write-Output (ConvertTo-Json @{ success = $true; data = $TreeData } -Compress -Depth 10)
    exit 0
    
} catch {
    $errorMessage = "Failed to retrieve Organizational Units. Details: $($_.Exception.Message)"
    Write-Output (ConvertTo-Json @{ success = $false; message = "ERROR: $errorMessage" })
    exit 1
}