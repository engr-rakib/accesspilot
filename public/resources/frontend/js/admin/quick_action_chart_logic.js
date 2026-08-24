document.addEventListener('DOMContentLoaded', function() {
    initQuickActionChart();
});

// Re-initialize after SPA content updates
document.addEventListener('spaContentUpdated', function() {
    initQuickActionChart();
});

function initQuickActionChart() {
    const chartCanvas = document.getElementById('todayLogChart');
    if (!chartCanvas) return;
    
    // Check if we already have an initialized marker on the element
    if (chartCanvas.dataset.initialized === 'true') {
        // Just refresh the data if already initialized
        if (typeof window.fetchTodayLogChartData === 'function') {
            window.fetchTodayLogChartData(true);
        }
        return;
    }
    chartCanvas.dataset.initialized = 'true';

    const resolvedBaseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (typeof baseURL === 'string' ? baseURL : '');
    
    // Register ChartDataLabels plugin if it's available and not already registered
    if (typeof ChartDataLabels !== 'undefined' && typeof Chart !== 'undefined') {
        if (!Chart.registry.plugins.get('datalabels')) {
            Chart.register(ChartDataLabels);
        }
    }

    if (!resolvedBaseUrl) {
        console.error('Application base URL is not defined. Quick action chart logic cannot proceed.');
        return;
    }

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

    let hasAnimatedSidebarChart = false;

    function updateSidebarBadge() {
        const now = new Date();
        const dateStr = now.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
        const targetCanvas = document.getElementById('todayLogChart');
        const badge = targetCanvas?.closest('.card')?.querySelector('.time-badge');
        if (badge) badge.textContent = dateStr;
    }

    // Function to fetch and render Today's Log Chart
    window.fetchTodayLogChartData = function(forceRefresh = false) {
        const targetCanvas = document.getElementById('todayLogChart');
        if (!targetCanvas) return;

        updateSidebarBadge();

        const todayLogsApiUrl = buildAdminApiUrl('log_data', { time_period: 'today' });

        fetch(todayLogsApiUrl)
            .then(response => response.json())
            .then(data => {
                // Data for the CHART (should be todayActionStatusBreakdown - action-based)
                if (data.todayActionStatusBreakdown && typeof renderTodayLogChart === 'function') {
                    const chartLabels = Object.keys(data.todayActionStatusBreakdown);
                    const chartData = Object.values(data.todayActionStatusBreakdown);
                    renderTodayLogChart(chartLabels, chartData, 'todayLogChart', null, !hasAnimatedSidebarChart);
                    hasAnimatedSidebarChart = true;
                }

                // Data for the CUSTOM LEGEND (should be todayStatusBreakdown - status-based)
                if (data.todayStatusBreakdown && typeof updateCustomLegend === 'function') {
                    const legendOrder = ['NOT FOUND', 'TRIGGERED', 'SUCCESS', 'SKIPPED', 'FAILED'];
                    const statusBreakdown = data.todayStatusBreakdown;

                    const legendItems = Object.keys(statusBreakdown).map(label => ({
                        label: label,
                        value: statusBreakdown[label],
                        color: window.statusColors ? (window.statusColors[label.toUpperCase()] || '#CCCCCC') : '#CCCCCC'
                    }));

                    legendItems.sort((a, b) => {
                        const indexA = legendOrder.indexOf(a.label.toUpperCase());
                        const indexB = legendOrder.indexOf(b.label.toUpperCase());
                        if (indexA === -1) return 1;
                        if (indexB === -1) return -1;
                        return indexA - indexB;
                    });

                    const sortedLabels = legendItems.map(item => item.label);
                    const sortedData = legendItems.map(item => item.value);
                    const sortedColors = legendItems.map(item => item.color);
                    
                    updateCustomLegend('todayLogChart', sortedLabels, sortedData, sortedColors);
                }
            })
            .catch(error => console.error('Error fetching today\'s logs data for quick action card:', error));
    }

    // Initial render with empty data to avoid layout shift
    if (typeof renderTodayLogChart === 'function') {
        renderTodayLogChart([], [], 'todayLogChart', null, true);
    }

    // Call the function to fetch and render the chart data
    fetchTodayLogChartData(true);
    
    // Set up polling for automatic updates (every 60 seconds)
    if (window._quickActionChartPollTimer) clearInterval(window._quickActionChartPollTimer);
    window._quickActionChartPollTimer = setInterval(() => {
        if (document.getElementById('todayLogChart')) {
            fetchTodayLogChartData(true);
        } else {
            clearInterval(window._quickActionChartPollTimer);
        }
    }, 60000);
}
