<?php
/**
 * Server.php Configuration and Initialization
 * 
 * This file contains configuration loading, validation,
 * and initialization logic extracted from server.php.
 */

/**
 * Initialize server environment and headers
 */
function initializeServerEnvironment() {
    @set_time_limit(0);
    session_name("supermon61");
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    ini_set('log_errors', 1);

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    date_default_timezone_set('America/New_York');
}

/**
 * Validate and parse nodes parameter
 * @return array Array of valid node IDs or exits on error
 */
function validateNodesParameter() {
    if (empty($_GET['nodes'])) {
        error_log("Unknown request! Missing nodes parameter in server.php.");
        $data = ['status' => 'Unknown request! Missing nodes parameter.'];
        echo "event: error\n";
        echo 'data: ' . json_encode($data) . "\n\n";
        ob_flush();
        flush();
        if (session_status() == PHP_SESSION_ACTIVE) { session_write_close(); }
        exit;
    }

    $passedNodes = explode(',', trim(strip_tags($_GET['nodes'])));
    $passedNodes = array_filter(array_map('trim', $passedNodes), 'strlen');

    if (empty($passedNodes)) {
        error_log("No valid nodes in 'nodes' parameter after parsing in server.php.");
        $data = ['status' => 'No valid nodes provided in the request.'];
        echo "event: error\n";
        echo 'data: ' . json_encode($data) . "\n\n";
        ob_flush();
        flush();
        if (session_status() == PHP_SESSION_ACTIVE) { session_write_close(); }
        exit;
    }

    return $passedNodes;
}

/**
 * Load and parse ASTDB file
 * @return array ASTDB data array
 */
function loadAstDatabase() {
    global $ASTDB_TXT;
    
    $db = $ASTDB_TXT ?? null;
    $astdb = [];
    
    if (isset($db) && file_exists($db)) {
        $fh = fopen($db, "r");
        if ($fh && flock($fh, LOCK_SH)) {
            while (($line = fgets($fh)) !== FALSE) {
                $arr = preg_split("/\|/", trim($line));
                if (isset($arr[0])) {
                    $astdb[$arr[0]] = $arr;
                }
            }
            flock($fh, LOCK_UN);
            fclose($fh);
        } elseif ($fh) {
            error_log("ASTDB_TXT: Opened but flock failed for $db.");
            fclose($fh);
        } else {
             error_log("ASTDB_TXT: Could not open file $db for reading.");
        }
    } else {
        error_log("ASTDB_TXT ('" . ($db ?? 'Not defined') . "') not defined or file does not exist.");
    }
    
    return $astdb;
}

/**
 * Load and validate configuration file
 * @return array Configuration array or exits on error
 */
function loadServerConfiguration() {
    $SUPINI = get_ini_name($_SESSION['user'] ?? '');

    if (!file_exists($SUPINI)) {
        $data = ['status' => "Critical Error: Couldn't load $SUPINI file."];
        error_log("CRITICAL ERROR: SUPINI file '$SUPINI' for user '" . ($_SESSION['user'] ?? 'Unknown') . "' does NOT exist.");
        echo "event: error\n";
        echo 'data: ' . json_encode($data) . "\n\n";
        ob_flush();
        flush();
        if (session_status() == PHP_SESSION_ACTIVE) { session_write_close(); }
        exit;
    }

    $config = parse_ini_file($SUPINI, true);
    if ($config === false) {
        error_log("CRITICAL ERROR: parse_ini_file failed for '$SUPINI'. PHP error: " . print_r(error_get_last(), true));
        $data = ['status' => "Critical Error: Couldn't parse $SUPINI file. Check INI syntax."];
        echo "event: error\n";
        echo 'data: ' . json_encode($data) . "\n\n";
        ob_flush();
        flush();
        if (session_status() == PHP_SESSION_ACTIVE) { session_write_close(); }
        exit;
    }

    // Close session after loading config
    if (session_status() == PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    return $config;
}

/**
 * Validate nodes against configuration
 * @param array $passedNodes Array of requested node IDs
 * @param array $config Configuration array
 * @return array Array of valid nodes
 */
function validateNodes($passedNodes, $config) {
    $nodes = [];
    
    foreach ($passedNodes as $node) {
        $trimmedNode = trim($node);
        if (isset($config[$trimmedNode])) {
            $nodes[] = $trimmedNode;
        } else {
            $data = ['node' => $trimmedNode, 'status' => "Node $trimmedNode is not in configuration file"];
            error_log("Node '$trimmedNode' IS NOT VALID. Not found in configuration.");
            echo "event: nodes\n";
            echo 'data: ' . json_encode([$trimmedNode => $data]) . "\n\n";
            ob_flush();
            flush();
        }
    }

    if (empty($nodes)) {
        error_log("No valid nodes to process after checking config.");
        $data = ['status' => 'No valid nodes found in configuration.'];
        echo "event: error\n";
        echo 'data: ' . json_encode($data) . "\n\n";
        ob_flush();
        flush();
        exit;
    }
    
    return $nodes;
}

/**
 * Initialize server with all configuration and validation
 * @return array Returns [$nodes, $config, $astdb]
 */
function initializeServer() {
    // Initialize environment
    initializeServerEnvironment();
    
    // Validate nodes parameter
    $passedNodes = validateNodesParameter();
    
    // Load ASTDB
    $astdb = loadAstDatabase();
    
    // Load configuration
    $config = loadServerConfiguration();
    
    // Validate nodes against config
    $nodes = validateNodes($passedNodes, $config);
    
    return [$nodes, $config, $astdb];
}
?>
