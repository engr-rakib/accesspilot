<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../Support/helpers.php';
require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Infrastructure/PowerShell/powershell_runner.php';
require_once __DIR__ . '/../../../Ldap/Config/ldap_config_repository.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

if (!defined('API_GATEWAY')) {
    die('Direct access not permitted');
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$username = trim($_POST['username'] ?? '');
if ($username === '') {
    echo json_encode(['success' => false, 'message' => 'Username required.', 'workstations' => []]);
    exit();
}

set_time_limit(60);

session_write_close();

// Obtain Kerberos ticket from LDAP bind credentials
$config = ldap_read_config();
$targetDC = (string) ($config['host'] ?? '');
$bindDn = (string) ($config['bind_dn'] ?? '');
$bindPassword = ldap_read_bind_password();
if ($targetDC !== '' && $bindDn !== '' && $bindPassword !== '') {
    $userUpn = str_replace("'", "''", $bindDn);
    if (strpos($userUpn, '@') !== false) {
        $parts = explode('@', $userUpn);
        $parts[1] = strtoupper($parts[1]);
        $userUpn = implode('@', $parts);
    }
    $pwd = str_replace("'", "''", $bindPassword);
    $keytab = '/tmp/sec_krb5.keytab';
    $ktutilInput = "add_entry -password -p {$userUpn} -k 1 -e aes256-cts-hmac-sha1-96\n{$pwd}\nwrite_kt {$keytab}\nquit\n";
    $ktutilFile = tempnam(sys_get_temp_dir(), 'kt_') . '.txt';
    @file_put_contents($ktutilFile, $ktutilInput);
    exec('ktutil < ' . escapeshellarg($ktutilFile) . ' 2>/dev/null', $ktutilOut, $ktutilExit);
    @unlink($ktutilFile);
    if ($ktutilExit === 0) {
        exec('kinit -k -t ' . escapeshellarg($keytab) . ' ' . escapeshellarg($userUpn) . ' 2>/dev/null', $kinitOut, $kinitExit);
        @unlink($keytab);
    }
}

$escTargetDC = escapeshellarg($targetDC);
$escUsername = escapeshellarg($username);
$script = <<<PSEOF
[CmdletBinding()]Param([string]\$TargetDC={$escTargetDC}, [string]\$Username={$escUsername})

\$ErrorActionPreference = 'Stop'
try {
    Import-Module PSWSMan -ErrorAction Stop
    \$session = New-PSSession -ComputerName \$TargetDC -Authentication Kerberos -ErrorAction Stop
    \$raw = Invoke-Command -Session \$session -ArgumentList \$Username -ScriptBlock {
        param(\$u)
        \$ws = @()
        Import-Module ActiveDirectory -ErrorAction Stop
        
        # 1. AD userWorkstations attribute
        try {
            \$adUser = Get-ADUser -Identity \$u -Properties userWorkstations, DistinguishedName -ErrorAction SilentlyContinue
            if (\$adUser -and \$adUser.userWorkstations) {
                \$ws += \$adUser.userWorkstations -split ',' | ForEach-Object { \$_.Trim() } | Where-Object { \$_ -ne '' }
            }
            \$userDn = if (\$adUser) { \$adUser.DistinguishedName } else { \$null }
        } catch { \$userDn = \$null }
        
        # 2. Computer accounts where description matches username
        try {
            \$comps = Get-ADComputer -Filter "description -like '*\$u*'" -Properties Name, Description -ErrorAction SilentlyContinue
            foreach (\$c in \$comps) { if (\$ws -notcontains \$c.Name) { \$ws += \$c.Name } }
        } catch { }
        
        # 3. Computer accounts where managedBy matches user
        if (\$userDn) {
            try {
                \$mngComps = Get-ADComputer -Filter "managedBy -eq '\$userDn'" -Properties Name -ErrorAction SilentlyContinue
                foreach (\$c in \$mngComps) { if (\$ws -notcontains \$c.Name) { \$ws += \$c.Name } }
            } catch { }
        }
        
        # 4. Scan security events for source IPs
        try {
            \$evts = Get-WinEvent -LogName Security -FilterXPath "*[System[(EventID=4624)]]" -MaxEvents 500 -ErrorAction SilentlyContinue
            foreach (\$e in \$evts) {
                \$xml = [xml]\$e.ToXml()
                \$data = @{}
                foreach (\$d in \$xml.Event.EventData.Data) { \$data[\$d.Name] = \$d.'#text' }
                \$tu = \$data['TargetUserName']
                \$ip = \$data['IpAddress']
                if (\$tu -eq \$u) {
                    if (\$ip -and \$ip -ne '-' -and \$ip -ne '' -and \$ip -notmatch '^127\.|^0\.|^::1$') {
                        if (\$ws -notcontains \$ip) { \$ws += \$ip }
                    }
                }
            }
        } catch { }
        
        @(\$ws | Sort-Object -Unique)
    }
    Remove-PSSession \$session
    \$workstations = @(\$raw | ForEach-Object { if (\$_ -is [array]) { \$_ } else { \$_ } })
    \$workstations = @(\$workstations | Where-Object { \$_ -and \$_ -ne '' } | Sort-Object -Unique)
    \$payload = @{ success = \$true; workstations = \$workstations }
    Write-Output "---DATA_START---"
    Write-Output (\$payload | ConvertTo-Json -Compress)
    Write-Output "---DATA_END---"
} catch {
    \$payload = @{ success = \$false; error = \$_.Exception.Message }
    Write-Output "---DATA_START---"
    Write-Output (\$payload | ConvertTo-Json -Compress)
    Write-Output "---DATA_END---"
}
PSEOF;

$result = powershell_run_inline($script, ['non_interactive' => true, 'mode' => 'exec']);

if ($result['success']) {
    $output = $result['output'];
    if (preg_match('/---DATA_START---\s*(.*?)\s*---DATA_END---/s', $output, $m)) {
        $decoded = json_decode($m[1], true);
        if ($decoded) {
            $workstations = $decoded['workstations'] ?? [];
            // Resolve IPs to hostnames via PHP DNS + check AD computer existence
            $filtered = [];
            foreach ($workstations as $ws) {
                if (filter_var($ws, FILTER_VALIDATE_IP)) {
                    $hostname = gethostbyaddr($ws);
                    if ($hostname && $hostname !== $ws) {
                        $netbios = strtolower(strstr($hostname, '.', true) ?: $hostname);
                        $filtered[] = "$netbios (from DNS: $ws)";
                    } else {
                        $filtered[] = $ws;
                    }
                } else {
                    $filtered[] = $ws;
                }
            }
            echo json_encode([
                'success' => !empty($decoded['success']),
                'workstations' => $filtered,
                'message' => $decoded['error'] ?? ''
            ]);
            exit();
        }
    }
}

echo json_encode(['success' => false, 'workstations' => [], 'message' => 'Failed to lookup workstations.']);
