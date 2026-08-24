<?php

if (!function_exists('mt_check_bimi')) {
    function mt_check_bimi(string $domain): array
    {
        require_once __DIR__ . '/../Dns/dns_resolver.php';
        $records = dns_resolve_txt('default._bimi.' . $domain);
        $bimiRecords = [];
        $brandUrl = null;
        foreach ($records as $txt) {
            if (stripos($txt, 'v=BIMI1') === 0) {
                $bimiRecords[] = $txt;
                if (preg_match('/l=([^\s;]+)/i', $txt, $m)) {
                    $brandUrl = $m[1];
                }
            }
        }
        return [
            'has_bimi' => count($bimiRecords) > 0,
            'records' => $bimiRecords,
            'count' => count($bimiRecords),
            'logo_url' => $brandUrl,
        ];
    }
}

if (!function_exists('mt_check_mta_sts')) {
    function mt_check_mta_sts(string $domain): array
    {
        require_once __DIR__ . '/../Dns/dns_resolver.php';
        $txt = dns_resolve_txt('_mta-sts.' . $domain);
        $stsRecords = [];
        $version = null;
        $mode = null;
        foreach ($txt as $r) {
            if (stripos($r, 'v=STSv1') === 0) {
                $stsRecords[] = $r;
                if (preg_match('/v=STSv1/i', $r)) $version = 'STSv1';
                if (preg_match('/id=(\d+)/i', $r, $m)) $version = 'STSv1 (id=' . $m[1] . ')';
            }
        }
        $policyUrl = 'https://mta-sts.' . $domain . '/.well-known/mta-sts.txt';
        $policyContent = null;
        $policyError = null;
        $ctx = stream_context_create(['http' => ['timeout' => 5, 'user_agent' => 'EmailTools/1.0']]);
        $policyContent = @file_get_contents($policyUrl, false, $ctx);
        if ($policyContent === false) {
            $policyError = 'Could not fetch policy (HTTP error)';
        } else {
            if (preg_match('/^mode:\s*(\w+)/mi', $policyContent, $m)) {
                $mode = strtolower($m[1]);
            }
        }
        return [
            'has_sts' => count($stsRecords) > 0,
            'dns_records' => $stsRecords,
            'dns_count' => count($stsRecords),
            'policy_url' => $policyUrl,
            'policy_content' => $policyContent,
            'policy_error' => $policyError,
            'mode' => $mode,
        ];
    }
}

if (!function_exists('mt_smtp_test')) {
    function mt_smtp_test(string $host, int $port = 25, int $timeout = 10): array
    {
        $start = microtime(true);
        $conn = @fsockopen($host, $port, $errno, $errstr, $timeout);
        $latency = round((microtime(true) - $start) * 1000, 1);
        if (!$conn) {
            return [
                'reachable' => false,
                'port' => $port,
                'error' => $errstr,
                'latency' => $latency,
            ];
        }
        $banner = @fgets($conn, 512);
        $startTls = null;
        $ehloResp = [];
        fwrite($conn, "EHLO email-check.local\r\n");
        while (($line = @fgets($conn, 512)) !== false) {
            $trimmed = trim($line);
            $ehloResp[] = $trimmed;
            if (strlen($trimmed) >= 4 && substr($trimmed, 3, 1) !== '-') break;
        }
        $supportsStartTls = false;
        foreach ($ehloResp as $line) {
            if (stripos($line, 'STARTTLS') !== false) {
                $supportsStartTls = true;
                break;
            }
        }
        fwrite($conn, "QUIT\r\n");
        @fclose($conn);
        return [
            'reachable' => true,
            'port' => $port,
            'banner' => trim($banner ?? ''),
            'ehlo' => $ehloResp,
            'supports_starttls' => $supportsStartTls,
            'latency' => $latency,
        ];
    }
}

if (!function_exists('mt_port_scan')) {
    function mt_port_scan(string $host, int $timeout = 5): array
    {
        $commonPorts = [
            25 => 'SMTP',
            465 => 'SMTPS',
            587 => 'Submission',
            993 => 'IMAPS',
            995 => 'POP3S',
            143 => 'IMAP',
            110 => 'POP3',
            2525 => 'SMTP Alt',
        ];
        $results = [];
        foreach ($commonPorts as $port => $service) {
            $start = microtime(true);
            $conn = @fsockopen($host, $port, $errno, $errstr, $timeout);
            $latency = round((microtime(true) - $start) * 1000, 1);
            if ($conn) {
                $banner = @fgets($conn, 256);
                @fclose($conn);
                $results[] = [
                    'port' => $port,
                    'service' => $service,
                    'open' => true,
                    'banner' => trim($banner ?? ''),
                    'latency' => $latency,
                ];
            } else {
                $results[] = [
                    'port' => $port,
                    'service' => $service,
                    'open' => false,
                    'error' => $errstr,
                    'latency' => $latency,
                ];
            }
        }
        return $results;
    }
}
