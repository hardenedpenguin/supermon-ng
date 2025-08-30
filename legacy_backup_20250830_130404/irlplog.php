<?php
/**
 * Supermon-ng IRLP Log Viewer
 * 
 * Provides a web-based interface for viewing IRLP (Internet Radio Linking Project) log files.
 * Allows authenticated users to view IRLP system messages and events through
 * a secure web interface with proper validation.
 * 
 * Features:
 * - User authentication and authorization (IRLPLOGUSER permission required)
 * - Log file path validation and security checks
 * - Safe log file reading with error handling
 * - Formatted log display with proper escaping
 * - Simple and clean log viewer interface
 * 
 * Security: Requires IRLPLOGUSER permission
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("user_files/global.inc");
include("includes/common.inc");
include("authusers.php");
include("includes/irlplog/irlplog-controller.inc");

// Run the IRLP log system
runIrlplog();