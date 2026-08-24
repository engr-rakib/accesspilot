    <?php
require_once include_path('app/Domain/UserManagement/user_management_service.php');
$pending_requests_count = getPendingRegistrationRequestCount();
$isLicenseRestricted = !empty($licenseStatus['is_restricted']);
?>
    <header class="navbar navbar-expand-lg navbar-dark bg-dark shell-app-header">
        <div class="container-fluid">
            <!-- Logo and brand name -->
            <a class="navbar-brand shell-brand" href="#">
                <?php
                    $logo_path = $app_config['app_info']['logo_path'];
                    $logo_src = (strpos($logo_path, 'http') === 0) ? $logo_path : $baseURL . $logo_path;
                ?>
                <img src="<?= $logo_src ?>" alt="Logo" height="40" class="d-inline-block align-top shell-brand-logo" style="width: auto;">
                <span class="shell-brand-copy">
                    <span class="shell-brand-app"><?= htmlspecialchars($app_config['app_info']['name'] ?? 'AccessPilot') ?></span>
                    <span id="portal-page-title"><?= htmlspecialchars($pageTitle ?? ($app_config['app_info']['name'] ?? 'AccessPilot')) ?></span>
                </span>
            </a>

            <?php if ($isLicenseRestricted): ?>
            <div class="ms-4 alert alert-danger py-1 px-3 mb-0 d-flex align-items-center shadow-sm" style="font-size: 0.85rem; border-radius: 20px;">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Restricted Mode:</strong>&nbsp;Please purchase and provide a legal license to enable operations.
            </div>
            <?php endif; ?>

            <!-- Spacer to push the following items to the right -->
            <div class="flex-grow-1"></div>

            <!-- User info and theme selector -->
            <div class="d-flex align-items-center shell-header-actions">
                <!-- User Info -->
                <div class="header-info-group text-end me-3 shell-presence-block">
                    <span class="d-block" id="current-time"><?= date('h:i A') ?></span>
                    <span class="d-block welcome-message"><?= $welcomeMessage ?></span>
                </div>

                <?php if (function_exists('has_permission') && has_permission('card_notification_center')): ?>
                <button class="btn notification-bell-btn me-2" type="button" id="notificationBellButton" aria-label="Open notifications" data-noc-tip="System Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge" style="display:none;">0</span>
                </button>
                <?php endif; ?>

                <!-- Theme Selector -->
                <div class="theme-selector-container shell-theme-palette">
                    <div class="theme-color-box theme-corporate-blue-bg" data-theme="theme-corporate-blue" data-noc-tip="Corporate Blue Theme"></div>
                    <div class="theme-color-box theme-red-bg" data-theme="theme-red" data-noc-tip="Red Theme"></div>
                    <div class="theme-color-box theme-natural-green-bg" data-theme="theme-natural-green" data-noc-tip="Natural Green Theme"></div>
                    <div class="theme-color-box theme-matte-black-bg" data-theme="theme-matte-black" data-noc-tip="Matte Black Theme"></div>
                </div>
            </div>
        </div>
    </header>
