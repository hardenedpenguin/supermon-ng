<?php
/**
 * Supermon-ng ExtNodes
 * 
 * Provides a web-based viewer for AllStar external nodes configuration.
 * Displays the contents of the rpt_extnodes file with proper formatting
 * and access control for authorized users.
 * 
 * Features:
 * - Secure file content display with HTML escaping
 * - User authentication and permission validation (EXNUSER)
 * - Simple HTML page with pre-formatted content
 * - File existence validation and error handling
 * - Clean, readable output format
 * - Access denied page for unauthorized users
 * 
 * Security:
 * - Requires valid session and EXNUSER permission
 * - File path validation and existence checks
 * - HTML escaping for safe content display
 * - Access control with proper messaging
 * 
 * Display:
 * - Clean HTML page with pre-formatted content
 * - File path header with separator line
 * - Raw file content display
 * - Error message for missing files
 * - Access denied page for unauthorized access
 * 
 * Dependencies: session.inc, common.inc (for EXTNODES), authusers.php
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("includes/common.inc");
include("authusers.php");
include("includes/extnodes/extnodes-controller.inc");

// Run the extnodes system
runExtnodes();
?>