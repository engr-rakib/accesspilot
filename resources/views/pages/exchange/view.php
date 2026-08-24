<div class="page-container email-page">
    <!-- Header -->
    <div class="page-header d-flex align-items-center justify-content-between mb-3">
        <div>
            <h2 class="fw-bold m-0" style="font-size:var(--font-xxl);">
                <i class="fas fa-exchange-alt me-2" style="color:var(--primary-color);"></i>Exchange Management
            </h2>
            <p class="text-muted m-0" style="font-size:var(--font-sm);">Mailbox lifecycle, distribution groups, mail flow monitoring</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span id="exchangeServerStatus" class="status-badge status-info" style="font-size:var(--font-xs);">
                <i class="fas fa-sync fa-spin me-1"></i>Discovering...
            </span>
        </div>
    </div>

    <!-- NOC-style Tab Bar -->
    <div class="noc-tabs-bar" id="exchangeTabs">
        <div class="noc-tab-item active" data-tab="recipients">
            <i class="fas fa-inbox me-1"></i> Mailboxes &amp; Groups
        </div>
        <div class="noc-tab-item" data-tab="monitoring">
            <i class="fas fa-chart-line me-1"></i> Monitoring
        </div>
        <div class="noc-tab-item" data-tab="settings">
            <i class="fas fa-cogs me-1"></i> Settings
        </div>
    </div>

    <!-- Feedback Card -->
    <div class="card mb-3 slide-in-bottom" id="exchangeActionCard" style="display:none;border-left:4px solid var(--primary-color);">
        <div class="card-body py-3 px-4 d-flex align-items-center justify-content-between">
            <div>
                <strong><span id="exchangeActionTitle">Result</span></strong>
                <div id="exchangeActionMessage" class="mt-1" style="font-size:var(--font-sm);"></div>
            </div>
            <button class="btn btn-sm text-muted p-1" onclick="this.closest('.card').style.display='none'" style="font-size:18px;">&times;</button>
        </div>
    </div>

    <!-- Tab Content -->
    <div class="noc-tab-content">

        <!-- TAB: Mailboxes & Groups (combined) -->
        <div class="tab-pane active" id="tab-recipients">
            <!-- Combined Search Bar -->
            <div class="app-table-card ws-card mb-3" style="border-top:3px solid var(--primary-color);">
                <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-search me-2"></i>Search</h3>
                    <div class="d-flex gap-1 flex-wrap">
                        <button class="btn btn-outline-primary" id="exchangeMailboxUserCreateBtn" style="height:36px;border-radius:6px;" data-noc-tip="Create a new AD user with Exchange mailbox">
                            <i class="fas fa-user-plus me-1"></i>New User
                        </button>
                        <button class="btn btn-outline-success" id="exchangeGroupCreateBtn" style="height:36px;border-radius:6px;" data-noc-tip="Create a new distribution group">
                            <i class="fas fa-users me-1"></i>New Group
                        </button>
                    </div>
                </div>
                <div class="p-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-sm-5">
                            <label style="font-size:10px;color:var(--text-muted);">Mailbox / AD User</label>
                            <input type="text" class="app-table-input" id="exchangeMailboxIdentity"
                                placeholder="Username, email, or employee ID"
                                style="width:100%;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;">
                        </div>
                        <div class="col-sm-5">
                            <label style="font-size:10px;color:var(--text-muted);">Distribution Group</label>
                            <input type="text" class="app-table-input" id="exchangeGroupKeyword"
                                placeholder="Group name, email, or alias"
                                style="width:100%;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;">
                        </div>
                        <div class="col-sm-2">
                            <button class="btn btn-primary w-100" id="exchangeCombinedSearchGo" style="height:36px;border-radius:6px;" data-noc-tip="Search by mailbox identity or group keyword">
                                <i class="fas fa-search me-1"></i>Search
                            </button>
                        </div>
                    </div>
                    <div class="mt-2 text-muted" style="font-size:9px;">
                        <i class="fas fa-info-circle"></i> Typing in one field locks the other. Results appear below.
                    </div>
                </div>
            </div>

            <!-- Create Group Form -->
            <div id="exchangeGroupCreateForm" style="display:none;">
                <div class="app-table-card ws-card mb-3" style="border-top:3px solid #198754;">
                    <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                        <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-plus-circle me-2"></i>Create Distribution Group</h3>
                        <button class="app-table-btn" id="exchangeGroupCreateCancel" style="height:36px;" data-noc-tip="Cancel group creation">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                    </div>
                    <div class="card-body p-3" style="overflow:visible;">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label" style="font-size:var(--font-xs);">Name *</label>
                                <input type="text" class="app-table-input" id="exchangeGroupCreateName"
                                    style="width:100%;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" style="font-size:var(--font-xs);">Alias</label>
                                <input type="text" class="app-table-input" id="exchangeGroupCreateAlias"
                                    style="width:100%;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;">
                            </div>
                            <div class="col-12">
                                <label class="form-label" style="font-size:var(--font-xs);">Description</label>
                                <textarea class="app-table-input" id="exchangeGroupCreateDescription"
                                    style="width:100%;min-height:60px;border:1px solid var(--border-color);border-radius:6px;padding:8px 12px;resize:vertical;"></textarea>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" style="font-size:var(--font-xs);">OU Location</label>
                                <div class="custom-select-container" style="position:relative">
                                    <input type="text" id="exchangeGroupCreateOUDisplay" placeholder="Type to search OU..." style="width:100%;height:36px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 12px;">
                                    <input type="hidden" id="exchangeGroupCreateOU" name="exchangeGroupCreateOU">
                                    <div class="custom-select-dropdown" id="exchangeGroupCreateOUDropdown">
                                        <ul class="custom-select-list" id="exchangeGroupCreateOUList"></ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button class="btn btn-success w-100" id="exchangeGroupCreateSubmit" style="height:36px;border-radius:6px;" data-noc-tip="Create the distribution group in Active Directory">
                                    <i class="fas fa-check me-1"></i>Create Group
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enable Mailbox / Create User Form -->
            <div id="exchangeMailboxUserCreateForm" style="display:none;">
                <div class="app-table-card ws-card mb-3 overflow-visible-card" style="border-top:3px solid #0d6efd;">
                    <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                        <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-user-plus me-2"></i>New Mailbox User</h3>
                        <button class="app-table-btn" id="exchangeMailboxUserCreateCancel" style="height:36px;">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                    </div>
                    <div class="card-body p-3" style="overflow:visible;">
                        <!-- Overflow fixed by .card-body:has(.custom-select-container) CSS rule -->
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="exchangeExistingUserToggle" checked>
                            <label class="form-check-label" for="exchangeExistingUserToggle" style="font-size:var(--font-xs);">Existing AD User</label>
                        </div>

                        <!-- Existing AD User Section -->
                        <div id="exchangeExistingUserSection">
                            <p class="text-muted" style="font-size:var(--font-xs);">Search for an existing AD user to enable their mailbox.</p>
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <label class="form-label" style="font-size:var(--font-xs);">AD Username / Email</label>
                                    <input type="text" class="app-table-input" id="exchangeSearchIdentity"
                                        placeholder="samaccountname or email"
                                        style="width:100%;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;">
                                </div>
                                <div class="col-sm-6 d-flex align-items-end">
                                    <button class="btn btn-primary w-100" id="exchangeSearchIdentityGo" style="height:36px;border-radius:6px;" data-noc-tip="Search for an existing AD user">
                                        <i class="fas fa-search me-1"></i>Search &amp; Enable
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- New User Creation Section -->
                        <div id="exchangeNewUserSection" style="display:none;">
                            <p class="text-muted" style="font-size:var(--font-xs);">Create a new AD user and enable their mailbox.</p>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label" style="font-size:var(--font-xs);">First Name *</label>
                                    <input type="text" class="app-table-input" id="exchangeUserCreateFirstName"
                                        style="width:100%;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" style="font-size:var(--font-xs);">Last Name *</label>
                                    <input type="text" class="app-table-input" id="exchangeUserCreateLastName"
                                        style="width:100%;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" style="font-size:var(--font-xs);">Username (sAMAccountName) *</label>
                                    <input type="text" class="app-table-input" id="exchangeUserCreateUsername"
                                        style="width:100%;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" style="font-size:var(--font-xs);">Display Name</label>
                                    <input type="text" class="app-table-input" id="exchangeUserCreateDisplayName"
                                        style="width:100%;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" style="font-size:var(--font-xs);">Primary SMTP Email</label>
                                    <input type="email" class="app-table-input" id="exchangeUserCreateEmail"
                                        placeholder="user@domain.com"
                                        style="width:100%;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;">
                                </div>
                                <div class="col-md-4" style="position:relative;z-index:99999">
                                    <label class="form-label" style="font-size:var(--font-xs);">OU Location</label>
                                    <div class="custom-select-container" style="position:relative">
                                        <input type="text" id="exchangeUserCreateOUDisplay" placeholder="Type to search OU..." style="width:100%;height:36px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 12px;">
                                        <input type="hidden" id="exchangeUserCreateOU" name="exchangeUserCreateOU">
                                        <div class="custom-select-dropdown exchange-dropdown" id="exchangeUserCreateOUDropdown">
                                            <ul class="custom-select-list" id="exchangeUserCreateOUList"></ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-2 pb-2 mt-1" style="border-bottom:1px solid var(--border-color);">
                                <div class="d-flex align-items-center mb-2">
                                    <strong style="font-size:var(--font-sm);"><i class="fas fa-users me-1"></i>Group Membership <i class="fas fa-info-circle text-muted" data-noc-tip="Search and add groups. The user will be added to these groups after creation." style="font-size:10px;cursor:help"></i></strong>
                                </div>
                                <div class="custom-select-container">
                                    <div id="exchangeUserGroupTags" class="selected-tags-container"></div>
                                    <input type="text" id="exchangeUserGroupDisplay" placeholder="Type to search groups..." style="width:100%;height:36px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 12px;">
                                    <input type="hidden" id="exchangeUserGroupMembers" name="exchangeUserGroupMembers">
                                     <div class="custom-select-dropdown exchange-dropdown" id="exchangeUserGroupDropdown">
                                        <ul class="custom-select-list" id="exchangeUserGroupList"></ul>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-2">
                                <div class="col-12 mt-2">
                                    <button class="btn btn-success" id="exchangeUserCreateSubmit" style="height:36px;border-radius:6px;" data-noc-tip="Create the user and enable mailbox">
                                        <i class="fas fa-plus me-1"></i>Create User &amp; Enable Mailbox
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Single Dynamic Result Card -->
            <div id="exchangeResultCard" class="app-table-card ws-card mb-3" style="border-top:3px solid var(--primary-color);display:block;">
                <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="m-0" style="font-size:var(--font-lg);">
                        <i class="fas fa-search me-2"></i><span id="exchangeResultTitle">Search results</span>
                    </h3>
                    <div class="d-flex gap-1" id="exchangeResultActions">
                        <button class="app-table-btn action-exchange-enable" style="height:32px;display:none;" data-action="enable" data-noc-tip="Enable mailbox">
                            <i class="fas fa-check-circle"></i>
                        </button>
                        <button class="app-table-btn action-exchange-disable" style="height:32px;display:none;" data-action="disable" data-noc-tip="Disable mailbox">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    </div>
                </div>
                <div class="p-3" id="exchangeResultBody" style="overflow-wrap:anywhere;">
                    <p class="text-muted mb-0">Search for a mailbox or group to see results.</p>
                </div>
            </div>
        </div>

        <!-- TAB: Monitoring -->
        <div class="tab-pane" id="tab-monitoring" style="display:none;">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="ws-card p-3 text-center" style="border-top:3px solid var(--primary-color);">
                        <h2 class="m-0 fw-bold" id="monDbCount" style="color:var(--primary-color);">-</h2>
                        <p class="m-0 text-muted" style="font-size:var(--font-xs);">Databases</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ws-card p-3 text-center" style="border-top:3px solid #28a745;">
                        <h2 class="m-0 fw-bold" id="monMailboxCount" style="color:#28a745;">-</h2>
                        <p class="m-0 text-muted" style="font-size:var(--font-xs);">Mailboxes</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ws-card p-3 text-center" style="border-top:3px solid #ffc107;">
                        <h2 class="m-0 fw-bold" id="monServerCount" style="color:#ffc107;">-</h2>
                        <p class="m-0 text-muted" style="font-size:var(--font-xs);">Exchange Servers</p>
                    </div>
                </div>
            </div>

            <!-- Database Status -->
            <div class="app-table-card ws-card mb-3" style="border-top:3px solid var(--primary-color);">
                <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-database me-2"></i>Database Status</h3>
                    <button class="app-table-btn" id="refreshDatabases" style="height:36px;" data-noc-tip="Refresh Exchange database list and server status">
                        <i class="fas fa-sync me-1"></i>Refresh
                    </button>
                </div>
                <div class="p-3" id="exchangeDatabaseStatus">
                    <div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading...</p></div>
                </div>
            </div>

            <!-- Quota Warning Report (PowerShell) -->
            <div class="app-table-card ws-card mb-3" style="border-top:3px solid #fd7e14;">
                <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-exclamation-triangle me-2"></i>Quota Warning Report</h3>
                    <button class="app-table-btn" id="refreshQuotaReport" style="height:36px;" data-noc-tip="Fetch mailbox quota warnings from Exchange">
                        <i class="fas fa-sync me-1"></i>Refresh
                    </button>
                </div>
                <div class="p-3" id="exchangeQuotaReport">
                    <p class="text-muted mb-0" style="font-size:var(--font-sm);">
                        <i class="fas fa-info-circle me-1"></i>Quota reports require Exchange PowerShell connection.
                    </p>
                </div>
            </div>

            <!-- Mail Flow Queues (PowerShell) -->
            <div class="app-table-card ws-card mb-3" style="border-top:3px solid #0d6efd;">
                <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-truck me-2"></i>Mail Flow Queues</h3>
                    <button class="app-table-btn" id="refreshMailQueues" style="height:36px;" data-noc-tip="Refresh mail flow queue status">
                        <i class="fas fa-sync me-1"></i>Refresh
                    </button>
                </div>
                <div class="p-3" id="exchangeMailQueues">
                    <p class="text-muted mb-0" style="font-size:var(--font-sm);">
                        <i class="fas fa-info-circle me-1"></i>Mail queue requires Exchange PowerShell connection.
                    </p>
                </div>
            </div>

            <!-- Transport Rules (PowerShell) -->
            <div class="app-table-card ws-card mb-3" style="border-top:3px solid #198754;">
                <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-gavel me-2"></i>Transport Rules</h3>
                    <button class="app-table-btn" id="refreshTransportRules" style="height:36px;" data-noc-tip="Refresh transport rules list">
                        <i class="fas fa-sync me-1"></i>Refresh
                    </button>
                </div>
                <div class="p-3" id="exchangeTransportRules">
                    <p class="text-muted mb-0" style="font-size:var(--font-sm);">
                        <i class="fas fa-info-circle me-1"></i>Transport rules require Exchange PowerShell connection.
                    </p>
                </div>
            </div>

            <!-- Message Tracking (PowerShell) -->
            <div class="app-table-card ws-card mb-3" style="border-top:3px solid #6f42c1;">
                <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-search me-2"></i>Message Tracking</h3>
                    <button class="app-table-btn" id="searchMessageTracking" style="height:36px;">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                </div>
                <div class="p-3" id="exchangeMessageTracking">
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <input type="text" class="app-table-input" id="mtSender"
                                placeholder="Sender email"
                                style="width:100%;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;font-size:var(--font-xs);">
                        </div>
                        <div class="col-md-4">
                            <input type="text" class="app-table-input" id="mtRecipient"
                                placeholder="Recipient email"
                                style="width:100%;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;font-size:var(--font-xs);">
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="app-table-input" id="mtStartDate"
                                style="width:100%;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;font-size:var(--font-xs);">
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="app-table-input" id="mtEndDate"
                                style="width:100%;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;font-size:var(--font-xs);">
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" id="mtSearchGo" style="height:36px;border-radius:6px;font-size:var(--font-xs);" data-noc-tip="Search message tracking logs by sender/recipient/date">
                            <i class="fas fa-search me-1"></i>Search
                        </button>
                    </div>
                    <div id="mtResults" class="mt-2"></div>
                </div>
            </div>
        </div>

        <!-- TAB: Settings -->
        <div class="tab-pane" id="tab-settings" style="display:none;">
            <div class="app-table-card ws-card mb-3" style="border-top:3px solid var(--primary-color);">
                <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-cogs me-2"></i>Exchange Connection</h3>
                    <div class="d-flex gap-1 flex-wrap">
                        <button class="app-table-btn" id="exchangeTestConnection" style="height:36px;" data-noc-tip="Test Exchange PowerShell connection">
                            <i class="fas fa-plug me-1"></i>Test Connection
                        </button>
                    </div>
                </div>
                <div class="p-3">
                    <div id="exchangeConnectionInfo" class="mb-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="status-badge status-info"><i class="fas fa-sync fa-spin me-1"></i>Discovering...</span>
                        </div>
                    </div>

                    <div id="exchangeConfigHint" class="mt-3 pt-3" style="border-top:1px solid var(--border-color);display:none;">
                        <p class="mb-0" style="font-size:var(--font-sm);color:var(--text-muted);">
                            <i class="fas fa-info-circle me-1"></i>
                            Exchange connection settings (server, PS URI, credentials) are configured in
                            <a href="?page=system_config" class="text-decoration-underline fw-medium">System Config → Edit Domain</a>.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Default Policies -->
            <div class="app-table-card ws-card mb-3" style="border-top:3px solid #fd7e14;">
                <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-sliders-h me-2"></i>Default Policies</h3>
                    <button class="app-table-btn" id="saveDefaultPolicies" style="height:36px;" data-noc-tip="Save default database, quota, and warning settings">
                        <i class="fas fa-save me-1"></i>Save
                    </button>
                </div>
                <div class="p-3">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label style="font-size:var(--font-xs);">Default Database</label>
                            <input type="text" class="app-table-input" id="settingsDefaultDb" placeholder="DB01"
                                style="width:100%;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;font-size:var(--font-xs);">
                        </div>
                        <div class="col-md-4">
                            <label style="font-size:var(--font-xs);">Default Quota (GB)</label>
                            <input type="number" class="app-table-input" id="settingsDefaultQuota" value="10"
                                style="width:100%;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;font-size:var(--font-xs);">
                        </div>
                        <div class="col-md-4">
                            <label style="font-size:var(--font-xs);">Warning Threshold (GB)</label>
                            <input type="number" class="app-table-input" id="settingsWarningQuota" value="8"
                                style="width:100%;height:36px;border:1px solid var(--border-color);border-radius:6px;padding:0 12px;font-size:var(--font-xs);">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Retention Policies -->
            <div class="app-table-card ws-card mb-3" style="border-top:3px solid #6f42c1;">
                <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-clock me-2"></i>Retention Policies</h3>
                    <button class="app-table-btn" id="refreshRetentionPolicies" style="height:36px;" data-noc-tip="Load retention policies from Exchange">
                        <i class="fas fa-sync me-1"></i>Refresh
                    </button>
                </div>
                <div class="p-3" id="exchangeRetentionPolicies">
                    <p class="text-muted mb-0" style="font-size:var(--font-sm);">
                        <i class="fas fa-info-circle me-1"></i>Requires Exchange PowerShell connection.
                    </p>
                </div>
            </div>

            <!-- P3 — Create Mailbox Types -->
            <div class="app-table-card ws-card mb-3" style="border-top:3px solid #198754;">
                <div class="log-title-wrapper app-table-title d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="m-0" style="font-size:var(--font-lg);"><i class="fas fa-plus-circle me-2"></i>Create Mailbox (P3)</h3>
                </div>
                <div class="p-3">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <h5 style="font-size:var(--font-sm);">Shared Mailbox</h5>
                            <input type="text" class="app-table-input mb-2" id="p3SharedName" placeholder="Name" style="width:100%;height:30px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                            <input type="text" class="app-table-input mb-2" id="p3SharedAlias" placeholder="Alias" style="width:100%;height:30px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                            <input type="text" class="app-table-input mb-2" id="p3SharedDisplay" placeholder="Display Name" style="width:100%;height:30px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                            <button class="btn btn-sm btn-outline-success" id="p3CreateShared" style="height:30px;font-size:var(--font-xs);" data-noc-tip="Create a new shared mailbox">Create Shared</button>
                        </div>
                        <div class="col-md-4">
                            <h5 style="font-size:var(--font-sm);">Room Mailbox</h5>
                            <input type="text" class="app-table-input mb-2" id="p3RoomName" placeholder="Room name" style="width:100%;height:30px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                            <input type="text" class="app-table-input mb-2" id="p3RoomAlias" placeholder="Alias" style="width:100%;height:30px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                            <input type="number" class="app-table-input mb-2" id="p3RoomCapacity" placeholder="Capacity" style="width:100%;height:30px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                            <button class="btn btn-sm btn-outline-primary" id="p3CreateRoom" style="height:30px;font-size:var(--font-xs);" data-noc-tip="Create a new room mailbox">Create Room</button>
                        </div>
                        <div class="col-md-4">
                            <h5 style="font-size:var(--font-sm);">Equipment Mailbox</h5>
                            <input type="text" class="app-table-input mb-2" id="p3EquipName" placeholder="Equipment name" style="width:100%;height:30px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                            <input type="text" class="app-table-input mb-2" id="p3EquipAlias" placeholder="Alias" style="width:100%;height:30px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                            <button class="btn btn-sm btn-outline-warning" id="p3CreateEquip" style="height:30px;font-size:var(--font-xs);" data-noc-tip="Create a new equipment mailbox">Create Equipment</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
