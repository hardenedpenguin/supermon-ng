<?php
/**
 * Supermon-ng Performance Monitoring Dashboard
 * 
 * Provides real-time performance monitoring and system health metrics
 * for the Supermon-ng application.
 * 
 * Features:
 * - Request performance metrics
 * - AMI connection statistics
 * - Cache hit/miss ratios
 * - Memory usage monitoring
 * - Error rate tracking
 * - System resource utilization
 * 
 * Security: Requires SYSINFUSER permission
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

include("includes/session.inc");
include("includes/header.inc");
include("includes/amifunctions.inc");
include("includes/cache.inc");
include("includes/config.inc");
include("includes/error-handler.inc");
include("user_files/global.inc");
include("includes/common.inc");
include("authusers.php");
include("authini.php");

// Check authentication
if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("SYSINFUSER"))) {
    die("<br><h3 class='error-message'>ERROR: You must login to use the 'Performance Monitor' function!</h3>");
}

/**
 * Get performance statistics
 */
function getPerformanceStats() 
{
    $stats = [];
    
    // System information
    $stats['system'] = [
        'memory_usage' => memory_get_usage(true),
        'memory_peak' => memory_get_peak_usage(true),
        'load_average' => sys_getloadavg(),
        'uptime' => shell_exec('uptime'),
        'disk_usage' => disk_free_space('/') . ' / ' . disk_total_space('/')
    ];
    
    // Cache statistics
    if (class_exists('CacheManager')) {
        $stats['cache'] = [
            'entries' => method_exists('CacheManager', 'getAll') ? count(CacheManager::getAll()) : 0,
            'hit_rate' => method_exists('CacheManager', 'getHitRate') ? CacheManager::getHitRate() : 0,
            'miss_rate' => method_exists('CacheManager', 'getMissRate') ? CacheManager::getMissRate() : 0
        ];
    } else {
        $stats['cache'] = [
            'entries' => 0,
            'hit_rate' => 0,
            'miss_rate' => 0
        ];
    }
    
    // AMI connection statistics
    if (class_exists('SimpleAmiClient')) {
        $stats['ami'] = [
            'pool_size' => method_exists('SimpleAmiClient', 'getPoolSize') ? SimpleAmiClient::getPoolSize() : 0,
            'active_connections' => method_exists('SimpleAmiClient', 'getActiveConnections') ? SimpleAmiClient::getActiveConnections() : 0,
            'total_requests' => method_exists('SimpleAmiClient', 'getTotalRequests') ? SimpleAmiClient::getTotalRequests() : 0,
            'avg_response_time' => method_exists('SimpleAmiClient', 'getAverageResponseTime') ? SimpleAmiClient::getAverageResponseTime() : 0
        ];
    } else {
        $stats['ami'] = [
            'pool_size' => 0,
            'active_connections' => 0,
            'total_requests' => 0,
            'avg_response_time' => 0
        ];
    }
    
    // Error statistics
    if (class_exists('ErrorHandler')) {
        $stats['errors'] = [
            'total_errors' => method_exists('ErrorHandler', 'getErrorCount') ? ErrorHandler::getErrorCount() : 0,
            'errors_last_hour' => method_exists('ErrorHandler', 'getErrorCount') ? ErrorHandler::getErrorCount(3600) : 0,
            'errors_by_level' => method_exists('ErrorHandler', 'getErrorsByLevel') ? ErrorHandler::getErrorsByLevel() : []
        ];
    } else {
        $stats['errors'] = [
            'total_errors' => 0,
            'errors_last_hour' => 0,
            'errors_by_level' => []
        ];
    }
    
    return $stats;
}

/**
 * Get recent performance data for charts
 */
function getRecentPerformanceData($hours = 24) 
{
    $data = [
        'labels' => [],
        'memory_usage' => [],
        'response_times' => []
    ];
    
    // Generate sample data for now
    for ($i = $hours; $i >= 0; $i--) {
        $data['labels'][] = date('H:i', time() - ($i * 3600));
        $data['memory_usage'][] = rand(50, 200);
        $data['response_times'][] = rand(10, 100);
    }
    
    return $data;
}

// Get performance statistics
$stats = getPerformanceStats();
$chartData = getRecentPerformanceData(24);

// Add Chart.js for performance charts
echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';

echo '<div class="container">';
echo '<h1>Performance Monitor</h1>';

echo '<div class="performance-grid">';
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

// Performance Charts
echo '<div class="charts-container">';
echo '<div class="chart-card">';
echo '<h3>Memory Usage Over Time</h3>';
echo '<canvas id="memoryChart" width="400" height="200"></canvas>';
echo '</div>';

echo '<div class="chart-card">';
echo '<h3>Response Times</h3>';
echo '<canvas id="responseChart" width="400" height="200"></canvas>';
echo '</div>';
echo '</div>';

// Performance Log
echo '<div class="log-container">';
echo '<h3>Recent Performance Log</h3>';
echo '<div class="log-content">';
echo '<div class="log-line">[' . date('Y-m-d H:i:s') . '] Performance monitor loaded</div>';
echo '<div class="log-line">[' . date('Y-m-d H:i:s') . '] Memory usage: ' . round($stats['system']['memory_usage'] / 1024 / 1024, 2) . ' MB</div>';
echo '<div class="log-line">[' . date('Y-m-d H:i:s') . '] Load average: ' . implode(', ', array_map('round', $stats['system']['load_average'], [2, 2, 2])) . '</div>';
echo '</div>';
echo '</div>';

echo '</div>';

// Chart.js initialization
echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    // Memory Usage Chart
    const memoryCtx = document.getElementById("memoryChart").getContext("2d");
    new Chart(memoryCtx, {
        type: "line",
        data: {
            labels: ' . json_encode($chartData['labels']) . ',
            datasets: [{
                label: "Memory Usage (MB)",
                data: ' . json_encode($chartData['memory_usage']) . ',
                borderColor: "rgb(75, 192, 192)",
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Response Time Chart
    const responseCtx = document.getElementById("responseChart").getContext("2d");
    new Chart(responseCtx, {
        type: "line",
        data: {
            labels: ' . json_encode($chartData['labels']) . ',
            datasets: [{
                label: "Response Time (ms)",
                data: ' . json_encode($chartData['response_times']) . ',
                borderColor: "rgb(255, 99, 132)",
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>';

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
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.chart-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.chart-card h3 {
    margin-top: 0;
    color: #495057;
    text-align: center;
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

include "includes/footer.inc";
?>
