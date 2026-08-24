<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../Support/helpers.php';
require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Ldap/Router/ad_operation_router.php';

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
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to view AD Health Check reports.']);
    exit();
}

if (!defined('API_GATEWAY')) {
    die('Direct access not permitted');
}

header('Content-Type: application/json');

$appName = config_get('app.app_info.name', 'AD Health Report');
$appLogoPath = config_get('app.app_info.logo_path', '');
$copyrightYear = config_get('ui.footer.copyright_year', '');
$developerName = config_get('ui.footer.developer_name', '');
$developerUrl = config_get('ui.footer.developer_url', '');
$copyrightMessage = config_get('ui.footer.copyright_message', '');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tempReportFileName = 'ad_health_report_' . uniqid() . '.html';
    $tempReportFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $tempReportFileName;

    $psResult = ad_dispatch_report_operation('ad_health_check', [
        'ReportFile' => true,
        'OutputReportPath' => $tempReportFilePath,
        'AppName' => $appName,
        'AppLogoPath' => $appLogoPath,
        'CopyrightYear' => $copyrightYear,
        'DeveloperName' => $developerName,
        'DeveloperUrl' => $developerUrl,
        'CopyrightMessage' => $copyrightMessage,
    ]);
    $return_var = $psResult['exit_code'];
    $output = $psResult['output'];
    $jsonValid = !empty($psResult['json_valid']);
    $decoded = $psResult['decoded'] ?? null;

    error_log("Health check result — exit_code: $return_var, json_valid: " . ($jsonValid ? 'true' : 'false') . ", file_exists: " . (file_exists($tempReportFilePath) ? 'true' : 'false'));

    // HTML report file path (written by either PowerShell or LDAP handler)
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

            $executiveSummary = '';
            $keyFindings = [];
            $xpath = new DOMXPath($dom);
            $executiveSummaryDivs = $xpath->query('//div[contains(@class, "card") and contains(@class, "mb-4") and contains(@class, "border-0")]');

            if ($executiveSummaryDivs->length > 0) {
                $executiveSummaryDiv = $executiveSummaryDivs->item(0);
                $h2 = $executiveSummaryDiv->getElementsByTagName('h2');
                if ($h2->length > 0) {
                    $executiveSummary = str_replace('Executive Summary:', '', $h2->item(0)->textContent);
                    $executiveSummary = trim($executiveSummary);
                }

                $ul = $executiveSummaryDiv->getElementsByTagName('ul');
                if ($ul->length > 0) {
                    foreach ($ul->item(0)->getElementsByTagName('li') as $li) {
                        $keyFindings[] = trim($li->textContent);
                    }
                }
            }

            $body = $dom->getElementsByTagName('body');
            $fullBodyText = '';
            if ($body->length > 0) {
                $fullBodyText = trim($body->item(0)->textContent);
                $fullBodyText = preg_replace("/\s+/", " ", $fullBodyText);
            }

            $reportSummary = '';
            if (!empty($executiveSummary) || !empty($keyFindings)) {
                $keyFindingsString = '';
                if (count($keyFindings) === 1 && strpos($keyFindings[0], 'No critical issues found') !== false) {
                    $keyFindingsString = $keyFindings[0];
                } elseif (!empty($keyFindings)) {
                    $keyFindingsString = "\n" . implode("\n", $keyFindings);
                }
                $reportSummary = "Overall Health: " . $executiveSummary . "\nKey Findings:" . $keyFindingsString;
            }

            if ($jsonValid && is_array($decoded) && isset($decoded['results'])) {
                $results = $decoded['results'];
                $sc = $decoded['successCount'] ?? 0;
                $fc = $decoded['failCount'] ?? 0;
                $lines = [];
                $lines[] = ($fc === 0 ? "✓ Healthy — All {$sc} checks passed." : "⚠ {$fc} check(s) need attention out of " . count($results) . " total checks.");
                $lines[] = '';
                foreach ($results as $r) {
                    $s = $r['status'];
                    $icon = $s === 'PASS' ? '✓' : ($s === 'FAIL' ? '✗' : ($s === 'WARN' ? '⚠' : '·'));
                    $lines[] = "{$icon}  {$r['test']} — {$s}";
                    $detail = trim($r['detail'] ?? '');
                    if ($detail !== '') {
                        $lines[] = "    {$detail}";
                    }
                }
                $response['message'] = implode("\n", $lines);
            } else {
                $response['message'] = $fullBodyText ?: ($reportSummary ?: 'AD Health Check completed.');
            }

            $response['success'] = true;
            $response['report_title'] = $reportTitle;
            $response['report_preview'] = $reportSummary ?: ($fullBodyText ? substr($fullBodyText, 0, 400) . (strlen($fullBodyText) > 400 ? '...' : '') : '');
        } else {
            $feedback = ldap_feedback_troubleshoot('ad_health_check', $psResult);
            $response = array_merge($response, $feedback);
        }
        @unlink($tempReportFilePath);

    // LDAP JSON fallback (if no HTML file was written)
    } elseif ($jsonValid && is_array($decoded) && isset($decoded['results'])) {
        $results = $decoded['results'];
        $sc = $decoded['successCount'] ?? 0;
        $fc = $decoded['failCount'] ?? 0;
        $reportTitle = 'AD Health Check';
        $_SESSION['ad_health_report_html'] = "<html><head><title>{$reportTitle}</title></head><body style='font-family:sans-serif;padding:20px;'></body></html>";

        $lines = [];
        $lines[] = ($fc === 0 ? "✓ Healthy — All {$sc} checks passed." : "⚠ {$fc} check(s) need attention out of " . count($results) . " total checks.");
        $lines[] = '';
        foreach ($results as $r) {
            $s = $r['status'];
            $icon = $s === 'PASS' ? '✓' : ($s === 'FAIL' ? '✗' : ($s === 'WARN' ? '⚠' : '·'));
            $lines[] = "{$icon}  {$r['test']} — {$s}";
            $detail = trim($r['detail'] ?? '');
            if ($detail !== '') {
                $lines[] = "    {$detail}";
            }
        }
        $reportSummary = "{$sc} passed, {$fc} " . ($fc === 1 ? 'issue' : 'issues');

        $response['success'] = true;
        $response['message'] = implode("\n", $lines);
        $response['report_title'] = $reportTitle;
        $response['report_preview'] = $reportSummary;

    } else {
        $feedback = ldap_feedback_troubleshoot('ad_health_check', $psResult);
        $response = array_merge($response, $feedback);
    }
} else {
    $response['message'] = 'Invalid request method.';
}

ob_clean();
echo json_encode($response);
