<div class="log-filter border-bottom mb-0" style="padding: 0px 18px 10px 18px; background: transparent;">
    <form class="filter-form">
        <input type="hidden" name="page" value="dashboard">
        <div class="filter-grid" style="grid-template-columns: 1.2fr 1fr 1fr 1fr 1fr auto;">
            <div class="filter-group">
                <label for="search"><i class="fas fa-search"></i> Search</label>
                <input type="text" name="search" id="search" placeholder="Action, user, or performer..."
                    value="">
            </div>

            <div class="filter-group">
                <label for="time_period"><i class="fas fa-clock"></i> Time Period</label>
                <select name="time_period" id="time_period">
                    <option value="all">All Time</option>
                    <option value="today">Today</option>
                    <option value="72hours">Last 3 days</option>
                    <option value="week">Last 7 Days</option>
                    <option value="month" selected>Last 30 Days</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>

            <div id="custom-date-inputs" class="filter-group" style="display: none; grid-column: span 2;">
                <div style="display: flex; gap: 10px; align-items: flex-end;">
                    <div style="flex: 1;">
                        <label for="start_date"><i class="fas fa-calendar-alt"></i> From</label>
                        <input type="date" id="start_date" name="start_date" class="form-control">
                    </div>
                    <div style="flex: 1;">
                        <label for="end_date"><i class="fas fa-calendar-alt"></i> To</label>
                        <input type="date" id="end_date" name="end_date" class="form-control">
                    </div>
                </div>
            </div>

            <div class="filter-group">
                <label for="category"><i class="fas fa-folder"></i> Category</label>
                <select name="category" id="category">
                    <option value="">All Categories</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="status"><i class="fas fa-check-circle"></i> Status</label>
                <select name="status" id="status">
                    <option value="">All Statuses</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="domainFilter"><i class="fas fa-server"></i> Domain</label>
                <select name="domain" id="domainFilter">
                    <option value="">Active Domain</option>
                    <option value="all">All Domains</option>
                </select>
            </div>

            <div class="filter-group filter-action-cell">
                <button type="button" id="apply-filters-btn" class="btn btn-primary icon-only-btn" title="Apply Filters" aria-label="Apply Filters">
                    <i class="fas fa-filter"></i>
                </button>
                <button type="button" id="reset-filters-btn" class="btn btn-secondary icon-only-btn" title="Reset Filters" aria-label="Reset Filters">
                    <i class="fas fa-redo"></i>
                </button>
                <button type="button" id="export-logs-btn" class="btn btn-success icon-only-btn" title="Export Logs as CSV" aria-label="Export Logs">
                    <i class="fas fa-download"></i>
                </button>
            </div>
        </div>
    </form>
</div>
