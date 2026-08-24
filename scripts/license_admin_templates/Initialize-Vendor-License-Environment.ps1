[CmdletBinding()]
param(
    [string]$PhpPath,
    [string]$PrivateKeyPath,
    [string]$PublicKeyPath,
    [string]$OpenSslConf,
    [switch]$PersistLocalConfig
)

$ErrorActionPreference = 'Stop'

function Read-RequiredValue {
    param(
        [string]$Prompt,
        [string]$DefaultValue = ''
    )

    while ($true) {
        $fullPrompt = if ($DefaultValue) { "$Prompt [$DefaultValue]" } else { $Prompt }
        $value = Read-Host $fullPrompt
        if ([string]::IsNullOrWhiteSpace($value)) {
            $value = $DefaultValue
        }

        if (-not [string]::IsNullOrWhiteSpace($value)) {
            return $value.Trim()
        }

        Write-Host "Value cannot be empty." -ForegroundColor Red
    }
}

function Resolve-ExistingPath {
    param(
        [string[]]$Candidates
    )

    foreach ($candidate in $Candidates) {
        if ($candidate -and (Test-Path $candidate)) {
            return (Resolve-Path $candidate).Path
        }
    }

    return $null
}

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot = Split-Path -Parent (Split-Path -Parent $scriptRoot)
$defaultPublicKeyPath = Join-Path $repoRoot 'config\license_public.pem'
$defaultPhpPath = Resolve-ExistingPath @(
    $env:ACCESSPILOT_VENDOR_PHP,
    'C:\php8.5.4_nts\php.exe',
    'C:\php\php.exe'
)
$defaultPrivateKeyPath = if ($env:ACCESSPILOT_VENDOR_PRIVATE_KEY_PATH) {
    $env:ACCESSPILOT_VENDOR_PRIVATE_KEY_PATH
} else {
    'C:\AccessPilotVendor\keys\private_key.pem'
}
$defaultPublicKeyOutputPath = if ($env:ACCESSPILOT_LICENSE_PUBLIC_KEY_PATH) {
    $env:ACCESSPILOT_LICENSE_PUBLIC_KEY_PATH
} else {
    $defaultPublicKeyPath
}
$defaultPhpRoot = if ($defaultPhpPath) {
    Split-Path -Parent $defaultPhpPath
} else {
    'C:\php8.5.4_nts'
}
$defaultOpenSslConf = Resolve-ExistingPath @(
    $env:OPENSSL_CONF,
    (Join-Path $defaultPhpRoot 'extras\ssl\openssl.cnf')
)

if (-not $PhpPath) {
    $PhpPath = Read-RequiredValue -Prompt 'Enter php.exe path' -DefaultValue ($(if ($defaultPhpPath) { $defaultPhpPath } else { 'C:\php8.5.4_nts\php.exe' }))
}
if (-not (Test-Path $PhpPath)) {
    throw "PHP path not found: $PhpPath"
}
$PhpPath = (Resolve-Path $PhpPath).Path

if (-not $PrivateKeyPath) {
    $PrivateKeyPath = Read-RequiredValue -Prompt 'Enter vendor private key path' -DefaultValue $defaultPrivateKeyPath
}

$privateKeyDir = Split-Path -Parent $PrivateKeyPath
if (-not (Test-Path $privateKeyDir)) {
    New-Item -Path $privateKeyDir -ItemType Directory -Force | Out-Null
}

if (-not $PublicKeyPath) {
    $PublicKeyPath = Read-RequiredValue -Prompt 'Enter public key output path' -DefaultValue $defaultPublicKeyOutputPath
}
$publicKeyDir = Split-Path -Parent $PublicKeyPath
if (-not (Test-Path $publicKeyDir)) {
    New-Item -Path $publicKeyDir -ItemType Directory -Force | Out-Null
}

if (-not $OpenSslConf) {
    $OpenSslConf = Read-RequiredValue -Prompt 'Enter openssl.cnf path' -DefaultValue ($(if ($defaultOpenSslConf) { $defaultOpenSslConf } else { '' }))
}
if (-not (Test-Path $OpenSslConf)) {
    throw "OPENSSL_CONF path not found: $OpenSslConf"
}
$OpenSslConf = (Resolve-Path $OpenSslConf).Path

$env:ACCESSPILOT_VENDOR_PHP = $PhpPath
$env:ACCESSPILOT_VENDOR_PRIVATE_KEY_PATH = $PrivateKeyPath
$env:ACCESSPILOT_LICENSE_PUBLIC_KEY_PATH = $PublicKeyPath
$env:OPENSSL_CONF = $OpenSslConf

Write-Host "`nVendor license environment loaded for this session." -ForegroundColor Green
Write-Host "ACCESSPILOT_VENDOR_PHP=$PhpPath" -ForegroundColor DarkGray
Write-Host "ACCESSPILOT_VENDOR_PRIVATE_KEY_PATH=$PrivateKeyPath" -ForegroundColor DarkGray
Write-Host "ACCESSPILOT_LICENSE_PUBLIC_KEY_PATH=$PublicKeyPath" -ForegroundColor DarkGray
Write-Host "OPENSSL_CONF=$OpenSslConf" -ForegroundColor DarkGray

if ($PersistLocalConfig) {
    $localConfigPath = Join-Path $scriptRoot 'vault\vendor-env.local.ps1'
    $content = @(
        '$env:ACCESSPILOT_VENDOR_PHP = ' + ("'" + $PhpPath.Replace("'", "''") + "'"),
        '$env:ACCESSPILOT_VENDOR_PRIVATE_KEY_PATH = ' + ("'" + $PrivateKeyPath.Replace("'", "''") + "'"),
        '$env:ACCESSPILOT_LICENSE_PUBLIC_KEY_PATH = ' + ("'" + $PublicKeyPath.Replace("'", "''") + "'"),
        '$env:OPENSSL_CONF = ' + ("'" + $OpenSslConf.Replace("'", "''") + "'")
    ) -join "`r`n"
    Set-Content -Path $localConfigPath -Value $content -Encoding UTF8
    Write-Host "Persisted local vendor env file: $localConfigPath" -ForegroundColor Yellow
    Write-Host "Load it later with: . `"$localConfigPath`"" -ForegroundColor Yellow
}

Write-Host "`nNext steps:" -ForegroundColor Cyan
Write-Host "1. If the private key does not exist yet, generate it intentionally with generator.php --allow-keygen." -ForegroundColor White
Write-Host "2. Use Issue-License.ps1 or Renew-License.ps1 from this same PowerShell session." -ForegroundColor White
