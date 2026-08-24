<?php
header('Content-Type: application/json');
$checks = [
    'status' => 'ok',
    'nginx' => true,
    'php' => true,
    'ldap' => extension_loaded('ldap'),
    'time' => date('c'),
];
http_response_code($checks['ldap'] ? 200 : 503);
echo json_encode($checks);
