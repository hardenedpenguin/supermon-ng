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
 * @version 3.0.1
 * @since 1.0.0
 */

// Load bootstrap and required components
include("includes/bootstrap.inc");
include("includes/header.inc");
load_ami_functions();
load_cache_system();
load_auth_system();
load_user_config();

// Check authentication
if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("SYSINFUSER"))) {
    die("<br><h3 class='error-message'>ERROR: You must login to use the 'Performance Monitor' function!</h3>");
}

// Load performance components
include("includes/performance/performance-controller.inc");
include("includes/performance/performance-ui.inc");

// Get performance data
$stats = getPerformanceStats();
$chartData = getRecentPerformanceData(24);

// Debug: Log chart data for troubleshooting
error_log("Performance chart data: " . json_encode($chartData));

// Display the performance dashboard
displayPerformanceDashboard($stats, $chartData);

include "includes/footer.inc";
?>
