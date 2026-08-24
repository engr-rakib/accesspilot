(function() {
    'use strict';

    /**
     * @file dashboard_logic.js
     * @brief Core JavaScript logic for the dynamic User Activity Dashboard.
     */
    window.initDashboardLogic = function() {

        if (typeof Chart !== 'undefined' && typeof ChartDataLabels !== 'undefined') {
            if (!Chart.registry.plugins.get('datalabels')) {
                Chart.register(ChartDataLabels);
            }
        }

        const appConfig = window.APP_CONFIG || {};
        const resolvedBaseUrl = appConfig.baseUrl || (typeof baseURL === 'string' ? baseURL : window.location.origin);
        
        const chartInstances = window.chartInstances || {};

        const dashboardContainer = document.getElementById('dashboard-container');
        if (!dashboardContainer) return;
        if (dashboardContainer.dataset.initialized === 'true') return;
        dashboardContainer.dataset.initialized = 'true';

    const activityHourCanvas = document.getElementById('activityHourChart');
    const activityHourCard = document.getElementById('activityHourCard');
    const activityHourChartBody = document.getElementById('activityHourChartBody');
    const eventOverviewCard = document.getElementById('eventOverviewCard');
    let activityHourChart = null;
    if (activityHourCanvas && typeof createActivityHourChart === 'function') {
        activityHourChart = createActivityHourChart('activityHourChart');
    }
    let currentRange = 'week';
    let activityHourResizeObserver = null;

    // --- DOM Element References ---
    const statElements = {
        onlineUsers: document.getElementById('stat-online-users'),
        enabledUsers: document.getElementById('stat-enabled-users'),
        logins: document.getElementById('stat-logins'),
        failures: document.getElementById('stat-failures'),
    };
    const onlineUsersTableBody = document.getElementById('onlineUsersTableBody');
    const auditLogTableBody = document.getElementById('auditLogTableBody');
    const guestFailureCountEl = document.getElementById('guest-failure-count');
    const guestFailureCanvas = document.getElementById('guestFailureChart');
    const guestRangeButtons = document.querySelectorAll('#guestRangeButtons [data-guest-range]');
    const guestStartDate = document.getElementById('guestStartDate');
    const guestEndDate = document.getElementById('guestEndDate');
    const guestCustomApplyBtn = document.getElementById('guestCustomApplyBtn');
    const guestDownloadReportBtn = document.getElementById('guestDownloadReportBtn');
    let guestFailureChart = null;
    let guestChartRange = 'today';
    let lastGuestChartData = null;
    if (guestFailureCanvas && typeof renderGuestFailureChart === 'function') {
        guestFailureChart = renderGuestFailureChart(guestFailureCanvas.getContext('2d'), [], {}, [], true);
    }
    const loadingSpinner = document.getElementById('loading-spinner');
    const eventLogSearchInput = document.getElementById('eventLogSearchInput');
    const eventTimePeriod = document.getElementById('eventTimePeriod');
    const eventActionFilter = document.getElementById('eventActionFilter');
    const eventStatusFilter = document.getElementById('eventStatusFilter');
    const applyEventFiltersBtn = document.getElementById('applyEventFiltersBtn');
    const resetEventFiltersBtn = document.getElementById('resetEventFiltersBtn');
    const eventCustomDateInputs = document.getElementById('eventCustomDateInputs');
    const eventStartDate = document.getElementById('eventStartDate');
    const eventEndDate = document.getElementById('eventEndDate');
    const eventLogCount = document.getElementById('event-log-count');
    const eventNoLogsMessage = document.getElementById('event-no-logs-message');

    let allLogs = []; // Store logs for tooltip context
    let isInitialLoad = true;
    let userColorMap = {}; // To store user colors
    const colorPalette = [
        '#E6194B', '#3CB44B', '#FFE119', '#4363D8', '#F58231', '#911EB4', '#46F0F0', '#F032E6', '#BCF60C', '#FABEBE',
        '#008080', '#E6BEFF', '#9A6324', '#FFFAC8', '#800000', '#AAFFC3', '#808000', '#FFD8B1', '#000075', '#808080',
        '#FFFFFF', '#000000', '#A9A9A9', '#D3D3D3', '#8B0000', '#FF8C00', '#FFD700', '#ADFF2F', '#00FF7F', '#00CED1',
        '#4682B4', '#6A5ACD', '#8A2BE2', '#9932CC', '#BA55D3', '#DA70D6', '#EE82EE', '#FF00FF', '#800080', '#4B0082',
        '#6A5ACD', '#7B68EE', '#9370DB', '#DDA0DD', '#EE82EE', '#FF00FF', '#FF69B4', '#FF1493', '#C71585', '#DB7093',
        '#E6194B', '#3CB44B', '#FFE119', '#4363D8', '#F58231', '#911EB4', '#46F0F0', '#F032E6', '#BCF60C', '#FABEBE',
        '#008080', '#E6BEFF', '#9A6324', '#FFFAC8', '#800000', '#AAFFC3', '#808000', '#FFD8B1', '#000075', '#808080',
        '#A9A9A9', '#D3D3D3', '#8B0000', '#FF8C00', '#FFD700', '#ADFF2F', '#00FF7F', '#00CED1', '#4682B4', '#6A5ACD',
        '#8A2BE2', '#9932CC', '#BA55D3', '#DA70D6', '#EE82EE', '#FF00FF', '#800080', '#4B0082', '#6A5ACD', '#7B68EE',
        '#9370DB', '#DDA0DD', '#EE82EE', '#FF00FF', '#FF69B4', '#FF1493', '#C71585', '#DB7093', '#E6194B', '#3CB44B'
    ];

    function simpleHash(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = (hash << 5) - hash + char;
            hash |= 0;
        }
        return Math.abs(hash);
    }

    function hexToRgba(hex, alpha) {
        alpha = (alpha === undefined) ? 1 : alpha;
        if (!/^#([A-Fa-f0-9]{3}){1,2}$/.test(hex)) {
            return hex;
        }
        let r, g, b;
        if (hex.length === 4) {
            r = parseInt(hex[1] + hex[1], 16);
            g = parseInt(hex[2] + hex[2], 16);
            b = parseInt(hex[3] + hex[3], 16);
        } else {
            r = parseInt(hex.slice(1, 3), 16);
            g = parseInt(hex.slice(3, 5), 16);
            b = parseInt(hex.slice(5, 7), 16);
        }
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    function toTitleCase(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/[_-]+/g, ' ')
            .replace(/\b\w/g, (char) => char.toUpperCase());
    }

    function fillSelectOptions(select, values, defaultLabel) {
        if (!select) return;
        const currentValue = select.value;
        select.innerHTML = '';
        const first = document.createElement('option');
        first.value = '';
        first.textContent = defaultLabel;
        select.appendChild(first);

        (values || []).forEach((value) => {
            if (!value) return;
            const option = document.createElement('option');
            option.value = value;
            option.textContent = toTitleCase(value);
            select.appendChild(option);
        });

        if ([...select.options].some((option) => option.value === currentValue)) {
            select.value = currentValue;
        }
    }

    function getSelectedDateRange() {
        const period = eventTimePeriod ? eventTimePeriod.value : currentRange;
        const today = new Date();
        let start = new Date(today);
        let end = new Date(today);

        switch(period) {
            case 'today':
                break;
            case '72hours':
                start.setDate(today.getDate() - 2);
                break;
            case 'week':
                start.setDate(today.getDate() - 6);
                break;
            case 'month':
                start.setDate(today.getDate() - 29);
                break;
            case 'year':
                start = new Date(today.getFullYear(), 0, 1);
                break;
            case 'all':
                start = new Date(2000, 0, 1);
                break;
            case 'custom':
                if (eventStartDate?.value) start = new Date(`${eventStartDate.value}T00:00:00`);
                if (eventEndDate?.value) end = new Date(`${eventEndDate.value}T23:59:59`);
                break;
            default:
                start.setDate(today.getDate() - 29);
                break;
        }

        return { start: formatDate(start), end: formatDate(end), period };
    }

    function syncActivityHourCardHeight() {
        if (activityHourChart && document.getElementById('activityHourChart')) {
            activityHourChart.resize();
        }
        var overviewChart = chartInstances['overviewChart'];
        if (overviewChart && typeof overviewChart.resize === 'function' && document.getElementById('overviewChart')) {
            overviewChart.resize();
        }
        var uaChart = chartInstances['userActivityTrackingChart2'];
        if (uaChart && typeof uaChart.resize === 'function' && document.getElementById('userActivityTrackingChart2')) {
            uaChart.resize();
        }
    }

    async function updateDashboard(range = 'week') {
        if (!document.getElementById('dashboard-container')) {
            if (window._dashboardPollTimer) clearInterval(window._dashboardPollTimer);
            return; // Exit if page changed
        }

        if (isInitialLoad && loadingSpinner) {
            loadingSpinner.style.display = 'block';
        }
        try {
            const dates = getSelectedDateRange();
            const response = await fetch(`${resolvedBaseUrl}/audit.php?start=${dates.start}&end=${dates.end}&_=${new Date().getTime()}`);
            if (!response.ok) throw new Error(`API Error: ${response.status}`);
            const data = await response.json();
            allLogs = data.logs;
            fillSelectOptions(eventActionFilter, [...new Set(allLogs.map((log) => log.action))].sort(), 'All Actions');
            fillSelectOptions(eventStatusFilter, [...new Set(allLogs.map((log) => log.status))].sort(), 'All Statuses');

            // Update stats
            Object.keys(statElements).forEach(key => {
                let statKey = key;
                if (key === 'logins') statKey = 'logins_in_range';
                else if (key === 'failures') statKey = 'failures_in_range';
                else if (key === 'onlineUsers') statKey = 'online_users';
                else if (key === 'enabledUsers') statKey = 'enabled_users';

                if(statElements[key] && data.stats[statKey] !== undefined) statElements[key].textContent = data.stats[statKey];
            });

            // Tooltips
            const onlineUsersStatElement = document.getElementById('stat-online-users');
            if (onlineUsersStatElement) {
                onlineUsersStatElement.style.cursor = 'pointer';
                let tooltipTimeout;

                const createTooltipContent = (users) => {
                    if (!users || !Array.isArray(users) || users.length === 0) return 'No active users.';
                    let content = '';
                    users.forEach((user, index) => {
                        if (user && user.username) {
                            content += `<b>${escapeHtml(user.username)}</b><br>`;
                            content += `IP: ${escapeHtml(user.ip)}<br>`;
                            content += `Session: ${formatTime(user.current_session_time)}<br>`;
                            content += `Last Seen: ${escapeHtml(user.last_session)}<br>`;
                            content += `Logins: ${user.login_count}<br>`;
                            if (index < users.length - 1) content += '<br>';
                        }
                    });
                    return content;
                };

                const showTooltip = (event, users) => {
                    clearTimeout(tooltipTimeout);
                    tooltipTimeout = setTimeout(() => {
                        // EFFECT: stat tooltip | Purpose: hover tooltip with fade-in for dashboard stat elements
                        let tooltip = document.getElementById('online-users-tooltip');
                        if (!tooltip) {
                            tooltip = document.createElement('div');
                            tooltip.id = 'online-users-tooltip';
                            tooltip.style.cssText = `position: absolute; background-color: #333; color: #fff; padding: 10px; border-radius: 5px; z-index: 10000; font-size: 0.85em; line-height: 1.4; max-width: 300px; box-shadow: 0 2px 10px rgba(0,0,0,0.5); pointer-events: none; opacity: 0; transition: opacity 0.2s ease-in-out;`;
                            document.body.appendChild(tooltip);
                        }
                        tooltip.innerHTML = createTooltipContent(users);
                        const rect = onlineUsersStatElement.getBoundingClientRect();
                        tooltip.style.left = `${rect.left + window.scrollX}px`;
                        tooltip.style.top = `${rect.bottom + window.scrollY + 5}px`;
                        tooltip.style.opacity = '1';
                    }, 500);
                };

                // EFFECT: tooltip hide with fade-out | Purpose: smooth tooltip dismissal
                const hideTooltip = () => {
                    clearTimeout(tooltipTimeout);
                    const tooltip = document.getElementById('online-users-tooltip');
                    if (tooltip) {
                        tooltip.style.opacity = '0';
                        setTimeout(() => { if (tooltip.parentNode) tooltip.parentNode.removeChild(tooltip); }, 200);
                    }
                };

                onlineUsersStatElement.removeEventListener('mouseover', onlineUsersStatElement._showTooltipHandler);
                onlineUsersStatElement.removeEventListener('mouseout', onlineUsersStatElement._hideTooltipHandler);
                onlineUsersStatElement._showTooltipHandler = (e) => showTooltip(e, data.online_users_list);
                onlineUsersStatElement._hideTooltipHandler = hideTooltip;
                onlineUsersStatElement.addEventListener('mouseover', onlineUsersStatElement._showTooltipHandler);
                onlineUsersStatElement.addEventListener('mouseout', onlineUsersStatElement._hideTooltipHandler);
            }

            // Stat Logins Tooltip
            const loginsStatElement = document.getElementById('stat-logins');
            if (loginsStatElement) {
                loginsStatElement.style.cursor = 'pointer';
                let tooltipTimeout;
                const createLoginsTooltipContent = (logins) => {
                    if (logins.length === 0) return 'No successful logins.';
                    let content = '';
                    logins.forEach((login, index) => {
                        content += `<b>${escapeHtml(login.username)}</b><br>Time: ${escapeHtml(login.timestamp.split(' ')[1])}<br>IP: ${escapeHtml(login.ip)}<br>`;
                        if (index < logins.length - 1) content += '<br>';
                    });
                    return content;
                };
                const showLoginsTooltip = (event, logins) => {
                    clearTimeout(tooltipTimeout);
                    tooltipTimeout = setTimeout(() => {
                        let tooltip = document.getElementById('logins-tooltip');
                        if (!tooltip) {
                            tooltip = document.createElement('div');
                            tooltip.id = 'logins-tooltip';
                            tooltip.style.cssText = `position: absolute; background-color: #333; color: #fff; padding: 10px; border-radius: 5px; z-index: 10000; font-size: 0.85em; line-height: 1.4; max-width: 300px; box-shadow: 0 2px 10px rgba(0,0,0,0.5); pointer-events: none; opacity: 0; transition: opacity 0.2s ease-in-out;`;
                            document.body.appendChild(tooltip);
                        }
                        tooltip.innerHTML = createLoginsTooltipContent(logins);
                        const rect = loginsStatElement.getBoundingClientRect();
                        tooltip.style.left = `${rect.left + window.scrollX}px`;
                        tooltip.style.top = `${rect.bottom + window.scrollY + 5}px`;
                        tooltip.style.opacity = '1';
                    }, 500);
                };
                const hideLoginsTooltip = () => {
                    clearTimeout(tooltipTimeout);
                    const tooltip = document.getElementById('logins-tooltip');
                    if (tooltip) {
                        tooltip.style.opacity = '0';
                        setTimeout(() => { if (tooltip.parentNode) tooltip.parentNode.removeChild(tooltip); }, 200);
                    }
                };
                loginsStatElement.removeEventListener('mouseover', loginsStatElement._showLoginsTooltipHandler);
                loginsStatElement.removeEventListener('mouseout', loginsStatElement._hideLoginsTooltipHandler);
                loginsStatElement._showLoginsTooltipHandler = (e) => showLoginsTooltip(e, data.successful_logins_list);
                loginsStatElement._hideLoginsTooltipHandler = hideLoginsTooltip;
                loginsStatElement.addEventListener('mouseover', loginsStatElement._showLoginsTooltipHandler);
                loginsStatElement.addEventListener('mouseout', loginsStatElement._hideLoginsTooltipHandler);
            }

            // Stat Failures Tooltip
            const failuresStatElement = document.getElementById('stat-failures');
            if (failuresStatElement) {
                failuresStatElement.style.cursor = 'pointer';
                let tooltipTimeout;
                const createFailuresTooltipContent = (failures) => {
                    if (failures.length === 0) return 'No failed logins.';
                    let content = '';
                    failures.forEach((failure, index) => {
                        content += `<b>${escapeHtml(failure.username)}</b><br>Time: ${escapeHtml(failure.timestamp.split(' ')[1])}<br>IP: ${escapeHtml(failure.ip)}<br>Details: ${escapeHtml(failure.details)}<br>`;
                        if (index < failures.length - 1) content += '<br>';
                    });
                    return content;
                };
                const showFailuresTooltip = (event, failures) => {
                    clearTimeout(tooltipTimeout);
                    tooltipTimeout = setTimeout(() => {
                        let tooltip = document.getElementById('failures-tooltip');
                        if (!tooltip) {
                            tooltip = document.createElement('div');
                            tooltip.id = 'failures-tooltip';
                            tooltip.style.cssText = `position: absolute; background-color: #333; color: #fff; padding: 10px; border-radius: 5px; z-index: 10000; font-size: 0.85em; line-height: 1.4; max-width: 300px; box-shadow: 0 2px 10px rgba(0,0,0,0.5); pointer-events: none; opacity: 0; transition: opacity 0.2s ease-in-out;`;
                            document.body.appendChild(tooltip);
                        }
                        tooltip.innerHTML = createFailuresTooltipContent(failures);
                        const rect = failuresStatElement.getBoundingClientRect();
                        tooltip.style.left = `${rect.left + window.scrollX}px`;
                        tooltip.style.top = `${rect.bottom + window.scrollY + 5}px`;
                        tooltip.style.opacity = '1';
                    }, 500);
                };
                const hideFailuresTooltip = () => {
                    clearTimeout(tooltipTimeout);
                    const tooltip = document.getElementById('failures-tooltip');
                    if (tooltip) {
                        tooltip.style.opacity = '0';
                        setTimeout(() => { if (tooltip.parentNode) tooltip.parentNode.removeChild(tooltip); }, 200);
                    }
                };
                failuresStatElement.removeEventListener('mouseover', failuresStatElement._showFailuresTooltipHandler);
                failuresStatElement.removeEventListener('mouseout', failuresStatElement._hideFailuresTooltipHandler);
                failuresStatElement._showFailuresTooltipHandler = (e) => showFailuresTooltip(e, data.failed_logins_list);
                failuresStatElement._hideFailuresTooltipHandler = hideFailuresTooltip;
                failuresStatElement.addEventListener('mouseover', failuresStatElement._showFailuresTooltipHandler);
                failuresStatElement.addEventListener('mouseout', failuresStatElement._hideFailuresTooltipHandler);
            }

            // Stat Enabled Users Tooltip
            const enabledUsersStatElement = document.getElementById('stat-enabled-users');
            if (enabledUsersStatElement) {
                enabledUsersStatElement.style.cursor = 'pointer';
                let tooltipTimeout;
                const createEnabledUsersTooltipContent = (enabledUsers) => {
                    if (enabledUsers.length === 0) return 'No enabled users.';
                    let content = '';
                    enabledUsers.forEach((user, index) => {
                        content += `<b>${escapeHtml(user)}</b>`;
                        if (index < enabledUsers.length - 1) content += '<br>';
                    });
                    return content;
                };
                const showEnabledUsersTooltip = (event, enabledUsers) => {
                    clearTimeout(tooltipTimeout);
                    tooltipTimeout = setTimeout(() => {
                        let tooltip = document.getElementById('enabled-users-tooltip');
                        if (!tooltip) {
                            tooltip = document.createElement('div');
                            tooltip.id = 'enabled-users-tooltip';
                            tooltip.style.cssText = `position: absolute; background-color: #333; color: #fff; padding: 10px; border-radius: 5px; z-index: 10000; font-size: 0.85em; line-height: 1.4; max-width: 300px; box-shadow: 0 2px 10px rgba(0,0,0,0.5); pointer-events: none; opacity: 0; transition: opacity 0.2s ease-in-out;`;
                            document.body.appendChild(tooltip);
                        }
                        tooltip.innerHTML = createEnabledUsersTooltipContent(enabledUsers);
                        const rect = enabledUsersStatElement.getBoundingClientRect();
                        tooltip.style.left = `${rect.left + window.scrollX}px`;
                        tooltip.style.top = `${rect.bottom + window.scrollY + 5}px`;
                        tooltip.style.opacity = '1';
                    }, 500);
                };
                const hideEnabledUsersTooltip = () => {
                    clearTimeout(tooltipTimeout);
                    const tooltip = document.getElementById('enabled-users-tooltip');
                    if (tooltip) {
                        tooltip.style.opacity = '0';
                        setTimeout(() => { if (tooltip.parentNode) tooltip.parentNode.removeChild(tooltip); }, 200);
                    }
                };
                enabledUsersStatElement.removeEventListener('mouseover', enabledUsersStatElement._showEnabledUsersTooltipHandler);
                enabledUsersStatElement.removeEventListener('mouseout', enabledUsersStatElement._hideEnabledUsersTooltipHandler);
                enabledUsersStatElement._showEnabledUsersTooltipHandler = (e) => showEnabledUsersTooltip(e, data.enabled_users_list);
                enabledUsersStatElement._hideEnabledUsersTooltipHandler = hideEnabledUsersTooltip;
                enabledUsersStatElement.addEventListener('mouseover', enabledUsersStatElement._showEnabledUsersTooltipHandler);
                enabledUsersStatElement.addEventListener('mouseout', enabledUsersStatElement._hideEnabledUsersTooltipHandler);
            }

            // Charts Update
            if (activityHourChart) {
                activityHourChart.data.labels = data.stats.active_hour_labels || [];
                const activityByUser = data.stats.activity_by_hour_by_user || {};
                const activeUsernames = Object.keys(activityByUser);

                activeUsernames.forEach(username => {
                    const existingDataset = activityHourChart.data.datasets.find(ds => ds.label === username);
                    if (existingDataset) {
                        existingDataset.data = activityByUser[username];
                    } else {
                        if (!userColorMap[username]) {
                            const colorIndex = simpleHash(username) % colorPalette.length;
                            userColorMap[username] = colorPalette[colorIndex];
                        }
                        const color = userColorMap[username];
                        const newDataset = {
                            label: username,
                            data: activityByUser[username],
                            borderColor: color,
                            backgroundColor: hexToRgba(color, 0.2),
                            fill: true, tension: 0.4, borderWidth: 2
                        };
                        activityHourChart.data.datasets.push(newDataset);
                    }
                });
                activityHourChart.data.datasets = activityHourChart.data.datasets.filter(ds => activeUsernames.includes(ds.label));
            }

            const overviewData = { 'Online': data.stats.online_users, 'Logins': data.stats.logins_in_range, 'Failures': data.stats.failures_in_range, 'Enabled': data.stats.enabled_users };
            const ctxOverview = document.getElementById('overviewChart')?.getContext('2d');
            if (ctxOverview) {
                renderOverviewStatsChart(ctxOverview, overviewData, isInitialLoad);
                const overviewChart = chartInstances['overviewChart'];
                if (overviewChart) {
                    const legendContainer = document.getElementById('overviewChart-legend');
                    if (legendContainer) {
                        legendContainer.innerHTML = '';
                        legendContainer.appendChild(createHtmlLegend(overviewChart, 'doughnut'));
                    }
                }
            }

            // User Activity Tracking chart is driven ONLY by refreshGuestChart
            // (rolling guest window) to keep it in sync with the guest chart.

            const quickActionChart = document.getElementById('dashboardTodayLogChart');
            if (quickActionChart) {
                const todayLogsApiUrl = `${resolvedBaseUrl}/api/index.php?endpoint=log_data&time_period=today`;
                fetch(todayLogsApiUrl)
                    .then(response => response.json())
                    .then(data => {
                        if (data.todayActionStatusBreakdown && typeof renderTodayLogChart === 'function') {
                            renderTodayLogChart(Object.keys(data.todayActionStatusBreakdown), Object.values(data.todayActionStatusBreakdown), 'dashboardTodayLogChart', null);
                        }
                        if (data.todayStatusBreakdown && typeof updateCustomLegend === 'function') {
                            const legendOrder = ['NOT FOUND', 'TRIGGERED', 'SUCCESS', 'SKIPPED', 'FAILED'];
                            const legendItems = Object.keys(data.todayStatusBreakdown).map(label => ({ label: label, value: data.todayStatusBreakdown[label], color: window.statusColors[label.toUpperCase()] || '#CCCCCC' }));
                            legendItems.sort((a, b) => {
                                const indexA = legendOrder.indexOf(a.label.toUpperCase());
                                const indexB = legendOrder.indexOf(b.label.toUpperCase());
                                return (indexA === -1 ? 1 : indexB === -1 ? -1 : indexA - indexB);
                            });
                            updateCustomLegend('dashboardTodayLogChart', legendItems.map(item => item.label), legendItems.map(item => item.value), legendItems.map(item => item.color));
                        }
                    }).catch(err => console.error('Error fetching today\'s logs data:', err));
            }

            if (activityHourChart) {
                activityHourChart.options.animation = isInitialLoad ? { duration: 1000, easing: 'easeOutQuart' } : { duration: 0 };
                activityHourChart.update();
                isInitialLoad = false;
            }

            requestAnimationFrame(syncActivityHourCardHeight);

            updateOnlineUsersTable(data.online_users_list);
            updateAuditLogTable(data.logs);

        } catch (error) {
            console.error('Dashboard update failed:', error);
        } finally {
            if (loadingSpinner) loadingSpinner.style.display = 'none';
        }
    }

    function updateOnlineUsersTable(users) {
        if (!onlineUsersTableBody) return;
        onlineUsersTableBody.innerHTML = users.length ? '' : '<tr><td colspan="4" class="text-center">No active users.</td></tr>';
        users.forEach(user => {
            const sessionTime = user.current_session_time > 0 ? user.current_session_time : 0;
            const row = `<tr><td>${user.username}</td><td>${escapeHtml(user.ip)}</td><td data-time="${sessionTime}">${formatTime(sessionTime)}</td><td><button class="btn btn-danger btn-sm terminate-btn" data-username="${user.username}" title="Terminate Session"><i class="fas fa-power-off"></i></button></td></tr>`;
            onlineUsersTableBody.insertAdjacentHTML('beforeend', row);
        });
    }

    function guestRangeDates() {
        const today = new Date();
        let start = new Date(today);
        const end = new Date(today);

        switch (guestChartRange) {
            case 'yesterday':
                start = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);
                end = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                break;
            case 'lastweek':
                start.setDate(today.getDate() - 6);
                start.setHours(0, 0, 0, 0);
                break;
            case 'month':
                start.setDate(today.getDate() - 29);
                start.setHours(0, 0, 0, 0);
                break;
            case 'custom':
                if (guestStartDate?.value) start = new Date(`${guestStartDate.value}T00:00:00`);
                if (guestEndDate?.value) end = new Date(`${guestEndDate.value}T23:59:59`);
                break;
            case 'last12h': // rolling 12 hours, anchored to the current hour
                start = new Date(today);
                start.setMinutes(0, 0, 0);
                start.setHours(start.getHours() - 11);
                break;
            case 'last72h': // rolling 72 hours, anchored to the current hour
                start = new Date(today);
                start.setMinutes(0, 0, 0);
                start.setHours(start.getHours() - 71);
                break;
            default: // last24h — rolling 24 hours, anchored to the current hour
                start = new Date(today);
                start.setMinutes(0, 0, 0);
                start.setHours(start.getHours() - 23);
                break;
        }

        if (guestChartRange === 'last12h' || guestChartRange === 'last24h' || guestChartRange === 'last72h') {
            return { start: formatDateTime(start), end: formatDateTime(end) };
        }
        if (guestChartRange === 'lastweek' || guestChartRange === 'month') {
            // Include today up to now (end as datetime), otherwise today's activity
            // is excluded by the backend window guard and the last bucket is always 0.
            return { start: formatDate(start), end: formatDateTime(end) };
        }
        return { start: formatDate(start), end: formatDate(end) };
    }

    async function refreshGuestChart() {
        const ctxG = document.getElementById('guestFailureChart')?.getContext('2d');
        if (!ctxG) return;
        try {
            const d = guestRangeDates();
            const guestQ = `guest_range=${encodeURIComponent(guestChartRange)}&guest_start=${encodeURIComponent(d.start)}&guest_end=${encodeURIComponent(d.end)}`;
            // Scope the audit read to the selected window only (not 365 days) so
            // each 5s refresh stays fast.
            const res = await fetch(`${resolvedBaseUrl}/audit.php?start=${encodeURIComponent(d.start)}&end=${encodeURIComponent(d.end)}&${guestQ}&_=${new Date().getTime()}`);
            if (!res.ok) throw new Error(`API Error: ${res.status}`);
            const data = await res.json();
            lastGuestChartData = data;
            renderGuestFailureChart(ctxG, data.guest_labels || [], data.guest_failures_by_ip_hour || {}, data.guest_failed_attempts || [], false);
            if (guestFailureCountEl) guestFailureCountEl.textContent = String((data.guest_failed_attempts || []).length);
            const ctxAct = document.getElementById('userActivityTrackingChart2')?.getContext('2d');
            if (ctxAct && typeof renderUserActivityTrackingChart === 'function') {
                renderUserActivityTrackingChart(ctxAct, data.guest_user_activity_tracking || { users: [], actions: [], data: {} }, false);
            }
        } catch (err) {
            console.error('Guest chart refresh failed:', err);
        }
    }

    function setGuestRange(range) {
        guestChartRange = range;
        (guestRangeButtons || []).forEach(btn => {
            const isActive = btn.dataset.guestRange === range;
            btn.classList.toggle('active', isActive);
            if (isActive) {
                btn.style.color = '#ffffff';
                btn.style.backgroundColor = '#4f46e5';
                btn.style.borderColor = '#4f46e5';
            } else {
                btn.style.color = '';
                btn.style.backgroundColor = '';
                btn.style.borderColor = '';
            }
        });
        refreshGuestChart();
    }

    function downloadGuestReport() {
        const ctxG = document.getElementById('guestFailureChart');
        if (!ctxG) return;
        // PNG of the chart
        try {
            const chart = chartInstances['guestFailureChart'] || guestFailureChart;
            const url = chart && typeof chart.toBase64Image === 'function' ? chart.toBase64Image('image/png', 1) : ctxG.toDataURL('image/png');
            const a = document.createElement('a');
            a.href = url;
            a.download = `guest_monitoring_${formatDate(new Date())}.png`;
            a.click();
        } catch (e) { console.error('PNG export failed:', e); }

        // CSV of per-IP data
        const data = lastGuestChartData || {};
        const labels = data.guest_labels || [];
        const byIp = data.guest_failures_by_ip_hour || {};
        const attempts = data.guest_failed_attempts || [];
        const rows = [];
        rows.push(['Guest Monitoring Report', formatDate(new Date()), 'Range: ' + guestChartRange]);
        rows.push([]);
        rows.push(['Bucket', ...labels]);
        const totals = labels.map((_, i) => Object.values(byIp).reduce((s, arr) => s + (arr[i] || 0), 0));
        rows.push(['TOTAL', ...totals]);
        Object.keys(byIp).forEach(ip => {
            rows.push([ip, ...byIp[ip].map(v => v || 0)]);
        });
        rows.push([]);
        rows.push(['Time', 'Attempted ID', 'IP Address', 'Attempt']);
        attempts.forEach(a => {
            const attemptLabel = (a.details && String(a.details).toLowerCase().includes('rate')) ? 'Rate Limited'
                : (a.details && String(a.details).toLowerCase().includes('locked')) ? 'Locked Out' : 'Invalid Credentials';
            rows.push([a.timestamp || '', a.username || '', a.ip || 'N/A', attemptLabel]);
        });
        const csv = rows.map(r => r.map(cell => {
            const s = String(cell ?? '');
            return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
        }).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `guest_monitoring_${formatDate(new Date())}.csv`;
        link.click();
        setTimeout(() => URL.revokeObjectURL(link.href), 3000);
    }

    function updateAuditLogTable(logs) {
        if (!auditLogTableBody) return;
        const tableWrapper = auditLogTableBody.closest('.log-table-wrapper');
        // UX: preserve scroll position | Purpose: keep scroll stable during table data refresh
        const previousScrollTop = tableWrapper ? tableWrapper.scrollTop : 0;
        const searchTerm = eventLogSearchInput ? eventLogSearchInput.value.toLowerCase() : '';
        const selectedAction = eventActionFilter ? eventActionFilter.value : '';
        const selectedStatus = eventStatusFilter ? eventStatusFilter.value : '';

        const filteredData = logs.filter(log => {
            const matchesSearch = !searchTerm || (
                (log.timestamp || '').toLowerCase().includes(searchTerm) ||
                (log.username || '').toLowerCase().includes(searchTerm) ||
                (log.action || '').toLowerCase().includes(searchTerm) ||
                (log.status || '').toLowerCase().includes(searchTerm) ||
                (log.ip || '').toLowerCase().includes(searchTerm) ||
                (log.details || '').toLowerCase().includes(searchTerm)
            );
            const matchesAction = !selectedAction || log.action === selectedAction;
            const matchesStatus = !selectedStatus || log.status === selectedStatus;
            return matchesSearch && matchesAction && matchesStatus;
        });

        auditLogTableBody.innerHTML = '';
        if (eventNoLogsMessage) eventNoLogsMessage.style.display = filteredData.length ? 'none' : 'block';
        if (eventLogCount) eventLogCount.textContent = String(filteredData.length);

        filteredData.forEach(log => {
            const statusValue = String(log.status || '');
            const upperStatus = statusValue.toUpperCase();
            let statusClass = 'status-info';
            if (upperStatus === 'SUCCESS') statusClass = 'status-success';
            else if (upperStatus.includes('FAIL') || upperStatus.includes('ERROR')) statusClass = 'status-failed';
            else if (upperStatus.includes('PENDING')) statusClass = 'status-pending';
            else if (upperStatus.includes('WARNING') || upperStatus.includes('NOT FOUND')) statusClass = 'status-warning';

            const actionClass = (log.action || '').toLowerCase().replace(/[^a-zA-Z0-9]/g, '_');

            let ts = String(log.timestamp || '');
            // Fix for missing space between date and time
            if (ts.length >= 10 && ts[10] !== ' ') {
                ts = ts.slice(0, 10) + ' ' + ts.slice(10);
            }
            const timestampParts = ts.split(' ');
            const timestampDate = timestampParts.slice(0, 1).join(' ');
            const timestampTime = timestampParts.slice(1).join(' ');

            const row = `<tr>
                <td class="log-timestamp-cell"><span class="log-timestamp-date">${escapeHtml(timestampDate)}</span><span class="log-timestamp-time">${escapeHtml(timestampTime)}</span></td>
                <td>${escapeHtml(log.username)}</td>
                <td><span class="action-badge action-${escapeHtml(actionClass)}">${escapeHtml(log.action)}</span></td>
                <td><span class="status-badge ${statusClass}">${escapeHtml(statusValue)}</span></td>
                <td>${escapeHtml(log.ip)}</td>
                <td class="message-column">${escapeHtml(log.details)}</td>
            </tr>`;
            auditLogTableBody.insertAdjacentHTML('beforeend', row);
        });

        if (tableWrapper) {
            window.requestAnimationFrame(() => {
                tableWrapper.scrollTop = previousScrollTop;
            });
        }
    }

    async function terminateUserSession(username) {
        if (!confirm(`Are you sure you want to terminate the session for ${username}?`)) return;
        if (loadingSpinner) loadingSpinner.style.display = 'block';
        try {
            const response = await fetch(`${resolvedBaseUrl}/api/index.php?endpoint=user_management_action`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'terminate_session', username: username }) });
            const result = await response.json();
            if (result.success) { alert(result.message); updateDashboard(currentRange); }
            else throw new Error(result.message || 'Failed to terminate session.');
        } catch (error) { alert(`Error: ${error.message}`); }
        finally { if (loadingSpinner) loadingSpinner.style.display = 'none'; }
    }

    const formatDate = (d) => {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };
    const formatDateTime = (d) => {
        const date = formatDate(d);
        const hh = String(d.getHours()).padStart(2, '0');
        const mm = String(d.getMinutes()).padStart(2, '0');
        const ss = String(d.getSeconds()).padStart(2, '0');
        return `${date} ${hh}:${mm}:${ss}`;
    };
    const formatTime = (s) => `${Math.floor(s/60)}m ${s%60}s`;
    const formatTime12Hour = (timeString) => {
        if (!timeString) return '';
        const [hour, minute, second] = timeString.split(':');
        let h = parseInt(hour, 10); const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12; h = h ? h : 12; return `${h}:${minute}:${second} ${ampm}`;
    }
    const escapeHtml = (u) => typeof u !== 'string' ? '' : u.replace(/[&<>"'']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));

    if (eventTimePeriod) {
        eventTimePeriod.value = currentRange;
        eventTimePeriod.addEventListener('change', () => {
            const isCustom = eventTimePeriod.value === 'custom';
            if (eventCustomDateInputs) eventCustomDateInputs.style.display = isCustom ? 'block' : 'none';
            currentRange = eventTimePeriod.value;
            if (!isCustom) updateDashboard(currentRange);
        });
    }

    if (onlineUsersTableBody) {
        onlineUsersTableBody.addEventListener('click', (e) => {
            const btn = e.target.closest('.terminate-btn');
            if (btn) terminateUserSession(btn.dataset.username);
        });
    }

    if (eventLogSearchInput) {
        eventLogSearchInput.addEventListener('keyup', (e) => {
            if (e.key && e.key !== 'Enter') {
                updateAuditLogTable(allLogs);
                return;
            }
            updateAuditLogTable(allLogs);
        });
    }
    if (eventActionFilter) eventActionFilter.addEventListener('change', () => updateAuditLogTable(allLogs));
    if (eventStatusFilter) eventStatusFilter.addEventListener('change', () => updateAuditLogTable(allLogs));
    if (applyEventFiltersBtn) {
        applyEventFiltersBtn.addEventListener('click', () => {
            currentRange = eventTimePeriod ? eventTimePeriod.value : currentRange;
            updateDashboard(currentRange);
        });
    }
    if (resetEventFiltersBtn) {
        resetEventFiltersBtn.addEventListener('click', () => {
            if (eventLogSearchInput) eventLogSearchInput.value = '';
            if (eventActionFilter) eventActionFilter.value = '';
            if (eventStatusFilter) eventStatusFilter.value = '';
            if (eventTimePeriod) eventTimePeriod.value = 'week';
            if (eventStartDate) eventStartDate.value = '';
            if (eventEndDate) eventEndDate.value = '';
            if (eventCustomDateInputs) eventCustomDateInputs.style.display = 'none';
            currentRange = 'week';
            updateDashboard(currentRange);
        });
    }

    if (eventOverviewCard && activityHourCard && typeof ResizeObserver !== 'undefined') {
        activityHourResizeObserver = new ResizeObserver(() => {
            requestAnimationFrame(syncActivityHourCardHeight);
        });
        activityHourResizeObserver.observe(eventOverviewCard);
    }

    // Guest chart range buttons
    (guestRangeButtons || []).forEach(btn => {
        btn.addEventListener('click', () => setGuestRange(btn.dataset.guestRange));
    });
    if (guestCustomApplyBtn) {
        guestCustomApplyBtn.addEventListener('click', () => {
            guestChartRange = 'custom';
            (guestRangeButtons || []).forEach(b => b.classList.toggle('active', b.dataset.guestRange === 'custom'));
            refreshGuestChart();
        });
    }
    if (guestDownloadReportBtn) {
        guestDownloadReportBtn.addEventListener('click', downloadGuestReport);
    }

    // --- IP Blocking panel ---
    const guestBlockedIpsBtn = document.getElementById('guestBlockedIpsBtn');
    const guestBlockedIpsPanel = document.getElementById('guestBlockedIpsPanel');
    const guestBlockIpInput = document.getElementById('guestBlockIpInput');
    const guestBlockIpAddBtn = document.getElementById('guestBlockIpAddBtn');
    const guestBlockToggle = document.getElementById('guestBlockToggle');
    const guestBlockMsg = document.getElementById('guestBlockMsg');
    const guestBlockedIpsList = document.getElementById('guestBlockedIpsList');
    let blocklistData = { enabled: true, blocklist: [], allowlist: [], my_ip: '' };

    function setGuestBlockMsg(text, ok) {
        if (guestBlockMsg) {
            guestBlockMsg.textContent = text || '';
            guestBlockMsg.style.color = ok ? '#198754' : '#dc3545';
        }
    }

    async function loadBlockedIps() {
        try {
            const res = await fetch('/api/index.php?endpoint=ip_block&action=list', { method: 'GET' });
            const data = await res.json();
            if (data && data.success) blocklistData = data;
            renderBlockedIps();
        } catch (e) {
            setGuestBlockMsg('Failed to load blocklist.', false);
        }
    }

    function renderBlockedIps() {
        if (!guestBlockToggle || !guestBlockedIpsList) return;
        guestBlockToggle.checked = !!blocklistData.enabled;
        guestBlockedIpsList.innerHTML = '';
        const all = blocklistData.blocklist || [];
        if (all.length === 0) {
            guestBlockedIpsList.innerHTML = '<span class="text-muted">No IPs blocked.</span>';
        } else {
            all.forEach(entry => {
                const chip = document.createElement('span');
                chip.className = 'd-inline-flex align-items-center gap-1 border rounded px-2 py-1';
                chip.style.cssText = 'background: rgba(220,53,69,.12); border-color: rgba(220,53,69,.35)!important;';
                chip.innerHTML = '<i class="fas fa-ban" style="color:#dc3545;"></i><span>' + entry.replace(/[<>&]/g, '') + '</span>' +
                    '<button type="button" class="btn btn-sm btn-link p-0 ms-1" style="color:#dc3545;" title="Unblock">' +
                    '<i class="fas fa-times"></i></button>';
                const btn = chip.querySelector('button');
                btn.addEventListener('click', () => removeBlockedIp(entry));
                guestBlockedIpsList.appendChild(chip);
            });
        }
        if (guestBlockedIpsList.querySelector('.text-muted') && blocklistData.my_ip) {
            guestBlockedIpsList.innerHTML += '<span class="text-muted ms-1">(your IP: ' + blocklistData.my_ip.replace(/[<>&]/g, '') + ')</span>';
        }
    }

    async function addBlockedIp() {
        if (!guestBlockIpInput) return;
        const ip = guestBlockIpInput.value.trim();
        if (!ip) { setGuestBlockMsg('Enter an IP or CIDR.', false); return; }
        const body = new URLSearchParams();
        body.set('action', 'add');
        body.set('ip', ip);
        const res = await fetch('/api/index.php?endpoint=ip_block', { method: 'POST', body });
        const data = await res.json();
        if (data && data.success) {
            blocklistData = data;
            guestBlockIpInput.value = '';
            setGuestBlockMsg(data.message, true);
            if (blocklistData.my_ip && (data.blocklist || []).some(e => e === blocklistData.my_ip || e === ip)) {
                setGuestBlockMsg(data.message + ' WARNING: this matches your current IP — you may be blocked on next load.', true);
            }
            renderBlockedIps();
        } else {
            setGuestBlockMsg((data && data.message) || 'Block failed.', false);
        }
    }

    async function removeBlockedIp(entry) {
        const body = new URLSearchParams();
        body.set('action', 'remove');
        body.set('ip', entry);
        const res = await fetch('/api/index.php?endpoint=ip_block', { method: 'POST', body });
        const data = await res.json();
        if (data && data.success) {
            blocklistData = data;
            setGuestBlockMsg(data.message, true);
            renderBlockedIps();
        } else {
            setGuestBlockMsg((data && data.message) || 'Unblock failed.', false);
        }
    }

    async function toggleBlocking() {
        const body = new URLSearchParams();
        body.set('action', 'toggle');
        const res = await fetch('/api/index.php?endpoint=ip_block', { method: 'POST', body });
        const data = await res.json();
        if (data && data.success) {
            blocklistData = data;
            setGuestBlockMsg(data.message, true);
            renderBlockedIps();
        } else {
            setGuestBlockMsg((data && data.message) || 'Toggle failed.', false);
            renderBlockedIps();
        }
    }

    if (guestBlockedIpsBtn) {
        guestBlockedIpsBtn.addEventListener('click', () => {
            const visible = guestBlockedIpsPanel.style.display !== 'none';
            guestBlockedIpsPanel.style.display = visible ? 'none' : 'block';
            if (!visible) loadBlockedIps();
        });
    }
    if (guestBlockIpAddBtn) guestBlockIpAddBtn.addEventListener('click', addBlockedIp);
    if (guestBlockIpInput) guestBlockIpInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') addBlockedIp(); });
    if (guestBlockToggle) guestBlockToggle.addEventListener('change', toggleBlocking);
    // Default to last 12 hours on init
    setGuestRange('last12h');

    window.addEventListener('resize', syncActivityHourCardHeight);

    // Set intervals
    if (window._dashboardSessionTimer) clearInterval(window._dashboardSessionTimer);
    window._dashboardSessionTimer = setInterval(() => {
        if (!onlineUsersTableBody) return;
        onlineUsersTableBody.querySelectorAll('td[data-time]').forEach(cell => {
            let t = parseInt(cell.dataset.time) + 1; cell.dataset.time = t; cell.textContent = formatTime(t);
        });
    }, 1000);

    if (window._dashboardPollTimer) clearInterval(window._dashboardPollTimer);
    window._dashboardPollTimer = setInterval(() => {
        if (document.getElementById('dashboard-container')) updateDashboard(currentRange);
        else { clearInterval(window._dashboardPollTimer); clearInterval(window._dashboardSessionTimer); }
    }, 5000);

    // Dedicated guest chart live poll — runs on any page hosting the guest card
    // (dashboard-container may be absent, e.g. on the User Activity page).
    if (window._guestPollTimer) clearInterval(window._guestPollTimer);
    window._guestPollTimer = setInterval(() => {
        if (document.getElementById('guestFailureChart')) {
            refreshGuestChart();
        } else {
            clearInterval(window._guestPollTimer);
        }
    }, 5000);

    syncActivityHourCardHeight();
    updateDashboard(currentRange);
};

// Auto-init on full page load (not just SPA)
(function autoInitDashboard() {
    var doInit = function() {
        if (document.getElementById('dashboard-container')) {
            window.initDashboardLogic();
            setTimeout(function() {
                var c = chartInstances;
                Object.keys(c).forEach(function(k) {
                    if (c[k] && typeof c[k].resize === 'function') c[k].resize();
                });
            }, 500);
        }
    };
    if (document.getElementById('dashboard-container')) {
        doInit();
    } else {
        var attempts = 0;
        var check = function() {
            attempts++;
            if (document.getElementById('dashboard-container')) {
                doInit();
            } else if (attempts < 20) {
                setTimeout(check, 100);
            }
        };
        setTimeout(check, 100);
    }
})();

})();
