<?php
/**
 * Supermon-ng CPU Statistics Viewer
 * 
 * Provides a web-based interface for viewing CPU and system statistics.
 * Allows authenticated users to view comprehensive system information including
 * date/time, system info, network interfaces, disk usage, and process status.
 * 
 * Features:
 * - User authentication and authorization (CSTATUSER permission required)
 * - Multiple system command execution for comprehensive statistics
 * - Real-time system information display
 * - Network interface status and configuration
 * - Disk usage and filesystem information
 * - Process and system load information
 * - Formatted output with proper command identification
 * 
 * Security: Requires CSTATUSER permission for system command execution
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("authusers.php");
include("includes/common.inc");
include("includes/cpustats/cpustats-controller.inc");

// Run the CPU statistics system
runCpustats();
