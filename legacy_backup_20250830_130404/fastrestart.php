<?php
/**
 * Supermon-ng FastRestart
 * 
 * Provides fast restart functionality for AllStar Asterisk systems.
 * Allows authenticated users to perform immediate Asterisk restarts through
 * secure AMI (Asterisk Manager Interface) connections with proper validation.
 * 
 * Features:
 * - Secure Asterisk restart execution via AMI
 * - User authentication and permission validation (FSTRESUSER)
 * - Parameter validation and sanitization
 * - INI file configuration loading and validation
 * - AMI connection management and authentication
 * - Comprehensive error handling and reporting
 * - Proper resource cleanup and connection management
 * - HTML output formatting for web interface
 * 
 * Security:
 * - Session validation and authentication required
 * - FSTRESUSER permission validation
 * - Input sanitization and validation
 * - HTML escaping for safe output
 * - AMI connection security with proper cleanup
 * - Comprehensive error reporting for debugging
 * 
 * Command:
 * - Uses "restart now" Asterisk command
 * - Performs immediate Asterisk restart
 * - Returns confirmation message
 * - Logs restart action for audit trail
 * 
 * Dependencies: session.inc, amifunctions.inc, authusers.php, authini.php
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include("includes/session.inc");
include('includes/amifunctions.inc');
include("authusers.php");
include("authini.php");
include("includes/fastrestart/fastrestart-controller.inc");

// Run the fastrestart system
runFastrestart();
?>