param (
    [Parameter(Mandatory=$true)]
    [string[]]$Usernames,
    [Parameter(Mandatory=$true)]
    [string]$ExecutedBy,
    [Parameter(Mandatory=$true)]
    [string]$SecureConfigPath,
    [string]$SharedConfigPath = '',
    [string]$OuConfigJson = '',
    [string]$GroupConfigJson = ''
)

# --- Clean input ---
$ExecutedBy = $ExecutedBy.Trim()
if ([string]::IsNullOrEmpty($ExecutedBy)) { $ExecutedBy = "UNKNOWN" }

# --- Transcript log setup ---
try {
    $secureConfig = Get-Content -Path $SecureConfigPath -Raw | ConvertFrom-Json

    $resolvedSharedConfigPath = $SharedConfigPath
    if ([string]::IsNullOrEmpty($resolvedSharedConfigPath)) {
        $resolvedSharedConfigPath = Join-Path (Split-Path $SecureConfigPath -Parent) "shared_config.json"
    }

    $activeDomainAdName = $null
    if (Test-Path $resolvedSharedConfigPath -PathType Leaf) {
        $sharedConfig = Get-Content $resolvedSharedConfigPath -Raw | ConvertFrom-Json
        $activeDomainAdName = $sharedConfig.active_domain_ad_name
    }
    if ([string]::IsNullOrEmpty($activeDomainAdName)) {
        $activeDomainAdName = $secureConfig.domain_name
    }
    if ([string]::IsNullOrEmpty($activeDomainAdName)) { $activeDomainAdName = "default" }

    $BaseLogPath = $secureConfig.BaseLogPath
    $transcriptLogFolder = Join-Path $BaseLogPath "$activeDomainAdName\scripts_logs\User_Management\NewUser\New_user_transcript_logs"
    $transcriptLogFile = Join-Path $transcriptLogFolder "audit-$(Get-Date -Format yyyy-MM-dd).log"

    if (!(Test-Path $transcriptLogFolder -PathType Container)) {
        $null = New-Item -ItemType Directory -Path $transcriptLogFolder -Force -ErrorAction Stop
    }
} catch {
    $transcriptLogFile = $null
}

# --- Call the core script ---
try {
    $ScriptPath = "$PSScriptRoot\create-user-core.ps1"
    if (-not (Test-Path $ScriptPath)) {
        $errorMessage = "Core script 'create-user-core.ps1' not found."
        Write-Output (ConvertTo-Json @{ success = $false; message = $errorMessage })
        exit 1
    }

    # Capture ALL output streams from the core script (stdout + info/warn/error/debug)
    $allOutput = & $ScriptPath -Usernames $Usernames -SecureConfigPath $SecureConfigPath -SharedConfigPath $SharedConfigPath -ExecutedBy $ExecutedBy -OuConfigJson $OuConfigJson -GroupConfigJson $GroupConfigJson *>&1

    # Write every line of captured output to transcript log
    if ($transcriptLogFile) {
        $allOutput | ForEach-Object { "$_" } | Add-Content -Path $transcriptLogFile -ErrorAction SilentlyContinue
    }

    # Extract the JSON output (last non-empty object is the final JSON)
    $jsonOutput = $allOutput | Select-Object -Last 1
    Write-Output $jsonOutput
} catch {
    $errorMessage = "An unexpected error occurred in the wrapper script: $($_.Exception.Message)"
    Write-Output (ConvertTo-Json @{ success = $false; message = $errorMessage })
    exit 1
}

exit 0
