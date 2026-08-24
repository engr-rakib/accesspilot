document.addEventListener('DOMContentLoaded', function() {
    const resolvedBaseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (typeof baseURL === 'string' ? baseURL : '');
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
    function updateSidebarChart() {
        fetch(buildAdminApiUrl('log_data', { time_period: 'today' }))
            .then(response => response.json())
            .then(data => {
                if (data.todayActionStatusBreakdown) {
                    const chartLabels = Object.keys(data.todayActionStatusBreakdown);
                    const chartData = Object.values(data.todayActionStatusBreakdown);
                    const existingChart = Chart.getChart('todayLogChart'); // Assuming todayLogChart is the ID for sidebar chart
                    if (existingChart) existingChart.destroy();
                    // Assuming renderTodayLogChart is available globally or imported
                    if (typeof renderTodayLogChart === 'function') {
                        renderTodayLogChart(chartLabels, chartData, 'todayLogChart', null);
                    } else {
                        console.error('renderTodayLogChart function not found. Make sure chart_renderer.js is loaded.');
                    }
                }
            })
            .catch(error => console.error('Error fetching sidebar chart data:', error));
    }

    // Initial load
    updateSidebarChart();

    // Set up polling
    setInterval(updateSidebarChart, 5000); // Update every 5 seconds
});
