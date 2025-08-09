<?php
/**
 * Voter Server Configuration and Initialization
 * 
 * Handles SSE headers, configuration loading, parameter validation,
 * and AMI connection setup for the voter server functionality.
 */

/**
 * Initialize voter server configuration and setup
 * 
 * @return array Returns [$node, $nodeConfig, $astdb, $fp] or exits with error
 */
function initializeVoterServer() {
    global $ASTDB_TXT, $USERFILES;
    
    // Set time limit for long-running SSE
    @set_time_limit(0);
    
    // Include required dependencies if not already included
    if (!function_exists('get_ini_name')) {
        include_once('authini.php');
    }
    
    // Include session and close it for SSE
    include_once("session.inc");
    session_write_close();

    // Set SSE headers
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    // Set timezone
    date_default_timezone_set('America/Los_Angeles');

    // Load AllStar database
    $db = $ASTDB_TXT;
    $astdb = [];
    if (file_exists($db)) {
        $fh = fopen($db, "r");
        if ($fh && flock($fh, LOCK_SH)) {
            while (($line = fgets($fh)) !== false) {
                $trimmed_line = trim($line);
                if (empty($trimmed_line)) continue;
                $arr = explode("|", $trimmed_line);
                if (isset($arr[0])) $astdb[$arr[0]] = $arr;
            }
            flock($fh, LOCK_UN); fclose($fh);
        }
    }

    // Validate node parameter
    if (empty($_GET['node'])) {
        echo "data: [FATAL] 'node' parameter is missing.\n\n";
        ob_flush(); flush(); exit;
    }
    $node = trim(strip_tags($_GET['node']));

    // Determine configuration file path
    $ini_file_path = '';
    $user_context = 'public';

    if (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && !empty($_SESSION['user'])) {
        $ini_file_path = get_ini_name($_SESSION['user']);
        $user_context = "user '{$_SESSION['user']}'";
    } else {
        $ini_file_path = "$USERFILES/allmon.ini";
    }

    // Load and validate configuration
    if (!file_exists($ini_file_path)) {
        echo "data: [FATAL] Configuration file not found for {$user_context}: {$ini_file_path}\n\n";
        ob_flush(); flush(); exit;
    }

    $config = parse_ini_file($ini_file_path, true);
    if ($config === false) {
        echo "data: [FATAL] Error parsing configuration file: {$ini_file_path}\n\n";
        ob_flush(); flush(); exit;
    }

    if (!isset($config[$node])) {
        echo "data: [FATAL] Configuration for node '$node' not found in {$user_context} context.\n\n";
        ob_flush(); flush(); exit;
    }
    $nodeConfig = $config[$node];

    // Establish AMI connection
    $fp = SimpleAmiClient::connect($nodeConfig['host']);
    if ($fp === false) {
        echo "data: [FATAL] Could not connect to Asterisk Manager on host: {$nodeConfig['host']}.\n\n";
        ob_flush(); flush(); exit;
    }

    if (SimpleAmiClient::login($fp, $nodeConfig['user'], $nodeConfig['passwd']) === false) {
        echo "data: [FATAL] Could not login to Asterisk Manager. Check user/password.\n\n";
        ob_flush(); flush();
        SimpleAmiClient::logoff($fp);
        exit;
    }

    return [$node, $nodeConfig, $astdb, $fp];
}

/**
 * Cleanup AMI connection
 */
function cleanupVoterAMI($fp) {
    SimpleAmiClient::logoff($fp);
}
