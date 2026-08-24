<?php

if (!function_exists('rbl_get_blacklists')) {
    function rbl_get_blacklists(): array
    {
        return [
            'zen.spamhaus.org',
            'bl.spamcop.net',
            'b.barracudacentral.org',
            'dnsbl.sorbs.net',
            'psbl.surriel.com',
            'cbl.abuseat.org',
            'bogons.cymru.com',
            'spam.dnsbl.sorbs.net',
            'dnsbl-1.uceprotect.net',
            'dnsbl-2.uceprotect.net',
            'dnsbl-3.uceprotect.net',
            'db.wpbl.info',
            'ix.dnsbl.manitu.net',
            'tor.dnsbl.sectoor.de',
            'dnsbl.dronebl.org',
            'http.dnsbl.sorbs.net',
            'smtp.dnsbl.sorbs.net',
            'socks.dnsbl.sorbs.net',
            'zombie.dnsbl.sorbs.net',
            'web.dnsbl.sorbs.net',
            'misc.dnsbl.sorbs.net',
            'dnsbl.njabl.org',
            'dnsbl.ahbl.org',
            'dul.dnsbl.sorbs.net',
            'spam.spamrats.com',
            'all.s5h.net',
            's5h.net',
            'no-more-funn.moensted.dk',
            'korea.services.net',
            'fresh.spam.dnsbl.sorbs.net',
            'bsb.spamlookup.net',
            'spamsources.fabel.dk',
            'dnsbl.inps.de',
            'mail-abuse.blacklist.jippg.org',
            'rbl.efnetrbl.org',
            'rbl.schulte.org',
            'blacklist.woody.ch',
            'bl.fmb.la',
            'blacklist.sci.kun.nl',
        ];
    }
}

if (!function_exists('rbl_dig_lookup')) {
    function rbl_dig_lookup(string $queryHost): array
    {
        $result = ['ip' => null, 'latency' => 0];
        $start = microtime(true);
        $cmd = 'dig +short +time=3 +tries=1 A ' . escapeshellarg($queryHost) . ' @8.8.8.8 2>/dev/null';
        $output = [];
        $rc = -1;
        exec($cmd, $output, $rc);
        $result['latency'] = round((microtime(true) - $start) * 1000, 1);
        if ($rc === 0 && !empty($output)) {
            $ip = trim($output[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $result['ip'] = $ip;
            }
        }
        return $result;
    }
}

if (!function_exists('rbl_check_ip')) {
    function rbl_check_ip(string $ip): array
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['target' => $ip, 'error' => 'Invalid IP address', 'total' => 0, 'listed' => 0, 'results' => []];
        }

        $isIPv6 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        $revIp = rbl_reverse_ip($ip);
        $blacklists = rbl_get_blacklists();
        $results = [];
        $listed = 0;

        foreach ($blacklists as $bl) {
            $queryHost = $revIp . '.' . $bl;
            $dig = rbl_dig_lookup($queryHost);
            $isListed = $dig['ip'] !== null;
            if ($isListed) $listed++;

            $results[] = [
                'blacklist' => $bl,
                'listed' => $isListed,
                'response' => $isListed ? $dig['ip'] : '127.0.0.1',
                'latency' => $dig['latency'],
            ];
        }

        return [
            'target' => $ip,
            'total' => count($blacklists),
            'listed' => $listed,
            'results' => $results,
        ];
    }
}

if (!function_exists('rbl_reverse_ip')) {
    function rbl_reverse_ip(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $hex = bin2hex(inet_pton($ip));
            $parts = str_split(strrev($hex));
            return implode('.', $parts) . '.ip6.arpa';
        }
        $parts = explode('.', $ip);
        return implode('.', array_reverse($parts));
    }
}
