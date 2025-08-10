<?php
/**
 * Supermon-ng Control Server
 * 
 * Provides server control functionality through AMI (Asterisk Manager Interface).
 * Allows authenticated users to execute commands on remote nodes through
 * secure AMI connections with proper validation and error handling.
 * 
 * Features:
 * - User authentication and session validation
 * - Parameter validation (node and command)
 * - INI file configuration loading and validation
 * - AMI connection management and authentication
 * - Command execution with node substitution
 * - Comprehensive error handling and reporting
 * - Secure output formatting with HTML escaping
 * 
 * Security: Requires valid session and proper node configuration
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include('includes/amifunctions.inc');
include('user_files/global.inc');
include('includes/common.inc');
include('authini.php');
include("includes/controlserver/controlserver-controller.inc");

// Run the control server system
runControlserver();

?>