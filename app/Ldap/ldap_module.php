<?php
/**
 * AccessPilot LDAP module bootstrap.
 *
 * Usage:
 *   require_once app_root('app/Ldap/ldap_module.php');
 *
 * Loads all LDAP Config, Connection, Operations, Services, Diagnostics, and Router.
 */

if (defined('ACCESSPILOT_LDAP_MODULE_LOADED')) {
    return;
}

define('ACCESSPILOT_LDAP_MODULE_LOADED', true);

$ldapModuleRoot = __DIR__;

require_once $ldapModuleRoot . '/Config/ldap_config_repository.php';
require_once $ldapModuleRoot . '/Connection/ldap_connection_factory.php';
require_once $ldapModuleRoot . '/Support/ldap_helpers.php';
require_once $ldapModuleRoot . '/Operations/ldap_operation_catalog.php';
require_once $ldapModuleRoot . '/Operations/ldap_response_adapter.php';

$ldapOperationStubs = [
    'ldap_user_repository.php',
    'ldap_group_repository.php',
    'ldap_user_writer.php',
    'ldap_directory_writer.php',
];

foreach ($ldapOperationStubs as $stubFile) {
    $stubPath = $ldapModuleRoot . '/Operations/' . $stubFile;
    if (is_file($stubPath)) {
        require_once $stubPath;
    }
}

require_once $ldapModuleRoot . '/Diagnostics/ldap_environment.php';
require_once $ldapModuleRoot . '/Services/ldap_config_service.php';
require_once $ldapModuleRoot . '/Router/ad_operation_router.php';
