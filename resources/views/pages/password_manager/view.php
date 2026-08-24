<?php
if (!defined('_CORE_ADMIN_')) {
    die('Direct access not permitted');
}

if (!has_permission('page_password_manager')) {
    include include_path('resources/views/pages/auth/unauthorized_view.php');
    return;
}
?>

<?php if (has_permission('card_password_manager')): ?>
<div class="row gx-0 mb-0">
    <div class="col-12">
        <div class="card app-table-card slide-in-top" style="overflow:hidden !important;">
            <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                <h3 class="log-title-wrapper app-table-title" style="padding-left:1rem !important;padding-right:1rem !important;margin:0 !important;border-bottom:1px solid rgba(15,23,42,0.08) !important;">
                    <span><i class="fas fa-key me-2"></i>My Passwords</span>
                    <?php if (has_permission('action_password_create')): ?>
                    <button class="btn btn-primary btn-sm px-3 fw-bold" id="createNewPasswordBtn" data-noc-tip="Store a new encrypted credential in the secure vault.">
                        <i class="fas fa-plus"></i> Create New
                    </button>
                    <?php endif; ?>
                </h3>
                <div class="password-table-wrap" style="padding:0">
                    <table class="table table-hover app-data-table log-table mb-0" id="passwordTable">
                        <thead>
                            <tr>
                                <th>System Name</th>
                                <?php if (has_permission('action_password_view_all')): ?>
                                    <th>Creator</th>
                                <?php endif; ?>
                                <th>Owner</th>
                                <th>Type</th>
                                <th>ID</th>
                                <th style="min-width:110px">Password</th>
                                <th>IP</th>
                                <th>URL</th>
                                <th>Remarks</th>
                                <th style="width:110px">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="passwordTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (has_permission('page_global_passwords')): ?>
<div class="row gx-0 mb-0">
    <div class="col-12">
        <div class="card app-table-card slide-in-top" style="overflow:hidden !important;">
            <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                <h3 class="log-title-wrapper app-table-title" style="padding-left:1rem !important;padding-right:1rem !important;margin:0 !important;border-bottom:1px solid rgba(15,23,42,0.08) !important;">
                    <span><i class="fas fa-globe-americas me-2"></i>Global Passwords</span>
                </h3>
                <div class="password-table-wrap" style="padding:0">
                    <table class="table table-hover app-data-table log-table mb-0" id="globalPasswordTable">
                        <thead>
                            <tr>
                                <th>System Name</th>
                                <th>Creator</th>
                                <th>Owner</th>
                                <th>Type</th>
                                <th>ID</th>
                                <th style="min-width:110px">Password</th>
                                <th>IP</th>
                                <th>URL</th>
                                <th>Remarks</th>
                                <th style="width:110px">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="globalPasswordTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
