<?php
/**
 * Log Table Component for Dashboard
 */
$table_title = 'Action logs';
$table_icon = 'fa-history';
$badge_id = 'dashboard-log-success-percentage';
$badge_text = '0%';
$tbody_id = 'dashboard-detailed-logs-tbody';
$no_logs_id = 'dashboard-no-logs-message';
$no_logs_message = 'No activity logs found for the selected filters';
$show_header = $show_header ?? true;
// Safe initialization
$table_classes = '';
$header_extra = '';

include include_path('resources/views/components/global/ui_log_table.php');

// Strict cleanup
unset($table_title, $table_icon, $badge_id, $badge_text, $tbody_id, $no_logs_id, $no_logs_message, $show_header, $table_classes, $header_extra);
?>
