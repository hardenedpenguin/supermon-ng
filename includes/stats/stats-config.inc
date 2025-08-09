<?php
/**
 * AllStar Statistics Configuration and Initialization
 * 
 * Handles authentication, configuration loading, parameter validation,
 * and AMI connection setup for the stats functionality.
 */

/**
 * Initialize stats page configuration and setup
 * 
 * @return array Returns [$node, $config, $fp] or exits with error
 */
function initializeStatsPage() {
    // Include required dependencies if not already included
    if (!function_exists('get_user_auth')) {
        include_once('authusers.php');
    }
    if (!function_exists('get_ini_name')) {
        include_once('authini.php');
    }
    
    // Authentication check
    if (!(isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && function_exists('get_user_auth') && get_user_auth("ASTATUSER"))) {
        die("<h3 class='error-message'>ERROR: You Must login and have ASTATUSER permission to use this function!</h3>");
    }

    // Load configuration
    $SUPINI = get_ini_name($_SESSION['user']);

    if (!file_exists($SUPINI)) {
        die("<span class='ast-status-error-msg'>ERROR:</span> Couldn't load <span class='ast-status-highlight'>$SUPINI</span> ini file.\n");
    }

    $config = parse_ini_file($SUPINI, true);

    // Validate node parameter
    $node = isset($_GET['node']) ? trim(strip_tags($_GET['node'])) : null;

    if (empty($node)) {
         die("<span class='ast-status-error-msg'>ERROR:</span> 'node' parameter is missing in the URL.");
    }
    if (!isset($config[$node])) {
         die("<span class='ast-status-error-msg'>ERROR:</span> Node <span class='nodeNum'>$node</span> is not in <span class='ast-status-highlight'>$SUPINI</span> file.");
    }

    // Verify AMI client availability
    if (!class_exists('SimpleAmiClient')) {
        die("<span class='ast-status-error-msg'>ERROR:</span> SimpleAmiClient class not found. Ensure amifunctions.inc provides it.");
    }

    // Establish AMI connection
    $fp = SimpleAmiClient::connect($config[$node]['host']);
    if ($fp === false) {
        die("<span class='ast-status-error-msg'>ERROR:</span> Could not connect to Asterisk Manager on host <span class='ast-status-highlight'>{$config[$node]['host']}</span>.");
    }

    $loginSuccess = SimpleAmiClient::login($fp, $config[$node]['user'], $config[$node]['passwd']);
    if ($loginSuccess === false) {
        SimpleAmiClient::logoff($fp);
        die("<span class='ast-status-error-msg'>ERROR:</span> Could not login to Asterisk Manager using user <span class='ast-status-highlight'>{$config[$node]['user']}</span>. Check credentials and manager.conf permissions.");
    }

    return [$node, $config, $fp];
}

/**
 * Cleanup AMI connection
 */
function cleanupStatsAMI($fp) {
    SimpleAmiClient::logoff($fp);
}
