// assets/dashboard/js/dash_pro_scripts.js

window.initializeDashboard = function() {
    const dashboardRoot = document.querySelector('.dashboard-content');
    if (!dashboardRoot) return;
    if (window._dashboardInitializedRoot === dashboardRoot) return;
    window._dashboardInitialized = true;
    window._dashboardInitializedRoot = dashboardRoot;
    
    const apiBaseURL = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (typeof baseURL === 'string' ? baseURL : '');
    const DASHBOARD_REFRESH_INTERVAL_MS = 5000;
    const MAX_LOG_ROWS = 100;

    if (typeof Chart !== 'undefined' && typeof ChartDataLabels !== 'undefined') {
        if (!Chart.registry.plugins.get('datalabels')) {
            Chart.register(ChartDataLabels);
        }
    }
    if (typeof Chart !== 'undefined') {
        Chart.defaults.animation.duration = 1000;
    }
    let hasAnimatedPrimaryCharts = false;
    let hasAnimatedFilteredCharts = false;

    let allLogs = []; 
    let filteredLogs = []; 
    
    const tbody = document.getElementById('dashboard-detailed-logs-tbody');
    const noLogsMessage = document.getElementById('dashboard-no-logs-message');
    
    const searchInput = document.getElementById('search');
    const timePeriodSelect = document.getElementById('time_period');
    const categorySelect = document.getElementById('category');
    const statusSelect = document.getElementById('status');
    const domainSelect = document.getElementById('domainFilter');
    const exportBtn = document.getElementById('export-logs-btn');
    const domainBadge = document.getElementById('dashboard-domain-badge');
    const applyBtn = document.getElementById('apply-filters-btn');
    const resetBtn = document.getElementById('reset-filters-btn');
    
    const customDateInputs = document.getElementById('custom-date-inputs');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');

    if (timePeriodSelect && !timePeriodSelect.dataset.defaultApplied) {
        timePeriodSelect.value = 'week';
        timePeriodSelect.dataset.defaultApplied = 'true';
    }

    const todayBadge = document.querySelector('#dashboardTodayLogChart')?.closest('.card')?.querySelector('.time-badge');
    const weeklyBadge = document.querySelector('#weeklyLogsChart')?.closest('.card')?.querySelector('.time-badge');
    const monthlyBadge = document.querySelector('#filteredChart')?.closest('.card')?.querySelector('.time-badge');

    function updateBadges() {
        const now = new Date();
        const dateStr = now.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
        
        // 1. Today's Date (Update ALL badges for all Today's Log charts)
        const todayBadges = document.querySelectorAll('#dashboardTodayLogChart, [id^="dashboardTodayLogChart_"]');
        todayBadges.forEach(canvas => {
            const badge = canvas.closest('.card')?.querySelector('.time-badge');
            if (badge) badge.textContent = dateStr;
        });

        // 2. Week Number
        if (weeklyBadge) {
            const startOfYear = new Date(now.getFullYear(), 0, 1);
            const pastDaysOfYear = (now - startOfYear) / 86400000;
            const weekNum = Math.ceil((pastDaysOfYear + startOfYear.getDay() + 1) / 7);
            weeklyBadge.textContent = `Week ${weekNum}`;
        }

        // 3. Month Name & Number
        if (monthlyBadge) {
            const monthName = now.toLocaleDateString('en-US', { month: 'long' });
            const monthNum = now.getMonth() + 1;
            monthlyBadge.textContent = `${monthName} (M${monthNum})`;
        }
    }

    function initializeEmptyDashboardCharts() {
        if (typeof renderTodayLogChart === 'function') {
            const todayCanvases = document.querySelectorAll('#dashboardTodayLogChart, [id^="dashboardTodayLogChart_"]');
            todayCanvases.forEach((canvas, idx) => {
                if (!canvas.id || canvas.id === 'dashboardTodayLogChart') {
                    if (todayCanvases.length > 1 && !canvas.dataset.chartCanvasId) {
                        canvas.dataset.chartCanvasId = `dashboardTodayLogChart_${idx}`;
                        canvas.id = canvas.dataset.chartCanvasId;
                    }
                }

                const canvasId = canvas.dataset.chartCanvasId || canvas.id || 'dashboardTodayLogChart';
                if (canvasId) {
                    renderTodayLogChart([], [], canvasId, null, true);
                }
            });
        }

        if (typeof renderWeeklyLogsChart === 'function' && document.getElementById('weeklyLogsChart')) {
            renderWeeklyLogsChart('weeklyLogsChart', {}, [], true);
        }

        if (typeof renderMonthlyChart === 'function' && document.getElementById('filteredChart')) {
            renderMonthlyChart([], [], 'filteredChart');
        }
    }

    function normalizeLegendStatus(status) {
        const normalized = String(status || '').toUpperCase();
        if (normalized.includes('NOT FOUND')) return 'NOT FOUND';
        if (normalized.includes('EXISTS')) return 'SKIPPED';
        if (normalized.includes('TRIGGERED')) return 'TRIGGERED';
        if (normalized.includes('FAIL')) return 'FAILED';
        if (normalized.includes('WARN')) return 'WARNING';
        return normalized || 'UNKNOWN';
    }

    function getStatusLegendColor(statusLabel) {
        const colorMap = {
            'SUCCESS': '#249b2e',
            'FAILED': '#c02a43',
            'SKIPPED': '#ac3fac',
            'TRIGGERED': '#e98503',
            'NOT FOUND': '#ff3300',
            'WARNING': '#1dc4ab',
            'UNKNOWN': '#6c757d'
        };
        return colorMap[String(statusLabel).toUpperCase()] || '#6c757d';
    }

    function updateDashboardCornerLegend(canvasId, labels, dataValues) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        const legendList = canvas.closest('.card')?.querySelector('.dashboard-chart-legend-list');
        if (!legendList) return;

        legendList.innerHTML = '';

        labels.forEach((label, index) => {
            const color = getStatusLegendColor(label);
            const value = dataValues[index] ?? 0;
            const item = document.createElement('li');
            const displayLabel = String(label)
                .toLowerCase()
                .replace(/\b\w/g, (char) => char.toUpperCase());
            item.innerHTML = `
                <span style="background-color: ${color}; width: 10px; height: 10px; display: inline-block; border-radius: 50%; margin-right: 6px;"></span>
                ${displayLabel}: ${value}
            `;
            legendList.appendChild(item);
        });
    }

    function toTitleCase(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/[_-]+/g, ' ')
            .replace(/\b\w/g, (char) => char.toUpperCase());
    }

    function updateFilterOptions(selectElement, values, defaultLabel, formatter) {
        if (!selectElement) return;
        const currentValue = selectElement.value;
        selectElement.innerHTML = '';

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = defaultLabel;
        selectElement.appendChild(defaultOption);

        (values || []).forEach((value) => {
            if (!value) return;
            const option = document.createElement('option');
            option.value = value;
            option.textContent = formatter ? formatter(value) : value;
            selectElement.appendChild(option);
        });

        if ([...selectElement.options].some((option) => option.value === currentValue)) {
            selectElement.value = currentValue;
        }
    }

    function _badgeColor(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        const hue = Math.abs(hash) % 360;
        return { bg: `hsl(${hue}, 55%, 88%)`, fg: `hsl(${hue}, 55%, 25%)` };
    }

    let _logsHash = '';
    window.updateDetailedLogsTable = function(logs) {
        if (!tbody) return;
        if (logs.length > MAX_LOG_ROWS) logs = logs.slice(0, MAX_LOG_ROWS);
        const newHash = logs.map(l => l.timestamp + l.action + l.status).join('|');
        if (newHash === _logsHash) return;
        _logsHash = newHash;
        const hadRows = tbody.children.length > 0;
        const logTableWrapper = tbody.closest('.log-table-wrapper');
        const previousScrollTop = logTableWrapper ? logTableWrapper.scrollTop : 0;
        const previousScrollHeight = logTableWrapper ? logTableWrapper.scrollHeight : 0;
        tbody.innerHTML = '';

        const percentageBadge = document.getElementById('dashboard-log-success-percentage');

        if (logs.length === 0) {
            if (noLogsMessage) noLogsMessage.style.display = 'block';
            if (percentageBadge) {
                percentageBadge.textContent = 'N/A';
                percentageBadge.style.backgroundColor = '#6c757d';
            }
            if (typeof window.syncLogTableHeight === 'function') {
                window.requestAnimationFrame(() => window.syncLogTableHeight());
            }
            if (logTableWrapper) {
                window.requestAnimationFrame(() => {
                    logTableWrapper.scrollTop = previousScrollTop;
                });
            }
            return;
        }
        if (noLogsMessage) noLogsMessage.style.display = 'none';

        let successCount = 0;
        logs.forEach(log => {
            const status = (log.status || '').toUpperCase();
            if (status === 'SUCCESS') successCount++;

            let ts = String(log.timestamp || '');
            // Fix for missing space between date and time
            if (ts.length >= 10 && ts[10] !== ' ') {
                ts = ts.slice(0, 10) + ' ' + ts.slice(10);
            }
            const timestampParts = ts.split(' ');
            const timestampDate = timestampParts.slice(0, 1).join(' ');
            const timestampTime = timestampParts.slice(1).join(' ');

            const row = document.createElement('tr');
            const statusClass = status.toLowerCase().replace(/[^a-zA-Z]/g, '_');
            const actionClass = (log.action || '').toLowerCase().replace(/[^a-zA-Z0-9]/g, '_');
            
            const dc = _badgeColor(log.domain || 'N/A');
            const cc = _badgeColor(log.category || 'other');
            row.innerHTML = `
                <td class="log-timestamp-cell">
                    <span class="log-timestamp-date">${timestampDate}</span>
                    <span class="log-timestamp-time">${timestampTime}</span>
                </td>
                <td><span class="domain-badge" style="background:${dc.bg};color:${dc.fg}">${log.domain || 'N/A'}</span></td>
                <td><span class="action-badge action-${actionClass}">${log.action}</span></td>
                <td>${log.targetUser}</td>
                <td><span class="category-badge" style="background:${cc.bg};color:${cc.fg}">${log.category}</span></td>
                <td>${log.performedBy}</td>
                <td>${log.message || ''}</td>
                <td><span class="status-badge status-${statusClass}">${log.status}</span></td>
            `;
            tbody.appendChild(row);
        });

        if (typeof window.syncLogTableHeight === 'function') {
            window.requestAnimationFrame(() => window.syncLogTableHeight());
        }

        if (logTableWrapper) {
            window.requestAnimationFrame(() => {
                logTableWrapper.scrollTop = (hadRows && previousScrollTop > 0) ? previousScrollTop : 0;
            });
        }

        if (percentageBadge) {
            const total = logs.length;
            const percentage = Math.round((successCount / total) * 100);
            percentageBadge.textContent = `${percentage}%`;

            // Dynamic Coloring
            if (percentage >= 80) percentageBadge.style.backgroundColor = '#28a745'; // Green
            else if (percentage >= 50) percentageBadge.style.backgroundColor = '#ffc107'; // Yellow
            else percentageBadge.style.backgroundColor = '#dc3545'; // Red
        }
    };

    function renderOverallCharts(logs) {
        if (typeof renderTodayLogChart !== 'function') return;
        const shouldAnimatePrimaryCharts = !hasAnimatedPrimaryCharts;
        const now = new Date();
        const todayDate = now.toISOString().slice(0, 10);
        const currentMonthPrefix = now.toISOString().slice(0, 7);
        const getLastNDays = (n) => {
            const dates = [];
            for (let i = n - 1; i >= 0; i--) {
                const d = new Date();
                d.setDate(d.getDate() - i);
                dates.push(d.toISOString().slice(0, 10));
            }
            return dates;
        };
        const last7Days = getLastNDays(7);

        const tActions = {};
        const todayLogs = logs.filter(l => l.timestamp.startsWith(todayDate));
        todayLogs.forEach(l => {
            const a = l.action.toUpperCase(); tActions[a] = (tActions[a] || 0) + 1;
        });
        const todayStatusCounts = {};
        todayLogs.forEach((log) => {
            const statusKey = normalizeLegendStatus(log.status);
            todayStatusCounts[statusKey] = (todayStatusCounts[statusKey] || 0) + 1;
        });
        
        // --- NEW: Render ALL Today's Log charts (Sidebar and Dashboard) ---
        const labels = Object.keys(tActions);
        const dataValues = Object.values(tActions);
        
        const canvases = document.querySelectorAll('#dashboardTodayLogChart, [id^="dashboardTodayLogChart_"]');
        canvases.forEach((canvas) => {
            const canvasId = canvas.dataset.chartCanvasId || canvas.id || 'dashboardTodayLogChart';
            if (canvasId) {
                renderTodayLogChart(labels, dataValues, canvasId, null, shouldAnimatePrimaryCharts);
                updateDashboardCornerLegend(canvasId, Object.keys(todayStatusCounts), Object.values(todayStatusCounts));
            }
        });

        const wData = {}; const aNames = new Set();
        last7Days.forEach(d => {
            wData[d] = {};
            logs.filter(l => l.timestamp.startsWith(d)).forEach(l => {
                const a = l.action.toUpperCase(); aNames.add(a);
                wData[d][a] = (wData[d][a] || 0) + 1;
            });
        });
        if (typeof renderWeeklyLogsChart === 'function') renderWeeklyLogsChart('weeklyLogsChart', wData, Array.from(aNames), shouldAnimatePrimaryCharts);

        const mActions = {};
        const monthlyLogs = logs.filter(l => l.timestamp.startsWith(currentMonthPrefix));
        monthlyLogs.forEach(l => {
            const a = l.action.toUpperCase(); mActions[a] = (mActions[a] || 0) + 1;
        });
        const monthlyStatusCounts = {};
        monthlyLogs.forEach((log) => {
            const statusKey = normalizeLegendStatus(log.status);
            monthlyStatusCounts[statusKey] = (monthlyStatusCounts[statusKey] || 0) + 1;
        });
        const monthlyLabels = Object.keys(mActions);
        const monthlyData = Object.values(mActions);
        if (typeof renderMonthlyChart === 'function') {
            renderMonthlyChart(monthlyLabels, monthlyData, 'filteredChart', null, shouldAnimatePrimaryCharts);
            updateDashboardCornerLegend('filteredChart', Object.keys(monthlyStatusCounts), Object.values(monthlyStatusCounts));
        }

        hasAnimatedPrimaryCharts = true;
    }

    function renderFilteredCharts(logs) {
        const shouldAnimateFilteredCharts = !hasAnimatedFilteredCharts;
        const now = new Date();
        const todayDate = now.toISOString().slice(0, 10);
        const currentMonthPrefix = now.toISOString().slice(0, 7);
        const getLastNDays = (n) => {
            const dates = [];
            for (let i = n - 1; i >= 0; i--) {
                const d = new Date();
                d.setDate(d.getDate() - i);
                dates.push(d.toISOString().slice(0, 10));
            }
            return dates;
        };
        const last7Days = getLastNDays(7);

        // Today's Log chart
        const tActions = {};
        const todayLogs = logs.filter(l => l.timestamp.startsWith(todayDate));
        todayLogs.forEach(l => { const a = l.action.toUpperCase(); tActions[a] = (tActions[a] || 0) + 1; });
        const todayStatusCounts = {};
        todayLogs.forEach(log => { const s = normalizeLegendStatus(log.status); todayStatusCounts[s] = (todayStatusCounts[s] || 0) + 1; });
        const todayLabels = Object.keys(tActions);
        const todayData = Object.values(tActions);
        const todayCanvases = document.querySelectorAll('#dashboardTodayLogChart, [id^="dashboardTodayLogChart_"]');
        todayCanvases.forEach(canvas => {
            const cid = canvas.dataset.chartCanvasId || canvas.id || 'dashboardTodayLogChart';
            if (cid && typeof renderTodayLogChart === 'function') {
                renderTodayLogChart(todayLabels, todayData, cid, null, shouldAnimateFilteredCharts);
                if (typeof updateDashboardCornerLegend === 'function') updateDashboardCornerLegend(cid, Object.keys(todayStatusCounts), Object.values(todayStatusCounts));
            }
        });

        // Weekly chart
        const wData = {}; const aNames = new Set();
        last7Days.forEach(d => { wData[d] = {}; logs.filter(l => l.timestamp.startsWith(d)).forEach(l => { const a = l.action.toUpperCase(); aNames.add(a); wData[d][a] = (wData[d][a] || 0) + 1; }); });
        if (typeof renderWeeklyLogsChart === 'function') renderWeeklyLogsChart('weeklyLogsChart', wData, Array.from(aNames), shouldAnimateFilteredCharts);

        // Monthly chart
        const mActions = {};
        const monthlyLogs = logs.filter(l => l.timestamp.startsWith(currentMonthPrefix));
        monthlyLogs.forEach(l => { const a = l.action.toUpperCase(); mActions[a] = (mActions[a] || 0) + 1; });
        const monthlyStatusCounts = {};
        monthlyLogs.forEach(log => { const s = normalizeLegendStatus(log.status); monthlyStatusCounts[s] = (monthlyStatusCounts[s] || 0) + 1; });
        const monthlyLabels = Object.keys(mActions);
        const monthlyData = Object.values(mActions);
        if (typeof renderMonthlyChart === 'function') {
            renderMonthlyChart(monthlyLabels, monthlyData, 'filteredChart', null, shouldAnimateFilteredCharts);
            if (typeof updateDashboardCornerLegend === 'function') updateDashboardCornerLegend('filteredChart', Object.keys(monthlyStatusCounts), Object.values(monthlyStatusCounts));
        }

        const sHist = {}; const sTypes = ['SUCCESS', 'FAILED', 'SKIPPED', 'WARNING', 'NOT FOUND', 'TRIGGERED'];
        let successCount = 0;
        const totalCount = logs.length;

        last7Days.forEach(d => {
            sHist[d] = {}; sTypes.forEach(s => sHist[d][s] = 0);
            logs.filter(l => l.timestamp.startsWith(d)).forEach(l => {
                const st = l.status.toUpperCase(); 
                if (sTypes.includes(st)) sHist[d][st]++;
                if (st === 'SUCCESS') successCount++;
            });
        });
        if (typeof renderStatusBreakdownChart === 'function') renderStatusBreakdownChart('historicalStatusChart', sHist, logs, 'weekly', shouldAnimateFilteredCharts);
        
        // Update Status Ratio Badge
        const statusBadge = document.querySelector('#historicalStatusChart')?.closest('.card')?.querySelector('.time-badge');
        if (statusBadge && totalCount > 0) {
            const ratio = Math.round((successCount / totalCount) * 100);
            statusBadge.textContent = `Success: ${ratio}%`;
        } else if (statusBadge) {
            statusBadge.textContent = 'No Data';
        }

        const asData = {};
        const actionCounts = {};
        logs.forEach(l => {
            const a = l.action.toUpperCase(); const st = l.status.toUpperCase();
            if (!asData[a]) asData[a] = {}; asData[a][st] = (asData[a][st] || 0) + 1;
            actionCounts[a] = (actionCounts[a] || 0) + 1;
        });
        if (typeof renderActionStatusBreakdownChart === 'function') renderActionStatusBreakdownChart('actionStatusBreakdownChart', asData, shouldAnimateFilteredCharts);

        // Update Max Action Ratio Badge
        const actionBadge = document.querySelector('#actionStatusBreakdownChart')?.closest('.card')?.querySelector('.time-badge');
        if (actionBadge && totalCount > 0 && Object.keys(actionCounts).length > 0) {
            const maxAction = Object.entries(actionCounts).reduce((a, b) => a[1] > b[1] ? a : b);
            const ratio = Math.round((maxAction[1] / totalCount) * 100);
            actionBadge.textContent = `${maxAction[0]} (${ratio}%)`;
        } else if (actionBadge) {
            actionBadge.textContent = 'No Data';
        }

        const ops = {};
        logs.forEach(l => { 
            const o = l.performedBy || 'Unknown'; 
            if (o !== 'N/A' && o !== 'SYSTEM') { ops[o] = (ops[o] || 0) + 1; }
        });
        const sorted = Object.entries(ops).sort((a,b) => b[1] - a[1]).slice(0, 10);
        
        const legendWrapper = document.getElementById('active-users-legend');
        if (legendWrapper) {
            legendWrapper.innerHTML = '';
            sorted.forEach(([o, c], index) => {
                const operatorDiv = document.createElement('div');
                operatorDiv.className = 'operator-rank-entry mb-1 p-1 px-2 rounded';
                operatorDiv.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><i class="fas fa-user-cog me-1 text-primary"></i>${o}</span>
                        <span class="text-primary fw-bold dashboard-rank-badge">Rank ${index + 1}</span>
                    </div>
                    <div class="text-muted dashboard-entry-meta">
                        <i class="fas fa-history me-1"></i>Total: <span class="fw-bold text-dark">${c}</span> actions
                    </div>`;
                legendWrapper.appendChild(operatorDiv);
            });
        }

        hasAnimatedFilteredCharts = true;
    }

    function syncInfoCardsVisibility() {
        const serverInfoSec = document.getElementById('serverUserInfoDisplay');
        const employeeInfoSec = document.getElementById('employeeInfoDisplay');
        
        // Only hide if we are currently on the dashboard page
        const urlParams = new URLSearchParams(window.location.search);
        const currentPage = urlParams.get('page');
        
        if (currentPage === 'dashboard') {
            if (serverInfoSec) serverInfoSec.style.display = 'none';
            if (employeeInfoSec) employeeInfoSec.style.display = 'none';
        }
    }

    async function fetchActiveUsers() {
        const activeUsersWrapper = document.querySelector('.user-activity-list');
        if (!activeUsersWrapper) return;
        try {
            const response = await fetch(`${apiBaseURL}/audit.php`);
            const data = await response.json();
            const users = data.online_users_list || [];
            activeUsersWrapper.innerHTML = '';
            if (users.length === 0) {
                activeUsersWrapper.innerHTML = '<div class="text-center p-2 text-muted" style="font-size: 0.75rem;">No active sessions.</div>';
                return;
            }
            users.forEach(user => {
                const userDiv = document.createElement('div');
                userDiv.className = 'active-user-entry mb-1 p-1 px-2 rounded';
                userDiv.dataset.sessionTime = user.current_session_time;
                userDiv.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><i class="fas fa-user-circle me-1 text-success"></i>${user.username}</span>
                        <span class="text-success fw-bold dashboard-online-badge">Online</span>
                    </div>
                    <div class="text-muted dashboard-entry-meta">
                        <span title="IP Address"><i class="fas fa-network-wired me-1"></i>${user.ip}</span> | 
                        <span title="Session Duration"><i class="fas fa-stopwatch me-1"></i><span class="session-timer">${formatSeconds(user.current_session_time)}</span></span>
                    </div>`;
                activeUsersWrapper.appendChild(userDiv);
            });
        } catch (error) { console.error('Error fetching active users:', error); }
    }

    function formatSeconds(seconds) {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return `${m}m ${s}s`;
    }

    let domainFilterValue = '';

    function updateDomainUI(availableDomains, activeDomain, currentFilter) {
        // Ensure availableDomains is an array
        if (!availableDomains || !Array.isArray(availableDomains)) {
            availableDomains = [];
        }
        var activeDomainStr = activeDomain || '';
        var filterStr = currentFilter || '';

        if (domainSelect) {
            var curVal = domainSelect.value;
            domainSelect.innerHTML = '<option value="">Current Domain (' + activeDomainStr + ')</option><option value="all">All Domains</option>';
            if (availableDomains.length) {
                availableDomains.forEach(function(d) {
                    if (!d || typeof d !== 'object') return;
                    if (d.key === activeDomainStr) return; // skip active, already shown as first option
                    var opt = document.createElement('option');
                    opt.value = d.key || '';
                    var displayName = (d.ad_name || d.key || '');
                    opt.textContent = displayName;
                    domainSelect.appendChild(opt);
                });
            }
            if (curVal) domainSelect.value = curVal;
        }
        if (domainBadge) {
            var displayText = '';
            if (filterStr === 'all') {
                displayText = 'All Domains';
                domainBadge.style.backgroundColor = '#6366f1';
            } else if (filterStr && filterStr !== '' && filterStr !== activeDomainStr) {
                // Specific domain selected
                if (Array.isArray(availableDomains)) {
                    var found = availableDomains.find(function(d) { return d && d.key === filterStr; });
                    displayText = found ? (found.ad_name || found.key || filterStr) : filterStr;
                } else {
                    displayText = filterStr;
                }
                domainBadge.style.backgroundColor = '#475569';
            } else {
                // Active domain
                if (Array.isArray(availableDomains)) {
                    var found = availableDomains.find(function(d) { return d && d.key === activeDomainStr; });
                    displayText = found ? (found.ad_name || found.key || activeDomainStr) : (activeDomainStr || '');
                } else {
                    displayText = activeDomainStr || '';
                }
                domainBadge.style.backgroundColor = '#475569';
            }
            domainBadge.textContent = displayText;
        }
    }

    async function fetchDashboardData() {
        var domainParam = domainFilterValue || '';
        var url = apiBaseURL + '/api/index.php?endpoint=log_data&time_period=all';
        if (domainParam) url += '&domain=' + encodeURIComponent(domainParam);
        try {
            const response = await fetch(url);
            const data = await response.json();
            if (!response.ok || data.success === false) {
                throw new Error(data.message || 'Failed to load dashboard data.');
            }
            allLogs = data.allLogs || [];
            updateFilterOptions(categorySelect, data.available_categories, 'All Categories', toTitleCase);
            updateFilterOptions(statusSelect, data.available_statuses, 'All Statuses', toTitleCase);
            updateDomainUI(data.available_domains, data.activeDomain, domainFilterValue);
            updateBadges();
            renderOverallCharts(allLogs);
            applyFilters(); 
            fetchActiveUsers();
        } catch (error) { console.error('Dashboard Fetch Error:', error); }
    }

    function applyFilters() {
        var newDomain = domainSelect ? domainSelect.value : '';
        if (newDomain !== domainFilterValue) {
            domainFilterValue = newDomain;
            fetchDashboardData();
            return;
        }

        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const selectedCategory = categorySelect ? categorySelect.value : '';
        const selectedStatus = statusSelect ? statusSelect.value.toUpperCase() : '';
        const selectedPeriod = timePeriodSelect ? timePeriodSelect.value : 'all';

        let now = new Date();
        let cutoff = 0;
        let endCutoff = 0;

        if (selectedPeriod === 'today') cutoff = new Date().setHours(0,0,0,0);
        else if (selectedPeriod === '72hours') cutoff = now - (72 * 60 * 60 * 1000);
        else if (selectedPeriod === 'week') cutoff = now - (7 * 24 * 60 * 60 * 1000);
        else if (selectedPeriod === 'month') cutoff = now - (30 * 24 * 60 * 60 * 1000);
        else if (selectedPeriod === 'custom') {
            if (startDateInput && startDateInput.value) cutoff = new Date(startDateInput.value + ' 00:00:00').getTime();
            if (endDateInput && endDateInput.value) endCutoff = new Date(endDateInput.value + ' 23:59:59').getTime();
        }

        filteredLogs = allLogs.filter(l => {
            const logTime = new Date(l.timestamp).getTime();
            if (cutoff > 0 && logTime < cutoff) return false;
            if (endCutoff > 0 && logTime > endCutoff) return false;

            const matchesSearch = !searchTerm || l.action.toLowerCase().includes(searchTerm) || l.performedBy.toLowerCase().includes(searchTerm) || l.targetUser.toLowerCase().includes(searchTerm) || (l.message && l.message.toLowerCase().includes(searchTerm));
            const matchesCategory = !selectedCategory || l.category === selectedCategory;
            let nS = (l.status || '').toUpperCase();
            if (nS.includes('NOT FOUND')) nS = 'NOT FOUND'; else if (nS.includes('EXISTS')) nS = 'SKIPPED'; else if (nS.includes('TRIGGERED')) nS = 'TRIGGERED';
            const matchesStatus = !selectedStatus || nS === selectedStatus;
            return matchesSearch && matchesCategory && matchesStatus;
        });

        window.updateDetailedLogsTable(filteredLogs);
        renderFilteredCharts(filteredLogs);
    }

    if (timePeriodSelect) {
        timePeriodSelect.onchange = () => {
            if (timePeriodSelect.value === 'custom') {
                if (customDateInputs) customDateInputs.style.display = 'block';
            } else {
                if (customDateInputs) customDateInputs.style.display = 'none';
                applyFilters();
            }
        };
    }
    if (applyBtn) applyBtn.onclick = (e) => { e.preventDefault(); applyFilters(); };
    if (resetBtn) {
        resetBtn.onclick = (e) => {
            e.preventDefault();
            if (searchInput) searchInput.value = '';
            if (categorySelect) categorySelect.value = '';
            if (statusSelect) statusSelect.value = '';
            if (timePeriodSelect) timePeriodSelect.value = 'week';
            if (customDateInputs) customDateInputs.style.display = 'none';
            if (startDateInput) startDateInput.value = '';
            if (endDateInput) endDateInput.value = '';
            applyFilters();
        };
    }
    if (searchInput) searchInput.onkeyup = (e) => { if (e.key === 'Enter') applyFilters(); };

    // Domain filter change triggers re-fetch
    if (domainSelect) domainSelect.onchange = applyFilters;

    // Export button
    if (exportBtn) {
        exportBtn.onclick = function() {
            var params = new URLSearchParams({ endpoint: 'log_data', time_period: 'all', export: '1' });
            if (domainFilterValue) params.set('domain', domainFilterValue);
            var exportUrl = apiBaseURL + '/api/index.php?' + params.toString();
            window.open(exportUrl, '_blank');
        };
    }

    initializeEmptyDashboardCharts();

    function runInitialDashboardLoad() {
        // Delay the first render slightly so Chart.js animates after the loader overlay is gone.
        window.setTimeout(fetchDashboardData, 250);
    }

    if (document.readyState === 'complete') {
        runInitialDashboardLoad();
    } else {
        window.addEventListener('load', runInitialDashboardLoad, { once: true });
    }
    syncInfoCardsVisibility();
    
    if (window._activeUserRefresher) clearInterval(window._activeUserRefresher);
    window._activeUserRefresher = setInterval(fetchActiveUsers, 10000);

    if (window._dashboardDataRefresher) clearInterval(window._dashboardDataRefresher);
    window._dashboardDataRefresher = setInterval(fetchDashboardData, DASHBOARD_REFRESH_INTERVAL_MS);

    if (window._sessionTimerIncrementer) clearInterval(window._sessionTimerIncrementer);
    window._sessionTimerIncrementer = setInterval(() => {
        document.querySelectorAll('.active-user-entry').forEach(entry => {
            let time = parseInt(entry.dataset.sessionTime) + 1;
            entry.dataset.sessionTime = time;
            const timerSpan = entry.querySelector('.session-timer');
            if (timerSpan) timerSpan.textContent = formatSeconds(time);
        });
    }, 1000);
};

(function autoInit() {
    if (document.getElementById('dashboard-detailed-logs-tbody')) {
        window.initializeDashboard();
    } else {
        window._initAttempts = (window._initAttempts || 0) + 1;
        if (window._initAttempts < 10) { setTimeout(autoInit, 100); }
    }
})();
