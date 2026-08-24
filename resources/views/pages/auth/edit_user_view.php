<?php
if (!defined('_CORE_ADMIN_')) {
    die('Direct access not permitted');
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// This is the view file for editing a user.
// It is included by indexpro.php.

// Page-level access control
if (!has_permission('user_edit')) {
    include include_path('resources/views/pages/auth/unauthorized_view.php');
    return;
}

$username_to_edit = $_GET['username'] ?? '';
$user_to_edit = $users[$username_to_edit] ?? null;
$is_protected_admin_user = (strcasecmp((string)$username_to_edit, 'admin') === 0);
$defaultTempPassword = config_get('default_password', '');

if (!$user_to_edit) {
    echo '<div class="alert alert-danger">User not found.</div>';
    return;
}
?>

<div class="card mb-3 slide-in-bottom" id="actionTakenCardContainer" style="display: none;">
    <div class="card-header d-flex align-items-center">
        <h3 class="mb-0">Action Taken: <span id="actionTakenTitle"></span></h3>
    </div>
    <div class="card-body">
        <div id="actionTakenMessageDisplay" class="alert"></div>
    </div>
</div>
        <div class="card mb-3 slide-in-top">
            <div class="card-header">
                <h3><i class="fas fa-user-edit"></i> Edit User: <?= htmlspecialchars($username_to_edit) ?></h3>
            </div>
            <div class="card-body">
                <form id="editUserForm" method="POST" action="<?= admin_page_url('edit_user', ['username' => $username_to_edit]) ?>">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="old_username" value="<?= htmlspecialchars($username_to_edit) ?>">

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="new_username" class="form-label">Logon ID (Primary Key)</label>
                            <input type="text" class="form-control fw-bold" id="new_username" name="new_username" value="<?= htmlspecialchars($username_to_edit) ?>" required>
                            <div class="form-text">Changing this changes the login name.</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="full_name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($user_to_edit['full_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" <?= $is_protected_admin_user ? 'disabled' : '' ?>>
                                <?php
                                $roles_data = repo_read_roles();
                                $current_user_role = $user_to_edit['role'] ?? '';
                                if (!empty($roles_data)) {
                                    foreach ($roles_data as $role_name => $role_details) {
                                        $selected = (strcasecmp((string)$current_user_role, (string)$role_name) === 0) ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($role_name) . '" ' . $selected . '>' . htmlspecialchars(ucfirst(str_replace('_', ' ', $role_name))) . '</option>';
                                    }
                                } else {
                                    echo '<option value="">Error: roles repository is empty or unreadable.</option>';
                                }
                                ?>
                            </select>
                            <?php if ($is_protected_admin_user): ?>
                                <input type="hidden" name="role" value="core_admin">
                                <div class="form-text text-danger">The `admin` account is protected and must remain assigned to `core_admin`.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user_to_edit['email'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4 mb-3 align-self-center">
                            <div class="form-check mt-4">
                                <input type="checkbox" class="form-check-input" id="system_access" name="system_access" <?= $user_to_edit['system_access'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="system_access">System Access</label>
                            </div>
                        </div>
                    </div>

                    <div class="row edit-user-password-panel">
                        <div class="col-12 mb-4">
                            <div class="edit-user-password-grid">
                                <div class="edit-user-password-main">
                                    <label for="temporary_password" class="form-label">Update Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="temporary_password" name="temporary_password" placeholder="Leave blank to keep the current password unchanged">
                                        <button type="button" class="btn btn-outline-secondary password-toggle-btn" data-toggle-target="temporary_password" aria-label="Show or hide temporary password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="edit-user-password-options">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="use_default_password" name="use_default_password">
                                            <label class="form-check-label" for="use_default_password">Use default password (<?= htmlspecialchars($defaultTempPassword) ?>)</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="force_password_change" name="force_password_change">
                                            <label class="form-check-label" for="force_password_change">Force password change on next login</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="edit-user-password-side">
                                    <button type="button" class="btn btn-outline-primary" id="updatePasswordOnlyBtn">
                                        <i class="fas fa-key me-1"></i> Update Password
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center mt-4 mb-3">
                        <h3 class="mb-0">General Information</h3>
                        <button type="button" class="btn btn-sm btn-outline-info ms-auto" id="fetchHrmsDataBtn">
                            <i class="fas fa-sync-alt me-1"></i> Update from HRMS
                        </button>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="mobile" class="form-label">Mobile</label>
                            <input type="text" class="form-control" id="mobile" name="mobile" value="<?= htmlspecialchars($user_to_edit['mobile'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="hrms_id" class="form-label">Employee ID (EMP_CODE)</label>
                            <input type="text" class="form-control" id="hrms_id" name="hrms_id" value="<?= htmlspecialchars($user_to_edit['hrms_id'] ?? '') ?>" data-lookup-id="<?= htmlspecialchars($user_to_edit['hrms_id'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="hrms_status" class="form-label">Status (Live from HRMS)</label>
                            <input type="text" class="form-control" id="hrms_status" name="hrms_status_display" value="<?= htmlspecialchars($user_to_edit['hrms_status'] ?? 'Not available') ?>" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="designation" class="form-label">Designation</label>
                            <input type="text" class="form-control" id="designation" name="designation" value="<?= htmlspecialchars($user_to_edit['designation'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="designation_order" class="form-label">Designation Order</label>
                            <input type="text" class="form-control" id="designation_order" name="designation_order" value="<?= htmlspecialchars($user_to_edit['designation_order'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="operating_unit" class="form-label">Operating Unit</label>
                            <input type="text" class="form-control" id="operating_unit" name="operating_unit" value="<?= htmlspecialchars($user_to_edit['operating_unit'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="location" name="location" value="<?= htmlspecialchars($user_to_edit['location'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="department" class="form-label">Department</label>
                            <input type="text" class="form-control" id="department" name="department" value="<?= htmlspecialchars($user_to_edit['department'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="section" class="form-label">Section</label>
                            <input type="text" class="form-control" id="section" name="section" value="<?= htmlspecialchars($user_to_edit['section'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="product" class="form-label">Product</label>
                            <input type="text" class="form-control" id="product" name="product" value="<?= htmlspecialchars($user_to_edit['product'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="sub_section" class="form-label">Sub Section</label>
                            <input type="text" class="form-control" id="sub_section" name="sub_section" value="<?= htmlspecialchars($user_to_edit['sub_section'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="joining_date" class="form-label">Joining Date</label>
                            <input type="text" class="form-control" id="joining_date" name="joining_date" value="<?= htmlspecialchars($user_to_edit['joining_date'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="dob" class="form-label">Date of Birth</label>
                            <input type="text" class="form-control" id="dob" name="dob" value="<?= htmlspecialchars($user_to_edit['dob'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="gender" class="form-label">Gender</label>
                            <input type="text" class="form-control" id="gender" name="gender" value="<?= htmlspecialchars($user_to_edit['gender'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="age" class="form-label">Age</label>
                            <input type="text" class="form-control" id="age" name="age" value="<?= htmlspecialchars($user_to_edit['age'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="app-form-actions">
                        <button type="submit" class="btn btn-primary btn-modify-action"><i class="fas fa-save"></i> Submit</button>
                        <a href="<?= admin_page_url('user_management') ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                    </div>
                </form>
            </div>
        </div>
