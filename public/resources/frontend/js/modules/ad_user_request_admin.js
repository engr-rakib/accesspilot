window.initAdUserRequestsCard = function() {
    const appConfig = window.APP_CONFIG || {};
    const resolvedBaseUrl = appConfig.baseUrl || (typeof baseURL === 'string' ? baseURL : window.location.origin);
    const tbody = document.getElementById('ad-user-requests-tbody');
    const selectAllCheckbox = document.getElementById('selectAllAdUserRequests');
    const bulkApproveBtn = document.getElementById('bulkApproveAdRequestsBtn');
    const bulkDenyBtn = document.getElementById('bulkDenyAdRequestsBtn');
    if (!tbody) return;

    if (window._adUserRequestManualCreateListenerAttached !== true) {
        document.addEventListener('manualUserCreateCompleted', async event => {
            const detail = event.detail || {};
            const pendingRequestId = String(detail.requestId || '').trim();
            if (!pendingRequestId) {
                return;
            }

            try {
                const finalized = await finalizeQuickActionRequest(pendingRequestId, !!detail.success, detail.message || '');
                if (typeof displayActionTakenResult === 'function') {
                    displayActionTakenResult('User Request', finalized.message || detail.message || '', !!detail.success);
                }
                fetchRequests();
            } catch (error) {
                console.error(error);
            }
        });
        window._adUserRequestManualCreateListenerAttached = true;
    }

    function escapeHtml(value) {
        if (value === null || typeof value === 'undefined') return '';
        return String(value).replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    function buildDetails(request, isSvcAccount) {
        const type = request.request_type || '';
        const parts = [];

        if (isSvcAccount) {
            parts.push(`Server/Op: ${request.justification || ''}`);
        } else if (request.justification) {
            parts.push(request.justification);
        }

        if (request.hrms_id) parts.push(`HRMS ID: ${request.hrms_id}`);
        if (request.requested_name) parts.push(`Name: ${request.requested_name}`);
        if (request.custom_display_name) parts.push(`Display Name: ${request.custom_display_name}`);

        if (type.startsWith('exchange_')) {
            if (request.exchange_email) parts.push(`Email: ${request.exchange_email}`);
            if (request.exchange_extra) {
                const extraLabel = (type === 'exchange_set_quota') ? 'Quota' :
                    (type === 'exchange_set_forward') ? 'Forward To' :
                    (type === 'exchange_set_mail_tip') ? 'Mail Tip' :
                    (type === 'exchange_group_add_member' || type === 'exchange_group_remove_member') ? 'Member' :
                    'Extra';
                parts.push(`${extraLabel}: ${request.exchange_extra}`);
            }
            if (type === 'exchange_group_create') {
                if (request.group_name) parts.push(`Group: ${request.group_name}`);
                if (request.group_alias) parts.push(`Alias: ${request.group_alias}`);
                if (request.group_description) parts.push(`Desc: ${request.group_description}`);
            }
        }

        return parts.filter(Boolean).join(' | ') || 'No extra details';
    }

    function renderRows(requests) {
        const emptyMsg = document.getElementById('ad-user-requests-empty-msg');

        tbody.innerHTML = '';

        if (!requests || requests.length === 0) {
            if (emptyMsg) emptyMsg.style.display = 'block';
            syncBulkControls();
            return;
        }

        if (emptyMsg) emptyMsg.style.display = 'none';

        tbody.innerHTML = requests.map(request => {
            const requesterMeta = [request.requester_email, request.requester_contact].filter(Boolean).join(' | ');
            const isSvcAccount = request.request_type === 'create_service_account';
            const details = buildDetails(request, isSvcAccount);

            const domainClass = request.target_domain ? `domain-${escapeHtml(request.target_domain)}` : '';

            return `
                <tr class="pulse-request ${domainClass}">
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input ad-user-request-check" data-request-id="${escapeHtml(request.id)}">
                    </td>
                    <td>${escapeHtml(request.timestamp || '')}</td>
                    <td><strong>${escapeHtml(request.request_type_label || '')}</strong></td>
                    <td><strong>${escapeHtml(request.target_display_username || request.target_username || request.hrms_id || 'N/A')}</strong></td>
                    <td>
                        <div>${escapeHtml(request.requester_name || '')}</div>
                        <div class="ad-user-request-meta">${escapeHtml(requesterMeta)}</div>
                    </td>
                    <td><small>${escapeHtml(details || 'No extra details')}</small></td>
                    <td class="action-cell">
                        <div class="ad-user-request-actions">
                            <button type="button" class="btn btn-icon btn-success btn-sm ad-request-approve-btn" data-request-id="${escapeHtml(request.id)}" title="Approve & Execute">
                                <i class="fas fa-play"></i>
                            </button>
                            <button type="button" class="btn btn-icon btn-danger btn-sm ad-request-deny-btn" data-request-id="${escapeHtml(request.id)}" title="Deny Request">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
        }).join('');

        attachRowActions();
        attachSelectionHandlers();
        syncBulkControls();
    }

    async function fetchRequests() {
        try {
            const response = await fetch(resolvedBaseUrl + '/ad_user_request_admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_pending_ad_user_requests' })
            });
            const data = await response.json();
            if (data.success) {
                renderRows(data.requests || []);
            }
        } catch (error) {
            console.error('Failed to load AD user requests:', error);
        }
    }

    function getSelectedRequestIds() {
        return Array.from(document.querySelectorAll('.ad-user-request-check:checked')).map(input => input.dataset.requestId);
    }

    function syncBulkControls() {
        const rowChecks = Array.from(document.querySelectorAll('.ad-user-request-check'));
        const checkedCount = rowChecks.filter(input => input.checked).length;

        if (selectAllCheckbox) {
            selectAllCheckbox.checked = rowChecks.length > 0 && checkedCount === rowChecks.length;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < rowChecks.length;
        }

        const hasSelection = checkedCount > 0;
        if (bulkApproveBtn) bulkApproveBtn.style.display = hasSelection ? 'inline-flex' : 'none';
        if (bulkDenyBtn) bulkDenyBtn.style.display = hasSelection ? 'inline-flex' : 'none';
    }

    function attachSelectionHandlers() {
        document.querySelectorAll('.ad-user-request-check').forEach(input => {
            input.onchange = syncBulkControls;
        });
    }

    async function processRequest(action, requestId, note = '', extra = {}) {
        const payload = { action, request_id: requestId, ...extra };
        if (note !== '') {
            payload.note = note;
        }

        const response = await fetch(resolvedBaseUrl + '/ad_user_request_admin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        return response.json();
    }

    async function finalizeQuickActionRequest(requestId, success, message) {
        return processRequest('finalize_ad_user_request', requestId, '', { success, message });
    }

    function waitForQuickActionResult(expectedAction, expectedUsername) {
        return new Promise((resolve, reject) => {
            const timeoutId = window.setTimeout(() => {
                document.removeEventListener('quickActionCompleted', handler);
                reject(new Error('Timed out waiting for quick action result.'));
            }, 45000);

            function handler(event) {
                const detail = event.detail || {};
                const actionMatches = detail.action === expectedAction;
                const usernameMatches = String(detail.username || '').trim().toLowerCase() === String(expectedUsername || '').trim().toLowerCase();
                if (!actionMatches || !usernameMatches) {
                    return;
                }

                window.clearTimeout(timeoutId);
                document.removeEventListener('quickActionCompleted', handler);
                resolve(detail);
            }

            document.addEventListener('quickActionCompleted', handler);
        });
    }

    async function runApproveThroughQuickAction(requestId) {
        const prepare = await processRequest('prepare_ad_user_request', requestId);
        if (!prepare.success || !prepare.execution) {
            return prepare;
        }

        const action = String(prepare.execution.action || '');
        const target = String(prepare.execution.target || '').trim();
        if (action === 'manualCreateCustomUser') {
            return openManualCreateFromRequest(requestId, prepare);
        }

        const usernameInput = document.getElementById('username');
        const quickActionButton = document.querySelector(`.action-button[value="${action}"]`);

        if (!usernameInput || !quickActionButton || !target) {
            return processRequest('approve_ad_user_request', requestId);
        }

        usernameInput.value = target;
        usernameInput.dispatchEvent(new Event('input', { bubbles: true }));
        usernameInput.dispatchEvent(new Event('change', { bubbles: true }));
        usernameInput.focus();

        const resultPromise = waitForQuickActionResult(action, target);
        quickActionButton.click();

        const result = await resultPromise;
        const finalized = await finalizeQuickActionRequest(requestId, !!result.success, result.message || '');

        return {
            success: !!result.success,
            message: result.message || finalized.message || '',
            finalize: finalized,
            request: prepare.request || null
        };
    }

    function buildManualCreateDescription(request) {
        const parts = [
            request.requester_name ? `Requested by ${request.requester_name}` : '',
            request.requester_email || '',
            request.requester_contact || '',
            request.justification || ''
        ].filter(Boolean);
        return parts.join(' | ');
    }

    async function openManualCreateFromRequest(requestId, prepare) {
        const manualCreateButton = document.getElementById('ADmanualUserCreateButton');
        const manualUsernameInput = document.getElementById('manualUsername');
        const manualDisplayNameInput = document.getElementById('manualDisplayName');
        const manualDescriptionInput = document.getElementById('manualDescription');
        const manualOUDisplay = document.getElementById('manualOUDisplay');
        const submitManualCreateButton = document.getElementById('submitManualCreate');

        if (!manualCreateButton || !manualUsernameInput || !manualDisplayNameInput || !manualDescriptionInput || !submitManualCreateButton) {
            return {
                success: false,
                message: 'Manual User Creation form is not available on this page.',
            };
        }

        const request = prepare.request || {};
        const manualCreate = prepare.execution.manual_create || {};

        manualCreateButton.click();

        manualUsernameInput.value = String(manualCreate.username || request.custom_username || request.target_username || '').trim();
        manualDisplayNameInput.value = String(manualCreate.display_name || request.custom_display_name || request.requested_name || '').trim();
        manualDescriptionInput.value = String(manualCreate.description || buildManualCreateDescription(request)).trim();
        submitManualCreateButton.dataset.pendingRequestId = requestId;

        // Handle service account auto-check
        if (manualCreate.is_service_account) {
            const svcCheck = document.getElementById('serviceAccountCheck');
            const svcOpInput = document.getElementById('serverOperation');
            if (svcCheck) {
                svcCheck.checked = true;
                svcCheck.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (svcOpInput && manualCreate.server_operation) {
                svcOpInput.value = manualCreate.server_operation;
            }
        }

        manualUsernameInput.dispatchEvent(new Event('input', { bubbles: true }));
        manualDisplayNameInput.dispatchEvent(new Event('input', { bubbles: true }));
        manualDescriptionInput.dispatchEvent(new Event('input', { bubbles: true }));

        if (manualOUDisplay) {
            manualOUDisplay.focus();
        } else {
            submitManualCreateButton.focus();
        }

        return {
            success: true,
            message: 'Manual User Creation form prepared. Select OU if needed, then submit the manual creation form to complete this request.',
            request
        };
    }

    async function processBulk(action) {
        const requestIds = getSelectedRequestIds();
        if (requestIds.length === 0) return;

        let note = '';
        if (action === 'deny_ad_user_request') {
            note = window.prompt('Optional deny note for selected requests:', '') || '';
        } else if (!confirm(`Process ${requestIds.length} selected request(s) now?`)) {
            return;
        }

        if (bulkApproveBtn) bulkApproveBtn.disabled = true;
        if (bulkDenyBtn) bulkDenyBtn.disabled = true;
        if (selectAllCheckbox) selectAllCheckbox.disabled = true;

        let successCount = 0;
        const failed = [];

        for (const requestId of requestIds) {
            try {
                const data = action === 'approve_ad_user_request'
                    ? await runApproveThroughQuickAction(requestId)
                    : await processRequest(action, requestId, note);
                if (data.success) {
                    successCount += 1;
                } else {
                    failed.push(data.message || requestId);
                }
            } catch (error) {
                failed.push(requestId);
                console.error(error);
            }
        }

        if (typeof displayActionTakenResult === 'function') {
            const verb = action === 'approve_ad_user_request' ? 'approved' : 'denied';
            let message = `${successCount} request(s) ${verb}.`;
            if (failed.length > 0) {
                message += ` ${failed.length} failed.`;
            }
            displayActionTakenResult('User Request', message, failed.length === 0);
        }

        fetchRequests();

        if (bulkApproveBtn) bulkApproveBtn.disabled = false;
        if (bulkDenyBtn) bulkDenyBtn.disabled = false;
        if (selectAllCheckbox) selectAllCheckbox.disabled = false;
    }

    function attachRowActions() {
        document.querySelectorAll('.ad-request-approve-btn').forEach(button => {
            button.onclick = async function() {
                if (!confirm('Approve and execute this AD user request now?')) return;
                const requestId = this.dataset.requestId;
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                try {
                    const data = await runApproveThroughQuickAction(requestId);
                    if (typeof displayActionTakenResult === 'function') {
                        displayActionTakenResult('User Request', data.message || '', !!data.success);
                    }
                    fetchRequests();
                } catch (error) {
                    console.error(error);
                    if (typeof displayActionTakenResult === 'function') {
                        displayActionTakenResult('User Request', `Error: ${error.message}`, false);
                    }
                } finally {
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-play"></i>';
                }
            };
        });

        document.querySelectorAll('.ad-request-deny-btn').forEach(button => {
            button.onclick = async function() {
                const note = window.prompt('Optional deny note:', '') || '';
                const requestId = this.dataset.requestId;
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                try {
                    const data = await processRequest('deny_ad_user_request', requestId, note);
                    if (typeof displayActionTakenResult === 'function') {
                        displayActionTakenResult('User Request', data.message || '', !!data.success);
                    }
                    fetchRequests();
                } catch (error) {
                    console.error(error);
                } finally {
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-times"></i>';
                }
            };
        });
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.onchange = function() {
            document.querySelectorAll('.ad-user-request-check').forEach(input => {
                input.checked = this.checked;
            });
            syncBulkControls();
        };
    }

    if (bulkApproveBtn) {
        bulkApproveBtn.onclick = function() {
            processBulk('approve_ad_user_request');
        };
    }

    if (bulkDenyBtn) {
        bulkDenyBtn.onclick = function() {
            processBulk('deny_ad_user_request');
        };
    }

    fetchRequests();
    if (window._adUserRequestInterval) {
        clearInterval(window._adUserRequestInterval);
    }
    window._adUserRequestInterval = setInterval(fetchRequests, 10000);
};

window.initAdUserRequestsCard();
