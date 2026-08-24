<?php

/**
 * IP Blocking Service
 *
 * Persistent blocklist of source IPs/CIDRs. When enabled and a request arrives
 * from a blocked IP, the app responds as "unreachable" (HTTP 403, empty body)
 * so the attacker cannot reach the site.
 *
 * Storage: App_Data/blocked_ips.json
 * Structure: { "enabled": bool, "allowlist": [ip...], "blocklist": [ip|cidr...] }
 */

if (!function_exists('ip_block_file_path')) {
    function ip_block_file_path(): string
    {
        if (!function_exists('resolve_secure_path')) {
            @require_once __DIR__ . '/../../Infrastructure/Persistence/repositories.php';
        }
        if (function_exists('resolve_secure_path')) {
            try {
                $secure = resolve_secure_path('security', 'blocked_ips.json');
                if ($secure !== '') {
                    return $secure;
                }
            } catch (\Throwable $e) {
                // fall through to App_Data fallback
            }
        }
        return __DIR__ . '/../../../App_Data/blocked_ips.json';
    }
}

if (!function_exists('ip_block_read')) {
    function ip_block_read(): array
    {
        $path = ip_block_file_path();
        if (!is_file($path)) {
            return ['enabled' => true, 'allowlist' => [], 'blocklist' => []];
        }
        $raw = @file_get_contents($path);
        $data = $raw ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return ['enabled' => true, 'allowlist' => [], 'blocklist' => []];
        }
        return [
            'enabled'   => (bool)($data['enabled'] ?? true),
            'allowlist' => array_values(array_filter(array_map('strval', $data['allowlist'] ?? []))),
            'blocklist' => array_values(array_filter(array_map('strval', $data['blocklist'] ?? []))),
        ];
    }
}

if (!function_exists('ip_block_write')) {
    function ip_block_write(array $data): bool
    {
        $path = ip_block_file_path();
        $payload = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $tmp = $path . '.tmp';
        if (@file_put_contents($tmp, $payload) === false) {
            return false;
        }
        @chmod($tmp, 0664);
        return @rename($tmp, $path);
    }
}

if (!function_exists('ip_block_normalize')) {
    function ip_block_normalize(string $entry): string
    {
        $entry = trim($entry);
        if (strpos($entry, '/') !== false) {
            return strtolower($entry);
        }
        if (filter_var($entry, FILTER_VALIDATE_IP)) {
            return $entry;
        }
        return '';
    }
}

if (!function_exists('ip_block_matches')) {
    function ip_block_matches(string $clientIp, string $entry): bool
    {
        $clientIp = trim($clientIp);
        $entry = trim($entry);
        if ($clientIp === '' || $entry === '') {
            return false;
        }
        // Exact match
        if ($clientIp === $entry) {
            return true;
        }
        // CIDR match
        if (strpos($entry, '/') !== false) {
            [$subnet, $bitsStr] = explode('/', $entry, 2);
            $bits = (int)$bitsStr;
            if (filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                if ($bits < 0 || $bits > 32) return false;
                $mask = (0xFFFFFFFF << (32 - $bits)) & 0xFFFFFFFF;
                return ((ip2long($clientIp) & $mask) === (ip2long($subnet) & $mask));
            }
            if (filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $binIp = @inet_pton($clientIp);
                $binSub = @inet_pton($subnet);
                if ($binIp === false || $binSub === false || $bits < 0 || $bits > 128) return false;
                $fullBytes = $bits >> 3;
                $remBits = $bits & 7;
                for ($i = 0; $i < 16; $i++) {
                    $a = ord($binIp[$i]);
                    $b = ord($binSub[$i]);
                    if ($i < $fullBytes) {
                        if ($a !== $b) return false;
                    } elseif ($i === $fullBytes) {
                        $mask = $remBits === 0 ? 0 : (0xFF << (8 - $remBits)) & 0xFF;
                        if (($a & $mask) !== ($b & $mask)) return false;
                    }
                }
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('ip_block_is_blocked')) {
    function ip_block_is_blocked(string $clientIp): bool
    {
        $data = ip_block_read();
        if (empty($data['enabled'])) {
            return false;
        }
        foreach ($data['allowlist'] as $allow) {
            if (ip_block_matches($clientIp, $allow)) {
                return false;
            }
        }
        foreach ($data['blocklist'] as $block) {
            if (ip_block_matches($clientIp, $block)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('ip_block_client')) {
    function ip_block_client(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
}

if (!function_exists('ip_block_enforce')) {
    function ip_block_enforce(): void
    {
        $ip = ip_block_client();
        if ($ip === '' || !ip_block_is_blocked($ip)) {
            return;
        }
        // Respond as "unreachable" — 403 with an empty body and closed connection.
        if (!headers_sent()) {
            http_response_code(403);
            header('Connection: close');
            header('Content-Type: text/plain');
            header('X-Content-Type-Options: nosniff');
        }
        exit;
    }
}
