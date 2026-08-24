// Initialize Global Action Result Card elements
window.actionTakenCardContainer = document.getElementById('actionTakenCardContainer');
window.actionTakenTitleSpan = document.getElementById('actionTakenTitle');
window.actionTakenIcon = document.getElementById('actionTakenIcon');
window.actionTakenMessageDisplay = document.getElementById('actionTakenMessageDisplay');
const actionTakenCardContent = window.actionTakenCardContainer ? window.actionTakenCardContainer.querySelector('.card-body') : null;
const actionTakenMessageDiv = actionTakenCardContent ? actionTakenCardContent.querySelector('.copy-content') : null;

const actionButtons = document.querySelectorAll('.action-button:not([value="manualCreate"]):not(#submitManualCreate):not([value="ADmanualUserCreate"]):not(#getHrmsAdReportButton):not(#exportAdUsersButton):not(#adHealthCheckButton):not(#userReportButton):not(#submitUserReport):not(#disableAllInactive):not(#ADdirectoryBuilderButton)');
const usernameInput = document.getElementById('username');
const serverUserInfoSection = document.getElementById('serverUserInfoDisplay');
const employeeInfoSection = document.getElementById('employeeInfoDisplay');

const resolvedBaseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (typeof baseURL === 'string' ? baseURL : '');
const executeActionApiUrl = `${resolvedBaseUrl}/api/index.php?endpoint=execute_action`;
const allLogDataApiBaseUrl = `${resolvedBaseUrl}/api/index.php?endpoint=get_all_log_data`;
const dashboardLogDataApiUrl = `${resolvedBaseUrl}/api/index.php?endpoint=log_data`;

function buildAdminApiUrl(endpoint, params = {}) {
    const url = new URL(`${resolvedBaseUrl}/api/index.php`, window.location.origin);
    url.searchParams.set('endpoint', endpoint);
    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            url.searchParams.set(key, value);
        }
    });
    return url.toString();
}

// Store original titles with icons
const serverInfoOriginalTitleHtml = serverUserInfoSection ? serverUserInfoSection.querySelector('h3').outerHTML : '';
const employeeInfoOriginalTitleHtml = employeeInfoSection ? employeeInfoSection.querySelector('h3').outerHTML : '';



// Helper function to format server status values with color coding
function formatServerStatusValue(label, value) {
    const className = getServerStatusClass(label, value);
    let formattedValue = htmlspecialchars(value);

    if (className) {
        return `<span class="status-box ${className}">${formattedValue}</span>`;
    }
    return formattedValue;
}

function getServerStatusClass(label, value) {
    const lowerLabel = String(label || '').toLowerCase();
    const lowerValue = String(value || '').trim().toLowerCase();

    if (lowerLabel.includes('account status')) {
        return lowerValue.includes('enable') || lowerValue.includes('active') ? 'status-active' : 'status-other';
    }
    if (lowerLabel.includes('account lock status')) {
        return lowerValue === 'unlocked' ? 'status-active' : 'status-other';
    }
    if (lowerLabel.includes('password status')) {
        return lowerValue === 'valid' || lowerValue === 'never expires' ? 'status-active' : 'status-other';
    }
    return '';
}

function isNegativeServerStatus(label, value) {
    return getServerStatusClass(label, value) === 'status-other';
}

function getEmployeeStatusClass(status) {
    const normalizedStatus = String(status || '').trim().toUpperCase();
    if (normalizedStatus === 'ACTIVE') return 'status-active';
    if (normalizedStatus) return 'status-other';
    return '';
}

function isNegativeEmployeeStatus(status) {
    return getEmployeeStatusClass(status) === 'status-other';
}

function formatInfoValue(label, value, type = 'default') {
    if (type === 'server-status') return formatServerStatusValue(label, value);
    if (type === 'employee-status') {
        const statusClass = getEmployeeStatusClass(value);
        return statusClass
            ? `<span class="status-box ${statusClass}">${htmlspecialchars(value)}</span>`
            : htmlspecialchars(value);
    }
    return htmlspecialchars(value);
}

function renderInfoRow(label, value, type = 'default') {
    return `<li class="info-row"><span class="hrms-value"><span class="hrms-label">${htmlspecialchars(label)}: </span>${formatInfoValue(label, value, type)}</span></li>`;
}

function renderInfoTextRow(text) {
    return `<li class="info-row info-row-text"><span class="hrms-value">${htmlspecialchars(text)}</span></li>`;
}

function renderInfoSection(title, rows, extraClass = '') {
    if (!rows.length) return '';
    const className = `info-group ${extraClass}`.trim();
    return `
        <div class="${className}">
            <div class="info-group-title">${htmlspecialchars(title)}</div>
            <ul class="hrms-list info-group-list">${rows.join('')}</ul>
        </div>
    `;
}

function renderInfoTopMeta(label, value) {
    return `
        <div class="info-top-meta">
            <div class="info-top-summary">
                <div class="info-top-label">${htmlspecialchars(label)}:</div>
                <div class="info-top-value">${htmlspecialchars(value || 'N/A')}</div>
            </div>
        </div>
    `;
}

function resolveCardPhotoUrl(primaryUrl) {
    return primaryUrl && String(primaryUrl).trim() !== '' ? primaryUrl : appLogoUrl;
}

function renderCardPhotoFrame(imageUrl, statusClass = '') {
    const resolvedImageUrl = resolveCardPhotoUrl(imageUrl);
    return `<div class="hrms-profile-pic-container ${statusClass}"><img src="${resolvedImageUrl}" alt="User Photo" class="hrms-profile-pic" onerror="this.onerror=null;this.src=appLogoUrl;"></div>`;
}

function renderInfoCardContent(titleHtml, metaHtml, sectionsHtml, stateClass = 'alert alert-success', overlayHtml = '', shellClass = '') {
    const cardClassName = `${stateClass} info-card-shell ${shellClass}`.trim();
    return `${titleHtml}<div class="${cardClassName}">${overlayHtml}${metaHtml}<div class="info-groups-stack">${sectionsHtml}</div></div>`;
}

// EFFECT: shimmer skeleton placeholder | Purpose: loading placeholder for info cards
function showPlaceholder(element, titleHtml) {
    const title = (titleHtml && titleHtml.includes('<h3')) ? titleHtml : `<h3 class="card-title">${titleHtml}</h3>`;
    element.innerHTML = `
        ${title}
        <div class="placeholder-content">
            <div class="animated-background">
                <div class="masker header-top"></div>
                <div class="masker header-left"></div>
                <div class="masker header-right"></div>
                <div class="masker header-bottom"></div>
                <div class="masker subheader-left"></div>
                <div class="masker subheader-right"></div>
                <div class="masker content-top"></div>
                <div class="masker content-first-end"></div>
                <div class="masker content-second-line"></div>
                <div class="masker content-second-end"></div>
                <div class="masker content-third-line"></div>
                <div class="masker content-third-end"></div>
            </div>
        </div>
    `;
}

// EFFECT: loading dots animation | Purpose: visual feedback for in-progress actions
function showLoading(element, titleHtml = '') {
    if (typeof window.showLoadingAnimation === 'function') {
        if (titleHtml) element.innerHTML = titleHtml;
        window.showLoadingAnimation(element);
        if (titleHtml) {
             // If titleHtml was provided, we need to prepend it since showLoadingAnimation overwrites innerHTML
             element.innerHTML = titleHtml + element.innerHTML;
        }
    } else {
        const colors = ['#1976D2', '#AA3A46', '#1B5E20'];
        element.innerHTML = (titleHtml || '') + 
            `<div class="alert-loading-content">
                <div class="loading-dots">
                    <span style="background-color: ${colors[0]};"></span>
                    <span style="background-color: ${colors[1]};"></span>
                    <span style="background-color: ${colors[2]};"></span>
                </div>
                <div class="loading-text">Your request is underway...</div>
            </div>`;
    }
}
function decodeHtml(html) {
    var txt = document.createElement("textarea");
    txt.innerHTML = html;
    return txt.value;
}

function getQuickActionButtonLabel(button) {
    if (!button) return '';
    if (!button.dataset.originalLabel) {
        const textNodes = Array.from(button.childNodes)
            .filter(node => node.nodeType === Node.TEXT_NODE)
            .map(node => node.textContent.trim())
            .filter(Boolean);
        button.dataset.originalLabel = textNodes.join(' ') || button.textContent.trim();
    }
    return button.dataset.originalLabel;
}

// EFFECT: quick action button states | Purpose: loading spinner / success check / error cross visual feedback
function setQuickActionButtonState(button, state, labelOverride = '') {
    if (!button) return;

    if (!button.dataset.originalHtml) {
        button.dataset.originalHtml = button.innerHTML;
    }

    const originalLabel = getQuickActionButtonLabel(button);
    const label = labelOverride || originalLabel;

    button.classList.remove('quick-action-loading', 'quick-action-success', 'quick-action-error', 'quick-action-info');

    if (state === 'loading') {
        button.disabled = true;
        button.classList.add('quick-action-loading');
        button.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${label}`;
        return;
    }

    if (state === 'success') {
        button.disabled = false;
        button.classList.add('quick-action-success');
        button.innerHTML = `<i class="fas fa-check-circle"></i> ${label}`;
        return;
    }

    if (state === 'error') {
        button.disabled = false;
        button.classList.add('quick-action-error');
        button.innerHTML = `<i class="fas fa-times-circle"></i> ${label}`;
        return;
    }

    if (state === 'info') {
        button.disabled = false;
        button.classList.add('quick-action-info');
        button.innerHTML = `<i class="fas fa-check"></i> ${label}`;
        return;
    }

    button.disabled = false;
    button.innerHTML = button.dataset.originalHtml;
}

// EFFECT: auto-revert button state | Purpose: reset button to default after delay
function resetQuickActionButton(button, delay = 1500) {
    if (!button) return;
    window.setTimeout(() => {
        setQuickActionButtonState(button, 'default');
    }, delay);
}

function emitQuickActionResult(action, username, success, message) {
    document.dispatchEvent(new CustomEvent('quickActionCompleted', {
        detail: {
            action,
            username,
            success: !!success,
            message: message || ''
        }
    }));
}

function renderRecentActivityLogs(logs) {
    const tbody = document.getElementById('detailed-logs-tbody');
    const noLogsMessage = document.getElementById('no-logs-message');
    const percentageBadge = document.getElementById('log-success-percentage');

    if (!tbody) return;
    tbody.innerHTML = '';

    if (!logs || logs.length === 0) {
        if (noLogsMessage) noLogsMessage.style.display = 'block';
        if (percentageBadge) {
            percentageBadge.textContent = 'N/A';
            percentageBadge.style.backgroundColor = '#6c757d';
        }
        return;
    }

    if (noLogsMessage) noLogsMessage.style.display = 'none';

    let successCount = 0;
    logs.forEach(log => {
        let ts = String(log.timestamp || '');
        // Fix for missing space between date and time
        if (ts.length >= 10 && ts[10] !== ' ') {
            ts = ts.slice(0, 10) + ' ' + ts.slice(10);
        }
        const timestampParts = ts.split(' ');
        const timestampDate = timestampParts.slice(0, 1).join(' ');
        const timestampTime = timestampParts.slice(1).join(' ');

        const row = document.createElement('tr');
        const statusLabel = log.status || '';
        let statusClass = 'status-info';
        const upperStatus = statusLabel.toUpperCase();

        if (upperStatus === 'SUCCESS') successCount++;

        if (upperStatus === 'SUCCESS') statusClass = 'status-success';
        else if (upperStatus.includes('FAIL') || upperStatus.includes('ERROR')) statusClass = 'status-failed';
        else if (upperStatus.includes('PENDING')) statusClass = 'status-pending';
        else if (upperStatus.includes('WARNING') || upperStatus.includes('NOT FOUND')) statusClass = 'status-warning';

        row.innerHTML = `
            <td class="log-timestamp-cell">
                <span class="log-timestamp-date">${timestampDate}</span>
                <span class="log-timestamp-time">${timestampTime}</span>
            </td>
            <td><span class="domain-badge">${log.domain || 'N/A'}</span></td>
            <td><span class="action-badge action-${log.action.toLowerCase().replace(/[^a-zA-Z0-9]/g, '_')}">${log.action}</span></td>
            <td><span title="${escapeHTML(log.targetUser)}">${escapeHTML(log.targetUser)}</span></td>
            <td>${log.category}</td>
            <td>${log.performedBy || 'N/A'}</td>
            <td class="message-column">${log.message || ''}</td>
            <td><span class="status-badge ${statusClass}">${statusLabel}</span></td>
        `;
        tbody.appendChild(row);
    });

    if (typeof window.syncLogTableHeight === 'function') {
        window.requestAnimationFrame(() => window.syncLogTableHeight());
    }

    if (percentageBadge) {
        const total = logs.length;
        const percentage = Math.round((successCount / total) * 100);
        percentageBadge.textContent = `${percentage}%`;

        if (percentage >= 80) percentageBadge.style.backgroundColor = '#28a745';
        else if (percentage >= 50) percentageBadge.style.backgroundColor = '#ffc107';
        else percentageBadge.style.backgroundColor = '#dc3545';
    }
}

// Global helper to refresh info cards (supports multi-user with tabs)
function buildCardBodyLayout(photo, meta, content) {
    return '<div class="card-body-layout"><div class="card-body-main">' + (meta || '') + '<div class="info-groups-stack">' + (content || 'No data available.') + '</div></div><div class="card-body-sidebar">' + (photo || '') + '</div></div>';
}

function buildTabPaneHtml(html, username, isActive) {
    return '<div class="info-card-tab-pane' + (isActive ? ' active' : '') + '">' + buildCardBodyLayout(html.photo, html.meta, html.content) + '</div>';
}

function renderServerHtml(data) {
    if (!data) {
        return { content: '<div class="alert alert-error">Server information not found.</div>', meta: '', photo: '' };
    }
    const infoOutput = data.data?.infoOutput;
    if (!infoOutput) {
        return { content: '<div class="alert alert-error">' + htmlspecialchars(data.message || 'Server information not found.') + '</div>', meta: '', photo: '' };
    }
    const adData = data.data.adData || {};
    const lines = infoOutput.split(/\r?\n/);
    const serverSections = [];
    let currentSection = null;
    let principalId = '', logonName = '';
    const sectionMap = {
        'current user conditions -': 'Current User Conditions',
        'assigned privileges:': 'Assigned Privileges',
        'user activity -': 'User Activity',
        'infrastructure information -': 'Infrastructure Information',
        'user profiling information -': 'User Profiling Information',
        'user inforamtion-': 'User Information',
        'exchange mailbox -': 'Exchange Mailbox'
    };
    let pendingGap = false;
    lines.forEach(line => {
        const t = line.trim(), n = t.toLowerCase();
        if (!t) { pendingGap = true; return; }
        if (n.startsWith('user principal id:')) {
            principalId = t.substring(t.indexOf(':') + 1).trim();
            return;
        }
        if (n.includes('workstation')) {
            // fall through to regular row parser
        } else if (n.includes('ad account') || n.includes('samaccount') || n.includes('logon name') || n.includes('logon id')) {
            const ci = t.indexOf(':');
            if (ci !== -1) logonName = t.substring(ci + 1).trim();
            return;
        }
        if (sectionMap[n]) { currentSection = { title: sectionMap[n], rows: [], isAlert: false }; serverSections.push(currentSection); pendingGap = false; return; }
        if (n.startsWith('multiple users found')) {
            const fi = t.toLowerCase().indexOf('found:');
            if (!currentSection) { currentSection = { title: 'Multiple Users', rows: [], isAlert: false }; serverSections.push(currentSection); }
            currentSection.rows.push(renderInfoTextRow(fi >= 0 ? t.substring(0, fi).trim() : t));
            if (fi >= 0) t.substring(fi + 6).split(',').map(i => i.trim()).filter(Boolean).forEach(m => { currentSection.rows.push('<li class="info-row privilege-row"><span class="hrms-value">' + htmlspecialchars(m) + '</span></li>'); });
            pendingGap = false;
            return;
        }
        if (t.startsWith('------||>')) {
            if (!currentSection) { currentSection = { title: 'Assigned Privileges', rows: [], isAlert: false }; serverSections.push(currentSection); }
            currentSection.rows.push('<li class="info-row privilege-row"><span class="hrms-value">' + htmlspecialchars(t.replace('------||>', '').trim()) + '</span></li>');
            pendingGap = false;
            return;
        }
        const ci = t.indexOf(':');
        if (ci !== -1 && currentSection) {
            if (pendingGap) { currentSection.rows.push('<li class="info-row-hrms-gap"></li>'); pendingGap = false; }
            const label = t.substring(0, ci).trim(), value = t.substring(ci + 1).trim();
            if (isNegativeServerStatus(label, value)) currentSection.isAlert = true;
            currentSection.rows.push(renderInfoRow(label, value, 'server-status'));
        }
    });
    const identityRows = [];
    if (logonName) identityRows.push(renderInfoRow('Logon Name', logonName));
    if (principalId) identityRows.push(renderInfoRow('Principal ID', principalId));
    const identityHtml = identityRows.length ? renderInfoSection('Identity', identityRows, '') : '';
    const sectionsHtml = serverSections.map(s => renderInfoSection(s.title, s.rows, s.isAlert ? 'info-group-alert' : '')).join('');
    if (!identityHtml && !sectionsHtml) {
        return { content: '<div class="alert alert-error">' + htmlspecialchars(infoOutput).replace(/\n/g, '<br>') + '</div>', meta: '', photo: '' };
    }
    return { content: identityHtml + sectionsHtml, meta: '', photo: renderCardPhotoFrame(adData.thumbnailPhotoDataUri || '', '') };
}

function renderHrmsHtml(data) {
    if (!data.success || !data.data.apiData) {
        return { content: '<div class="alert alert-error">' + htmlspecialchars(data.message || 'Employee information not found.') + '</div>', meta: '', photo: '' };
    }
    const api = data.data.apiData;
    const empStatus = api['EMP_STS'] || '';
    const statusClass = getEmployeeStatusClass(empStatus);
    const picUrl = api['PIC_URL_'] || '';

    const identityRows = [];
    if (api['EMP_CODE']) identityRows.push(renderInfoRow('Employee ID', api['EMP_CODE']));
    if (api['EMP_ID']) identityRows.push(renderInfoRow('EMP Code', api['EMP_ID']));

    const fields = [
        { title: 'Employee Overview', alert: false, rows: [
            { k: 'EMP_NAME', l: 'Name' }, { k: 'EMP_STS', l: 'Status', t: 'employee-status' },
            { k: 'DESIGNATION', l: 'Designation' }, { k: 'RANK', l: 'Rank' }, { k: 'ROLE_TITLE', l: 'Role Title' },
            { k: 'EMAIL', l: 'Email' }, { k: 'MOBILE', l: 'Mobile' }
        ]},
        { title: 'Organization', alert: false, rows: [
            { k: 'OPERATING_UNIT_TITLE', l: 'Operating Unit' }, { k: 'LOCATION_TITLE', l: 'Location' },
            { k: 'DEPARTMENT_TITLE', l: 'Department' }, { k: 'SECTION_TITLE', l: 'Section' },
            { k: 'SUB_SECTION_TITLE', l: 'Sub Section' }, { k: 'PRODUCT_TITLE', l: 'Product' },
            { k: 'PRODUCT_GROUP_TITLE', l: 'Product Group' }, { k: 'TEAM_TITLE', l: 'Team' },
            { k: 'SUB_TEAM_TITLE', l: 'Sub Team' }, { k: 'RESPONSIBILITY', l: 'Responsibility' }
        ]},
        { title: 'Personal', alert: false, rows: [
            { k: 'EMP_CAT_TITLE', l: 'Employee Category' }, { k: 'JOINING_DT', l: 'Joining Date' },
            { k: 'JOINING_DATE', l: 'Joining Date (Alt)' }, { k: 'DOB', l: 'Date of Birth' },
            { k: 'AGE', l: 'Age' }, { k: 'GENDER', l: 'Gender' },
            { k: 'LAST_EDU_TITLE', l: 'Last Education' }, { k: 'ADDRESS_PERMANENT', l: 'Permanent Address' }
        ]}
    ];
    const sectionsHtml = fields.map(s => {
        const rows = s.rows.map(f => {
            const v = api[f.k];
            if (v === undefined || v === null || v === '') return '';
            if (f.k === 'EMP_STS' && isNegativeEmployeeStatus(v)) s.alert = true;
            return renderInfoRow(f.l, v, f.t || 'default');
        }).filter(Boolean);
        return renderInfoSection(s.title, rows, s.alert ? 'info-group-alert' : '');
    }).join('');
    const identityHtml = identityRows.length ? renderInfoSection('Identity', identityRows, '') : '';
    let img = '';
    if (picUrl) img = hrmsImgBaseUrl + '/' + (picUrl.startsWith('images/repository/') ? picUrl.substring('images/repository/'.length) : picUrl);
    return { content: identityHtml + sectionsHtml, meta: '', photo: renderCardPhotoFrame(img, statusClass) };
}

window.refreshInfoCards = async function(user) {
    try {
        let sDisplay = document.getElementById('serverUserInfoDisplay');
        let eDisplay = document.getElementById('employeeInfoDisplay');
        if (!sDisplay && !eDisplay) {
            for (let w = 0; w < 10; w++) {
                await new Promise(r => setTimeout(r, 150));
                sDisplay = document.getElementById('serverUserInfoDisplay');
                eDisplay = document.getElementById('employeeInfoDisplay');
                if (sDisplay || eDisplay) break;
            }
            if (!sDisplay && !eDisplay) return;
        }

        const allUsers = user.split(/[\s,;]+/).map(u => u.trim()).filter(Boolean);
        if (allUsers.length === 0) return;

        function extractNearbyIds(r) {
            let ids = null;
            const sug = r.server?.data?.suggestions;
            if (sug && typeof sug === 'object') {
                for (const v of Object.values(sug)) { if (Array.isArray(v)) { ids = v; break; } }
            }
            if (!ids && r.server?.data?.infoOutput) {
                const m = r.server.data.infoOutput.match(/(?:Multiple matching IDs|Nearby IDs that exist in AD):\s*([\d,\s]+)/);
                if (m) ids = m[1].split(',').map(id => id.trim()).filter(Boolean);
            }
            return ids || [];
        }

        function renderCurrentCards(serverResults, employeeResults, sDisplay, eDisplay) {
            if (sDisplay) buildTabbedCard(serverInfoOriginalTitleHtml, serverResults, 'server', renderServerHtml, sDisplay);
            if (eDisplay) {
                const nonSuggestions = employeeResults.filter(r => !r.isSuggestion);
                buildTabbedCard(employeeInfoOriginalTitleHtml, nonSuggestions, 'hrms', renderHrmsHtml, eDisplay);
            }
        }

        function buildTabbedCard(titleHtml, results, dataField, renderFn, container) {
            const extract = (r) => r[dataField];
            const getTabLabel = (r) => {
                if (dataField === 'hrms') return r.hrms?.data?.apiData?.EMP_CODE || r.username;
                return r.username;
            };
            const tabs = results.map((r, i) => '<div class="info-card-tab' + (i === 0 ? ' active' : '') + '" data-tab="' + i + '">' + htmlspecialchars(getTabLabel(r)) + '</div>').join('');
            const panes = results.map((r, i) => buildTabPaneHtml(renderFn(extract(r), r), r.username, i === 0)).join('');
            container.innerHTML = titleHtml + '<div class="info-card-tabs">' + tabs + '</div><div class="info-card-body-scroll">' + panes + '</div>';
            container.classList.add('tabbed-active');
            if (container._tabListener) container.removeEventListener('click', container._tabListener);
            container._tabListener = function(e) {
                const tab = e.target.closest('.info-card-tab');
                if (!tab) return;
                const idx = parseInt(tab.dataset.tab);
                container.querySelectorAll('.info-card-tab').forEach((t, i) => t.classList.toggle('active', i === idx));
                container.querySelectorAll('.info-card-tab-pane').forEach((p, i) => p.classList.toggle('active', i === idx));
            };
            container.addEventListener('click', container._tabListener);
        }

        // Accumulate results as they arrive — render each one immediately
        const allServerResults = [];
        const allEmployeeResults = [];

        await Promise.all(allUsers.map(async (singleUser) => {
            let result;
            try {
                const [serverData, hrmsData] = await Promise.all([
                    fetch(executeActionApiUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'username=' + encodeURIComponent(singleUser) + '&action=info&part=server_info' }).then(r => r.json()).catch(() => ({ success: false, message: 'Server info fetch failed' })),
                    fetch(executeActionApiUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'username=' + encodeURIComponent(singleUser) + '&action=info&part=hrms_info' }).then(r => r.json()).catch(() => ({ success: false, message: 'HRMS info fetch failed' }))
                ]);
                result = { username: singleUser, server: serverData, hrms: hrmsData };
            } catch (err) {
                console.error('[INFO] fetch failed for', singleUser, err);
                result = { username: singleUser, server: { success: false, message: err.message }, hrms: { success: false, message: err.message } };
            }
            allServerResults.push(result);
            allEmployeeResults.push(result);
            renderCurrentCards([...allServerResults], [...allEmployeeResults], sDisplay, eDisplay);
        }));

        // Suggestions — one at a time, add each to server card immediately
        const pendingSuggestions = [];
        for (const r of allServerResults) {
            const nearbyIds = extractNearbyIds(r);
            for (const sid of nearbyIds) {
                pendingSuggestions.push(
                    (async () => {
                        try {
                            const sd = await fetch(executeActionApiUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'username=' + encodeURIComponent(sid) + '&action=info&part=server_info' }).then(r => r.json()).catch(() => ({ success: false, message: 'Failed' }));
                            const sugResult = { username: sid, server: sd, hrms: null, isSuggestion: true, parentUser: r.username };
                            allServerResults.push(sugResult);
                            renderCurrentCards([...allServerResults], [...allEmployeeResults], sDisplay, eDisplay);
                        } catch (err) {
                            // ignore suggestion failure
                        }
                    })()
                );
            }
        }
        await Promise.all(pendingSuggestions);
    } catch (err) {
        console.error('[INFO] refreshInfoCards error:', err);
    }
}

function updateAccountStatus(newStatus) {
    const serverDisplay = document.getElementById('serverUserInfoDisplay');
    if (!serverDisplay) return;
    const labels = serverDisplay.querySelectorAll('.hrms-label');
    for (const label of labels) {
        if (label.textContent.includes('Account Status')) {
            const valueSpan = label.closest('.hrms-value');
            if (valueSpan) {
                const statusClass = newStatus.toLowerCase().includes('enable') ? 'status-active' : 'status-other';
                valueSpan.innerHTML = '<span class="hrms-label">Account Status: </span><span class="status-box ' + statusClass + '">' + newStatus + '</span>';
            }
            break;
        }
    }
}

window.handleActionButtonClick = async function(event) {
    clearReportButtons();
    const button = event.currentTarget;
    const uInput = document.getElementById('username');
    const username = uInput ? uInput.value.trim() : '';
    const action = button.value;

    if (button && typeof button.scrollIntoView === 'function') {
        button.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
    }

    if (action !== 'manualCreate' && !username) {
        console.warn('[ASSISTANT] missing username for action', { action });
        if (uInput) {
            // EFFECT: shake animation | Purpose: validation feedback for empty input
            uInput.classList.add('shake');
            uInput.focus();
            setTimeout(() => uInput.classList.remove('shake'), 820);
        }
        return;
    }

    setQuickActionButtonState(button, 'loading', 'Processing...');

    let sDisplay = document.getElementById('serverUserInfoDisplay');
    let eDisplay = document.getElementById('employeeInfoDisplay');
    if (!sDisplay && !eDisplay) {
        for (let w = 0; w < 10; w++) {
            await new Promise(r => setTimeout(r, 150));
            sDisplay = document.getElementById('serverUserInfoDisplay');
            eDisplay = document.getElementById('employeeInfoDisplay');
            if (sDisplay || eDisplay) break;
        }
    }
    if (sDisplay) showPlaceholder(sDisplay, serverInfoOriginalTitleHtml || 'Server Information');
    if (eDisplay) showPlaceholder(eDisplay, employeeInfoOriginalTitleHtml || 'Employee Information');

    const mainPromises = [];

    if (action !== 'info' && action !== 'modifyuser') {
        let container = document.getElementById('actionTakenCardContainer');
        let titleSpan = document.getElementById('actionTakenTitle');
        let msgDisplay = document.getElementById('actionTakenMessageDisplay');
        let msgDiv;
        if (!container) {
            for (let w = 0; w < 10; w++) {
                await new Promise(r => setTimeout(r, 150));
                container = document.getElementById('actionTakenCardContainer');
                if (container) break;
            }
        }
        msgDiv = container ? container.querySelector('.copy-content') : null;

        if (container) {
            container.classList.add('visible');
            if (titleSpan) titleSpan.textContent = action.charAt(0).toUpperCase() + action.slice(1);
            if (msgDisplay) {
                msgDisplay.classList.remove('alert-success', 'alert-error', 'alert-info');
                msgDisplay.classList.add('alert');
                showLoading(msgDisplay);
            }
        }

        const actionPromise = fetch(executeActionApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `username=${encodeURIComponent(username)}&action=${encodeURIComponent(action)}&part=action_result`
        })
        .then(res => res.json())
        .then(data => {
            const msg = data.message || '';
            const isSuccessState = Boolean(data.success || msg.includes('Success: 1'));
            if (container) {
                const icon = document.getElementById('actionTakenIcon');
                if (titleSpan) titleSpan.textContent = action.charAt(0).toUpperCase() + action.slice(1);
                if (msgDisplay) {
                    msgDisplay.innerHTML = styleFeedbackMessage(msg);
                    msgDisplay.classList.remove('alert-success', 'alert-error', 'alert-info');

                    if (isSuccessState) {
                        msgDisplay.classList.add('alert-success');
                        if (icon) icon.className = 'fas fa-check-circle me-2';
                        if (action === 'enableUser' || action === 'disableUser') {
                            window.refreshInfoCards(username).then(() => {
                                updateAccountStatus(action === 'enableUser' ? 'Enabled' : 'Disabled');
                            });
                        } else {
                            window.refreshInfoCards(username);
                        }
                        if (typeof fetchTodayLogChartData === 'function') fetchTodayLogChartData();
                        const logsTable = document.getElementById('detailed-logs-tbody');
                        if (logsTable) {
                            fetch(buildAdminApiUrl('log_data', { time_period: 'today' }))
                                .then(res => res.json())
                                .then(data => renderRecentActivityLogs(data.detailedLogs || []));
                        }
                    } else if (msg.includes('Failed: ') && !msg.includes('Failed: 0')) {
                        msgDisplay.classList.add('alert-error');
                        if (icon) icon.className = 'fas fa-times-circle me-2';
                    } else {
                        msgDisplay.classList.add('alert-info');
                        if (icon) icon.className = 'fas fa-info-circle me-2';
                    }
                }
                if (msgDiv) msgDiv.innerHTML = msg;
            }

            setQuickActionButtonState(button, isSuccessState ? 'success' : 'error', isSuccessState ? 'Done' : 'Retry');
            resetQuickActionButton(button, isSuccessState ? 1800 : 2200);
            emitQuickActionResult(action, username, isSuccessState, data.message || '');
            
            // Auto-hide the result card after delay
            if (typeof autoHideActionCard === 'function') autoHideActionCard();
        })
        .catch(error => {
            console.error('Action Error:', error);
            setQuickActionButtonState(button, 'error', 'Failed');
            resetQuickActionButton(button, 2200);
            displayActionTakenResult(action, `Error: ${error.message}`, false);
            emitQuickActionResult(action, username, false, `Error: ${error.message}`);
        });
        mainPromises.push(actionPromise);

    } else {
        const container = document.getElementById('actionTakenCardContainer');
        if (container) container.classList.remove('visible');
        mainPromises.push(window.refreshInfoCards(username));

        if (action === 'modifyuser' && typeof window.fetchAndPopulateUserData === 'function') {
            window.fetchAndPopulateUserData(username);
        }

        setQuickActionButtonState(button, 'info', 'Updated');
        resetQuickActionButton(button, 1400);
        emitQuickActionResult(action, username, true, `${action} workflow opened for ${username}.`);
    }

    try {
        await Promise.all(mainPromises);
    } finally {
        if (button.classList.contains('quick-action-loading')) {
            setQuickActionButtonState(button, 'default');
        }
    }
}

window.initializeActionProcessors = function() {
    // Re-find core UI elements as they might have been replaced by SPA content updates
    window.actionTakenCardContainer = document.getElementById('actionTakenCardContainer');
    window.actionTakenTitleSpan = document.getElementById('actionTakenTitle');
    window.actionTakenIcon = document.getElementById('actionTakenIcon');
    window.actionTakenMessageDisplay = document.getElementById('actionTakenMessageDisplay');

const actionButtons = document.querySelectorAll('.action-button:not([value="manualCreate"]):not(#submitManualCreate):not([value="ADmanualUserCreate"]):not(#getHrmsAdReportButton):not(#exportAdUsersButton):not(#adHealthCheckButton):not(#userReportButton):not(#submitUserReport):not(#disableAllInactive):not(#ADdirectoryBuilderButton):not(#userSecurityEventsButton)');
    actionButtons.forEach(button => {
        // Remove existing listener to avoid duplicates
        button.removeEventListener('click', window.handleActionButtonClick);
        button.addEventListener('click', window.handleActionButtonClick);
    });
};

// Initial attachment
window.initializeActionProcessors();

window.renderRecentActivityLogs = renderRecentActivityLogs;

window.initializeRecentActivityLogs = function() {
    const tbody = document.getElementById('detailed-logs-tbody');
    if (!tbody) return;

    fetch(buildAdminApiUrl('log_data', { time_period: 'today' }))
        .then(res => res.json())
        .then(data => renderRecentActivityLogs(data.detailedLogs || []))
        .catch(error => console.error('Recent Activity Fetch Error:', error));
};

// Auto-fetch and populate on initial load
window.initializeRecentActivityLogs();

function updateTodayLogTimeBadge() {
    const todayBadge = document.querySelector('#todayLogChart')?.closest('.card')?.querySelector('.time-badge');
    if (todayBadge) {
        const today = new Date();
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        todayBadge.textContent = today.toLocaleDateString(undefined, options);
    }
}

function htmlspecialchars(str) {
    if (str === null || typeof str === 'undefined') return '';
    var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(str).replace(/[&<>"']/g, m => map[m]);
}

function displayActionTakenResult(title, message, isSuccess) {
    const container = document.getElementById('actionTakenCardContainer');
    const titleSpan = document.getElementById('actionTakenTitle');
    const msgDisplay = document.getElementById('actionTakenMessageDisplay');
    const msgDiv = container ? container.querySelector('.copy-content') : null;

    if (container && titleSpan && msgDisplay) {
        container.style.display = 'block';
        container.classList.add('visible');
        if (titleSpan) titleSpan.textContent = title;
        msgDisplay.innerHTML = styleFeedbackMessage(message);
        msgDisplay.classList.remove('alert-success', 'alert-error', 'alert-info');
        msgDisplay.classList.add('alert', isSuccess ? 'alert-success' : 'alert-error');
        const icon = document.getElementById('actionTakenIcon');
        if (icon) icon.className = isSuccess ? 'fas fa-check-circle me-2' : 'fas fa-times-circle me-2';
        if (msgDiv) msgDiv.innerHTML = message;
        
        // Auto-hide the result card after delay
        if (typeof autoHideActionCard === 'function') autoHideActionCard();
    }
}
