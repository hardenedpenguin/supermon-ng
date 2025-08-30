<?php
/**
 * Supermon-ng Web Error Log Viewer
 * 
 * Provides a web-based interface for viewing web server error logs with advanced parsing.
 * Allows authenticated users to view and analyze web server error messages through
 * a structured table interface with regex-based log parsing.
 * 
 * Features:
 * - User authentication and authorization (WERRUSER permission required)
 * - Advanced regex-based log parsing for structured display
 * - Log file validation and security checks
 * - Tabular display with columns: Line, Timestamp, Level, Client, Details
 * - Support for both module-based and standard error log formats
 * - Safe log file reading with comprehensive error handling
 * - Formatted log display with proper escaping
 * 
 * Security: Requires WERRUSER permission and proper log file configuration
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("user_files/global.inc");
include("includes/common.inc");
include("authusers.php");
include("includes/weberrlog/weberrlog-controller.inc");

// Run the web error log system
runWeberrlog();
