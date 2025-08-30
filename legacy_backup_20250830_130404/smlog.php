<?php
/**
 * Supermon-ng Supermon Log Viewer
 * 
 * Provides a web-based interface for viewing Supermon login/logout log files.
 * Allows authenticated users to view user authentication events and system
 * activity through a secure web interface with proper validation.
 * 
 * Features:
 * - User authentication and authorization (SMLOGUSER permission required)
 * - Log file path validation and security checks
 * - Safe log file reading with error handling
 * - Reverse chronological log display
 * - Formatted log display with proper escaping
 * 
 * Security: Requires SMLOGUSER permission
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("user_files/global.inc");
include("includes/common.inc");
include("authusers.php");
include("includes/smlog/smlog-controller.inc");

// Run the Supermon log system
runSmlog();