<?php

if (!function_exists('header_parse_raw')) {
    function header_parse_raw(string $rawHeaders): array
    {
        $lines = preg_split('/\r?\n/', $rawHeaders);
        $headers = [];
        $currentName = '';
        $currentValue = '';

        foreach ($lines as $line) {
            if (preg_match('/^([\w\-]+):\s*(.*)$/s', $line, $m)) {
                if ($currentName !== '') {
                    $headers[] = ['name' => $currentName, 'value' => trim($currentValue)];
                }
                $currentName = $m[1];
                $currentValue = $m[2];
            } elseif (preg_match('/^\s+(.*)$/', $line, $m)) {
                $currentValue .= ' ' . trim($m[1]);
            }
        }
        if ($currentName !== '') {
            $headers[] = ['name' => $currentName, 'value' => trim($currentValue)];
        }

        return $headers;
    }
}

if (!function_exists('header_extract_received')) {
    function header_extract_received(array $headers): array
    {
        $received = [];
        foreach ($headers as $h) {
            if (strtolower($h['name']) === 'received') {
                $received[] = $h['value'];
            }
        }
        return $received;
    }
}

if (!function_exists('header_parse_received_chain')) {
    function header_parse_received_chain(string $rawHeaders): array
    {
        $headers = header_parse_raw($rawHeaders);
        $received = header_extract_received($headers);

        $hops = [];
        foreach ($received as $r) {
            $hop = ['raw' => $r];
            if (preg_match('/from\s+(\S+)/i', $r, $m)) $hop['from'] = $m[1];
            if (preg_match('/by\s+(\S+)/i', $r, $m)) $hop['by'] = $m[1];
            if (preg_match('/with\s+(\S+)/i', $r, $m)) $hop['with'] = $m[1];
            if (preg_match('/id\s+(\S+)/i', $r, $m)) $hop['id'] = $m[1];
            if (preg_match('/for\s+<([^>]+)>/i', $r, $m)) $hop['for'] = $m[1];
            $hops[] = $hop;
        }

        return [
            'received_count' => count($hops),
            'hops' => $hops,
            'first_hop' => $hops[0] ?? null,
            'last_hop' => !empty($hops) ? $hops[count($hops) - 1] : null,
        ];
    }
}

if (!function_exists('header_extract_auth_results')) {
    function header_extract_auth_results(string $rawHeaders): array
    {
        $headers = header_parse_raw($rawHeaders);
        $authResults = [];

        foreach ($headers as $h) {
            if (strtolower($h['name']) === 'authentication-results') {
                $result = ['raw' => $h['value']];
                if (preg_match('/spf=(\w+)/i', $h['value'], $m)) $result['spf'] = $m[1];
                if (preg_match('/dkim=(\w+)/i', $h['value'], $m)) $result['dkim'] = $m[1];
                if (preg_match('/dmarc=(\w+)/i', $h['value'], $m)) $result['dmarc'] = $m[1];
                $authResults[] = $result;
            }
        }

        return $authResults;
    }
}

if (!function_exists('header_get_envelope')) {
    function header_get_envelope(string $rawHeaders): array
    {
        $headers = header_parse_raw($rawHeaders);
        $envelope = [];

        foreach ($headers as $h) {
            $name = strtolower($h['name']);
            $val = $h['value'];
            if ($name === 'from') $envelope['from'] = $val;
            if ($name === 'to') $envelope['to'] = $val;
            if ($name === 'subject') $envelope['subject'] = $val;
            if ($name === 'date') $envelope['date'] = $val;
            if ($name === 'message-id') $envelope['message_id'] = $val;
            if ($name === 'reply-to') $envelope['reply_to'] = $val;
            if ($name === 'return-path') $envelope['return_path'] = $val;
            if ($name === 'content-type') $envelope['content_type'] = $val;
        }

        return $envelope;
    }
}

if (!function_exists('header_full_analysis')) {
    function header_full_analysis(string $rawHeaders): array
    {
        $envelope = header_get_envelope($rawHeaders);
        $received = header_parse_received_chain($rawHeaders);
        $authResults = header_extract_auth_results($rawHeaders);
        $allHeaders = header_parse_raw($rawHeaders);

        $spfResult = 'neutral';
        $dkimResult = 'neutral';
        $dmarcResult = 'neutral';

        foreach ($authResults as $ar) {
            if (!empty($ar['spf'])) $spfResult = strtolower($ar['spf']);
            if (!empty($ar['dkim'])) $dkimResult = strtolower($ar['dkim']);
            if (!empty($ar['dmarc'])) $dmarcResult = strtolower($ar['dmarc']);
        }

        $spoofScore = 0;
        if ($spfResult === 'fail' || $spfResult === 'softfail') $spoofScore += 40;
        if ($dkimResult === 'fail') $spoofScore += 30;
        if ($dmarcResult === 'fail') $spoofScore += 30;
        if ($spfResult === 'pass' && $dkimResult === 'pass') $spoofScore = 0;

        return [
            'envelope' => $envelope,
            'received_chain' => $received,
            'auth_results' => $authResults,
            'spf' => $spfResult,
            'dkim' => $dkimResult,
            'dmarc' => $dmarcResult,
            'spoof_score' => min(100, $spoofScore),
            'header_count' => count($allHeaders),
        ];
    }
}
