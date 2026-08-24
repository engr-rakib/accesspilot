# =============================================================================
# AccessPilot — Windows/IIS installer (token-gated, live-system install)
#   $env:ACCESSPILOT_INSTALL_TOKEN='<your-token>'; .\install.ps1
# =============================================================================
$ErrorActionPreference = 'Stop'
$script:DistRepo = if ($env:ACCESSPILOT_DIST_REPO) { $env:ACCESSPILOT_DIST_REPO } else { 'engr-rakib/accesspilot-dist' }
$script:Dest     = if ($env:ACCESSPILOT_DEST) { $env:ACCESSPILOT_DEST } else { 'C:\inetpub\accesspilot' }
$script:Token    = $env:ACCESSPILOT_INSTALL_TOKEN
if (-not $script:Token) { $script:Token = Read-Host 'Enter your install token' }
if (-not $script:Token) { Write-Error 'No install token. Get one from Trendpilot (rakibcse47@gmail.com)'; exit 1 }

function Write-Step($m) { Write-Host "[accesspilot] $m" }

Write-Step 'Cloning product onto this machine...'
$tmp = Join-Path $env:TEMP ("ap_" + [guid]::NewGuid().ToString('N').Substring(0,8))
git clone -q --depth 1 "https://$($script:Token)@github.com/$($script:DistRepo).git" $tmp
if ($LASTEXITCODE -ne 0) { Write-Error 'clone failed - check your install token'; exit 1 }

Write-Step "Deploying to $($script:Dest)"
New-Item -ItemType Directory -Force -Path $script:Dest | Out-Null
robocopy $tmp $script:Dest /MIR /XD .git /NFL /NDL /NJH /NJS | Out-Null
Remove-Item -Recurse -Force $tmp
Write-Step 'Clone removed - code lives only on this server'

# --- IIS site + HTTPS binding ---
Import-Module WebAdministration -ErrorAction SilentlyContinue
$webRoot = Join-Path $script:Dest 'public'
$site = Get-WebSite | Where-Object { $_.physicalPath -eq $webRoot }
if (-not $site) {
  Write-Step "Registering IIS site 'AccessPilot'"
  $port = if ($env:APP_PORT) { [int]$env:APP_PORT } else { 443 }
  New-WebSite -Name 'AccessPilot' -PhysicalPath $webRoot -Port $port -Force | Out-Null
}
Restart-WebAppPool -Name 'DefaultAppPool' -ErrorAction SilentlyContinue
Write-Step 'DONE - open https://localhost/ (evaluation mode until a license is applied)'
