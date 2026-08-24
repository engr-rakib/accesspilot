# scripts/license_admin_templates/Renew-License.ps1

function Resolve-PhpPath {
    if ($env:ACCESSPILOT_VENDOR_PHP -and (Test-Path $env:ACCESSPILOT_VENDOR_PHP)) {
        return $env:ACCESSPILOT_VENDOR_PHP
    }

    $candidates = @(
        "C:\php8.5.4_nts\php.exe",
        "C:\php\php.exe"
    )

    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) {
            return $candidate
        }
    }

    throw "PHP executable not found. Set ACCESSPILOT_VENDOR_PHP to a valid php.exe path."
}

$PHP_PATH = Resolve-PhpPath
if (-not $env:OPENSSL_CONF) {
    $candidateOpenSslConf = Join-Path (Split-Path $PHP_PATH -Parent) "extras\ssl\openssl.cnf"
    if (Test-Path $candidateOpenSslConf) {
        $env:OPENSSL_CONF = $candidateOpenSslConf
    }
}

Write-Host "--- UM Portal: Renew Existing License ---" -ForegroundColor Yellow

$DeployID = Read-Host "Enter Deployment ID"

$DecodeScript = Join-Path $PSScriptRoot "core\decode_deploy.php"
$DecodeResult = & $PHP_PATH $DecodeScript --deployment-id="$DeployID" 2>$null
$Decoded = $DecodeResult | ConvertFrom-Json -ErrorAction SilentlyContinue

$Domain = ""
$ClientName = ""

if ($Decoded -and $Decoded.success -and $Decoded.org_name) {
    $ClientName = $Decoded.org_name
    $Domain = $Decoded.domain_name
    Write-Host "`n✅ Deployment ID auto-decoded:" -ForegroundColor Green
    Write-Host "   Client Name: $ClientName" -ForegroundColor White
    Write-Host "   Domain Name: $Domain" -ForegroundColor White
    $confirm = Read-Host "`nUse these values? (Y/n, default: Y)"
    if ($confirm -eq "n" -or $confirm -eq "N") {
        $ClientName = Read-Host "Confirm Client Name"
        $Domain = Read-Host "Enter Client Domain to Renew"
    }
} else {
    Write-Host "⚠️  Could not auto-decode Deployment ID. Enter details manually." -ForegroundColor Yellow
    $Domain = Read-Host "Enter Client Domain to Renew"
    $ClientName = Read-Host "Confirm Client Name"
}

$NewExpiry = Read-Host "Enter New Expiry Date (YYYY-MM-DD)"
$MaxDomains = Read-Host "Enter max domains (0=unlimited, or 1,2,3,5; press Enter for default 1)"
if ([string]::IsNullOrWhiteSpace($MaxDomains)) { $MaxDomains = "1" }
$LicenseID  = "REN-" + (Get-Date -Format "yyyyMMdd") + "-" + (Get-Random -Minimum 1000 -Maximum 9999)

$Product = "AccessPilot"

# PEM output directory
$SecureBase = if ($env:ACCESSPILOT_SECURE_BASE_PATH) { $env:ACCESSPILOT_SECURE_BASE_PATH } else { "C:/inetpub/Desk_secure_files" }
$OutputDirRaw = "$SecureBase\vendor_issued_licenses"
if (!(Test-Path $OutputDirRaw)) { New-Item -Path $OutputDirRaw -ItemType Directory | Out-Null }
$OutputDir = (Get-Item $OutputDirRaw).FullName

$JSON = & $PHP_PATH (Join-Path $PSScriptRoot "core\generator.php") `
    --id="$LicenseID" `
    --product="$Product" `
    --client="$ClientName" `
    --domain="$Domain" `
    --deployment-id="$DeployID" `
    --expiry="$NewExpiry" `
    --max-domains="$MaxDomains" `
    --allow-keygen

if ($LASTEXITCODE -ne 0) {
    throw "License renewal generation failed."
}

function ConvertTo-PemLicense {
    param([string]$JsonContent)
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($JsonContent)
    $b64 = [Convert]::ToBase64String($bytes)
    $lines = for ($i = 0; $i -lt $b64.Length; $i += 64) {
        $b64.Substring($i, [Math]::Min(64, $b64.Length - $i))
    }
    return "-----BEGIN LICENSE-----`n$($lines -join "`n")`n-----END LICENSE-----"
}

$SafeName = $ClientName -replace '[^\w\.\-]', '_'
$BaseName = "$SafeName`_$LicenseID"

# Save PEM to vendor_issued_licenses/
$PemFile = "$OutputDir\$BaseName.pem"
$PEM = ConvertTo-PemLicense -JsonContent $JSON
[System.IO.File]::WriteAllText($PemFile, $PEM, [System.Text.UTF8Encoding]::new($false))

# Register in tracking card
$RegisterScript = Join-Path $PSScriptRoot "core\register_license.php"
& $PHP_PATH $RegisterScript --file="$PemFile" 2>$null | Out-Null

Write-Host "`nRENEWAL GENERATED!" -ForegroundColor Green
Write-Host "Renewal File: $PemFile" -ForegroundColor White
Write-Host "`nPEM format wraps the signed JSON in -----BEGIN LICENSE----- / -----END LICENSE----- headers." -ForegroundColor Cyan
Write-Host "Upload this .pem file to the client's Vendor Console to apply the license." -ForegroundColor Gray

if ($env:ACCESSPILOT_VENDOR_PRIVATE_KEY_PATH) {
    Write-Host "Private key source: $env:ACCESSPILOT_VENDOR_PRIVATE_KEY_PATH" -ForegroundColor DarkGray
}
