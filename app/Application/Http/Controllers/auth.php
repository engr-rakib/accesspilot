<?php

ob_start();
$forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
$isHttps = $forwardedProto === 'https'
    || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

require_once __DIR__ . '/../../Support/helpers.php';
require_once __DIR__ . '/../../../Domain/Auth/auth_service.php';
require_once __DIR__ . '/../../../Domain/Licensing/license_service.php';

$response = ['success' => false, 'message' => 'Invalid request.'];

$data = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_array($data)) {
    $response = auth_handle_request($data);
}

ob_clean();
echo json_encode($response);
