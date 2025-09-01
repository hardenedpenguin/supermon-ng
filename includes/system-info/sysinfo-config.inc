<?php
/**
 * System Info Configuration and Security
 * 
 * Handles authentication, parameter validation, and initial setup
 * for the system information display functionality.
 */

/**
 * Initialize system info page configuration and security
 * 
 * @return array Returns [$Show_Detail] or exits with error
 */
function initializeSystemInfo() {
    // Include required dependencies
    include("includes/session.inc");
    include("includes/security.inc");
    include("includes/common.inc");
    include("user_files/global.inc");
    include("user_files/authusers.inc");
    include("user_files/authini.inc");
    include("user_files/favini.inc");
    include("user_files/cntrlini.inc");

    // Check authentication
    if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("SYSINFUSER"))) {
        die("<br><h3 class='error-message'>ERROR: You Must login to use the 'System Info' function!</h3>");
    }

    // Sanitize input parameters
    $Show_Detail = 0; // Default value
    $name = isset($_COOKIE['display-data']['name']) ? htmlspecialchars($_COOKIE['display-data']['name']) : '';
    $value = isset($_COOKIE['display-data']['value']) ? htmlspecialchars($_COOKIE['display-data']['value']) : '';

    if (!empty($name) && !empty($value)) {
        $name = htmlspecialchars($name);
        $value = htmlspecialchars($value);
        
        if ($name === "show-detailed") {
            $Show_Detail = htmlspecialchars($value);
        }
    }

    return [$Show_Detail];
}
