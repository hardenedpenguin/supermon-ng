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
    
    // Performance log analysis
    $stats['performance'] = analyzePerformanceLog();
    
    return $stats;
}

/**
 * Analyze performance log
 */
function analyzePerformanceLog() 
{
    $logFile = '/tmp/supermon-ng-performance.log';
    if (!file_exists($logFile)) {
        return [];
    }
    
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    if (!$lines) {
        return [];
    }
    
    $stats = [
        'total_requests' => 0,
        'avg_response_time' => 0,
        'slow_requests' => 0,
        'memory_usage' => []
    ];
    
    $totalTime = 0;
    $requestCount = 0;
    
    foreach ($lines as $line) {
        if (preg_match('/REQUEST_COMPLETE/', $line)) {
            $stats['total_requests']++;
            $requestCount++;
            
            if (preg_match('/"duration_ms":(\d+\.?\d*)/', $line, $matches)) {
                $duration = (float)$matches[1];
                $totalTime += $duration;
                
                if ($duration > 1000) { // Slow requests > 1 second
                    $stats['slow_requests']++;
                }
            }
            
            if (preg_match('/"memory_peak_mb":(\d+\.?\d*)/', $line, $matches)) {
                $stats['memory_usage'][] = (float)$matches[1];
            }
        }
    }
    
    if ($requestCount > 0) {
        $stats['avg_response_time'] = round($totalTime / $requestCount, 2);
    }
    
    // Calculate memory statistics
    if (!empty($stats['memory_usage'])) {
        $stats['avg_memory'] = round(array_sum($stats['memory_usage']) / count($stats['memory_usage']), 2);
        $stats['max_memory'] = round(max($stats['memory_usage']), 2);
    }
    
    return $stats;
}

/**
 * Get recent performance data for charts
 */
function getRecentPerformanceData($hours = 24) 
{
    $logFile = '/tmp/supermon-ng-performance.log';
    if (!file_exists($logFile)) {
        return [];
    }
    
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    if (!$lines) {
        return [];
    }
    
    $data = [
        'timestamps' => [],
        'response_times' => [],
        'memory_usage' => []
    ];
    
    $cutoff = time() - ($hours * 3600);
    
    foreach ($lines as $line) {
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] REQUEST_COMPLETE/', $line, $matches)) {
            $timestamp = strtotime($matches[1]);
            
            if ($timestamp >= $cutoff) {
                $data['timestamps'][] = date('H:i', $timestamp);
                
                if (preg_match('/"duration_ms":(\d+\.?\d*)/', $line, $matches)) {
                    $data['response_times'][] = (float)$matches[1];
                } else {
                    $data['response_times'][] = 0;
                }
                
                if (preg_match('/"memory_peak_mb":(\d+\.?\d*)/', $line, $matches)) {
                    $data['memory_usage'][] = (float)$matches[1];
                } else {
                    $data['memory_usage'][] = 0;
                }
            }
        }
    }
    
    return $data;
}

// Get performance statistics
$stats = getPerformanceStats();
$chartData = getRecentPerformanceData(24);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Monitor - Supermon-ng</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/tables.css">
    <link rel="stylesheet" href="css/widgets.css">
    <link rel="stylesheet" href="css/responsive.css">
    <?php if (file_exists('css/custom.css')): ?>
        <link rel="stylesheet" href="css/custom.css">
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <h1>Performance Monitor</h1>
        
        <!-- System Overview -->
        <div class="performance-grid">
            <div class="performance-card">
                <h3>System Resources</h3>
                <div class="metric">
                    <span class="label">Memory Usage:</span>
                    <span class="value"><?php echo round($stats['system']['memory_usage'] / 1024 / 1024, 2); ?> MB</span>
                </div>
                <div class="metric">
                    <span class="label">Peak Memory:</span>
                    <span class="value"><?php echo round($stats['system']['memory_peak'] / 1024 / 1024, 2); ?> MB</span>
                </div>
                <div class="metric">
                    <span class="label">Load Average:</span>
                    <span class="value"><?php echo implode(', ', array_map('round', $stats['system']['load_average'], [2, 2, 2])); ?></span>
                </div>
            </div>
            
            <div class="performance-card">
                <h3>Cache Performance</h3>
                <?php if (isset($stats['cache'])): ?>
                    <div class="metric">
                        <span class="label">Cache Entries:</span>
                        <span class="value"><?php echo $stats['cache']['entries']; ?></span>
                    </div>
                    <div class="metric">
                        <span class="label">Hit Rate:</span>
                        <span class="value"><?php echo round($stats['cache']['hit_rate'] * 100, 1); ?>%</span>
                    </div>
                    <div class="metric">
                        <span class="label">Miss Rate:</span>
                        <span class="value"><?php echo round($stats['cache']['miss_rate'] * 100, 1); ?>%</span>
                    </div>
                <?php else: ?>
                    <p>Cache statistics not available</p>
                <?php endif; ?>
            </div>
            
            <div class="performance-card">
                <h3>AMI Connections</h3>
                <?php if (isset($stats['ami'])): ?>
                    <div class="metric">
                        <span class="label">Pool Size:</span>
                        <span class="value"><?php echo $stats['ami']['pool_size']; ?></span>
                    </div>
                    <div class="metric">
                        <span class="label">Active Connections:</span>
                        <span class="value"><?php echo $stats['ami']['active_connections']; ?></span>
                    </div>
                    <div class="metric">
                        <span class="label">Avg Response Time:</span>
                        <span class="value"><?php echo round($stats['ami']['avg_response_time'], 2); ?> ms</span>
                    </div>
                <?php else: ?>
                    <p>AMI statistics not available</p>
                <?php endif; ?>
            </div>
            
            <div class="performance-card">
                <h3>Error Statistics</h3>
                <div class="metric">
                    <span class="label">Total Errors:</span>
                    <span class="value"><?php echo $stats['errors']['total_errors']; ?></span>
                </div>
                <div class="metric">
                    <span class="label">Errors (Last Hour):</span>
                    <span class="value"><?php echo $stats['errors']['errors_last_hour']; ?></span>
                </div>
                <div class="metric">
                    <span class="label">Slow Requests:</span>
                    <span class="value"><?php echo $stats['performance']['slow_requests'] ?? 0; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Performance Charts -->
        <div class="charts-container">
            <div class="chart-card">
                <h3>Response Time Trend (Last 24 Hours)</h3>
                <canvas id="responseTimeChart" width="400" height="200"></canvas>
            </div>
            
            <div class="chart-card">
                <h3>Memory Usage Trend (Last 24 Hours)</h3>
                <canvas id="memoryChart" width="400" height="200"></canvas>
            </div>
        </div>
        
        <!-- Performance Log -->
        <div class="log-container">
            <h3>Recent Performance Log</h3>
            <div class="log-content">
                <?php
                $logFile = '/tmp/supermon-ng-performance.log';
                if (file_exists($logFile)) {
                    $lines = array_slice(file($logFile, FILE_IGNORE_NEW_LINES), -50);
                    foreach (array_reverse($lines) as $line) {
                        echo '<div class="log-line">' . htmlspecialchars($line) . '</div>';
                    }
                } else {
                    echo '<p>No performance log available</p>';
                }
                ?>
            </div>
        </div>
    </div>
    
    <script>
        // Response Time Chart
        const responseTimeCtx = document.getElementById('responseTimeChart').getContext('2d');
        new Chart(responseTimeCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartData['timestamps']); ?>,
                datasets: [{
                    label: 'Response Time (ms)',
                    data: <?php echo json_encode($chartData['response_times']); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
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
        
        // Memory Usage Chart
        const memoryCtx = document.getElementById('memoryChart').getContext('2d');
        new Chart(memoryCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartData['timestamps']); ?>,
                datasets: [{
                    label: 'Memory Usage (MB)',
                    data: <?php echo json_encode($chartData['memory_usage']); ?>,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
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
        
        // Auto-refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
    
    <style>
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
    </style>
</body>
</html>
