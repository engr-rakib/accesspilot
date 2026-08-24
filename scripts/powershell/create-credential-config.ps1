param(
    [Parameter(Mandatory = $true)]
    [string]$AdminUsername,
    [Parameter(Mandatory = $true)]
    [string]$AdminPassword,
    [Parameter(Mandatory = $true)]
    [string]$ConfigPath,
    [Parameter(Mandatory = $true)]
    [string]$BaseLogPath
)

try {
    $cred = New-Object System.Management.Automation.PSCredential($AdminUsername, ($AdminPassword | ConvertTo-SecureString -AsPlainText -Force))
    $config = [PSCustomObject]@{
        AdminCredential = $cred
        BaseLogPath     = $BaseLogPath
    }
    $config | Export-Clixml -Path $ConfigPath
    Write-Output "OK"
    exit 0
} catch {
    Write-Output "ERROR: $($_.Exception.Message)"
    exit 1
}
