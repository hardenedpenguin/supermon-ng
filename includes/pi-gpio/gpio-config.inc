<?php
/**
 * Pi GPIO Configuration and Security
 * 
 * Handles authentication and initial setup for GPIO control functionality.
 */

/**
 * Initialize GPIO configuration and security
 */
function initializeGPIOConfig() {
    // Include required dependencies
    include("includes/security.inc");
    include("includes/session.inc");
    include("user_files/global.inc");
    include("includes/common.inc");
    include("authusers.php");
    include("authini.php");

    // Check authentication
    if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("GPIOUSER"))) {
        die ("<br><h3 class='error-message'>ERROR: You Must login to use the 'GPIO' function!</h3>");
    }
}
