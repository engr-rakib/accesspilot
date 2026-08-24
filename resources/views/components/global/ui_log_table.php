<?php
/**
 * Reusable Log Table Component
 * 
 * Enforces a strict no-horizontal-scroll policy and fluid layout.
 */
$headers = $headers ?? ['Timestamp', 'Domain', 'Action', 'Target User', 'Category', 'Operator', 'Message', 'Status'];
$show_header = $show_header ?? true;
?>

<div class="log-container" style="padding: 0 !important; margin: 0 !important; width: 100% !important; display: block !important;">
    <?php if ($show_header): ?>
    <div class="log-title-wrapper app-table-title" style="margin: 0 !important; border-bottom: 1px solid rgba(15, 23, 42, 0.08) !important;">
        <span><i class="fas <?php echo $table_icon ?? 'fa-history'; ?> me-2"></i><?php echo $table_title ?? 'Action logs'; ?></span>
        <div class="d-flex align-items-center gap-2">
            <?php if (isset($header_extra)) echo $header_extra; ?>
            <?php if (!empty($badge_id)): ?>
            <span id="<?php echo $badge_id; ?>" class="badge rounded-pill fs-6" style="color: white; background-color: #6c757d;">
                <?php echo $badge_text ?? 'N/A'; ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <div class="log-table-wrapper app-table-wrapper">
        <table class="table app-data-table log-table mb-0 <?php echo $table_classes ?? ''; ?>">
            <thead>
                <tr>
                    <?php foreach ($headers as $header): ?>
                        <th><?php echo $header; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody id="<?php echo $tbody_id ?? ''; ?>">
                <!-- Content injected by JavaScript -->
            </tbody>
        </table>
    </div>
    <div class="no-logs" id="<?php echo $no_logs_id ?? ''; ?>" style="display: none; padding: 20px; text-align: center; color: #666; background: transparent;">
        <i class="fas fa-info-circle"></i>
        <p><?php echo $no_logs_message ?? 'No activity logs found'; ?></p>
    </div>
</div>
