<?php
// Canonical employee database view.
if (!defined('_CORE_ADMIN_')) {
    die('Direct access not permitted');
}

if (!has_permission('page_employee_db')) {
    include include_path('resources/views/pages/auth/unauthorized_view.php');
    return;
}

$employee_db_permissions = [
    'can_search' => has_permission('action_employee_search'),
    'can_add' => has_permission('action_add_employee'),
    'can_edit' => has_permission('action_edit_employee'),
    'can_delete' => has_permission('action_delete_employee'),
    'can_save' => has_permission('action_save_employee')
];
?>

<div class="page-header">
    <h1><i class="fas fa-database"></i> Employee Database</h1>
    <div class="header-actions">
        <div class="search-container">
            <input type="text" id="employeeSearchInput" class="form-control" placeholder="Search employees...">
            <?php if ($employee_db_permissions['can_search']): ?>
            <button id="employeeSearchButton" class="btn btn-primary" data-noc-tip="Search for employee records."><i class="fas fa-search"></i></button>
            <?php endif; ?>
        </div>
        <?php if ($employee_db_permissions['can_add']): ?>
        <button id="addEmployeeBtn" class="btn btn-success btn-sm px-3 fw-bold" data-noc-tip="Register a new employee."><i class="fas fa-plus"></i> Add Employee</button>
        <?php endif; ?>
    </div>
</div>

<?php if (has_permission('card_employee_db_table')): ?>
<div class="card mb-3">
    <div class="card-body">
        <div class="table-responsive">
            <table id="employeeTable" class="table app-data-table log-table table-hover">
                <thead>
                    <tr>
                        <th>EMP ID</th>
                        <th>EMP Code</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="9" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Add/Edit Modal -->
<div id="employeeModal" class="modal fade" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form id="employeeForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" data-noc-tip="Close modal."></button>
                </div>
                <div class="modal-body">
                    <!-- Form content will be loaded here by JavaScript -->
                    <p class="text-center">Loading form...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-noc-tip="Discard changes.">Cancel</button>
                    <?php if ($employee_db_permissions['can_save']): ?>
                    <button type="submit" class="btn btn-primary" data-noc-tip="Save employee record.">Save</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.employeeDbPermissions = <?= json_encode($employee_db_permissions) ?>;
</script>
