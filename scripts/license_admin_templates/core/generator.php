<?php
// scripts/license_admin_templates/core/generator.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Dhaka'); // Adjust if needed, but this matches your current output offset

$options = getopt('', [
    'id:',
    'product:',
    'client:',
    'domain:',
    'deployment-id:',
    'expiry:',
    'max-domains:',
    'private-key::',
    'public-key::',
    'allow-keygen'
]);

$vaultDir = realpath(__DIR__ . '/../vault') ?: (__DIR__ . '/../vault');
$defaultPrivateKeyPath = $vaultDir . '/private_key.pem';
$defaultPublicKeyPath = __DIR__ . '/../../../config/license_public.pem';
$envPrivateKeyPath = getenv('ACCESSPILOT_VENDOR_PRIVATE_KEY_PATH');
$envPublicKeyPath = getenv('ACCESSPILOT_LICENSE_PUBLIC_KEY_PATH');

$privateKeyCandidate = $options['private-key'] ?? null;
if ($privateKeyCandidate === null || trim((string) $privateKeyCandidate) === '') {
    $privateKeyCandidate = $envPrivateKeyPath;
}
if ($privateKeyCandidate === false || trim((string) $privateKeyCandidate) === '') {
    $privateKeyCandidate = $defaultPrivateKeyPath;
}
$privateKeyPath = trim((string) $privateKeyCandidate);

$publicKeyCandidate = $options['public-key'] ?? null;
if ($publicKeyCandidate === null || trim((string) $publicKeyCandidate) === '') {
    $publicKeyCandidate = $envPublicKeyPath;
}
if ($publicKeyCandidate === false || trim((string) $publicKeyCandidate) === '') {
    $publicKeyCandidate = $defaultPublicKeyPath;
}
$publicKeyPath = trim((string) $publicKeyCandidate);
$allowKeygen = array_key_exists('allow-keygen', $options);

if (!is_dir($vaultDir)) {
    if (!mkdir($vaultDir, 0755, true)) {
        die("ERROR: Failed to create vault directory: $vaultDir\n");
    }
}

if (!file_exists($privateKeyPath)) {
    if (!$allowKeygen) {
        fwrite(STDERR, "ERROR: Private key not found at {$privateKeyPath}\n");
        fwrite(STDERR, "Set ACCESSPILOT_VENDOR_PRIVATE_KEY_PATH or pass --private-key, or use --allow-keygen for explicit keypair creation.\n");
        exit(1);
    }

    $res = openssl_pkey_new(["private_key_bits" => 2048, "private_key_type" => OPENSSL_KEYTYPE_RSA]);
    if ($res === false) {
        die("ERROR: openssl_pkey_new failed. check openssl.cnf\n");
    }
    openssl_pkey_export($res, $privateKey);
    $pub = openssl_pkey_get_details($res)["key"];

    $privateKeyDir = dirname($privateKeyPath);
    $publicKeyDir = dirname($publicKeyPath);
    if (!is_dir($privateKeyDir)) {
        mkdir($privateKeyDir, 0755, true);
    }
    if (!is_dir($publicKeyDir)) {
        mkdir($publicKeyDir, 0755, true);
    }

    file_put_contents($privateKeyPath, $privateKey);
    file_put_contents($publicKeyPath, $pub);
    fwrite(STDERR, "WARNING: Generated a new RSA keypair. Protect the private key and distribute only the public key.\n");
}

if (!file_exists($privateKeyPath)) {
    die("ERROR: Private key not found at $privateKeyPath\n");
}

function normalize_date($value) {
    $timestamp = strtotime((string)$value);
    return $timestamp ? date('Y-m-d', $timestamp) : 'INVALID';
}

$payload = [
    'license_id' => trim((string)($options['id'] ?? 'UNSET')),
    'product_name' => trim((string)($options['product'] ?? 'AccessPilot')),
    'issued_to' => trim((string)($options['client'] ?? 'UNSET')),
    'domain_name' => trim((string)($options['domain'] ?? 'UNSET')),
    'deployment_id' => trim((string)($options['deployment-id'] ?? '')),
    'expires_on' => normalize_date($options['expiry'] ?? ''),
    'issued_at' => date('Y-m-d'),
];

$maxDomains = trim((string)($options['max-domains'] ?? ''));
if ($maxDomains !== '') {
    $payload['max_domains'] = (int) $maxDomains;
}

$signParts = [
    $payload['license_id'],
    $payload['product_name'],
    $payload['issued_to'],
    $payload['domain_name'],
];
if ($payload['deployment_id'] !== '') {
    $signParts[] = $payload['deployment_id'];
}
$signParts[] = $payload['expires_on'];
$signParts[] = $payload['issued_at'];

if (!empty($payload['max_domains'])) {
    $signParts[] = (string) $payload['max_domains'];
}

$signingString = strtoupper(implode('|', $signParts));
$keyContents = file_get_contents($privateKeyPath);
$privateKey = openssl_pkey_get_private($keyContents);

if ($privateKey === false) {
    die("ERROR: openssl_pkey_get_private failed.\n");
}

if (!openssl_sign($signingString, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
    die("ERROR: openssl_sign failed.\n");
}

$payload['signature'] = base64_encode($signature);
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
