(function() {
    'use strict';

    /**
     * @file dashboard_charts.js
     * @brief Reusable functions for creating and updating charts for the admin dashboard.
     * 
     * This file uses Chart.js to render visualizations.
     */

    // Track chart instances locally and expose globally for backward compatibility
    const chartInstances = window.__dashboardChartInstances || (window.__dashboardChartInstances = {});
    window.chartInstances = chartInstances;

    /**
     * Creates a doughnut chart for login status distribution.
     * @param {string} canvasId The ID of the canvas element to render the chart on.
     * @returns {Chart} A new Chart.js chart instance.
     */
    window.createLoginStatusChart = function(canvasId) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return null;
        const ctx = canvas.getContext('2d');
        return new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Success', 'Failure'],
                datasets: [{
                    data: [0, 0],
                    backgroundColor: ['#28a745', '#dc3545'],
                    borderColor: ['#28a745', '#dc3545'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    title: {
                        display: true,
                        text: 'Logins vs Failures (Today)'
                    }
                }
            }
        });
    };

    /**
     * Creates a line chart for activity by hour.
     * @param {string} canvasId The ID of the canvas element to render the chart on.
     * @returns {Chart} A new Chart.js chart instance.
     */
    window.createActivityHourChart = function(canvasId) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return null;
        const ctx = canvas.getContext('2d');

        return new Chart(ctx, {
            type: 'line',
            data: {
                labels: [], // Labels will be loaded from API
                datasets: []
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: {
                                size: 8
                            },
                            boxWidth: 5,
                            boxHeight: 5
                        }
                    },
                    title: {
                        display: true,
                        text: 'User Activity Session by Hour'
                    },
                    datalabels: {
                        display: false
                    }
                }
            }
        });
    };

    /**
     * Updates a chart with new data.
     * @param {Chart} chart - The Chart.js instance to update.
     * @param {Array} newData - The new data array for the first dataset.
     */
    window.updateChartData = function(chart, newData) {
        chart.data.datasets[0].data = newData;
        chart.update();
    };

    /**
     * Creates a doughnut chart for top actions distribution.
     * @param {string} canvasId The ID of the canvas element to render the chart on.
     * @returns {Chart} A new Chart.js chart instance.
     */
    window.createTopActionsChart = function(canvasId) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return null;
        const ctx = canvas.getContext('2d');
        return new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: [
                        '#4361ee', // primary
                        '#3f37c9', // secondary
                        '#4cc9f0', // success
                        '#f72585', // danger
                        '#f8961e', // warning
                        '#4895ef', // info
                    ],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    title: {
                        display: true,
                        text: 'Top Actions in Range'
                    }
                }
            }
        });
    };

    /**
     * Updates a pie or doughnut chart with new labels and data.
     * @param {Chart} chart - The Chart.js instance to update.
     * @param {Array} newLabels - The new labels for the chart.
     * @param {Array} newData - The new data array for the first dataset.
     */
    window.updatePieChartData = function(chart, newLabels, newData) {
        chart.data.labels = newLabels;
        chart.data.datasets[0].data = newData;
        chart.update();
    };

    /**
     * Updates a line chart with new labels and data.
     * @param {Chart} chart - The Chart.js instance to update.
     * @param {Array} newLabels - The new labels for the chart.
     * @param {Array} newData - The new data array for the first dataset.
     */
    window.updateLineChartData = function(chart, newLabels, newData) {
        chart.data.labels = newLabels;
        chart.data.datasets[0].data = newData;
        chart.update();
    };

    window.createDoughnutChart = function(canvasId, titleText) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return null;
        const ctx = canvas.getContext('2d');
        return new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: [],
                    borderColor: [],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 8
                            },
                            boxWidth: 6,
                            boxHeight: 6
                        }
                    },
                    title: {
                        display: true,
                        text: titleText
                    }
                }
            }
        });
    };

    window.updateDoughnutChart = function(chart, labels, data, backgroundColors, borderColors) {
        chart.data.labels = labels;
        chart.data.datasets[0].data = data;
        chart.data.datasets[0].backgroundColor = backgroundColors;
        chart.data.datasets[0].borderColor = borderColors;
        chart.update();
    };

    window.destroyDashboardCharts = function() {
        Object.keys(chartInstances).forEach((chartId) => {
            const chart = chartInstances[chartId];
            if (chart && typeof chart.destroy === 'function') {
                try {
                    chart.destroy();
                } catch (error) {
                    console.error(`Failed to destroy chart instance: ${chartId}`, error);
                }
            }
            delete chartInstances[chartId];
        });
    };

    window.renderChart = function(ctx, type, data, options) {
        const canvas = ctx.canvas;
        const chartId = canvas.id;
        
        // Safety: Check if Chart.js already has an instance on this canvas that we don't track
        const existingInstance = Chart.getChart(canvas);
        if (existingInstance && !chartInstances[chartId]) {
            existingInstance.destroy();
        }

        const trackedChart = chartInstances[chartId];

        if (trackedChart) {
            if (trackedChart.config.type !== type) {
                trackedChart.destroy();
                ensureDatalabelsDisabled(options);
                chartInstances[chartId] = new Chart(ctx, { type, data, options });
                return chartInstances[chartId];
            }

            ensureDatalabelsDisabled(options);
            trackedChart.data = data;
            const noAnim = options && options.animation && options.animation.duration === 0;
            if (noAnim) {
                trackedChart.options.animation = { duration: 0 };
                trackedChart.update('none');
            } else {
                trackedChart.options = options;
                trackedChart.update();
            }
            return trackedChart;
        }

        function ensureDatalabelsDisabled(opts) {
            if (!opts.plugins) opts.plugins = {};
            if (opts.plugins.datalabels === undefined) {
                opts.plugins.datalabels = { display: false };
            }
        }

        ensureDatalabelsDisabled(options);

        chartInstances[chartId] = new Chart(ctx, { type, data, options });
        return chartInstances[chartId];
    };

    window.renderOverviewChart = function(ctx, data) {
        const chartData = {
            labels: Object.keys(data),
            datasets: [{
                data: Object.values(data),
                backgroundColor: ['#28a745', '#dc3545', '#ffc107', '#17a2b8']
            }]
        };
        const options = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } };
        window.renderChart(ctx, 'doughnut', chartData, options);
    };

    window.renderActivityHourChart = function(ctx, data) {
        const allHours = Array.from({ length: 24 }, (_, i) => i);
        const datasets = Object.keys(data).map(user => ({
            label: user,
            data: data[user],
            borderColor: window.getRandomColor ? window.getRandomColor() : '#888',
            fill: false,
            tension: 0.4
        }));

        const chartData = { labels: allHours, datasets };
        const options = { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } }, plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 6, font: { size: 10 } } } } };
        window.renderChart(ctx, 'line', chartData, options);
    };

    window.renderTopActionsChart = function(ctx, data) {
        const chartData = {
            labels: Object.keys(data),
            datasets: [{
                label: 'Top 5 Actions',
                data: Object.values(data),
                backgroundColor: Object.keys(data).map(() => window.getRandomColor ? window.getRandomColor() : '#888')
            }]
        };
        const options = { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } } };
        window.renderChart(ctx, 'bar', chartData, options);
    };

    window.renderOverviewStatsChart = function(ctx, data, isInitialLoad) {
        const chartData = {
            labels: Object.keys(data),
            datasets: [{
                data: Object.values(data),
                backgroundColor: ['#36A2EB', '#FFCE56', '#FF6384', '#4BC0C0'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        };
        const options = {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '50%',
            circumference: 180,
            rotation: -90,
            plugins: {
                legend: { display: false },
                tooltip: { enabled: true }
            },
            animation: isInitialLoad ? { duration: 1000, easing: 'easeOutQuart' } : { duration: 0 }
        };
        window.renderChart(ctx, 'doughnut', chartData, options);
    };

    window.renderUserActivityTrackingChart = function(ctx, data, isInitialLoad) {
        const COLORS = ['#2563EB','#DC2626','#F59E0B','#059669','#7C3AED','#EA580C','#0891B2','#BE123C','#65A30D','#9333EA','#0D9488','#D97706'];
        const userTotals = (data.users || []).map(user => {
            const actions = data.data?.[user] || {};
            const total = Object.values(actions).reduce((s, v) => s + (typeof v === 'number' ? v : 0), 0);
            return { user, total };
        }).sort((a, b) => b.total - a.total);
        const chartData = {
            labels: userTotals.map(d => d.user),
            datasets: [{
                label: 'User Activity Tracking',
                data: userTotals.map(d => d.total),
                backgroundColor: userTotals.map((_, i) => COLORS[i % COLORS.length]),
                borderWidth: 0,
                borderRadius: 6,
                barThickness: 30
            }]
        };
        const options = {
            responsive: true, maintainAspectRatio: false,
            indexAxis: 'y',
            scales: {
                x: { beginAtZero: true, grid: { display: false }, ticks: { font: { size: 10 } } },
                y: { ticks: { autoSkip: false, font: { size: 11 } } }
            },
            plugins: {
                legend: { display: false },
                title: { display: false },
                datalabels: {
                    anchor: 'end',
                    align: 'end',
                    color: '#374151',
                    font: { weight: 'bold', size: 11 },
                    offset: 4
                }
            },
            animation: isInitialLoad ? { duration: 1000, easing: 'easeOutQuart' } : { duration: 0 }
        };
        window.renderChart(ctx, 'bar', chartData, options);
    };

    function toAmPm(label) {
        const m = /^(\d{1,2}):(\d{2})$/.exec(String(label));
        if (!m) return label;
        let h = parseInt(m[1], 10);
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12;
        if (h === 0) h = 12;
        return `${h}:${m[2]} ${ampm}`;
    }

    function ensureGuestTooltipEl() {
        let tipEl = document.getElementById('guest-chart-tooltip');
        if (!tipEl) {
            tipEl = document.createElement('div');
            tipEl.id = 'guest-chart-tooltip';
            tipEl.style.cssText = 'position:fixed; display:none; z-index:10050; background:#1e293b; color:#f8fafc; ' +
                'border:1px solid rgba(148,163,184,0.25); border-radius:8px; padding:8px 10px; font-size:11px; ' +
                'line-height:1.5; max-width:420px; max-height:320px; overflow-y:auto; box-shadow:0 6px 18px rgba(0,0,0,0.35); ' +
                'pointer-events:none; white-space:normal;';
            document.body.appendChild(tipEl);
        }
        return tipEl;
    }

    function escapeHtmlStr(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
    }

    function guestTooltipExternal(context) {
        const tipEl = document.getElementById('guest-chart-tooltip');
        if (!tipEl) return;
        const tooltip = context.tooltip;
        if (!tooltip || !tooltip.opacity || !tooltip.dataPoints || !tooltip.dataPoints.length) {
            tipEl.style.display = 'none';
            return;
        }
        const chart = context.chart;
        const labels = chart._guestLabels || (chart.data.labels || []);
        // Prefer the exact hovered line captured in onHover; fall back to the
        // nearest datapoint only when no hover match is available.
        let item = tooltip.dataPoints[0];
        const hov = chart._guestHover;
        if (hov) {
            const match = tooltip.dataPoints.find(dp => dp.datasetIndex === hov.ds);
            if (match) item = match;
        }
        const user = item.dataset.label;
        const bucketLabel = labels[item.dataIndex] || '';
        const failedCount = item.parsed.y || 0;
        // Only the attempts that actually fall inside the hovered bucket (hour/day).
        const userAttempts = (item.dataset._userAttempts || [])
            .filter(a => a._bucket === item.dataIndex)
            .slice(0, 20);

        let html = `<div style="font-weight:700;margin-bottom:4px;">${escapeHtmlStr(user)} — ${escapeHtmlStr(bucketLabel)}</div>`;
        html += `<div style="opacity:0.85;margin-bottom:6px;">Failed attempts: <b>${failedCount}</b></div>`;

        if (userAttempts.length) {
            html += '<div style="border-top:1px solid rgba(148,163,184,0.25);padding-top:6px;">Attempts:</div>';
            userAttempts.forEach(a => {
                const attemptLabel = (a.details && String(a.details).toLowerCase().includes('rate')) ? 'Rate Limited'
                    : (a.details && String(a.details).toLowerCase().includes('locked')) ? 'Locked Out' : 'Invalid Credentials';
                let ts = String(a.timestamp || '').slice(0, 19);
                const parts = ts.split(' ');
                if (parts.length >= 2) {
                    const tm = /^(\d{1,2}):(\d{2})/.exec(parts[parts.length - 1]);
                    if (tm) {
                        let h = parseInt(tm[1], 10);
                        const ampm = h >= 12 ? 'PM' : 'AM';
                        h = h % 12; if (h === 0) h = 12;
                        parts[parts.length - 1] = `${h}:${tm[2]} ${ampm}`;
                        ts = parts.join(' ');
                    }
                }
                html += `<div style="display:flex;gap:6px;padding:2px 0;border-bottom:1px dashed rgba(148,163,184,0.15);">
                    <span style="opacity:0.75;flex:0 0 auto;">${escapeHtmlStr(ts)}</span>
                    <span style="color:#c7d2fe;flex:0 0 auto;">${escapeHtmlStr(a.username || '?')}</span>
                    <span style="color:#fca5a5;flex:0 0 auto;">${escapeHtmlStr(attemptLabel)}</span>
                </div>`;
            });
            if (userAttempts.length >= 20) html += '<div style="opacity:0.7;padding-top:4px;">… and more</div>';
        }

        tipEl.innerHTML = html;
        tipEl.style.display = 'block';

        const canvasRect = context.chart.canvas.getBoundingClientRect();
        let left = tooltip.caretX !== undefined ? canvasRect.left + tooltip.caretX : tooltip.x;
        let top = tooltip.caretY !== undefined ? canvasRect.top + tooltip.caretY : tooltip.y;
        const pad = 14;
        if (left + tipEl.offsetWidth + pad > window.innerWidth) {
            left = left - tipEl.offsetWidth - pad;
        } else {
            left = left + pad;
        }
        if (top + tipEl.offsetHeight + pad > window.innerHeight) {
            top = window.innerHeight - tipEl.offsetHeight - pad;
        } else {
            top = top + pad;
        }
        tipEl.style.left = Math.max(8, left) + 'px';
        tipEl.style.top = Math.max(8, top) + 'px';
    }

    window.renderGuestFailureChart = function(ctx, labels, userHourlyData, attempts, isInitialLoad) {
        const displayLabels = (Array.isArray(labels) && labels.length ? labels : Array.from({ length: 24 }, (_, i) => `${String(i).padStart(2, '0')}:00`))
            .map(toAmPm);

        const users = Object.keys(userHourlyData || {}).sort((a, b) => {
            const sumA = (userHourlyData[a] || []).reduce((s, v) => s + (v || 0), 0);
            const sumB = (userHourlyData[b] || []).reduce((s, v) => s + (v || 0), 0);
            return sumB - sumA;
        });

        const lineColors = [
            '#E6194B', '#3CB44B', '#4363D8', '#F58231', '#911EB4', '#46F0F0', '#F032E6', '#008080',
            '#E6BEFF', '#9A6324', '#800000', '#AAFFC3', '#808000', '#FFD8B1', '#000075', '#A9A9A9'
        ];

        // Stable per-username color (hash) so a user keeps the same line color across
        // filter switches — the sort order changes but the color must not.
        function guestUserColor(user) {
            let h = 0;
            const s = String(user);
            for (let i = 0; i < s.length; i++) {
                h = ((h << 5) - h) + s.charCodeAt(i);
                h |= 0;
            }
            return lineColors[Math.abs(h) % lineColors.length];
        }

        const attemptsByKey = {};
        (attempts || []).forEach(a => {
            const k = (a && a.ip && a.ip !== 'N/A') ? String(a.ip) : 'Unknown IP';
            if (!attemptsByKey[k]) attemptsByKey[k] = [];
            attemptsByKey[k].push(a);
        });

        const datasets = users.map((user) => ({
            label: user,
            data: Array.from({ length: displayLabels.length }, (_, i) =>
                (userHourlyData[user] && typeof userHourlyData[user][i] === 'number') ? userHourlyData[user][i] : 0
            ),
            borderColor: guestUserColor(user),
            backgroundColor: guestUserColor(user) + '22',
            borderWidth: 2,
            pointRadius: (c) => (c.raw > 0 ? 3.5 : 0),
            pointHoverRadius: (c) => (c.raw > 0 ? 5 : 0),
            pointHitRadius: (c) => (c.raw > 0 ? 10 : 0),
            tension: 0.3,
            fill: false,
            spanGaps: true,
            _userAttempts: attemptsByKey[user] || []
        }));

        const chartData = { labels: displayLabels, datasets };

        ensureGuestTooltipEl();

        const existing = (window.chartInstances || {})['guestFailureChart'];
        if (existing && existing.config.type === 'line') {
            existing._guestLabels = displayLabels;
            existing.data.labels = displayLabels;
            existing.data.datasets = datasets;
            existing.options.plugins.legend.display = users.length > 1;
            existing.options.animation = { duration: 0 };
            existing.update('none');
            return existing;
        }

        const options = {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'nearest', intersect: true },
            onHover: function(event, chartElement, chart) {
                const target = event.native && event.native.target;
                if (target) target.style.cursor = chartElement && chartElement.length ? 'pointer' : 'default';
                // Remember the exact hovered line so the tooltip shows ONLY that
                // dataset at that bucket (nearest-mode can otherwise pick a
                // neighbouring line when points sit close together).
                chart._guestHover = (chartElement && chartElement.length)
                    ? { ds: chartElement[0].datasetIndex, idx: chartElement[0].index }
                    : null;
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 9 }, maxRotation: 45, minRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
                y: { beginAtZero: true, grid: { display: true }, ticks: { font: { size: 9 }, precision: 0 } }
            },
            plugins: {
                legend: { display: users.length > 1, position: 'bottom', labels: { font: { size: 8 }, boxWidth: 12, boxHeight: 2, padding: 6 } },
                title: { display: true, text: 'Failed Login Attempts by Source IP', font: { size: 11 }, padding: { bottom: 4 } },
                datalabels: { display: false },
                tooltip: {
                    enabled: false,
                    external: guestTooltipExternal
                }
            },
            animation: isInitialLoad ? { duration: 1000, easing: 'easeOutQuart' } : { duration: 0 }
        };
        window.renderChart(ctx, 'line', chartData, options);
    };

    window.createHtmlLegend = function(chart, chartType) {
        const legendContainer = document.createElement('div');
        legendContainer.style.display = 'flex';
        legendContainer.style.flexWrap = 'wrap';
        legendContainer.style.justifyContent = 'center';
        legendContainer.style.marginTop = '10px';

        let legendItems = [];

        if (chartType === 'doughnut') {
            legendItems = chart.data.labels.map((label, i) => {
                const color = chart.data.datasets[0].backgroundColor[i];
                const item = document.createElement('div');
                item.style.display = 'flex';
                item.style.alignItems = 'center';
                item.style.marginRight = '10px';
                item.style.fontSize = '10px';

                const box = document.createElement('span');
                box.style.display = 'inline-block';
                box.style.width = '10px';
                box.style.height = '10px';
                box.style.backgroundColor = color;
                box.style.marginRight = '5px';

                const text = document.createElement('span');
                text.innerText = label;

                item.appendChild(box);
                item.appendChild(text);
                return item;
            });
        } else if (chartType === 'bar') {
            legendItems = chart.data.datasets.map((dataset, i) => {
                const color = dataset.backgroundColor;
                const item = document.createElement('div');
                item.style.display = 'flex';
                item.style.alignItems = 'center';
                item.style.marginRight = '10px';
                item.style.fontSize = '10px';

                const box = document.createElement('span');
                box.style.display = 'inline-block';
                box.style.width = '10px';
                box.style.height = '10px';
                box.style.backgroundColor = color;
                box.style.marginRight = '5px';

                const text = document.createElement('span');
                text.innerText = dataset.label;

                item.appendChild(box);
                item.appendChild(text);
                return item;
            });
        }

        legendItems.forEach(item => {
            legendContainer.appendChild(item);
        });

        return legendContainer;
    };

    window.getRandomColor = function() {
        const letters = '0123456789ABCDEF';
        let color = '#';
        for (let i = 0; i < 6; i++) {
            color += letters[Math.floor(Math.random() * 16)];
        }
        return color;
    };

})();
