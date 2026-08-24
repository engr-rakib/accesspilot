<?php
if (!defined('_CORE_ADMIN_')) {
    die('Direct access not permitted');
}
// This is the view file for creating a user manually.
// It is included by indexpro.php.

// Page-level access control
if (!has_permission('user_create')) {
    include include_path('resources/views/pages/auth/unauthorized_view.php');
    return;
}

$defaultTempPassword = config_get('default_password', '');
?>
<div class="card mb-3 slide-in-bottom" id="actionTakenCardContainer">
    <div class="card-header d-flex align-items-center">
        <h3 class="mb-0">Action Taken: <span id="actionTakenTitle"></span></h3>
    </div>
    <div class="card-body">
        <div id="actionTakenMessageDisplay" class="alert"></div>
    </div>
</div>

<?php
if (isset($_SESSION['flash_message'])):
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const actionTakenCardContainer = document.getElementById('actionTakenCardContainer');
    const actionTakenTitleSpan = document.getElementById('actionTakenTitle');
    const actionTakenMessageDisplay = document.getElementById('actionTakenMessageDisplay');
    const message = <?= json_encode($_SESSION['flash_message']) ?>;
    const isSuccess = <?= json_encode($_SESSION['flash_is_success']) ?>;

    if (actionTakenCardContainer) {
        actionTakenCardContainer.style.display = 'block';
        actionTakenTitleSpan.textContent = isSuccess ? 'Success' : 'Error';
        actionTakenMessageDisplay.innerHTML = message;
        actionTakenMessageDisplay.className = isSuccess ? 'alert alert-success' : 'alert alert-danger';
    }
});
</script>
<?php
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_is_success']);
endif;
?>
<div class="card mb-3 slide-in-top">
    <div class="card-header">
        <h3><i class="fas fa-user-plus"></i> Create New User</h3>
    </div>
    <div class="card-body">
        <form id="createUserForm" method="POST" action="<?= admin_page_url('create_user') ?>">
            <input type="hidden" name="action" value="manual_create_user">
            
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="create_from_hrms_check" name="create_from_hrms">
                <label class="form-check-label" for="create_from_hrms_check">Create from HRMS portal</label>
            </div>

            <div id="manual_fields_container">
                <div class="row">
                    <div class="col-md-4 mb-3" id="username_wrapper">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="col-md-4 mb-3" id="full_name_wrapper">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name">
                    </div>
                    <div class="col-md-4 mb-3" id="role_wrapper">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role">
                            <?php 
                                global $app_config; // Use the global config from indexpro.php
                                $roles_data = repo_read_roles();
                                if (!empty($roles_data)) {
                                    foreach ($roles_data as $role_name => $role_details) {
                                        $selected = ($role_name === 'user') ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($role_name) . '" ' . $selected . '>' . htmlspecialchars(ucfirst(str_replace('_', ' ', $role_name))) . '</option>';
                                    }
                                } else {
                                    echo '<option value="">Error: roles repository is empty or unreadable.</option>';
                                }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-8 mb-3" id="email_wrapper">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="col-md-4 mb-3 align-self-center" id="system_access_wrapper">
                        <div class="form-check mt-4">
                            <input type="checkbox" class="form-check-input" id="system_access" name="system_access" checked>
                            <label class="form-check-label" for="system_access">System Access</label>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3 align-self-center" id="enable_mailbox_wrapper">
                        <div class="form-check mt-4">
                            <input type="checkbox" class="form-check-input" id="enable_mailbox" name="enable_mailbox">
                            <label class="form-check-label" for="enable_mailbox"><i class="fas fa-envelope me-1"></i>Enable Mailbox</label>
                        </div>
                    </div>
                </div>

                <hr id="general_info_hr">

                <h3 class="mt-4" id="general_info_h3">General Information</h3>
                <div class="row">
                    <div class="col-md-6 mb-3" id="mobile_wrapper">
                        <label for="mobile" class="form-label">Mobile</label>
                        <input type="text" class="form-control" id="mobile" name="mobile">
                    </div>
                    <div class="col-md-6 mb-3" id="hrms_id_wrapper">
                        <label for="hrms_id" class="form-label">Employee ID (EMP_CODE)</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="hrms_id" name="hrms_id">
                            <button type="button" class="btn btn-outline-info" id="fetchCreateHrmsDataBtn">
                                <i class="fas fa-cloud-download-alt me-1"></i> Fetch
                            </button>
                        </div>
                        <div class="form-text" id="hrms_fetch_hint">Manual mode-e HRMS ID diye auto-fill korte `Fetch` use koro.</div>
                    </div>
                    <div class="col-md-6 mb-3" id="designation_wrapper">
                        <label for="designation" class="form-label">Designation</label>
                        <input type="text" class="form-control" id="designation" name="designation">
                    </div>
                    <div class="col-md-6 mb-3" id="designation_order_wrapper">
                        <label for="designation_order" class="form-label">Designation Order</label>
                        <input type="text" class="form-control" id="designation_order" name="designation_order">
                    </div>
                    <div class="col-md-6 mb-3" id="operating_unit_wrapper">
                        <label for="operating_unit" class="form-label">Operating Unit</label>
                        <input type="text" class="form-control" id="operating_unit" name="operating_unit">
                    </div>
                    <div class="col-md-6 mb-3" id="location_wrapper">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="location" name="location">
                    </div>
                    <div class="col-md-6 mb-3" id="department_wrapper">
                        <label for="department" class="form-label">Department</label>
                        <input type="text" class="form-control" id="department" name="department">
                    </div>
                    <div class="col-md-6 mb-3" id="section_wrapper">
                        <label for="section" class="form-label">Section</label>
                        <input type="text" class="form-control" id="section" name="section">
                    </div>
                    <div class="col-md-6 mb-3" id="product_wrapper">
                        <label for="product" class="form-label">Product</label>
                        <input type="text" class="form-control" id="product" name="product">
                    </div>
                    <div class="col-md-6 mb-3" id="sub_section_wrapper">
                        <label for="sub_section" class="form-label">Sub Section</label>
                        <input type="text" class="form-control" id="sub_section" name="sub_section">
                    </div>
                    <div class="col-md-6 mb-3" id="joining_date_wrapper">
                        <label for="joining_date" class="form-label">Joining Date</label>
                        <input type="text" class="form-control" id="joining_date" name="joining_date">
                    </div>
                    <div class="col-md-6 mb-3" id="dob_wrapper">
                        <label for="dob" class="form-label">Date of Birth</label>
                        <input type="text" class="form-control" id="dob" name="dob">
                    </div>
                    <div class="col-md-6 mb-3" id="gender_wrapper">
                        <label for="gender" class="form-label">Gender</label>
                        <input type="text" class="form-control" id="gender" name="gender">
                    </div>
                    <div class="col-md-6 mb-3" id="age_wrapper">
                        <label for="age" class="form-label">Age</label>
                        <input type="text" class="form-control" id="age" name="age">
                    </div>
                </div>
            </div> <!-- End manual_fields_container -->

            <p class="text-muted">
                Default temporary password: <strong><?= htmlspecialchars($defaultTempPassword) ?></strong>
            </p>

            <hr>

            <div class="app-form-actions">
            <?php if (has_permission('manual_create_user')): ?>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Submit</button>
            <?php else: ?>
            <div class="alert alert-warning">
                You can view this page, but your role does not currently allow submitting manual user-creation requests.
            </div>
            <?php endif; ?>
            <a href="<?= admin_page_url('user_management') ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>
