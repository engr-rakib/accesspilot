<?php
if (!defined('_CORE_ADMIN_')) {
    die('Direct access not permitted');
}

// Page-level access control
if (!has_permission('page_role_management')) {
    include include_path('resources/views/pages/auth/unauthorized_view.php');
    return;
}

$role_name_to_edit = $_GET['role_name'] ?? '';
$is_edit = !empty($role_name_to_edit);
$can_manage_role = $is_edit ? has_permission('action_role_edit') : has_permission('action_role_create');
$can_add_role_member = has_permission('action_role_add_member');
$can_remove_role_member = has_permission('action_role_remove_member');

if (!$can_manage_role) {
    include include_path('resources/views/pages/auth/unauthorized_view.php');
    return;
}

// Fetch existing role data if editing
$role_data = null;
if ($is_edit) {
    $all_roles = repo_read_roles();
    $role_data = $all_roles[$role_name_to_edit] ?? null;
}

if ($is_edit && !$role_data) {
    echo '<div class="alert alert-danger">Role not found.</div>';
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
        <h3><i class="fas fa-user-shield"></i> <?= $is_edit ? 'Edit Role: ' . htmlspecialchars($role_name_to_edit) : 'Create New Role' ?></h3>
    </div>
    <div class="card-body">
        <form id="roleFormPage" data-config='<?= json_encode(['is_edit' => $is_edit, 'role_name' => $role_name_to_edit, 'permissions' => $role_data['permissions'] ?? [], 'can_add_member' => $can_add_role_member, 'can_remove_member' => $can_remove_role_member]) ?>'>
            <input type="hidden" id="roleNameHidden" name="original_role_name" value="<?= htmlspecialchars($role_name_to_edit) ?>">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="roleName" class="form-label">Role Name</label>
                    <input type="text" class="form-control" id="roleName" name="role_name" value="<?= htmlspecialchars($role_name_to_edit) ?>" <?= ($is_edit && $role_name_to_edit === 'core_admin') ? 'disabled' : '' ?> required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="roleDescription" class="form-label">Description</label>
                    <textarea class="form-control" id="roleDescription" name="description" rows="1"><?= htmlspecialchars($role_data['description'] ?? '') ?></textarea>
                </div>
            </div>

            <hr>
            <h5>Permissions</h5>
            <div id="permissionsContainer" class="container-fluid mb-4">
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading permissions...</span>
                    </div>
                </div>
            </div>

            <?php if ($is_edit): ?>
            <hr>
            <div class="d-flex align-items-center mb-3">
                <h5 class="mb-0">Role Members</h5>
                <?php if ($can_add_role_member): ?>
                <button type="button" class="btn btn-sm btn-outline-success ms-auto" id="showAddMemberBtn">
                    <i class="fas fa-user-plus me-1"></i> Add Member
                </button>
                <?php endif; ?>
            </div>
            
            <div id="addMemberForm" class="card bg-light mb-3" style="display: none;">
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-9">
                            <label for="memberSelect" class="form-label">Select User to Add</label>
                            <select id="memberSelect" class="form-select">
                                <option value="">Loading users...</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <?php if ($can_add_role_member): ?>
                            <button type="button" class="btn btn-success w-100" id="confirmAddMemberBtn">Add</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div id="membersContainer" class="table-responsive">
                <table class="table app-data-table log-table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody id="membersTableBody">
                        <tr>
                            <td colspan="4" class="text-center">Loading members...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="app-form-actions">
                <button type="submit" class="btn btn-primary" id="saveRoleBtn">
                    <i class="fas fa-save me-1"></i> Submit
                </button>
                <a href="<?= admin_page_url('user_management') ?>" class="btn btn-secondary">
                    <i class="fas fa-times me-1"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// We use a small inline script to trigger the re-initialization of the role management logic
// but specifically for this form view.
if (typeof window.initRoleForm === 'function') {
    window.initRoleForm(<?= json_encode(['is_edit' => $is_edit, 'role_name' => $role_name_to_edit, 'permissions' => $role_data['permissions'] ?? [], 'can_add_member' => $can_add_role_member, 'can_remove_member' => $can_remove_role_member]) ?>);
} else {
    // Fallback if the main script isn't loaded yet
    console.error('Role form initialization function not found.');
}
</script>
