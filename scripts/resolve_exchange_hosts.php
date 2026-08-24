<?php
/**
 * Resolve Exchange server hostnames and add to /etc/hosts.
 * Runs at container startup as root via docker-compose command.
 */
$jsonPath = '/data/secure/ldap/domains.json';
if (!file_exists($jsonPath)) {
    echo "  No domains config at $jsonPath\n";
    exit(0);
}

$domains = json_decode(file_get_contents($jsonPath), true);
if (!is_array($domains)) {
    echo "  Invalid domains config\n";
    exit(0);
}

$hostsFile = '/etc/hosts';
$added = 0;
foreach ($domains as $d) {
    $ex = $d['exchange'] ?? [];
    if (empty($ex['enabled'])) continue;

    // Check server_override first, then extract from ps_uri_override
    $host = trim($ex['server_override'] ?? '');
    $psPort = $ex['ps_use_https'] ? 443 : 80;
    if ($host === '') {
        $uri = trim($ex['ps_uri_override'] ?? '');
        if ($uri !== '') {
            $parts = parse_url($uri);
            $host = $parts['host'] ?? '';
            if (isset($parts['port'])) {
                $psPort = (int)$parts['port'];
            } elseif (isset($parts['scheme'])) {
                $psPort = strtolower($parts['scheme']) === 'https' ? 443 : 80;
            }
        }
    }
    if ($host === '') continue;

    // Collect candidate IPs from all DNS A records, then pick the first that is reachable on the PS port.
    $candidates = gethostbynamel($host);
    if (empty($candidates) || (count($candidates) === 1 && !filter_var($candidates[0], FILTER_VALIDATE_IP))) {
        $adIp = trim($d['ip'] ?? '');
        if ($adIp !== '') {
            $out = [];
            $cmd = sprintf('nslookup %s %s 2>/dev/null | grep -i "Address" | grep -v "#" | awk \'{print $2}\'', escapeshellarg($host), escapeshellarg($adIp));
            @exec($cmd, $out);
            $candidates = array_values(array_filter($out, fn($ip) => filter_var(trim($ip), FILTER_VALIDATE_IP)));
        }
    }

    $picked = '';
    foreach ($candidates as $ip) {
        $ip = trim($ip);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) continue;
        $errno = 0; $errstr = '';
        $ctx = stream_context_create(['socket' => ['timeout' => 2, 'connecttimeout' => 2]]);
        $conn = @stream_socket_client("tcp://{$ip}:{$psPort}", $errno, $errstr, 2, STREAM_CLIENT_CONNECT, $ctx);
        if ($conn !== false) {
            @fclose($conn);
            $ipc = $ip;
            break;
        }
    }
    if ($ipc === '') {
        echo "  WARNING: No reachable IP for $host on port $psPort (tried: " . implode(',', $candidates) . ") \n";
        continue;
    }

    // Remove any stale entry for this host, then write the reachable one.
    $lines = file($hostsFile);
    $kept = [];
    foreach ($lines as $line) {
        if (preg_match('/\s' . preg_quote($host, '/') . '(\s|$)/', $line)) continue;
        $kept[] = $line;
    }
    file_put_contents($hostsFile, implode('', $kept));
    file_put_contents($hostsFile, "$ipc $host $host\n", FILE_APPEND);
    echo "  Resolved $host -> $ipc (reachable, port $psPort)\n";
    $added++;
}

if ($added === 0) {
    echo "  No new Exchange hosts to resolve\n";
}
