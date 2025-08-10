<?php
/**
 * Supermon-ng AstNodes
 * 
 * Provides a web-based viewer for AllStar Asterisk database files.
 * Displays the contents of the ASTDB_TXT file with proper formatting,
 * syntax highlighting, and access control for authorized users.
 * 
 * Features:
 * - Secure file content display with HTML escaping
 * - User authentication and permission validation (NINFUSER)
 * - Responsive design for mobile and desktop viewing
 * - Monospace font for optimal code readability
 * - Scrollable content area with max height limits
 * - Error handling for missing or unreadable files
 * - Configuration validation for ASTDB_TXT path
 * - Access denied messaging for unauthorized users
 * 
 * Security:
 * - Requires valid session and NINFUSER permission
 * - File path validation and readability checks
 * - HTML escaping for safe content display
 * - Configuration validation to prevent errors
 * 
 * Display:
 * - Clean, modern interface with CSS variables
 * - File header showing current file path
 * - Monospace content area with proper wrapping
 * - Error messages with appropriate styling
 * - Mobile-responsive design
 * 
 * Dependencies: common.inc (for ASTDB_TXT), authusers.php (for permissions)
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("includes/common.inc");
include("authusers.php");
include("includes/astnodes/astnodes-controller.inc");

// Include header for consistent styling
include "header.inc";

// Run the astnodes system
runAstnodes();

include "footer.inc";
?>
