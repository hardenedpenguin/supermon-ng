<?php
/**
 * Supermon-ng Asterisk Log Viewer
 * 
 * Provides a web-based interface for viewing Asterisk log files.
 * Allows authenticated users to view Asterisk system messages and events
 * through a secure web interface with proper validation.
 * 
 * Features:
 * - User authentication and authorization (ASTLUSER permission required)
 * - Log file path validation and security checks
 * - Safe log file reading with error handling
 * - Formatted log display with proper escaping
 * - Responsive web interface
 * 
 * Security: Requires ASTLUSER permission
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("user_files/global.inc");
include("includes/common.inc");
include("authusers.php");
include("includes/astlog/astlog-controller.inc");

// Run the Asterisk log system
runAstlog();
