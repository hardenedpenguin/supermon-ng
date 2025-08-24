<?php
/**
 * Performance Monitoring UI Component
 * 
 * Handles the display and presentation of performance data.
 * 
 * @author Supermon-ng Team
 * @version 3.0.1
 */

/**
 * Display performance dashboard
 */
function displayPerformanceDashboard($stats, $chartData) {
    // Add Chart.js for performance charts
    echo '<script src="js/chart.js"></script>';
    
    echo '<div class="container">';
    echo '<h1>Performance Monitor</h1>';
    
    // System Overview Cards
    displaySystemCards($stats);
    
    // Performance Charts
    displayPerformanceCharts($chartData);
    
    // Performance Log
    displayPerformanceLog($stats);
    
    echo '</div>';
    
    // Chart.js initialization
    displayChartScripts($chartData);
    
    // CSS Styles
    displayPerformanceStyles();
}

/**
 * Display system overview cards
 */
function displaySystemCards($stats) {
    echo '<div class="performance-grid">';
    
    // System Resources
    echo '<div class="performance-card">';
    echo '<h3>System Resources</h3>';
    echo '<div class="metric">';
    echo '<span class="label">Memory Usage:</span>';
    echo '<span class="value">' . round($stats['system']['memory_usage'] / 1024 / 1024, 2) . ' MB</span>';
    echo '</div>';
    echo '<div class="metric">';
    echo '<span class="label">Peak Memory:</span>';
    echo '<span class="value">' . round($stats['system']['memory_peak'] / 1024 / 1024, 2) . ' MB</span>';
    echo '</div>';
    echo '<div class="metric">';
    echo '<span class="label">Load Average:</span>';
    echo '<span class="value">' . implode(', ', array_map('round', $stats['system']['load_average'], [2, 2, 2])) . '</span>';
    echo '</div>';
    echo '</div>';
    
    // Cache Performance
    echo '<div class="performance-card">';
    echo '<h3>Cache Performance</h3>';
    if (isset($stats['cache'])) {
        echo '<div class="metric">';
        echo '<span class="label">Cache Entries:</span>';
        echo '<span class="value">' . $stats['cache']['entries'] . '</span>';
        echo '</div>';
        echo '<div class="metric">';
        echo '<span class="label">Hit Rate:</span>';
        echo '<span class="value">' . round($stats['cache']['hit_rate'] * 100, 1) . '%</span>';
        echo '</div>';
        echo '<div class="metric">';
        echo '<span class="label">Miss Rate:</span>';
        echo '<span class="value">' . round($stats['cache']['miss_rate'] * 100, 1) . '%</span>';
        echo '</div>';
    } else {
        echo '<p>Cache statistics not available</p>';
    }
    echo '</div>';
    
    // AMI Connections
    echo '<div class="performance-card">';
    echo '<h3>AMI Connections</h3>';
    if (isset($stats['ami'])) {
        echo '<div class="metric">';
        echo '<span class="label">Pool Size:</span>';
        echo '<span class="value">' . $stats['ami']['pool_size'] . '</span>';
        echo '</div>';
        echo '<div class="metric">';
        echo '<span class="label">Active Connections:</span>';
        echo '<span class="value">' . $stats['ami']['active_connections'] . '</span>';
        echo '</div>';
        echo '<div class="metric">';
        echo '<span class="label">Avg Response Time:</span>';
        echo '<span class="value">' . round($stats['ami']['avg_response_time'], 2) . ' ms</span>';
        echo '</div>';
    } else {
        echo '<p>AMI statistics not available</p>';
    }
    echo '</div>';
    
    // Error Statistics
    echo '<div class="performance-card">';
    echo '<h3>Error Statistics</h3>';
    echo '<div class="metric">';
    echo '<span class="label">Total Errors:</span>';
    echo '<span class="value">' . $stats['errors']['total_errors'] . '</span>';
    echo '</div>';
    echo '<div class="metric">';
    echo '<span class="label">Errors (Last Hour):</span>';
    echo '<span class="value">' . $stats['errors']['errors_last_hour'] . '</span>';
    echo '</div>';
    echo '</div>';
    
    echo '</div>';
}

/**
 * Display performance charts
 */
function displayPerformanceCharts($chartData) {
    echo '<div class="charts-container">';
    
    echo '<div class="chart-card">';
    echo '<h3>Memory Usage Over Time</h3>';
    echo '<div style="position: relative; height: 300px; width: 100%;">';
    echo '<canvas id="memoryChart"></canvas>';
    echo '</div>';
    echo '<div id="memoryData" style="display:none;">' . json_encode($chartData['memory_usage']) . '</div>';
    echo '</div>';
    
    echo '<div class="chart-card">';
    echo '<h3>Response Times</h3>';
    echo '<div style="position: relative; height: 300px; width: 100%;">';
    echo '<canvas id="responseChart"></canvas>';
    echo '</div>';
    echo '<div id="responseData" style="display:none;">' . json_encode($chartData['response_times']) . '</div>';
    echo '</div>';
    
    echo '</div>';
    
    // Debug information
    echo '<div class="debug-info" style="background: #f0f0f0; padding: 10px; margin: 10px 0; font-family: monospace; font-size: 12px;">';
    echo '<strong>Debug Info:</strong><br>';
    echo 'Memory data points: ' . count($chartData['memory_usage']) . '<br>';
    echo 'Response time data points: ' . count($chartData['response_times']) . '<br>';
    echo 'Labels: ' . count($chartData['labels']) . '<br>';
    echo 'Sample memory values: ' . implode(', ', array_slice($chartData['memory_usage'], 0, 5)) . '<br>';
    echo 'Sample response times: ' . implode(', ', array_slice($chartData['response_times'], 0, 5)) . '<br>';
    echo '</div>';
}

/**
 * Display performance log
 */
function displayPerformanceLog($stats) {
    echo '<div class="log-container">';
    echo '<h3>Recent Performance Log</h3>';
    echo '<div class="log-content">';
    echo '<div class="log-line">[' . date('Y-m-d H:i:s') . '] Performance monitor loaded</div>';
    echo '<div class="log-line">[' . date('Y-m-d H:i:s') . '] Memory usage: ' . round($stats['system']['memory_usage'] / 1024 / 1024, 2) . ' MB</div>';
    echo '<div class="log-line">[' . date('Y-m-d H:i:s') . '] Load average: ' . implode(', ', array_map('round', $stats['system']['load_average'], [2, 2, 2])) . '</div>';
    echo '</div>';
    echo '</div>';
}

/**
 * Display chart initialization scripts
 */
function displayChartScripts($chartData) {
    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        console.log("Chart data:", ' . json_encode($chartData) . ');
        
        // Memory Usage Chart
        const memoryCtx = document.getElementById("memoryChart");
        if (memoryCtx) {
            new Chart(memoryCtx.getContext("2d"), {
                type: "line",
                data: {
                    labels: ' . json_encode($chartData['labels']) . ',
                    datasets: [{
                        label: "Memory Usage (MB)",
                        data: ' . json_encode($chartData['memory_usage']) . ',
                        borderColor: "rgb(75, 192, 192)",
                        backgroundColor: "rgba(75, 192, 192, 0.1)",
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: "Memory (MB)",
                                font: {
                                    size: 12
                                }
                            },
                            ticks: {
                                font: {
                                    size: 10
                                }
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: "Time",
                                font: {
                                    size: 12
                                }
                            },
                            ticks: {
                                maxTicksLimit: 12,
                                font: {
                                    size: 10
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            labels: {
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Response Time Chart
        const responseCtx = document.getElementById("responseChart");
        if (responseCtx) {
            new Chart(responseCtx.getContext("2d"), {
                type: "line",
                data: {
                    labels: ' . json_encode($chartData['labels']) . ',
                    datasets: [{
                        label: "Response Time (ms)",
                        data: ' . json_encode($chartData['response_times']) . ',
                        borderColor: "rgb(255, 99, 132)",
                        backgroundColor: "rgba(255, 99, 132, 0.1)",
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: "Response Time (ms)",
                                font: {
                                    size: 12
                                }
                            },
                            ticks: {
                                font: {
                                    size: 10
                                }
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: "Time",
                                font: {
                                    size: 12
                                }
                            },
                            ticks: {
                                maxTicksLimit: 12,
                                font: {
                                    size: 10
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            labels: {
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        }
    });
    </script>';
}

/**
 * Display performance styles
 */
function displayPerformanceStyles() {
    echo '<style>
    .performance-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .performance-card {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .performance-card h3 {
        margin-top: 0;
        color: #495057;
        border-bottom: 2px solid #007bff;
        padding-bottom: 10px;
    }
    
    .metric {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        padding: 5px 0;
    }
    
    .metric .label {
        font-weight: bold;
        color: #6c757d;
    }
    
    .metric .value {
        font-family: monospace;
        color: #495057;
    }
    
    .charts-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .chart-card {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        min-height: 350px;
    }
    
    .chart-card h3 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #495057;
        text-align: center;
        font-size: 16px;
    }
    
    .log-container {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .log-content {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 15px;
        max-height: 400px;
        overflow-y: auto;
        font-family: monospace;
        font-size: 12px;
    }
    
    .log-line {
        margin-bottom: 2px;
        word-wrap: break-word;
    }
    
    @media (max-width: 768px) {
        .performance-grid {
            grid-template-columns: 1fr;
        }
        
        .charts-container {
            grid-template-columns: 1fr;
        }
    }
    </style>';
}
?>
