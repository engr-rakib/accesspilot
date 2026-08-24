<?php
if (!defined('_CORE_ADMIN_')) {
    die('Direct access not permitted');
}
// This is the view file for user management.
// It is included by indexpro.php.

// Page-level access control
if (!has_permission('page_user_management')) {
    include include_path('resources/views/pages/auth/unauthorized_view.php');
    return;
}

?>

<?php
// Create a permissions object for the frontend script (for Role Management)
$js_permissions = [
    'can_create' => has_permission('action_role_create'),
    'can_edit' => has_permission('action_role_edit'),
    'can_delete' => has_permission('action_role_delete'),
    'can_edit_user' => has_permission('action_usermgmt_edit'),
    'can_reset_user' => has_permission('action_usermgmt_reset'),
    'can_delete_user' => has_permission('action_usermgmt_delete')
];
?>
<script>
    // Pass permissions to the JavaScript file
    const userPermissions = <?= json_encode($js_permissions) ?>;

    document.addEventListener('DOMContentLoaded', function() {
        const roleModal = document.getElementById('roleModal');
        if (roleModal && roleModal.parentElement !== document.body) {
            document.body.appendChild(roleModal);
        }
    });
</script>

<style>
    .user-mgmt-action-cell .user-expand-btn,
    .user-mgmt-action-cell button.user-expand-btn {
        background: transparent !important;
        background-color: transparent !important;
        background-image: none !important;
        border: 1px solid rgba(0,0,0,0.12) !important;
        color: #6b7280 !important;
        box-shadow: none !important;
        width: 28px !important;
        height: 28px !important;
        min-width: 28px !important;
        min-height: 28px !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 8px !important;
        font-size: 0.72rem !important;
        line-height: 1 !important;
    }
    .user-mgmt-action-cell .user-expand-btn:hover,
    .user-mgmt-action-cell .user-expand-btn:focus,
    .user-mgmt-action-cell .user-expand-btn:active {
        background: transparent !important;
        background-color: transparent !important;
        background-image: none !important;
    }
</style>
<div class="user-management-content slide-in-top">

    <?php if (has_permission('card_roles_list')): ?>
    <div class="row gx-0 mb-0">
        <div class="col-12">
            <div class="card app-table-card" style="overflow: hidden !important;">
                <div class="card-body no-padding" style="padding: 0 !important; margin: 0 !important;">
                    <div class="log-title-wrapper app-table-title" style="margin: 0 !important; border-bottom: 1px solid rgba(15, 23, 42, 0.08) !important;">
                        <span><i class="fas fa-user-shield me-2"></i>Role Management</span>
                        <?php if ($js_permissions['can_create']): ?>
                        <button class="btn btn-primary btn-sm px-3 fw-bold" id="createNewRoleBtn" data-noc-tip="Define a new security role with custom permissions."><i class="fas fa-plus"></i> Create Role</button>
                        <?php endif; ?>
                    </div>
                    <div class="log-table-wrapper app-table-wrapper">
                        <table class="table app-data-table log-table mb-0 table-hover role-management-table">
                            <colgroup>
                                <col style="width: 28%;">
                                <col style="width: 58%;">
                                <col style="width: 14%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Role Name</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rolesTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('card_pending_requests')): ?>
    <div class="row gx-0 mb-0">
        <div class="col-12">
            <div class="card app-table-card" style="overflow: hidden !important;">
                <div class="card-body no-padding" style="padding: 0 !important; margin: 0 !important;">
                    <div class="log-title-wrapper app-table-title" style="margin: 0 !important; border-bottom: 1px solid rgba(15, 23, 42, 0.08) !important;">
                        <span><i class="fas fa-user-clock me-2"></i>Pending Registration Requests</span>
                        <?php if (has_permission('action_usermgmt_create')): ?>
                        <a href="<?= admin_page_url('create_user') ?>" class="btn btn-primary btn-sm px-3 fw-bold" data-noc-tip="Manually provision a new administrative user account.">
                            <i class="fas fa-plus"></i> Create User
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="log-table-wrapper app-table-wrapper">
                        <table class="table app-data-table log-table mb-0 table-hover pending-requests-table">
                            <colgroup>
                                <col style="width: 16%;"><col style="width: 18%;"><col style="width: 28%;"><col style="width: 22%;"><col style="width: 16%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>HRMS ID</th><th>Username</th><th>Email</th><th>Timestamp</th><th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="pending-registration-tbody">
                                <?php if (empty($registration_requests)): ?>
                                    <tr><td colspan="5" class="app-empty-cell text-center py-4"><i class="fas fa-info-circle me-2"></i>No pending registration requests.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($registration_requests as $index => $request): ?>
                                    <tr class="app-row-pulse">
                                        <td><?= htmlspecialchars($request['hrms_id']) ?></td>
                                        <td><?= htmlspecialchars($request['username']) ?></td>
                                        <td><?= htmlspecialchars($request['email']) ?></td>
                                        <td><?= htmlspecialchars($request['timestamp']) ?></td>
                                        <td class="user-mgmt-action-cell">
                                            <?php if (has_permission('action_usermgmt_approve_deny')): ?>
                                            <div class="app-action-buttons">
                                                <button type="button" class="btn btn-icon btn-success btn-sm approve-btn" data-bs-toggle="modal" data-bs-target="#approveUserModal" data-index="<?= $index ?>" data-noc-tip="Approve Request"><i class="fas fa-check"></i></button>
                                                <button type="button" class="btn btn-icon btn-danger btn-sm deny-btn" data-index="<?= $index ?>" data-noc-tip="Deny Request"><i class="fas fa-times"></i></button>
                                            </div>
                                            <?php else: ?><span>No permission</span><?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php
    $reset_requests = repo_read_password_reset_requests();
    $pending_reset_requests = array_filter($reset_requests, function($r) { return ($r['status'] ?? '') === 'pending'; });
    if (has_permission('card_pending_requests')): ?>
    <div class="row gx-0 mb-0">
        <div class="col-12">
            <div class="card app-table-card" style="overflow: hidden !important;">
                <div class="card-body no-padding" style="padding: 0 !important; margin: 0 !important;">
                    <div class="log-title-wrapper app-table-title" style="margin: 0 !important; border-bottom: 1px solid rgba(15, 23, 42, 0.08) !important;">
                        <span><i class="fas fa-key me-2"></i>User profile password Reset requests</span>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" id="bulkResetBtn" class="btn btn-warning btn-sm app-hidden" data-bs-toggle="modal" data-bs-target="#resetPasswordModal"><i class="fas fa-key me-1"></i> Reset Selected</button>
                        </div>
                    </div>
                    <div class="log-table-wrapper app-table-wrapper">
                        <table class="table app-data-table log-table mb-0 reset-requests-table">
                            <colgroup>
                                <col style="width: 4%;"><col style="width: 6%;"><col style="width: 12%;"><col style="width: 17%;"><col style="width: 21%;"><col style="width: 24%;"><col style="width: 16%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th style="width:40px;text-align:center;"><input type="checkbox" id="selectAllResetRequests" class="form-check-input"></th>
                                    <th style="width:40px;text-align:center;">SL</th>
                                    <th style="width:100px;">Logon ID</th><th>Name</th><th>Reason</th><th>Timestamp</th>
                                    <th class="text-end" style="width:120px;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="password-reset-requests-tbody">
                                <?php if (empty($pending_reset_requests)): ?>
                                    <tr><td colspan="7" class="app-empty-cell text-center py-4"><i class="fas fa-info-circle me-2"></i>No pending password reset requests.</td></tr>
                                <?php else: ?>
                                    <?php $sl = 1; foreach ($pending_reset_requests as $index => $request): ?>
                                    <tr class="app-row-pulse">
                                        <td class="text-center"><input type="checkbox" class="form-check-input reset-request-check" data-username="<?= htmlspecialchars($request['username']) ?>" data-request-index="<?= $index ?>"></td>
                                        <td class="text-center"><?= $sl++ ?></td>
                                        <td><strong><?= htmlspecialchars($request['username']) ?></strong></td>
                                        <td><?= htmlspecialchars($request['full_name'] ?? 'N/A') ?></td>
                                        <td><small><?= htmlspecialchars($request['reason'] ?? 'N/A') ?></small></td>
                                        <td><?= date('Y-m-d h:i:s A', strtotime($request['timestamp'])) ?></td>
                                        <td class="user-mgmt-action-cell">
                                            <div class="app-action-buttons">
                                                <button type="button" class="btn btn-icon btn-warning btn-sm reset-request-approve-btn" data-bs-toggle="modal" data-bs-target="#resetPasswordModal" data-username="<?= htmlspecialchars($request['username']) ?>" data-request-index="<?= $index ?>" title="Approve & Reset"><i class="fas fa-key"></i></button>
                                                <button type="button" class="btn btn-icon btn-danger btn-sm reset-request-deny-btn" data-request-index="<?= $index ?>" title="Deny Request"><i class="fas fa-times"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('card_existing_users')): ?>
    <div class="row gx-0 mb-0">
        <div class="col-12">
            <div class="card app-table-card" style="overflow: hidden !important;">
                <div class="card-body no-padding" style="padding: 0 !important; margin: 0 !important;">
                    <div class="log-title-wrapper app-table-title" style="margin: 0 !important; border-bottom: 1px solid rgba(15, 23, 42, 0.08) !important;">
                        <span><i class="fas fa-users me-2"></i>Existing Users</span>
                        <div class="d-flex align-items-center gap-2 app-header-actions">
                            <div class="input-group input-group-sm app-search-group user-mgmt-search-group" style="flex-wrap: nowrap;">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" id="userSearchInput" class="form-control border-start-0 ps-0" placeholder="Search users..." style="font-size:0.85rem;">
                            </div>
                        </div>
                    </div>
                    <div class="log-table-wrapper app-table-wrapper" style="overflow-x:auto;">
                        <table class="table app-data-table log-table mb-0 table-hover existing-users-table">
                            <thead>
                                <tr>
                                    <th class="text-center user-col-index">#</th>
                                    <th class="user-col-logon">Logon ID</th>
                                    <th class="user-col-name">Name</th>
                                    <th class="user-col-email">Email</th>
                                    <th class="user-col-mobile">Mobile</th>
                                    <th class="user-col-role">Role</th>
                                    <th class="text-center user-col-status">Status</th>
                                    <th class="text-center user-col-access">Access</th>
                                    <th class="text-end user-col-action">Action</th>
                                </tr>
                            </thead>
                            <tbody id="existing-users-tbody">
                                <?php 
                                if (!empty($users)) ksort($users);
                                $sl = 1;
                                foreach (($users ?? []) as $username => $user): 
                                ?>
                                    <tr data-username="<?= htmlspecialchars($username) ?>">
                                        <td class="text-center user-col-index"><?= $sl++ ?></td>
                                        <td class="user-col-logon"><strong><?= htmlspecialchars($username) ?></strong></td>
                                        <td class="user-col-name"><?= htmlspecialchars($user['full_name'] ?? 'N/A') ?></td>
                                        <td class="user-col-email"><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                                        <td class="font-tech user-col-mobile"><?= htmlspecialchars($user['mobile'] ?? '-') ?></td>
                                        <td class="user-col-role"><small><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role'] ?? 'user'))) ?></small></td>
                                        <td class="text-center user-col-status">
                                            <span class="user-status-indicator">
                                                <span class="user-status-dot" data-user="<?= htmlspecialchars($username) ?>" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#6b7280;"></span>
                                                <span class="user-status-text text-muted">Offline</span>
                                            </span>
                                        </td>
                                        <td class="text-center user-col-access"><?= ($user['system_access'] ?? false) ? '<span class="text-success fw-bold">ON</span>' : '<span class="text-danger fw-bold">OFF</span>' ?></td>
                                        <td class="user-mgmt-action-cell">
                                            <div class="app-action-buttons">
                                                <button type="button" class="btn btn-icon btn-sm user-expand-btn" style="background:transparent;border:1px solid rgba(0,0,0,0.12);color:#6b7280;" data-user="<?= htmlspecialchars($username) ?>" title="Details"><i class="fas fa-chevron-down"></i></button>
                                                <?php if (has_permission('action_usermgmt_edit')): ?>
                                                    <a href="<?= admin_page_url('edit_user', ['username' => $username]) ?>" class="btn btn-icon btn-primary btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
                                                <?php endif; ?>
                                                <?php if (has_permission('action_usermgmt_reset')): ?>
                                                    <button type="button" class="btn btn-icon btn-warning btn-sm reset-password-btn" data-bs-toggle="modal" data-bs-target="#resetPasswordModal" data-username="<?= htmlspecialchars($username) ?>" title="Reset Password"><i class="fas fa-key"></i></button>
                                                <?php endif; ?>
                                                <?php if (has_permission('action_usermgmt_delete')): ?>
                                                    <button type="button" class="btn btn-icon btn-danger btn-sm delete-btn" data-username="<?= htmlspecialchars($username) ?>" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="user-detail-row" data-user="<?= htmlspecialchars($username) ?>" style="display:none;">
                                        <td colspan="9" style="padding:0 !important;">
                                            <div style="padding:10px 16px;background:rgba(var(--primary-rgb,24,53,147),0.03);border-bottom:1px solid var(--border-color,rgba(0,0,0,0.06));position:relative;">
                                                <div class="row g-3">
                                                    <div class="col-md-12">
                                                            <div style="display:flex;align-items:center;justify-content:space-between;">
                                                                <span class="text-muted" style="font-size:0.72rem;">Activity Log (last 20)</span>
                                                            </div>
                                                        <div class="user-activity-list" style="max-height:200px;overflow-y:auto;margin-top:4px;font-size:0.8rem;">
                                                            <table style="width:100%;border-collapse:collapse;">
                                                                <thead>
                                                                    <tr style="border-bottom:1px solid rgba(0,0,0,0.08);">
                                                                        <th style="text-align:left;padding:3px 6px;font-weight:600;color:#6b7280;font-size:0.7rem;white-space:nowrap;">Time</th>
                                                                        <th style="text-align:left;padding:3px 6px;font-weight:600;color:#6b7280;font-size:0.7rem;white-space:nowrap;">Action</th>
                                                                        <th style="text-align:left;padding:3px 6px;font-weight:600;color:#6b7280;font-size:0.7rem;white-space:nowrap;">Details</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="user-activity-tbody">
                                                                    <tr><td colspan="3" style="padding:6px;color:#6b7280;" class="user-last-active">Loading...</td></tr>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                                <hr style="margin:6px 0;opacity:0.15;">
                                                <div class="row g-3">
                                                    <div class="col-md-3"><span class="text-muted" style="font-size:0.72rem;">Created</span><br><span class="font-tech" style="font-size:0.85rem;"><?= htmlspecialchars($user['created_at'] ?? date('Y-m-d')) ?></span></div>
                                                    <div class="col-md-3"><span class="text-muted" style="font-size:0.72rem;">Role Permissions</span><br><span style="font-size:0.82rem;"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role'] ?? 'user'))) ?> level</span></div>
                                                    <div class="col-md-3"><span class="text-muted" style="font-size:0.72rem;">Preferences</span><br><span style="font-size:0.82rem;">Theme: <?= htmlspecialchars($user['preferences']['theme'] ?? 'default') ?></span></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Modal for Create/Edit Role -->
<div class="modal fade" id="roleModal" tabindex="-1" aria-labelledby="roleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="roleModalLabel">Create Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="roleForm">
                    <input type="hidden" id="roleNameHidden" name="original_role_name">
                    <div class="mb-3">
                        <label for="roleName" class="form-label">Role Name</label>
                        <input type="text" class="form-control" id="roleName" name="role_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="roleDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="roleDescription" name="description" rows="2"></textarea>
                    </div>
                    <hr>
                    <h5>Permissions</h5>
                    <div id="permissionsContainer" class="container-fluid">
                        <!-- Permissions will be loaded here -->
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveRoleBtn" data-noc-tip="Persist role configuration.">Save Role</button>
            </div>
        </div>
    </div>
</div>

<?php
// Flash message display logic
if (isset($_SESSION['flash_message'])):
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const actionTakenCardContainer = document.getElementById('actionTakenCardContainer');
    const actionTakenTitleSpan = document.getElementById('actionTakenTitle');
    const actionTakenMessageDisplay = document.getElementById('actionTakenMessageDisplay');
    if (actionTakenCardContainer && actionTakenTitleSpan && actionTakenMessageDisplay) {
        const message = <?= json_encode($_SESSION['flash_message']) ?>;
        const isSuccess = <?= json_encode($_SESSION['flash_is_success']) ?>;

        actionTakenCardContainer.classList.add('visible');
        actionTakenCardContainer.style.display = 'block';
        actionTakenCardContainer.classList.remove('slide-in-bottom');

        actionTakenTitleSpan.textContent = isSuccess ? 'Success' : 'Error';
        actionTakenMessageDisplay.className = isSuccess ? 'alert alert-success text-start' : 'alert alert-danger text-start';
        actionTakenMessageDisplay.innerHTML = message;

        // Auto-hide after 20 seconds
        setTimeout(() => {
            actionTakenCardContainer.style.display = 'none';
            actionTakenCardContainer.classList.remove('visible');
        }, 20000); 
    }
});
</script>
<?php
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_is_success']);
endif;
?>
