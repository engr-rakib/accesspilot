param([string]$Username)
$Username = if ($Username) { $Username.Trim() } else { "" }
if ($Username -eq "") { "[]"; exit 0 }
try {
    $events = Get-WinEvent -FilterHashtable @{LogName='Security';Id=4624} -MaxEvents 100 -ErrorAction SilentlyContinue
    $map = @{}
    foreach ($ev in $events) {
        $tu = $ev.Properties[5].Value
        if ($tu -eq $Username -or $tu -like "*\$Username" -or $tu -like "$Username@*") {
            $ws = $ev.Properties[11].Value
            if ($ws -and $ws -ne '-' -and $ws -ne '') {
                $t = $ev.TimeCreated.ToString('yyyy-MM-dd HH:mm:ss')
                if (-not $map.ContainsKey($ws) -or $map[$ws] -lt $t) { $map[$ws] = $t }
            }
        }
    }
    $out = $map.GetEnumerator() | Sort-Object Value -Descending | ForEach-Object {
        [PSCustomObject]@{ workstation = $_.Key; time = $_.Value }
    }
    if ($out) { $out | ConvertTo-Json -Compress } else { "[]" }
    exit 0
} catch { "[]"; exit 0 }
