<?php
/**
 * Supermon-ng Control Server Favorites Controller
 * 
 * Main controller for the control server favorites functionality that orchestrates authentication,
 * parameter validation, AMI connection, and command execution with enhanced error handling.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

include_once "includes/controlserverfavs/controlserverfavs-auth.inc";
include_once "includes/controlserverfavs/controlserverfavs-ui.inc";

/**
 * Initialize and run the control server favorites system
 * 
 * This is the main entry point for the control server favorites functionality.
 * Handles authentication, parameter validation, AMI connection, and command execution with enhanced error handling.
 * 
 * @return void
 */
function runControlserverfavs(): void {
    // Validate user access
    if (!validateControlserverfavsAccess()) {
        outputControlserverfavsAccessDenied();
    }

    // Get and validate parameters
    $params = getControlserverfavsParameters();
    if ($params === false) {
        outputControlserverfavsParameterError();
    }

    $node = $params['node'];
    $cmd = $params['cmd'];

    // Load node configuration
    $nodeConfig = loadControlserverfavsNodeConfig($node);
    if ($nodeConfig === false) {
        $supIniFile = getControlserverfavsIniPath();
        if (!file_exists($supIniFile)) {
            outputControlserverfavsIniNotFound($supIniFile);
        }
        
        $config = parse_ini_file($supIniFile, true);
        if ($config === false) {
            outputControlserverfavsIniParseError($supIniFile);
        }
        
        outputControlserverfavsNodeNotFound($node, $supIniFile);
    }

    $fp = null;
    try {
        // Connect to AMI
        $fp = connectToControlserverfavsAmi($nodeConfig, $node);

        // Execute command
        $commandOutput = executeControlserverfavsCommand($fp, $node, $cmd);

        // Output result
        $cmdString = str_replace("%node%", $node, $cmd);
        outputControlserverfavsResult($cmdString, $commandOutput);

    } catch (RuntimeException $e) {
        outputControlserverfavsRuntimeError($e->getMessage());
    } finally {
        // Clean up connection
        if (isset($fp) && is_resource($fp)) {
            SimpleAmiClient::logoff($fp);
        }
    }
}
