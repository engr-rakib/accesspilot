<?php
// config/menu_config.php

// Defines the structure of the offcanvas navigation menu.
// This is the single source of truth for both menu rendering and page access permissions.
return [
    [
        'name' => 'Home',
        'url' => '/index.php',
        'icon' => 'fa-user-shield',
        'permission' => 'page_ad_administration'
    ],
    [
        'name' => 'Dashboard',
        'url' => '/index.php?page=dashboard',
        'icon' => 'fa-chart-bar',
        'permission' => 'page_dashboard'
    ],

       [
        'name' => 'App Events',
        'url' => '/index.php?page=user_activity',
        'icon' => 'fa-users-cog',
        'permission' => 'page_application_events'
    ],

    // [
    //     'name' => 'Employee DB',
    //     'url' => '/EmployeeDB/emp_index.php',
    //     'icon' => 'fa-database',
    //     'permission' => 'page_employee_db'
    // ],
    // [
    //     'name' => 'Control Panel',
    //     'url' => '/coreAdmin/controlpanel.php',
    //     'icon' => 'fa-cogs',
    //     'permission' => 'page_control_panel'
    // ],
    // [
    //     'name' => 'Settings',
    //     'url' => '/coreAdmin/settings.php',
    //     'icon' => 'fa-sliders-h',
    //     'permission' => 'page_settings'
    // ],
    // [
    //     'name' => 'Profile Info',
    //     'url' => '/coreAdmin/profile.php',
    //     'icon' => 'fa-user',
    //     'permission' => 'page_profile_info'
    // ],
    [
        'name' => 'Identity & Access ',
        'url' => '/index.php?page=user_management',
        'icon' => 'fa-users-cog',
        'permission' => 'page_user_management'
    ],
    [
        'name' => 'Credential Vault ',
        'url' => '/index.php?page=password_manager',
        'icon' => 'fa-key',
        'permission' => 'page_password_manager'
    ],
    [
        'name' => 'Infrastructure Monitor',
        'url' => '/index.php?page=monitoring',
        'icon' => 'fa-satellite-dish',
        'permission' => 'page_monitoring|page_ad_administration'
    ],
    [
        'name' => 'Email Analysis ',
        'url' => '/index.php?page=email_tools',
        'icon' => 'fa-envelope-open-text',
        'permission' => 'page_email_tools'
    ],
    [
        'name' => 'Exchange ',
        'url' => '/index.php?page=exchange',
        'icon' => 'fa-exchange-alt',
        'permission' => 'page_exchange'
    ],

  
    [
        'name' => 'Configuration',
        'url' => '/index.php?page=system_config',
        'icon' => 'fa-cogs',
        'permission' => 'page_system_config|page_ad_administration'
    ],
    [
        'name' => 'User Guide',
        'url' => '/index.php?page=documentation_guide',
        'icon' => 'fa-book-open',
        'permission' => 'page_documentation_guide'
    ],

    [
        'name' => 'Documentation',
        'url' => '/index.php?page=documentation',
        'icon' => 'fa-book',
        'permission' => 'page_documentation'
    ],
    [
        'name' => 'License',
        'url' => '/index.php?page=license',
        'icon' => 'fa-certificate',
        'permission' => ''
    ],
    
     [
        'name' => 'Profile',
        'url' => '/index.php?page=profile',
        'icon' => 'fa-user-circle',
        'permission' => ''
    ],
    [
        'name' => 'About Us',
        'url' => '/index.php?page=about',
        'icon' => 'fa-info-circle',
        'permission' => 'page_about_us'
    ],
    // Sign-out link does not need a permission check.

    [
        'name' => 'Sign Out',
        'url' => '/logout.php',
        'icon' => 'fa-sign-out-alt',
        'permission' => ''
    ],
];
