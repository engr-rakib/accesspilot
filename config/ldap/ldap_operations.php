<?php
/**
 * Per-operation LDAP certification — true = LDAP may run when backend is ldap/auto.
 * Enable only after Windows IIS parity tests pass.
 */

return [
    'ldap_ready' => [
        'get_user_info' => true,
        'get_user_info_bulk' => true,
        'resolve_principal' => true,
        'get_group_members' => true,
        'get_ous' => true,
        'get_groups' => true,
        'enable_user' => true,
        'disable_user' => true,
        'unlock_user' => true,
        'reset_password' => true,
        'modify_user' => true,
        'set_group_members' => true,
        'create_user' => true,
        'create_directory_object' => true,
        'delete_directory_object' => true,
        'execute_action' => true,

        // Intelligence Hub
        'hrms_ad_report' => true,
        'ou_group_user_report' => false,
        'ad_health_check' => true,
        'user_report' => true,
    ],
];
