<?php
/**
 * config/powershell.php
 * 
 * PowerShell automation and script mapping.
 */

$powershellRoot = dirname(__DIR__) . '/scripts/powershell';

return [
    'powershell' => [
        'binary' => PHP_OS_FAMILY !== 'Windows' ? '/usr/bin/pwsh' : 'powershell.exe',
        'default_flags' => [
            '-NoProfile',
            '-ExecutionPolicy Bypass',
        ],
    ],
    'script_paths' => [
        // Individual Automation Scripts
        'unlockUser' => $powershellRoot . '/unlock-user.ps1',
        'resetUnlock' => $powershellRoot . '/reset-unlock-user.ps1',
        'enableUser' => $powershellRoot . '/enable-user.ps1',
        'disableUser' => $powershellRoot . '/disable-user.ps1',
        'createUser' => $powershellRoot . '/create-user.ps1',
        'getUserInfo' => $powershellRoot . '/get-user-info.ps1',
        'getADUserInfo' => $powershellRoot . '/get-ad-user-info.ps1',
        'getADGroupMembers' => $powershellRoot . '/get-ad-group-members.ps1',
        'resolveADPrincipal' => $powershellRoot . '/resolve-ad-principal.ps1',
        'createADDirectoryObject' => $powershellRoot . '/create-ad-directory-object.ps1',
        'deleteADDirectoryObject' => $powershellRoot . '/delete-ad-directory-object.ps1',
        'modifyuser' => $powershellRoot . '/modify-ad-user.ps1',
        'setADGroupMembers' => $powershellRoot . '/set-ad-group-members.ps1',
        'AD_HRMS_STS' => $powershellRoot . '/check-ad-hrms-status.ps1',
        'Export_HRMS_AD_Login_ID' => $powershellRoot . '/export-hrms-ad-login-id.ps1',
        'HRMS_AD_Report' => $powershellRoot . '/get-hrms-ad-report.ps1',
        'Export_AD_User_list' => $powershellRoot . '/export-ad-user-list.ps1',
        'AD_Helth_Check' => $powershellRoot . '/get-ad-health.ps1',
        'AD_Health_Kerberos' => $powershellRoot . '/get-ad-health-kerberos.ps1',
        'ou_group_user_report' => $powershellRoot . '/get-ou-group-user-report.ps1',
        'getOU_Dropdwon' => $powershellRoot . '/get-ad-organizational-units.ps1',
        'getGroup_dropdown' => $powershellRoot . '/get-ad-groups.ps1',
        'manual_create_user' => $powershellRoot . '/manual-create-ad-user.ps1',
        'user_report' => $powershellRoot . '/get-user-report.ps1',
        'UserSecurityEvents' => $powershellRoot . '/get-user-security-events.ps1',
        'CreateCredentialConfig' => $powershellRoot . '/create-credential-config.ps1',
        'clearUacFlags' => $powershellRoot . '/clear-uac-flags.ps1',
    ],
];
