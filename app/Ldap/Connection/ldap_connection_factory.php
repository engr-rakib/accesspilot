<?php

require_once __DIR__ . '/../Config/ldap_config_repository.php';

if (!function_exists('ldap_extension_loaded')) {
    function ldap_extension_loaded(): bool
    {
        return extension_loaded('ldap');
    }
}

if (!function_exists('ldap_build_uri')) {
    function ldap_build_uri(array $config): string
    {
        $host = trim((string) ($config['host'] ?? ''));
        $port = (int) ($config['port'] ?? 389);
        $useTls = !empty($config['use_tls']);

        if ($host === '') {
            throw new InvalidArgumentException('LDAP host is required.');
        }

        if ($useTls && $port === 636) {
            return 'ldaps://' . $host . ':' . $port;
        }

        return 'ldap://' . $host . ':' . $port;
    }
}

if (!function_exists('ldap_set_tls_never')) {
    function ldap_set_tls_never(): void
    {
        if (defined('LDAP_OPT_X_TLS_REQUIRE_CERT') && defined('LDAP_OPT_X_TLS_NEVER')) {
            @ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
        }
    }
}

if (!function_exists('ldap_apply_connection_options')) {
    function ldap_apply_connection_options($connection, array $config): void
    {
        $timeout = (int) ($config['connect_timeout'] ?? 5);
        if ($timeout < 1) {
            $timeout = 5;
        }

        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, $timeout);
        if (defined('LDAP_OPT_TIMEOUT')) {
            @ldap_set_option($connection, LDAP_OPT_TIMEOUT, $timeout);
        }
    }
}

if (!function_exists('ldap_start_tls_if_needed')) {
    function ldap_start_tls_if_needed($connection, array $config): void
    {
        $port = (int) ($config['port'] ?? 389);
        $useTls = !empty($config['use_tls']);

        if (!$useTls || $port === 636) {
            return;
        }

        if (!@ldap_start_tls($connection)) {
            throw new RuntimeException('LDAP StartTLS failed: ' . ldap_error($connection));
        }
    }
}

if (!function_exists('ldap_connect_and_bind')) {
    /**
     * @return array{connection: resource, config: array}
     */
    function ldap_connect_and_bind(?array $configOverride = null): array
    {
        if (!ldap_extension_loaded()) {
            throw new RuntimeException('PHP ldap extension is not loaded. Enable extension=ldap in php.ini.');
        }

        $config = $configOverride ?? ldap_read_config();
        $bindDn = trim((string) ($config['bind_dn'] ?? ''));
        $bindPassword = ldap_read_bind_password();

        if ($bindDn === '') {
            throw new InvalidArgumentException('LDAP bind DN is required.');
        }
        if ($bindPassword === '') {
            throw new InvalidArgumentException('LDAP bind password is not configured.');
        }

        ldap_set_tls_never();

        $uri = ldap_build_uri($config);
        $host = $config['host'] ?? 'unknown';

        // Retry the connect/TLS/bind sequence once: DCs occasionally drop a
        // transient connection (e.g. StartTLS "Connect error" / LDAP 91) during
        // momentary network or DC hiccups. A fresh connection is side-effect free.
        $lastError = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $connection = @ldap_connect($uri);
            if ($connection === false) {
                $lastError = new RuntimeException('ldap_connect failed for ' . $uri);
            } else {
                try {
                    ldap_apply_connection_options($connection, $config);
                    ldap_start_tls_if_needed($connection, $config);

                    if (!@ldap_bind($connection, $bindDn, $bindPassword)) {
                        $error = ldap_error($connection);
                        $diag = '';
                        @ldap_get_option($connection, defined('LDAP_OPT_DIAGNOSTIC_MESSAGE') ? LDAP_OPT_DIAGNOSTIC_MESSAGE : 0x0032, $diag);
                        $detail = $diag ? " ({$diag})" : '';
                        $lastError = new RuntimeException("LDAP bind failed to {$host}: {$error}{$detail}");
                    } else {
                        return ['connection' => $connection, 'config' => $config];
                    }
                } catch (RuntimeException $e) {
                    $lastError = $e;
                }
                @ldap_unbind($connection);
            }

            if ($attempt === 1) {
                usleep(300000);
            }
        }

        throw $lastError ?? new RuntimeException("LDAP connect/bind failed to {$host}.");
    }
}

if (!function_exists('ldap_test_connection')) {
    function ldap_test_connection(?array $configOverride = null, ?string $passwordOverride = null): array
    {
        $started = microtime(true);

        if (!ldap_extension_loaded()) {
            return [
                'success' => false,
                'message' => 'PHP ldap extension is not loaded.',
                'extension_loaded' => false,
                'latency_ms' => 0,
            ];
        }

        try {
            $config = $configOverride ?? ldap_read_config();

            if ($passwordOverride !== null && $passwordOverride !== '') {
                $bindPassword = $passwordOverride;
            } else {
                $bindPassword = ldap_read_bind_password();
            }

            $bindDn = trim((string) ($config['bind_dn'] ?? ''));
            if (trim((string) ($config['host'] ?? '')) === '') {
                throw new InvalidArgumentException('LDAP host is required.');
            }
            if ($bindDn === '') {
                throw new InvalidArgumentException('LDAP bind DN is required.');
            }
            if ($bindPassword === '') {
                throw new InvalidArgumentException('LDAP bind password is required for testing.');
            }

            ldap_set_tls_never();

            $uri = ldap_build_uri($config);
            $connection = @ldap_connect($uri);
            if ($connection === false) {
                throw new RuntimeException('ldap_connect failed for ' . $uri);
            }

            ldap_apply_connection_options($connection, $config);
            ldap_start_tls_if_needed($connection, $config);

            if (!@ldap_bind($connection, $bindDn, $bindPassword)) {
                throw new RuntimeException('LDAP bind failed: ' . ldap_error($connection));
            }

            $namingContext = '';
            $search = @ldap_read($connection, '', '(objectClass=*)', ['defaultNamingContext'], 0, 0, 0);
            if ($search !== false) {
                $entry = ldap_first_entry($connection, $search);
                if ($entry !== false) {
                    $attrs = ldap_get_attributes($connection, $entry);
                    if (!empty($attrs['defaultnamingcontext'][0])) {
                        $namingContext = (string) $attrs['defaultnamingcontext'][0];
                    }
                }
            }

            @ldap_unbind($connection);

            $latencyMs = (int) round((microtime(true) - $started) * 1000);

            return [
                'success' => true,
                'message' => 'LDAP connection and bind succeeded.',
                'extension_loaded' => true,
                'latency_ms' => $latencyMs,
                'server_naming_context' => $namingContext,
                'uri' => $uri,
            ];
        } catch (Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $started) * 1000);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'extension_loaded' => ldap_extension_loaded(),
                'latency_ms' => $latencyMs,
                'server_naming_context' => '',
                'uri' => '',
            ];
        }
    }
}
