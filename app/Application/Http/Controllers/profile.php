<?php

ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
define('_CORE_ADMIN_', true);
header('Content-Type: application/json');
require_once __DIR__ . '/../../Support/helpers.php';
require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Domain/UserManagement/user_management_service.php';
require_once __DIR__ . '/../../../Domain/Audit/audit_service.php';
require_once __DIR__ . '/../../../Infrastructure/Persistence/repositories.php';
require_once __DIR__ . '/../../../Ldap/Config/ldap_config_repository.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

$authenticatedUser = $_SESSION['username'] ?? 'UnknownUser';

if (!function_exists('readAllAuditLogs')) {
    function readAllAuditLogs(): array {
        $dir = resolved_log_path();
        if (!is_dir($dir)) { return []; }
        $files = glob($dir . DIRECTORY_SEPARATOR . 'audit-*.csv');
        if (!$files) { return []; }
        sort($files);
        $logs = [];
        foreach ($files as $file) {
            $fh = @fopen($file, 'r');
            if (!$fh) continue;
            $headers = fgetcsv($fh, 0, ',', '"', '\\');
            if (!$headers) { fclose($fh); continue; }
            while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
                $entry = [];
                foreach ($headers as $i => $h) {
                    $entry[trim($h)] = $row[$i] ?? '';
                }
                $logs[] = $entry;
            }
            fclose($fh);
        }
        return $logs;
    }
}

if (!function_exists('readActivityLog')) {
    function readActivityLog(): array {
        return readAllAuditLogs();
    }
}

// Handle File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $file = $_FILES['avatar'];

    // 1. Size Constraint: Max 1MB
    if ($file['size'] > 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Image size exceeds 1MB limit.']);
        exit();
    }

    // 2. Validate Image & Ratio
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        echo json_encode(['success' => false, 'message' => 'File is not a valid image.']);
        exit();
    }

    $width = $check[0];
    $height = $check[1];
    if ($width !== $height) {
        echo json_encode(['success' => false, 'message' => 'Image must be a square (1:1 aspect ratio). Please crop and try again.']);
        exit();
    }

    // 3. Strict extension allowlist
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExtension, $allowedExtensions, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: jpg, jpeg, png, gif, webp.']);
        exit();
    }

    // 4. Re-encode image to strip metadata (EXIF, comments, embedded PHP)
    $imageResource = null;
    switch ($fileExtension) {
        case 'jpg':
        case 'jpeg':
            $imageResource = @imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'png':
            $imageResource = @imagecreatefrompng($file['tmp_name']);
            break;
        case 'gif':
            $imageResource = @imagecreatefromgif($file['tmp_name']);
            break;
        case 'webp':
            $imageResource = @imagecreatefromwebp($file['tmp_name']);
            break;
    }
    if ($imageResource === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to process the image.']);
        exit();
    }

    $safeFileName = preg_replace('/[^a-z0-9]/i', '_', $authenticatedUser) . '_' . time() . '.' . $fileExtension;
    $targetPath = repo_profile_img_path($safeFileName);

    $saved = false;
    switch ($fileExtension) {
        case 'jpg':
        case 'jpeg':
            $saved = imagejpeg($imageResource, $targetPath, 85);
            break;
        case 'png':
            $saved = imagepng($imageResource, $targetPath, 6);
            break;
        case 'gif':
            $saved = imagegif($imageResource, $targetPath);
            break;
        case 'webp':
            $saved = imagewebp($imageResource, $targetPath, 85);
            break;
    }
    imagedestroy($imageResource);

    if ($saved) {
        $users = readUsers();
        if (isset($users[$authenticatedUser])) {
            $users[$authenticatedUser]['avatar'] = $safeFileName;
            writeUsers($users);
            $_SESSION['avatar'] = $safeFileName;
            
            log_activity($authenticatedUser, 'Updated Profile Picture', 'success', "User uploaded a new secure profile picture.");
            echo json_encode(['success' => true, 'message' => 'Profile picture updated.', 'avatar' => $safeFileName]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found in database.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to store image in secure vault.']);
    }
    exit();
}

$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);
$action = $data['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($action)) {
    $users = readUsers();

    switch ($action) {
        case 'get_profile':
            if (isset($users[$authenticatedUser])) {
                $profile = $users[$authenticatedUser];
                unset($profile['password']);
                $allLogs = readActivityLog();
                $totalActions = 0; $loginCount = 0; $login24h = 0;
                $adActionCount = 0; $webActionCount = 0;
                $adBreakdown = [];
                $currentDomain = function_exists('ldap_active_domain_key') ? ldap_active_domain_key() : 'Unknown';
                $cutoff24h = time() - 86400;
                $adActions = ['unlockUser','resetUnlock','enableUser','disableUser','createUser','modifyuser','info'];
                foreach ($allLogs as $log) {
                    if (($log['Username'] ?? '') !== $authenticatedUser) continue;
                    $logAction = $log['Action'] ?? '';
                    $logTime = $log['Timestamp'] ?? '';
                    $logDetails = $log['Details'] ?? '';
                    $totalActions++;
                    if ($logAction === 'domain_switch') {
                        if (preg_match('/to:\s*(\S+)/i', $logDetails, $m)) {
                            $currentDomain = $m[1];
                        }
                        continue;
                    }
                    if (stripos($logAction, 'login') !== false) {
                        $loginCount++;
                        if ($logTime) {
                            $ts = strtotime($logTime);
                            if ($ts !== false && $ts >= $cutoff24h) $login24h++;
                        }
                        continue;
                    }
                    if (in_array($logAction, $adActions, true)) {
                        $adActionCount++;
                        $adBreakdown[$currentDomain] = ($adBreakdown[$currentDomain] ?? 0) + 1;
                        continue;
                    }
                    $webActionCount++;
                }
                $profile['action_count'] = $totalActions;
                $profile['login_count'] = $loginCount;
                $profile['login_24h'] = $login24h;
                $profile['ad_action_count'] = $adActionCount;
                $profile['web_action_count'] = $webActionCount;
                $profile['ad_breakdown'] = $adBreakdown;
                echo json_encode(['success' => true, 'profile' => $profile]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Profile data not found.']);
            }
            break;

        case 'update_details':
            if (isset($users[$authenticatedUser])) {
                $users[$authenticatedUser]['full_name'] = trim($data['full_name'] ?? $users[$authenticatedUser]['full_name'] ?? '');
                $users[$authenticatedUser]['email'] = trim($data['email'] ?? $users[$authenticatedUser]['email'] ?? '');
                $users[$authenticatedUser]['mobile'] = trim($data['mobile'] ?? $users[$authenticatedUser]['mobile'] ?? '');
                
                if (writeUsers($users)) {
                    log_activity($authenticatedUser, 'Updated Profile Details', 'success', "User updated their profile information.");
                    echo json_encode(['success' => true, 'message' => 'Profile details updated successfully.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save changes.']);
                }
            }
            break;

        case 'update_preferences':
            if (isset($users[$authenticatedUser])) {
                $users[$authenticatedUser]['preferences'] = [
                    'theme' => $data['theme'] ?? ($users[$authenticatedUser]['preferences']['theme'] ?? 'theme-corporate-blue'),
                    'auto_refresh' => (bool)($data['auto_refresh'] ?? true),
                    'notifications' => (bool)($data['notifications'] ?? true),
                    'sound_alerts' => (bool)($data['sound_alerts'] ?? false),
                ];
                
                if (writeUsers($users)) {
                    echo json_encode(['success' => true, 'message' => 'Preferences saved.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save preferences.']);
                }
            }
            break;

        case 'get_activity':
            $limit = min((int)($data['limit'] ?? 10), 50);
            $allLogs = readActivityLog();
            $userLogs = [];
            $count = 0;
            for ($i = count($allLogs) - 1; $i >= 0 && $count < $limit; $i--) {
                if (($allLogs[$i]['Username'] ?? '') === $authenticatedUser) {
                    $userLogs[] = [
                        'action' => $allLogs[$i]['Action'] ?? '',
                        'status' => $allLogs[$i]['Status'] ?? '',
                        'timestamp' => $allLogs[$i]['Timestamp'] ?? '',
                        'details' => $allLogs[$i]['Details'] ?? ''
                    ];
                    $count++;
                }
            }
            echo json_encode(['success' => true, 'activity' => $userLogs]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
            break;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
