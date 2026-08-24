<?php
if (!defined('_CORE_ADMIN_')) {
    die('Direct access not permitted');
}

if (!has_permission('card_pending_requests')) {
    return;
}
?>


<div class="row gx-0 mb-0" id="ad-user-requests-row">
    <div class="col-12">
        <?php
        // 1. Prepare Header Actions
        ob_start();
        ?>
        <button type="button" id="bulkApproveAdRequestsBtn" class="btn btn-sm btn-success" style="display:none;" data-noc-tip="Approve selected requests">
            <i class="fas fa-play"></i>
        </button>
        <button type="button" id="bulkDenyAdRequestsBtn" class="btn btn-sm btn-danger" style="display:none;" data-noc-tip="Deny selected requests">
            <i class="fas fa-times"></i>
        </button>
        <a href="<?= route_url('request_portal.php') ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="https://<?= $_SERVER['HTTP_HOST'] ?>/request_portal.php">
            <i class="fas fa-up-right-from-square me-1"></i> Request Portal
        </a>
        <?php
        $_local_header_extra = ob_get_clean();

        // 2. Configure Component
        ob_start();
        $table_title = 'User Requests';
        $table_icon = 'fa-user-clock';
        $badge_id = '';
        $header_extra = $_local_header_extra;
        $tbody_id = 'ad-user-requests-tbody';
        $headers = [
            '<input type="checkbox" id="selectAllAdUserRequests" class="form-check-input">',
            'Time',
            'Type',
            'Target',
            'Requester',
            'Details',
            'Action'
        ];
        $table_classes = 'table-hover ad-user-requests-table';
        $no_logs_id = 'ad-user-requests-empty-msg';
        $no_logs_message = 'No pending user requests.';
        
        include include_path('resources/views/components/global/ui_log_table.php');
        $_local_card_content = ob_get_clean();

        // 3. Render Card
        $card_id = ''; 
        $card_classes = 'slide-in-bottom ad-user-requests-card app-table-card';
        $card_content = $_local_card_content;
        include include_path('resources/views/components/global/ui_card.php');

        // Cleanup
        unset($table_title, $table_icon, $badge_id, $header_extra, $tbody_id, $headers, $table_classes, $no_logs_id, $no_logs_message, $card_content, $card_id, $card_classes, $_local_header_extra, $_local_card_content);
        ?>
    </div>
</div>
