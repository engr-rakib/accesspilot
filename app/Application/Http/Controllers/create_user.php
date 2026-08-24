<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
define('_CORE_ADMIN_', true);
header('Content-Type: application/json');

require_once __DIR__ . '/../../Support/helpers.php';
require_once __DIR__ . '/../../../Domain/RBAC/rbac_service.php';
require_once __DIR__ . '/../../../Domain/Audit/audit_service.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.']);
    exit();
}

if (isset($_SESSION['role'])) {
    load_user_permissions($_SESSION['role']);
}

if (!(has_permission('action_new_user_form') || has_permission('manual_create_user') || has_permission('user_create'))) {
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to create users.']);
    exit();
}

require_once __DIR__ . '/../../../Domain/UserManagement/user_management_service.php';
require_once __DIR__ . '/../../../Domain/ActiveDirectory/action_executor.php';

$response = [
    'success' => false,
    'message' => 'Invalid request.'
];

$data = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $create_from_hrms = isset($data['create_from_hrms']);
    $username_input = '';
    $hrms_id_input = '';

    if ($create_from_hrms) {
        $hrms_id_input = $data['hrms_id'] ?? '';
        $username_input = $hrms_id_input;
    } else {
        $username_input = $data['username'] ?? '';
        $hrms_id_input = $data['hrms_id'] ?? '';
    }

    $email = $data['email'] ?? '';
    $role = $data['role'] ?? 'user';
    $full_name = $data['full_name'] ?? '';
    $system_access = isset($data['system_access']);
    $enable_mailbox = isset($data['enable_mailbox']);

    $users = readUsers();

    if (isset($users[$username_input])) {
        $response['message'] = 'Error: User \'' . htmlspecialchars($username_input) . '\' already exists.';
    } elseif (empty($username_input)) {
        $response['message'] = 'Error: Username/HRMS ID is required.';
    } else {
        $default_temp_password = config_get('default_password');
        $new_user_data = [
            'password' => password_hash($default_temp_password, PASSWORD_DEFAULT),
            'email' => $email,
            'role' => $role,
            'system_access' => $system_access,
            'hrms_id' => $hrms_id_input,
            'full_name' => $full_name,
            'enable_mailbox' => $enable_mailbox,
        ];

        if (!empty($hrms_id_input)) {
            $hrms_info = getHRMSInfo($hrms_id_input);
            if ($hrms_info['success'] && !empty($hrms_info['apiData'])) {
                $apiData = $hrms_info['apiData'];
                $new_user_data['hrms_id'] = $apiData['EMP_CODE'] ?? $new_user_data['hrms_id'];
                $new_user_data['full_name'] = $apiData['EMP_NAME'] ?? $new_user_data['full_name'];
                $new_user_data['email'] = $apiData['EMAIL'] ?? $new_user_data['email'];
                $new_user_data['mobile'] = $apiData['MOBILE'] ?? '';
                $new_user_data['designation'] = $apiData['DESIGNATION'] ?? '';
                $new_user_data['designation_order'] = $apiData['DESIGNATION_ORDER'] ?? '';
                $new_user_data['operating_unit'] = $apiData['OPERATING_UNIT_TITLE'] ?? '';
                $new_user_data['location'] = $apiData['LOCATION_TITLE'] ?? '';
                $new_user_data['department'] = $apiData['DEPARTMENT_TITLE'] ?? '';
                $new_user_data['section'] = $apiData['SECTION_TITLE'] ?? '';
                $new_user_data['product'] = $apiData['PRODUCT_TITLE'] ?? '';
                $new_user_data['sub_section'] = $apiData['SUB_SECTION_TITLE'] ?? '';
                $new_user_data['joining_date'] = $apiData['JOINING_DT'] ?? '';
                $new_user_data['dob'] = $apiData['DOB'] ?? '';
                $new_user_data['gender'] = $apiData['GENDER'] ?? '';
                $new_user_data['age'] = $apiData['AGE'] ?? '';
                $new_user_data['hrms_status'] = $apiData['EMP_STS'] ?? '';
            } elseif ($create_from_hrms) {
                $response['message'] = 'Error: HRMS data not found for the provided ID.';
                log_activity($_SESSION['username'] ?? 'UnknownUser', 'Created Web User', 'failure', "HRMS lookup failed for '{$hrms_id_input}'.");
                echo json_encode($response);
                exit();
            }
        }

        $users[$username_input] = $new_user_data;
        writeUsers($users);

        $response['success'] = true;
        $response['message'] = 'User \'' . htmlspecialchars($username_input) . '\' created successfully. Temporary password: <strong>' . $default_temp_password . '</strong>';
        $response['created_username'] = $username_input;
        log_activity($_SESSION['username'] ?? 'UnknownUser', 'Created Web User', 'success', "Created web user '{$username_input}'" . ($hrms_id_input !== '' ? " with HRMS ID '{$hrms_id_input}'." : '.'));
        $_SESSION['flash_message'] = $response['message'];
        $_SESSION['flash_is_success'] = true;
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
