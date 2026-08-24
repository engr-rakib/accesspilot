<?php

require_once __DIR__ . '/../../Application/Support/helpers.php';
require_once __DIR__ . '/../../Infrastructure/PowerShell/powershell_runner.php';
require_once __DIR__ . '/../Licensing/license_service.php';

function executeADAction($username, $action, $authenticatedUser)
{
    if (license_is_restricted()) {
        return [
            'success' => false,
            'message' => 'Operation restricted. Please purchase and provide a legal license to perform this action.',
            'infoOutput' => '',
            'apiData' => null,
            'actionTaken' => '',
            'showUserInfoSection' => false,
        ];
    }

    $scriptPaths = (array) config_get('script_paths', []);

    $message = '';
    $success = false;

    if (array_key_exists($action, $scriptPaths)) {
        if ($action === 'AD_Helth_Check') {
            $result = powershell_run_script($action, [
                'ExecutedBy' => $authenticatedUser,
            ], [
                'include_secure_config' => true,
                'mode' => 'shell',
            ]);
            $psOutput = $result['output'];

            if ($psOutput === null) {
                $success = false;
                $message = 'Error: Failed to execute PowerShell script (shell_exec returned null).';
            } else {
                $isError = (stripos($psOutput, 'Error:') !== false || stripos($psOutput, 'Failed:') !== false || stripos($psOutput, 'Exception:') !== false);
                $success = !$isError;
                $message = nl2br(htmlspecialchars(trim($psOutput)));
            }
        } elseif ($action === 'AD_HRMS_STS') {
            $secureDebugRoot = rtrim((string) (secure_path('appusers_dir') ?: storage_path('secure_debug')), '/\\') . '/debug';
            if (!is_dir($secureDebugRoot)) {
                mkdir($secureDebugRoot, 0755, true);
            }

            $tempBatFilePath = $secureDebugRoot . '/debug_powershell_command.bat';
            $tempOutputFilePath = $secureDebugRoot . '/debug_powershell_output.txt';
            $powershellCommand = powershell_build_command($action, [
                'ExecutedBy' => $authenticatedUser,
                'Usernames' => $username,
            ], [
                'include_secure_config' => true,
                'non_interactive' => true,
            ]);

            file_put_contents($tempBatFilePath, $powershellCommand . " > \"$tempOutputFilePath\" 2>&1");
            $batRun = powershell_exec_command('cmd /c ' . escapeshellarg($tempBatFilePath), [
                'capture_stderr' => false,
            ]);
            $psOutput = file_exists($tempOutputFilePath) ? file_get_contents($tempOutputFilePath) : '';

            $success = ($batRun['exit_code'] === 0);
            $message = "AD & HRMS Status report execution attempted via secured debug runner. Exit Code: " . $batRun['exit_code'] . ". Raw PowerShell output: " . ($psOutput ? htmlspecialchars($psOutput) : '(empty)') . ". Debug artifacts stored in secured runtime storage.";
        } elseif ($action === 'Export_HRMS_AD_Login_ID') {
            $result = powershell_run_script($action, [
                'ExecutedBy' => $authenticatedUser,
                'Usernames' => $username,
            ], [
                'include_secure_config' => true,
                'mode' => 'shell',
            ]);
            $psOutput = $result['output'];

            if ($psOutput === null) {
                $success = false;
                $message = 'Error: Failed to execute PowerShell script (shell_exec returned null).';
            } else {
                $isError = (stripos($psOutput, 'Error:') !== false || stripos($psOutput, 'Failed:') !== false || stripos($psOutput, 'Exception:') !== false);
                $success = !$isError;
                $message = nl2br(htmlspecialchars(trim($psOutput)));
            }
        } elseif ($action === 'Export_AD_User_list') {
            $result = powershell_run_script($action, [
                'ExecutedBy' => $authenticatedUser,
            ], [
                'include_secure_config' => true,
                'mode' => 'shell',
            ]);
            $psOutput = $result['output'];

            if ($psOutput === null) {
                $success = false;
                $message = 'Error: Failed to execute PowerShell script (shell_exec returned null).';
            } else {
                $isError = (stripos($psOutput, 'Error:') !== false || stripos($psOutput, 'Failed:') !== false || stripos($psOutput, 'Exception:') !== false);
                $success = !$isError;
                $message = nl2br(htmlspecialchars(trim($psOutput)));
            }
        } elseif ($action === 'createUser') {
            $usernamesArray = array_map('trim', preg_split('/[\s,]+/', $username, -1, PREG_SPLIT_NO_EMPTY));
            $psArrayString = implode(',', $usernamesArray);

            // Read active domain config for OU/Group customization
            $psParams = [
                'ExecutedBy' => $authenticatedUser,
                'Usernames' => $psArrayString,
            ];
            if (function_exists('ldap_active_domain_key') && function_exists('ldap_get_domain')) {
                $activeKey = ldap_active_domain_key();
                $domain = ldap_get_domain($activeKey);
                if ($domain) {
                    if (!empty($domain['ou_config'])) {
                        $json = json_encode($domain['ou_config'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        if ($json !== false) {
                            $psParams['OuConfig'] = $json;
                        }
                    }
                    if (!empty($domain['group_config'])) {
                        $json = json_encode($domain['group_config'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        if ($json !== false) {
                            $psParams['GroupConfig'] = $json;
                        }
                    }
                }
            }

            $result = powershell_run_script($action, $psParams, [
                'include_secure_config' => true,
                'mode' => 'shell',
            ]);
            $psOutput = $result['output'];
            $psResponse = json_decode($psOutput, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($psResponse)) {
                $detailedActionMessage = $psResponse['detailedActionMessage'] ?? 'No detailed message provided.';
                $processedCount = $psResponse['processed'] ?? 0;
                $successCount = $psResponse['successCount'] ?? 0;
                $skippedCount = $psResponse['skippedCount'] ?? 0;
                $failedCount = $psResponse['failedCount'] ?? 0;
                $moveCount = $psResponse['moveCount'] ?? 0;
                $resetCount = $psResponse['resetCount'] ?? 0;
                $enableCount = $psResponse['enableCount'] ?? 0;

                $summaryParts = [];
                $summaryParts[] = 'Processed: ' . $processedCount;
                $summaryParts[] = 'SKIP: ' . $skippedCount;
                $summaryParts[] = 'MOVE: ' . $moveCount;
                $summaryParts[] = 'RESET: ' . $resetCount;
                if ($enableCount > 0) {
                    $summaryParts[] = 'ENABLE: ' . $enableCount;
                }
                $summaryParts[] = 'CREATED : ' . $successCount;
                $summaryParts[] = 'FAILED : ' . $failedCount;

                $message = $detailedActionMessage . "\n>> " . implode(' | ', $summaryParts) . ' <<';
                $success = ($processedCount > 0 && $failedCount == 0);
            } else {
                $success = false;
                $message = 'Failed to parse PowerShell output. Raw: ' . htmlspecialchars(trim($psOutput));
            }
        } elseif ($action === 'modifyuser') {
            $result = powershell_run_script($action, [
                'OriginalSamAccountName' => $_POST['username'] ?? $username,
                'NewSamAccountName' => $_POST['new_username'] ?? $username,
                'DisplayName' => $_POST['display_name'] ?? '',
                'OU' => $_POST['ou'] ?? '',
                'Description' => $_POST['description'] ?? '',
                'Title' => $_POST['title'] ?? '',
                'Department' => $_POST['department'] ?? '',
                'Company' => $_POST['company'] ?? '',
                'PhysicalDeliveryOfficeName' => $_POST['physicalDeliveryOfficeName'] ?? '',
                'TelephoneNumber' => $_POST['telephoneNumber'] ?? '',
                'ExecutedBy' => $authenticatedUser,
            ], [
                'include_secure_config' => true,
                'mode' => 'shell',
            ]);
            $psOutput = $result['output'];
            $psResponse = json_decode($psOutput, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($psResponse)) {
                $success = $psResponse['success'] ?? false;
                $message = $psResponse['message'] ?? 'Unknown response from PowerShell script.';
            } else {
                $success = false;
                $message = 'Failed to parse PowerShell output. Raw: ' . htmlspecialchars(trim($psOutput));
            }
        } elseif ($action === 'manual_create_user') {
            $ouComponents = [];
            if (!empty($_POST['sub_section'])) $ouComponents[] = 'OU=' . ldap_escape_dn_component($_POST['sub_section']);
            if (!empty($_POST['product'])) $ouComponents[] = 'OU=' . ldap_escape_dn_component($_POST['product']);
            if (!empty($_POST['section'])) $ouComponents[] = 'OU=' . ldap_escape_dn_component($_POST['section']);
            if (!empty($_POST['department'])) $ouComponents[] = 'OU=' . ldap_escape_dn_component($_POST['department']);
            if (!empty($_POST['operating_unit'])) $ouComponents[] = 'OU=' . ldap_escape_dn_component($_POST['operating_unit']);
            $constructedOU = implode(',', $ouComponents);
            $result = powershell_run_script($action, [
                'ExecutedBy' => $authenticatedUser,
                'Username' => $_POST['username'] ?? '',
                'DisplayName' => $_POST['full_name'] ?? '',
                'OU' => $constructedOU,
            ], [
                'include_secure_config' => true,
                'mode' => 'shell',
            ]);
            $psOutput = $result['output'];
            $psResponse = json_decode($psOutput, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($psResponse)) {
                $success = $psResponse['success'] ?? false;
                $message = $psResponse['message'] ?? 'Unknown response from PowerShell script.';
            } else {
                $success = false;
                $message = 'Failed to parse PowerShell output. Raw: ' . htmlspecialchars(trim($psOutput));
            }
        } else {
            $usernamesArray = array_map('trim', preg_split('/[\s,;]+/', $username, -1, PREG_SPLIT_NO_EMPTY));
            $result = powershell_run_script($action, [
                'ExecutedBy' => $authenticatedUser,
                'Username' => $usernamesArray,
            ], [
                'include_secure_config' => true,
                'mode' => 'shell',
            ]);
            $psOutput = $result['output'];
            $psResponse = json_decode($psOutput, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($psResponse)) {
                $success = $psResponse['success'] ?? false;
                $summaryMessage = $psResponse['message'] ?? 'Unknown response from PowerShell script.';
                $userResults = $psResponse['userResults'] ?? [];
                $userMessages = [];

                foreach ($userResults as $userResult) {
                    $userMessages[] = ($userResult['success'] ? 'SUCCESS: ' : 'ERROR: ') . ($userResult['message'] ?? 'No message provided.');
                }

                $message = implode("\n\n", $userMessages);
                if (!empty($userMessages)) {
                    $message .= "\n\n";
                }
                $message .= '>> ' . $summaryMessage . ' <<';
            } else {
                $success = false;
                $message = 'Failed to parse PowerShell output. Raw: ' . htmlspecialchars(trim($psOutput));
            }
        }
    } else {
        $message = 'Error: Invalid AD action specified.';
    }

    return [
        'success' => $success,
        'message' => $message,
        'infoOutput' => '',
        'apiData' => null,
        'actionTaken' => $action,
        'showUserInfoSection' => false,
    ];
}

function ldap_escape_dn_component(string $value): string
{
    $special = ['\\', ',', '+', '"', '<', '>', ';', '=', '#', "\n", "\r"];
    $replacement = [];
    foreach ($special as $ch) {
        $replacement[] = '\\' . dechex(ord($ch));
    }
    return str_replace($special, $replacement, $value);
}
