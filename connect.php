<?php
/**
 * Supermon-ng Connect
 * 
 * Provides connection management functionality through AMI (Asterisk Manager Interface).
 * Allows authenticated users to connect, monitor, and disconnect from remote nodes
 * through secure AMI connections with proper validation and JSON responses.
 * 
 * Features:
 * - User authentication and session validation
 * - Parameter validation with sanitization
 * - INI file configuration loading and validation
 * - AMI connection management and authentication
 * - Multiple action types (connect, monitor, localmonitor, disconnect)
 * - Permanent and temporary connection support
 * - ILink command execution with proper formatting
 * - JSON response formatting for AJAX compatibility
 * - Comprehensive error handling and reporting
 * 
 * Actions:
 * - connect: Connect to remote node (CONNECTUSER permission)
 * - monitor: Monitor remote node (MONUSER permission)
 * - localmonitor: Local monitoring (LMONUSER permission)
 * - disconnect: Disconnect from remote node (DISCUSER permission)
 * 
 * Security: Requires valid session and appropriate user permissions
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include('includes/session.inc');
include('authusers.php');
include('includes/global.inc');
include('includes/amifunctions.inc');
include('includes/common.inc');
include('authini.php');
include("includes/connect/connect-controller.inc");

// Run the connect system
runConnect();

?>