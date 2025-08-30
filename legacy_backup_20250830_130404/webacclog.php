<?php
/**
 * Supermon-ng Web Access Log Viewer
 * 
 * Provides a web-based interface for viewing web server access logs with secure access.
 * Allows authenticated users to view web server access logs through a secure interface
 * with fallback methods for file access and comprehensive security validation.
 * 
 * Features:
 * - User authentication and authorization (WLOGUSER permission required)
 * - Secure file path validation with whitelist approach
 * - Fallback methods for log file access (direct read + sudo)
 * - Safe command execution with proper escaping
 * - Tabular display with line numbers and log entries
 * - Last 100 lines display with reverse chronological order
 * - Comprehensive error handling and user feedback
 * 
 * Security: Requires WLOGUSER permission and secure file path validation
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("includes/security.inc");
include("user_files/global.inc");
include("includes/common.inc");
include("authusers.php");
include("authini.php");
include("includes/webacclog/webacclog-controller.inc");

// Run the web access log system
runWebacclog();
