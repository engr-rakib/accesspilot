param (
    [string]$Usernames,
    [string]$ExecutedBy
)

$ExecutedBy = if ($ExecutedBy) { $ExecutedBy.Trim() } else { "UNKNOWN" }
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
. "$scriptDir\ldap_ad_helpers.ps1"

$results = @()
$totalFound = 0; $totalAdOnly = 0; $totalHrmsOnly = 0; $totalNotFound = 0; $totalErrors = 0

try {
    $idList = @($Usernames -split ',' | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne '' })
    $hrmsApiBase = 'https://whrmsapi.waltonbd.com/info/emp_info.php'

    foreach ($input in $idList) {
        $row = [PSCustomObject]@{
            HRMS_ID     = $input
            Logon_ID    = 'N/A'
            EMP_NAME    = 'N/A'
            AD_Name     = 'N/A'
            DESIGNATION = 'N/A'
            HRMS_STATUS = 'N/A'
            AD_STATUS   = 'N/A'
            Find_Status = 'Not Found'
        }
        $empName = 'N/A'; $hrmsStatus = 'N/A'; $hrmsCode = ''; $designation = 'N/A'

        try {
            # HRMS API with input
            try {
                $apiUrl = "$hrmsApiBase?emp_id=$([System.Uri]::EscapeDataString($input))"
                $apiResponse = Invoke-RestMethod -Uri $apiUrl -Method Get -TimeoutSec 5 -ErrorAction Stop
                if ($apiResponse -and $apiResponse.EMP_ID) {
                    $empName = if ($apiResponse.EMP_NAME) { $apiResponse.EMP_NAME } else { 'N/A' }
                    $hrmsStatus = if ($apiResponse.EMP_STS) { $apiResponse.EMP_STS } else { 'N/A' }
                    $designation = if ($apiResponse.DESIGNATION) { $apiResponse.DESIGNATION } else { 'N/A' }
                    $hrmsCode = if ($apiResponse.EMP_CODE) { $apiResponse.EMP_CODE } else { $apiResponse.EMP_ID }
                }
            } catch { }

            # Extract numeric part
            $inputNum = ''
            if ($input -match '(\d+)$') { $inputNum = $matches[1] }

            # Retry HRMS with numeric part
            if (-not $hrmsCode -and $inputNum -and $inputNum -ne $input) {
                try {
                    $apiUrl = "$hrmsApiBase?emp_id=$([System.Uri]::EscapeDataString($inputNum))"
                    $apiResponse = Invoke-RestMethod -Uri $apiUrl -Method Get -TimeoutSec 5 -ErrorAction Stop
                    if ($apiResponse -and $apiResponse.EMP_ID) {
                        $empName = if ($apiResponse.EMP_NAME) { $apiResponse.EMP_NAME } else { 'N/A' }
                        $hrmsStatus = if ($apiResponse.EMP_STS) { $apiResponse.EMP_STS } else { 'N/A' }
                        $designation = if ($apiResponse.DESIGNATION) { $apiResponse.DESIGNATION } else { 'N/A' }
                        $hrmsCode = if ($apiResponse.EMP_CODE) { $apiResponse.EMP_CODE } else { $apiResponse.EMP_ID }
                    }
                } catch { }
            }

            if ($hrmsCode) { $row.HRMS_ID = $hrmsCode }
            $row.EMP_NAME = $empName
            $row.DESIGNATION = $designation
            $row.HRMS_STATUS = $hrmsStatus

            # AD lookup
            init-ldap
            $adFound = $false; $adSam = ''; $adDisplay = ''; $adEnabled = $false
            $empCode = if ($hrmsCode) { $hrmsCode } else { $input }
            $searchIds = @($input, $empCode)
            if ($inputNum -and $inputNum -ne $input) { $searchIds += $inputNum }
            $searchIds = $searchIds | Select-Object -Unique

            foreach ($sid in $searchIds) {
                if ([string]::IsNullOrEmpty($sid)) { continue }
                $f = "(&(objectCategory=person)(objectClass=user)(samAccountName=$sid))"
                $raw = ldap-search -Filter $f -Props @("samAccountName","displayName","userAccountControl","employeeID")
                $users = ldap-result $raw @("samAccountName","displayName","userAccountControl","employeeID")
                if ($users -and $users.Count -gt 0) {
                    $adSam = $users[0].samAccountName
                    $adDisplay = $users[0].displayName
                    $uac = [int]($users[0].userAccountControl -as [int])
                    $adEnabled = ($uac -band 2) -eq 0
                    $adFound = $true

                    # If HRMS still not found, try employeeID from AD
                    if (-not $hrmsCode) {
                        $adEmpId = $users[0].employeeID
                        if ($adEmpId) {
                            try {
                                $apiUrl = "$hrmsApiBase?emp_id=$([System.Uri]::EscapeDataString($adEmpId))"
                                $apiResponse = Invoke-RestMethod -Uri $apiUrl -Method Get -TimeoutSec 5 -ErrorAction Stop
                                if ($apiResponse -and $apiResponse.EMP_ID) {
                                    $empName = if ($apiResponse.EMP_NAME) { $apiResponse.EMP_NAME } else { 'N/A' }
                                    $hrmsStatus = if ($apiResponse.EMP_STS) { $apiResponse.EMP_STS } else { 'N/A' }
                                    $designation = if ($apiResponse.DESIGNATION) { $apiResponse.DESIGNATION } else { 'N/A' }
                                    $hrmsCode = if ($apiResponse.EMP_CODE) { $apiResponse.EMP_CODE } else { $apiResponse.EMP_ID }
                                    if ($hrmsCode) { $row.HRMS_ID = $hrmsCode }
                                    $row.EMP_NAME = $empName
                                    $row.DESIGNATION = $designation
                                    $row.HRMS_STATUS = $hrmsStatus
                                }
                            } catch { }
                        }
                    }
                    break
                }
            }

            if (-not $adFound) {
                if ($inputNum) {
                    $f = "(&(objectCategory=person)(objectClass=user)(samAccountName=*$inputNum*))"
                    $raw = ldap-search -Filter $f -Props @("samAccountName","displayName","userAccountControl") -SearchBase $null
                    $users = ldap-result $raw @("samAccountName","displayName","userAccountControl")
                    if ($users -and $users.Count -gt 0) {
                        $adSam = $users[0].samAccountName
                        $adDisplay = $users[0].displayName
                        $uac = [int]($users[0].userAccountControl -as [int])
                        $adEnabled = ($uac -band 2) -eq 0
                        $adFound = $true
                    }
                }
            }

            if ($adFound) {
                $row.Logon_ID = $adSam
                $row.AD_Name = if ($adDisplay) { $adDisplay } else { 'N/A' }
                $row.AD_STATUS = if ($adEnabled) { 'Enabled' } else { 'Disabled' }
                if ($hrmsCode) {
                    $row.Find_Status = 'Found'
                    $totalFound++
                } else {
                    $row.Find_Status = 'AD Only'
                    $totalAdOnly++
                }
            } elseif ($hrmsCode) {
                $row.AD_STATUS = 'Not Created'
                $row.Find_Status = 'HRMS Only'
                $totalHrmsOnly++
            } else {
                $row.Find_Status = 'Not Found'
                $totalNotFound++
            }
        } catch {
            $totalErrors++
            $row.Find_Status = 'Error'
        }
        $results += $row
    }

    $totalProcessed = $results.Count
    $parts = @("Processed: $totalProcessed")
    if ($totalFound -gt 0) { $parts += "Found: $totalFound" }
    if ($totalAdOnly -gt 0) { $parts += "AD Only: $totalAdOnly" }
    if ($totalHrmsOnly -gt 0) { $parts += "HRMS Only: $totalHrmsOnly" }
    if ($totalNotFound -gt 0) { $parts += "Not Found: $totalNotFound" }
    if ($totalErrors -gt 0) { $parts += "Errors: $totalErrors" }
    $summary = $parts -join ', '

    # Output CSV
    $csv = @()
    $csv += '"HRMS_ID","Logon_ID","EMP_NAME","AD_Name","DESIGNATION","HRMS_STATUS","AD_STATUS","Find_Status"'
    foreach ($r in $results) {
        $csv += "`"$($r.HRMS_ID)`",`"$($r.Logon_ID)`",`"$($r.EMP_NAME)`",`"$($r.AD_Name)`",`"$($r.DESIGNATION)`",`"$($r.HRMS_STATUS)`",`"$($r.AD_STATUS)`",`"$($r.Find_Status)`""
    }
    $csv -join "`n"
} catch {
    "ERROR: $($_.Exception.Message)"
    exit 1
}
