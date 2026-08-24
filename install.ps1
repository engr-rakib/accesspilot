# AccessPilot — Windows (IIS) one-line installer
#   irm https://github.com/<OWNER>/<REPO>/releases/latest/download/install.ps1 | iex
#
# Downloads the tagged source tarball, deploys to C:\inetpub\accesspilot,
# guarantees PHP 8.5 + extensions, and registers the IIS site + HTTPS binding.
# The app boots in READ-ONLY EVALUATION mode until a license certificate is applied.
# =============================================================================

$ErrorActionPreference = 'Stop'

# --- Edit these once if you self-host this script -----------------------------
$script:Owner   = 'ACCESSPILOT_GH_OWNER'
$script:Repo    = 'ACCESSPILOT_GH_REPO'
$script:Ref     = if ($env:ACCESSPILOT_REF) { $env:ACCESSPILOT_REF } else { 'latest' }
$script:Dest    = if ($env:ACCESSPILOT_DEST) { $env:ACCESSPILOT_DEST } else { 'C:\inetpub\accesspilot' }
$script:WebRoot = "$script:Dest\public"

function Write-Step([string]$m){ Write-Host "[accesspilot] $m" }

# --- 1. Prerequisites ----------------------------------------------------------
Write-Step "Welcome to the AccessPilot installer"
$php = Get-Command php -ErrorAction SilentlyContinue
if (-not $php) { throw 'PHP is required. Install PHP 8.5 NTS and add php.exe to PATH, then re-run.' }

# --- 2. Fetch + verify release tarball ------------------------------------------
$base = "https://github.com/$script:Owner/$script:Repo/releases/$($script:Ref -eq 'latest' ? 'latest' : "download/$script:Ref")"
Write-Step "Downloading release from $base"
$tmp = Join-Path $env:TEMP 'accesspilot-install'
New-Item -ItemType Directory -Force -Path $tmp | Out-Null
$zip = Join-Path $tmp 'accesspilot.zip'
Invoke-WebRequest "$base/download/accesspilot.zip" -OutFile $zip -UseBasicParsing
Write-Step "Verifying SHA256"
$sums = Invoke-WebRequest "$base/download/SHA256SUMS" -UseBasicParsing
$hash = (Get-FileHash $zip -Algorithm SHA256).Hash.ToLowerInvariant()
if ($sums.Content -notmatch [regex]::Escape($hash)) { throw 'Checksum mismatch — aborting' }
Write-Step "Checksum OK"

# --- 3. Deploy -------------------------------------------------------------------
Write-Step "Deploying to $script:Dest"
Expand-Archive -Path $zip -DestinationPath $tmp\extract -Force
New-Item -ItemType Directory -Force -Path $script:Dest | Out-Null
Copy-Item -Path "$tmp\extract\*" -Destination $script:Dest -Recurse -Force

# --- 4. IIS site + HTTPS binding ---------------------------------------------------
Import-Module WebAdministration -ErrorAction SilentlyContinue
$site = Get-WebSite | Where-Object { $_.physicalPath -eq $script:WebRoot }
if (-not $site) {
  Write-Step "Registering IIS site 'AccessPilot'"
  $port = if ($env:APP_PORT) { [int]$env:APP_PORT } else { 443 }
  New-WebSite -Name 'AccessPilot' -PhysicalPath $script:WebRoot -Port $port -Force | Out-Null
}
Write-Step "Restarting site"
Restart-WebAppPool -Name 'DefaultAppPool' -ErrorAction SilentlyContinue

Write-Host ""
Write-Step "DONE — AccessPilot is starting at https://localhost/"
Write-Step "Change the seeded admin password immediately (SECURITY.md)."
Write-Step "Portal is READ-ONLY EVALUATION mode until a license is applied."
Write-Step "To unlock operations: purchase a license -> License Center -> Apply Certificate."