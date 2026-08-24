<?php include include_path('resources/views/components/ad_user_request/admin_card.php'); ?>

<?php if (has_permission('card_recent_activity')): ?>
    <div class="row gx-0" id="log-card-row">
        <div class="col-12">
            <?php
            // Prepare the inner content of the card (The Log Table)
            ob_start();
            $table_title = 'Action logs';
            $table_icon = 'fa-history';
            $badge_id = 'log-success-percentage';
            $badge_text = 'N/A';
            $tbody_id = 'detailed-logs-tbody';
            $no_logs_id = 'no-logs-message';
            $no_logs_message = 'No activity logs found';
            // Ensure no specialized classes or header extras leak
            $table_classes = ''; 
            $header_extra = '';

            include include_path('resources/views/components/global/ui_log_table.php');
            $card_content = ob_get_clean();

            // Wrap it in the standard card
            $card_id = 'recent-activity-card';
            $card_classes = 'slide-in-bottom recent-activity-card app-table-card';
            include include_path('resources/views/components/global/ui_card.php');

            // Strict cleanup
            unset($table_title, $table_icon, $badge_id, $badge_text, $tbody_id, $no_logs_id, $no_logs_message, $table_classes, $header_extra, $card_content, $card_id, $card_classes);
            ?>
        </div>
    </div>
<?php endif; ?>
