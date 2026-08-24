<?php

if (!function_exists('email_validate_syntax')) {
    function email_validate_syntax(string $email): array
    {
        $email = trim($email);
        $isValid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        $parts = explode('@', $email, 2);
        return [
            'valid' => $isValid && count($parts) === 2,
            'email' => $email,
            'local' => $parts[0] ?? '',
            'domain' => $parts[1] ?? '',
            'format_valid' => $isValid,
        ];
    }
}

if (!function_exists('email_is_disposable')) {
    function email_is_disposable(string $domain): bool
    {
        $disposableDomains = [
            'mailinator.com', 'guerrillamail.com', '10minutemail.com',
            'tempmail.com', 'throwaway.email', 'yopmail.com',
            'sharklasers.com', 'trashmail.com', 'mailnator.com',
            'temp-mail.org', 'fakeinbox.com', 'dispostable.com',
            'getairmail.com', 'tempinbox.com', 'mailexpire.com',
            'spamgourmet.com', 'mailmetrash.com', 'mytrashmail.com',
            'tempemail.net', 'emailfake.com', 'tempmail.net',
            'trashmail.net', 'spambox.us', 'spam.la',
        ];
        return in_array(strtolower($domain), $disposableDomains, true);
    }
}

if (!function_exists('email_is_role_account')) {
    function email_is_role_account(string $local): bool
    {
        $rolePrefixes = [
            'admin', 'administrator', 'info', 'support', 'sales',
            'contact', 'webmaster', 'postmaster', 'hostmaster',
            'noreply', 'no-reply', 'mailer-daemon', 'mailer',
            'abuse', 'security', 'billing', 'team', 'help',
            'hr', 'jobs', 'recruitment', 'marketing', 'press',
            'pr', 'feedback', 'enquiries', 'enquiry', 'office',
            'service', 'services', 'manager', 'management',
        ];
        $localLower = strtolower(trim($local));
        return in_array($localLower, $rolePrefixes, true);
    }
}

if (!function_exists('email_check_domain_mx')) {
    function email_check_domain_mx(string $domain): array
    {
        require_once __DIR__ . '/../Dns/dns_resolver.php';
        $mx = dns_resolve_mx($domain);
        return [
            'has_mx' => count($mx) > 0,
            'mx_records' => $mx,
            'mx_count' => count($mx),
        ];
    }
}

if (!function_exists('email_smtp_verify')) {
    function email_smtp_verify(string $email, int $timeout = 10): array
    {
        $parsed = email_validate_syntax($email);
        if (!$parsed['valid']) {
            return ['reachable' => false, 'error' => 'Invalid email syntax', 'latency' => 0];
        }

        $domain = $parsed['domain'];
        require_once __DIR__ . '/../Dns/dns_resolver.php';
        $mx = dns_resolve_mx($domain);

        if (empty($mx)) {
            return ['reachable' => false, 'error' => 'No MX records found', 'latency' => 0];
        }

        $mxHost = $mx[0]['host'];
        $start = microtime(true);

        $conn = @fsockopen($mxHost, 25, $errno, $errstr, $timeout);
        $latency = round((microtime(true) - $start) * 1000, 1);

        if (!$conn) {
            return ['reachable' => false, 'error' => "Connection failed: $errstr", 'latency' => $latency];
        }

        $banner = fgets($conn, 512);
        fwrite($conn, "HELO email-check.local\r\n");
        fgets($conn, 512);
        fwrite($conn, "MAIL FROM:<check@{$domain}>\r\n");
        fgets($conn, 512);
        fwrite($conn, "RCPT TO:<{$email}>\r\n");
        $rcptResponse = fgets($conn, 512);
        fwrite($conn, "QUIT\r\n");
        fclose($conn);

        $accepted = $rcptResponse !== false && (strpos($rcptResponse, '250') === 0 || strpos($rcptResponse, '451') === 0);
        $maybeExists = strpos($rcptResponse ?? '', '250') === 0;

        return [
            'reachable' => $accepted,
            'mailbox_exists' => $maybeExists,
            'mx_host' => $mxHost,
            'banner' => trim($banner ?? ''),
            'response' => trim($rcptResponse ?? ''),
            'latency' => $latency,
        ];
    }
}
