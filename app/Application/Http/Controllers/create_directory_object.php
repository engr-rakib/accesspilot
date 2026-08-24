<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Infrastructure/PowerShell/powershell_runner.php';
require_once __DIR__ . '/../../../Ldap/Config/ldap_config_repository.php';
require_once __DIR__ . '/../../../Ldap/Operations/ldap_directory_writer.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.']);
    exit();
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

if (!(has_permission('manual_create_user') || has_permission('modify_ad_user'))) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to create directory objects.']);
    exit();
}

if (!defined('API_GATEWAY')) {
    die('Direct access not permitted');
}

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['message'] = 'Invalid request method.';
        echo json_encode($response);
        exit();
    }

    $objectType = trim((string) ($_POST['objectType'] ?? ''));
    $objectName = trim((string) ($_POST['objectName'] ?? ''));
    $parentOU = trim((string) ($_POST['parentOU'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $executedBy = $_SESSION['username'] ?? 'UnknownUser';

    if ($objectType === '' || $objectName === '' || $parentOU === '') {
        $response['message'] = 'Object type, object name, and parent OU are required.';
        echo json_encode($response);
        exit();
    }

    $backend = ldap_read_config()['backend'] ?? 'powershell';

    if ($backend === 'ldap' || $backend === 'auto') {
        $result = ldap_directory_writer_create([
            'ObjectType' => $objectType,
            'ObjectName' => $objectName,
            'ParentOU' => $parentOU,
            'Description' => $description,
            'ExecutedBy' => $executedBy,
        ], $executedBy);
        $decoded = $result['decoded'] ?? [];
        $logAction = ($objectType === 'OU') ? 'create_ou' : 'create_grp';
        ldap_write_script_log('create_directory_object', $objectName, !empty($decoded['success']), $decoded['message'] ?? '', $executedBy, $logAction);
    } else {
        $result = powershell_run_json_script('createADDirectoryObject', [
            'ObjectType' => $objectType,
            'ObjectName' => $objectName,
            'ParentOU' => $parentOU,
            'Description' => $description,
            'ExecutedBy' => $executedBy,
        ], [
            'include_secure_config' => true,
        ]);
    }

    if ($result['json_valid']) {
        $decoded = $result['decoded'];
        $response['success'] = $decoded['success'] ?? false;
        $response['message'] = $decoded['message'] ?? 'Action processed.';
    } else {
        $response['message'] = 'Failed to create the directory object or parse response.';
        $response['technical_details'] = $result['output'];
    }
} catch (Throwable $e) {
    $response['message'] = 'Critical System Error: ' . $e->getMessage();
    error_log('create_directory_object.php Fatal: ' . $e->getMessage());
}

echo json_encode($response);
