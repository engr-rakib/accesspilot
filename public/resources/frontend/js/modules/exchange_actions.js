(function() {
    'use strict';

    let activeIdentity = '';
    let mailboxList = [];
    let exchangeDatabases = [];
    let _exchangeSelectedGroups = [];

    // Exchange operation info map — operator knowledge for every feature
    window.exchangeOpInfo = {
        // Mailbox actions
        mailbox_list: 'Lists all mailboxes matching a search pattern. Wildcards (*) supported.',
        mailbox_search: 'Displays detailed properties and configuration for a specific mailbox.',
        mailbox_enable: 'Activates an Exchange mailbox for an existing AD user. Requires a valid AD account.',
        mailbox_disable: 'Deactivates the Exchange mailbox. The AD user account is NOT affected — mailbox data is retained for 30 days by default.',
        mailbox_update_profile: 'Updates display name, department, phone, and other AD attributes synced to Exchange.',
        mailbox_set_primary_smtp: 'Changes the primary (reply-to) email address. Old primary becomes a secondary alias.',
        mailbox_add_address: 'Adds an additional SMTP alias to the mailbox. The user can receive email at this address.',
        mailbox_remove_address: 'Permanently removes an email alias from the mailbox.',
        mailbox_set_forward: 'Forwards all incoming email to another address. Delivery to the original mailbox can optionally be preserved.',
        mailbox_set_litigation_hold: 'Preserves all mailbox content (including deleted items) for e-discovery. Users cannot permanently delete items while hold is active.',
        mailbox_set_hidden_gal: 'Hides or shows the mailbox in the Global Address List. Hidden users can still receive email.',
        mailbox_set_oof: 'Configures automatic out-of-office replies. Supports internal-only or external sender messages.',
        mailbox_add_full_access: 'Grants another user full access to this mailbox (read, send, delete). Does NOT grant "Send As" permission.',
        mailbox_remove_full_access: 'Revokes full access permission from a user.',
        mailbox_add_send_as: 'Allows another user to send emails that appear to come from this mailbox. Requires separate Full Access permission to open the mailbox.',
        mailbox_remove_send_as: 'Revokes Send-As permission from a user.',
        mailbox_move_request: 'Moves the mailbox to a different database. The user can typically continue working during the move.',
        mailbox_enable_archive: 'Enables an archive mailbox (Auto-Expanding Archive in Exchange Online / on-prem). Additional storage for older items.',
        mailbox_disable_archive: 'Disables the archive mailbox. Archived items are retained on the server for 30 days.',
        mailbox_set_mail_tip: 'Sets a custom message displayed in Outlook when a user addresses this mailbox (e.g. "This is a shared mailbox").',
        mailbox_set_calendar_permissions: 'Grants a user permission to view or manage this mailbox\'s calendar.',
        mailbox_remove_calendar_permissions: 'Removes a user\'s calendar permission.',
        mailbox_set_quota: 'Configures storage limits: IssueWarning (yellow), ProhibitSend (can\'t send), ProhibitSendReceive (can\'t send or receive).',
        mailbox_stats: 'Displays real-time mailbox size, item count, last logon time, and database information.',
        mailbox_user_create: 'Creates a new AD user AND enables their Exchange mailbox in one operation. User account is created as enabled.',
        mailbox_create_shared: 'Creates a shared mailbox (visible in GAL). No AD user login — access is granted via Full Access delegation.',
        mailbox_create_room: 'Creates a room mailbox for meeting spaces. Supports auto-booking and capacity limits.',
        mailbox_create_equipment: 'Creates an equipment mailbox for resources (projectors, vehicles, etc.). Similar to Room but typed as Equipment.',

        // Group actions
        group_search: 'Searches for distribution/security groups by name or alias. Wildcards supported.',
        group_members: 'Lists all members of a distribution group with their display name and email.',
        group_create: 'Creates a new distribution group. Supports Organizational Unit selection for placement.',
        group_delete: 'Permanently deletes the distribution group. Membership is NOT preserved.',

        // Monitoring actions
        monitoring_databases: 'Shows all Exchange mailbox databases with their hosting server. Refreshes via LDAP + PowerShell.',
        monitoring_quota: 'Reports all mailboxes approaching or exceeding their storage quota limits. Uses Get-MailboxStatistics.',
        monitoring_queues: 'Displays current mail flow queues — messages waiting to be delivered. Useful for identifying delivery bottlenecks.',
        monitoring_transport_rules: 'Lists all transport rules (mail flow rules) applied to messages. Rules are processed in priority order.',
        monitoring_message_tracking: 'Searches the message tracking log for email delivery history. Filter by sender, recipient, and date range.',
        monitoring_retention_policies: 'Lists available retention policies that can be applied to mailboxes for email lifecycle management.',

        // Settings / Discovery
        discover: 'Tests the Exchange PowerShell connection. Verifies WinRM, Kerberos/Basic auth, and server reachability.',
        settings_save: 'Saves default policies: default mailbox database, default quota (GB), and warning threshold (GB). These apply to new mailboxes.',

        // P3 Create forms
        create_shared_mailbox: 'Creates a shared mailbox. Requires: Name (visible in GAL), Alias (email prefix), Display Name.',
        create_room_mailbox: 'Creates a room mailbox for meeting spaces. Requires: Room name, Alias, Capacity (max attendees).',
        create_equipment_mailbox: 'Creates an equipment mailbox. Requires: Equipment name, Alias.',

        // Combined Search
        combined_search: 'Searches by Mailbox identity or Group keyword. Fields are mutually exclusive — only one search type at a time.',
    };

    function renderExchangeOpInfoIcon(opKey) {
        var desc = window.exchangeOpInfo && window.exchangeOpInfo[opKey];
        if (!desc) return '';
        return ' <i class="fas fa-info-circle exchange-op-info" style="cursor:help;font-size:12px;opacity:0.6" data-exchange-op="' + opKey + '" data-noc-tip="' + htmlspecialchars(desc) + '"></i>';
    }

    function renderGroupTags() {
        var tagsEl = document.getElementById('exchangeUserGroupTags');
        var hiddenEl = document.getElementById('exchangeUserGroupMembers');
        if (!tagsEl) return;
        tagsEl.innerHTML = '';
        _exchangeSelectedGroups.forEach(function(g, idx) {
            var tag = document.createElement('span');
            tag.className = 'tag-badge';
            tag.innerHTML = htmlspecialchars(g.Name) + ' <i class="fas fa-times" style="cursor:pointer;font-size:10px;" data-idx="' + idx + '"></i>';
            tag.querySelector('.fa-times').addEventListener('click', function() {
                _exchangeSelectedGroups.splice(parseInt(this.dataset.idx), 1);
                renderGroupTags();
            });
            tagsEl.appendChild(tag);
        });
        if (hiddenEl) hiddenEl.value = _exchangeSelectedGroups.map(function(g) { return g.DistinguishedName; }).join(',');
    }

    function init() {
        bindTabs();
        bindCombinedSearch();
        bindMailboxListControls();
        bindGroupCreate();
        bindMailboxUserCreate();
        bindDatabasesRefresh();
        bindQuotaReportRefresh();
        bindMailQueues();
        bindMessageTracking();
        bindTransportRules();
        bindRetentionPolicies();
        bindP3MailboxCreate();
        bindSettingsSave();
        bindTestConnection();
        discoverOnLoad();
        setTimeout(loadDatabases, 500);
    }

    function bindOuTree(displayId, dropdownId, listId, hiddenId, types) {
        var displayEl = document.getElementById(displayId);
        var ddEl = document.getElementById(dropdownId);
        var listEl = document.getElementById(listId);
        var hiddenEl = document.getElementById(hiddenId);
        if (!displayEl || !ddEl || !listEl) return;
        if (displayEl._ouTreeBound) return;
        displayEl._ouTreeBound = true;
        if (window._adTreeCache) window._adTreeCache.unified = [];

        function open() {
            if (!ddEl) return;
            ddEl.style.display = 'block';
            var ws = document.querySelector('.workspace-content-scroll');
            if (ws) ws.classList.add('dropdown-open');
            if (window.adTreeDropdown && window.adTreeDropdown.fetchUnifiedTree) {
                window.adTreeDropdown.fetchUnifiedTree(listEl, function(item) {
                    if (item.clear) { displayEl.value = ''; if (hiddenEl) hiddenEl.value = ''; }
                    else { displayEl.value = item.Name; if (hiddenEl) hiddenEl.value = item.DistinguishedName; }
                    close();
                }, types, displayEl.value);
            }
        }

        function close() {
            if (!ddEl) return;
            ddEl.style.display = 'none';
            var ws = document.querySelector('.workspace-content-scroll');
            if (ws) ws.classList.remove('dropdown-open');
        }

        displayEl.addEventListener('focus', open);
        displayEl.addEventListener('input', open);
        displayEl.addEventListener('click', function() {
            if (ddEl && ddEl.style.display === 'none') open();
        });
        document.addEventListener('click', function(e) {
            var container = displayEl.closest('.custom-select-container');
            if (container && !container.contains(e.target)) close();
        });
        displayEl.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') close();
        });
    }

    function bindGroupMemberSearch() {
        var displayEl = document.getElementById('exchangeUserGroupDisplay');
        var ddEl = document.getElementById('exchangeUserGroupDropdown');
        var listEl = document.getElementById('exchangeUserGroupList');
        if (!displayEl || !ddEl || !listEl) return;
        if (window._adTreeCache) window._adTreeCache.unified = [];

        function open() {
            if (!ddEl) return;
            ddEl.style.display = 'block';
            var ws = document.querySelector('.workspace-content-scroll');
            if (ws) ws.classList.add('dropdown-open');
            if (window.adTreeDropdown && window.adTreeDropdown.fetchUnifiedTree) {
                window.adTreeDropdown.fetchUnifiedTree(listEl, function(item) {
                    if (item.clear) return;
                    if (!_exchangeSelectedGroups.some(function(g) { return g.DistinguishedName === item.DistinguishedName; })) {
                        _exchangeSelectedGroups.push(item);
                        renderGroupTags();
                    }
                }, ['Group'], displayEl.value);
            }
        }

        function close() {
            if (!ddEl) return;
            ddEl.style.display = 'none';
            var ws = document.querySelector('.workspace-content-scroll');
            if (ws) ws.classList.remove('dropdown-open');
        }

        displayEl.addEventListener('focus', open);
        displayEl.addEventListener('input', open);
        displayEl.addEventListener('click', function() {
            if (ddEl && ddEl.style.display === 'none') open();
        });
        document.addEventListener('click', function(e) {
            var container = displayEl.closest('.custom-select-container');
            if (container && !container.contains(e.target)) close();
        });
        displayEl.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') close();
        });
    }

    function bindTabs() {
        const tabs = document.querySelectorAll('#exchangeTabs .noc-tab-item');
        const panes = document.querySelectorAll('.noc-tab-content .tab-pane');
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const target = this.dataset.tab;
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                panes.forEach(p => {
                    p.style.display = p.id === 'tab-' + target ? '' : 'none';
                });
            });
        });
    }

    function bindCombinedSearch() {
        var mbInput = document.getElementById('exchangeMailboxIdentity');
        var grpInput = document.getElementById('exchangeGroupKeyword');
        var go = document.getElementById('exchangeCombinedSearchGo');

        function setLocked(src, other) {
            var srcVal = src.value.trim();
            other.disabled = srcVal.length > 0;
            other.style.opacity = srcVal.length > 0 ? '0.5' : '1';
        }

        if (mbInput && grpInput) {
            mbInput.addEventListener('input', function() {
                setLocked(mbInput, grpInput);
            });
            grpInput.addEventListener('input', function() {
                setLocked(grpInput, mbInput);
            });
            mbInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && go) go.click();
            });
            grpInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && go) go.click();
            });
        }

        if (go && mbInput && grpInput) {
            go.addEventListener('click', function() {
                var mbVal = mbInput.value.trim();
                var grpVal = grpInput.value.trim();

                if (mbVal && grpVal) {
                    showExchangeAction('Error', 'Clear one field — only one search type at a time.');
                    return;
                }

                if (mbVal) {
                    loadMailboxList(mbVal);
                } else if (grpVal) {
                    doGroupSearch(grpVal);
                } else {
                    showExchangeAction('Info', 'Enter a mailbox username or group name to search.');
                }
            });
        }
    }

    function bindMailboxListControls() {
        var add = document.getElementById('exchangeMailboxAddBtn');
        var input = document.getElementById('exchangeMailboxIdentity');

        if (add && input) {
            add.addEventListener('click', function() {
                input.focus();
                input.select();
                var body = document.getElementById('exchangeResultBody');
                var nameSpan = document.getElementById('exchangeResultTitle');
                if (nameSpan) nameSpan.textContent = 'Enable mailbox';
                if (body) {
                    body.innerHTML = '<div class="alert alert-info mb-0">Enter an existing AD username/email, search it, then use the enable mailbox button.</div>';
                }
            });
        }
    }

    function loadMailboxList(keyword) {
        var body = document.getElementById('exchangeResultBody');
        var title = document.getElementById('exchangeResultTitle');
        if (!body) return;
        if (!keyword) {
            showMailboxSearchPrompt();
            return;
        }

        body.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Searching...</p></div>';
        if (title) title.innerHTML = '<i class="fas fa-inbox me-2"></i>Mailbox Results' + renderExchangeOpInfoIcon('mailbox_list') + ' <span class="badge bg-secondary" style="font-size:var(--font-xs);vertical-align:middle;">0</span>';

        fetch('/api/index.php?endpoint=exchange', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({ action: 'mailbox_list', keyword: keyword || '', limit: 50 })
        })
        .then(parseJsonResponse)
        .then(function(data) {
            if (data.success) {
                mailboxList = data.mailboxes || [];
                renderMailboxList(mailboxList, data);
            } else {
                body.innerHTML = '<div class="alert alert-danger mb-0">' + htmlspecialchars(data.message || 'Mailbox search failed.') + '</div>';
            }
        })
        .catch(function(err) {
            body.innerHTML = '<div class="alert alert-danger mb-0">Request failed: ' + htmlspecialchars(err.message) + '</div>';
        });
    }

    function showMailboxSearchPrompt() {
        var body = document.getElementById('exchangeResultBody');
        var title = document.getElementById('exchangeResultTitle');
        if (body) body.innerHTML = '<p class="text-muted mb-0">Search for a mailbox or group to see results.</p>';
        if (title) title.innerHTML = '<i class="fas fa-search me-2"></i>Search results';
    }

    function renderMailboxList(rows, meta) {
        var body = document.getElementById('exchangeResultBody');
        var title = document.getElementById('exchangeResultTitle');
        if (!body) return;

        if (!rows.length) {
            body.innerHTML = '<div class="alert alert-info mb-0">No users found.</div>';
            if (title) title.innerHTML = '<i class="fas fa-inbox me-2"></i>Mailbox Results' + renderExchangeOpInfoIcon('mailbox_list') + ' <span class="badge bg-secondary" style="font-size:var(--font-xs);vertical-align:middle;">0</span>';
            return;
        }

        var html = '<div class="table-responsive" style="max-height:280px;overflow:auto;border:1px solid var(--border-color);border-radius:6px;">';
        html += '<table class="app-data-table" style="width:100%;font-size:var(--font-xs);">';
        html += '<thead style="position:sticky;top:0;z-index:2;background:var(--card-bg);">';
        html += '<tr><th>Display Name</th><th>AD User ID</th><th>Mailbox Status</th><th>Email Address</th></tr>';
        html += '</thead><tbody>';
        rows.forEach(function(row, idx) {
            var selected = row.identity === activeIdentity ? 'background:rgba(13,110,253,.12);' : '';
            html += '<tr class="exchange-mailbox-row" data-identity="' + htmlspecialchars(row.identity || '') + '" data-index="' + idx + '" style="cursor:pointer;' + selected + '">';
            html += '<td><strong>' + htmlspecialchars(row.display_name || row.identity || '') + '</strong></td>';
            html += '<td>' + htmlspecialchars(row.identity || '-') + '</td>';
            html += '<td>' + (row.has_mailbox ? '<span class="status-badge status-success" style="font-size:10px;">Enabled</span>' : '<span class="status-badge status-warning" style="font-size:10px;">No mailbox</span>') + '</td>';
            html += '<td>' + htmlspecialchars(row.email || '-') + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        html += '<div class="d-flex justify-content-between align-items-center mt-2" style="font-size:var(--font-xs);">';
        html += '<span class="text-muted">' + rows.length + ' user' + (rows.length !== 1 ? 's' : '') + ' found';
        if (meta && meta.total_returned !== undefined) html += ' (showing ' + meta.total_returned + ')';
        html += '.</span>';
        html += '<span class="text-muted">' + (activeIdentity ? '1 selected' : '0 selected') + '</span>';
        html += '</div>';

        body.innerHTML = html;
        if (title) title.innerHTML = '<i class="fas fa-inbox me-2"></i>Mailbox Results' + renderExchangeOpInfoIcon('mailbox_list') + ' <span class="badge bg-secondary" style="font-size:var(--font-xs);vertical-align:middle;">' + rows.length + '</span>';

        bindMailboxTableRows(body);
    }

    function bindMailboxTableRows(body) {
        if (!body) body = document.getElementById('exchangeResultBody');
        body.querySelectorAll('.exchange-mailbox-row').forEach(function(row) {
            row.addEventListener('click', function() {
                var identity = this.dataset.identity;
                if (identity) doMailboxSearch(identity);
            });
        });
    }

    function doGroupSearch(keyword) {
        if (!keyword) return;
        var body = document.getElementById('exchangeResultBody');
        var title = document.getElementById('exchangeResultTitle');
        if (!body) return;

        body.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Searching...</p></div>';
        if (title) title.innerHTML = '<i class="fas fa-users me-2"></i>Group Results' + renderExchangeOpInfoIcon('group_search') + ' <span class="badge bg-secondary" style="font-size:var(--font-xs);vertical-align:middle;">0</span>';

        fetch('/api/index.php?endpoint=exchange', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({ action: 'group_search', keyword: keyword })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.groups) {
                renderGroupList(data.groups, body, title);
            } else {
                body.innerHTML = '<div class="alert alert-danger mb-0">' + (data.message || 'Search failed') + '</div>';
            }
        })
        .catch(err => {
            body.innerHTML = '<div class="alert alert-danger mb-0">Request failed: ' + err.message + '</div>';
        });
    }

    function renderGroupList(groups, bodyEl, titleEl) {
        if (!bodyEl) bodyEl = document.getElementById('exchangeResultBody');
        if (!titleEl) titleEl = document.getElementById('exchangeResultTitle');

        if (groups.length === 0) {
            bodyEl.innerHTML = '<div class="alert alert-info mb-0">No groups found.</div>';
            if (titleEl) titleEl.innerHTML = '<i class="fas fa-users me-2"></i>Group Results' + renderExchangeOpInfoIcon('group_search') + ' <span class="badge bg-secondary" style="font-size:var(--font-xs);vertical-align:middle;">0</span>';
            return;
        }

        let html = '<div class="table-responsive"><table class="app-data-table" style="width:100%;">';
        html += '<thead><tr><th>Name</th><th>Email</th><th>Alias</th><th>Members</th><th>Type</th><th>Managed By</th><th></th></tr></thead><tbody>';
        groups.forEach(function(g) {
            const typeLabel = g.is_distribution
                ? '<span class="status-badge status-info">Distribution</span>'
                : '<span class="status-badge status-warning">Security</span>';
            const email = g.primary_smtp || g.mail || '-';
            html += '<tr>';
            html += '<td><strong>' + htmlspecialchars(g.name) + '</strong></td>';
            html += '<td style="font-size:var(--font-xs);">' + htmlspecialchars(email) + '</td>';
            html += '<td>' + htmlspecialchars(g.alias || '-') + '</td>';
            html += '<td>' + g.member_count + '</td>';
            html += '<td>' + typeLabel + '</td>';
            html += '<td style="font-size:var(--font-xs);">' + htmlspecialchars(g.managed_by || '-') + '</td>';
                    html += '<td><button class="btn btn-sm btn-outline-primary view-group-members" data-group="' + htmlspecialchars(g.distinguishedName) + '" style="font-size:var(--font-xs);"><i class="fas fa-users me-1"></i>Members</button></td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        bodyEl.innerHTML = html;
        if (titleEl) titleEl.innerHTML = '<i class="fas fa-users me-2"></i>Group Results' + renderExchangeOpInfoIcon('group_search') + ' <span class="badge bg-secondary" style="font-size:var(--font-xs);vertical-align:middle;">' + groups.length + '</span>';

        bodyEl.querySelectorAll('.view-group-members').forEach(function(btn) {
            btn.addEventListener('click', function() {
                doGroupMembers(this.dataset.group);
            });
        });
    }

    function bindGroupCreate() {
        var toggleBtn = document.getElementById('exchangeGroupCreateBtn');
        var formDiv = document.getElementById('exchangeGroupCreateForm');
        var cancelBtn = document.getElementById('exchangeGroupCreateCancel');
        var submitBtn = document.getElementById('exchangeGroupCreateSubmit');
        var treeBound = false;
        if (toggleBtn && formDiv) {
            toggleBtn.addEventListener('click', function() {
                formDiv.style.display = 'block';
                if (!treeBound) {
                    treeBound = true;
                    bindOuTree('exchangeGroupCreateOUDisplay', 'exchangeGroupCreateOUDropdown', 'exchangeGroupCreateOUList', 'exchangeGroupCreateOU', ['OU', 'Domain']);
                }
            });
        }
        if (cancelBtn && formDiv) {
            cancelBtn.addEventListener('click', function() {
                formDiv.style.display = 'none';
                ['exchangeGroupCreateName','exchangeGroupCreateAlias','exchangeGroupCreateDescription',
                 'exchangeGroupCreateOUDisplay','exchangeGroupCreateOU'].forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.value = '';
                });
            });
        }
        if (submitBtn) {
            submitBtn.addEventListener('click', doGroupCreate);
        }
    }

    function doGroupCreate() {
        var name = document.getElementById('exchangeGroupCreateName');
        var alias = document.getElementById('exchangeGroupCreateAlias');
        var desc = document.getElementById('exchangeGroupCreateDescription');
        var ouDisplay = document.getElementById('exchangeGroupCreateOUDisplay');
        var ou = document.getElementById('exchangeGroupCreateOU');
        var nameVal = name ? name.value.trim() : '';
        if (!nameVal) { alert('Group name is required.'); return; }
        var btn = document.getElementById('exchangeGroupCreateSubmit');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...'; }

        fetch('/api/index.php?endpoint=exchange', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({
                action: 'group_create',
                name: nameVal,
                alias: alias ? alias.value.trim() : '',
                description: desc ? desc.value.trim() : '',
                ou: ou ? ou.value.trim() : ''
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                alert(data.message);
                var formDiv = document.getElementById('exchangeGroupCreateForm');
                if (formDiv) formDiv.style.display = 'none';
                if (name) name.value = '';
                if (alias) alias.value = '';
                if (desc) desc.value = '';
                if (ou) ou.value = '';
                if (ouDisplay) ouDisplay.value = '';
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(function(err) {
            alert('Request failed: ' + err.message);
        })
        .finally(function() {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check me-1"></i>Create Group'; }
        });
    }

    function bindMailboxUserCreate() {
        var toggleBtn = document.getElementById('exchangeMailboxUserCreateBtn');
        var formDiv = document.getElementById('exchangeMailboxUserCreateForm');
        var cancelBtn = document.getElementById('exchangeMailboxUserCreateCancel');
        var searchBtn = document.getElementById('exchangeSearchIdentityGo');
        var searchInput = document.getElementById('exchangeSearchIdentity');
        var existingToggle = document.getElementById('exchangeExistingUserToggle');
        var existingSection = document.getElementById('exchangeExistingUserSection');
        var newSection = document.getElementById('exchangeNewUserSection');
        var submitBtn = document.getElementById('exchangeUserCreateSubmit');
        var treeBound = false;

        function toggleMode(isExisting) {
            if (existingSection) existingSection.style.display = isExisting ? 'block' : 'none';
            if (newSection) newSection.style.display = isExisting ? 'none' : 'block';
            if (!isExisting && !treeBound) {
                treeBound = true;
                bindOuTree('exchangeUserCreateOUDisplay', 'exchangeUserCreateOUDropdown', 'exchangeUserCreateOUList', 'exchangeUserCreateOU', ['OU', 'Domain']);
                bindGroupMemberSearch();
            }
        }

        if (toggleBtn && formDiv) {
            toggleBtn.addEventListener('click', function() {
                formDiv.style.display = 'block';
                toggleMode(existingToggle ? existingToggle.checked : true);
                if (searchInput) searchInput.focus();
            });
        }
        if (cancelBtn && formDiv) {
            cancelBtn.addEventListener('click', function() {
                formDiv.style.display = 'none';
                if (searchInput) searchInput.value = '';
                ['exchangeUserCreateFirstName','exchangeUserCreateLastName','exchangeUserCreateUsername',
                 'exchangeUserCreateDisplayName','exchangeUserCreateEmail','exchangeUserCreateOUDisplay',
                 'exchangeUserCreateOU'].forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.value = '';
                });
                _exchangeSelectedGroups = [];
                renderGroupTags();
            });
        }
        if (existingToggle) {
            existingToggle.addEventListener('change', function() {
                toggleMode(this.checked);
            });
        }
        if (searchBtn && searchInput) {
            searchBtn.addEventListener('click', function() {
                var val = searchInput.value.trim();
                if (!val) return;
                formDiv.style.display = 'none';
                searchInput.value = '';
                document.getElementById('exchangeMailboxIdentity').value = val;
                document.getElementById('exchangeCombinedSearchGo').click();
            });
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && searchBtn) searchBtn.click();
            });
        }
        if (submitBtn) {
            submitBtn.addEventListener('click', doMailboxUserCreate);
        }
    }

    function doMailboxUserCreate() {
        var firstName = document.getElementById('exchangeUserCreateFirstName');
        var lastName = document.getElementById('exchangeUserCreateLastName');
        var username = document.getElementById('exchangeUserCreateUsername');
        var displayName = document.getElementById('exchangeUserCreateDisplayName');
        var email = document.getElementById('exchangeUserCreateEmail');
        var ou = document.getElementById('exchangeUserCreateOU');

        var fn = firstName ? firstName.value.trim() : '';
        var ln = lastName ? lastName.value.trim() : '';
        var un = username ? username.value.trim() : '';
        if (!fn || !ln || !un) { showExchangeFeedback(false, 'Validation Error', 'First name, last name, and username are required.'); return; }

        var btn = document.getElementById('exchangeUserCreateSubmit');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...'; }

        fetch('/api/index.php?endpoint=exchange', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({
                action: 'mailbox_user_create',
                firstName: fn,
                lastName: ln,
                username: un,
                displayName: displayName ? displayName.value.trim() : '',
                email: email ? email.value.trim() : '',
                ou: ou ? ou.value.trim() : '',
                groups: _exchangeSelectedGroups.map(function(g) { return g.DistinguishedName; })
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showExchangeFeedback(true, 'User Created', data.message || 'User created and mailbox enabled.');
                var formDiv = document.getElementById('exchangeMailboxUserCreateForm');
                if (formDiv) formDiv.style.display = 'none';
                if (firstName) firstName.value = '';
                if (lastName) lastName.value = '';
                if (username) username.value = '';
                if (displayName) displayName.value = '';
                if (email) email.value = '';
                if (ou) ou.value = '';
                var ouDisplay = document.getElementById('exchangeUserCreateOUDisplay');
                if (ouDisplay) ouDisplay.value = '';
                _exchangeSelectedGroups = [];
                renderGroupTags();
                loadMailboxList('');
            } else {
                showExchangeFeedback(false, 'Creation Failed', data.message || 'Unknown error');
            }
        })
        .catch(function(err) {
            showExchangeFeedback(false, 'Request Error', err.message);
        })
        .finally(function() {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-plus me-1"></i>Create User & Enable Mailbox'; }
        });
    }

    function doGroupMembers(groupDn) {
        var bodyEl = document.getElementById('exchangeResultBody');
        var nameEl = document.getElementById('exchangeResultTitle');

        if (bodyEl) bodyEl.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading members...</p></div>';
        if (nameEl) {
            const match = groupDn.match(/^CN=([^,]+)/i);
            nameEl.innerHTML = '<i class="fas fa-users me-2"></i>' + htmlspecialchars(match ? match[1] : groupDn);
        }

        fetch('/api/index.php?endpoint=exchange', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({ action: 'group_members', group: groupDn })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.members) {
                renderGroupMembers(data.group, data.members, bodyEl, nameEl);
            } else {
                if (bodyEl) bodyEl.innerHTML = '<div class="alert alert-danger mb-0">' + (data.message || 'Failed to load members') + '</div>';
            }
        })
        .catch(err => {
            if (bodyEl) bodyEl.innerHTML = '<div class="alert alert-danger mb-0">Request failed: ' + err.message + '</div>';
        });
    }

    function renderGroupMembers(group, members, bodyEl, nameEl) {
        if (!bodyEl) bodyEl = document.getElementById('exchangeResultBody');
        if (!nameEl) nameEl = document.getElementById('exchangeResultTitle');
        if (nameEl && group) nameEl.innerHTML = '<i class="fas fa-users me-2"></i>' + htmlspecialchars(group.Name || nameEl.textContent || '') + renderExchangeOpInfoIcon('group_members');

        let html = '<div class="row g-3">';
        html += '<div class="col-12">';
        html += '<div class="mb-1"><strong>Name:</strong> ' + htmlspecialchars(group?.Name || 'N/A') + '</div>';
        html += '<div class="mb-1"><strong>Email:</strong> ' + htmlspecialchars(group?.mail || group?.primary_smtp || 'N/A') + '</div>';
        html += '<div class="mb-1"><strong>Managed By:</strong> ' + htmlspecialchars(group?.managed_by || 'N/A') + '</div>';
        html += '<div class="mb-1"><strong>Members:</strong> ' + (members ? members.length : 0) + '</div>';
        html += '</div>';
        html += '<div class="col-12"><hr class="my-1"></div>';
        html += '<div class="col-12"><h4 style="font-size:var(--font-md);">Members (' + (members ? members.length : 0) + ')' + renderExchangeOpInfoIcon('group_members') + '</h4></div>';
        html += '</div>';

        if (members && members.length > 0) {
            html += '<div class="table-responsive"><table class="app-data-table" style="width:100%;">';
            html += '<thead><tr><th>Name</th><th>Username</th><th>Type</th></tr></thead><tbody>';
            members.forEach(function(m) {
                const icon = m.ObjectClass === 'group' ? 'fa-users' : 'fa-user';
                html += '<tr>';
                html += '<td><i class="fas ' + icon + ' me-1"></i>' + htmlspecialchars(m.Name || m.DisplayName || '') + '</td>';
                html += '<td>' + htmlspecialchars(m.SamAccountName || '') + '</td>';
                html += '<td>' + (m.ObjectClass === 'group' ? 'Group' : 'User') + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
        } else {
            html += '<p class="text-muted">No members found.</p>';
        }

        bodyEl.innerHTML = html;

        var delBtn = bodyEl.querySelector('#btnDeleteGroup');
        if (delBtn) {
            delBtn.addEventListener('click', function() {
                if (confirm('Delete distribution group "' + (group?.Name || '') + '"? This cannot be undone.')) {
                    fetch('/api/index.php?endpoint=exchange', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                        body: JSON.stringify({ action: 'group_delete', identity: delBtn.dataset.group })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            alert(data.message);
                            var kw = document.getElementById('exchangeGroupKeyword');
                            if (kw && kw.value.trim()) doGroupSearch(kw.value.trim());
                        } else {
                            alert('Error: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(function(err) { alert('Request failed: ' + err.message); });
                }
            });
        }
    }

    function bindDatabasesRefresh() {
        const btn = document.getElementById('refreshDatabases');
        if (btn) {
            btn.addEventListener('click', loadDatabases);
        }
    }

    function bindQuotaReportRefresh() {
        const btn = document.getElementById('refreshQuotaReport');
        if (btn) {
            btn.addEventListener('click', loadQuotaReport);
        }
    }

    function loadQuotaReport() {
        const el = document.getElementById('exchangeQuotaReport');
        if (el) el.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading quota report...</p></div>';

        fetch('/api/index.php?endpoint=exchange', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({ action: 'monitoring_quota' })
        })
        .then(function(r) { return r.json(); })
        .then(data => {
            if (el) {
                if (data.success && data.mailboxes) {
                    let html = '';
                    const warnings = data.quota_warnings || [];
                    if (warnings.length > 0) {
                        html += '<div class="alert alert-warning mb-2">' + renderExchangeOpInfoIcon('monitoring_quota') + ' <i class="fas fa-exclamation-triangle me-1"></i>' + warnings.length + ' user(s) near quota.</div>';
                        html += '<div class="table-responsive"><table class="app-data-table" style="width:100%;">';
                        html += '<thead><tr><th>User</th><th>Database</th><th>Total Item Size</th><th>Item Count</th></tr></thead><tbody>';
                        warnings.forEach(function(w) {
                            html += '<tr>';
                            html += '<td>' + htmlspecialchars(w.DisplayName || w.Identity || '') + '</td>';
                            html += '<td>' + htmlspecialchars(w.Database || '-') + '</td>';
                            html += '<td>' + htmlspecialchars(w.TotalItemSize || '-') + '</td>';
                            html += '<td>' + (w.ItemCount || '-') + '</td>';
                            html += '</tr>';
                        });
                        html += '</tbody></table></div>';
                    } else {
                        html = '<div class="alert alert-success mb-0"><i class="fas fa-check-circle me-1"></i>' + (data.message || 'No quota warnings.') + '</div>';
                    }
                    el.innerHTML = html;
                } else {
                    el.innerHTML = '<div class="alert alert-warning mb-0">' + (data.message || 'Quota report requires PowerShell connection to Exchange server.') + '<pre class="mt-2 mb-0" style="font-size:var(--font-xs);white-space:pre-wrap;">' + htmlspecialchars(data.ps_output || '') + '</pre></div>';
                }
            }
        })
        .catch(err => {
            if (el) el.innerHTML = '<div class="alert alert-danger mb-0">Request failed: ' + err.message + '</div>';
        });
    }

    function loadDatabases() {
        const el = document.getElementById('exchangeDatabaseStatus');
        if (el) el.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading databases...</p></div>';

        fetch('/api/index.php?endpoint=exchange', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({ action: 'monitoring_databases' })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.databases) {
                exchangeDatabases = data.databases;
                refreshMailboxDatabaseSelects();
            }
            if (el) {
                // Update summary cards
                const dbCount = document.getElementById('monDbCount');
                const mbxCount = document.getElementById('monMailboxCount');
                const srvCount = document.getElementById('monServerCount');
                if (dbCount) dbCount.textContent = data.total || (data.databases ? data.databases.length : '-');
                if (mbxCount) mbxCount.textContent = data.mailbox_count != null ? data.mailbox_count : '-';
                if (srvCount) srvCount.textContent = data.server_count || '-';

                if (data.success && data.databases) {
                    let html = '';
                    if (data.databases.length === 0) {
                        html = '<p class="text-muted mb-0">' + renderExchangeOpInfoIcon('monitoring_databases') + ' No Exchange databases found via LDAP.</p>';
                    } else {
                        html += '<div class="mb-1">' + renderExchangeOpInfoIcon('monitoring_databases') + '</div>';
                        html += '<div class="table-responsive"><table class="app-data-table" style="width:100%;">';
                        html += '<thead><tr><th>Name</th><th>Server</th><th>Description</th></tr></thead><tbody>';
                        data.databases.forEach(function(db) {
                            html += '<tr>';
                            html += '<td><strong>' + htmlspecialchars(db.name || '') + '</strong></td>';
                            html += '<td>' + htmlspecialchars(db.server || '-') + '</td>';
                            html += '<td style="font-size:var(--font-xs);">' + htmlspecialchars(db.description || '') + '</td>';
                            html += '</tr>';
                        });
                        html += '</tbody></table></div>';
                    }
                    el.innerHTML = html;
                } else {
                    el.innerHTML = '<div class="alert alert-warning mb-0">' + (data.message || 'Failed to load databases') + '</div>';
                }
            }
        })
        .catch(err => {
            if (el) el.innerHTML = '<div class="alert alert-danger mb-0">Request failed: ' + err.message + '</div>';
        });
    }

    function refreshMailboxDatabaseSelects() {
        document.querySelectorAll('.mb-enable-database').forEach(function(select) {
            var current = select.value;
            var html = '<option value="">Auto select database</option>';
            exchangeDatabases.forEach(function(db) {
                var name = db.name || '';
                if (name) html += '<option value="' + htmlspecialchars(name) + '">' + htmlspecialchars(name) + '</option>';
            });
            select.innerHTML = html;
            if (current) select.value = current;
        });
        if (exchangeDatabases.length) {
            document.querySelectorAll('.mb-enable-db-empty').forEach(function(el) {
                el.style.display = 'none';
            });
        }
        document.querySelectorAll('.mb-move-db').forEach(function(select) {
            var current = select.value;
            select.innerHTML = mailboxDatabaseOptions('Select target database', current);
            if (current) select.value = current;
        });
    }

    function mailboxDatabaseOptions(emptyLabel, currentValue) {
        var html = '<option value="">' + htmlspecialchars(emptyLabel || 'Select database') + '</option>';
        var hasCurrent = false;
        exchangeDatabases.forEach(function(db) {
            var name = db.name || '';
            if (!name) return;
            hasCurrent = hasCurrent || name === currentValue;
            html += '<option value="' + htmlspecialchars(name) + '">' + htmlspecialchars(name) + (db.server ? ' (' + htmlspecialchars(db.server) + ')' : '') + '</option>';
        });
        if (currentValue && !hasCurrent) {
            html += '<option value="' + htmlspecialchars(currentValue) + '">' + htmlspecialchars(currentValue) + '</option>';
        }
        return html;
    }

    function bindTestConnection() {
        const btn = document.getElementById('exchangeTestConnection');
        if (btn) {
            btn.addEventListener('click', testConnection);
        }
    }

    function discoverOnLoad() {
        const status = document.getElementById('exchangeServerStatus');
        const info = document.getElementById('exchangeConnectionInfo');
        fetch('/api/index.php?endpoint=exchange', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({ action: 'discover' })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (status) {
                    status.className = 'status-badge status-success';
                    status.innerHTML = '<i class="fas fa-check-circle me-1"></i>' + (data.server || 'Exchange connected');
                }
                if (info) {
                    renderConnectionInfo(data);
                }
            } else {
                if (status) {
                    status.className = 'status-badge status-warning';
                    status.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>' + (data.message || 'Not detected');
                }
                if (info) {
                    info.innerHTML = '<p class="text-muted">' + (data.message || 'Exchange server auto-detection failed. Configure manually in Settings tab.') + '</p>';
                }
            }
        })
        .catch(() => {
            if (status) {
                status.className = 'status-badge status-failed';
                status.innerHTML = '<i class="fas fa-times-circle me-1"></i>Discovery failed';
            }
        });
    }

    function doMailboxSearch(identity) {
        if (!identity) return;
        activeIdentity = identity;
        const body = document.getElementById('exchangeResultBody');
        const nameSpan = document.getElementById('exchangeResultTitle');

        if (body) body.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Searching...</p></div>';
        if (nameSpan) nameSpan.innerHTML = '<i class="fas fa-user me-2"></i>' + htmlspecialchars(identity);

        fetch('/api/index.php?endpoint=exchange', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({ action: 'mailbox_search', identity: identity })
        })
        .then(parseJsonResponse)
        .then(data => {
            if (data.success && data.user) {
                renderMailboxResult(data.user, data.mailbox, body, nameSpan);
                markMailboxRowSelected(data.user.SamAccountName || identity);
            } else {
                if (body) body.innerHTML = '<div class="alert alert-danger mb-0">' + (data.message || 'User not found') + '</div>';
            }
        })
        .catch(err => {
            if (body) body.innerHTML = '<div class="alert alert-danger mb-0">Request failed: ' + err.message + '</div>';
        });
    }

    function markMailboxRowSelected(identity) {
        var body = document.getElementById('exchangeResultBody');
        if (!body || !identity) return;
        body.querySelectorAll('.exchange-mailbox-row').forEach(function(row) {
            row.style.background = row.dataset.identity === identity ? 'rgba(13,110,253,.12)' : '';
        });
    }

    function renderMailboxResult(user, mailbox, body, nameSpan) {
        if (!body) return;
        const mb = mailbox || {};

        // Build mailbox info HTML
        let html = '<div class="exchange-detail" style="font-size:var(--font-xs);line-height:1.45;">';

        // Mailbox Status
        html += '<div class="mb-3 pb-2" style="border-bottom:1px solid var(--border-color);">';
        html += '<div class="d-flex align-items-center gap-2 mb-2">';
        html += '<strong>Mailbox:</strong> ' + renderExchangeOpInfoIcon('mailbox_search') + ' ';
        if (mb.has_mailbox) {
            html += '<span class="status-badge status-success"><i class="fas fa-check-circle me-1"></i>Enabled</span>';
        } else {
            html += '<span class="status-badge status-warning"><i class="fas fa-times-circle me-1"></i>Not enabled</span>';
        }
        if (mb.mailbox_disabled) {
            html += '<span class="status-badge status-failed ms-2"><i class="fas fa-pause-circle me-1"></i>Disabled</span>';
        }
        html += '</div>';

        if (mb.has_mailbox) {
            html += '<div class="d-flex flex-wrap gap-1">';
            html += '<span class="status-badge status-info" style="font-size:10px;" data-noc-tip="Exchange recipient type">' + htmlspecialchars(mb.recipient_type || 'UserMailbox') + '</span>';
            if (mb.home_database) {
                html += '<span class="status-badge status-neutral" style="font-size:10px;" data-noc-tip="Current mailbox database">DB: ' + htmlspecialchars(shortExchangeDn(mb.home_database)) + '</span>';
            }
            if (mb.when_created && mb.when_created !== 'N/A') {
                html += '<span class="status-badge status-neutral" style="font-size:10px;" data-noc-tip="Mailbox creation time">Created: ' + htmlspecialchars(mb.when_created) + '</span>';
            }
            if (mb.hidden_from_gal) {
                html += '<span class="status-badge status-warning" style="font-size:10px;" data-noc-tip="Hidden from the global address list"><i class="fas fa-eye-slash me-1"></i>Hidden from GAL</span>';
            }
            html += '</div>';
        }
        html += '</div>';

        if (!mb.has_mailbox) {
            html += renderEnableMailboxPanel(user);
            body.innerHTML = html;
            bindEnableMailboxPanel(body);
            if (nameSpan) nameSpan.innerHTML = '<i class="fas fa-user me-2"></i>' + htmlspecialchars(user.DisplayName || user.SamAccountName || activeIdentity);
            const enableBtn = document.querySelector('.action-exchange-enable');
            const disableBtn = document.querySelector('.action-exchange-disable');
            if (enableBtn) enableBtn.style.display = 'none';
            if (disableBtn) disableBtn.style.display = 'none';
            return;
        }

        html += renderMailboxEditPanel(user, mb);

        // Email Addresses
        html += '<div class="mb-3 pb-2" style="border-bottom:1px solid var(--border-color);">';
        html += '<div class="d-flex align-items-center justify-content-between gap-2 mb-2">';
        html += '<strong><i class="fas fa-envelope me-1"></i>Email Addresses' + renderExchangeOpInfoIcon('mailbox_add_address') + '</strong>';
        html += '<span class="text-muted" style="font-size:10px;">New address is added as alias; promote any address to primary.</span>';
        html += '</div>';
        if (mb.proxy_addresses && mb.proxy_addresses.length > 0) {
            html += '<div class="table-responsive" style="max-height:160px;overflow:auto;border:1px solid var(--border-color);border-radius:6px;">';
            html += '<table class="app-data-table" style="width:100%;font-size:var(--font-xs);"><thead><tr><th>Address</th><th style="width:74px;">Type</th><th style="width:72px;">Action</th></tr></thead><tbody>';
            mb.proxy_addresses.forEach(function(addr) {
                const label = addr.is_primary ? '<span class="status-badge status-info" style="font-size:10px;">Primary</span>' : '<span class="status-badge status-neutral" style="font-size:10px;">Alias</span>';
                html += '<tr>';
                html += '<td style="overflow-wrap:anywhere;">' + htmlspecialchars(addr.address) + '</td>';
                html += '<td>' + label + '</td>';
                html += '<td>';
                if (!addr.is_primary) {
                    html += '<button class="btn btn-sm btn-outline-primary mb-action-primary-row" data-email="' + htmlspecialchars(addr.address) + '" style="height:24px;width:28px;padding:0;" data-noc-tip="Make primary SMTP"><i class="fas fa-star"></i></button>';
                    html += '<button class="btn btn-sm btn-outline-danger mb-action-alias-row-remove ms-1" data-email="' + htmlspecialchars(addr.address) + '" style="height:24px;width:28px;padding:0;" data-noc-tip="Remove email address"><i class="fas fa-minus"></i></button>';
                }
                html += '</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
        } else {
            html += '<p class="text-muted mb-0">' + htmlspecialchars(user.emailAddress && user.emailAddress !== 'N/A' ? user.emailAddress : 'No email addresses') + '</p>';
        }
        html += '<div class="row g-2 mt-2">';
        html += '<div class="col-md-6">';
        html += '<label style="font-size:10px;color:var(--text-muted);">Primary SMTP <i class="fas fa-info-circle text-muted" data-noc-tip="Primary reply address. Setting this promotes the address and demotes the old primary."></i></label>';
        html += '<div class="d-flex gap-1">';
        html += '<input type="email" class="mb-smtp-primary" value="' + htmlspecialchars(mb.primary_smtp || '') + '" placeholder="primary@domain.com" style="flex:1;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;min-width:0;">';
        html += '<button class="btn btn-sm btn-outline-primary mb-action-smtp" style="height:28px;font-size:var(--font-xs);width:34px;padding:0;" data-noc-tip="Set primary SMTP"><i class="fas fa-save"></i></button>';
        html += '</div></div>';
        html += '<div class="col-md-6">';
        html += '<label style="font-size:10px;color:var(--text-muted);">Add email address <i class="fas fa-info-circle text-muted" data-noc-tip="Adds an extra SMTP address as alias, for example user@marcelbd.com or user@accesspilot.com."></i></label>';
        html += '<div class="d-flex gap-1">';
        html += '<input type="email" class="mb-smtp-alias" placeholder="alias@domain.com" style="flex:1;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;min-width:0;">';
        html += '<button class="btn btn-sm btn-outline-primary mb-action-alias-add" style="height:28px;font-size:var(--font-xs);width:34px;padding:0;" data-noc-tip="Add email address"><i class="fas fa-plus"></i></button>';
        html += '</div></div>';
        html += '</div>';
        html += '</div>';

        if (mb.has_mailbox) {
            html += '<div class="mb-3 pb-2" style="border-bottom:1px solid var(--border-color);">';
            html += '<div class="d-flex align-items-center justify-content-between mb-2">';
            html += '<strong><i class="fas fa-chart-pie me-1"></i>Mailbox Size' + renderExchangeOpInfoIcon('mailbox_stats') + '</strong>';
            html += '<span id="exchangeMailboxQuotaBadge" class="status-badge status-info" style="font-size:10px;">Stats</span>';
            html += '</div>';
            html += '<div id="exchangeMailboxStatsBox" class="text-muted">Loading mailbox size...</div>';
            html += renderAppliedQuota(mb);
            html += '</div>';
        }

        html += '</div>';

        // Add action buttons row — all cards in 2-column grid, no headers
        if (mb.has_mailbox) {
            html += '<div class="row g-2 mt-2">';

            // Forwarding
            html += '<div class="col-sm-6 col-12">';
            html += '<div class="p-2" style="border:1px solid var(--border-color);border-radius:6px;height:100%">';
            html += '<label style="font-size:var(--font-xs);font-weight:bold;">Forwarding ' + renderExchangeOpInfoIcon('mailbox_set_forward') + ' <i class="fas fa-info-circle text-muted" data-noc-tip="Redirect incoming mail to another mailbox or SMTP address."></i></label>';
            html += '<div class="d-flex gap-1 mt-1">';
            html += '<input type="email" class="mb-fwd-target" placeholder="forward@domain.com" style="flex:1;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;" data-noc-tip="Forwarding address for incoming emails">';
            html += '<button class="btn btn-sm btn-outline-primary mb-action-fwd" style="height:28px;font-size:var(--font-xs);" data-noc-tip="Set forwarding to this address"><i class="fas fa-share"></i></button>';
            html += '<button class="btn btn-sm btn-outline-danger mb-action-fwd-clear" style="height:28px;font-size:var(--font-xs);" data-noc-tip="Remove forwarding"><i class="fas fa-times"></i></button>';
            html += '</div></div></div>';

            // Litigation Hold
            html += '<div class="col-sm-6 col-12">';
            html += '<div class="p-2" style="border:1px solid var(--border-color);border-radius:6px;height:100%">';
            html += '<label style="font-size:var(--font-xs);font-weight:bold;">Litigation Hold ' + renderExchangeOpInfoIcon('mailbox_set_litigation_hold') + ' <i class="fas fa-info-circle text-muted" data-noc-tip="Preserves all mailbox content (deleted/edited) for e-discovery. Safe to enable."></i></label>';
            html += '<div class="d-flex gap-1 mt-1 align-items-center">';
            html += '<span class="mb-lit-status badge bg-secondary" style="font-size:8px">Off</span>';
            html += '<button class="btn btn-sm btn-outline-warning mb-action-lit-on" style="height:28px;font-size:var(--font-xs);flex:1;" data-noc-tip="Enable litigation hold — preserves all items for compliance"><i class="fas fa-lock me-1"></i>Enable</button>';
            html += '<button class="btn btn-sm btn-outline-secondary mb-action-lit-off" style="height:28px;font-size:var(--font-xs);flex:1;" data-noc-tip="Disable litigation hold — items no longer preserved"><i class="fas fa-unlock me-1"></i>Disable</button>';
            html += '</div></div></div>';

            // GAL Visibility
            html += '<div class="col-sm-6 col-12">';
            html += '<div class="p-2" style="border:1px solid var(--border-color);border-radius:6px;height:100%">';
            html += '<label style="font-size:var(--font-xs);font-weight:bold;">GAL Visibility ' + renderExchangeOpInfoIcon('mailbox_set_hidden_gal') + ' <i class="fas fa-info-circle text-muted" data-noc-tip="Show or hide this mailbox in the global address list."></i></label>';
            html += '<div class="d-flex gap-1 mt-1 align-items-center">';
            html += '<span class="mb-gal-status badge ' + (mb.hidden_from_gal ? 'bg-warning' : 'bg-success') + '" style="font-size:8px">' + (mb.hidden_from_gal ? 'Hidden' : 'Visible') + '</span>';
            html += '<button class="btn btn-sm btn-outline-warning mb-action-hide-gal" style="height:28px;font-size:var(--font-xs);flex:1;" data-noc-tip="Hide from address book"><i class="fas fa-eye-slash me-1"></i>Hide</button>';
            html += '<button class="btn btn-sm btn-outline-success mb-action-show-gal" style="height:28px;font-size:var(--font-xs);flex:1;" data-noc-tip="Show in address book"><i class="fas fa-eye me-1"></i>Show</button>';
            html += '</div></div></div>';

            // OOF Auto-Reply
            html += '<div class="col-sm-6 col-12">';
            html += '<div class="p-2" style="border:1px solid var(--border-color);border-radius:6px;height:100%">';
            html += '<label style="font-size:var(--font-xs);font-weight:bold;">OOF Auto-Reply ' + renderExchangeOpInfoIcon('mailbox_set_oof') + ' <i class="fas fa-info-circle text-muted" data-noc-tip="Enable or disable automatic out-of-office replies."></i></label>';
            html += '<div class="d-flex gap-1 mt-1 align-items-center">';
            html += '<span class="mb-oof-status badge bg-secondary" style="font-size:8px">Off</span>';
            html += '<button class="btn btn-sm btn-outline-primary mb-action-oof-enable" style="height:28px;font-size:var(--font-xs);flex:1;" data-noc-tip="Enable OOF auto-reply"><i class="fas fa-reply me-1"></i>Enable</button>';
            html += '<button class="btn btn-sm btn-outline-secondary mb-action-oof-disable" style="height:28px;font-size:var(--font-xs);flex:1;" data-noc-tip="Disable OOF auto-reply"><i class="fas fa-times me-1"></i>Disable</button>';
            html += '</div></div></div>';

            // Full Access
            html += '<div class="col-sm-6 col-12">';
            html += '<div class="p-2" style="border:1px solid var(--border-color);border-radius:6px;height:100%">';
            html += '<label style="font-size:var(--font-xs);font-weight:bold;">Full Access ' + renderExchangeOpInfoIcon('mailbox_add_full_access') + ' <i class="fas fa-info-circle text-muted" data-noc-tip="Grant another user permission to open this mailbox and read its contents."></i></label>';
            html += '<div class="d-flex gap-1 mt-1">';
            html += '<input type="text" class="mb-fullaccess-user" placeholder="DOMAIN\\user" style="flex:1;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;" data-noc-tip="Domain\\username to grant access">';
            html += '<button class="btn btn-sm btn-outline-primary mb-action-fa-add" style="height:28px;font-size:var(--font-xs);" data-noc-tip="Add full access for this user"><i class="fas fa-plus"></i></button>';
            html += '<button class="btn btn-sm btn-outline-danger mb-action-fa-remove" style="height:28px;font-size:var(--font-xs);" data-noc-tip="Remove full access for this user"><i class="fas fa-minus"></i></button>';
            html += '</div></div></div>';

            // Send-As
            html += '<div class="col-sm-6 col-12">';
            html += '<div class="p-2" style="border:1px solid var(--border-color);border-radius:6px;height:100%">';
            html += '<label style="font-size:var(--font-xs);font-weight:bold;">Send-As ' + renderExchangeOpInfoIcon('mailbox_add_send_as') + ' <i class="fas fa-info-circle text-muted" data-noc-tip="Allow another user to send email as this mailbox."></i></label>';
            html += '<div class="d-flex gap-1 mt-1">';
            html += '<input type="text" class="mb-sendas-user" placeholder="DOMAIN\\user" style="flex:1;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;" data-noc-tip="Domain\\username who can send as this mailbox">';
            html += '<button class="btn btn-sm btn-outline-primary mb-action-sa-add" style="height:28px;font-size:var(--font-xs);" data-noc-tip="Grant send-as permission"><i class="fas fa-plus"></i></button>';
            html += '<button class="btn btn-sm btn-outline-danger mb-action-sa-remove" style="height:28px;font-size:var(--font-xs);" data-noc-tip="Revoke send-as permission"><i class="fas fa-minus"></i></button>';
            html += '</div></div></div>';

            // Move Database
            html += '<div class="col-sm-6 col-12">';
            html += '<div class="p-2" style="border:1px solid var(--border-color);border-radius:6px;height:100%">';
            html += '<label style="font-size:var(--font-xs);font-weight:bold;">Move Database ' + renderExchangeOpInfoIcon('mailbox_move_request') + ' <i class="fas fa-info-circle text-muted" data-noc-tip="Move mailbox to a different mailbox database."></i></label>';
            html += '<div class="d-flex gap-1 mt-1">';
            html += '<select class="mb-move-db" style="flex:1;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;min-width:0;" data-noc-tip="Choose the target database for the move">' + mailboxDatabaseOptions(exchangeDatabases.length ? 'Select target database' : 'Loading database list', '') + '</select>';
            html += '<button class="btn btn-sm btn-outline-warning mb-action-move" style="height:28px;font-size:var(--font-xs);" data-noc-tip="Start database move request"><i class="fas fa-arrow-right"></i></button>';
            html += '</div></div></div>';

            // Archive Mailbox
            html += '<div class="col-sm-6 col-12">';
            html += '<div class="p-2" style="border:1px solid var(--border-color);border-radius:6px;height:100%">';
            html += '<label style="font-size:var(--font-xs);font-weight:bold;">Archive Mailbox ' + renderExchangeOpInfoIcon('mailbox_enable_archive') + ' <i class="fas fa-info-circle text-muted" data-noc-tip="In-place archive for additional mailbox storage."></i></label>';
            html += '<div class="d-flex gap-1 mt-1">';
            html += '<button class="btn btn-sm btn-outline-success mb-action-arch-enable" style="height:28px;font-size:var(--font-xs);flex:1;" data-noc-tip="Enable archive mailbox"><i class="fas fa-archive me-1"></i>Enable</button>';
            html += '<button class="btn btn-sm btn-outline-danger mb-action-arch-disable" style="height:28px;font-size:var(--font-xs);flex:1;" data-noc-tip="Disable archive mailbox"><i class="fas fa-archive me-1"></i>Disable</button>';
            html += '</div></div></div>';

            // Mail Tip
            html += '<div class="col-sm-6 col-12">';
            html += '<div class="p-2" style="border:1px solid var(--border-color);border-radius:6px;height:100%">';
            html += '<label style="font-size:var(--font-xs);font-weight:bold;">Mail Tip ' + renderExchangeOpInfoIcon('mailbox_set_mail_tip') + ' <i class="fas fa-info-circle text-muted" data-noc-tip="Message shown to senders before they send mail to this mailbox."></i></label>';
            html += '<div class="d-flex gap-1 mt-1">';
            html += '<input type="text" class="mb-mailtip-text" placeholder="Mail tip message" style="flex:1;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;" data-noc-tip="Text to display when someone addresses mail to this mailbox">';
            html += '<button class="btn btn-sm btn-outline-primary mb-action-mailtip-set" style="height:28px;font-size:var(--font-xs);" data-noc-tip="Save mail tip"><i class="fas fa-save"></i></button>';
            html += '<button class="btn btn-sm btn-outline-secondary mb-action-mailtip-clear" style="height:28px;font-size:var(--font-xs);" data-noc-tip="Clear mail tip"><i class="fas fa-times"></i></button>';
            html += '</div></div></div>';

            // Calendar Permissions
            html += '<div class="col-sm-6 col-12">';
            html += '<div class="p-2" style="border:1px solid var(--border-color);border-radius:6px;height:100%">';
            html += '<label style="font-size:var(--font-xs);font-weight:bold;">Calendar Permissions ' + renderExchangeOpInfoIcon('mailbox_set_calendar_permissions') + ' <i class="fas fa-info-circle text-muted" data-noc-tip="Set or remove another user permission on this mailbox calendar."></i></label>';
            html += '<div class="d-flex gap-1 mt-1">';
            html += '<input type="text" class="mb-cal-user" placeholder="DOMAIN\\user" style="flex:1;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;" data-noc-tip="Domain\\username to grant calendar access">';
            html += '<select class="mb-cal-rights" style="height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;" data-noc-tip="Permission level">';
            html += '<option value="Reviewer">Reviewer</option><option value="Editor">Editor</option><option value="Author">Author</option>';
            html += '<option value="Contributor">Contributor</option><option value="FreeBusy">Free/Busy</option>';
            html += '</select>';
            html += '<button class="btn btn-sm btn-outline-primary mb-action-cal-set" style="height:28px;font-size:var(--font-xs);" data-noc-tip="Set permission"><i class="fas fa-plus"></i></button>';
            html += '<button class="btn btn-sm btn-outline-danger mb-action-cal-remove" style="height:28px;font-size:var(--font-xs);" data-noc-tip="Remove permission"><i class="fas fa-minus"></i></button>';
            html += '</div></div></div>';

            html += '</div>'; // close row
        }

        body.innerHTML = html;
        if (window.NocTooltip && typeof window.NocTooltip.init === 'function') {
            window.NocTooltip.init();
        }
        bindMailboxProfileSave(body);
        if (mb.has_mailbox) {
            loadMailboxStats(user.SamAccountName || activeIdentity);
        }

        if (nameSpan) nameSpan.innerHTML = '<i class="fas fa-user me-2"></i>' + htmlspecialchars(user.DisplayName || user.SamAccountName || activeIdentity);

        // Show/hide action buttons based on mailbox status
        const enableBtn = document.querySelector('.action-exchange-enable');
        const disableBtn = document.querySelector('.action-exchange-disable');
        if (enableBtn) {
            enableBtn.style.display = mb.has_mailbox ? 'none' : 'inline-block';
            enableBtn.onclick = function() {
                if (confirm('Enable mailbox for ' + (user.DisplayName || user.SamAccountName || activeIdentity) + '?')) {
                    doMailboxAction('mailbox_enable', activeIdentity, '');
                }
            };
        }
        if (disableBtn) {
            disableBtn.style.display = mb.has_mailbox ? 'inline-block' : 'none';
            disableBtn.onclick = function() {
                if (confirm('Disable mailbox for ' + (user.DisplayName || user.SamAccountName || activeIdentity) + '?')) {
                    doMailboxAction('mailbox_disable', activeIdentity, '');
                }
            };
        }

        // Quota
        var quotaBtn = body.querySelector('.mb-action-quota');
        if (quotaBtn) {
            quotaBtn.addEventListener('click', function() {
                var warn = body.querySelector('.mb-quota-warn');
                var send = body.querySelector('.mb-quota-send');
                var recv = body.querySelector('.mb-quota-recv');
                var unit = body.querySelector('.mb-quota-unit');
                doMailboxAction('mailbox_set_quota', activeIdentity, '', {
                    issue_warning_quota: warn ? warn.value : '5',
                    prohibit_send_quota: send ? send.value : '6',
                    prohibit_send_receive_quota: recv ? recv.value : '8',
                    quota_unit: unit ? unit.value : 'GB',
                });
            });
        }

        // Forward
        var fwdBtn = body.querySelector('.mb-action-fwd');
        if (fwdBtn) {
            fwdBtn.addEventListener('click', function() {
                var target = body.querySelector('.mb-fwd-target');
                if (target && target.value.trim()) {
                    doMailboxAction('mailbox_set_forward', activeIdentity, '', {
                        forward_to: target.value.trim(),
                        deliver_to_mailbox: true,
                    });
                }
            });
        }
        var fwdClear = body.querySelector('.mb-action-fwd-clear');
        if (fwdClear) {
            fwdClear.addEventListener('click', function() {
                doMailboxAction('mailbox_set_forward', activeIdentity, '', {
                    forward_to: '',
                    deliver_to_mailbox: true,
                });
            });
        }

        // Primary SMTP
        var smtpBtn = body.querySelector('.mb-action-smtp');
        if (smtpBtn) {
            smtpBtn.addEventListener('click', function() {
                var target = body.querySelector('.mb-smtp-primary');
                if (target && target.value.trim()) {
                    doMailboxAction('mailbox_set_primary_smtp', activeIdentity, '', {
                        email: target.value.trim(),
                    });
                }
            });
        }

        var aliasAddBtn = body.querySelector('.mb-action-alias-add');
        if (aliasAddBtn) {
            aliasAddBtn.addEventListener('click', function() {
                var target = body.querySelector('.mb-smtp-alias');
                if (target && target.value.trim()) {
                    doMailboxAction('mailbox_add_address', activeIdentity, '', {
                        email: target.value.trim(),
                    });
                }
            });
        }
        var aliasRemoveBtn = body.querySelector('.mb-action-alias-remove');
        if (aliasRemoveBtn) {
            aliasRemoveBtn.addEventListener('click', function() {
                var target = body.querySelector('.mb-smtp-alias');
                if (target && target.value.trim()) {
                    doMailboxAction('mailbox_remove_address', activeIdentity, '', {
                        email: target.value.trim(),
                    });
                }
            });
        }
        body.querySelectorAll('.mb-action-alias-row-remove').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var email = this.dataset.email || '';
                if (email && confirm('Remove alias ' + email + '?')) {
                    doMailboxAction('mailbox_remove_address', activeIdentity, '', { email: email });
                }
            });
        });
        body.querySelectorAll('.mb-action-primary-row').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var email = this.dataset.email || '';
                if (email && confirm('Make ' + email + ' the primary SMTP address?')) {
                    doMailboxAction('mailbox_set_primary_smtp', activeIdentity, '', { email: email });
                }
            });
        });

        // P2 — Litigation Hold
        bindSimpleAction(body, '.mb-action-lit-on', 'mailbox_set_litigation_hold', { enabled: true });
        bindSimpleAction(body, '.mb-action-lit-off', 'mailbox_set_litigation_hold', { enabled: false });
        // P2 — GAL
        bindSimpleAction(body, '.mb-action-hide-gal', 'mailbox_set_hidden_gal', { hidden: true });
        bindSimpleAction(body, '.mb-action-show-gal', 'mailbox_set_hidden_gal', { hidden: false });
        // P2 — OOF
        bindSimpleAction(body, '.mb-action-oof-enable', 'mailbox_set_oof', { state: 'Enabled', internal_message: 'I am out of the office.', external_message: 'I am out of the office.' });
        bindSimpleAction(body, '.mb-action-oof-disable', 'mailbox_set_oof', { state: 'Disabled' });
        // P2 — Full Access
        bindUserAction(body, '.mb-action-fa-add', 'mailbox_add_full_access', '.mb-fullaccess-user');
        bindUserAction(body, '.mb-action-fa-remove', 'mailbox_remove_full_access', '.mb-fullaccess-user');
        // P2 — Send-As
        bindUserAction(body, '.mb-action-sa-add', 'mailbox_add_send_as', '.mb-sendas-user');
        bindUserAction(body, '.mb-action-sa-remove', 'mailbox_remove_send_as', '.mb-sendas-user');
        // P2 — Move
        var moveBtn = body.querySelector('.mb-action-move');
        if (moveBtn) {
            moveBtn.addEventListener('click', function() {
                var db = body.querySelector('.mb-move-db');
                if (db && db.value.trim()) {
                    doMailboxAction('mailbox_move_request', activeIdentity, '', {
                        target_database: db.value.trim(),
                    });
                }
            });
        }

        // P3 — Archive
        bindSimpleAction(body, '.mb-action-arch-enable', 'mailbox_enable_archive', {});
        bindSimpleAction(body, '.mb-action-arch-disable', 'mailbox_disable_archive', {});

        // P3 — Mail Tip
        var mailTipSet = body.querySelector('.mb-action-mailtip-set');
        if (mailTipSet) {
            mailTipSet.addEventListener('click', function() {
                var tip = body.querySelector('.mb-mailtip-text');
                if (tip && tip.value.trim()) {
                    if (confirm('Set mail tip for ' + activeIdentity + '?')) {
                        doMailboxAction('mailbox_set_mail_tip', activeIdentity, '', { mail_tip: tip.value.trim() });
                    }
                }
            });
        }
        var mailTipClear = body.querySelector('.mb-action-mailtip-clear');
        if (mailTipClear) {
            mailTipClear.addEventListener('click', function() {
                if (confirm('Clear mail tip for ' + activeIdentity + '?')) {
                    doMailboxAction('mailbox_set_mail_tip', activeIdentity, '', { mail_tip: '' });
                }
            });
        }

        // P3 — Calendar Permissions
        var calSet = body.querySelector('.mb-action-cal-set');
        if (calSet) {
            calSet.addEventListener('click', function() {
                var user = body.querySelector('.mb-cal-user');
                var rights = body.querySelector('.mb-cal-rights');
                if (user && user.value.trim() && rights) {
                    doMailboxAction('mailbox_set_calendar_permissions', activeIdentity, '', {
                        user: user.value.trim(),
                        access_rights: rights.value,
                    });
                }
            });
        }
        var calRemove = body.querySelector('.mb-action-cal-remove');
        if (calRemove) {
            calRemove.addEventListener('click', function() {
                var user = body.querySelector('.mb-cal-user');
                if (user && user.value.trim()) {
                    doMailboxAction('mailbox_remove_calendar_permissions', activeIdentity, '', {
                        user: user.value.trim(),
                    });
                }
            });
        }
    }

    function bindSimpleAction(body, selector, action, extraParams) {
        var btn = body.querySelector(selector);
        if (btn) {
            btn.addEventListener('click', function() {
                if (confirm('Execute ' + action + '?')) {
                    doMailboxAction(action, activeIdentity, '', extraParams);
                }
            });
        }
    }

    function bindUserAction(body, btnSelector, action, inputSelector) {
        var btn = body.querySelector(btnSelector);
        if (btn) {
            btn.addEventListener('click', function() {
                var input = body.querySelector(inputSelector);
                if (input && input.value.trim()) {
                    if (confirm('Execute ' + action + ' for ' + input.value.trim() + '?')) {
                        doMailboxAction(action, activeIdentity, '', { user: input.value.trim() });
                    }
                }
            });
        }
    }

    function renderMailboxEditPanel(user, mb) {
        var html = '<div class="mb-3 pb-2" style="border-bottom:1px solid var(--border-color);">';
        html += '<div class="d-flex align-items-center justify-content-between mb-2">';
        html += '<strong><i class="fas fa-pen me-1"></i>Edit Mailbox' + renderExchangeOpInfoIcon('mailbox_update_profile') + '</strong>';
        html += '<button class="btn btn-sm btn-outline-primary mb-action-profile-save" style="height:28px;font-size:var(--font-xs);padding:0 10px;"><i class="fas fa-save me-1"></i>Save</button>';
        html += '</div>';
        html += '<div class="row g-2">';
        html += mailboxInput('First name', 'ex-prof-givenName', user.firstName === 'N/A' ? '' : user.firstName, 'col-sm-4');
        html += mailboxInput('Initials', 'ex-prof-initials', user.initials || '', 'col-sm-4');
        html += mailboxInput('Last name', 'ex-prof-sn', user.lastName === 'N/A' ? '' : user.lastName, 'col-sm-4');
        html += mailboxInput('Display name', 'ex-prof-displayName', user.DisplayName || '', 'col-12');
        html += mailboxInput('Alias', 'ex-prof-alias', mb.alias || '', 'col-sm-6');
        html += mailboxInput('Office', 'ex-prof-office', user.office === 'N/A' ? '' : user.office, 'col-sm-6');
        html += mailboxInput('Work phone', 'ex-prof-phone', user.phoneNumber === 'N/A' ? '' : user.phoneNumber, 'col-sm-6');
        html += mailboxInput('Title', 'ex-prof-title', user.jobTitle === 'N/A' ? '' : user.jobTitle, 'col-sm-4');
        html += mailboxInput('Department', 'ex-prof-department', user.department === 'N/A' ? '' : user.department, 'col-sm-4');
        html += mailboxInput('Company', 'ex-prof-company', user.company === 'N/A' ? '' : user.company, 'col-sm-4');
        html += '<div class="col-12 text-muted" style="font-size:10px;">General, contact, and organization fields save to AD. Alias saves through Exchange PowerShell.</div>';
        html += '</div></div>';
        return html;
    }

    function renderEnableMailboxPanel(user) {
        if (!exchangeDatabases.length) {
            loadDatabases();
        }
        var html = '<div class="mt-3 p-3" style="border:1px solid var(--border-color);border-radius:6px;background:rgba(148,163,184,.06);">';
        html += '<div class="d-flex align-items-center justify-content-between mb-2">';
        html += '<strong><i class="fas fa-inbox me-1"></i>Create mailbox for existing AD user' + renderExchangeOpInfoIcon('mailbox_enable') + '</strong>';
        html += '<span class="status-badge status-warning" style="font-size:10px;">No mailbox</span>';
        html += '</div>';
        html += '<div class="mb-2 text-muted" style="font-size:var(--font-xs);">This will run Enable-Mailbox for ' + htmlspecialchars(user.SamAccountName || activeIdentity) + '.</div>';
        html += '<div class="row g-2">';
        html += '<div class="col-sm-6"><label style="font-size:10px;color:var(--text-muted);">Alias</label><input type="text" class="mb-enable-alias" value="' + htmlspecialchars((user.SamAccountName || activeIdentity || '').replace(/[^A-Za-z0-9._-]/g, '')) + '" style="width:100%;height:30px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;"></div>';
        html += '<div class="col-sm-6"><label style="font-size:10px;color:var(--text-muted);">Primary SMTP</label><input type="email" class="mb-enable-primary-smtp" value="' + htmlspecialchars(user.emailAddress && user.emailAddress !== 'N/A' ? user.emailAddress : '') + '" placeholder="user@domain.com" style="width:100%;height:30px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;"></div>';
        html += '<div class="col-12"><label style="font-size:10px;color:var(--text-muted);">Additional SMTP aliases</label><textarea class="mb-enable-smtp-aliases" placeholder="alias1@domain.com, alias2@domain.com" style="width:100%;min-height:54px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:6px 8px;resize:vertical;"></textarea></div>';
        html += '<div class="col-12"><label style="font-size:10px;color:var(--text-muted);">Mailbox database</label>';
        html += '<div class="d-flex gap-1">';
        html += '<select class="mb-enable-database" style="flex:1;height:30px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;">';
        html += '<option value="">Auto select database</option>';
        exchangeDatabases.forEach(function(db) {
            var name = db.name || '';
            if (name) html += '<option value="' + htmlspecialchars(name) + '">' + htmlspecialchars(name) + '</option>';
        });
        html += '</select>';
        html += '<button class="btn btn-sm btn-outline-primary mb-action-enable-mailbox" style="height:30px;font-size:var(--font-xs);"><i class="fas fa-check-circle me-1"></i>Create</button>';
        html += '</div></div></div>';
        if (!exchangeDatabases.length) {
            html += '<div class="text-muted mt-2 mb-enable-db-empty" style="font-size:10px;">Loading database list. Auto select is still available.</div>';
        }
        html += '</div>';
        return html;
    }

    function bindEnableMailboxPanel(body) {
        var btn = body.querySelector('.mb-action-enable-mailbox');
        if (!btn) return;
        btn.addEventListener('click', function() {
            var db = body.querySelector('.mb-enable-database');
            var alias = body.querySelector('.mb-enable-alias');
            var primary = body.querySelector('.mb-enable-primary-smtp');
            var aliases = body.querySelector('.mb-enable-smtp-aliases');
            doMailboxAction('mailbox_enable', activeIdentity, db ? db.value.trim() : '', {
                alias: alias ? alias.value.trim() : '',
                primary_smtp: primary ? primary.value.trim() : '',
                smtp_aliases: aliases ? aliases.value.trim() : '',
            });
        });
    }

    function mailboxInput(label, cls, value, colClass) {
        return '<div class="' + colClass + '"><label style="font-size:10px;color:var(--text-muted);">' + htmlspecialchars(label) + '</label>' +
            '<input type="text" class="' + cls + '" value="' + htmlspecialchars(value || '') + '" style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;"></div>';
    }

    function bindMailboxProfileSave(body) {
        var btn = body.querySelector('.mb-action-profile-save');
        if (!btn) return;
        btn.addEventListener('click', function() {
            var fields = {
                givenName: getInputValue(body, '.ex-prof-givenName'),
                initials: getInputValue(body, '.ex-prof-initials'),
                sn: getInputValue(body, '.ex-prof-sn'),
                displayName: getInputValue(body, '.ex-prof-displayName'),
                alias: getInputValue(body, '.ex-prof-alias'),
                physicalDeliveryOfficeName: getInputValue(body, '.ex-prof-office'),
                telephoneNumber: getInputValue(body, '.ex-prof-phone'),
                title: getInputValue(body, '.ex-prof-title'),
                department: getInputValue(body, '.ex-prof-department'),
                company: getInputValue(body, '.ex-prof-company'),
            };

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Save';

            fetch('/api/index.php?endpoint=exchange', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                body: JSON.stringify({ action: 'mailbox_update_profile', identity: activeIdentity, fields: fields })
            })
            .then(parseJsonResponse)
            .then(function(data) {
                if (data.success) {
                    doMailboxSearch(activeIdentity);
                    loadMailboxList(document.getElementById('exchangeMailboxIdentity')?.value.trim() || '');
                } else {
                    alert(data.message || 'Mailbox profile update failed.');
                }
            })
            .catch(function(err) {
                alert('Request failed: ' + err.message);
            })
            .finally(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i>Save';
            });
        });
    }

    function getInputValue(root, selector) {
        var el = root.querySelector(selector);
        return el ? el.value.trim() : '';
    }

    function loadMailboxStats(identity) {
        var box = document.getElementById('exchangeMailboxStatsBox');
        var badge = document.getElementById('exchangeMailboxQuotaBadge');
        if (!box || !identity) return;

        fetch('/api/index.php?endpoint=exchange', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({ action: 'mailbox_stats', identity: identity })
        })
        .then(parseJsonResponse)
        .then(function(data) {
            if (!data.success) {
                box.innerHTML = '<span class="text-muted">Live mailbox size unavailable.</span>';
                if (badge) {
                    badge.className = 'status-badge status-warning';
                    badge.textContent = 'Size unavailable';
                }
                return;
            }

            var stats = normalizePsObject(data.stats);
            var mailbox = normalizePsObject(data.mailbox);
            var totalSize = pickValue(stats, ['TotalItemSize', 'totalitemsize', 'TotalMailboxSize']) || 'N/A';
            var itemCount = pickValue(stats, ['ItemCount', 'itemcount']) || 'N/A';
            var db = pickValue(stats, ['Database', 'database']) || pickValue(mailbox, ['Database', 'database']) || 'N/A';
            var warnQuota = pickValue(mailbox, ['IssueWarningQuota', 'issuewarningquota']) || 'N/A';
            var sendQuota = pickValue(mailbox, ['ProhibitSendQuota', 'prohibitsendquota']) || 'N/A';
            var recvQuota = pickValue(mailbox, ['ProhibitSendReceiveQuota', 'prohibitsendreceivequota']) || 'N/A';
            var usedBytes = parseExchangeSizeBytes(totalSize);
            var limitBytes = parseExchangeSizeBytes(recvQuota);
            var pct = usedBytes && limitBytes ? Math.round((usedBytes / limitBytes) * 100) : null;
            var hasLiveData = totalSize !== 'N/A' || itemCount !== 'N/A';
            var statusClass = pct === null ? 'status-info' : (pct >= 90 ? 'status-failed' : (pct >= 75 ? 'status-warning' : 'status-success'));
            var statusText = pct === null ? (hasLiveData ? 'Loaded' : 'Stats') : pct + '% used';

            // Update action card status badges from PS mailbox data
            var litHold = pickValue(mailbox, ['LitigationHoldEnabled', 'litigationholdenabled']);
            var litBadge = document.querySelector('.mb-lit-status');
            if (litBadge && litHold !== null) {
                litBadge.className = 'badge ' + (litHold ? 'bg-warning' : 'bg-secondary') + ' mb-lit-status';
                litBadge.textContent = litHold ? 'On' : 'Off';
            }
            var hiddenGalPs = pickValue(mailbox, ['HiddenFromAddressListsEnabled', 'hiddenfromaddresslistsenabled']);
            var galBadge = document.querySelector('.mb-gal-status');
            if (galBadge && hiddenGalPs !== null) {
                galBadge.className = 'badge ' + (hiddenGalPs ? 'bg-warning' : 'bg-success') + ' mb-gal-status';
                galBadge.textContent = hiddenGalPs ? 'Hidden' : 'Visible';
            }

            if (badge) {
                badge.className = 'status-badge ' + statusClass;
                badge.textContent = statusText;
            }
            var usageBar = document.getElementById('exchangeMailboxUsageBar');
            var usageText = document.getElementById('exchangeMailboxUsageText');
            if (usageBar && pct !== null) {
                usageBar.style.width = Math.min(100, Math.max(0, pct)) + '%';
                usageBar.style.background = pct >= 90 ? '#dc3545' : (pct >= 75 ? '#ffc107' : '#198754');
            }
            if (usageText) {
                usageText.textContent = pct === null ? 'Utilization unavailable from live stats.' : pct + '% used of send/receive quota.';
            }

            if (!hasLiveData) {
                box.innerHTML = '<span class="text-muted">Live mailbox stats unavailable. Applied quota shown below.</span>';
                return;
            }

            var html = '';
            html += '<div class="mb-1"><strong>Used:</strong> ' + htmlspecialchars(String(totalSize)) + '</div>';
            html += '<div class="mb-1"><strong>Items:</strong> ' + htmlspecialchars(String(itemCount)) + '</div>';
            html += '<div class="mb-1" style="overflow-wrap:anywhere;"><strong>Database:</strong> ' + htmlspecialchars(String(db)) + '</div>';
            html += '<div class="mt-2 p-2" style="background:rgba(148,163,184,.08);border-radius:6px;">';
            html += '<div><strong>Live warning:</strong> ' + htmlspecialchars(String(warnQuota)) + '</div>';
            html += '<div><strong>Live send block:</strong> ' + htmlspecialchars(String(sendQuota)) + '</div>';
            html += '<div><strong>Live send/receive block:</strong> ' + htmlspecialchars(String(recvQuota)) + '</div>';
            if (pct !== null && pct >= 90) {
                html += '<div class="mt-2"><span class="status-badge status-failed">Near full</span></div>';
            }
            html += '</div>';
            box.innerHTML = html;
        })
        .catch(function(err) {
            box.innerHTML = '<span class="text-muted">Live mailbox size unavailable.</span>';
            if (badge) {
                badge.className = 'status-badge status-warning';
                badge.textContent = 'Size unavailable';
            }
        });
    }

    function renderAppliedQuota(mb) {
        var defaults = mb.quota_use_database_defaults ? 'Database defaults' : 'Mailbox custom';
        var warn = mb.issue_warning_quota || valueFromKb(mb.issue_warning_quota_kb) || 'Database default';
        var send = mb.prohibit_send_quota || valueFromKb(mb.prohibit_send_quota_kb) || 'Database default';
        var recv = mb.prohibit_send_receive_quota || valueFromKb(mb.prohibit_send_receive_quota_kb) || 'Database default';
        var parsed = parseQuotaTriple(warn, send, recv);

        var html = '<div class="mt-2 p-2" style="background:rgba(148,163,184,.08);border-radius:6px;">';
        html += '<div class="mb-1"><strong>Applied quota:</strong> ' + htmlspecialchars(defaults) + '</div>';
        html += '<div><strong>Warning:</strong> ' + htmlspecialchars(warn) + '</div>';
        html += '<div><strong>Send block:</strong> ' + htmlspecialchars(send) + '</div>';
        html += '<div><strong>Send/receive block:</strong> ' + htmlspecialchars(recv) + '</div>';
        html += '<div class="mt-2">';
        html += '<div style="height:8px;background:rgba(148,163,184,.25);border-radius:999px;overflow:hidden;" data-noc-tip="Live utilization needs Get-MailboxStatistics. Current server connection did not return live size.">';
        html += '<div id="exchangeMailboxUsageBar" style="height:8px;width:0%;background:#0d6efd;border-radius:999px;"></div>';
        html += '</div>';
        html += '<div id="exchangeMailboxUsageText" class="text-muted mt-1" style="font-size:10px;">Utilization pending live mailbox size.</div>';
        html += '</div>';
        html += '<div class="mt-2 pt-2" style="border-top:1px solid var(--border-color);">';
        html += '<label style="font-size:var(--font-xs);font-weight:bold;">Edit quota <i class="fas fa-info-circle text-muted" data-noc-tip="Warn, send block, and send/receive block. Values can be MB, GB, or TB."></i></label>';
        html += '<div class="row g-1 mt-1 align-items-center">';
        html += '<div class="col-3"><input type="number" class="mb-quota-warn" value="' + htmlspecialchars(parsed.warn) + '" min="0" step="0.1" style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;text-align:center;" title="Warning"><div style="font-size:8px;color:var(--text-muted);text-align:center;line-height:1.2">Warn</div></div>';
        html += '<div class="col-3"><input type="number" class="mb-quota-send" value="' + htmlspecialchars(parsed.send) + '" min="0" step="0.1" style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;text-align:center;" title="Prohibit Send"><div style="font-size:8px;color:var(--text-muted);text-align:center;line-height:1.2">Send</div></div>';
        html += '<div class="col-3"><input type="number" class="mb-quota-recv" value="' + htmlspecialchars(parsed.recv) + '" min="0" step="0.1" style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;text-align:center;" title="Prohibit Send/Receive"><div style="font-size:8px;color:var(--text-muted);text-align:center;line-height:1.2">Recv</div></div>';
        html += '<div class="col-2"><select class="mb-quota-unit" style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;">';
        ['MB', 'GB', 'TB'].forEach(function(unit) {
            html += '<option value="' + unit + '"' + (parsed.unit === unit ? ' selected' : '') + '>' + unit + '</option>';
        });
        html += '</select></div>';
        html += '<div class="col-auto"><button class="btn btn-sm btn-outline-primary mb-action-quota" style="height:28px;width:32px;font-size:var(--font-xs);padding:0;" data-noc-tip="Save quota"><i class="fas fa-save"></i></button></div>';
        html += '</div></div>';
        html += '</div>';
        return html;
    }

    function parseQuotaTriple(warn, send, recv) {
        var values = [warn, send, recv].map(parseQuotaValue);
        var unit = values.find(function(v) { return v.unit; })?.unit || 'GB';
        return {
            warn: values[0].value || '5',
            send: values[1].value || '6',
            recv: values[2].value || '8',
            unit: unit,
        };
    }

    function parseQuotaValue(value) {
        var match = String(value || '').match(/([\d.]+)\s*(MB|GB|TB)/i);
        if (!match) return { value: '', unit: '' };
        return { value: match[1], unit: match[2].toUpperCase() };
    }

    function valueFromKb(value) {
        var kb = parseFloat(String(value || '').replace(/,/g, ''));
        if (!isFinite(kb) || kb <= 0) return '';
        var mb = kb / 1024;
        if (mb >= 1024) {
            var gb = mb / 1024;
            return trimNumber(gb) + ' GB';
        }
        return trimNumber(mb) + ' MB';
    }

    function trimNumber(value) {
        return String(Math.round(value * 100) / 100).replace(/\.0+$/, '');
    }

    function normalizePsObject(value) {
        if (Array.isArray(value)) return value[0] || {};
        if (value && typeof value === 'object') return value;
        return {};
    }

    function pickValue(obj, keys) {
        for (var i = 0; i < keys.length; i++) {
            if (obj && obj[keys[i]] !== undefined && obj[keys[i]] !== null) {
                return obj[keys[i]];
            }
        }
        return '';
    }

    function parseExchangeSizeBytes(value) {
        var s = String(value || '');
        var match = s.match(/([\d,.]+)\s*(B|KB|MB|GB|TB)/i);
        if (!match) return null;
        var num = parseFloat(match[1].replace(/,/g, ''));
        if (!isFinite(num)) return null;
        var unit = match[2].toUpperCase();
        var factor = { B: 1, KB: 1024, MB: 1048576, GB: 1073741824, TB: 1099511627776 }[unit] || 1;
        return num * factor;
    }

    function shortExchangeDn(value) {
        var s = String(value || '');
        var match = s.match(/cn=([^/]+)$/i);
        return match ? match[1] : s;
    }

    function showExchangeAction(type, message) {
        var card = document.getElementById('exchangeActionCard');
        var titleEl = document.getElementById('exchangeActionTitle');
        var msgEl = document.getElementById('exchangeActionMessage');
        if (!card || !titleEl || !msgEl) return;
        card.style.display = 'flex';
        var borderColor = '#ffc107';
        if (type === 'Error') borderColor = '#dc3545';
        else if (type === 'Success') borderColor = '#198754';
        else if (type === 'Info') borderColor = '#0d6efd';
        card.style.borderLeftColor = borderColor;
        titleEl.textContent = type;
        msgEl.textContent = message || '';
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        setTimeout(function(){ card.style.display = 'none'; }, 8000);
    }

    function showExchangeFeedback(success, title, message) {
        var card = document.getElementById('exchangeActionCard');
        var titleEl = document.getElementById('exchangeActionTitle');
        var msgEl = document.getElementById('exchangeActionMessage');
        if (!card || !titleEl || !msgEl) return;
        card.style.display = 'flex';
        card.style.borderLeftColor = success ? 'var(--success-color,#198754)' : 'var(--danger-color,#dc3545)';
        titleEl.textContent = title || (success ? 'Success' : 'Error');
        msgEl.innerHTML = message || '';
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        setTimeout(function(){ card.style.display = 'none'; }, success ? 8000 : 15000);
    }

    function doMailboxAction(action, identity, database, extraParams) {
        var params = { action: action, identity: identity, database: database };
        if (extraParams) {
            Object.keys(extraParams).forEach(function(k) { params[k] = extraParams[k]; });
        }
        const body = document.getElementById('exchangeResultBody');
        if (body) body.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Processing...</p></div>';

        fetch('/api/index.php?endpoint=exchange', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify(params)
        })
        .then(parseJsonResponse)
        .then(data => {
            if (data.success) {
                showExchangeFeedback(true, 'Operation Completed', data.message || 'The operation was completed successfully.');
                // Refresh the search to show updated status
                doMailboxSearch(identity);
                loadMailboxList(document.getElementById('exchangeMailboxIdentity')?.value.trim() || '');
            } else {
                showExchangeFeedback(false, 'Operation Failed', data.message || 'The operation failed.');
                if (body) body.innerHTML = '<div class="alert alert-danger mb-0">' + (data.message || 'Operation failed.') + '</div>';
            }
        })
        .catch(err => {
            showExchangeFeedback(false, 'Request Error', err.message);
            if (body) body.innerHTML = '<div class="alert alert-danger mb-0">Request failed: ' + err.message + '</div>';
        });
    }

    function bindMailQueues() {
        var btn = document.getElementById('refreshMailQueues');
        if (btn) btn.addEventListener('click', loadMailQueues);
    }

    function loadMailQueues() {
        var el = document.getElementById('exchangeMailQueues');
        if (el) el.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading queues...</p></div>';
        fetch('/api/index.php?endpoint=exchange', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({ action: 'monitoring_queues' })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (el) {
                if (data.success && data.queues && data.queues.length > 0) {
                    var html = '<div class="mb-1">' + renderExchangeOpInfoIcon('monitoring_queues') + '</div>';
                    html += '<div class="table-responsive"><table class="app-data-table" style="width:100%;font-size:var(--font-xs);">';
                    html += '<thead><tr><th>Queue</th><th>Status</th><th>Message Count</th><th>Next Hop</th></tr></thead><tbody>';
                    data.queues.forEach(function(q) {
                        var status = q.Status || q.status || 'Unknown';
                        var statusClass = status.toLowerCase() === 'ready' ? 'status-success' : (status.toLowerCase() === 'retry' ? 'status-warning' : 'status-failed');
                        html += '<tr><td>' + htmlspecialchars(q.Identity || q.identity || '') + '</td>';
                        html += '<td><span class="status-badge ' + statusClass + '">' + htmlspecialchars(status) + '</span></td>';
                        html += '<td>' + (q.MessageCount || q.message_count || 0) + '</td>';
                        html += '<td>' + htmlspecialchars(q.NextHopDomain || q.next_hop_domain || '-') + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                    el.innerHTML = html;
                } else if (data.success) {
                    el.innerHTML = '<div class="alert alert-info mb-0">No queues found. All mail flow is clear.</div>';
                } else {
                    el.innerHTML = '<div class="alert alert-warning mb-0">' + (data.message || 'Queue data requires PowerShell connection.') + '</div>';
                }
            }
        })
        .catch(function(err) {
            if (el) el.innerHTML = '<div class="alert alert-danger mb-0">Request failed: ' + err.message + '</div>';
        });
    }

    function bindMessageTracking() {
        var go = document.getElementById('mtSearchGo');
        if (go) {
            go.addEventListener('click', doMessageTracking);
        }
    }

    function bindTransportRules() {
        var btn = document.getElementById('refreshTransportRules');
        if (btn) btn.addEventListener('click', loadTransportRules);
    }

    function loadTransportRules() {
        var el = document.getElementById('exchangeTransportRules');
        if (el) el.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading...</p></div>';
        fetch('/api/index.php?endpoint=exchange', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({ action: 'monitoring_transport_rules' })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (el) {
                if (data.success && data.rules && data.rules.length > 0) {
                    var html = '<div class="mb-1">' + renderExchangeOpInfoIcon('monitoring_transport_rules') + '</div>';
                    html += '<div class="table-responsive"><table class="app-data-table" style="width:100%;font-size:var(--font-xs);">';
                    html += '<thead><tr><th>Name</th><th>Priority</th><th>State</th><th>Mode</th></tr></thead><tbody>';
                    data.rules.forEach(function(r) {
                        var state = r.State || r.state || 'Enabled';
                        var mode = r.Mode || r.mode || 'Enforce';
                        var stateClass = state.toLowerCase() === 'enabled' ? 'status-success' : 'status-warning';
                        html += '<tr><td>' + htmlspecialchars(r.Name || r.name || '') + '</td>';
                        html += '<td>' + (r.Priority || r.priority || '-') + '</td>';
                        html += '<td><span class="status-badge ' + stateClass + '">' + htmlspecialchars(state) + '</span></td>';
                        html += '<td>' + htmlspecialchars(mode) + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                    el.innerHTML = html;
                } else if (data.success) {
                    el.innerHTML = '<div class="alert alert-info mb-0">No transport rules found.</div>';
                } else {
                    el.innerHTML = '<div class="alert alert-warning mb-0">' + (data.message || 'Transport rules require PowerShell connection.') + '</div>';
                }
            }
        })
        .catch(function(err) {
            if (el) el.innerHTML = '<div class="alert alert-danger mb-0">Request failed: ' + err.message + '</div>';
        });
    }

    function doMessageTracking() {
        var sender = document.getElementById('mtSender');
        var recipient = document.getElementById('mtRecipient');
        var startDate = document.getElementById('mtStartDate');
        var endDate = document.getElementById('mtEndDate');
        var resultsEl = document.getElementById('mtResults');
        if (resultsEl) resultsEl.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Searching...</p></div>';

        fetch('/api/index.php?endpoint=exchange', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({
                action: 'monitoring_message_tracking',
                sender: sender ? sender.value.trim() : '',
                recipient: recipient ? recipient.value.trim() : '',
                start_date: startDate ? startDate.value : '',
                end_date: endDate ? endDate.value : ''
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (resultsEl) {
                if (data.success && data.messages && data.messages.length > 0) {
                    var html = '<div class="mb-1">' + renderExchangeOpInfoIcon('monitoring_message_tracking') + '</div>';
                    html += '<div class="table-responsive" style="max-height:400px;overflow-y:auto;"><table class="app-data-table" style="width:100%;font-size:var(--font-xs);">';
                    html += '<thead><tr><th>Time</th><th>Sender</th><th>Recipients</th><th>Subject</th><th>Status</th></tr></thead><tbody>';
                    data.messages.forEach(function(m) {
                        var statusClass = (m.Status || m.status || '').toLowerCase() === 'delivered' ? 'status-success' : 'status-info';
                        html += '<tr><td>' + htmlspecialchars(m.Timestamp || m.timestamp || m.Time || '') + '</td>';
                        html += '<td>' + htmlspecialchars(m.Sender || m.sender || '') + '</td>';
                        html += '<td>' + htmlspecialchars(m.Recipients || m.recipients || '') + '</td>';
                        html += '<td>' + htmlspecialchars(m.Subject || m.subject || m.MessageSubject || '') + '</td>';
                        html += '<td><span class="status-badge ' + statusClass + '">' + htmlspecialchars(m.Status || m.status || '') + '</span></td></tr>';
                    });
                    html += '</tbody></table></div>';
                    resultsEl.innerHTML = html;
                } else if (data.success) {
                    resultsEl.innerHTML = '<div class="alert alert-info mb-0">No messages found matching the criteria.</div>';
                } else {
                    resultsEl.innerHTML = '<div class="alert alert-warning mb-0">' + (data.message || 'Message tracking requires PowerShell connection.') + '</div>';
                }
            }
        })
        .catch(function(err) {
            if (resultsEl) resultsEl.innerHTML = '<div class="alert alert-danger mb-0">Request failed: ' + err.message + '</div>';
        });
    }

    function bindRetentionPolicies() {
        var btn = document.getElementById('refreshRetentionPolicies');
        if (btn) btn.addEventListener('click', loadRetentionPolicies);
    }

    function loadRetentionPolicies() {
        var el = document.getElementById('exchangeRetentionPolicies');
        if (el) el.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading...</p></div>';
        fetch('/api/index.php?endpoint=exchange', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({ action: 'monitoring_retention_policies' })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (el) {
                if (data.success && data.policies && data.policies.length > 0) {
                    var html = '<div class="mb-1">' + renderExchangeOpInfoIcon('monitoring_retention_policies') + '</div>';
                    html += '<div class="table-responsive"><table class="app-data-table" style="width:100%;font-size:var(--font-xs);">';
                    html += '<thead><tr><th>Name</th></tr></thead><tbody>';
                    data.policies.forEach(function(p) {
                        html += '<tr><td>' + htmlspecialchars(p.Name || p.name || '') + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                    el.innerHTML = html;
                } else if (data.success) {
                    el.innerHTML = '<div class="alert alert-info mb-0">No retention policies found.</div>';
                } else {
                    el.innerHTML = '<div class="alert alert-warning mb-0">' + (data.message || 'Requires PowerShell connection.') + '</div>';
                }
            }
        })
        .catch(function(err) {
            if (el) el.innerHTML = '<div class="alert alert-danger mb-0">Request failed: ' + err.message + '</div>';
        });
    }

    function bindP3MailboxCreate() {
        var shared = document.getElementById('p3CreateShared');
        var room = document.getElementById('p3CreateRoom');
        var equip = document.getElementById('p3CreateEquip');
        if (shared) {
            shared.addEventListener('click', function() {
                var name = document.getElementById('p3SharedName');
                var alias = document.getElementById('p3SharedAlias');
                var display = document.getElementById('p3SharedDisplay');
                if (name && name.value.trim()) {
                    p3CreateMailbox('mailbox_create_shared', {
                        name: name.value.trim(),
                        alias: alias ? alias.value.trim() : '',
                        display_name: display ? display.value.trim() : '',
                    }, 'Shared mailbox');
                }
            });
        }
        if (room) {
            room.addEventListener('click', function() {
                var name = document.getElementById('p3RoomName');
                var alias = document.getElementById('p3RoomAlias');
                var cap = document.getElementById('p3RoomCapacity');
                if (name && name.value.trim()) {
                    p3CreateMailbox('mailbox_create_room', {
                        name: name.value.trim(),
                        alias: alias ? alias.value.trim() : '',
                        capacity: cap ? cap.value.trim() : '',
                    }, 'Room mailbox');
                }
            });
        }
        if (equip) {
            equip.addEventListener('click', function() {
                var name = document.getElementById('p3EquipName');
                var alias = document.getElementById('p3EquipAlias');
                if (name && name.value.trim()) {
                    p3CreateMailbox('mailbox_create_equipment', {
                        name: name.value.trim(),
                        alias: alias ? alias.value.trim() : '',
                    }, 'Equipment mailbox');
                }
            });
        }
    }

    function p3CreateMailbox(action, params, label) {
        var btn = document.querySelector('[id^="p3Create"]:active') || document.activeElement;
        fetch('/api/index.php?endpoint=exchange', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify(Object.assign({ action: action }, params))
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                alert(label + ' created: ' + (data.message || ''));
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(function(err) {
            alert('Request failed: ' + err.message);
        });
    }

    function bindSettingsSave() {
        var btn = document.getElementById('saveDefaultPolicies');
        if (btn) {
            btn.addEventListener('click', function() {
                var db = document.getElementById('settingsDefaultDb');
                var quota = document.getElementById('settingsDefaultQuota');
                var warn = document.getElementById('settingsWarningQuota');
                fetch('/api/index.php?endpoint=exchange', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
                    body: JSON.stringify({
                        action: 'settings_save',
                        default_database: db ? db.value.trim() : '',
                        default_quota: quota ? quota.value : '10',
                        warning_quota: warn ? warn.value : '8',
                    })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    alert(data.message || (data.success ? 'Settings saved.' : 'Failed to save.'));
                })
                .catch(function(err) { alert('Request failed: ' + err.message); });
            });
        }
    }

    function testConnection() {
        const info = document.getElementById('exchangeConnectionInfo');
        if (info) info.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Testing connection...</p></div>';

        fetch('/api/index.php?endpoint=exchange', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
            body: JSON.stringify({ action: 'discover' })
        })
        .then(r => r.json())
        .then(data => renderConnectionInfo(data, info))
        .catch(err => {
            if (info) info.innerHTML = '<div class="alert alert-danger mb-0">Connection test failed: ' + err.message + '</div>';
        });
    }

    function renderConnectionInfo(data, infoEl) {
        const info = infoEl || document.getElementById('exchangeConnectionInfo');
        if (!info) return;

        const configHint = document.getElementById('exchangeConfigHint');

        if (data.success) {
            let html = '<div class="d-flex align-items-center gap-2 mb-2">';
            html += '<span class="status-badge status-success"><i class="fas fa-check-circle me-1"></i>Connected</span>';
            html += ' ' + renderExchangeOpInfoIcon('discover') + '';
            html += '</div>';
            if (data.server) html += '<div class="mb-1"><strong>Server:</strong> ' + data.server + '</div>';
            if (data.version) html += '<div class="mb-1"><strong>Exchange Version:</strong> ' + data.version + '</div>';
            if (data.databases) {
                html += '<div class="mb-2"><strong>Mailbox Databases (' + data.databases.length + '):</strong></div>';
                html += '<ul class="list-unstyled mb-0" style="font-size:var(--font-sm);">';
                data.databases.forEach(function(db) {
                    html += '<li><i class="fas fa-database me-1"></i>' + db.name + (db.server ? ' (' + db.server + ')' : '') + '</li>';
                });
                html += '</ul>';
            }
            info.innerHTML = html;
            if (configHint) configHint.style.display = 'block';

            // Populate default policies
            if (data.default_policies) {
                var pol = data.default_policies;
                var dbInput = document.getElementById('settingsDefaultDb');
                var quInput = document.getElementById('settingsDefaultQuota');
                var waInput = document.getElementById('settingsWarningQuota');
                if (dbInput && pol.default_database) dbInput.value = pol.default_database;
                if (quInput && pol.default_quota) quInput.value = pol.default_quota;
                if (waInput && pol.warning_quota) waInput.value = pol.warning_quota;
            }
        } else {
            info.innerHTML = '<div class="alert alert-warning mb-0">' + (data.message || 'Could not connect to Exchange.') + '</div>';
            if (configHint) configHint.style.display = 'none';
        }
    }

    function getCsrfToken() {
        return (window.APP_CONFIG && window.APP_CONFIG.csrfToken) || '';
    }

    function parseJsonResponse(response) {
        return response.text().then(function(text) {
            if (!text.trim()) {
                throw new Error('Empty response from server (HTTP ' + response.status + ').');
            }
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Invalid JSON response (HTTP ' + response.status + '): ' + text.slice(0, 180));
            }
        });
    }

    function htmlspecialchars(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
