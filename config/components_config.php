<?php
// config/components_config.php

return [
    // Global Components (Assistant panel — sidebar quick actions that appear on every page)
    'global_components' => [
        'name' => 'Global Components',
        'cards' => [
            'card_assistant' => [
                'name' => 'Assistant Card',
                'icon' => 'fas fa-tasks',
                'buttons' => [
                    'action_get_info' => ['name' => 'Info', 'icon' => 'fas fa-search'],
                    'action_unlock' => ['name' => 'Unlock', 'icon' => 'fas fa-lock-open'],
                    'action_new_user_form' => ['name' => 'New User', 'icon' => 'fas fa-user-plus'],
                    'action_u_and_reset' => ['name' => 'U & Reset', 'icon' => 'fas fa-sync-alt'],
                    'action_disable' => ['name' => 'Disable', 'icon' => 'fas fa-user-slash'],
                    'action_enable' => ['name' => 'Enable', 'icon' => 'fas fa-user-check'],
                    'action_manual_create_form' => ['name' => 'Manual', 'icon' => 'fas fa-user-plus'],
                    'action_modify_user_form' => ['name' => 'Modify', 'icon' => 'fas fa-user-edit'],
                    'action_directory_builder' => ['name' => 'Directory', 'icon' => 'fas fa-sitemap'],
                    'action_dashboard' => ['name' => 'Open Dashboard', 'icon' => 'fas fa-tachometer-alt']
                ],
                'cards' => [
                    'card_manual_create_form' => [
                        'name' => 'Manual User Creation Form', 'icon' => 'fas fa-file-alt',
                        'buttons' => [
                            'manual_create_user' => ['name' => 'Manual Create User API Access', 'icon' => 'fas fa-user-cog'],
                            'action_submit_manual_create' => ['name' => 'Create User Manually Button', 'icon' => 'fas fa-user-check'],
                            'action_cancel_manual_create' => ['name' => 'Cancel Button', 'icon' => 'fas fa-times']
                        ],
                        'cards' => [
                            'card_directory_builder_form' => [
                                'name' => 'OU and Groups Manager Form', 'icon' => 'fas fa-sitemap',
                                'buttons' => [
                                    'action_directory_builder_create' => ['name' => 'Create Directory Object', 'icon' => 'fas fa-plus-circle'],
                                    'action_directory_builder_manage' => ['name' => 'Manage Group Membership', 'icon' => 'fas fa-users-cog'],
                                    'action_directory_builder_delete' => ['name' => 'Delete Directory Object', 'icon' => 'fas fa-trash-alt'],
                                    'action_directory_builder_search_delete_target' => ['name' => 'Search Delete Target', 'icon' => 'fas fa-search'],
                                    'action_directory_builder_queue_member' => ['name' => 'Queue Group Member', 'icon' => 'fas fa-user-plus']
                                ]
                            ]
                        ]
                    ],
                    'card_modify_user_form' => [
                        'name' => 'Modify User Form', 'icon' => 'fas fa-file-alt',
                        'buttons' => [
                            'action_update_user' => ['name' => 'Update User Button', 'icon' => 'fas fa-user-edit'],
                            'action_cancel_modify' => ['name' => 'Cancel Button', 'icon' => 'fas fa-times']
                        ]
                    ],
                    'card_get_report' => [
                        'name' => 'Intelligence Hub', 'icon' => 'fas fa-chart-bar',
                        'buttons' => [
                            'action_get_ad_hrms_status' => ['name' => 'HRMS AD', 'icon' => 'fas fa-search'],
                            'action_export_hrms_ad_user_id' => ['name' => 'HRMS AD (User ID Export)', 'icon' => 'fas fa-file-export'],
                            'action_export_ad_users' => ['name' => 'Users', 'icon' => 'fas fa-file-export'],
                            'action_user_report' => ['name' => 'Reports', 'icon' => 'fas fa-file-alt'],
                            'action_security_events' => ['name' => 'Events', 'icon' => 'fas fa-shield-alt'],
                            'action_ad_health_check' => ['name' => 'Health', 'icon' => 'fas fa-heartbeat']
                        ]
                    ],
                    'card_notification_center' => [
                        'name' => 'Notification Center', 'icon' => 'fas fa-bell',
                        'buttons' => [
                            'notif_category_announcement' => ['name' => 'Receive Announcement Notifications', 'icon' => 'fas fa-bullhorn'],
                            'notif_category_requests' => ['name' => 'Receive Request Notifications', 'icon' => 'fas fa-user-clock'],
                            'notif_category_activity' => ['name' => 'Receive Activity Notifications', 'icon' => 'fas fa-stream'],
                            'notif_category_ad_actions' => ['name' => 'Receive AD Action Notifications', 'icon' => 'fas fa-user-cog'],
                            'notif_category_reports' => ['name' => 'Receive Report Notifications', 'icon' => 'fas fa-file-alt'],
                            'notif_category_security' => ['name' => 'Receive Security Notifications', 'icon' => 'fas fa-shield-alt'],
                            'action_notification_preferences' => ['name' => 'Update Notification Preferences', 'icon' => 'fas fa-sliders-h'],
                            'action_notification_send' => ['name' => 'Send Notifications', 'icon' => 'fas fa-paper-plane'],
                            'action_notification_manage' => ['name' => 'Manage Notifications', 'icon' => 'fas fa-trash-alt']
                        ]
                    ]
                ]
            ]
        ]
    ],

    // Page-specific Components
    'page_ad_administration' => [
        'name' => 'AD Administration',
        'icon' => 'fa-user-shield',
        'permissions' => [
            'execute_ad_actions' => ['name' => 'Execute All AD Actions', 'icon' => 'fas fa-cogs'],
            'view_user_info' => ['name' => 'View User Information', 'icon' => 'fas fa-info-circle'],
            'modify_ad_user' => ['name' => 'Modify AD User', 'icon' => 'fas fa-user-edit'],
            'reset_user_password' => ['name' => 'Reset AD User Password', 'icon' => 'fas fa-key'],
            'view_ad_groups' => ['name' => 'View AD Groups', 'icon' => 'fas fa-users'],
            'view_ad_ous' => ['name' => 'View AD Organizational Units', 'icon' => 'fas fa-sitemap'],
            'view_hrms_status' => ['name' => 'View HRMS Status', 'icon' => 'fas fa-user-check']
        ],
        'cards' => [
            'card_server_info' => ['name' => 'Server Information Card', 'icon' => 'fas fa-server'],
            'card_employee_info' => ['name' => 'Employee Information Card', 'icon' => 'fas fa-user'],
            'card_recent_activity' => ['name' => 'Recent Activity Card', 'icon' => 'fas fa-history'],
            'card_ad_user_request_admin' => [
                'name' => 'AD User Request Admin Card', 'icon' => 'fas fa-user-clock',
                'buttons' => [
                    'action_ad_request_approve' => ['name' => 'Approve AD User Requests', 'icon' => 'fas fa-check-circle'],
                    'action_ad_request_deny' => ['name' => 'Deny AD User Requests', 'icon' => 'fas fa-times-circle']
                ]
            ]
        ]
    ],
    'page_dashboard' => [
        'name' => 'Dashboard',
        'icon' => 'fa-chart-bar',
        'cards' => [
            'card_dashboard_today_log' => ['name' => 'Today\'s Log Card', 'icon' => 'fas fa-chart-pie'],
            'card_dashboard_weekly_logs' => ['name' => 'Weekly Logs Card', 'icon' => 'fas fa-chart-line'],
            'card_dashboard_monthly_activity' => ['name' => 'Monthly Activity Card', 'icon' => 'fas fa-chart-bar'],
            'card_dashboard_action_status' => ['name' => 'Action Status Breakdown Card', 'icon' => 'fas fa-tasks'],
            'card_dashboard_status_breakdown' => ['name' => 'Status Breakdown Card', 'icon' => 'fas fa-info-circle'],
            'card_dashboard_top_users' => ['name' => 'Top Users Card', 'icon' => 'fas fa-users'],
            'card_dashboard_filter_bar' => ['name' => 'Filter Bar', 'icon' => 'fas fa-filter'],
            'card_dashboard_log_table' => ['name' => 'Detailed Log Table', 'icon' => 'fas fa-table'],
            'card_dashboard_exchange_monitor' => ['name' => 'Exchange Monitor Card', 'icon' => 'fas fa-exchange-alt']
        ]
    ],
    'page_user_management' => [
        'name' => 'User Management',
        'icon' => 'fa-users-cog',
        'permissions' => [
            'user_create' => ['name' => 'Access Create User Page', 'icon' => 'fas fa-user-plus']
        ],
        'cards' => [
            'card_pending_requests' => [
                'name' => 'Pending Registration Requests Card', 'icon' => 'fas fa-user-clock',
                'buttons' => [
                    'action_usermgmt_create' => ['name' => 'Create New Users', 'icon' => 'fas fa-plus'],
                    'action_usermgmt_approve_deny' => ['name' => 'Approve/Deny Registration Requests', 'icon' => 'fas fa-check-circle'],
                    'user_approve_request' => ['name' => 'Approve User Request Permission', 'icon' => 'fas fa-user-check']
                ]
            ],
            'card_existing_users' => [
                'name' => 'Existing Users Card', 'icon' => 'fas fa-users',
                'buttons' => [
                    'action_usermgmt_edit' => ['name' => 'Edit Existing Users', 'icon' => 'fas fa-edit'],
                    'action_usermgmt_reset' => ['name' => 'Reset User Passwords', 'icon' => 'fas fa-key'],
                    'action_usermgmt_delete' => ['name' => 'Delete Users', 'icon' => 'fas fa-trash-alt'],
                    'user_edit' => ['name' => 'Access Edit User Page', 'icon' => 'fas fa-user-edit'],
                    'user_delete' => ['name' => 'Delete User Permission', 'icon' => 'fas fa-user-minus'],
                    'user_password_reset' => ['name' => 'Reset User Password Permission', 'icon' => 'fas fa-key'],
                    'terminate_user_session' => ['name' => 'Terminate User Session', 'icon' => 'fas fa-stop-circle']
                ]
            ],
            'card_user_create_form' => [
                'name' => 'Create User Form', 'icon' => 'fas fa-user-plus',
                'buttons' => [
                    'user_create' => ['name' => 'Access Create User Form', 'icon' => 'fas fa-user-plus'],
                    'manual_create_user' => ['name' => 'Submit Create User Form', 'icon' => 'fas fa-save']
                ]
            ],
            'card_user_edit_form' => [
                'name' => 'Edit User Form', 'icon' => 'fas fa-user-edit',
                'buttons' => [
                    'user_edit' => ['name' => 'Access Edit User Form', 'icon' => 'fas fa-user-edit'],
                    'user_password_reset' => ['name' => 'Reset Password from Edit Form', 'icon' => 'fas fa-key']
                ]
            ]
        ]
    ],
    'page_password_manager' => [
        'name' => 'Password Manager',
        'icon' => 'fas fa-key',
        'cards' => [ // Use 'cards' to create the hierarchical view
            'card_my_passwords' => [ // My Passwords section
                'name' => 'My Passwords',
                'icon' => 'fas fa-table',
                'buttons' => [
                    'card_password_manager' => ['name' => 'Password Management Table', 'icon' => 'fas fa-table'],
                    'action_password_create' => ['name' => 'Create New Password Entry', 'icon' => 'fas fa-plus'],
                    'action_password_edit'   => ['name' => 'Edit a Password Entry', 'icon' => 'fas fa-edit'],
                    'action_password_delete' => ['name' => 'Delete a Password Entry', 'icon' => 'fas fa-trash-alt'],
    'action_password_share'  => ['name' => 'Share Password to the global table', 'icon' => 'fas fa-share-alt'],
                    'action_password_view_all' => ['name' => 'View All Users\' Passwords (Admin)', 'icon' => 'fas fa-eye']
                ]
            ],
            'card_global_passwords' => [ // Global Passwords section, nested
                'name' => 'Global Passwords',
                'icon' => 'fas fa-globe-americas',
                'buttons' => [
                    'page_global_passwords' => ['name' => 'Global Passwords Page Access', 'icon' => 'fas fa-globe-americas'],
                    'action_global_password_edit' => ['name' => 'Edit Global Password Entry', 'icon' => 'fas fa-edit'],
                    'action_global_password_delete' => ['name' => 'Delete Global Password Entry', 'icon' => 'fas fa-trash-alt']
                ]
            ]
        ]
    ],
    'page_role_management' => [
        'name' => 'Role Management',
        'icon' => 'fa-user-shield',
        'cards' => [
            'card_roles_list' => [
                'name' => 'Roles List Card', 'icon' => 'fas fa-user-shield',
                'buttons' => [
                    'action_role_create' => ['name' => 'Create New Roles', 'icon' => 'fas fa-plus'],
                    'action_role_edit'   => ['name' => 'Edit Existing Roles', 'icon' => 'fas fa-edit'],
                    'action_role_delete' => ['name' => 'Delete Roles', 'icon' => 'fas fa-trash-alt'],
                    'action_role_add_member' => ['name' => 'Add Users to Roles', 'icon' => 'fas fa-user-plus'],
                    'action_role_remove_member' => ['name' => 'Remove Users from Roles', 'icon' => 'fas fa-user-minus']
                ]
            ],
            'card_role_form' => [
                'name' => 'Role Create/Edit Form', 'icon' => 'fas fa-user-lock',
                'buttons' => [
                    'action_role_create' => ['name' => 'Create Role Form Access', 'icon' => 'fas fa-plus'],
                    'action_role_edit' => ['name' => 'Edit Role Form Access', 'icon' => 'fas fa-edit'],
                    'action_role_add_member' => ['name' => 'Add Role Members from Form', 'icon' => 'fas fa-user-plus'],
                    'action_role_remove_member' => ['name' => 'Remove Role Members from Form', 'icon' => 'fas fa-user-minus']
                ]
            ]
        ]
    ],
    'page_application_events' => [
        'name' => 'Application Events',
        'icon' => 'fa-users-cog',
        'cards' => [
            'card_event_filters' => ['name' => 'Header & Date Filters', 'icon' => 'fas fa-filter'],
            'card_event_overview' => ['name' => 'User Activity Tracking Chart', 'icon' => 'fas fa-chart-area'],
            'card_user_activity_tracking' => ['name' => 'Secondary User Activity Tracking Chart', 'icon' => 'fas fa-chart-pie'],
            'card_event_hourly_activity' => ['name' => 'Hourly Activity Chart', 'icon' => 'fas fa-chart-line'],
            'card_event_top_actions' => ['name' => 'Top Actions Chart', 'icon' => 'fas fa-chart-bar'],
            'card_event_active_sessions' => ['name' => 'Active Sessions Card', 'icon' => 'fas fa-clock'],
            'card_event_log_table' => ['name' => 'Activity Log Table', 'icon' => 'fas fa-table']
        ]
    ],
    'page_monitoring' => [
        'name' => 'Infrastructure Monitor',
        'icon' => 'fa-satellite-dish',
        'permissions' => [
            'view_monitoring_system' => ['name' => 'View System Monitor Tab', 'icon' => 'fas fa-server'],
            'view_monitoring_hub' => ['name' => 'View Infrastructure Hub Tab', 'icon' => 'fas fa-satellite-dish'],
            'view_monitoring_network' => ['name' => 'View Network Operations Tab', 'icon' => 'fas fa-network-wired']
        ],
        'cards' => [
            'card_monitoring_overview' => [
                'name' => 'Infrastructure Hub Overview', 'icon' => 'fas fa-satellite-dish',
                'buttons' => [
                    'action_monitoring_run_sweep' => ['name' => 'Run Sweep', 'icon' => 'fas fa-sync-alt'],
                    'action_monitoring_add_node' => ['name' => 'Open Add Node Modal', 'icon' => 'fas fa-plus-circle'],
                    'action_monitoring_add_node_submit' => ['name' => 'Initiate Heartbeat Monitor', 'icon' => 'fas fa-heartbeat']
                ]
            ],
            'card_monitoring_timeline' => [
                'name' => 'Multi-Node RTT Timeline', 'icon' => 'fas fa-project-diagram',
                'buttons' => [
                    'action_monitoring_rtt_pause' => ['name' => 'Pause/Resume RTT Timeline', 'icon' => 'fas fa-pause-circle'],
                    'action_monitoring_rtt_export' => ['name' => 'Export RTT Timeline', 'icon' => 'fas fa-file-export']
                ]
            ],
            'card_monitoring_grid' => ['name' => 'Monitoring Grid', 'icon' => 'fas fa-table'],
            'card_monitoring_event_logs' => [
                'name' => 'Infrastructure Event Logs', 'icon' => 'fas fa-history',
                'buttons' => [
                    'action_monitoring_load_logs' => ['name' => 'Load Monitoring Logs', 'icon' => 'fas fa-folder-open'],
                    'action_monitoring_export_logs' => ['name' => 'Export Monitoring Logs', 'icon' => 'fas fa-file-export']
                ]
            ],
            'card_monitoring_focus_area' => ['name' => 'Deep Analysis Focus Area', 'icon' => 'fas fa-chart-line'],
            'card_system_monitor' => [
                'name' => 'System Monitor (Container & Infrastructure)', 'icon' => 'fas fa-server',
                'cards' => [
                    'card_container_monitor' => [
                        'name' => 'Container Card', 'icon' => 'fas fa-box',
                        'buttons' => [
                        ]
                    ],
                    'card_system_infra_monitor' => [
                        'name' => 'System Infrastructure', 'icon' => 'fas fa-microchip',
                        'buttons' => [
                            'action_system_trend_export' => ['name' => 'Export System Trends', 'icon' => 'fas fa-file-export']
                        ]
                    ],
                    'card_advanced_analytics' => [
                        'name' => 'Advanced Analytics Charts', 'icon' => 'fas fa-chart-pie',
                        'buttons' => [
                        ]
                    ]
                ]
            ],
            'card_monitoring_network_profiler' => [
                'name' => 'Network Profiler', 'icon' => 'fas fa-network-wired',
                'buttons' => [
                    'action_monitoring_calculate_network' => ['name' => 'Analyse Target CIDR', 'icon' => 'fas fa-calculator'],
                    'action_monitoring_scan_block' => ['name' => 'Scan Network Block', 'icon' => 'fas fa-search-location'],
                    'action_monitoring_cancel_scan' => ['name' => 'Cancel Network Scan', 'icon' => 'fas fa-stop-circle']
                ]
            ],
            'card_monitoring_discovery_stream' => [
                'name' => 'Discovery Stream', 'icon' => 'fas fa-list-check',
                'buttons' => [
                    'action_monitoring_export_scan' => ['name' => 'Export Scan CSV', 'icon' => 'fas fa-file-export']
                ]
            ],
            'card_monitoring_ping' => [
                'name' => 'Diagnostic Ping', 'icon' => 'fas fa-terminal',
                'buttons' => [
                    'action_monitoring_manual_ping' => ['name' => 'Run Manual Ping', 'icon' => 'fas fa-signal'],
                    'action_monitoring_stop_ping' => ['name' => 'Stop Ping', 'icon' => 'fas fa-stop-circle']
                ]
            ],
            'card_monitoring_dns_lookup' => [
                'name' => 'DNS Lookup', 'icon' => 'fas fa-globe',
                'buttons' => [
                    'action_monitoring_dns_lookup' => ['name' => 'Run DNS Lookup', 'icon' => 'fas fa-search']
                ]
            ],
            'card_monitoring_port_check' => [
                'name' => 'Port Check', 'icon' => 'fas fa-plug',
                'buttons' => [
                    'action_monitoring_port_check' => ['name' => 'Run Port Check', 'icon' => 'fas fa-door-open']
                ]
            ],
            'card_monitoring_traceroute' => [
                'name' => 'Traceroute', 'icon' => 'fas fa-route',
                'buttons' => [
                    'action_monitoring_traceroute' => ['name' => 'Run Traceroute', 'icon' => 'fas fa-route']
                ]
            ],
            'card_monitoring_mtr' => [
                'name' => 'MTR Report', 'icon' => 'fas fa-chart-line',
                'buttons' => [
                    'action_monitoring_mtr_report' => ['name' => 'Generate MTR Report', 'icon' => 'fas fa-file-alt']
                ]
            ],
            'card_monitoring_whois' => [
                'name' => 'WHOIS Lookup', 'icon' => 'fas fa-globe',
                'buttons' => [
                    'action_monitoring_whois' => ['name' => 'Run WHOIS Lookup', 'icon' => 'fas fa-search']
                ]
            ],
            'card_monitoring_multi_ping' => [
                'name' => 'Multi-Ping Test', 'icon' => 'fas fa-arrows-alt-h',
                'buttons' => [
                    'action_monitoring_multi_ping' => ['name' => 'Run Multi-Ping', 'icon' => 'fas fa-play']
                ]
            ],
            'card_monitoring_node_management' => [
                'name' => 'Node Management', 'icon' => 'fas fa-server',
                'buttons' => [
                    'action_monitoring_node_summary' => ['name' => 'View Node Summary', 'icon' => 'fas fa-chart-bar'],
                    'action_monitoring_delete_node' => ['name' => 'Delete Node', 'icon' => 'fas fa-trash-alt']
                ]
            ]
        ]
    ],
    'page_system_config' => [
        'name' => 'System Configuration',
        'icon' => 'fa-cogs',
        'cards' => [
            'card_system_configuration' => [
                'name' => 'Deployment & System Configuration', 'icon' => 'fas fa-cogs',
                'buttons' => [
                    'action_system_update_domain' => ['name' => 'Update Domain Parameters', 'icon' => 'fas fa-globe'],
                    'action_system_update_credentials' => ['name' => 'Update Admin Credentials', 'icon' => 'fas fa-user-shield'],
                    'action_system_update_storage' => ['name' => 'Update Storage Paths', 'icon' => 'fas fa-map-marked-alt'],
                    'action_system_update_passwords' => ['name' => 'Update Default Passwords', 'icon' => 'fas fa-key'],
                    'action_system_confirm_update' => ['name' => 'Confirm Configuration Change', 'icon' => 'fas fa-check'],
                    'action_system_ldap_test' => ['name' => 'Test LDAP Connection', 'icon' => 'fas fa-plug'],
                    'action_system_ldap_test_user' => ['name' => 'Test LDAP User Lookup', 'icon' => 'fas fa-user-check']
                ]
            ],
            'card_system_domain_config' => [
                'name' => 'Domain Configuration', 'icon' => 'fas fa-globe',
                'buttons' => [
                    'action_system_domain_add' => ['name' => 'Add Domain', 'icon' => 'fas fa-plus-circle'],
                    'action_system_domain_switch' => ['name' => 'Switch Active Domain', 'icon' => 'fas fa-exchange-alt'],
                    'action_system_domain_edit' => ['name' => 'Edit Domain', 'icon' => 'fas fa-edit'],
                    'action_system_domain_delete' => ['name' => 'Delete Domain', 'icon' => 'fas fa-trash-alt'],
                    'action_system_domain_test' => ['name' => 'Test Domain Connection', 'icon' => 'fas fa-plug']
                ]
            ],
            'card_system_application_config' => [
                'name' => 'Application Configuration', 'icon' => 'fas fa-cube',
                'buttons' => [
                    'action_system_save_org' => ['name' => 'Save Organization Config', 'icon' => 'fas fa-save'],
                    'action_system_test_integration' => ['name' => 'Test HRMS API Integration', 'icon' => 'fas fa-plug'],
                    'action_system_save_integrations' => ['name' => 'Save HRMS Integrations', 'icon' => 'fas fa-save'],
                    'action_system_save_storage' => ['name' => 'Save Storage Paths', 'icon' => 'fas fa-save'],
                    'action_system_save_passwords' => ['name' => 'Save Default Passwords', 'icon' => 'fas fa-key'],
                    'action_system_refresh_diagnostics' => ['name' => 'Refresh Diagnostics', 'icon' => 'fas fa-sync-alt']
                ]
            ]
        ]
    ],
    
    'page_about_us' => [
        'name' => 'About Us',
        'icon' => 'fa-info-circle',
        'cards' => [
            'card_about_info' => ['name' => 'About Information', 'icon' => 'fas fa-info-circle'],
            'card_about_version' => ['name' => 'Current Version & Update', 'icon' => 'fas fa-tag'],
            'card_about_team' => ['name' => 'Meet the Team', 'icon' => 'fas fa-users'],
            'card_about_features' => ['name' => 'Key Features & Enhancements', 'icon' => 'fas fa-star'],
            'card_about_docs' => ['name' => 'Documentation & Upgrades', 'icon' => 'fas fa-book'],
            'card_about_mission' => ['name' => 'Our Mission', 'icon' => 'fas fa-bullseye']
        ]
    ],
    'page_documentation' => [
        'name' => 'Documentation',
        'icon' => 'fa-book',
        'cards' => [
            'card_doc_content' => ['name' => 'Documentation Content', 'icon' => 'fas fa-book']
        ]
    ],
    'page_documentation_guide' => [
        'name' => 'Documentation Guide',
        'icon' => 'fa-book-open',
        'cards' => [
            'card_doc_guide_content' => ['name' => 'Guide Content', 'icon' => 'fas fa-book-open']
        ]
    ],
    'page_license' => [
        'name' => 'License Center',
        'icon' => 'fa-certificate',
        'cards' => [
            'card_license_status' => ['name' => 'License Status Banner', 'icon' => 'fas fa-certificate'],
            'card_license_certificate' => ['name' => 'Certificate of Authenticity', 'icon' => 'fas fa-award'],
            'card_license_policy' => ['name' => 'Usage Terms', 'icon' => 'fas fa-scroll'],
            'card_license_sync' => [
                'name' => 'Sync Material Panel', 'icon' => 'fas fa-sync-alt',
                'buttons' => [
                    'action_license_sync_renewal' => ['name' => 'Synchronize Renewal Certificate', 'icon' => 'fas fa-upload']
                ]
            ],
            'card_license_lifecycle' => ['name' => 'Deployment Lifecycle & Behavior', 'icon' => 'fas fa-info-circle'],
            'card_license_support' => ['name' => 'Vendor Support', 'icon' => 'fas fa-headset'],
            'card_license_verification' => ['name' => 'Verification Info', 'icon' => 'fas fa-shield-alt']
        ]
    ],
    'page_change_password' => [
        'name' => 'Change Password',
        'icon' => 'fa-user-lock',
        'cards' => [
            'card_change_password_form' => ['name' => 'Change Password Form', 'icon' => 'fas fa-key']
        ]
    ],
    'page_profile' => [
        'name' => 'Profile Hub',
        'icon' => 'fa-user-circle',
        'cards' => [
            'card_profile_identity' => [
                'name' => 'Profile Identity Card', 'icon' => 'fas fa-id-badge',
                'buttons' => [
                    'action_profile_update_avatar' => ['name' => 'Update Avatar', 'icon' => 'fas fa-camera'],
                    'action_profile_update_details' => ['name' => 'Save Profile Details', 'icon' => 'fas fa-save']
                ]
            ],
            'card_profile_quick_stats' => ['name' => 'Quick Stats Card', 'icon' => 'fas fa-chart-simple'],
            'card_profile_theme' => [
                'name' => 'Theme Personalization Card', 'icon' => 'fas fa-palette',
                'buttons' => [
                    'action_profile_change_theme' => ['name' => 'Change Theme Preference', 'icon' => 'fas fa-paint-brush']
                ]
            ],
            'card_profile_notifications' => [
                'name' => 'Notification Preferences Card', 'icon' => 'fas fa-bell',
                'buttons' => [
                    'action_profile_test_sound' => ['name' => 'Test Sound Alert', 'icon' => 'fas fa-volume-up'],
                    'action_profile_save_notifications' => ['name' => 'Save Notification Settings', 'icon' => 'fas fa-save']
                ]
            ],
            'card_profile_security' => [
                'name' => 'Security & Password Card', 'icon' => 'fas fa-shield-alt',
                'buttons' => [
                    'action_profile_change_password' => ['name' => 'Update Password', 'icon' => 'fas fa-key']
                ]
            ],
            'card_profile_recent_activity' => ['name' => 'Recent Activity Card', 'icon' => 'fas fa-clock-rotate-left']
        ]
    ],
    'page_employee_db' => [
        'name' => 'Employee DB',
        'icon' => 'fa-database',
        'cards' => [
            'card_employee_db_table' => [
                'name' => 'Employee Table', 'icon' => 'fas fa-database',
                'buttons' => [
                    'action_employee_search' => ['name' => 'Search Employee Button', 'icon' => 'fas fa-search'],
                    'action_add_employee' => ['name' => 'Add Employee Button', 'icon' => 'fas fa-plus'],
                    'action_edit_employee' => ['name' => 'Edit Employee Button', 'icon' => 'fas fa-edit'],
                    'action_delete_employee' => ['name' => 'Delete Employee Button', 'icon' => 'fas fa-trash-alt'],
                    'action_save_employee' => ['name' => 'Save Employee Button', 'icon' => 'fas fa-save']
                ]
            ]
        ]
    ],
    'page_email_tools' => [
        'name' => 'Email Analysis',
        'icon' => 'fa-envelope-open-text',
        'cards' => [
            'card_email_dns' => [
                'name' => 'DNS Record Lookup', 'icon' => 'fas fa-globe',
                'buttons' => [
                    'action_email_dns_lookup' => ['name' => 'Run DNS Lookup', 'icon' => 'fas fa-search']
                ]
            ],
            'card_email_header' => [
                'name' => 'Header Analysis', 'icon' => 'fas fa-heading',
                'buttons' => [
                    'action_email_header_parse' => ['name' => 'Parse Email Headers', 'icon' => 'fas fa-heading']
                ]
            ],
            'card_email_blacklist' => [
                'name' => 'Blacklist Check', 'icon' => 'fas fa-ban',
                'buttons' => [
                    'action_email_blacklist_check' => ['name' => 'Run Blacklist Check', 'icon' => 'fas fa-ban']
                ]
            ],
            'card_email_validate' => [
                'name' => 'Email Validation', 'icon' => 'fas fa-check-circle',
                'buttons' => [
                    'action_email_validate' => ['name' => 'Validate Email Address', 'icon' => 'fas fa-check-circle']
                ]
            ],
            'card_email_smtp_test' => [
                'name' => 'SMTP Server Test', 'icon' => 'fas fa-envelope-open-text',
                'buttons' => [
                    'action_email_smtp_test' => ['name' => 'Run SMTP Test', 'icon' => 'fas fa-plug']
                ]
            ],
            'card_email_port_scan' => [
                'name' => 'Mail Port Scanner', 'icon' => 'fas fa-plug',
                'buttons' => [
                    'action_email_port_scan' => ['name' => 'Scan Mail Ports', 'icon' => 'fas fa-search']
                ]
            ],
            'card_email_bimi' => [
                'name' => 'BIMI Record Check', 'icon' => 'fas fa-image',
                'buttons' => [
                    'action_email_bimi_check' => ['name' => 'Run BIMI Check', 'icon' => 'fas fa-search']
                ]
            ],
            'card_email_mta_sts' => [
                'name' => 'MTA-STS Check', 'icon' => 'fas fa-shield-alt',
                'buttons' => [
                    'action_email_mta_sts_check' => ['name' => 'Run MTA-STS Check', 'icon' => 'fas fa-search']
                ]
            ]
        ]
    ],
    'page_exchange' => [
        'name' => 'Exchange Management',
        'icon' => 'fa-exchange-alt',
        'permissions' => [
            'action_exchange_mailbox_view' => ['name' => 'View Mailbox Information', 'icon' => 'fas fa-inbox'],
            'action_exchange_mailbox_enable' => ['name' => 'Enable Mailbox', 'icon' => 'fas fa-check-circle'],
            'action_exchange_mailbox_disable' => ['name' => 'Disable Mailbox', 'icon' => 'fas fa-times-circle'],
            'action_exchange_mailbox_quota' => ['name' => 'Set Mailbox Quota', 'icon' => 'fas fa-database'],
            'action_exchange_mailbox_forward' => ['name' => 'Configure Forwarding', 'icon' => 'fas fa-forward'],
            'action_exchange_mailbox_address' => ['name' => 'Manage Email Addresses', 'icon' => 'fas fa-at'],
            'action_exchange_group_view' => ['name' => 'View Distribution Groups', 'icon' => 'fas fa-users'],
            'action_exchange_group_create' => ['name' => 'Create Distribution Groups', 'icon' => 'fas fa-plus-circle'],
            'action_exchange_group_modify' => ['name' => 'Modify Group Members', 'icon' => 'fas fa-user-edit'],
            'action_exchange_group_delete' => ['name' => 'Delete Distribution Groups', 'icon' => 'fas fa-trash'],
            'action_exchange_monitoring' => ['name' => 'Access Exchange Monitoring', 'icon' => 'fas fa-chart-line'],
            'action_exchange_settings' => ['name' => 'Modify Exchange Settings', 'icon' => 'fas fa-cogs']
        ],
        'cards' => [
            'card_exchange_mailbox' => ['name' => 'Mailbox Search & Info', 'icon' => 'fas fa-inbox'],
            'card_exchange_groups' => ['name' => 'Distribution Groups', 'icon' => 'fas fa-users'],
            'card_exchange_monitoring' => ['name' => 'Quota & Queue Monitoring', 'icon' => 'fas fa-chart-line'],
            'card_exchange_settings' => ['name' => 'Exchange Connection Settings', 'icon' => 'fas fa-cogs']
        ]
    ]
];
