<?php
// scripts/license_admin_templates/core/register_license.php
// CLI script to register a generated PEM license into vendor_issued_licenses/ for tracking.
// Usage: php register_license.php --file="path/to/license.pem"

$options = getopt('', ['file:']);
$filePath = trim((string) ($options['file'] ?? ''));
if (!$filePath || !file_exists($filePath)) {
    fwrite(STDERR, "Usage: php register_license.php --file=\"path/to/license.pem\"\n");
    exit(1);
}

// Read and verify PEM content
$content = file_get_contents($filePath);
$trimmed = trim($content);

if (!str_starts_with($trimmed, '-----BEGIN LICENSE-----')) {
    fwrite(STDERR, "Not a valid PEM file — missing BEGIN LICENSE header.\n");
    exit(1);
}

// Decode inner JSON
$body = '';
foreach (explode("\n", $trimmed) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '-----')) continue;
    $body .= $line;
}
$decodedJson = base64_decode($body, true);
if ($decodedJson === false) {
    fwrite(STDERR, "Invalid PEM — base64 decode failed.\n");
    exit(1);
}
$data = json_decode($decodedJson, true);
if (!$data || !isset($data['license_id'], $data['issued_to'], $data['domain_name'])) {
    fwrite(STDERR, "Invalid PEM — missing required license fields.\n");
    exit(1);
}

// Ensure PEM is stored in vendor_issued_licenses/
$configPath = dirname(__DIR__, 3) . '/config/storage.php';
$licenseConfigPath = dirname(__DIR__, 3) . '/config/license.php';
$storage = file_exists($configPath) ? include $configPath : [];
$licenseConfig = file_exists($licenseConfigPath) ? include $licenseConfigPath : [];
$base = $storage['storage']['secure_base_path'] ?? 'C:/inetpub/Desk_secure_files';
$pemDir = $licenseConfig['license']['vendor_issued_dir'] ?? ($base . '/vendor_issued_licenses');

if (!is_dir($pemDir)) {
    mkdir($pemDir, 0775, true);
}

// Copy PEM to vendor_issued_licenses/ if not already there
$targetDir = realpath($pemDir) ?: $pemDir;
$sourceDir = realpath(dirname($filePath)) ?: '';
$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $data['issued_to'] ?? 'License');
$targetPath = $targetDir . '/' . $safeName . '_' . $data['license_id'] . '.pem';
if ($sourceDir !== $targetDir || realpath($filePath) !== realpath($targetPath)) {
    if (!file_exists($targetPath) || filemtime($filePath) > filemtime($targetPath)) {
        file_put_contents($targetPath, $trimmed . "\n");
    }
}

echo json_encode(['success' => true, 'message' => 'Registered ' . $data['license_id'] . ' in tracking.']);
