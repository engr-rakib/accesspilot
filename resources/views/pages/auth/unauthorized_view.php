<?php
if (!defined('_CORE_ADMIN_')) {
    die('Direct access not permitted');
}
?>
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm text-center mt-5">
                <div class="card-body">
                    <h1 class="display-1 text-danger"><i class="fas fa-hand-paper"></i></h1>
                    <h2 class="card-title">Access Denied</h2>
                    <p class="card-text">You do not have the necessary permissions to view this page.</p>
                    <p class="card-text">Please contact an administrator if you believe this is an error.</p>
                    <a href="<?= admin_page_url('dashboard') ?>" class="btn btn-primary mt-3">Return to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</div>
