<div class="dashboard-content slide-in-top">
    <!-- AD Activity Charts Section -->
    <div class="row dashboard-equal-row">
        <?php if (has_permission('card_dashboard_today_log')): ?>
        <div class="col-lg-4 dashboard-card-col">
            <?php $todayLogChartCanvasId = 'dashboardTodayLogChart'; include __DIR__ . '/../../components/dashboard/chart_today_log.php'; unset($todayLogChartCanvasId); ?>
        </div>
        <?php endif; ?>
        
        <?php if (has_permission('card_dashboard_weekly_logs')): ?>
        <div class="col-lg-4 dashboard-card-col">
            <?php include __DIR__ . '/../../components/dashboard/chart_weekly_logs.php'; ?>
        </div>
        <?php endif; ?>
        
        <?php if (has_permission('card_dashboard_monthly_activity')): ?>
        <div class="col-lg-4 dashboard-card-col">
            <?php include __DIR__ . '/../../components/dashboard/chart_monthly_activity.php'; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Filtered Analysis Section -->
    <div class="row dashboard-equal-row">
        <?php if (has_permission('card_dashboard_action_status')): ?>
        <div class="col-lg-4 dashboard-card-col">
            <?php include __DIR__ . '/../../components/dashboard/chart_action_status_breakdown.php'; ?>
        </div>
        <?php endif; ?>
        
        <?php if (has_permission('card_dashboard_status_breakdown')): ?>
        <div class="col-lg-4 dashboard-card-col">
            <?php include __DIR__ . '/../../components/dashboard/reusable_status_breakdown.php'; ?>
        </div>
        <?php endif; ?>
        
        <?php if (has_permission('card_dashboard_top_users')): ?>
        <div class="col-lg-4 dashboard-card-col">
            <?php include __DIR__ . '/../../components/dashboard/widget_top_users.php'; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if (has_permission('card_dashboard_filter_bar') || has_permission('card_dashboard_log_table') || has_permission('card_recent_activity')): ?>
    <div class="row gx-0">
        <div class="col-12">
            <?php
            // Prepare the unified content: Header -> Filters -> Table
            ob_start();
            ?>
            <div class="log-title-wrapper app-table-title">
                <span><i class="fas fa-history me-2"></i>Action logs <span id="dashboard-domain-badge" class="badge rounded-pill ms-1" style="background-color:#475569;color:#fff;font-size:0.7rem;vertical-align:middle;">&nbsp;</span></span>
                <div class="d-flex align-items-center gap-2">
                    <span id="dashboard-log-success-percentage" class="badge rounded-pill fs-6" style="color: white; background-color: #6c757d;">0%</span>
                </div>
            </div>
            <?php
            if (has_permission('card_dashboard_filter_bar')) {
                include __DIR__ . '/../../components/dashboard/filter_bar.php';
            }
            if (has_permission('card_dashboard_log_table') || has_permission('card_recent_activity')) {
                // Pass show_header = false so the table component doesn't render its own h3
                $show_header = false;
                include __DIR__ . '/../../components/dashboard/table_detailed_logs.php';
            }
            $card_content = ob_get_clean();

            // Wrap in a single professional card
            $card_id = 'dashboard-unified-log-card';
            $card_classes = 'recent-activity-card app-table-card';
            include include_path('resources/views/components/global/ui_card.php');
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>
