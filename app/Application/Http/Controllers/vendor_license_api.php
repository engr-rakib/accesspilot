<?php
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', '0');
while (ob_get_level() > 0) { @ob_end_clean(); }
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!defined('_CORE_ADMIN_')) {
    define('_CORE_ADMIN_', true);
}

require_once __DIR__ . '/../../Support/helpers.php';
require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Domain/Licensing/license_service.php';
require_once __DIR__ . '/../../../Domain/Audit/audit_service.php';
require_once __DIR__ . '/../../../Domain/Notifications/notification_service.php';
require_once __DIR__ . '/../../../Infrastructure/Persistence/repositories.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

if (!has_permission('page_vendor_console') && !has_permission('page_license')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden: Vendor console permission required.']);
    exit;
}

$username = (string) ($_SESSION['username'] ?? 'unknown');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = trim((string) ($_GET['action'] ?? ''));

// ── Storage: vendor_issued_licenses/ (client-bound PEM files for Vendor Console tracking) ──
function vendor_pem_dir(): string
{
    return license_vendor_issued_dir();
}

function vendor_pem_encode(array $data): string
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return license_encode_pem($json);
}

function vendor_pem_paths_for_id(string $id): array
{
    $matches = [];
    foreach (glob(vendor_pem_dir() . '/*.pem') ?: [] as $path) {
        $content = @file_get_contents($path);
        if ($content === false) {
            continue;
        }
        $data = license_decode_pem_array($content);
        if (($data['license_id'] ?? '') === $id) {
            $matches[] = $path;
        }
    }

    usort($matches, static fn($a, $b) => filemtime($b) <=> filemtime($a));
    return $matches;
}

function vendor_pem_list(): array
{
    $dir = vendor_pem_dir();
    $files = glob($dir . '/*.pem') ?: [];
    if (!$files) {
        return [];
    }

    $byId = [];
    foreach ($files as $path) {
        $content = @file_get_contents($path);
        if ($content === false) {
            continue;
        }
        $data = license_decode_pem_array($content);
        if (!$data || empty($data['license_id'])) {
            continue;
        }

        $licenseId = (string) $data['license_id'];
        $mtime = (int) filemtime($path);
        if (isset($byId[$licenseId]) && $byId[$licenseId]['_mtime'] >= $mtime) {
            continue;
        }

        $expiresOn = $data['expires_on'] ?? '';
        $now = date('Y-m-d');
        $byId[$licenseId] = [
            'id' => $licenseId,
            'license_id' => $licenseId,
            'product_name' => $data['product_name'] ?? 'AccessPilot',
            'issued_to' => $data['issued_to'] ?? '',
            'domain_name' => $data['domain_name'] ?? '',
            'deployment_id' => $data['deployment_id'] ?? '',
            'expires_on' => $expiresOn,
            'issued_at' => $data['issued_at'] ?? '',
            'max_domains' => (int) ($data['max_domains'] ?? 1),
            'signature' => $data['signature'] ?? '',
            'type' => str_starts_with($licenseId, 'REN') ? 'renew' : 'issue',
            'status' => ($expiresOn && $expiresOn < $now) ? 'expired' : 'active',
            'created_at' => date('Y-m-d H:i:s', $mtime),
            'updated_at' => date('Y-m-d H:i:s', $mtime),
            '_file' => $path,
            '_mtime' => $mtime,
        ];
    }

    return array_values($byId);
}

function vendor_pem_get_by_id(string $id): ?array
{
    $paths = vendor_pem_paths_for_id($id);
    if (!$paths) {
        return null;
    }

    foreach (vendor_pem_list() as $item) {
        if ($item['id'] === $id) {
            return $item;
        }
    }

    return null;
}

function vendor_pem_delete_by_id(string $id): bool
{
    $paths = vendor_pem_paths_for_id($id);
    if (!$paths) {
        return false;
    }

    $deleted = false;
    foreach ($paths as $path) {
        if (@unlink($path)) {
            $deleted = true;
        }
    }

    return $deleted;
}

function vendor_pem_update_by_id(string $id, array $updates): bool
{
    $paths = vendor_pem_paths_for_id($id);
    if (!$paths) {
        return false;
    }

    $content = @file_get_contents($paths[0]);
    if ($content === false) {
        return false;
    }

    $data = license_decode_pem_array($content);
    if (!$data) {
        return false;
    }

    foreach ($updates as $k => $v) {
        switch ($k) {
            case 'issued_to': $data['issued_to'] = $v; break;
            case 'domain_name': $data['domain_name'] = $v; break;
            case 'deployment_id': $data['deployment_id'] = $v; break;
            case 'expires_on': $data['expires_on'] = $v; break;
            case 'max_domains': $data['max_domains'] = (int) $v; break;
            case 'product_name': $data['product_name'] = $v; break;
        }
    }

    $targetPath = vendor_pem_dir() . DIRECTORY_SEPARATOR . license_vendor_issued_filename($data);
    foreach ($paths as $legacyPath) {
        if ($legacyPath !== $targetPath && file_exists($legacyPath)) {
            @unlink($legacyPath);
        }
    }

    return file_put_contents($targetPath, vendor_pem_encode($data)) !== false;
}

function vendor_private_key_path(): string
{
    $vaultPath = dirname(__DIR__, 4) . '/scripts/license_admin_templates/vault/private_key.pem';
    if (file_exists($vaultPath)) {
        return $vaultPath;
    }
    return license_vendor_signing_keys_dir() . '/private_key.pem';
}

function vendor_public_key_path(): string
{
    return license_vendor_signing_keys_dir() . '/public_key.pem';
}

// ── Signing helpers (mirrors generator.php) ──────────────────
function vendor_build_signing_string(array $payload): string
{
    $parts = [
        $payload['license_id'],
        $payload['product_name'],
        $payload['issued_to'],
        $payload['domain_name'],
    ];
    if (!empty($payload['deployment_id'])) {
        $parts[] = $payload['deployment_id'];
    }
    $parts[] = $payload['expires_on'];
    $parts[] = $payload['issued_at'];
    if (!empty($payload['max_domains'])) {
        $parts[] = (string) $payload['max_domains'];
    }
    return strtoupper(implode('|', $parts));
}

function vendor_sign_payload(array $payload): ?string
{
    $keyPath = vendor_private_key_path();
    if (!file_exists($keyPath) || !is_readable($keyPath)) {
        return null;
    }
    $keyContents = file_get_contents($keyPath);
    $privateKey = openssl_pkey_get_private($keyContents);
    if ($privateKey === false) {
        return null;
    }
    $signingString = vendor_build_signing_string($payload);
    $signature = '';
    if (!openssl_sign($signingString, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        return null;
    }
    return base64_encode($signature);
}

function vendor_build_full_payload(array $license, ?string $signature = null): array
{
    $payload = [
        'license_id' => $license['id'],
        'product_name' => $license['product_name'],
        'issued_to' => $license['issued_to'],
        'domain_name' => $license['domain_name'],
        'deployment_id' => $license['deployment_id'],
        'expires_on' => $license['expires_on'],
        'issued_at' => $license['issued_at'],
        'max_domains' => $license['max_domains'],
    ];
    if ($signature !== null) {
        $payload['signature'] = $signature;
    }
    return $payload;
}

// ── Actions ───────────────────────────────────────────────────

// List all licenses (from PEM files)
if ($action === 'vendor_list' && $method === 'GET') {
    $licenses = vendor_pem_list();
    usort($licenses, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    // Strip internal _file field
    $licenses = array_map(fn($l) => array_filter($l, fn($k) => $k !== '_file', ARRAY_FILTER_USE_KEY), $licenses);
    echo json_encode(['success' => true, 'licenses' => $licenses]);
    exit;
}

// Get single license
if ($action === 'vendor_get' && $method === 'GET') {
    $id = trim((string) ($_GET['id'] ?? ''));
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Missing license ID.']);
        exit;
    }
    $license = vendor_pem_get_by_id($id);
    if (!$license) {
        echo json_encode(['success' => false, 'message' => 'License not found.']);
        exit;
    }
    echo json_encode(['success' => true, 'license' => $license]);
    exit;
}

// Save new license
if ($action === 'vendor_save' && $method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $required = ['issued_to', 'domain_name', 'deployment_id', 'expires_on'];
    $missing = [];
    foreach ($required as $f) {
        if (empty($data[$f])) $missing[] = $f;
    }
    if ($missing) {
        echo json_encode(['success' => false, 'message' => 'Missing fields: ' . implode(', ', $missing)]);
        exit;
    }

    $now = date('Y-m-d H:i:s');
    $datePrefix = date('Ymd');
    $rand = random_int(1000, 9999);
    $prefix = ($data['type'] ?? '') === 'renew' ? 'REN' : 'LIC';
    $licId = $prefix . '-' . $datePrefix . '-' . $rand;

    $entry = [
        'license_id' => $licId,
        'product_name' => $data['product_name'] ?? 'AccessPilot',
        'issued_to' => trim($data['issued_to']),
        'domain_name' => trim($data['domain_name']),
        'deployment_id' => trim($data['deployment_id']),
        'expires_on' => trim($data['expires_on']),
        'issued_at' => date('Y-m-d'),
        'max_domains' => (int) ($data['max_domains'] ?? 1),
    ];

    // Sign + save PEM to vendor_issued_licenses/
    $entry['signature'] = vendor_sign_payload($entry) ?? '';
    $pemPath = vendor_pem_dir() . DIRECTORY_SEPARATOR . license_vendor_issued_filename($entry);
    file_put_contents($pemPath, vendor_pem_encode($entry));

    log_activity($username, 'vendor_license_generate', 'success', 'Generated license ' . $licId . ' for ' . $entry['issued_to']);

    if (function_exists('notifications_create_manual_notification')) {
        notifications_create_manual_notification([
            'title' => 'License Generated',
            'message' => 'Vendor generated license ' . $licId . ' for ' . $entry['issued_to'] . ' (' . $entry['domain_name'] . ').',
            'severity' => 'success',
            'category' => 'announcement',
            'target_url' => '/index.php?page=vendor_console',
            'is_persistent' => false,
        ], 'system');
    }

    $entry['id'] = $licId;
    echo json_encode(['success' => true, 'license' => $entry, 'message' => 'License ' . $licId . ' saved.']);
    exit;
}

// Update license
if ($action === 'vendor_update' && $method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $id = trim((string) ($data['id'] ?? ''));
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Missing license ID.']);
        exit;
    }

    $existing = vendor_pem_get_by_id($id);
    if (!$existing) {
        echo json_encode(['success' => false, 'message' => 'License not found.']);
        exit;
    }

    $updates = [];
    foreach (['issued_to', 'expires_on', 'max_domains'] as $field) {
        if (isset($data[$field])) {
            $updates[$field] = $data[$field];
        }
    }

    if (!vendor_pem_update_by_id($id, $updates)) {
        echo json_encode(['success' => false, 'message' => 'Failed to update license.']);
        exit;
    }

    log_activity($username, 'vendor_license_update', 'success', 'Updated license ' . $id);

    echo json_encode(['success' => true, 'message' => 'License ' . $id . ' updated.']);
    exit;
}

// Delete license
if ($action === 'vendor_delete' && $method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $id = trim((string) ($data['id'] ?? ''));
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Missing license ID.']);
        exit;
    }

    if (!vendor_pem_delete_by_id($id)) {
        echo json_encode(['success' => false, 'message' => 'License not found.']);
        exit;
    }

    log_activity($username, 'vendor_license_delete', 'success', 'Deleted license ' . $id);

    echo json_encode(['success' => true, 'message' => 'License deleted.']);
    exit;
}

// Download license as PEM (signed with RSA-SHA256 if private key available)
if ($action === 'vendor_download' && $method === 'GET') {
    $id = trim((string) ($_GET['id'] ?? ''));

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Missing license ID.']);
        exit;
    }

    $license = vendor_pem_get_by_id($id);
    if (!$license) {
        echo json_encode(['success' => false, 'message' => 'License not found.']);
        exit;
    }

    $signature = vendor_sign_payload($license);
    $payload = vendor_build_full_payload($license, $signature);

    header('Content-Type: application/x-pem-file');
    header('Content-Disposition: attachment; filename="license_' . $license['domain_name'] . '.pem"');
    echo vendor_pem_encode($payload);
    exit;
}

// Verify license integrity
if ($action === 'vendor_verify' && $method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $id = trim((string) ($data['id'] ?? ''));
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Missing license ID.']);
        exit;
    }

    $license = vendor_pem_get_by_id($id);
    if (!$license) {
        echo json_encode(['success' => false, 'message' => 'License not found.']);
        exit;
    }

    $expiresOn = $license['expires_on'] ?? '';
    $now = date('Y-m-d');
    $isExpired = $expiresOn && $expiresOn < $now;
    $hasPrivateKey = file_exists(vendor_private_key_path());

    // Generate a test signature to verify the signing chain works
    $signature = vendor_sign_payload($license);
    $signatureOk = $signature !== null;
    $deployDecryptable = function_exists('decrypt_deployment_data') ? decrypt_deployment_data($license['deployment_id'] ?? '') : null;
    $deployDecoded = $deployDecryptable !== null;
    $deployParts = $deployDecoded ? explode('|', $deployDecryptable, 2) : [];
    $deployMatches = $deployDecoded && ($deployParts[0] ?? '') === ($license['issued_to'] ?? '') && ($deployParts[1] ?? '') === ($license['domain_name'] ?? '');

    $checks = [
        ['label' => 'License ID Format', 'status' => preg_match('/^(LIC|REN)-\d{8}-\d{4}$/', $license['id'] ?? '') ? 'pass' : 'fail'],
        ['label' => 'Client Name', 'status' => !empty($license['issued_to']) ? 'pass' : 'fail'],
        ['label' => 'Domain Name', 'status' => !empty($license['domain_name']) ? 'pass' : 'fail'],
        ['label' => 'Deployment ID', 'status' => !empty($license['deployment_id']) ? 'pass' : 'fail'],
        ['label' => 'Deployment ID Decryptable', 'status' => $deployDecoded ? 'pass' : 'warn', 'note' => $deployDecoded ? '' : 'Not encrypted — legacy UUID or custom value'],
        ['label' => 'Deployment ID Matches', 'status' => $deployMatches ? 'pass' : 'warn', 'note' => $deployMatches ? '' : 'Encrypted org/domain differs from license fields'],
        ['label' => 'Expiry Date', 'status' => !empty($expiresOn) ? 'pass' : 'fail'],
        ['label' => 'Expiry Status', 'status' => $isExpired ? 'warn' : 'pass'],
        ['label' => 'Max Domains', 'status' => ($license['max_domains'] ?? 1) >= 0 ? 'pass' : 'fail'],
    ];

    if ($hasPrivateKey) {
        $checks[] = [
            'label' => 'RSA-SHA256 Signature',
            'status' => $signatureOk ? 'pass' : 'err',
            'note' => $signatureOk ? 'Signed on download' : 'Signing failed — check private key'
        ];
    } else {
        $checks[] = [
            'label' => 'RSA-SHA256 Signature',
            'status' => 'warn',
            'note' => 'No private key configured — signature will be missing from payload. Upload key in Console Settings.'
        ];
    }

    log_activity($username, 'vendor_license_verify', $isExpired ? 'warning' : 'success', 'Verified license ' . $id . ' for ' . $license['issued_to']);

    echo json_encode([
        'success' => true,
        'license_id' => $id,
        'overall' => ($isExpired || !$signatureOk) ? 'warning' : 'pass',
        'checks' => $checks,
    ]);
    exit;
}

// Check private key status
if ($action === 'vendor_key_status' && $method === 'GET') {
    $keyPath = vendor_private_key_path();
    $hasKey = file_exists($keyPath) && is_readable($keyPath);
    $keyInfo = null;
    if ($hasKey) {
        $keyContents = file_get_contents($keyPath);
        $res = openssl_pkey_get_private($keyContents);
        if ($res !== false) {
            $details = openssl_pkey_get_details($res);
            $keyInfo = [
                'bits' => $details['bits'] ?? 0,
                'type' => 'RSA',
            ];
        }
    }

    // Check if public key also exists
    $hasPublicKey = file_exists(vendor_public_key_path());

    echo json_encode([
        'success' => true,
        'has_private_key' => $hasKey,
        'has_public_key' => $hasPublicKey,
        'key_info' => $keyInfo,
        'private_key_path' => $keyPath,
    ]);
    exit;
}

// Save/upload private key
if ($action === 'vendor_save_key' && $method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $keyContent = trim((string) ($data['private_key'] ?? ''));
    if (empty($keyContent)) {
        echo json_encode(['success' => false, 'message' => 'Private key content is empty.']);
        exit;
    }

    // Validate it's a real RSA private key
    $res = openssl_pkey_get_private($keyContent);
    if ($res === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid private key — could not parse. Ensure it is a valid RSA private key in PEM format.']);
        exit;
    }

    $details = openssl_pkey_get_details($res);
    $keyPath = vendor_private_key_path();
    $written = file_put_contents($keyPath, $keyContent) !== false;

    if ($written) {
        // Auto-sync public key so verification works on license upload
        $pubKeyPem = $details['key'] ?? '';
        $pubKeyPath = function_exists('config_get') ? (string) config_get('license.public_key_path', '') : '';
        if ($pubKeyPath === '' || !$pubKeyPath) {
            $pubKeyPath = dirname(__DIR__, 4) . '/config/license_public.pem';
        }
        if ($pubKeyPem && $pubKeyPath) {
            $dir = dirname($pubKeyPath);
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            file_put_contents($pubKeyPath, $pubKeyPem);
        }

        log_activity($username, 'vendor_key_upload', 'success', 'Uploaded RSA private key (' . ($details['bits'] ?? 0) . ' bits)');
        echo json_encode([
            'success' => true,
            'message' => 'Private key saved (' . ($details['bits'] ?? 0) . '-bit RSA). Licenses will now be signed on download.',
            'bits' => $details['bits'] ?? 0,
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to write private key to secure storage.']);
    }
    exit;
}

// Delete private key
if ($action === 'vendor_delete_key' && $method === 'POST') {
    $keyPath = vendor_private_key_path();
    if (file_exists($keyPath)) {
        unlink($keyPath);
        log_activity($username, 'vendor_key_delete', 'success', 'Deleted private key');
        echo json_encode(['success' => true, 'message' => 'Private key deleted.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No private key found.']);
    }
    exit;
}

// Decode encrypted deployment ID
if ($action === 'vendor_decode_deploy' && $method === 'GET') {
    $deployId = trim((string) ($_GET['deployment_id'] ?? ''));
    if (empty($deployId)) {
        echo json_encode(['success' => false, 'message' => 'Missing deployment_id parameter.']);
        exit;
    }
    $decrypted = function_exists('decrypt_deployment_data') ? decrypt_deployment_data($deployId) : null;
    if ($decrypted === null) {
        echo json_encode(['success' => false, 'message' => 'Could not decode — invalid or legacy format.']);
        exit;
    }
    $parts = explode('|', $decrypted, 2);
    echo json_encode([
        'success' => true,
        'org_name' => $parts[0] ?? '',
        'domain_name' => $parts[1] ?? '',
        'deployment_id' => $deployId,
    ]);
    exit;
}

// ── Build + Download Client Release (direct zip, no PS script, no server files) ──
if ($action === 'vendor_build_release' && $method === 'POST') {
    try {
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        if (!class_exists('ZipArchive')) {
            echo json_encode(array('success' => false, 'message' => 'PHP Zip extension (ZipArchive) not installed.'));
            exit;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) $data = array();

        $orgName = trim((string) ($data['org_name'] ?? ''));
        if ($orgName === '') $orgName = config_get('org_name', 'Portal');
        $orgNameSafe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $orgName);
        $timestamp = date('Ymd_His');
        $zipName = $orgNameSafe . '_release_' . $timestamp . '.zip';
        $tmpDir = sys_get_temp_dir();
        if (!is_writable($tmpDir)) {
            $tmpDir = dirname($_SERVER['DOCUMENT_ROOT']) . '/tmp';
            if (!is_dir($tmpDir)) @mkdir($tmpDir, 0777, true);
            if (!is_writable($tmpDir)) {
                echo json_encode(array('success' => false, 'message' => 'Temp dir not writable: ' . sys_get_temp_dir()));
                exit;
            }
        }
        $zipPath = $tmpDir . DIRECTORY_SEPARATOR . $zipName;

        $sourceDir = dirname(__DIR__, 4);
        if (!is_dir($sourceDir)) {
            echo json_encode(array('success' => false, 'message' => 'Source dir not found: ' . $sourceDir));
            exit;
        }

        $excludeDirs = array(
            'dist_release', '.git', '.claude', '.synkron.syncdb',
            'node_modules', '.vscode', '.idea', '__pycache__',
        );
        $excludeFiles = array(
            'phperror8.5.4_nts.log', '.DS_Store', 'Thumbs.db',
        );
        $stripPaths = array(
            'scripts/license_admin_templates/',
            'analysis/codebase_upgrade_plan/',
            'docs/internal/',
            'docs/Technical/',
        );

        $zipFlags = ZipArchive::CREATE;
        if (defined('ZipArchive::OVERWRITE')) $zipFlags |= ZipArchive::OVERWRITE;
        else if (file_exists($zipPath)) @unlink($zipPath);

        $zip = new ZipArchive();
        $res = $zip->open($zipPath, $zipFlags);
        if ($res !== true) {
            echo json_encode(array('success' => false, 'message' => 'ZipArchive::open failed (code: ' . $res . ').'));
            exit;
        }

        $added = 0;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $file) {
            $fp = $file->getRealPath();
            if ($fp === false) continue;
            $rp = substr($fp, strlen($sourceDir) + 1);
            $rp = str_replace('\\', '/', $rp);
            if ($rp === '') continue;

            $skip = false;
            foreach ($excludeDirs as $ed) {
                if (strpos($rp, $ed . '/') === 0 || $rp === $ed) { $skip = true; break; }
            }
            if ($skip) continue;

            $base = basename($rp);
            if (in_array($base, $excludeFiles)) continue;

            $strip = false;
            foreach ($stripPaths as $sp) {
                if (strpos($rp, $sp) === 0) { $strip = true; break; }
            }
            if ($strip) continue;

            $parts = explode('/', $rp);
            if (count($parts) >= 2) {
                $parent = $parts[0] . '/' . $parts[1];
                foreach ($stripPaths as $sp) {
                    if (strpos($parent, $sp) === 0 || strpos($rp, $sp) === 0) { $strip = true; break; }
                }
            }
            if ($strip) continue;

            if (!$file->isFile() && !$file->isLink()) continue;

            $zip->addFile($fp, $rp);
            $added++;
        }

        $zip->close();

        if ($added === 0) {
            @unlink($zipPath);
            echo json_encode(array('success' => false, 'message' => 'No files matched.'));
            exit;
        }

        $_SESSION['vendor_download_zip'] = $zipPath;
        $_SESSION['vendor_download_name'] = $zipName;

        echo json_encode(array(
            'success' => true,
            'zip_name' => $zipName,
            'files' => $added,
        ));
    } catch (Exception $e) {
        echo json_encode(array('success' => false, 'message' => get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()));
    }
    exit;
}

// ── Download Client Release ────────────────────────────────
if ($action === 'vendor_download_release' && $method === 'GET') {
    $zipPath = isset($_SESSION['vendor_download_zip']) ? $_SESSION['vendor_download_zip'] : '';
    if (!$zipPath || !file_exists($zipPath)) {
        http_response_code(404);
        echo json_encode(array('success' => false, 'message' => 'No release zip found. Build one first.'));
        exit;
    }

    $zipName = basename($zipPath);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);

    @unlink($zipPath);
    unset($_SESSION['vendor_download_zip']);
    exit;
}

// ── Check credential state (per session) ────────────────────
if ($action === 'vendor_check_creds' && $method === 'GET') {
    $verified = !empty($_SESSION['vendor_creds_verified']);
    echo json_encode(['success' => true, 'verified' => $verified]);
    exit;
}

// ── Credential Verification (for sensitive page access) ──────
if ($action === 'vendor_verify_creds' && $method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $uid = trim((string) ($data['user_id'] ?? ''));
    $pwd = (string) ($data['password'] ?? '');

    if ($uid === '' || $pwd === '') {
        echo json_encode(['success' => false, 'message' => 'User ID and Password are required.']);
        exit;
    }

    $users = repo_read_users();
    if (!isset($users[$uid]) || !password_verify($pwd, $users[$uid]['password'])) {
        log_activity($username, 'vendor_credential_verify', 'failure', "Credential verification failed for user '$uid'.");
        echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
        exit;
    }

    $_SESSION['vendor_creds_verified'] = true;
    log_activity($username, 'vendor_credential_verify', 'success', "Credential verified for user '$uid'.");
    echo json_encode(['success' => true, 'message' => 'Verified.']);
    exit;
}

// ── Fallback ──────────────────────────────────────────────────
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action or method.']);
