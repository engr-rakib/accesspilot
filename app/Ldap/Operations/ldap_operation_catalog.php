<?php
/**
 * Maps router operation names to API endpoints, PowerShell script keys, and LDAP handlers.
 * Update ldap_ready in config/ldap_operations.php after parity tests.
 */

if (!function_exists('ldap_operation_catalog')) {
    function ldap_operation_catalog(): array
    {
        return [
            'get_user_info' => [
                'api_endpoint' => 'get_user_info',
                'ps_script_key' => 'getADUserInfo',
                'ldap_handler' => 'ldap_user_repository_find',
                'phase' => 1,
            ],
            'get_user_info_bulk' => [
                'api_endpoint' => 'execute_action',
                'ps_script_key' => 'getUserInfo',
                'ldap_handler' => 'ldap_user_repository_find_many',
                'phase' => 1,
            ],
            'resolve_principal' => [
                'api_endpoint' => 'resolve_directory_principal',
                'ps_script_key' => 'resolveADPrincipal',
                'ldap_handler' => 'ldap_resolve_principal',
                'phase' => 1,
            ],
            'get_group_members' => [
                'api_endpoint' => 'get_group_members',
                'ps_script_key' => 'getADGroupMembers',
                'ldap_handler' => 'ldap_group_repository_members',
                'phase' => 1,
            ],
            'get_ous' => [
                'api_endpoint' => 'get_ous',
                'ps_script_key' => 'getOU_Dropdwon',
                'ldap_handler' => 'ldap_directory_list_ous',
                'phase' => 1,
            ],
            'get_groups' => [
                'api_endpoint' => 'get_groups',
                'ps_script_key' => 'getGroup_dropdown',
                'ldap_handler' => 'ldap_group_repository_list',
                'phase' => 1,
            ],
            'enable_user' => [
                'ps_script_key' => 'enableUser',
                'ldap_handler' => 'ldap_user_writer_set_enabled',
                'phase' => 2,
            ],
            'disable_user' => [
                'ps_script_key' => 'disableUser',
                'ldap_handler' => 'ldap_user_writer_set_enabled',
                'phase' => 2,
            ],
            'unlock_user' => [
                'ps_script_key' => 'unlockUser',
                'ldap_handler' => 'ldap_user_writer_unlock',
                'phase' => 2,
            ],
            'reset_password' => [
                'api_endpoint' => 'reset_password_api',
                'ps_script_key' => 'resetUnlock',
                'ldap_handler' => 'ldap_user_writer_reset_password',
                'phase' => 2,
            ],
            'modify_user' => [
                'api_endpoint' => 'modify_ad_user',
                'ps_script_key' => 'modifyuser',
                'ldap_handler' => 'ldap_user_writer_update',
                'phase' => 3,
            ],
            'set_group_members' => [
                'api_endpoint' => 'update_group_members',
                'ps_script_key' => 'setADGroupMembers',
                'ldap_handler' => 'ldap_group_writer_sync_members',
                'phase' => 3,
            ],
            'create_user' => [
                'api_endpoint' => 'manual_create_user',
                'ps_script_key' => 'manual_create_user',
                'ldap_handler' => 'ldap_user_writer_create',
                'phase' => 3,
            ],
            'create_directory_object' => [
                'api_endpoint' => 'create_directory_object',
                'ps_script_key' => 'createADDirectoryObject',
                'ldap_handler' => 'ldap_directory_writer_create',
                'phase' => 3,
            ],
            'delete_directory_object' => [
                'api_endpoint' => 'delete_directory_object',
                'ps_script_key' => 'deleteADDirectoryObject',
                'ldap_handler' => 'ldap_directory_writer_delete',
                'phase' => 3,
            ],
            'execute_action' => [
                'api_endpoint' => 'execute_action',
                'ldap_handler' => 'ldap_dispatch_execute_action',
                'phase' => 3,
            ],

            // === Intelligence Hub ===
            'hrms_ad_report' => [
                'api_endpoint' => 'get_hrms_ad_report_message',
                'ps_script_key' => 'HRMS_AD_Report',
                'ldap_handler' => 'ldap_hub_hrms_ad_report',
                'phase' => 1,
            ],
            'ou_group_user_report' => [
                'api_endpoint' => 'get_ou_group_user_report',
                'ps_script_key' => 'ou_group_user_report',
                'ldap_handler' => 'ldap_hub_export_users',
                'phase' => 1,
            ],
            'ad_health_check' => [
                'api_endpoint' => 'get_ad_health_check',
                'ps_script_key' => 'AD_Helth_Check',
                'ldap_handler' => 'ldap_hub_health_check',
                'phase' => 1,
            ],
            'user_report' => [
                'api_endpoint' => 'get_user_report',
                'ps_script_key' => 'user_report',
                'ldap_handler' => 'ldap_hub_user_report',
                'phase' => 1,
            ],
        ];
    }
}

if (!function_exists('ldap_operation_meta')) {
    function ldap_operation_meta(string $operation): ?array
    {
        $catalog = ldap_operation_catalog();
        return $catalog[$operation] ?? null;
    }
}
