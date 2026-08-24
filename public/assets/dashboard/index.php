<?php
// Legacy compatibility entry only.
// Real dashboard route: /public/index.php?page=dashboard
// Keep for old bookmarks and historical URLs.

$_GET['page'] = 'dashboard';
require_once __DIR__ . '/../../index.php';
