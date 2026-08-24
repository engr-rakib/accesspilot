<!-- User Report Card -->
<div id="userReportCardContainer" style="display: none;">
    <div class="row gx-0">
        <div class="col-12">
            <div class="card slide-in-top overflow-visible-card app-table-card">
                <div class="card-body no-padding">
                    <div class="log-container">
                        <h3 class="log-title-wrapper app-table-title">
                            <span><i class="fas fa-file-invoice me-2"></i>User Status Report</span>
                        </h3>
                        <div class="p-3">
                            <form id="userReportForm">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="userStatus" class="form-label">User Status</label>
                                        <select class="form-select" id="userStatus" name="userStatus">
                                            <option value="active">Active Users</option>
                                            <option value="inactive" selected>Inactive Users</option>
                                            <option value="disabled">Disabled Users</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3" id="daysDropdownGroup">
                                        <label for="reportDays" class="form-label">Inactivity Period (Days)</label>
                                        <select class="form-select" id="reportDays" name="reportDays">
                                            <option value="15">Last 15 Days</option>
                                            <option value="30" selected>Last 30 Days</option>
                                            <option value="45">Last 45 Days</option>
                                            <option value="60">Last 60 Days</option>
                                            <option value="90">Last 90 Days</option>
                                            <option value="custom">Custom Days</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3" id="customDaysGroup" style="display: none;">
                                        <label for="customDays" class="form-label">Custom Days</label>
                                        <input type="number" class="form-control" id="customDays" name="customDays" placeholder="Enter number of days">
                                    </div>
                                </div>
                                <div class="app-form-actions">
                                    <button type="button" id="submitUserReport" class="btn btn-primary btn-report-action">
                                        <i class="fas fa-save me-1"></i> Submit
                                    </button>
                                    <button type="button" id="cancelUserReport" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Report Results Section -->
                    <div id="userReportResults" style="display: none;">
                        <div class="log-container no-padding border-top">
                            <h3 class="log-title-wrapper app-table-title">
                                <span><i class="fas fa-file-alt me-2"></i>Report Results <span id="reportSummary" class="badge rounded-pill bg-info ms-2 fs-6"></span></span>
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" id="downloadUserReport" class="btn btn-sm btn-success">
                                        <i class="fas fa-download me-1"></i> Download CSV
                                    </button>
                                    <div id="bulkActions" style="display: none;">
                                        <button type="button" id="disableAllInactive" class="btn btn-sm btn-danger">
                                            <i class="fas fa-user-slash me-1"></i> Disable All
                                        </button>
                                    </div>
                                </div>
                            </h3>
                            <div class="log-table-wrapper app-table-wrapper">
                                <table class="table app-data-table log-table mb-0" id="userReportTable">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Display Name</th>
                                            <th>Last Logon</th>
                                            <th>Status</th>
                                            <th>OU Path</th>
                                        </tr>
                                    </thead>
                                    <tbody id="userReportTbody">
                                        <!-- Results will be injected here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
