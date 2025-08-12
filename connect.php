<?php
/**
 * Supermon-ng Disconnect
 * 
 * Provides disconnect functionality through AMI (Asterisk Manager Interface).
 * Allows authenticated users to disconnect from remote nodes through
 * secure AMI connections with proper validation and JSON responses.
 * 
 * Features:
 * - User authentication and session validation
 * - Parameter validation with sanitization
 * - INI file configuration loading and validation
 * - AMI connection management and authentication
 * - Disconnect action execution
 * - ILink command execution with proper formatting
 * - JSON response formatting for AJAX compatibility
 * - Comprehensive error handling and reporting
 * 
 * Actions:
 * - disconnect: Disconnect from remote node (DISCUSER permission)
 * 
 * Security: Requires valid session and DISCUSER permission
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