<?php
define('_CORE_ADMIN_', true);

global $app_config;

require_once __DIR__ . '/../../../bootstrap/app.php';
require_once __DIR__ . '/../Middleware/session_guard.php';
require_once __DIR__ . '/../Routing/page_registry.php';
require_once __DIR__ . '/../Routing/spa_response.php';
require_once __DIR__ . '/Controllers/user_management.php';
require_once __DIR__ . '/Controllers/action_form.php';
require_once __DIR__ . '/../../Domain/Licensing/license_service.php';

$bootstrap = core_admin_bootstrap();
$app_config = $bootstrap['app_config'];
$secure_appusers_dir = $bootstrap['secure_appusers_dir'];

core_admin_require_authenticated_session();

include __DIR__ . '/../../../bootstrap/request_context.php';
include_once __DIR__ . '/../Support/helpers.php';
require_once __DIR__ . '/../../Infrastructure/Persistence/repositories.php';

$message = '';
$infoOutput = '';
$apiData = null;
$actionTaken = '';
$showUserInfoSection = false;
$todayLogActionsDistribution = [];
$detailedLogs = [];

$actionFormState = core_admin_handle_action_form_request();
$message = $actionFormState['message'];
$infoOutput = $actionFormState['infoOutput'];
$apiData = $actionFormState['apiData'];
$actionTaken = $actionFormState['actionTaken'];
$showUserInfoSection = $actionFormState['showUserInfoSection'];

if (core_admin_is_user_management_page($_GET['page'] ?? '')) {
    $userManagementState = core_admin_handle_user_management_page($app_config);
    $registration_requests = $userManagementState['registration_requests'];
    $users = $userManagementState['users'];
}

$page = $_GET['page'] ?? 'default';
$licenseStatus = license_get_status();
$isLicenseRestricted = !empty($licenseStatus['is_restricted']);

// Remove the hard redirect to allow navigation while restricted
// if ($isLicenseRestricted && $page !== 'license') {
//     redirect_to_license_center();
// }

$pageConfig = core_admin_resolve_page_config($page, $baseURL, $app_config);
$pageTitle = $pageConfig['pageTitle'] ?? '';
$pageDescription = $pageConfig['pageDescription'] ?? '';
$content_for_layout = $pageConfig['content_for_layout'] ?? '';
$show_sidebar = $pageConfig['show_sidebar'] ?? true;
$page_styles = $pageConfig['page_styles'] ?? [];
$page_scripts = $pageConfig['page_scripts'] ?? [];

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'SPA-Request') {
    core_admin_render_spa_response([
        'pageTitle' => $pageTitle,
        'pageDescription' => $pageDescription,
        'content_for_layout' => $content_for_layout,
        'page_scripts' => $page_scripts,
        'page_styles' => $page_styles,
        'page' => $page,
        'baseURL' => $baseURL,
        'app_config' => $app_config,
        'view_data' => [
            'users' => $users ?? [],
            'registration_requests' => $registration_requests ?? [],
        ],
    ]);
}

include __DIR__ . '/../../../resources/views/layouts/master.php';
