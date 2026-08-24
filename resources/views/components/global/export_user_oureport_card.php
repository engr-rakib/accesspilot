<!-- Export User Report Card (OU/Group) -->
<div id="exportUserReportCardContainer" style="display: none;">
    <div class="row gx-0">
        <div class="col-12">
            <div class="card slide-in-top overflow-visible-card app-table-card">
                <div class="card-body no-padding">
                    <div class="log-container">
                        <h3 class="log-title-wrapper app-table-title">
                            <span><i class="fas fa-file-export me-2"></i>Export Users</span>
                        </h3>
                        <div class="p-3">
                            <form id="exportUserReportForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="exportReportOUDisplay" class="form-label">Organizational Unit (OU)</label>
                                        <div class="custom-select-container">
                                            <input type="text" id="exportReportOUDisplay" class="form-control" placeholder="Type to search OU...">
                                            <input type="hidden" id="exportReportOU" name="ouName">
                                            <div class="custom-select-dropdown" id="exportReportOUDropdown">
                                                <ul class="custom-select-list" id="exportReportOUList">
                                                    <!-- OUs will be rendered here -->
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="exportReportGroupDisplay" class="form-label">Group Name</label>
                                        <div class="custom-select-container">
                                            <input type="text" id="exportReportGroupDisplay" class="form-control" placeholder="Type to search groups...">
                                            <input type="hidden" id="exportReportGroup" name="groupName">
                                            <div class="custom-select-dropdown" id="exportReportGroupDropdown">
                                                <ul class="custom-select-list" id="exportReportGroupList">
                                                    <!-- Groups will be rendered here -->
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="app-form-actions">
                                    <button type="button" id="submitExportUserReport" class="btn btn-primary btn-report-action">
                                        <i class="fas fa-file-export me-1"></i> Fetch Users
                                    </button>
                                    <button type="button" id="cancelExportUserReport" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Results Section -->
                    <div id="exportUserReportResults" style="display: none;">
                        <div class="log-container no-padding border-top">
                            <h3 class="log-title-wrapper app-table-title">
                                <span><i class="fas fa-file-alt me-2"></i>Export Results <span id="exportUserReportSummary" class="badge rounded-pill bg-info ms-2 fs-6"></span></span>
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" id="downloadExportUserReport" class="btn btn-sm btn-success">
                                        <i class="fas fa-download me-1"></i> Download CSV
                                    </button>
                                </div>
                            </h3>
                            <div class="log-table-wrapper app-table-wrapper">
                                <table class="table app-data-table log-table mb-0" id="exportUserReportTable">
                                    <thead>
                                        <tr>
                                            <th>Source</th>
                                            <th>Username</th>
                                            <th>Display Name</th>
                                            <th>Status</th>
                                            <th>60d Logon</th>
                                            <th>Last Logon</th>
                                            <th>Created</th>
                                            <th>Privilege</th>
                                            <th>Member Of</th>
                                            <th>OU Name</th>
                                        </tr>
                                    </thead>
                                    <tbody id="exportUserReportTbody">
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
