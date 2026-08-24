// coreAdminCharts.js
// This file is intended to contain common chart rendering logic
// that can be reused across different parts of the admin panel.

// Function to render a bar chart
function renderBarChart(canvasId, labels, data, labelText, backgroundColor, borderColor) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: labelText,
                data: data,
                backgroundColor: backgroundColor,
                borderColor: borderColor,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            }
        }
    });
}

// Function to render a pie/doughnut chart
function renderPieDoughnutChart(canvasId, labels, data, labelText, legendPosition = 'right') { // MODIFIED LINE
    const ctx = document.getElementById(canvasId).getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                label: labelText,
                data: data,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)', // Red
                    'rgba(54, 162, 235, 0.8)', // Blue
                    'rgba(255, 206, 86, 0.8)', // Yellow
                    'rgba(75, 192, 192, 0.8)', // Green
                    'rgba(153, 102, 255, 0.8)', // Purple
                    'rgba(255, 159, 64, 0.8)'  // Orange
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: legendPosition, // MODIFIED LINE
                    labels: { // ADDED BLOCK
                        font: {
                            size: 12 // Consistent font size for legends
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed !== null) {
                                label += context.parsed + ' (' + context.dataset.data[context.dataIndex] + '%)';
                            }
                            return label;
                        }
                    }
                },
                datalabels: {
                    color: '#fff',
                    formatter: (value, context) => {
                        const percentage = context.chart.data.datasets[0].data[context.dataIndex];
                        return percentage > 0 ? percentage + '%' : '';
                    }
                }
            }
        },
        plugins: [ChartDataLabels]
    });
}

// Function to render a line chart
function renderLineChart(canvasId, labels, datasets) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets.map(dataset => ({
                ...dataset,
                borderColor: dataset.borderColor || getRandomColor(), // Use provided color or generate random
                backgroundColor: dataset.backgroundColor || 'rgba(0, 0, 0, 0)', // Transparent fill by default
                tension: dataset.tension || 0.1,
                fill: dataset.fill || false
            }))
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            }
        }
    });
}

// Helper function to generate random colors for charts
function getRandomColor() {
    const letters = '0123456789ABCDEF';
    let color = '#';
    for (let i = 0; i < 6; i++) {
        color += letters[Math.floor(Math.random() * 16)];
    }
    return color;
}
