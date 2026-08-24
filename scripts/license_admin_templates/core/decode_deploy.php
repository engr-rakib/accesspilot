<?php
// scripts/license_admin_templates/core/decode_deploy.php
// Standalone CLI script to decrypt a Deployment ID.
// Usage: php decode_deploy.php --deployment-id="hex:hex"

$options = getopt('', ['deployment-id:', 'key:']);

$encoded = trim((string) ($options['deployment-id'] ?? ''));
if (!$encoded) {
    fwrite(STDERR, "Usage: php decode_deploy.php --deployment-id=\"hex:hex\"\n");
    exit(1);
}

// Key derivation (same logic as helpers.php → deployment_encryption_key())
$rawKey = trim((string) ($options['key'] ?? ''));
if ($rawKey === '') {
    // Try loading from config/app.php if available
    $configPath = __DIR__ . '/../../../config/app.php';
    if (file_exists($configPath)) {
        $cfg = include $configPath;
        $rawKey = (string) ($cfg['encryption_key'] ?? '');
    }
}

if (strlen($rawKey) < 32) {
    $rawKey = hash('sha256', $rawKey ?: 'AccessPilotDeploySecretKey2026');
}
$key = substr($rawKey, 0, 32);

// Decrypt (same logic as helpers.php → decrypt_deployment_data())
$parts = explode(':', $encoded, 2);
if (count($parts) !== 2) {
    echo json_encode(['success' => false, 'message' => 'Invalid format — expected hex:hex']);
    exit;
}

$iv = @hex2bin($parts[0]);
$ciphertext = @hex2bin($parts[1]);
if ($iv === false || $ciphertext === false || strlen($iv) !== 16) {
    echo json_encode(['success' => false, 'message' => 'Invalid hex data']);
    exit;
}

$decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
if ($decrypted === false) {
    echo json_encode(['success' => false, 'message' => 'Decryption failed — wrong key or corrupted data']);
    exit;
}

$parts = explode('|', $decrypted, 2);
echo json_encode([
    'success' => true,
    'org_name' => $parts[0] ?? '',
    'domain_name' => $parts[1] ?? '',
    'deployment_id' => $encoded,
]);
