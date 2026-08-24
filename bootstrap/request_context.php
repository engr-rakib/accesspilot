<?php
require_once __DIR__ . '/../app/Application/Support/helpers.php';
require_once __DIR__ . '/../app/Domain/Licensing/license_service.php';

$currentVersion = "v12.32";
$updateDate = "July 20, 2025";

$base_path = base_path();
$baseURL = base_url();

$username = $_SESSION['username'] ?? 'Guest';
$welcomeMessage = htmlspecialchars($username);
$domain = license_runtime_domain_name();
