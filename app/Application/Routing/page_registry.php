<?php

function core_admin_resolve_page_config(string $page, string $baseURL, array $app_config): array
{
    // ── Page-level RBAC map ─────────────────────────────────────────────
    // Each page requires one of the listed permissions (pipe = OR).
    // Pages with '' are accessible to any authenticated user.
    // create_role / edit_role are gated inside role_form_view.php itself.
    $page_permission_map = [
        'dashboard'           => 'page_dashboard',
        'user_management'     => 'page_user_management',
        'create_user'         => 'page_user_management|user_create',
        'edit_user'           => 'page_user_management|user_edit',
        'employee_db'         => 'page_employee_db',
        'about'               => 'page_about_us',
        'user_activity'       => 'page_application_events',
        'documentation'       => 'page_documentation',
        'documentation_guide' => 'page_documentation_guide',
        'license'             => '',
        'vendor_console'      => 'page_vendor_console',
        'license_doc'         => '',
        'ad-guide'            => '',
        'ad-objects'          => '',
        'ad-api'              => '',
        'ad-features'         => '',
        'password_manager'    => 'page_password_manager',
        'system_config'       => 'page_system_config|page_ad_administration',
        'profile'             => '',
        'monitoring'          => 'page_monitoring|page_ad_administration',
        'email_tools'         => 'page_email_tools',
        'exchange'            => 'page_exchange',
        'home'                => '',
        'default'             => '',
    ];

    $required_perm = $page_permission_map[$page] ?? ($page_permission_map['home'] ?? '');
    $can_access_page = true;
    if ($required_perm !== '') {
        $perm_list = array_filter(array_map('trim', explode('|', $required_perm)));
        $can_access_page = false;
        foreach ($perm_list as $p) {
            if (function_exists('has_permission') && ($p === '*' || has_permission($p))) {
                $can_access_page = true;
                break;
            }
        }
    }
    if (!$can_access_page) {
        return [
            'page'            => $page,
            'pageTitle'       => 'Access Denied',
            'pageDescription' => 'You do not have permission to view this page.',
            'show_sidebar'    => true,
            'content_for_layout' => include_path('resources/views/pages/auth/unauthorized_view.php'),
            'page_styles'     => [],
            'page_scripts'    => [],
        ];
    }

    $appName = $app_config['app_info']['name'] ?? 'AccessPilot';
    $pageTitle = $app_config['titles']['main_page'];
    $pageDescription = "Welcome to $appName. Your central hub for user management.";
    $show_sidebar = true;
    $content_for_layout = '';
    $page_styles = [];
    $page_scripts = [];

    switch ($page) {
        case 'dashboard':
            $pageTitle = $app_config['titles']['dashboard_page'] . ' - Overview';
            $pageDescription = "View key metrics and activity summaries for $appName.";
            $show_sidebar = true;
            $content_for_layout = include_path('resources/views/pages/dashboard/dashboard_content.php');
            $page_scripts[] = $baseURL . '/vendor/chartjs-plugin-datalabels/chartjs-plugin-datalabels.min.js?v=' . $app_config['app_info']['version'];
            $page_scripts[] = $baseURL . '/resources/frontend/js/dashboard/dash_pro_scripts.js?v=' . $app_config['app_info']['version'];
            break;

        case 'user_management':
            $pageTitle = $app_config['titles']['user_management_page'] . ' - Manage Users & Roles';
            $pageDescription = 'Manage user accounts, roles, permissions, and pending registration requests.';
            $content_for_layout = include_path('resources/views/pages/auth/user_management_view.php');
            $page_scripts[] = $baseURL . '/vendor/chartjs-plugin-datalabels/chartjs-plugin-datalabels.min.js?v=' . $app_config['app_info']['version'];
            $page_scripts[] = $baseURL . '/resources/frontend/js/modules/user_management_actions.js?v=' . $app_config['app_info']['version'];
            $page_scripts[] = $baseURL . '/resources/frontend/js/modules/role_management_actions.js?v=' . $app_config['app_info']['version'];
            break;

        case 'create_role':
            $pageTitle = 'Create New Role';
            $pageDescription = 'Create a new RBAC role and assign permissions.';
            $show_sidebar = true;
            $content_for_layout = include_path('resources/views/pages/auth/role_form_view.php');
            $page_scripts[] = $baseURL . '/resources/frontend/js/modules/role_management_actions.js?v=' . $app_config['app_info']['version'];
            break;

        case 'edit_role':
            $pageTitle = 'Edit Role';
            $pageDescription = 'Edit RBAC role metadata, permissions, and memberships.';
            $show_sidebar = true;
            $content_for_layout = include_path('resources/views/pages/auth/role_form_view.php');
            $page_scripts[] = $baseURL . '/resources/frontend/js/modules/role_management_actions.js?v=' . $app_config['app_info']['version'];
            break;

        case 'create_user':
            $pageTitle = $app_config['titles']['create_user_page'] . ' - New User Account';
            $pageDescription = 'Create a new user account with custom details and permissions.';
            $content_for_layout = include_path('resources/views/pages/auth/create_user_view.php');
            $page_scripts[] = $baseURL . '/resources/frontend/js/modules/create_user_actions.js?v=' . $app_config['app_info']['version'];
            break;

        case 'edit_user':
            $username_to_edit = $_GET['username'] ?? '';
            $pageTitle = $app_config['titles']['edit_user_page'] . ' - ' . htmlspecialchars($username_to_edit);
            $pageDescription = 'Edit details and permissions for user ' . htmlspecialchars($username_to_edit) . '.';
            $show_sidebar = true;
            $content_for_layout = include_path('resources/views/pages/auth/edit_user_view.php');
            $page_scripts[] = $baseURL . '/resources/frontend/js/modules/edit_user_actions.js?v=' . $app_config['app_info']['version'];
            break;

        case 'employee_db':
            $pageTitle = $app_config['titles']['employee_db_page'] . ' - Employee Information';
            $pageDescription = 'Browse and manage employee database records.';
            $content_for_layout = include_path('resources/views/pages/auth/employee_db_view.php');
            $page_scripts[] = $baseURL . '/resources/frontend/js/modules/employee_db_actions.js?v=' . $app_config['app_info']['version'];
            break;

        case 'about':
            $pageTitle = $app_config['titles']['about_page'];
            $pageDescription = "Learn more about $appName, its version, and development team.";
            $content_for_layout = include_path('resources/views/pages/submenu/about/about_content.php');
            $page_scripts[] = $baseURL . '/vendor/chartjs-plugin-datalabels/chartjs-plugin-datalabels.min.js?v=' . $app_config['app_info']['version'];
            $page_scripts[] = $baseURL . '/resources/frontend/js/admin/dashboard_charts.js?v=' . $app_config['app_info']['version'];
            break;

        case 'user_activity':
            $pageTitle = $app_config['titles']['user_activity_page'];
            $pageDescription = 'Review detailed logs of all user activities and system events.';
            $show_sidebar = true;
            $content_for_layout = include_path('resources/views/pages/audit/audit_log_viewer.php');
            $page_scripts[] = $baseURL . '/vendor/chartjs/chart.umd.min.js?v=' . $app_config['app_info']['version'];
            $page_scripts[] = $baseURL . '/resources/frontend/js/admin/dashboard_charts.js?v=' . $app_config['app_info']['version'];
            $page_scripts[] = $baseURL . '/resources/frontend/js/admin/dashboard_logic.js?v=' . $app_config['app_info']['version'];
            break;

        case 'documentation':
            $pageTitle = $app_config['titles']['documentation_page'];
            $pageDescription = "Access comprehensive documentation and guides for using $appName.";
            $content_for_layout = include_path('resources/views/pages/submenu/documentation/documentation_content.php');
            // Documentation CSS is now in pages.css (loaded globally)
            $page_scripts[] = $baseURL . '/vendor/mermaid/mermaid.min.js?v=' . $app_config['app_info']['version'];
            $page_scripts[] = $baseURL . '/resources/frontend/js/modules/documentation_scripts.js?v=' . $app_config['app_info']['version'];
            $page_scripts[] = $baseURL . '/resources/frontend/js/admin/dashboard_charts.js?v=' . $app_config['app_info']['version'];
            break;

        case 'documentation_guide':
            $pageTitle = "User Guide";
            $pageDescription = "A comprehensive professional manual detailing every feature, process, and configuration of $appName.";
            $content_for_layout = include_path('resources/views/pages/documentation/guide.php');
            // Documentation guide CSS is now in pages.css (loaded globally)
            $page_scripts[] = $baseURL . '/vendor/chartjs-plugin-datalabels/chartjs-plugin-datalabels.min.js?v=' . $app_config['app_info']['version'];
            break;

        case 'license':
            $pageTitle = $app_config['titles']['license_page'];
            $pageDescription = 'Review license status, expiry policy, renewal rules, and support contact details.';
            $show_sidebar = true;
            $content_for_layout = include_path('resources/views/pages/license/license_status_view.php');
            break;

        case 'vendor_console':
            $pageTitle = 'Vendor Console';
            $pageDescription = 'Generate, sign, and manage license certificates for client deployments.';
            $show_sidebar = true;
            $content_for_layout = include_path('resources/views/pages/license/vendor_view.php');
            $page_scripts[] = $baseURL . '/resources/frontend/js/modules/vendor_actions.js?v=' . $app_config['app_info']['version'];
            break;

        case 'license_doc':
            $pageTitle = 'Documentation';
            $pageDescription = 'System and license documentation.';
            $show_sidebar = true;
            $content_for_layout = include_path('resources/views/pages/license/doc_view.php');
            break;

        // Document viewer shortcuts (short URLs for client-facing guides)
        case 'ad-guide':
            $_GET['doc'] = 'ad_config_guide';
            $pageTitle = 'AD Configuration Guide';
            $pageDescription = 'Guide for OU, Group, and User Properties configuration.';
            $show_sidebar = true;
            $content_for_layout = include_path('resources/views/pages/license/doc_view.php');
            break;

        case 'ad-objects':
            $_GET['doc'] = 'ad_objects_config';
            $pageTitle = 'AD Objects Configuration';
            $pageDescription = 'Detailed OU and Group configuration reference.';
            $show_sidebar = true;
            $content_for_layout = include_path('resources/views/pages/license/doc_view.php');
            break;

        case 'ad-api':
            $_GET['doc'] = 'ad_api';
            $pageTitle = 'HRMS API Integration';
            $pageDescription = 'API integration guide for HRMS.';
            $show_sidebar = true;
            $content_for_layout = include_path('resources/views/pages/license/doc_view.php');
            break;

        case 'ad-features':
            $_GET['doc'] = 'ad_features';
            $pageTitle = 'Intelligent User Creation';
            $pageDescription = 'Feature specification for HRMS-driven AD user creation.';
            $show_sidebar = true;
            $content_for_layout = include_path('resources/views/pages/license/doc_view.php');
            break;

        case 'password_manager':
            $pageTitle = "Password Manager";
            $pageDescription = 'Securely store and manage system credentials.';
            $content_for_layout = include_path('resources/views/pages/password_manager/view.php');
            $page_scripts[] = $baseURL . '/vendor/chartjs-plugin-datalabels/chartjs-plugin-datalabels.min.js?v=' . $app_config['app_info']['version'];
            $page_scripts[] = $baseURL . '/resources/frontend/js/modules/password_manager_actions.js?v=' . $app_config['app_info']['version'];
            break;

        case 'system_config':
            $pageTitle = "System Configuration";
            $pageDescription = 'Configure core system settings, Active Directory integration, and secure storage.';
            $show_sidebar = true;
            $content_for_layout = include_path('resources/views/pages/tools/system_config_view.php');
            $page_styles[] = $baseURL . '/resources/frontend/css/system_config.css?v=' . $app_config['app_info']['version'];
            $page_scripts[] = $baseURL . '/resources/frontend/js/modules/system_config_actions.js?v=' . $app_config['app_info']['version'];
            break;

        case 'profile':
            $pageTitle = "My Profile";
            $pageDescription = 'View your account details and personalize your experience.';
            $content_for_layout = include_path('resources/views/pages/auth/profile_view.php');
            $page_scripts[] = $baseURL . '/resources/frontend/js/modules/change_password_actions.js?v=' . $app_config['app_info']['version'];
            break;

        case 'monitoring':
            $pageTitle = "Infrastructure Monitor";
            $pageDescription = 'Real-time server availability and latency tracking.';
            $show_sidebar = false;
            $content_for_layout = include_path('resources/views/pages/monitoring/view.php');
            $page_scripts[] = $baseURL . '/vendor/chartjs-plugin-datalabels/chartjs-plugin-datalabels.min.js?v=' . $app_config['app_info']['version'];
            $page_scripts[] = $baseURL . '/resources/frontend/js/modules/monitoring_actions.js?v=9.9.6_' . $app_config['app_info']['version'];
            break;

        case 'email_tools':
            $pageTitle = "Email Analysis Tools";
            $pageDescription = 'DNS record analysis, header inspection, SPF/DKIM/DMARC validation, blacklist checks.';
            $show_sidebar = true;
            $content_for_layout = include_path('resources/views/pages/email/view.php');
            $page_scripts[] = $baseURL . '/resources/frontend/js/modules/email_actions.js?v=' . $app_config['app_info']['version'];
            break;

        case 'exchange':
            $pageTitle = "Exchange Management";
            $pageDescription = 'Mailbox lifecycle, distribution groups, mail flow monitoring, and compliance controls.';
            $show_sidebar = true;
            $exConfig = function_exists('ldap_exchange_active_domain_config') ? ldap_exchange_active_domain_config() : ['enabled' => true];
            $exEnabled = !isset($exConfig['enabled']) || !empty($exConfig['enabled']);
            if (!$exEnabled) {
                $content_for_layout = '<div class="container mt-5"><div class="alert alert-warning">'
                    . 'Exchange is disabled in domain configuration. '
                    . '<a href="index.php?page=tools" class="alert-link">Go to Domain Configuration</a> to enable it.'
                    . '</div></div>';
            } else {
                $content_for_layout = include_path('resources/views/pages/exchange/view.php');
                $page_styles[] = $baseURL . '/resources/frontend/css/exchange.css?v=' . $app_config['app_info']['version'];
                $page_scripts[] = $baseURL . '/resources/frontend/js/modules/exchange_actions.js?v=' . $app_config['app_info']['version'];
            }
            break;

        default:
            $pageTitle = trim(($app_config['org_name'] ?? '') . ' - ' . $appName, ' -');
            $pageDescription = "Welcome to $appName. Your central hub for user management.";
            $content_for_layout = include_path('resources/views/components/main_content.php');
            $page_scripts[] = $baseURL . '/vendor/chartjs-plugin-datalabels/chartjs-plugin-datalabels.min.js?v=' . $app_config['app_info']['version'];
            $page_scripts[] = $baseURL . '/resources/frontend/js/admin/quick_action_chart_logic.js?v=' . $app_config['app_info']['version'];
            $page_scripts[] = $baseURL . '/resources/frontend/js/modules/ad_user_request_admin.js?v=' . $app_config['app_info']['version'];
            break;
    }

    return [
        'page' => $page,
        'pageTitle' => $pageTitle,
        'pageDescription' => $pageDescription,
        'show_sidebar' => $show_sidebar,
        'content_for_layout' => $content_for_layout,
        'page_styles' => $page_styles,
        'page_scripts' => $page_scripts,
    ];
}
