<!-- Security Events Card -->
<div id="securityEventsCardContainer" style="display: none;">
    <div class="row gx-0">
        <div class="col-12">
            <div class="card slide-in-top overflow-visible-card app-table-card">
                <div class="card-body no-padding">
                    <div class="log-container">
                        <h3 class="log-title-wrapper app-table-title">
                            <span><i class="fas fa-shield-alt me-2"></i>Security Events</span>
                        </h3>
                        <div class="p-3">
                            <form id="securityEventsForm">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="secEventsUsername" class="form-label">Username</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="secEventsUsername" placeholder="Leave blank for all users">
                                            <button type="button" id="secEventsLookupWs" class="btn btn-outline-info" data-noc-tip="Look up associated workstations for this user">
                                                <i class="fas fa-search"></i> WS
                                            </button>
                                        </div>
                                        <div id="secEventsWsLookupResults" class="mt-2" style="display:none">
                                            <small class="text-muted">Associated Workstations:</small>
                                            <div id="secEventsWsLookupList" class="d-flex flex-wrap gap-1 mt-1"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label for="secEventsDateFrom" class="form-label">Date From</label>
                                        <input type="date" class="form-control" id="secEventsDateFrom">
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label for="secEventsDateTo" class="form-label">Date To</label>
                                        <input type="date" class="form-control" id="secEventsDateTo">
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label for="secEventsEventIds" class="form-label">
                                            Event IDs
                                            <button type="button" id="secEventsIdRefBtn" class="btn btn-sm btn-link p-0 ms-1 align-baseline" style="font-size:0.85rem" data-noc-tip="Reference: Common Security Event IDs">
                                                <i class="fas fa-info-circle text-info"></i>
                                            </button>
                                        </label>
                                        <input type="text" class="form-control" id="secEventsEventIds" placeholder="4624,4625,...">
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label for="secEventsWorkstation" class="form-label">Workstation</label>
                                        <input type="text" class="form-control" id="secEventsWorkstation" placeholder="Computer name">
                                    </div>
                                </div>
                                <div class="app-form-actions">
                                    <button type="button" id="submitSecurityEvents" class="btn btn-primary btn-report-action">
                                        <i class="fas fa-search me-1"></i> Fetch Events
                                    </button>
                                    <button type="button" id="cancelSecurityEvents" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Results Section -->
                    <div id="securityEventsResults" style="display: none;">
                        <div class="log-container no-padding border-top">
                            <h3 class="log-title-wrapper app-table-title">
                                <span><i class="fas fa-file-alt me-2"></i>Event Results <span id="secEventsSummary" class="badge rounded-pill bg-info ms-2 fs-6"></span></span>
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" id="downloadSecurityEvents" class="btn btn-sm btn-success">
                                        <i class="fas fa-download me-1"></i> Download CSV
                                    </button>
                                </div>
                            </h3>
                            <div id="secEventsWorkstations" class="px-3 py-2 border-bottom bg-light" style="display:none">
                                <strong><i class="fas fa-laptop me-1"></i>Associated Workstations:</strong>
                                <span id="secEventsWsList"></span>
                            </div>
                            <div class="log-table-wrapper app-table-wrapper">
                                <table class="table app-data-table log-table mb-0" id="securityEventsTable">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Event</th>
                                            <th>User</th>
                                            <th>Workstation</th>
                                            <th>Source IP</th>
                                            <th>Logon Type</th>
                                        </tr>
                                    </thead>
                                    <tbody id="securityEventsTbody">
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

<!-- Event ID Reference Modal -->
<div class="modal fade" id="secEventsIdRefModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-list me-2"></i>Common Security Event ID Reference</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table app-data-table mb-0">
                    <thead>
                        <tr><th>Event ID</th><th>Description</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>4624</strong></td><td>Account Logon — successful logon</td></tr>
                        <tr><td><strong>4625</strong></td><td>Account Logon — failed logon (wrong password, locked out, etc.)</td></tr>
                        <tr><td><strong>4634</strong></td><td>Account Logoff</td></tr>
                        <tr><td><strong>4647</strong></td><td>Initiator logoff (user initiated)</td></tr>
                        <tr><td><strong>4720</strong></td><td>User account created</td></tr>
                        <tr><td><strong>4722</strong></td><td>User account enabled</td></tr>
                        <tr><td><strong>4723</strong></td><td>Password change attempted</td></tr>
                        <tr><td><strong>4724</strong></td><td>Password reset attempted</td></tr>
                        <tr><td><strong>4725</strong></td><td>User account disabled</td></tr>
                        <tr><td><strong>4726</strong></td><td>User account deleted</td></tr>
                        <tr><td><strong>4728</strong></td><td>Member added to Security-Global group</td></tr>
                        <tr><td><strong>4729</strong></td><td>Member removed from Security-Global group</td></tr>
                        <tr><td><strong>4732</strong></td><td>Member added to Security-Local group</td></tr>
                        <tr><td><strong>4733</strong></td><td>Member removed from Security-Local group</td></tr>
                        <tr><td><strong>4740</strong></td><td>User account locked out</td></tr>
                        <tr><td><strong>4767</strong></td><td>User account unlocked</td></tr>
                        <tr><td><strong>4768</strong></td><td>Kerberos TGT requested</td></tr>
                        <tr><td><strong>4769</strong></td><td>Kerberos service ticket requested</td></tr>
                        <tr><td><strong>4770</strong></td><td>Kerberos service ticket renewed</td></tr>
                        <tr><td><strong>4771</strong></td><td>Kerberos pre-authentication failed</td></tr>
                        <tr><td><strong>4776</strong></td><td>NTLM authentication (DC validated credential)</td></tr>
                        <tr><td><strong>4779</strong></td><td>Remote Desktop session disconnected</td></tr>
                        <tr><td><strong>4780</strong></td><td>ACL applied to admin group members</td></tr>
                        <tr><td><strong>4781</strong></td><td>User account renamed</td></tr>
                        <tr><td><strong>4794</strong></td><td>DPAPI backup key attempted</td></tr>
                        <tr><td><strong>4798</strong></td><td>User's local group membership enumerated</td></tr>
                        <tr><td><strong>4799</strong></td><td>Security-enabled local group membership enumerated</td></tr>
                        <tr><td><strong>5136</strong></td><td>Directory service object modified</td></tr>
                        <tr><td><strong>5137</strong></td><td>Directory service object created</td></tr>
                        <tr><td><strong>5140</strong></td><td>Network share object accessed</td></tr>
                        <tr><td><strong>5145</strong></td><td>Network share accessed (detailed)</td></tr>
                        <tr><td><strong>5379</strong></td><td>Credential Manager credentials read</td></tr>
                        <tr><td><strong>5722</strong></td><td>Trust relationship — session setup from remote computer failed (old computer password)</td></tr>
                        <tr><td><strong>5723</strong></td><td>Trust relationship — resubmit of trust password for domain failed</td></tr>
                        <tr><td><strong>5765</strong></td><td>Trust relationship — cross-forest trust created</td></tr>
                        <tr><td><strong>5805</strong></td><td>Trust relationship — machine account authentication failed</td></tr>
                        <tr><td><strong>6272</strong></td><td>Network Policy Server granted access</td></tr>
                        <tr><td><strong>6273</strong></td><td>Network Policy Server denied access</td></tr>
                        <tr><td><strong>6274</strong></td><td>Network Policy Server discarded request</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
