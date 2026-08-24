<?php if (has_permission('card_manual_create_form')):
?><div class="row gx-0" id="directoryBuilderFormContainerRow" style="display: none; position: relative; z-index: 9999;">
    <div class="col-12">
        <div class="card h-100 slide-in-top overflow-visible-card">
            <div class="card-body">
                <div id="directoryBuilderFormContainer">
                    <h3 class="card-title" id="directoryBuilderFormTitle">
                        <span><i class="fas fa-sitemap"></i> OU and Groups Manager</span>
                        <div class="mode-selector" role="group">
                            <input type="radio" class="mode-radio" name="dirMode" id="modeCreate" autocomplete="off" checked>
                            <label class="mode-btn" for="modeCreate" data-mode="create">
                                <i class="fas fa-plus-circle"></i><span>Create</span>
                            </label>
                            <input type="radio" class="mode-radio" name="dirMode" id="modeManage" autocomplete="off">
                            <label class="mode-btn" for="modeManage" data-mode="manage">
                                <i class="fas fa-cog"></i><span>Manage</span>
                            </label>
                            <input type="radio" class="mode-radio" name="dirMode" id="modeDelete" autocomplete="off">
                            <label class="mode-btn" for="modeDelete" data-mode="delete">
                                <i class="fas fa-trash-alt"></i><span>Delete</span>
                            </label>
                        </div>
                    </h3>

                    <div id="directoryBuilderInlineStatus" class="alert alert-info mb-3" style="display: none;"></div>

                    <!-- 1. Create Mode Section -->
                    <div id="createModeSection" class="mb-3">
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label for="directoryObjectType" class="form-label">Object Type</label>
                                <select id="directoryObjectType" class="form-select">
                                    <option value="">-- Select Object Type --</option>
                                    <option value="OU">Organizational Unit (OU)</option>
                                    <option value="Group">Security Group</option>
                                </select>
                            </div>
                        </div>
                        <div id="createFieldsContainer" class="row g-3 mt-1" style="display: none;">
                            <div class="col-lg-6">
                                <label for="directoryParentOUDisplay" class="form-label" id="lblParentOU">Select Parent OU</label>
                                <div class="custom-select-container">
                                    <input type="text" id="directoryParentOUDisplay" class="form-control" placeholder="Search parent...">
                                    <input type="hidden" id="directoryParentOU" name="directoryParentOU">
                                    <div class="custom-select-dropdown" id="directoryParentOUDropdown">
                                        <ul class="custom-select-list" id="directoryParentOUList"></ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <label for="directoryObjectDescription" class="form-label">Description / Note</label>
                                <input type="text" id="directoryObjectDescription" class="form-control" placeholder="Requirements or purpose...">
                            </div>
                            <div class="col-lg-6">
                                <label for="directoryObjectName" class="form-label" id="lblObjectName">New OU Name</label>
                                <input type="text" id="directoryObjectName" class="form-control" placeholder="Enter name...">
                            </div>
                        </div>
                    </div>

                    <!-- 2. Manage Mode Section -->
                    <div id="manageModeSection" class="mb-3" style="display: none;">
                        <div class="row g-3 mb-3">
                            <div class="col-lg-12">
                                <label for="manageGroupTargetDisplay" class="form-label">Select Group to Manage</label>
                                <div class="custom-select-container">
                                    <input type="text" id="manageGroupTargetDisplay" class="form-control" placeholder="Search for a group to edit members...">
                                    <input type="hidden" id="manageGroupTargetDN" name="manageGroupTargetDN">
                                    <div class="custom-select-dropdown" id="manageGroupTargetDropdown">
                                        <ul class="custom-select-list" id="manageGroupTargetList"></ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="manageGroupMembersArea" style="display:none;transition:opacity 0.25s ease;">
                            <div class="alert alert-info py-2 px-3 mb-3 d-flex justify-content-between align-items-center" style="border-radius: 8px;">
                                <div class="small fw-bold text-dark"><i class="fas fa-chart-pie me-1"></i>Group Summary</div>
                                <div class="d-flex gap-2">
                                    <span class="badge bg-primary shadow-sm">Total: <span id="managedGroupTotalCount">0</span></span>
                                    <span class="badge bg-success shadow-sm">Users: <span id="managedGroupUsersCount">0</span></span>
                                    <span class="badge bg-info shadow-sm">Groups: <span id="managedGroupSubGroupsCount">0</span></span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="manageGroupNewMemberInput" class="form-label small fw-bold">Add User or Nested Group</label>
                                <input type="text" class="form-control" id="manageGroupNewMemberInput" placeholder="Enter exact username or group name...">
                                <button type="button" id="btnManageGroupAddMember" title="Queue Member">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>

                            <div class="log-table-wrapper app-table-wrapper">
                                <table class="table app-data-table log-table mb-0 table-hover" id="manageGroupMembersTable">
                                    <thead>
                                        <tr>
                                            <th style="width:auto;">Member Name</th>
                                            <th style="width:140px;">ID</th>
                                            <th style="width:80px;">Type</th>
                                            <th style="width:1%;white-space:nowrap;text-align:right;padding-right:12px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="manageGroupMembersTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Delete Mode Section -->
                    <div id="deleteModeSection" class="mb-3" style="display: none;">
                        <div class="alert alert-danger d-flex align-items-center gap-3 py-2 px-3 mb-3" style="border-left:5px solid #dc3545;border-radius:8px;">
                            <i class="fas fa-exclamation-triangle text-danger" style="font-size:1.1rem;"></i>
                            <div>
                                <div class="fw-bold small text-dark">CRITICAL ACTION: DIRECTORY OBJECT REMOVAL</div>
                                <div class="small text-muted">Deleting an OU or Security Group is permanent and cannot be undone. OUs containing protected objects or children will automatically fail.</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="deleteTargetDisplay" class="form-label small fw-bold">Select Object to Delete</label>
                                <div class="custom-select-container">
                                    <div class="input-icon-wrapper">
                                        <i class="fas fa-search input-icon"></i>
                                        <input type="text" id="deleteTargetDisplay" class="form-control input-with-icon" placeholder="Search for the specific OU or Group to remove...">
                                    </div>
                                    <input type="hidden" id="deleteTargetDN" name="deleteTargetDN">
                                    <input type="hidden" id="deleteTargetType" name="deleteTargetType">
                                    <div class="custom-select-dropdown" id="deleteTargetDropdown">
                                        <ul class="custom-select-list" id="deleteTargetList"></ul>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="d-flex align-items-center gap-2 p-3 bg-light border rounded">
                                    <input class="form-check-input border-danger mt-0" type="checkbox" id="deleteSafetyCheck">
                                    <label class="small fw-bold text-dark mb-0" for="deleteSafetyCheck">
                                        I acknowledge that I am deleting a production directory object and understand this action is permanent.
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="app-form-actions">
                        <button type="button" id="submitDirectoryBuilder" class="btn btn-primary" style="display: none;">
                            <i class="fas fa-save me-1"></i>Submit
                        </button>
                        <button type="button" id="submitGroupManagerUpdate" class="btn btn-primary" style="display: none;">
                            <i class="fas fa-save me-1"></i>Submit
                        </button>
                        <button type="button" id="submitDirectoryDeleter" class="btn btn-danger" style="display: none;">
                            <i class="fas fa-trash-alt me-1"></i>Delete
                        </button>
                        <button type="button" id="cancelDirectoryBuilderFooterButton" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
