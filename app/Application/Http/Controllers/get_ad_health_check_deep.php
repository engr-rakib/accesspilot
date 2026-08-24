<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../Support/helpers.php';
require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Infrastructure/PowerShell/powershell_runner.php';
require_once __DIR__ . '/../../../Ldap/Config/ldap_config_repository.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    ob_clean();
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.']);
    exit();
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

if (!has_permission('action_ad_health_check')) {
    ob_clean();
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit();
}

if (!defined('API_GATEWAY')) {
    die('Direct access not permitted');
}

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tempReportFileName = 'ad_health_report_' . uniqid() . '.html';
    $tempReportFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $tempReportFileName;

    $config = ldap_read_config();
    $targetDC = (string) ($config['host'] ?? '');
    $bindPassword = ldap_read_bind_password();

    if ($targetDC === '' || $bindPassword === '') {
        $response['message'] = 'No bind credentials configured.';
        ob_clean();
        echo json_encode($response);
        exit();
    }

    // Step 1: Ensure Kerberos ticket using bind credentials
    $bindDn = (string) ($config['bind_dn'] ?? '');
    if ($bindDn === '') {
        $response['message'] = 'Bind DN not configured.';
        ob_clean();
        echo json_encode($response);
        exit();
    }

    // Always obtain a fresh ticket (ignore cached tickets)
    // Extract user principal and ensure realm is uppercase (Kerberos realm is always uppercase)
    $userUpn = str_replace("'", "''", $bindDn);
    if (strpos($userUpn, '@') !== false) {
        $parts = explode('@', $userUpn);
        $parts[1] = strtoupper($parts[1]);
        $userUpn = implode('@', $parts);
    }
    $pwd = str_replace("'", "''", $bindPassword);
    $keytab = '/tmp/health_krb5.keytab';
    $ktutilInput = "add_entry -password -p {$userUpn} -k 1 -e aes256-cts-hmac-sha1-96\n{$pwd}\nwrite_kt {$keytab}\nquit\n";
    $ktutilFile = tempnam(sys_get_temp_dir(), 'kt_') . '.txt';
    @file_put_contents($ktutilFile, $ktutilInput);
    exec('ktutil < ' . escapeshellarg($ktutilFile) . ' 2>/dev/null', $ktutilOut, $ktutilExit);
    @unlink($ktutilFile);
    if ($ktutilExit !== 0) {
        $response['message'] = 'Failed to create Kerberos keytab. Cannot connect to DC via WinRM.';
        ob_clean();
        echo json_encode($response);
        exit();
    }
    exec('kinit -k -t ' . escapeshellarg($keytab) . ' ' . escapeshellarg($userUpn) . ' 2>/dev/null', $kinitOut, $kinitExit);
    @unlink($keytab);
    if ($kinitExit !== 0) {
        $response['message'] = 'kinit failed. Cannot obtain Kerberos ticket for DC connection.';
        ob_clean();
        echo json_encode($response);
        exit();
    }

    // Step 2: Run the Kerberos-based health check (direct WinRM to DC, no credential config needed)
    $appName = config_get('app.app_info.name', 'AD Health Report');
    $appLogoPath = config_get('app.app_info.logo_path', '');
    $copyrightYear = config_get('ui.footer.copyright_year', '');
    $developerName = config_get('ui.footer.developer_name', '');
    $developerUrl = config_get('ui.footer.developer_url', '');
    $copyrightMessage = config_get('ui.footer.copyright_message', '');

    $psResult = powershell_run_script('AD_Health_Kerberos', [
        'TargetDC' => $targetDC,
        'OutputReportPath' => $tempReportFilePath,
        'AppName' => $appName,
        'AppLogoPath' => $appLogoPath,
        'CopyrightYear' => $copyrightYear,
        'DeveloperName' => $developerName,
        'DeveloperUrl' => $developerUrl,
        'CopyrightMessage' => $copyrightMessage,
    ], ['mode' => 'exec']);

    $return_var = $psResult['exit_code'];
    $output = $psResult['output'];

    // Step 3: Read the report
    if (file_exists($tempReportFilePath)) {
        $reportContent = file_get_contents($tempReportFilePath);
        if ($reportContent !== false) {
            $_SESSION['ad_health_report_html'] = $reportContent;

            $reportTitle = 'AD Health Check Report';
            $reportSummary = '';
            $dom = new DOMDocument();
            @$dom->loadHTML($reportContent);
            $titles = $dom->getElementsByTagName('title');
            if ($titles->length > 0) {
                $reportTitle = $titles->item(0)->textContent;
            }

            $xpath = new DOMXPath($dom);
            $execSummaryDivs = $xpath->query('//div[contains(@class, "card") and contains(@class, "mb-4") and contains(@class, "border-0")]');
            if ($execSummaryDivs->length > 0) {
                $h2 = $execSummaryDivs->item(0)->getElementsByTagName('h2');
                if ($h2->length > 0) {
                    $reportSummary = trim(str_replace('Executive Summary:', '', $h2->item(0)->textContent));
                }
                $ul = $execSummaryDivs->item(0)->getElementsByTagName('ul');
                if ($ul->length > 0) {
                    $findings = [];
                    foreach ($ul->item(0)->getElementsByTagName('li') as $li) {
                        $findings[] = trim($li->textContent);
                    }
                    if (!empty($findings)) {
                        $reportSummary .= "\nKey Findings:\n" . implode("\n", $findings);
                    }
                }
            }
            if (empty($reportSummary)) {
                $body = $dom->getElementsByTagName('body');
                if ($body->length > 0) {
                    $fullText = preg_replace("/\s+/", " ", $body->item(0)->textContent);
                    $reportSummary = substr($fullText, 0, 300) . (strlen($fullText) > 300 ? '...' : '');
                }
            }

            $response['success'] = true;
            $response['message'] = 'Deep health check completed successfully. Full report available for download.';
            $response['report_title'] = $reportTitle ?: 'Deep AD Health Check';
            $response['report_preview'] = $reportSummary;
            $response['active_domain_key'] = ldap_active_domain_key();
        } else {
            $response['message'] = 'Failed to read the generated report file.';
        }
        @unlink($tempReportFilePath);
    } else {
        $msg = 'Health check script did not produce a report. ';
        $msg .= 'Exit code: ' . $return_var . '. ';
        if (!empty($output)) {
            $outStr = is_array($output) ? implode("\n", $output) : $output;
            $msg .= 'Output: ' . substr($outStr, 0, 500);
        }
        $response['message'] = $msg;
    }
} else {
    $response['message'] = 'Invalid request method.';
}

ob_clean();
echo json_encode($response);
