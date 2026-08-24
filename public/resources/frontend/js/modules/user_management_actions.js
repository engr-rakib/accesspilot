// assets/js/user_management_actions.js

window.initUserManagement = function() {
    const resolvedBaseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (typeof baseURL === 'string' ? baseURL : '');
    const userManagementPageUrl = (window.APP_CONFIG && window.APP_CONFIG.userManagementPageUrl) || 'index.php?page=user_management';
    const userManagementApiUrl = `${resolvedBaseUrl}/api/index.php?endpoint=user_management_action`;

    function buildAdminPageUrl(page, params = {}) {
        const target = new URL(userManagementPageUrl, window.location.origin);
        target.searchParams.set('page', page);
        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && typeof value !== 'undefined') {
                target.searchParams.set(key, value);
            }
        });
        return target.pathname + target.search;
    }
    
    // --- State Management ---
    const resetPasswordModal = document.getElementById('resetPasswordModal');
    const approveUserModal = document.getElementById('approveUserModal');
    const usernameInput = document.getElementById('reset-username');
    
    if (!resetPasswordModal && !document.getElementById('existing-users-tbody')) return;

    const pageAnchor = document.getElementById('existing-users-tbody')
        || document.getElementById('rolesTableBody')
        || document.getElementById('pending-registration-tbody');
    if (pageAnchor && pageAnchor.dataset.userManagementInitialized === 'true') return;
    if (pageAnchor) pageAnchor.dataset.userManagementInitialized = 'true';

    let requestIndexInputReset = document.getElementById('reset-request-index');
    if (!requestIndexInputReset && usernameInput) {
        requestIndexInputReset = document.createElement('input');
        requestIndexInputReset.type = 'hidden';
        requestIndexInputReset.id = 'reset-request-index';
        usernameInput.parentNode.appendChild(requestIndexInputReset);
    }

    // --- Dynamic Refresh Logic ---
    async function fetchAndRefreshRequests() {
        try {
            const response = await fetch(userManagementApiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_pending_requests' })
            });
            const data = await response.json();
            if (data.success) {
                updateRegistrationTable(data.registration_requests || []);
                updateResetRequestsTable(data.password_reset_requests || []);
            }

            // Also refresh Existing Users
            const usersResponse = await fetch(userManagementApiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_users' })
            });
            const usersData = await usersResponse.json();
            if (usersData.success) {
                updateExistingUsersTable(usersData.users || {});
                refreshOnlineStatus();
            }
        } catch (error) {
            console.error('Error fetching requests:', error);
        }
    }

    function refreshOnlineStatus() {
        fetch(userManagementApiUrl,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'get_online_users'})}).then(function(r){return r.json();}).then(function(d){
            if(!d.success||!d.users)return;
            var online={};for(var i=0;i<d.users.length;i++){online[d.users[i].username]=true;}
            document.querySelectorAll('.user-status-dot').forEach(function(dot){
                var isOnline = !!online[dot.dataset.user];
                dot.style.background = isOnline ? '#22c55e' : '#6b7280';
                var label = dot.parentElement ? dot.parentElement.querySelector('.user-status-text') : null;
                if (label) {
                    label.textContent = isOnline ? 'Active' : 'Offline';
                    label.className = isOnline ? 'user-status-text text-success' : 'user-status-text text-muted';
                }
            });
        }).catch(function(){});
    }

    function updateExistingUsersTable(users) {
        const tbody = document.getElementById('existing-users-tbody');
        if (!tbody) return;

        // Preserve expanded state and activity table across refresh
        var expandedUsers = {};
        tbody.querySelectorAll('.user-detail-row').forEach(function(r){
            if (r.style.display !== 'none') {
                var tb = r.querySelector('.user-activity-tbody');
                expandedUsers[r.dataset.user] = tb ? tb.innerHTML : '';
            }
        });

        const sortedUsernames = Object.keys(users).sort((a, b) => a.localeCompare(b));

        let html = '';
        sortedUsernames.forEach((username, index) => {
            const user = users[username];
            const roleLabel = ucfirstWords((user.role || 'user').replace(/_/g, ' '));
            const accessLabel = user.system_access
                ? '<span class="text-success fw-bold">ON</span>'
                : '<span class="text-danger fw-bold">OFF</span>';
            const emailLabel = htmlspecialchars(user.email || '-');
            const mobileLabel = htmlspecialchars(user.mobile || '-');

            let actionButtons = `
                <button type="button" class="btn btn-icon btn-sm user-expand-btn" style="background:transparent!important;border:1px solid rgba(0,0,0,0.12);color:#6b7280;" data-user="${htmlspecialchars(username)}" title="Details">
                    <i class="fas fa-chevron-down"></i>
                </button>`;

            if (userPermissions && userPermissions.can_edit_user) {
                actionButtons += `
                    <a href="${buildAdminPageUrl('edit_user', { username })}" class="btn btn-icon btn-primary btn-sm" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>`;
            }
            if (userPermissions && userPermissions.can_reset_user) {
                actionButtons += `
                    <button type="button" class="btn btn-icon btn-warning btn-sm reset-password-btn" data-bs-toggle="modal" data-bs-target="#resetPasswordModal" data-username="${htmlspecialchars(username)}" title="Reset Password">
                        <i class="fas fa-key"></i>
                    </button>`;
            }
            if (userPermissions && userPermissions.can_delete_user) {
                actionButtons += `
                    <button type="button" class="btn btn-icon btn-danger btn-sm delete-btn" data-username="${htmlspecialchars(username)}" title="Delete">
                        <i class="fas fa-trash-alt"></i>
                    </button>`;
            }
            
            html += `
                <tr data-username="${htmlspecialchars(username)}">
                    <td class="text-center user-col-index">${index + 1}</td>
                    <td class="user-col-logon"><strong>${htmlspecialchars(username)}</strong></td>
                    <td class="user-col-name">${htmlspecialchars(user.full_name || 'N/A')}</td>
                    <td class="user-col-email">${emailLabel}</td>
                    <td class="font-tech user-col-mobile">${mobileLabel}</td>
                    <td class="user-col-role"><small>${htmlspecialchars(roleLabel)}</small></td>
                    <td class="text-center user-col-status">
                        <span class="user-status-indicator">
                            <span class="user-status-dot" data-user="${htmlspecialchars(username)}" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#6b7280;"></span>
                            <span class="user-status-text text-muted">Offline</span>
                        </span>
                    </td>
                    <td class="text-center user-col-access">${accessLabel}</td>
                    <td class="user-mgmt-action-cell">
                        <div class="user-mgmt-action-buttons">
                            ${actionButtons}
                        </div>
                    </td>
                </tr>
                <tr class="user-detail-row" data-user="${htmlspecialchars(username)}" style="display:none;">
                    <td colspan="9" style="padding:0 !important;">
                        <div style="padding:10px 16px;background:rgba(var(--primary-rgb,24,53,147),0.03);border-bottom:1px solid var(--border-color,rgba(0,0,0,0.06));">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <div style="display:flex;align-items:center;justify-content:space-between;">
                                        <span class="text-muted" style="font-size:0.72rem;">Activity Log (last 20)</span>
                                     </div>
                                    <div class="user-activity-list" style="max-height:200px;overflow-y:auto;margin-top:4px;font-size:0.8rem;">
                                        <table style="width:100%;border-collapse:collapse;">
                                            <thead>
                                                <tr style="border-bottom:1px solid rgba(0,0,0,0.08);">
                                                    <th style="text-align:left;padding:3px 6px;font-weight:600;color:#6b7280;font-size:0.7rem;white-space:nowrap;">Time</th>
                                                    <th style="text-align:left;padding:3px 6px;font-weight:600;color:#6b7280;font-size:0.7rem;white-space:nowrap;">Action</th>
                                                    <th style="text-align:left;padding:3px 6px;font-weight:600;color:#6b7280;font-size:0.7rem;white-space:nowrap;">Details</th>
                                                </tr>
                                            </thead>
                                            <tbody class="user-activity-tbody">
                                                <tr><td colspan="3" style="padding:6px;color:#6b7280;" class="user-last-active">Loading...</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <hr style="margin:6px 0;opacity:0.15;">
                            <div class="row g-3">
                                <div class="col-md-3"><span class="text-muted" style="font-size:0.72rem;">Created</span><br><span class="font-tech" style="font-size:0.85rem;">${htmlspecialchars(user.created_at || '')}</span></div>
                                <div class="col-md-3"><span class="text-muted" style="font-size:0.72rem;">Role Permissions</span><br><span style="font-size:0.82rem;">${htmlspecialchars(roleLabel)} level</span></div>
                                <div class="col-md-3"><span class="text-muted" style="font-size:0.72rem;">Preferences</span><br><span style="font-size:0.82rem;">Theme: ${htmlspecialchars((user.preferences && user.preferences.theme) || 'default')}</span></div>
                            </div>
                        </div>
                    </td>
                </tr>`;
        });
        tbody.innerHTML = html;
        attachExistingUserListeners();
        // Restore expanded detail rows with previous activity list
        Object.keys(expandedUsers).forEach(function(user){
            var detailRow = tbody.querySelector('.user-detail-row[data-user="' + user + '"]');
            if (!detailRow) return;
            detailRow.style.display = 'table-row';
            var prevHtml = expandedUsers[user];
            if (prevHtml) {
                var tb = detailRow.querySelector('.user-activity-tbody');
                if (tb) tb.innerHTML = prevHtml;
            }
            var chevron = tbody.querySelector('.user-expand-btn[data-user="' + user + '"] i');
            if (chevron) chevron.className = 'fas fa-chevron-up';
        });
    }

    function ucfirstWords(value) {
        return String(value || '')
            .split(' ')
            .filter(Boolean)
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    }

    function attachExistingUserListeners() {
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.onclick = async function() {
                const user = this.dataset.username;
                if (confirm(`Are you sure you want to delete user '${user}'?`)) {
                    try {
                        const response = await fetch(userManagementApiUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'delete_user', username: user })
                        });
                        const data = await response.json();
                        displayApiResponse('Delete User', data);
                        if (data.success) fetchAndRefreshRequests();
                    } catch (error) { console.error(error); }
                }
            };
        });

        document.querySelectorAll('.reset-password-btn').forEach(btn => {
            btn.onclick = function() {
                if (usernameInput) usernameInput.value = this.dataset.username;
                const rri = document.getElementById('reset-request-index');
                if (rri) rri.value = '';
                const bulkInfo = document.getElementById('bulk-reset-info');
                if (bulkInfo) bulkInfo.style.display = 'none';
            };
        });

        document.querySelectorAll('.user-expand-btn').forEach(function(btn) {
            btn.onclick = function() {
                var user = this.dataset.user;
                var detailRow = document.querySelector('.user-detail-row[data-user="' + user + '"]');
                if (!detailRow) return;
                var visible = detailRow.style.display != 'none';
                detailRow.style.display = visible ? 'none' : 'table-row';
                this.querySelector('i').className = visible ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
                if (!visible) {
                    var tbody = detailRow.querySelector('.user-activity-tbody');
                    if (tbody && tbody.querySelector('.user-last-active')) {
                        fetch(userManagementApiUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'get_user_activity', username: user, limit: 20 })
                        }).then(function(r) { return r.json(); }).then(function(d) {
                            if (!tbody) return;
                            if (d.success && d.activity && d.activity.length) {
                                var html = '';
                                d.activity.forEach(function(a) {
                                    if (a.action === 'active_now') {
                                        html += '<tr><td colspan="3" style="padding:4px 6px;"><span class="text-success fw-bold"><i class="fas fa-circle" style="font-size:7px;vertical-align:middle;margin-right:4px;"></i>Active now</span> <span class="text-muted" style="font-size:0.7rem;">' + (a.timestamp||'') + '</span></td></tr>';
                                    } else {
                                        var ts = a.timestamp || a.Timestamp || '';
                                        var act = a.action || a.Action || '';
                                        var det = a.details || a.Details || '';
                                        html += '<tr style="border-bottom:1px solid rgba(0,0,0,0.04);">'
                                            + '<td style="padding:4px 6px;white-space:nowrap;color:#6b7280;font-size:0.72rem;vertical-align:top;">' + ts + '</td>'
                                            + '<td style="padding:4px 6px;white-space:nowrap;vertical-align:top;"><span class="badge bg-light text-dark" style="font-weight:400;font-size:0.7rem;">' + act + '</span></td>'
                                            + '<td style="padding:4px 6px;color:var(--text-color,#333d51);vertical-align:top;">' + (det || '-') + '</td>'
                                            + '</tr>';
                                    }
                                });
                                tbody.innerHTML = html;
                            } else {
                                tbody.innerHTML = '<tr><td colspan="3" style="padding:6px;color:#6b7280;">No recent activity</td></tr>';
                            }
                        }).catch(function(){if(tbody)tbody.innerHTML='<tr><td colspan="3" style="padding:6px;color:#6b7280;">No recent activity</td></tr>';});
                    }
                }
            };
        });

    }

    function updateRegistrationTable(requests) {
        const tbody = document.getElementById('pending-registration-tbody');
        if (!tbody) return;

        if (requests.length === 0) {
            tbody.innerHTML = `<tr style="display: table-row;"><td colspan="5" class="text-center py-4 text-muted" style="display: table-cell; text-align: center !important;"><i class="fas fa-info-circle me-2"></i>No pending registration requests.</td></tr>`;
            return;
        }

        let html = '';
        requests.forEach((req, index) => {
            html += `
                <tr class="pulse-request">
                    <td>${htmlspecialchars(req.hrms_id)}</td>
                    <td>${htmlspecialchars(req.username)}</td>
                    <td>${htmlspecialchars(req.email)}</td>
                    <td>${formatDateTime12H(req.timestamp)}</td>
                    <td class="user-mgmt-action-cell">
                        <div class="user-mgmt-action-buttons">
                            <button type="button" class="btn btn-icon btn-success btn-sm approve-btn" data-bs-toggle="modal" data-bs-target="#approveUserModal" data-index="${index}" title="Approve Request"><i class="fas fa-check"></i></button>
                            <button type="button" class="btn btn-icon btn-danger btn-sm deny-btn" data-index="${index}" title="Deny Request"><i class="fas fa-times"></i></button>
                        </div>
                    </td>
                </tr>`;
        });
        tbody.innerHTML = html;
        attachRegistrationListeners();
    }

    function formatDateTime12H(dateTimeStr) {
        if (!dateTimeStr) return 'N/A';
        const date = new Date(dateTimeStr);
        if (isNaN(date.getTime())) return dateTimeStr;
        const y = date.getFullYear(), m = String(date.getMonth() + 1).padStart(2, '0'), d = String(date.getDate()).padStart(2, '0');
        let hh = date.getHours(); const mm = String(date.getMinutes()).padStart(2, '0'), ss = String(date.getSeconds()).padStart(2, '0'), ampm = hh >= 12 ? 'PM' : 'AM';
        hh = hh % 12; hh = hh ? hh : 12; return `${y}-${m}-${d} ${String(hh).padStart(2, '0')}:${mm}:${ss} ${ampm}`;
    }

    function updateResetRequestsTable(requests) {
        const tbody = document.getElementById('password-reset-requests-tbody');
        if (!tbody) return;
        const pending = requests.filter(r => r.status === 'pending');
        if (pending.length === 0) {
            tbody.innerHTML = `<tr style="display: table-row;"><td colspan="7" class="text-center py-4 text-muted" style="display: table-cell; text-align: center !important;"><i class="fas fa-info-circle me-2"></i>No pending password reset requests.</td></tr>`;
            return;
        }
        let html = '';
        pending.forEach((req, index) => {
            html += `
                <tr class="pulse-request">
                    <td class="text-center"><input type="checkbox" class="form-check-input reset-request-check" data-username="${htmlspecialchars(req.username)}" data-request-index="${index}"></td>
                    <td class="text-center">${index + 1}</td>
                    <td><strong>${htmlspecialchars(req.username)}</strong></td>
                    <td>${htmlspecialchars(req.full_name || 'N/A')}</td>
                    <td><small>${htmlspecialchars(req.reason || 'N/A')}</small></td>
                    <td>${formatDateTime12H(req.timestamp)}</td>
                    <td class="user-mgmt-action-cell">
                        <div class="user-mgmt-action-buttons">
                            <button type="button" class="btn btn-icon btn-warning btn-sm reset-request-approve-btn" data-bs-toggle="modal" data-bs-target="#resetPasswordModal" data-username="${htmlspecialchars(req.username)}" data-request-index="${index}" title="Approve & Reset"><i class="fas fa-key"></i></button>
                            <button type="button" class="btn btn-icon btn-danger btn-sm reset-request-deny-btn" data-request-index="${index}" title="Deny Request"><i class="fas fa-times"></i></button>
                        </div>
                    </td>
                </tr>`;
        });
        tbody.innerHTML = html;
        attachResetListeners();
    }

    function attachRegistrationListeners() {
        document.querySelectorAll('.approve-btn').forEach(btn => {
            btn.onclick = function() {
                const index = this.dataset.index;
                const approveIndexInput = document.getElementById('approve-request-index');
                if (approveIndexInput) approveIndexInput.value = index;
                const confirmBtn = document.getElementById('confirm-approve-user-btn');
                if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.innerHTML = 'Approve User'; }
                const warningSection = document.getElementById('hrms-warning-section');
                if (warningSection) warningSection.style.display = 'none';
                const acknowledgeCheck = document.getElementById('acknowledge_custom_user');
                if (acknowledgeCheck) acknowledgeCheck.checked = false;
            };
        });
        document.querySelectorAll('.deny-btn').forEach(btn => {
            btn.onclick = async function() {
                const index = this.dataset.index;
                if (confirm('Are you sure you want to deny this registration request?')) {
                    try {
                        const response = await fetch(userManagementApiUrl, { method: 'POST', body: JSON.stringify({ action: 'deny', request_index: index }) });
                        const data = await response.json();
                        displayApiResponse('Deny Request', data);
                        if (data.success) fetchAndRefreshRequests();
                    } catch (error) { console.error(error); }
                }
            };
        });
    }

    function attachResetListeners() {
        document.querySelectorAll('.reset-request-approve-btn').forEach(btn => {
            btn.onclick = function() {
                if (usernameInput) usernameInput.value = this.dataset.username;
                const rri = document.getElementById('reset-request-index');
                if (rri) rri.value = this.dataset.requestIndex;
                const bulkInfo = document.getElementById('bulk-reset-info');
                if (bulkInfo) bulkInfo.style.display = 'none';
            };
        });
        document.querySelectorAll('.reset-request-deny-btn').forEach(btn => {
            btn.onclick = async function() {
                if (confirm('Are you sure you want to deny this password reset request?')) {
                    try {
                        const response = await fetch(userManagementApiUrl, { method: 'POST', body: JSON.stringify({ action: 'deny_password_reset', request_index: this.dataset.requestIndex }) });
                        const data = await response.json();
                        displayApiResponse('Deny Reset Request', data);
                        if (data.success) fetchAndRefreshRequests();
                    } catch (error) { console.error(error); }
                }
            };
        });
        const selectAll = document.getElementById('selectAllResetRequests'), bulkBtn = document.getElementById('bulkResetBtn');
        if (selectAll) {
            selectAll.onclick = function() {
                document.querySelectorAll('.reset-request-check').forEach(cb => cb.checked = selectAll.checked);
                if (bulkBtn) bulkBtn.style.display = selectAll.checked ? 'inline-block' : 'none';
            };
        }
        document.querySelectorAll('.reset-request-check').forEach(cb => {
            cb.onchange = () => {
                const checkedCount = document.querySelectorAll('.reset-request-check:checked').length;
                if (bulkBtn) bulkBtn.style.display = checkedCount > 0 ? 'inline-block' : 'none';
            };
        });
    }

    const bulkBtn = document.getElementById('bulkResetBtn');
    if (bulkBtn) {
        bulkBtn.onclick = function() {
            const checked = document.querySelectorAll('.reset-request-check:checked');
            const usernames = Array.from(checked).map(cb => cb.dataset.username);
            const indices = Array.from(checked).map(cb => cb.dataset.requestIndex);
            if (usernameInput) usernameInput.value = usernames.join(', ');
            const rri = document.getElementById('reset-request-index');
            if (rri) rri.value = indices.join(',');
            let bulkInfo = document.getElementById('bulk-reset-info');
            if (!bulkInfo && usernameInput) {
                bulkInfo = document.createElement('div'); bulkInfo.id = 'bulk-reset-info'; bulkInfo.className = 'alert alert-info py-1 small mb-2';
                usernameInput.parentNode.insertBefore(bulkInfo, usernameInput);
            }
            if (bulkInfo) { bulkInfo.innerHTML = `<i class="fas fa-info-circle me-1"></i> Bulk action: ${usernames.length} users selected.`; bulkInfo.style.display = 'block'; }
        };
    }

    const defaultPasswordCheck = document.getElementById('default_password_check');
    const newPasswordGroup = document.getElementById('new_password_group');
    if (defaultPasswordCheck && newPasswordGroup) {
        defaultPasswordCheck.onchange = function() { newPasswordGroup.style.display = this.checked ? 'none' : 'block'; };
        newPasswordGroup.style.display = defaultPasswordCheck.checked ? 'none' : 'block';
    }

    const confirmResetBtn = document.getElementById('confirm-reset-password-btn');
    if (confirmResetBtn) {
        confirmResetBtn.onclick = async function() {
            const payload = { action: 'reset_password_bulk', usernames: usernameInput.value.split(',').map(u => u.trim()), force_password_change: document.getElementById('force_password_change').checked, request_indices: document.getElementById('reset-request-index').value.split(',') };
            if (document.getElementById('default_password_check').checked) payload.use_default_password = true; else payload.new_password = document.getElementById('new_password').value;
            try {
                const response = await fetch(userManagementApiUrl, { method: 'POST', body: JSON.stringify(payload) });
                const data = await response.json();
                bootstrap.Modal.getInstance(resetPasswordModal).hide();
                displayApiResponse('Reset Passwords', data);
                if (data.success) fetchAndRefreshRequests();
            } catch (error) { console.error(error); }
        };
    }

    const approveDefaultPasswordCheck = document.getElementById('approve-default-password-check');
    const approveNewPasswordGroup = document.getElementById('approve-new_password_group');
    if (approveDefaultPasswordCheck && approveNewPasswordGroup) {
        approveDefaultPasswordCheck.onchange = function() { approveNewPasswordGroup.style.display = this.checked ? 'none' : 'block'; };
    }

    const confirmApproveBtn = document.getElementById('confirm-approve-user-btn');
    if (confirmApproveBtn) {
        confirmApproveBtn.onclick = async function() {
            const payload = { action: 'approve', request_index: document.getElementById('approve-request-index').value, ignore_hrms: document.getElementById('acknowledge_custom_user').checked };
            if (document.getElementById('approve-default-password-check').checked) payload.use_default_password = true; else payload.new_password = document.getElementById('approve-new-password').value;
            confirmApproveBtn.disabled = true; confirmApproveBtn.innerHTML = 'Processing...';
            try {
                const response = await fetch(userManagementApiUrl, { method: 'POST', body: JSON.stringify(payload) });
                const data = await response.json();
                if (!data.success && data.hrms_failed) {
                    const ws = document.getElementById('hrms-warning-section'); if (ws) ws.style.display = 'block';
                    confirmApproveBtn.disabled = false; confirmApproveBtn.innerHTML = 'Approve as Custom User'; return;
                }
                if (data.success) {
                    bootstrap.Modal.getOrCreateInstance(approveUserModal).hide();
                    displayApiResponse('Approve User', data); fetchAndRefreshRequests();
                } else { alert(data.message); confirmApproveBtn.disabled = false; confirmApproveBtn.innerHTML = 'Approve User'; }
            } catch (error) { console.error(error); confirmApproveBtn.disabled = false; confirmApproveBtn.innerHTML = 'Approve User'; }
        };
    }

    attachRegistrationListeners(); attachResetListeners(); attachExistingUserListeners();

    // UI: online status dot | Purpose: green/gray dot for user online status
    (function loadStatus(){
        fetch(userManagementApiUrl,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'get_online_users'})}).then(function(r){return r.json();}).then(function(d){
            if(!d.success||!d.users)return;
            var online={};for(var i=0;i<d.users.length;i++){online[d.users[i].username]=true;}
            document.querySelectorAll('.user-status-dot').forEach(function(dot){
                var isOnline = !!online[dot.dataset.user];
                dot.style.background = isOnline ? '#22c55e' : '#6b7280';
                var label = dot.parentElement ? dot.parentElement.querySelector('.user-status-text') : null;
                if (label) {
                    label.textContent = isOnline ? 'Active' : 'Offline';
                    label.className = isOnline ? 'user-status-text text-success' : 'user-status-text text-muted';
                }
            });
        }).catch(function(){});
    })();

    // Enhanced search
    var searchInput=document.getElementById('userSearchInput');
    if(searchInput){
        searchInput.oninput=function(){
            var f=this.value.toLowerCase();
            document.querySelectorAll('#existing-users-tbody tr:not(.user-detail-row)').forEach(function(row){
                var text='';for(var i=0;i<row.cells.length;i++){text+=row.cells[i].textContent.toLowerCase()+' ';}
                var match=text.includes(f);
                row.style.display=match?'':'none';
                var detailRow=document.querySelector('.user-detail-row[data-user="'+row.dataset.username+'"]');
                if(detailRow)detailRow.style.display=match?detailRow.style.display:'none';
            });
        };
    }

    if (window._userMgmtInterval) clearInterval(window._userMgmtInterval);
    window._userMgmtInterval = setInterval(fetchAndRefreshRequests, 10000);
    fetchAndRefreshRequests();
};

window.initUserManagement();

function htmlspecialchars(str) {
    if (str === null || typeof str === 'undefined') return '';
    var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(str).replace(/[&<>"']/g, m => map[m]);
}

function displayApiResponse(title, data) {
    const container = document.getElementById('actionTakenCardContainer'), titleSpan = document.getElementById('actionTakenTitle'), msgDisplay = document.getElementById('actionTakenMessageDisplay');
    if (container && titleSpan && msgDisplay) {
        container.classList.add('visible'); container.style.display = 'block'; titleSpan.textContent = title;
        msgDisplay.className = data.success ? 'alert alert-success' : 'alert alert-error';
        msgDisplay.innerHTML = data.message;
    }
}


function htmlspecialchars(str) {
    if (str === null || typeof str === 'undefined') return '';
    var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(str).replace(/[&<>"']/g, m => map[m]);
}

function displayApiResponse(title, data) {
    const container = document.getElementById('actionTakenCardContainer');
    const titleSpan = document.getElementById('actionTakenTitle');
    const msgDisplay = document.getElementById('actionTakenMessageDisplay');
    const msgDiv = container ? container.querySelector('.copy-content') : null;

    if (container && titleSpan && msgDisplay) {
        container.classList.add('visible');
        container.style.display = 'block';
        titleSpan.textContent = title;
        msgDisplay.className = data.success ? 'alert alert-success' : 'alert alert-error';
        msgDisplay.innerHTML = data.message;
        if (msgDiv) msgDiv.innerHTML = data.message;
    }
}
