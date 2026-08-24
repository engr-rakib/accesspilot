<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }
define('_CORE_ADMIN_', true);
header('Content-Type: application/json');
require_once __DIR__ . '/../../../Infrastructure/Persistence/repositories.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.']);
    exit();
}

$active_users = array_keys(repo_read_authenticated_users());
sort($active_users);
echo json_encode($active_users);

