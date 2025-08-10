<?php
/**
 * Supermon-ng Linux System Log Viewer
 * 
 * Provides a web-based interface for viewing Linux system logs using journalctl.
 * Allows authenticated users to view system messages and events from the last 24 hours
 * with sudo lines filtered out for security.
 * 
 * Features:
 * - User authentication and authorization (LLOGUSER permission required)
 * - Command configuration validation (SUDO, JOURNALCTL, SED)
 * - Secure command execution with proper filtering
 * - Real-time log display with journalctl integration
 * - Filtered output (sudo lines removed for security)
 * 
 * Security: Requires LLOGUSER permission and proper command configuration
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("includes/common.inc");
include("authusers.php");
include("includes/linuxlog/linuxlog-controller.inc");

// Set a description for this specific log page, used in the title and heading.
$log_description = "System Log (journalctl, last 24 hours, sudo lines filtered)";

// Run the Linux log system
runLinuxlog($log_description);
