# prepare-client-release.ps1
# Use this script to create a clean version of the portal for the client.

$SourceDir = $PSScriptRoot + "\.."
$ReleaseDir = $PSScriptRoot + "\..\dist_release"

Write-Host "--- UM Portal Release Packager ---" -ForegroundColor Cyan

# 1. Create fresh release directory
if (Test-Path $ReleaseDir) {
    Write-Host "Cleaning old release directory..." -ForegroundColor Yellow
    Remove-Item -Path $ReleaseDir -Recurse -Force
}
New-Item -Path $ReleaseDir -ItemType Directory | Out-Null

# 2. Copy all files
Write-Host "Copying files to release folder..." -ForegroundColor Gray
Copy-Item -Path "$SourceDir\*" -Destination $ReleaseDir -Recurse -Exclude "dist_release", ".git", ".claude", ".synkron.syncdb"

# 3. Remove Vendor-Only sensitive tools
$SensitivePaths = @(
    "$ReleaseDir\scripts\license_admin_templates",
    "$ReleaseDir\analysis\codebase_upgrade_plan",
    "$ReleaseDir\docs\internal",
    "$ReleaseDir\docs\Technical",
    "$ReleaseDir\phperror8.5.4_nts.log"
)

foreach ($Path in $SensitivePaths) {
    if (Test-Path $Path) {
        Write-Host "Removing sensitive path: $Path" -ForegroundColor Red
        Remove-Item -Path $Path -Recurse -Force
    }
}

Write-Host "`n--- Release Prepared Successfully ---" -ForegroundColor Green
Write-Host "Location: $ReleaseDir" -ForegroundColor White
Write-Host "You can now zip the 'dist_release' folder and provide it to the client." -ForegroundColor Gray
