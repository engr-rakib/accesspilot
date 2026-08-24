<?php

/**
 * @file audit_log_viewer.php
 * @brief Frontend for the real-time User Activity Dashboard.
 */

if (!function_exists('has_permission') || !has_permission('page_application_events')) {
    echo '<div class="alert alert-danger">Access Denied. You do not have permission to view this page.</div>';
    return;
}
?>

<div id="dashboard-container" class="dashboard-content audit-dashboard-content slide-in-top">
    <div id="loading-spinner" class="spinner-border text-primary" role="status" style="display: none;">
        <span class="visually-hidden">Loading...</span>
    </div>

    <div class="row dashboard-equal-row">
        <?php if (has_permission('card_event_overview')): ?>
            <div class="col-lg-4 dashboard-card-col">
                <div id="eventOverviewCard" class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-chart-pie me-2"></i>Overview</h3>
                    </div>
                    <div class="card-body d-flex flex-column p-0">
                        <div class="row g-0 flex-shrink-0" style="border-bottom:1px solid rgba(15,23,42,0.06);">
                            <div class="col-3 text-center py-2">
                                <div class="stat-icon" style="font-size:0.75rem;line-height:1;opacity:0.7;"><i class="fas fa-globe"></i></div>
                                <div id="stat-online-users" class="stat-number" style="font-size:1rem;font-weight:600;line-height:1.2;">0</div>
                                <div class="text-uppercase" style="font-size:0.55rem;opacity:0.6;letter-spacing:0.3px;">Online</div>
                            </div>
                            <div class="col-3 text-center py-2">
                                <div class="stat-icon" style="font-size:0.75rem;line-height:1;opacity:0.7;"><i class="fas fa-sign-in-alt"></i></div>
                                <div id="stat-logins" class="stat-number" style="font-size:1rem;font-weight:600;line-height:1.2;">0</div>
                                <div class="text-uppercase" style="font-size:0.55rem;opacity:0.6;letter-spacing:0.3px;">Logins</div>
                            </div>
                            <div class="col-3 text-center py-2">
                                <div class="stat-icon" style="font-size:0.75rem;line-height:1;opacity:0.7;"><i class="fas fa-exclamation-triangle"></i></div>
                                <div id="stat-failures" class="stat-number" style="font-size:1rem;font-weight:600;line-height:1.2;">0</div>
                                <div class="text-uppercase" style="font-size:0.55rem;opacity:0.6;letter-spacing:0.3px;">Failures</div>
                            </div>
                            <div class="col-3 text-center py-2">
                                <div class="stat-icon" style="font-size:0.75rem;line-height:1;opacity:0.7;"><i class="fas fa-user-check"></i></div>
                                <div id="stat-enabled-users" class="stat-number" style="font-size:1rem;font-weight:600;line-height:1.2;">0</div>
                                <div class="text-uppercase" style="font-size:0.55rem;opacity:0.6;letter-spacing:0.3px;">Enabled</div>
                            </div>
                        </div>
                        <div class="flex-grow-1 d-flex flex-column" style="min-height:0;padding:4px 12px 8px 12px;">
                            <div class="chart-container flex-grow-1" style="min-height:0;">
                                <canvas id="overviewChart"></canvas>
                            </div>
                            <div id="overviewChart-legend" class="flex-shrink-0" style="margin-top:4px;"></div>
                        </div>
                    </div>                </div>
            </div>
        <?php endif; ?>

        <?php if (has_permission('card_event_hourly_activity')): ?>
            <div class="col-lg-4 dashboard-card-col">
                <div id="activityHourCard" class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-chart-line me-2"></i>Session by Hour</h3>
                    </div>
                    <div id="activityHourChartBody" class="card-body chart-container">
                        <canvas id="activityHourChart"></canvas>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (has_permission('card_user_activity_tracking')): ?>
        <div class="col-lg-4 dashboard-card-col">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-bar me-2"></i>User Activity Tracking</h3>
                </div>
                <div class="card-body chart-container" style="padding: 8px 12px 4px 12px;">
                    <canvas id="userActivityTrackingChart2"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Unsuccessful Guest User Monitoring Section -->
    <div class="row gx-0">
        <?php if (has_permission('card_event_active_sessions')): ?>
            <div class="col-12">
                <div class="card" id="guest-monitoring-card" style="overflow: hidden !important;">
                    <div class="card-body no-padding" style="padding: 0 !important; margin: 0 !important;">
                        <div class="log-title-wrapper app-table-title" style="margin: 0 !important; border-bottom: 1px solid rgba(15, 23, 42, 0.08) !important;">
                            <span><i class="fas fa-user-slash me-2"></i>Unsuccessful Guest User Monitoring</span>
                            <span class="guest-card-actions d-flex align-items-center gap-2" style="flex-wrap: wrap;">
                                <span class="d-inline-flex align-items-center gap-1" id="guestRangeButtons" role="group" aria-label="Guest chart range">
                                    <button type="button" class="btn btn-sm btn-outline-primary active" data-guest-range="last12h">Last 12H</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-guest-range="last72h">Last 72H</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-guest-range="lastweek">Last Week</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-guest-range="month">Last Month</button>
                                </span>
                                <span id="guestCustomDateInputs" class="d-inline-flex align-items-center gap-1">
                                    <input type="date" id="guestStartDate" class="form-control form-control-sm" style="width: auto;">
                                    <span style="opacity: 0.6; font-size: 0.8rem;">to</span>
                                    <input type="date" id="guestEndDate" class="form-control form-control-sm" style="width: auto;">
                                    <button type="button" id="guestCustomApplyBtn" class="btn btn-sm btn-outline-primary">Apply</button>
                                </span>
                                <span id="guest-failure-count" class="badge rounded-pill fs-6" style="color: white; background-color: #dc3545;">0</span>
                                <button type="button" id="guestDownloadReportBtn" class="btn btn-sm btn-outline-primary" title="Download Report (PNG + CSV)">
                                    <i class="fas fa-download me-1"></i>Report
                                </button>
                                <button type="button" id="guestBlockedIpsBtn" class="btn btn-sm btn-outline-danger" title="Manage blocked IPs">
                                    <i class="fas fa-ban me-1"></i>Blocked IPs
                                </button>
                            </span>
                        </div>
                        <div id="guestBlockedIpsPanel" class="px-3 py-2" style="display: none; border-bottom: 1px solid rgba(15, 23, 42, 0.08); background: rgba(220, 53, 69, 0.03);">
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                                <span class="fw-semibold small" style="white-space: nowrap;"><i class="fas fa-shield-halved me-1"></i>IP Blocking</span>
                                <input type="text" id="guestBlockIpInput" class="form-control form-control-sm" style="width: 230px;" placeholder="IP or CIDR (e.g. 203.0.113.5 or 10.0.0.0/24)">
                                <button type="button" id="guestBlockIpAddBtn" class="btn btn-sm btn-danger">Block IP</button>
                                <div class="form-check ms-auto mb-0">
                                    <input class="form-check-input" type="checkbox" id="guestBlockToggle" checked>
                                    <label class="form-check-label small" for="guestBlockToggle">Blocking enabled</label>
                                </div>
                            </div>
                            <div id="guestBlockedIpsList" class="d-flex flex-wrap gap-1 small mb-1"></div>
                            <div id="guestBlockMsg" class="small" style="opacity: 0.85;"></div>
                        </div>
                        <div class="row gx-0">
                            <div class="col-12">
                                <div class="chart-container" style="height: 280px; padding: 8px 12px;">
                                    <canvas id="guestFailureChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Active Sessions Section -->
    <div class="row gx-0">
        <?php if (has_permission('card_event_active_sessions')): ?>
            <div class="col-12">
                <?php
                ob_start();
                $headers = ['Username', 'IP Address', 'Session Duration', 'Terminate'];
                $table_title = 'Active Sessions';
                $table_icon = 'fa-clock';
                $tbody_id = 'onlineUsersTableBody';
                $show_header = true;
                include include_path('resources/views/components/global/ui_log_table.php');
                $card_content = ob_get_clean();

                $card_id = 'active-sessions-card';
                $card_classes = 'recent-activity-card app-table-card';
                include include_path('resources/views/components/global/ui_card.php');
                ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- App Events (Audit Logs) Section -->
    <?php if (has_permission('card_event_log_table')): ?>
        <div class="row gx-0">
            <div class="col-12">
                <?php
                ob_start();
                ?>
                <!-- Standardized Header -->
                <div class="log-title-wrapper app-table-title" style="margin: 0 !important; border-bottom: 1px solid rgba(15, 23, 42, 0.08) !important;">
                    <span><i class="fas fa-history me-2"></i>App Events</span>
                    <span id="event-log-count" class="badge rounded-pill fs-6" style="color: white; background-color: #6c757d;">0</span>
                </div>

                <!-- Standardized Filter Bar -->
                <?php if (has_permission('card_event_filters')): ?>
                <div class="log-filter border-bottom mb-0" style="padding: 0px 18px 10px 18px; background: transparent;">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label for="eventLogSearchInput"><i class="fas fa-search"></i> Search</label>
                            <input type="text" id="eventLogSearchInput" placeholder="User, action, IP, or details...">
                        </div>
                        <div class="filter-group">
                            <label for="eventTimePeriod"><i class="fas fa-clock"></i> Time Period</label>
                            <select id="eventTimePeriod">
                                <option value="week">Last 7 Days</option>
                                <option value="month">Last 30 Days</option>
                                <option value="today">Today</option>
                                <option value="72hours">Last 3 Days</option>
                                <option value="year">This Year</option>
                                <option value="all">All Time</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="eventActionFilter"><i class="fas fa-bolt"></i> Action</label>
                            <select id="eventActionFilter">
                                <option value="">All Actions</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="eventStatusFilter"><i class="fas fa-check-circle"></i> Status</label>
                            <select id="eventStatusFilter">
                                <option value="">All Statuses</option>
                            </select>
                        </div>
                        <div class="filter-group filter-action-cell">
                            <button type="button" id="applyEventFiltersBtn" class="btn btn-primary icon-only-btn" title="Apply Filters" aria-label="Apply Filters">
                                <i class="fas fa-filter"></i>
                            </button>
                            <button type="button" id="resetEventFiltersBtn" class="btn btn-secondary icon-only-btn" title="Reset Filters" aria-label="Reset Filters">
                                <i class="fas fa-redo"></i>
                            </button>
                        </div>

                        <!-- Custom Date Inputs (Standardized) -->
                        <div id="eventCustomDateInputs" class="filter-group" style="display: none; grid-column: span 2;">
                            <div style="display: flex; gap: 10px; align-items: flex-end;">
                                <div style="flex: 1;">
                                    <label for="eventStartDate"><i class="fas fa-calendar-alt"></i> From</label>
                                    <input type="date" id="eventStartDate" class="form-control">
                                </div>
                                <div style="flex: 1;">
                                    <label for="eventEndDate"><i class="fas fa-calendar-alt"></i> To</label>
                                    <input type="date" id="eventEndDate" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Standardized Table -->
                <?php
                $headers = ['Timestamp', 'User', 'Action', 'Status', 'IP', 'Details'];
                $tbody_id = 'auditLogTableBody';
                $no_logs_id = 'event-no-logs-message';
                $no_logs_message = 'No activity logs found for selected filters';
                $show_header = false;
                include include_path('resources/views/components/global/ui_log_table.php');
                $card_content = ob_get_clean();

                $card_id = 'audit-log-unified-card';
                $card_classes = 'recent-activity-card app-table-card';
                include include_path('resources/views/components/global/ui_card.php');
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
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
        actionTakenCardContainer.style.display = 'block';
        actionTakenTitleSpan.textContent = isSuccess ? 'Success' : 'Error';
        actionTakenMessageDisplay.innerHTML = message;
        actionTakenMessageDisplay.className = isSuccess ? 'alert alert-success' : 'alert alert-danger';

        setTimeout(() => {
            actionTakenCardContainer.style.display = 'none';
        }, 20000);
    }
});
</script>
<?php
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_is_success']);
else:
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const actionTakenCardContainer = document.getElementById('actionTakenCardContainer');
    if (actionTakenCardContainer) {
        actionTakenCardContainer.style.display = 'none';
    }
});
</script>
<?php
endif;
?>
