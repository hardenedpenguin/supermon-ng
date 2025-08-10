<?php
/**
 * Supermon-ng BubbleChart
 * 
 * Provides AllStarLink "Bubble Chart" functionality for displaying node status.
 * Authenticates users and generates JavaScript to open popup windows to
 * stats.allstarlink.org for viewing node bubble charts and status information.
 * 
 * Features:
 * - User authentication and permission validation (BUBLUSER)
 * - Parameter validation and sanitization
 * - Support for both 'node' and 'localnode' POST parameters
 * - Automatic popup window generation
 * - Integration with stats.allstarlink.org
 * - User-friendly message display
 * - Secure URL generation and encoding
 * 
 * Security:
 * - Session validation and authentication required
 * - BUBLUSER permission validation
 * - Input sanitization and validation
 * - HTML escaping for safe output
 * - URL encoding for external links
 * 
 * Parameters:
 * - node: Specific node number to display
 * - localnode: Alternative node parameter
 * - Priority given to 'node' parameter if both are provided
 * 
 * External Integration:
 * - Connects to stats.allstarlink.org/getstatus.cgi
 * - Opens popup windows for bubble chart display
 * - Provides node status and connectivity information
 * 
 * Dependencies: session.inc, authusers.php
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("authusers.php");
include("includes/bubblechart/bubblechart-controller.inc");

// Run the bubblechart system
runBubblechart();
?>