<?php
/**
 * Supermon-ng DTMF
 * 
 * Provides DTMF (Dual-Tone Multi-Frequency) command execution functionality.
 * Allows authenticated users to send DTMF commands to AllStar nodes through
 * secure AMI (Asterisk Manager Interface) connections with proper validation.
 * 
 * Features:
 * - Secure DTMF command execution via AMI
 * - User authentication and permission validation (DTMFUSER)
 * - Parameter validation and sanitization
 * - INI file configuration loading and validation
 * - AMI connection management and authentication
 * - Comprehensive error handling and reporting
 * - CSRF protection for form submissions
 * - Proper resource cleanup and connection management
 * - Command output formatting and display
 * 
 * Security:
 * - Session validation and authentication required
 * - DTMFUSER permission validation
 * - CSRF token validation for form submissions
 * - Input sanitization and validation
 * - HTML escaping for safe output
 * - AMI connection security with proper cleanup
 * - Comprehensive error reporting for debugging
 * 
 * Command Format:
 * - Uses "rpt fun" Asterisk command format
 * - Supports standard DTMF tone sequences
 * - Executes commands on specified local nodes
 * - Returns command output or success confirmation
 * 
 * Dependencies: session.inc, amifunctions.inc, global.inc, authusers.php, common.inc, authini.php, csrf.inc
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include('includes/amifunctions.inc');
include('user_files/global.inc');
include('authusers.php');
include('includes/common.inc');
include('authini.php');
include('includes/csrf.inc');
include("includes/dtmf/dtmf-controller.inc");

// Validate CSRF token
require_csrf();

// Run the DTMF system
runDtmf();
?>