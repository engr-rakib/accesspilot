<?php

if (!function_exists('dns_public_servers')) {
    function dns_public_servers(): array
    {
        return ['8.8.8.8', '1.1.1.1', '9.9.9.9'];
    }
}

if (!function_exists('dns_dig_query')) {
    function dns_dig_query(string $domain, string $type = 'MX'): array
    {
        $servers = dns_public_servers();
        foreach ($servers as $ns) {
            $cmd = sprintf('dig @%s %s %s +short 2>/dev/null', escapeshellarg($ns), escapeshellarg($type), escapeshellarg($domain));
            $output = [];
            $rc = -1;
            exec($cmd, $output, $rc);
            if ($rc === 0 && !empty($output)) {
                return $output;
            }
        }
        return [];
    }
}

if (!function_exists('dns_parse_dig_mx')) {
    function dns_parse_dig_mx(string $domain): array
    {
        $records = [];
        $lines = dns_dig_query($domain, 'MX');
        if (empty($lines)) {
            $lines = dns_dig_query($domain, 'MX');
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (preg_match('/^(\d+)\s+(.+?)\.?$/', $line, $m)) {
                $records[] = [
                    'host' => rtrim($m[2], '.'),
                    'preference' => (int)$m[1],
                ];
            }
        }
        usort($records, fn($a, $b) => $a['preference'] - $b['preference']);
        return $records;
    }
}

if (!function_exists('dns_parse_dig_txt')) {
    function dns_parse_dig_txt(string $domain): array
    {
        $lines = dns_dig_query($domain, 'TXT');
        $txts = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $line = trim($line, '"');
            if ($line !== '') $txts[] = $line;
        }
        return $txts;
    }
}

if (!function_exists('dns_resolve_mx')) {
    function dns_resolve_mx(string $domain): array
    {
        $records = [];
        if (function_exists('dns_get_record')) {
            $mx = @dns_get_record($domain, DNS_MX);
            if ($mx !== false) {
                foreach ($mx as $r) {
                    $records[] = [
                        'host' => $r['target'] ?? '',
                        'preference' => (int)($r['pri'] ?? 0),
                    ];
                }
            }
        }
        if (empty($records)) {
            $records = dns_parse_dig_mx($domain);
        }
        usort($records, fn($a, $b) => $a['preference'] - $b['preference']);
        return $records;
    }
}

if (!function_exists('dns_resolve_a')) {
    function dns_resolve_a(string $hostname): array
    {
        $ips = [];
        if (function_exists('dns_get_record')) {
            $a = @dns_get_record($hostname, DNS_A);
            if ($a !== false) {
                foreach ($a as $r) {
                    if (!empty($r['ip'])) $ips[] = $r['ip'];
                }
            }
        }
        if (empty($ips)) {
            $lines = dns_dig_query($hostname, 'A');
            foreach ($lines as $line) {
                $line = trim($line);
                if (filter_var($line, FILTER_VALIDATE_IP)) $ips[] = $line;
            }
        }
        return $ips;
    }
}

if (!function_exists('dns_resolve_aaaa')) {
    function dns_resolve_aaaa(string $hostname): array
    {
        $ips = [];
        if (function_exists('dns_get_record')) {
            $aaaa = @dns_get_record($hostname, DNS_AAAA);
            if ($aaaa !== false) {
                foreach ($aaaa as $r) {
                    if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
                }
            }
        }
        return $ips;
    }
}

if (!function_exists('dns_resolve_txt')) {
    function dns_resolve_txt(string $domain): array
    {
        $txts = [];
        if (function_exists('dns_get_record')) {
            $txt = @dns_get_record($domain, DNS_TXT);
            if ($txt !== false) {
                foreach ($txt as $r) {
                    if (!empty($r['txt'])) $txts[] = $r['txt'];
                }
            }
        }
        if (empty($txts)) {
            $txts = dns_parse_dig_txt($domain);
        }
        return $txts;
    }
}

if (!function_exists('dns_check_spf')) {
    function dns_check_spf(string $domain): array
    {
        $txts = dns_resolve_txt($domain);
        $spfRecords = [];
        foreach ($txts as $txt) {
            if (stripos($txt, 'v=spf1') === 0) {
                $spfRecords[] = $txt;
            }
        }
        return $spfRecords;
    }
}

if (!function_exists('dns_check_dkim')) {
    function dns_check_dkim(string $domain, string $selector = 'default'): array
    {
        $records = [];
        $dkimName = $selector . '._domainkey.' . $domain;
        if (function_exists('dns_get_record')) {
            $txt = @dns_get_record($dkimName, DNS_TXT);
            if ($txt !== false) {
                foreach ($txt as $r) {
                    if (!empty($r['txt'])) {
                        $records[] = ['selector' => $selector, 'record' => $r['txt']];
                    }
                }
            }
        }
        if (empty($records)) {
            $lines = dns_dig_query($dkimName, 'TXT');
            foreach ($lines as $line) {
                $line = trim($line);
                $line = trim($line, '"');
                if ($line !== '') {
                    $records[] = ['selector' => $selector, 'record' => $line];
                }
            }
        }
        return $records;
    }
}

if (!function_exists('dns_check_dmarc')) {
    function dns_check_dmarc(string $domain): array
    {
        $records = [];
        $dmarcName = '_dmarc.' . $domain;
        if (function_exists('dns_get_record')) {
            $txt = @dns_get_record($dmarcName, DNS_TXT);
            if ($txt !== false) {
                foreach ($txt as $r) {
                    if (!empty($r['txt']) && stripos($r['txt'], 'v=DMARC1') === 0) {
                        $records[] = $r['txt'];
                    }
                }
            }
        }
        if (empty($records)) {
            $lines = dns_dig_query($dmarcName, 'TXT');
            foreach ($lines as $line) {
                $line = trim($line);
                $line = trim($line, '"');
                if ($line !== '' && stripos($line, 'v=DMARC1') === 0) {
                    $records[] = $line;
                }
            }
        }
        return $records;
    }
}

if (!function_exists('dns_resolve_ptr')) {
    function dns_resolve_ptr(string $ip): array
    {
        $hostnames = [];
        $reverse = gethostbyaddr($ip);
        if ($reverse !== false && $reverse !== $ip) {
            $hostnames[] = $reverse;
        }
        return $hostnames;
    }
}

if (!function_exists('dns_parse_spf')) {
    function dns_parse_spf(string $spfRecord): array
    {
        $parts = preg_split('/\s+/', trim($spfRecord));
        $result = ['mechanisms' => [], 'all' => ''];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || stripos($part, 'v=spf1') === 0) continue;
            if (in_array($part, ['+all', '-all', '~all', '?all'], true)) {
                $result['all'] = $part;
            } else {
                $result['mechanisms'][] = $part;
            }
        }
        return $result;
    }
}
