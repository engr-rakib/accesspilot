<?php
$crt = '/data/secure/ssl/accesspilot.crt';
$key = '/data/secure/ssl/accesspilot.key';
@mkdir('/data/secure/ssl', 0755, true);
if (file_exists($crt)) {
    exit(0);
}
$pk = openssl_pkey_new(['private_key_bits' => 2048, 'digest_alg' => 'sha256']);
$csr = openssl_csr_new(['CN' => 'AccessPilot', 'O' => 'AccessPilot'], $pk, ['digest_alg' => 'sha256']);
$x509 = openssl_csr_sign($csr, null, $pk, 3650, ['digest_alg' => 'sha256']);
openssl_x509_export_to_file($x509, $crt);
openssl_pkey_export_to_file($pk, $key);
chmod($key, 0640);
