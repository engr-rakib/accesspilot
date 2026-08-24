

window.destroyLegacyDashboardCharts = function() {
    if (typeof Chart === 'undefined' || typeof Chart.getChart !== 'function') {
        return;
    }

    document.querySelectorAll('canvas').forEach((canvas) => {
        const chart = Chart.getChart(canvas);
        if (chart) {
            try {
                chart.destroy();
            } catch (error) {
                console.error(`Failed to destroy legacy chart: ${canvas.id || 'unknown-canvas'}`, error);
            }
        }
    });

    if (window.weeklyLogsChart && typeof window.weeklyLogsChart.destroy === 'function') {
        try {
            window.weeklyLogsChart.destroy();
        } catch (error) {
            console.error('Failed to destroy weeklyLogsChart', error);
        }
    }
    window.weeklyLogsChart = null;
};

function getThemeCssVar(name, fallback) {
    const bodyValue = getComputedStyle(document.body).getPropertyValue(name).trim();
    if (bodyValue) return bodyValue;
    const rootValue = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return rootValue || fallback;
}

function getDashboardChartTextColor() {
    return getThemeCssVar('--text-color', '#333');
}

function getDashboardChartGridColor() {
    return document.body.classList.contains('theme-matte-black')
        ? 'rgba(215, 251, 255, 0.08)'
        : 'rgba(15, 23, 42, 0.08)';
}

function getDashboardChartLabelColor() {
    return document.body.classList.contains('theme-matte-black') ? '#dffcff' : '#ffffff';
}

window.operationColors = {
    // Unique Creation Colors
    'CREATE': '#10b981',
    'C_OU': '#22c55e',
    'C_GRP': '#059669',
    'CREATE OU': '#22c55e',
    'CREATE GRP': '#059669',
    'CREATE_OU': '#22c55e',
    'CREATE_GROUP': '#059669',
    'M_CREATE' : '#0ea5e9',
    'ADDED GRP' : '#10b981',
    'ADD_GRP_MMBR' : '#3db3e2',
    'ADD_GROP_MMBR' : '#3db3e2', // Include typo variation

    // Unique Removal Colors
    'DELETE': '#ef4444',
    'D_OU': '#f43f5e',
    'D_GRP': '#be123c',
    'DELETE OU': '#f43f5e',
    'DELETE GRP': '#be123c',
    'DELETE_OU': '#f43f5e',
    'DELETE_GROUP': '#be123c',
    'DISABLE': '#991b1b',
    'DSBL_ATO': '#b91c1c',
    'MV_ATO': '#6366f1',

    // Unique Modification Colors
    'MODIFY': '#eab308',
    'G_UPD': '#3b82f6',
    'GRP UPDATE': '#3b82f6',
    'MEMBER_UPDATE': '#3b82f6',
    'USERMODIFY': '#ca8a04',
    
    // Technical & Status Colors
    'STS_CHK': '#6366f1',
    'HRMS_INC': '#4f46e5',
    'LOGONID': '#a855f7',

    // Security & Recovery
    'ENABLE': '#84cc16',
    'UNLOCK': '#14b8a6',
    'ENBL_ATO': '#15803d',

    // Resets
    'U&RESET': '#f97316',
    'U & RESET': '#ea580c',
    'RESET': '#f97316',
    'PASSRESET': '#f97316',
    'RSET_PASSWD': '#f97316',

    // Discovery & Info
    'INFO': '#64748b',
    'USERINFO': '#475569',
    'OU_USERS': '#1e293b',
    'GRP_USERS': '#334155',

    // Exchange Mailbox Colors
    'MBX_ENABLE': '#0078d4',
    'MBX_DISABLE': '#d9534f',
    'MBX_USER_CREATE': '#005a9e',
    'MBX_SHARED': '#0099bc',
    'MBX_ROOM': '#00bc70',
    'MBX_EQUIP': '#7fba00',
    'MBX_QUOTA': '#ff8c00',
    'MBX_FWD': '#8c6ff7',
    'MBX_PRI_SMTP': '#e81123',
    'MBX_ADD_ADDR': '#107c10',
    'MBX_REM_ADDR': '#d13438',
    'MBX_FULL_ACCESS': '#0078d4',
    'MBX_REM_FULL_ACCESS': '#d9534f',
    'MBX_SEND_AS': '#4a90e2',
    'MBX_REM_SEND_AS': '#d9534f',
    'MBX_LIT_HOLD': '#f9a825',
    'MBX_HID_GAL': '#607d8b',
    'MBX_UPD_PROFILE': '#039be5',
    'MBX_OOF': '#26a69a',
    'MBX_MOVE': '#5c6bc0',
    'MBX_ARCH_ON': '#66bb6a',
    'MBX_ARCH_OFF': '#ef5350',
    'MBX_ARCH_GET': '#7e57c2',
    'MBX_MAIL_TIP': '#42a5f5',
    'MBX_CAL_PERM': '#ab47bc',
    'MBX_REM_CAL_PERM': '#ec407a',
    'MBX_RESTORE': '#ff7043',

    // Exchange Group Colors
    'GRP_CREATE': '#009688',
    'GRP_ADD_MEM': '#26a69a',
    'GRP_REM_MEM': '#e57373',
    'GRP_DELETE': '#c62828',
    'GRP_SEARCH': '#546e7a',
    'GRP_MEMBERS': '#78909c',

    // Exchange Settings
    'SETTINGS': '#78909c'
};

// Standard Professional Status Colors
window.statusColors = {
    'SUCCESS': '#249b2e',
    'FAILED': '#c02a43',
    'SKIPPED': '#ac3fac',
    'TRIGGERED': '#e98503',
    'NOT FOUND': '#ff3300',
    'WARNING': '#1dc4ab',
    'UNKNOWN': '#6c757d'
};

// Standardized options for all doughnut charts
function getStandardDoughnutOptions(title, showDataLabels = false) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        devicePixelRatio: 2, // For sharpness
        animation: {
            duration: 1200,
            easing: 'easeOutQuart'
        },
        plugins: {
            legend: {
                display: true,
                position: 'bottom',
                align: 'center',
                labels: {
                    color: getDashboardChartTextColor(),
                    boxWidth: 8,
                    font: {
                        size: 9
                    },
                    padding: 5
                }
            },
            title: {
                display: false // Title is now in the card-header, so we hide it here
            },
            datalabels: {
                display: showDataLabels,
                color: 'white',
                font: {
                    weight: 'bold',
                    size: 10
                },
                formatter: (value, ctx) => {
                    if (value === 0) return '';
                    let sum = ctx.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                    let percentage = (value * 100 / sum);
                    if (percentage < 5) return ''; // Hide label if it's too small
                    return percentage.toFixed(0) + "%";
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Count: ' + context.formattedValue;
                    }
                }
            }
        }
    };
}


// Centralized safe rendering helper for dashboard/chart_renderer.js
function safeRenderChart(ctx, type, data, options) {
    if (!ctx) return null;
    
    // Safety: Check if Chart.js already has an instance on this canvas
    const existingInstance = Chart.getChart(ctx);
    if (existingInstance) {
        existingInstance.destroy();
    }

    // Default options to disable datalabels if they cause issues
    if (!options.plugins) options.plugins = {};
    if (options.plugins.datalabels === undefined) {
        options.plugins.datalabels = { display: false };
    }

    return new Chart(ctx, { type, data, options });
}

// Function to render/update a doughnut chart
function renderDoughnutChart(canvasId, labels, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    const backgroundColors = labels.map(label => operationColors[label.toUpperCase()] || '#CCCCCC');

    const chartData = {
        labels: labels,
        datasets: [{
            label: '# of Actions',
            data: data,
            backgroundColor: backgroundColors,
            borderColor: '#fff',
            borderWidth: 2
        }]
    };

    const options = getStandardDoughnutOptions("Today's Actions", false);
    return safeRenderChart(ctx, 'doughnut', chartData, options);
}

// Function to render/update the Weekly Logs (Stacked Bar) chart
window.weeklyLogsChart = null; 

function renderWeeklyLogsChart(canvasId, dateData, actionNames, animate = true) {
    const weeklyLogsCtx = document.getElementById(canvasId);
    if (!weeklyLogsCtx) return;

    // Convert date keys (YYYY-MM-DD) to day labels (e.g., 'Sun')
    const dateLabels = Object.keys(dateData).sort(); 
    const dayLabels = dateLabels.map(dateStr => {
        const parts = dateStr.split('-');
        const dateObj = new Date(parts[0], parts[1] - 1, parts[2]);
        const dayIndex = dateObj.getDay();
        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        return days[dayIndex];
    });

    const datasets = actionNames.map(actionName => {
        const data = dateLabels.map(date => dateData[date][actionName] || 0);
        return {
            label: actionName,
            data: data,
            backgroundColor: window.operationColors[actionName.toUpperCase()] || '#808080',
        };
    });

    const chartData = {
        labels: dayLabels,
        datasets: datasets
    };

    const options = {
        responsive: true,
        maintainAspectRatio: false,
        devicePixelRatio: 2,
        animation: {
            duration: animate ? 1200 : 0,
            easing: 'easeOutQuart'
        },
        plugins: {
            legend: {
                position: 'bottom',
                align: 'center',
                labels: {
                    color: getDashboardChartTextColor(),
                    boxWidth: 8,
                    font: { size: 9, style: 'normal' },
                    padding: 4
                }
            },
            tooltip: {
                mode: 'index',
                intersect: false,
            },
            datalabels: {
                color: 'white',
                font: {
                    weight: 'bold',
                    size: 10
                },
                formatter: (value) => {
                    return value > 1 ? value : '';
                }
            }
        },
        scales: {
            x: {
                stacked: true,
                ticks: {
                    font: { size: 9, style: 'normal' },
                    color: getDashboardChartTextColor(),
                },
                grid: { display: false }
            },
            y: {
                stacked: true,
                ticks: {
                    font: { size: 9, style: 'normal' },
                    color: getDashboardChartTextColor(),
                }
            }
        },
        datasets: {
            bar: {
                barPercentage: 0.95,
                categoryPercentage: 0.9,
            }
        }
    };

    window.weeklyLogsChart = safeRenderChart(weeklyLogsCtx, 'bar', chartData, options);
    return window.weeklyLogsChart;
}





function getCategoryColor(category, returnAll = false) {
    const colors = {
        'disable': '#d62728',
        'enable': '#2ca02c',
        'newUser': '#1f77b4',
        'passRest': '#ff7f0e',
        'unlocked': '#9467bd'
    };
    if (returnAll) {
        return colors;
    }
    return colors[category] || '#CCCCCC';
}


// Function to render/update the monthly activity doughnut chart
function renderMonthlyChart(labels, data, canvasId = 'monthlyChart', customBackgroundColors = null, animate = true) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    const now = new Date();
    const monthNames = ["January", "February", "March", "April", "May", "June",
      "July", "August", "September", "October", "November", "December"
    ];
    const currentMonthName = monthNames[now.getMonth()];
    const currentYear = now.getFullYear();
    const chartTitle = `Monthly logs - ${currentMonthName} ${currentYear}`;

    const backgroundColors = customBackgroundColors || labels.map(label => operationColors[label.toUpperCase()] || '#CCCCCC');

    const chartData = {
        labels: labels,
        datasets: [{
            label: '# of Actions',
            data: data,
            backgroundColor: backgroundColors,
            borderColor: '#fff',
            borderWidth: 2
        }]
    };

    const options = getStandardDoughnutOptions(chartTitle, false);
    options.animation = {
        duration: animate ? 900 : 0,
        easing: 'easeOutQuart'
    };

    return safeRenderChart(ctx, 'doughnut', chartData, options);
}

function renderTodayLogChart(labels, data, canvasId = 'todayLogChart', customBackgroundColors = null, animate = true) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    const backgroundColors = customBackgroundColors || labels.map(label => window.operationColors[label.toUpperCase()] || '#CCCCCC');

    const chartData = {
        labels: labels,
        datasets: [{
            label: 'Today\'s Log Breakdown',
            data: data,
            backgroundColor: backgroundColors,
            borderColor: '#fff',
            borderWidth: 2
        }]
    };

    const options = getStandardDoughnutOptions("Today's Log", false);
    options.animation = {
        duration: animate ? 900 : 0,
        easing: 'easeOutQuart'
    };

    return safeRenderChart(ctx, 'doughnut', chartData, options);
}

// Function to render/update the Status Breakdown chart
function renderStatusBreakdownChart(canvasId, historicalStatusData, allDetailedLogs, timePeriod, animate = true) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    let chartInstance = Chart.getChart(ctx);

    const labels = Object.keys(historicalStatusData).sort(); // These will be dates or hours
    const statusTypes = Object.keys(statusColors);

    const datasets = statusTypes.map(statusType => {
        return {
            label: statusType,
            data: labels.map(label => historicalStatusData[label][statusType] || 0),
            borderColor: statusColors[statusType],
            backgroundColor: statusColors[statusType],
            fill: false,
            tension: 0.4,
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 5
        };
    });

    const options = {
        responsive: true,
        maintainAspectRatio: false,
        devicePixelRatio: 2,
        animation: {
            duration: animate ? 900 : 0,
            easing: 'easeOutQuart'
        },
        plugins: {
            legend: {
                position: 'bottom',
                align: 'center',
                labels: {
                    color: getDashboardChartTextColor(),
                    boxWidth: 8,
                    font: { size: 9, style: 'normal' },
                    padding: 4
                }
            },
            tooltip: {
                mode: 'nearest',
                intersect: false,
                callbacks: {
                    filter: function(context) {
                        return context.parsed.y !== 0;
                    },
                    title: function(context) {
                        // The title should be the date or hour
                        const label = context[0].label;
                        if (timePeriod === 'today') {
                            return `${label}:00 - ${label}:59`; // Format for hourly
                        }
                        return label; // Format for daily
                    },
                    label: function(context) {
                        let label = context.dataset.label || '';
                        const currentLabel = context.label; // This will be date or hour
                        const status = context.dataset.label;

                        // Adjust logDate comparison based on timePeriod
                        const matchingLogs = allDetailedLogs.filter(log => {
                            const logTimestamp = new Date(log.timestamp);
                            let matches = false;
                            if (timePeriod === 'today') {
                                const logHour = String(logTimestamp.getHours()).padStart(2, '0');
                                matches = logHour === currentLabel && log.status.toUpperCase() === status.toUpperCase();
                            } else {
                                const logDate = logTimestamp.toISOString().slice(0, 10);
                                matches = logDate === currentLabel && log.status.toUpperCase() === status.toUpperCase();
                            }
                            return matches;
                        });

                        let tooltipLines = [];
                        tooltipLines.push(`${label}: ${context.formattedValue} entries`);

                        const maxLogsToShow = 3;
                        matchingLogs.slice(0, maxLogsToShow).forEach(log => {
                            tooltipLines.push(`  - ${log.timestamp} | ${log.performedBy} | ${log.ip}`);
                        });

                        if (matchingLogs.length > maxLogsToShow) {
                            tooltipLines.push(`  ... and ${matchingLogs.length - maxLogsToShow} more.`);
                        }

                        return tooltipLines;
                    }
                }
            },
            datalabels: {
                display: false
            }
        },
        scales: {
            x: {
                title: {
                    display: false,
                    text: (timePeriod === 'today') ? 'Hour' : 'Date' // Dynamic title
                },
                ticks: {
                    font: { size: 9, style: 'normal' },
                    color: getDashboardChartTextColor(),
                    maxRotation: 0,
                    minRotation: 0,
                    callback: function(value, index, ticks) {
                        const label = this.getLabelForValue(value);
                        if (timePeriod === 'today') return label + ':00';
                        const parts = label.split('-');
                        if (parts.length === 3) {
                            const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                            const m = months[parseInt(parts[1], 10) - 1];
                            const d = parseInt(parts[2], 10);
                            return m + ' ' + d;
                        }
                        return label;
                    }
                },
                grid: { display: false }
            },
            y: {
                beginAtZero: true,
                title: {
                    display: false,
                    text: 'Number of Actions'
                },
                ticks: {
                    font: { size: 9, style: 'normal' },
                    color: getDashboardChartTextColor(),
                    precision: 0
                },
                grid: {
                    color: getDashboardChartGridColor()
                }
            }
        }
    };

    const chartData = {
        labels: labels,
        datasets: datasets
    };

    if (chartInstance) {
        chartInstance.data.labels = labels;
        chartInstance.data.datasets = datasets;
        chartInstance.options = options;
        chartInstance.update();
    } else {
        chartInstance = safeRenderChart(ctx, 'line', chartData, options);
    }
    return chartInstance;
}

// Function to render/update the Action Status Breakdown chart
window.renderActionStatusBreakdownChart = function(canvasId, actionStatusBreakdownData, animate = true) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    const actionNames = Object.keys(actionStatusBreakdownData).sort();
    const statusTypes = Object.keys(statusColors); 

    const datasets = statusTypes.map(statusType => {
        return {
            label: statusType,
            data: actionNames.map(actionName => actionStatusBreakdownData[actionName][statusType] || 0),
            backgroundColor: statusColors[statusType],
            borderColor: 'white',
            borderWidth: 1
        };
    });

    const chartData = {
        labels: actionNames,
        datasets: datasets
    };

    const options = {
        indexAxis: 'y', 
        responsive: true,
        maintainAspectRatio: false,
        devicePixelRatio: 2,
        animation: {
            duration: animate ? 900 : 0,
            easing: 'easeOutQuart'
        },
        plugins: {
            legend: {
                position: 'bottom',
                align: 'center',
                labels: {
                    color: getDashboardChartTextColor(),
                    boxWidth: 8,
                    font: { size: 9, style: 'normal' },
                    padding: 4
                }
            },
            tooltip: {
                mode: 'nearest', 
                intersect: true, 
                callbacks: {
                    filter: function(context) { 
                        return context.parsed !== 0;
                    },
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) {
                            label += ': ';
                        }
                        label += context.formattedValue;
                        return label;
                    }
                }
            },
            datalabels: {
                display: true,
                color: getDashboardChartTextColor(),
                anchor: 'end',
                align: 'right',
                offset: 6,
                clamp: true,
                clip: false,
                font: {
                    weight: '500',
                    size: 7
                },
                formatter: function(value, context) {
                    if (!value) return '';
                    const actionLabel = context.chart.data.labels[context.dataIndex] || '';
                    const lastVisibleDataset = context.chart.data.datasets.reduce((lastIndex, dataset, datasetIndex) => {
                        const currentValue = dataset.data[context.dataIndex] || 0;
                        return currentValue > 0 ? datasetIndex : lastIndex;
                    }, -1);
                    return context.datasetIndex === lastVisibleDataset ? actionLabel : '';
                }
            }
        },
        scales: {
            x: {
                stacked: true,
                ticks: {
                    font: { size: 9, style: 'normal' },
                    color: getDashboardChartTextColor(),
                    precision: 0
                },
                grid: { display: false }
            },
            y: {
                stacked: true,
                ticks: {
                    display: false
                },
                grid: {
                    display: false
                }
            }
        },
        datasets: { 
            bar: {
                barPercentage: 0.95,
                categoryPercentage: 0.9,
            }
        }
    };

    return safeRenderChart(ctx, 'bar', chartData, options);
}

function updateCustomLegend(canvasId, labels, data, colors) {
    const canvasElement = document.getElementById(canvasId);
    const chartContainer = canvasElement?.closest('.card');
    if (!chartContainer) {
        return;
    }

    const ulElement = chartContainer.querySelector('ul');
    if (!ulElement) {
        return;
    }

    ulElement.classList.add('custom-legend-list');
    ulElement.innerHTML = '';

    labels.forEach((label, index) => {
        const li = document.createElement('li');
        const color = colors[index];
        const value = data[index];

        li.innerHTML = `
            <span style="background-color: ${color}; width: 10px; height: 10px; display: inline-block; border-radius: 50%; margin-right: 5px;"></span>
            ${label}: ${value}
        `;
        ulElement.appendChild(li);
    });
}
