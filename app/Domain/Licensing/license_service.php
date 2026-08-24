<?php
// app/Domain/Licensing/license_service.php

require_once __DIR__ . '/../../Application/Support/helpers.php';

if (!function_exists('license_decode_pem_array')) {
    function license_decode_pem_array(string $content): ?array
    {
        $trimmed = trim($content);
        if ($trimmed === '' || !str_starts_with($trimmed, '-----BEGIN LICENSE-----')) {
            return null;
        }

        $body = '';
        foreach (explode("\n", $trimmed) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '-----')) {
                continue;
            }
            $body .= $line;
        }

        $decodedJson = base64_decode($body, true);
        if ($decodedJson === false) {
            return null;
        }

        $data = json_decode($decodedJson, true);
        return is_array($data) ? $data : null;
    }
}

if (!function_exists('license_vendor_issued_filename')) {
    function license_vendor_issued_filename(array $data): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $data['issued_to'] ?? 'Unknown');
        return $safe . '_' . ($data['license_id'] ?? 'UNKNOWN') . '.pem';
    }
}

if (!function_exists('license_ensure_dir')) {
    function license_ensure_dir(string $dir): string
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return realpath($dir) ?: $dir;
    }
}

if (!function_exists('license_vendor_issued_dir')) {
    function license_vendor_issued_dir(): string
    {
        license_migrate_legacy_storage();
        return license_ensure_dir((string) config_get('license.vendor_issued_dir', ''));
    }
}

if (!function_exists('license_deployment_active_dir')) {
    function license_deployment_active_dir(): string
    {
        license_migrate_legacy_storage();
        return license_ensure_dir((string) config_get('license.deployment_active_dir', ''));
    }
}

if (!function_exists('license_vendor_signing_keys_dir')) {
    function license_vendor_signing_keys_dir(): string
    {
        license_migrate_legacy_storage();
        return license_ensure_dir((string) config_get('license.vendor_signing_keys_dir', ''));
    }
}

if (!function_exists('license_pem_dir')) {
    function license_pem_dir(): string
    {
        return license_deployment_active_dir();
    }
}

if (!function_exists('license_migrate_legacy_storage')) {
    function license_migrate_legacy_storage(): void
    {
        static $started = false;
        if ($started) {
            return;
        }
        $started = true;

        $lockPath = app_root('App_Data/license_storage_migrated.lock');
        if (file_exists($lockPath)) {
            return;
        }

        $base = rtrim((string) config_get('storage.secure_base_path', 'C:/inetpub/Desk_secure_files'), '/\\');
        $vendorIssuedDir = license_ensure_dir((string) config_get('license.vendor_issued_dir', $base . '/vendor_issued_licenses'));
        $deploymentActiveDir = license_ensure_dir((string) config_get('license.deployment_active_dir', $base . '/deployment_active_license'));
        $signingKeysDir = license_ensure_dir((string) config_get('license.vendor_signing_keys_dir', $base . '/vendor_signing_keys'));
        $activeFilename = (string) config_get('license.deployment_active_filename', 'active_license.pem');

        $legacyVendorDir = $base . '/accesspilot_pem';
        if (is_dir($legacyVendorDir)) {
            foreach (glob($legacyVendorDir . '/*.pem') ?: [] as $legacyFile) {
                $content = @file_get_contents($legacyFile);
                if ($content === false) {
                    continue;
                }
                $data = license_decode_pem_array($content);
                if (!$data || empty($data['license_id'])) {
                    continue;
                }
                $target = $vendorIssuedDir . '/' . license_vendor_issued_filename($data);
                if (!file_exists($target) || filemtime($legacyFile) > filemtime($target)) {
                    file_put_contents($target, trim($content) . "\n");
                }
            }
        }

        $legacyMixedDir = $base . '/vendor_licenses';
        if (is_dir($legacyMixedDir)) {
            $latestActive = null;
            $latestActiveMtime = 0;
            foreach (glob($legacyMixedDir . '/*.pem') ?: [] as $legacyFile) {
                $basename = basename($legacyFile);
                if (in_array($basename, ['private_key.pem', 'public_key.pem'], true)) {
                    continue;
                }
                $mtime = (int) filemtime($legacyFile);
                if ($mtime >= $latestActiveMtime) {
                    $latestActiveMtime = $mtime;
                    $latestActive = $legacyFile;
                }
            }
            if ($latestActive !== null) {
                $content = @file_get_contents($latestActive);
                if ($content !== false) {
                    file_put_contents($deploymentActiveDir . '/' . $activeFilename, trim($content) . "\n");
                }
            }

            foreach (['private_key.pem', 'public_key.pem'] as $keyFile) {
                $legacyKey = $legacyMixedDir . '/' . $keyFile;
                $targetKey = $signingKeysDir . '/' . $keyFile;
                if (file_exists($legacyKey) && (!file_exists($targetKey) || filemtime($legacyKey) > filemtime($targetKey))) {
                    @copy($legacyKey, $targetKey);
                }
            }
        }

        @file_put_contents($lockPath, date('c'));
    }
}

if (!function_exists('license_encode_pem')) {
    function license_encode_pem(string $json): string
    {
        $b64 = base64_encode($json);
        $lines = str_split($b64, 64);
        return "-----BEGIN LICENSE-----\n" . implode("\n", $lines) . "\n-----END LICENSE-----\n";
    }
}

if (!function_exists('license_state_path')) {
    function license_state_path(): string
    {
        return (string) config_get('license.state_path', app_root('App_Data/license_state.json'));
    }
}

if (!function_exists('license_secure_config_path')) {
    function license_secure_config_path(): string
    {
        return resolved_secure_config_path();
    }
}

if (!function_exists('license_public_key_path')) {
    function license_public_key_path(): string
    {
        return (string) config_get('license.public_key_path', '');
    }
}

if (!function_exists('license_public_key_contents')) {
    function license_public_key_contents(): ?string
    {
        $path = license_public_key_path();
        if ($path === '' || !file_exists($path) || !is_readable($path)) {
            return null;
        }

        $contents = trim((string) file_get_contents($path));
        return $contents !== '' ? $contents : null;
    }
}

if (!function_exists('license_read_state')) {
    function license_read_state(): array
    {
        $dir = license_pem_dir();
        $activeFile = $dir . '/' . (string) config_get('license.deployment_active_filename', 'active_license.pem');
        $candidateFiles = [];

        if (file_exists($activeFile)) {
            $candidateFiles[] = $activeFile;
        }

        foreach (glob($dir . '/*.pem') ?: [] as $file) {
            if (!in_array($file, $candidateFiles, true)) {
                $candidateFiles[] = $file;
            }
        }

        if (!$candidateFiles) {
            return [];
        }

        usort($candidateFiles, static fn($a, $b) => filemtime($b) <=> filemtime($a));
        $content = trim((string) file_get_contents($candidateFiles[0]));
        if ($content === '') {
            return [];
        }

        $decoded = license_decode_pem_array($content);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('license_write_state')) {
    function license_write_state(array $state): bool
    {
        $dir = license_pem_dir();
        $filename = (string) config_get('license.deployment_active_filename', 'active_license.pem');
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $pem = license_encode_pem($json);
        $target = $dir . '/' . $filename;

        foreach (glob($dir . '/*.pem') ?: [] as $legacyFile) {
            if ($legacyFile !== $target) {
                @unlink($legacyFile);
            }
        }

        return file_put_contents($target, $pem) !== false;
    }
}

if (!function_exists('license_parse_secure_config_metadata')) {
    function license_parse_secure_config_metadata(): array
    {
        static $metadata = null;
        if ($metadata !== null && !empty($metadata)) return $metadata;

        $metadata = [];
        $path = license_secure_config_path();
        
        if ($path === '' || !file_exists($path)) {
            return $metadata;
        }

        $content = (string) file_get_contents($path);
        if ($content === '') return $metadata;

        // Handle UTF-16LE encoding (common for PowerShell Export-Clixml)
        if (str_starts_with($content, "\xFF\xFE")) {
            if (function_exists('mb_convert_encoding')) {
                $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16LE');
            }
        }
        
        // Strip BOM if present
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        // Pass 1: Extract all named string/numeric properties from MS block
        if (preg_match('/<MS>(.*?)<\/MS>/is', $content, $msMatch)) {
            $msContent = $msMatch[1];
            if (preg_match_all('/N="([^"]+)"[^>]*>(.*?)<\//is', $msContent, $matches)) {
                foreach ($matches[1] as $index => $name) {
                    $val = trim((string)$matches[2][$index]);
                    // Only take value if it doesn't look like more XML tags
                    if (strpos($val, '<') === false) {
                        $metadata[$name] = $val;
                    }
                }
            }
        }

        // Pass 2: Extract nested AdminCredential properties (UserName and presence of SS Password)
        if (preg_match('/<Obj N="AdminCredential".*?<Props>(.*?)<\/Props>/is', $content, $propsMatch)) {
            $propsXml = $propsMatch[1];
            if (preg_match('/<S N="UserName">(.*?)<\/S>/is', $propsXml, $userMatch)) {
                $metadata['UserName'] = trim($userMatch[1]);
            }
            if (strpos($propsXml, 'N="Password"') !== false && strpos($propsXml, '<SS') !== false) {
                $metadata['HasPassword'] = true;
            }
        }

        return $metadata;
    }
}

if (!function_exists('license_normalize_date')) {
    function license_normalize_date($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }
}

if (!function_exists('license_runtime_domain_name')) {
    function license_runtime_domain_name(): string
    {
        return get_domain_name();
    }
}

if (!function_exists('license_runtime_product_name')) {
    function license_runtime_product_name(): string
    {
        return 'AccessPilot';
    }
}

if (!function_exists('license_runtime_deployment_id')) {
    function license_runtime_deployment_id(): string
    {
        if (function_exists('get_deployment_id')) {
            return get_deployment_id();
        }
        return '';
    }
}

if (!function_exists('license_verify_signature')) {
    function license_verify_signature(array $fields, string $signatureBase64): bool
    {
        $publicKey = license_public_key_contents();
        if (!$publicKey) {
            return false;
        }

        $baseParts = [
            $fields['license_id'],
            $fields['product_name'],
            $fields['issued_to'],
            $fields['domain_name'],
            $fields['deployment_id'],
            $fields['expires_on'],
            $fields['issued_at'],
        ];

        $signature = base64_decode($signatureBase64);
        if ($signature === false) {
            return false;
        }

        $res = openssl_get_publickey($publicKey);
        if (!$res) {
            return false;
        }

        // Try WITHOUT max_domains first (backward compat for pre-max_domains licenses)
        $signingString = strtoupper(implode('|', $baseParts));
        if (openssl_verify($signingString, $signature, $res, OPENSSL_ALGO_SHA256) === 1) {
            return true;
        }

        // Try WITH max_domains if available (new licenses signed with max_domains field)
        if (!empty($fields['max_domains'])) {
            $partsWithMax = array_merge($baseParts, [(string) $fields['max_domains']]);
            $signingString = strtoupper(implode('|', $partsWithMax));
            if (openssl_verify($signingString, $signature, $res, OPENSSL_ALGO_SHA256) === 1) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('license_validate_runtime_binding')) {
    function license_validate_runtime_binding(array $fields): array
    {
        $runtimeDomain = license_runtime_domain_name();
        $runtimeDeployId = license_runtime_deployment_id();

        if ($fields['domain_name'] !== $runtimeDomain) {
            return [
                'success' => false,
                'message' => "Certificate domain mismatch: '{$fields['domain_name']}' does not match deployment '{$runtimeDomain}'."
            ];
        }

    // Product name check (must match hardcoded AccessPilot)
    if ($fields['product_name'] !== license_runtime_product_name()) {
        return [
            'success' => false,
            'message' => "Certificate product mismatch: '{$fields['product_name']}' does not match 'AccessPilot'."
        ];
    }

    // Deployment ID check (required)
    if (empty($fields['deployment_id'])) {
        return [
            'success' => false,
            'message' => "Certificate is missing deployment ID binding."
        ];
    }
    if ($fields['deployment_id'] !== $runtimeDeployId) {
        return [
            'success' => false,
            'message' => "Certificate deployment ID mismatch."
        ];
    }

    return ['success' => true];
    }
}

if (!function_exists('license_get_status')) {
    function license_get_status(): array
    {
        static $license_status_cache = null;
        if (isset($license_status_cache) && is_array($license_status_cache)) {
            return $license_status_cache;
        }

        $state = license_read_state();
        $licenseConfig = (array) config_get('license', []);
        $contact = (array) ($licenseConfig['contact'] ?? []);
        $policy = (array) ($licenseConfig['policy'] ?? []);

        $productName = 'AccessPilot';
        $domainName = (string) ($state['domain_name'] ?? get_domain_name());
        $issuedTo = (string) ($state['issued_to'] ?? get_domain_name());
        $licenseId = trim((string) ($state['license_id'] ?? ''));
        $issuedAt = license_normalize_date($state['issued_at'] ?? null);
        $signature = trim((string) ($state['signature'] ?? ''));
        $expiryDate = license_normalize_date($state['expires_on'] ?? null);
        
        $warningDays = (int) ($licenseConfig['warning_days'] ?? 90);
        $graceDays = 7; // Solid 7-day grace period

        $daysRemaining = null;
        $isExpired = false;
        $isGracePeriod = false;
        $isTrulyRestricted = false;

        if ($expiryDate !== null) {
            $expiryTimestamp = strtotime($expiryDate . ' 23:59:59');
            $graceTimestamp = $expiryTimestamp + ($graceDays * 86400);
            
            $now = time();
            $daysRemaining = (int) floor(($expiryTimestamp - $now) / 86400);
            
            if ($now > $expiryTimestamp) {
                $isExpired = true;
                if ($now <= $graceTimestamp) {
                    $isGracePeriod = true;
                    $graceRemaining = (int) ceil(($graceTimestamp - $now) / 86400);
                } else {
                    $isTrulyRestricted = true;
                }
            }
        } else {
            // No certificate applied yet
            $isTrulyRestricted = true;
        }

        $signedFields = [
            'license_id' => $licenseId,
            'product_name' => $productName,
            'issued_to' => $issuedTo,
            'domain_name' => $domainName,
            'deployment_id' => $state['deployment_id'] ?? '',
            'expires_on' => $expiryDate ?? '',
            'issued_at' => $issuedAt ?? '',
            'max_domains' => $state['max_domains'] ?? 1,
        ];

        $signatureConfigured = $signature !== '';
        $signatureValid = $signatureConfigured ? license_verify_signature($signedFields, $signature) : false;
        $bindingValidation = $signatureConfigured ? license_validate_runtime_binding($signedFields) : ['success' => true];
        $bindingValid = (bool) ($bindingValidation['success'] ?? false);

        $restricted = false;
        $statusLabel = 'active';
        $statusMessage = 'License is active.';

        if (!$signatureConfigured) {
            $restricted = true;
            $statusLabel = 'missing_certificate';
            $statusMessage = 'No signed license certificate is configured for this deployment.';
        } elseif (!$signatureValid) {
            $restricted = true;
            $statusLabel = 'invalid_signature';
            $statusMessage = 'The configured license certificate signature is invalid.';
        } elseif (!$bindingValid) {
            $restricted = true;
            $statusLabel = 'invalid_binding';
            $statusMessage = (string) ($bindingValidation['message'] ?? 'The license certificate does not match this deployment.');
        } elseif ($isTrulyRestricted) {
            $restricted = true;
            $statusLabel = 'expired';
            $statusMessage = 'The license and grace period have both expired. Operational features are now restricted.';
        } elseif ($isGracePeriod) {
            $statusLabel = 'grace_period';
            $statusMessage = "The license has expired, but you are within the {$graceDays}-day grace period. Access will be restricted in {$graceRemaining} day(s).";
        } elseif ($daysRemaining !== null && $daysRemaining <= $warningDays) {
            $statusLabel = 'warning';
            $statusMessage = 'The license is active but approaching expiry.';
        }

        $license_status_cache = [
            'product_name' => $productName,
            'domain_name' => $domainName,
            'issued_to' => $issuedTo,
            'license_id' => $licenseId,
            'issued_at' => $issuedAt,
            'signature' => $signature,
            'signature_configured' => $signatureConfigured,
            'signature_valid' => $signatureValid,
            'binding_valid' => $bindingValid,
            'expires_on' => $expiryDate,
            'days_remaining' => $daysRemaining,
            'warning_days' => $warningDays,
            'grace_days' => $graceDays,
            'is_warning' => $statusLabel === 'warning',
            'is_grace_period' => $isGracePeriod,
            'is_expired' => $isExpired,
            'is_restricted' => $restricted,
            'status' => $statusLabel,
            'message' => $statusMessage,
            'key_configured' => $signatureConfigured,
            'key_valid' => $signatureValid && $bindingValid,
            'verification_mode' => 'signed_certificate',
            'page_slug' => (string) ($licenseConfig['page_slug'] ?? 'license'),
            'contact' => $contact,
            'policy' => $policy,
            'secure_config_path' => license_secure_config_path(),
            'state_path' => license_state_path(),
            'public_key_path' => license_public_key_path(),
            'runtime_domain_name' => license_runtime_domain_name(),
            'runtime_product_name' => license_runtime_product_name(),
            'runtime_deployment_id' => license_runtime_deployment_id(),
            'deployment_id' => $signedFields['deployment_id'] ?? '',
            'max_domains' => (int) ($state['max_domains'] ?? 1),
        ];

        // Resolve domain usage stats
        $maxDomains = (int) ($license_status_cache['max_domains'] ?? 1);
        $license_status_cache['domains_used'] = (function_exists('ldap_get_domains') ? count(ldap_get_domains()) : 1);
        $license_status_cache['domains_remaining'] = $maxDomains === 0 ? -1 : max(0, $maxDomains - $license_status_cache['domains_used']);

        return $license_status_cache;
    }
}

if (!function_exists('license_clear_status_cache')) {
    function license_clear_status_cache(): void
    {
        global $license_status_cache;
        $license_status_cache = null;
    }
}

if (!function_exists('license_is_restricted')) {
    function license_is_restricted(): bool
    {
        return (bool) (license_get_status()['is_restricted'] ?? false);
    }
}

if (!function_exists('license_status_page_url')) {
    function license_status_page_url(): string
    {
        $pageSlug = (string) (license_get_status()['page_slug'] ?? 'license');
        return admin_page_url($pageSlug);
    }
}

if (!function_exists('license_denied_response')) {
    function license_denied_response(): array
    {
        $status = license_get_status();
        return [
            'success' => false,
            'license_restricted' => true,
            'message' => $status['message'] ?? 'License restricted.',
            'redirect' => license_status_page_url(),
            'status' => $status['status'] ?? 'expired',
            'expires_on' => $status['expires_on'] ?? null,
            'days_remaining' => $status['days_remaining'] ?? null,
        ];
    }
}

if (!function_exists('license_build_notification')) {
    function license_build_notification(): ?array
    {
        $status = license_get_status();
        $days = $status['days_remaining'];

        if ($status['is_restricted']) {
            return [
                'id' => 'license_restricted_notice',
                'source' => 'license',
                'category' => 'security',
                'title' => 'License Restricted',
                'message' => 'License status: ' . strtoupper((string) ($status['status'] ?? 'restricted')) . '. ' . ($status['message'] ?? ''),
                'severity' => 'danger',
                'created_at' => date('Y-m-d H:i:s'),
                'target_url' => license_status_page_url(),
                'is_persistent' => true,
                'audience' => ['type' => 'all'],
                'required_permissions' => [],
                'meta' => ['license' => true],
            ];
        }

        if ($status['is_grace_period']) {
             return [
                'id' => 'license_grace_notice',
                'source' => 'license',
                'category' => 'warning',
                'title' => 'License Expired (Grace Period Active)',
                'message' => $status['message'],
                'severity' => 'warning',
                'created_at' => date('Y-m-d H:i:s'),
                'target_url' => license_status_page_url(),
                'is_persistent' => true,
                'audience' => ['type' => 'all'],
                'required_permissions' => [],
                'meta' => ['license' => true],
            ];
        }

        if (!empty($status['is_warning'])) {
            return [
                'id' => 'license_warning_notice',
                'source' => 'license',
                'category' => 'announcement',
                'title' => 'License Renewal Reminder',
                'message' => 'License expires in ' . (int) $days . ' day(s). Plan renewal before service restriction.',
                'severity' => 'warning',
                'created_at' => date('Y-m-d H:i:s'),
                'target_url' => license_status_page_url(),
                'is_persistent' => true,
                'audience' => ['type' => 'all'],
                'required_permissions' => [],
                'meta' => ['license' => true],
            ];
        }

        return null;
    }
}

if (!function_exists('license_can_manage')) {
    function license_can_manage(): bool
    {
        if (!isset($_SESSION['username'], $_SESSION['role'])) {
            return false;
        }

        if (function_exists('has_permission') && has_permission('page_ad_administration')) {
            return true;
        }

        return (string) ($_SESSION['role'] ?? '') === 'core_admin';
    }
}

if (!function_exists('license_validate_certificate_payload')) {
    function license_validate_certificate_payload(array $payload): array
    {
        $normalized = [
            'license_id' => trim((string) ($payload['license_id'] ?? '')),
            'product_name' => trim((string) ($payload['product_name'] ?? '')),
            'issued_to' => trim((string) ($payload['issued_to'] ?? '')),
            'domain_name' => trim((string) ($payload['domain_name'] ?? '')),
            'deployment_id' => trim((string) ($payload['deployment_id'] ?? '')),
            'expires_on' => license_normalize_date($payload['expires_on'] ?? null),
            'issued_at' => license_normalize_date($payload['issued_at'] ?? null),
            'signature' => trim((string) ($payload['signature'] ?? '')),
            'max_domains' => isset($payload['max_domains']) ? (int) $payload['max_domains'] : 1,
        ];

        foreach (['license_id', 'product_name', 'issued_to', 'domain_name', 'expires_on', 'issued_at', 'signature'] as $required) {
            if (empty($normalized[$required])) {
                return ['success' => false, 'message' => 'Missing license field: ' . $required . '.'];
            }
        }

        if (!license_verify_signature($normalized, $normalized['signature'])) {
            return ['success' => false, 'message' => 'Signed license certificate verification failed.'];
        }

        $bindingValidation = license_validate_runtime_binding($normalized);
        if (!$bindingValidation['success']) {
            return $bindingValidation;
        }

        return ['success' => true, 'payload' => $normalized];
    }
}

if (!function_exists('license_decode_pem')) {
    function license_decode_pem(string $input): ?string
    {
        if (!str_starts_with(trim($input), '-----BEGIN LICENSE-----')) {
            return null;
        }
        $lines = explode("\n", $input);
        $body = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '-----')) {
                continue;
            }
            $body .= $line;
        }
        $decoded = base64_decode($body, true);
        return $decoded !== false ? $decoded : null;
    }
}

if (!function_exists('license_apply_input')) {
    function license_apply_input(string $input): array
    {
        $input = trim($input);
        if ($input === '') {
            return ['success' => false, 'message' => 'License input is empty.'];
        }

        $originalInput = $input;

        // Auto-detect PEM format and decode to JSON
        $pemJson = license_decode_pem($input);
        if ($pemJson !== null) {
            $input = $pemJson;
        }

        $decoded = json_decode($input, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'message' => 'Paste a valid signed license PEM certificate. Raw input is not supported.'];
        }

        $validation = license_validate_certificate_payload($decoded);
        if (!$validation['success']) {
            return $validation;
        }

        $currentState = license_read_state();
        $currentState = array_merge($currentState, $validation['payload']);
        unset($currentState['license_key']);

        if (!license_write_state($currentState)) {
            return ['success' => false, 'message' => 'Failed to save signed license certificate to secure storage.'];
        }

        license_clear_status_cache();
        return ['success' => true, 'message' => 'Signed license certificate applied successfully.'];
    }
}
