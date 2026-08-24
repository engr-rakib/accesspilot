# Test-AD-Connection.ps1
param(
    [string]$Domain,
    [string]$UserName,
    [string]$Password, # If provided, use this. Otherwise, use stored.
    [string]$SecureConfigPath,
    [switch]$UseStoredCredentials
)

$ErrorActionPreference = "Stop"

function Send-Result {
    param($PingStatus, $PingMessage, $AuthStatus, $AuthMessage)
    $res = @{
        ping = @{ success = $PingStatus; message = $PingMessage }
        auth = @{ success = $AuthStatus; message = $AuthMessage }
    }
    return $res | ConvertTo-Json -Compress
}

# 1. Ping Test
$pingSuccess = $false
$pingMessage = "Unreachable"
if (Test-Connection -ComputerName $Domain -Count 1 -Quiet) {
    $pingSuccess = $true
    $pingMessage = "Reachable"
} else {
    Write-Output (Send-Result $false "Unreachable" $false "Domain Controller not found")
    exit
}

# 2. Auth Test
$authSuccess = $false
$authMessage = "Not Tested"

try {
    $cred = $null
    if ($UseStoredCredentials) {
        if (-not (Test-Path $SecureConfigPath)) {
            Write-Output (Send-Result $true "Reachable" $false "Secure config file missing")
            exit
        }
        $Config = Import-Clixml $SecureConfigPath
        $cred = $Config.AdminCredential
    } else {
        $secPass = ConvertTo-SecureString $Password -AsPlainText -Force
        $cred = New-Object System.Management.Automation.PSCredential($UserName, $secPass)
    }

    # Attempt LDAP Bind
    $root = [ADSI]"LDAP://$Domain"
    $root.Username = $cred.UserName
    $root.Password = $cred.GetNetworkCredential().Password
    
    # Try to access a property to force bind
    if ($null -ne $root.distinguishedName) {
        $authSuccess = $true
        $authMessage = "Authenticated Successfully"
    }
} catch {
    $authMessage = $_.Exception.Message
    if ($authMessage -like "*password has expired*") {
        $authMessage = "Password Expired"
    } elseif ($authMessage -like "*Logon failure*") {
        $authMessage = "Logon Failure: Invalid Credentials"
    }
}

Write-Output (Send-Result $pingSuccess $pingMessage $authSuccess $authMessage)
