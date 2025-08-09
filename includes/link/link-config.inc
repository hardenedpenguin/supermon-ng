<?php
/**
 * Link.php Configuration Management
 * 
 * This file contains configuration loading and validation
 * logic extracted from link.php.
 */

/**
 * Validate and parse nodes parameter
 * @return array Array of valid node IDs or dies on error
 */
function validateLinkNodesParameter() {
    $raw_nodes_param = trim(strip_tags($_GET['nodes'] ?? ''));
    if (empty($raw_nodes_param)) {
        die ("Please provide a properly formated URI. (ie link.php?nodes=1234 | link.php?nodes=1234,2345)");
    }
    $passedNodes = array_filter(array_map('trim', explode(',', $raw_nodes_param)), 'strlen');
    if (empty($passedNodes)) {
        die ("Please provide a properly formated URI. (ie link.php?nodes=1234 | link.php?nodes=1234,2345) - No valid nodes found after parsing.");
    }
    return $passedNodes;
}

/**
 * Load and validate configuration for link.php
 * @param array $passedNodes Array of requested nodes
 * @return array Configuration array
 */
function loadLinkConfiguration($passedNodes) {
    $SUPINI = get_ini_name($_SESSION['user']);

    if (!file_exists($SUPINI)) {
        die("Couldn't load $SUPINI file.\n");
    }
    $config = parse_ini_file($SUPINI, true);

    $nodes_check = array();
    foreach ($passedNodes as $node_item) {
        if (isset($config[$node_item])) {
            $nodes_check[] = $node_item;
        }
    }
    if (empty($nodes_check)) {
        die("None of the provided nodes were found in the configuration file: $SUPINI");
    }

    return [$config, $nodes_check];
}

/**
 * Load ASTDB database for link.php
 * @return array ASTDB data array
 */
function loadLinkAstDatabase() {
    global $ASTDB_TXT;
    
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
    
    return $astdb;
}

/**
 * Process display preferences from cookies
 * @return array Display settings array
 */
function processDisplayPreferences() {
    $expiretime = 2147483645;
    $cookie_data = $_COOKIE['display-data'] ?? [];

    $Displayed_Nodes = isset($cookie_data['number-displayed']) ? htmlspecialchars($cookie_data['number-displayed']) : null;
    $Display_Count   = isset($cookie_data['show-number']) ? htmlspecialchars($cookie_data['show-number']) : null;
    $Show_All        = isset($cookie_data['show-all']) ? htmlspecialchars($cookie_data['show-all']) : null;
    $Show_Detail     = isset($cookie_data['show-detailed']) ? htmlspecialchars($cookie_data['show-detailed']) : null;

    if ($Displayed_Nodes === null || $Displayed_Nodes === "0") {
        $Displayed_Nodes = "999";
    }
    if ($Display_Count === null) {
        $Display_Count = 0;
    }
    if ($Show_All === null) {
        $Show_All = "1";
    }
    if ($Show_Detail === null) {
        $Show_Detail = "1";
    }
    setcookie("display-data[show-detailed]", $Show_Detail, $expiretime);

    return [
        'Displayed_Nodes' => $Displayed_Nodes,
        'Display_Count' => $Display_Count,
        'Show_All' => $Show_All,
        'Show_Detail' => $Show_Detail
    ];
}

/**
 * Initialize session for link.php
 */
function initializeLinkSession() {
    if (!isset($_SESSION['user'])) {
        $_SESSION['user'] = "";
    }
}

/**
 * Master initialization function for link.php
 * @return array Returns [$nodes, $config, $astdb, $displayPrefs, $parms]
 */
function initializeLinkPage() {
    // Initialize session
    initializeLinkSession();
    
    // Validate nodes parameter
    $passedNodes = validateLinkNodesParameter();
    $parms = implode(',', $passedNodes);
    
    // Process display preferences
    $displayPrefs = processDisplayPreferences();
    
    // Load configuration
    list($config, $nodes) = loadLinkConfiguration($passedNodes);
    
    // Load ASTDB
    $astdb = loadLinkAstDatabase();
    
    return [$nodes, $config, $astdb, $displayPrefs, $parms];
}
?>
