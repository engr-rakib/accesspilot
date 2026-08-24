<?php

require_once __DIR__ . '/../../Infrastructure/PowerShell/powershell_runner.php';
require_once __DIR__ . '/../../Ldap/ldap_module.php';

function getADUserInfo($username, $authenticatedUser)
{
    // Try router first (handles both LDAP and PowerShell via ad_operation_router)
    $routerResult = ad_execute_json_script('get_user_info_bulk', 'getUserInfo', [
        'Username' => $username,
        'ExecutedBy' => $authenticatedUser,
    ], [
        'include_secure_config' => true,
        'non_interactive' => true,
        'mode' => 'shell',
    ]);

    $psResponse = $routerResult['decoded'] ?? null;

    // Fallback to direct PowerShell only if router returned no output at all
    if (empty($psResponse) && empty($routerResult['output'])) {
        $result = powershell_run_script('getUserInfo', [
            'Username' => $username,
            'ExecutedBy' => $authenticatedUser,
        ], [
            'include_secure_config' => true,
            'non_interactive' => true,
            'mode' => 'shell',
        ]);
        $psOutput = $result['output'];
        $psResponse = json_decode($psOutput, true);
    } else {
        $psOutput = json_encode($psResponse);
    }

    if (json_last_error() === JSON_ERROR_NONE && is_array($psResponse)) {
        $overallSuccess = $psResponse['success'] ?? false;
        $infoOutput = '';
        $primaryUserData = null;

        if ($overallSuccess) {
            $userResults = $psResponse['userResults'] ?? [];
            $infoOutputArray = [];

            foreach ($userResults as $userResult) {
                if ($userResult['success']) {
                    $userData = $userResult['data'];
                    if ($primaryUserData === null) {
                        $primaryUserData = $userData;
                    }

                    $formattedUserInfo = '';
                    $samName = $userData['SamAccountName'] ?? '';
                    $formattedUserInfo .= 'AD Account: ' . ($samName !== '' ? $samName : 'N/A') . "\n";
                    $formattedUserInfo .= 'User Principal ID: ' . ($userData['userPrincipalName'] ?? 'N/A') . "\n\n";
                    $formattedUserInfo .= "current user conditions -\n";
                    $formattedUserInfo .= 'Account Status: ' . ($userData['accountStatus'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Account Lock Status: ' . ($userData['accountLockStatus'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'LockOut Time: ' . ($userData['lockoutTime'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Password Status: ' . ($userData['passwordStatus'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Last Password Reset: ' . ($userData['lastPasswordReset'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Password Expiry Date: ' . ($userData['passwordExpiryDate'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Account Expiry Date: ' . ($userData['accountExpirationDate'] ?? 'Never') . "\n";
                    $formattedUserInfo .= 'Security Flags: ' . ($userData['securityFlags'] ?? 'Normal') . "\n\n";

                    if (isset($userData['assignedPrivileges']) && is_array($userData['assignedPrivileges'])) {
                        $formattedUserInfo .= "Assigned Privileges:\n";
                        foreach ($userData['assignedPrivileges'] as $privilege) {
                            $formattedUserInfo .= '------||> ' . $privilege . "\n";
                        }
                    } else {
                        $formattedUserInfo .= 'Assigned Privileges: ' . ($userData['assignedPrivileges'] ?? 'No privileges are assigned.') . "\n";
                    }
                    $formattedUserInfo .= "\n";
                    $formattedUserInfo .= "User Activity -\n";
                    $formattedUserInfo .= 'BAD Passwd Attempt Count: ' . ($userData['totalWrongPassAttemptCount'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'BAD Passwd Attempt Count (Last 12h): ' . ($userData['wrongPassAttemptCountLast12h'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Last Password Attempt D & T: ' . ($userData['lastPasswordAttemptDateTime'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Total Logon Count: ' . ($userData['totalLogonCount'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Last Login: ' . ($userData['lastLogin'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Last LogOff time: ' . ($userData['lastLogoffTime'] ?? 'N/A') . "\n";
                    $wsData = $userData['lastLogonWorkstation'] ?? [];
                    if (is_array($wsData) && !empty($wsData)) {
                        foreach ($wsData as $ws) {
                            $wsName = $ws['workstation'] ?? 'N/A';
                            $wsTime = $ws['time'] ?? '';
                            $formattedUserInfo .= 'Workstation name: ' . $wsName . ($wsTime !== '' ? ' - ' . $wsTime : '') . "\n";
                        }
                    } else {
                        $formattedUserInfo .= 'Workstation name: N/A' . "\n";
                    }
                    $formattedUserInfo .= "\n";
                    $formattedUserInfo .= "Infrastructure Information -\n";
                    $formattedUserInfo .= 'Home Directory: ' . ($userData['homeDirectory'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Home Drive: ' . ($userData['homeDrive'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Profile Path: ' . ($userData['profilePath'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Logon Script: ' . ($userData['logonScript'] ?? 'N/A') . "\n\n";
                    $formattedUserInfo .= "User Profiling Information -\n";
                    $formattedUserInfo .= 'Account Created On: ' . ($userData['accountCreatedOn'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Account Created by: ' . ($userData['accountCreatedBy'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Provision operator name: ' . ($userData['provisionOperatorName'] ?? 'N/A') . "\n\n";
                    $formattedUserInfo .= "User inforamtion-\n";
                    $formattedUserInfo .= 'Employee ID: ' . ($userData['employeeID'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Full Name: ' . ($userData['fullName'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Display Name: ' . ($userData['displayName'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'First Name: ' . ($userData['firstName'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Last Name: ' . ($userData['lastName'] ?? 'N/A') . "\n\n";
                    $formattedUserInfo .= 'Office: ' . ($userData['office'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Phone Number: ' . ($userData['phoneNumber'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Email Address: ' . ($userData['emailAddress'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Web Page: ' . ($userData['webPage'] ?? 'N/A') . "\n\n";
                    $formattedUserInfo .= 'Job Title: ' . ($userData['jobTitle'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Department: ' . ($userData['department'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Company: ' . ($userData['company'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Manager: ' . ($userData['manager'] ?? 'N/A') . "\n\n";
                    $formattedUserInfo .= 'Street Address: ' . ($userData['streetAddress'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'City: ' . ($userData['city'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'State: ' . ($userData['state'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Postal Code: ' . ($userData['postalCode'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'Country: ' . ($userData['country'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'PO Box: ' . ($userData['postOfficeBox'] ?? 'N/A') . "\n\n";
                    $formattedUserInfo .= 'Description: ' . ($userData['description'] ?? 'N/A') . "\n";
                    $formattedUserInfo .= 'OU Location: ' . ($userData['ouLocation'] ?? 'N/A') . "\n\n";

                    $mailboxData = $userData['exchange_mailbox'] ?? [];
                    if (!empty($mailboxData)) {
                        $formattedUserInfo .= "Exchange Mailbox -\n";
                        $formattedUserInfo .= 'Mailbox Status: ' . ($mailboxData['has_mailbox'] ? 'Enabled' : 'Not enabled') . "\n";
                        if ($mailboxData['has_mailbox']) {
                            $formattedUserInfo .= 'Alias: ' . ($mailboxData['alias'] ?: 'N/A') . "\n";
                            $formattedUserInfo .= 'Recipient Type: ' . ($mailboxData['recipient_type'] ?: 'N/A') . "\n";
                            if ($mailboxData['home_database']) {
                                $formattedUserInfo .= 'Home Database: ' . $mailboxData['home_database'] . "\n";
                            }
                            if ($mailboxData['when_created'] && $mailboxData['when_created'] !== 'N/A') {
                                $formattedUserInfo .= 'Mailbox Created: ' . $mailboxData['when_created'] . "\n";
                            }
                            $formattedUserInfo .= 'Hidden from GAL: ' . ($mailboxData['hidden_from_gal'] ? 'Yes' : 'No') . "\n";
                            $formattedUserInfo .= 'Archive: ' . (!empty($mailboxData['archive_name']) ? $mailboxData['archive_name'] : 'Not enabled') . "\n";
                        }
                        if (!empty($mailboxData['proxy_addresses'])) {
                            foreach ($mailboxData['proxy_addresses'] as $addr) {
                                $label = $addr['is_primary'] ? ' [PRIMARY]' : '';
                                $formattedUserInfo .= '------||> ' . $addr['address'] . $label . "\n";
                            }
                        }
                        $formattedUserInfo .= "\n";
                    }

                    $infoOutputArray[] = $formattedUserInfo;
                } else {
                    $infoOutputArray[] = 'Error for user ' . ($userResult['username'] ?? 'N/A') . ': ' . ($userResult['message'] ?? 'Unknown error.');
                }
            }

            $infoOutput = implode("\n\n", $infoOutputArray);
        } else {
            $rawMsg = $psResponse['message'] ?? 'Failed to fetch server info.';
            // Strip summary lines (>> Processed: ... <<) from user-facing output
            $cleanParts = array_filter(explode("\n", $rawMsg), function ($line) {
                return trim($line) !== '' && !preg_match('/^>>\s*Processed:/', trim($line));
            });
            $infoOutput = implode("\n", $cleanParts);
        }

        return [
            'infoOutput' => rtrim($infoOutput, ':'),
            'success' => $overallSuccess,
            'adData' => $primaryUserData,
            'suggestions' => $psResponse['suggestions'] ?? null,
        ];
    }

    $infoOutput = 'Failed to parse directory backend output. Raw: ' . $psOutput;
    error_log('getADUserInfo Debug: JSON parse failed. Raw Output: ' . $psOutput);

    return [
        'infoOutput' => rtrim($infoOutput, ':'),
        'success' => false,
        'adData' => null,
        'suggestions' => null,
    ];
}

function fetchHRMSRaw($apiUrl)
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_SSLVERSION => 6,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_USERAGENT => 'AccessPilot-Portal/4.0',
    ]);

    $apiResponse = curl_exec($ch);
    curl_close($ch);

    if ($apiResponse === false) {
        $encodedUrl = base64_encode($apiUrl);
        $command = "powershell -NoProfile -ExecutionPolicy Bypass -Command \"\$url = [System.Text.Encoding]::UTF8.GetString([System.Convert]::FromBase64String('$encodedUrl')); try { (Invoke-RestMethod -Uri \$url -Method Get) | ConvertTo-Json -Compress -Depth 10 } catch { return \$null }\"";
        $apiResponse = shell_exec($command);
    }

    return $apiResponse;
}

function getHRMSInfo($username)
{
    $baseUrl = (string) config_get('api_paths.hrms_api_url', '');

    if (empty($baseUrl)) {
        return ['success' => false, 'apiData' => null, 'message' => 'HRMS API URL is not configured. Go to System Configuration to set it.'];
    }

    $ids = array_unique(array_filter([$username, preg_replace('/[^0-9]/', '', $username)]));

    $apiFailed = false;
    foreach ($ids as $id) {
        $resp = fetchHRMSRaw($baseUrl . urlencode($id));
        if ($resp === false || $resp === null) {
            $apiFailed = true;
            continue;
        }
        $data = json_decode($resp, true);
        if ($data && is_array($data) && !empty($data['EMP_ID'])) {
            return ['success' => true, 'apiData' => $data];
        }
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            $apiFailed = true;
            continue;
        }
    }

    if ($apiFailed) {
        return ['success' => false, 'apiData' => null, 'message' => 'HRMS API is not reachable. Verify the API URL in System Configuration or check network/SSL settings.'];
    }

    return ['success' => false, 'apiData' => null, 'message' => "User '$username' not found in HRMS."];
}

if (!function_exists('getHRMSEmployeesByStatus')) {
    function getHRMSEmployeesByStatus(string $status, ?string $overrideUrl = null): array
    {
        if ($overrideUrl) {
            $baseUrl = $overrideUrl;
        } else {
            $baseUrl = (string) config_get('api_paths.hrms_emp_sts_url', '');
            if (empty($baseUrl)) {
                $baseUrl = (string) config_get('api_paths.hrms_api_url', '');
            }
        }
        if (empty($baseUrl)) {
            return ['success' => false, 'employees' => [], 'message' => 'API URL not configured.'];
        }

        // Strip existing query params and build URL with emp_sts
        $cleanUrl = preg_replace('/\?.*$/', '', $baseUrl);
        $apiUrl = $cleanUrl . '?emp_sts=' . urlencode($status);

        $resp = fetchHRMSRaw($apiUrl);
        if ($resp === false || $resp === null) {
            return ['success' => false, 'employees' => [], 'message' => 'API unreachable.'];
        }

        $data = json_decode($resp, true);
        if (!is_array($data)) {
            return ['success' => false, 'employees' => [], 'message' => 'Invalid JSON response from API.'];
        }

        return [
            'success' => true,
            'employees' => $data,
            'message' => count($data) . ' employee(s) found with status: ' . $status,
        ];
    }
}
