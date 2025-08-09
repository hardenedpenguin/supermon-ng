<?php
/**
 * AllStar Lookup Configuration and Initialization
 * 
 * Handles configuration loading, parameter validation, database loading,
 * and AMI connection setup for the astlookup functionality.
 */

/**
 * Initialize lookup page configuration and setup
 * 
 * @return array Returns [$lookupNode, $localnode, $perm, $config, $astdb, $fp]
 */
function initializeLookupPage() {
    global $ASTDB_TXT;
    
    // Include required dependencies if not already included
    if (!function_exists('get_user_auth')) {
        include_once('authusers.php');
    }
    if (!function_exists('get_ini_name')) {
        include_once('authini.php');
    }
    if (!function_exists('csrf_token_field')) {
        include_once('includes/csrf.inc');
    }
    
    // Authentication check
    if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("ASTLKUSER"))) {
        die ("<br><h3 class='error-message'>ERROR: You Must login to use the 'Lookup' function!</h3>");
    }

    // Validate CSRF token for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
    }

    // Get and sanitize input parameters
    $lookupNode = trim(strip_tags($_GET['node'] ?? ''));
    $localnode = trim(strip_tags($_GET['localnode'] ?? ''));
    $perm = trim(strip_tags($_GET['perm'] ?? ''));

    // Validate inputs
    if (!preg_match('/^\d+$/', $localnode)) {
        die("<h3 class='error-message'>ERROR: Invalid local node parameter.</h3>");
    }

    if (empty($lookupNode)) {
        die("<h3 class='error-message'>ERROR: Please provide a node number or callsign to lookup.</h3>");
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

    // Load the AllStar database
    $db = $ASTDB_TXT;
    $astdb = array();
    if (file_exists($db)) {
        $fh = fopen($db, "r");
        if ($fh && flock($fh, LOCK_SH)) {
            while (($line = fgets($fh)) !== FALSE) {
                $arr_db = explode('|', trim($line));
                if (isset($arr_db[0])) {
                     $astdb[$arr_db[0]] = $arr_db;
                }
            }
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    // Establish AMI connection
    if (($fp = SimpleAmiClient::connect($config[$localnode]['host'])) === FALSE) {
        die("<h3 class='error-message'>ERROR: Could not connect to Asterisk Manager.</h3>");
    }

    if (SimpleAmiClient::login($fp, $config[$localnode]['user'], $config[$localnode]['passwd']) === FALSE) {
        SimpleAmiClient::logoff($fp);
        die("<h3 class='error-message'>ERROR: Could not login to Asterisk Manager.</h3>");
    }

    return [$lookupNode, $localnode, $perm, $config, $astdb, $fp];
}

/**
 * AMI command helper functions
 */
function sendCmdToAMI($fp, $cmd)
{
    return SimpleAmiClient::command($fp, $cmd);
}

function getDataFromAMI($fp, $cmd)
{
    return SimpleAmiClient::command($fp, $cmd);
}

/**
 * Cleanup AMI connection
 */
function cleanupLookupAMI($fp) {
    SimpleAmiClient::logoff($fp);
}
