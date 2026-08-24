<?php
global $app_config;
$devName = htmlspecialchars($app_config['footer']['developer_name'] ?? 'Rakibuzzaman');
$appName = htmlspecialchars($app_config['app_info']['name'] ?? 'AccessPilot');
$appVer  = htmlspecialchars($app_config['app_info']['version'] ?? '2.0.0');
$year    = $app_config['footer']['copyright_year'] ?? date('Y');
?>
<footer class="app-signature-footer">
    <div class="footer-sig-wrapper">
        <span class="footer-main-row">
            <span class="sig-main-text"><?= $appName ?> v<?= $appVer ?></span>
            <span class="dot-sep">&middot;</span>
            <span class="sig-dev">Developed by <strong><a href="https://engr-rakib.github.io/web/" target="_blank"><?= $devName ?></a></strong></span>
            <span class="dot-sep">&middot;</span>
            <span>© <?= $year ?> All Rights Reserved</span>
        </span>
        <span class="footer-links-row">
            <a href="#">Privacy Policy</a>
            <span class="footer-sep">|</span>
            <a href="#">Terms of Service</a>
            <span class="footer-sep">|</span>
            <a href="#">Contact Support</a>
        </span>
    </div>
</footer>
