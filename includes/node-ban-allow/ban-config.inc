<?php
/**
 * Node Ban/Allow Configuration and Security
 * 
 * Handles authentication, parameter validation, and AMI connection setup
 * for node access control functionality.
 */

/**
 * Initialize node ban/allow configuration and security
 * 
 * @return array Returns [$Node, $localnode, $config, $fp] or exits with error
 */
function initializeBanAllow() {
    // Include required dependencies
    include("includes/session.inc");
    include('includes/amifunctions.inc');
    include("includes/common.inc");
    include("authusers.php");
    include("authini.php");
    include("includes/csrf.inc");

    // Check authentication
    if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("BANUSER")))  {
        die ("<br><h3 class='error-message'>ERROR: You Must login to use the 'Restrict' function!</h3>");
    }

    // Validate CSRF token for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
    }

    // Extract and sanitize parameters
    $Node = trim(strip_tags($_GET['node'] ?? $_GET['ban-node'] ?? ''));
    $localnode = trim(strip_tags($_GET['localnode'] ?? ''));

    // Debug: Show what parameters we received
    error_log("Node-ban-allow Debug: Node='$Node', localnode='$localnode'");

    // Validate inputs - only require localnode to be numeric, Node can be empty initially
    if (!preg_match('/^\d+$/', $localnode)) {
        die("<h3 class='error-message'>ERROR: Invalid local node parameter.</h3>");
    }

    // Node parameter is optional (can be empty when page loads)
    if (!empty($Node) && !preg_match('/^\d+$/', $Node)) {
        die("<h3 class='error-message'>ERROR: Invalid node parameter.</h3>");
    }

    // Load configuration
    $SUPINI = get_ini_name($_SESSION['user']);

    if (!file_exists($SUPINI)) {
        die("<h3 class='error-message'>ERROR: Couldn't load $SUPINI file.</h3>");
    }

    $config = parse_ini_file($SUPINI, true);

    if (empty($localnode) || !isset($config[$localnode])) {
        die("<h3 class='error-message'>ERROR: Node $localnode is not in $SUPINI file or not specified.</h3>");
    }

    // Establish AMI connection
    if (($fp = SimpleAmiClient::connect($config[$localnode]['host'])) === FALSE) {
        die("<h3 class='error-message'>ERROR: Could not connect to Asterisk Manager.</h3>");
    }

    if (SimpleAmiClient::login($fp, $config[$localnode]['user'], $config[$localnode]['passwd']) === FALSE) {
        SimpleAmiClient::logoff($fp);
        die("<h3 class='error-message'>ERROR: Could not login to Asterisk Manager.</h3>");
    }

    return [$Node, $localnode, $config, $fp];
}

/**
 * Cleanup AMI connection for ban/allow operations
 * 
 * @param resource $fp AMI connection to close
 */
function cleanupBanAllowAMI($fp) {
    SimpleAmiClient::logoff($fp);
}
