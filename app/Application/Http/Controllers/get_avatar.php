<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
define('_CORE_ADMIN_', true);

require_once __DIR__ . '/../../../Infrastructure/Persistence/repositories.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    exit();
}

$filename = $_GET['file'] ?? '';
if (empty($filename)) {
    header('X-Accel-Redirect: /assets/images/logo.png');
    exit();
}

$filePath = repo_profile_img_path($filename);

if (file_exists($filePath)) {
    header('X-Accel-Redirect: /_xaccel/avatar/' . rawurlencode($filename));
} else {
    error_log("Avatar not found: " . $filePath);
    header('X-Accel-Redirect: /assets/images/logo.png');
}
exit();
