<?php
/**
 * Supermon-ng Control Server Controller
 * 
 * Main controller for the control server functionality that orchestrates authentication,
 * parameter validation, AMI connection, and command execution.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include_once "includes/controlserver/controlserver-auth.inc";
include_once "includes/controlserver/controlserver-ui.inc";

/**
 * Initialize and run the control server system
 * 
 * This is the main entry point for the control server functionality.
 * Handles authentication, parameter validation, AMI connection, and command execution.
 * 
 * @return void
 */
function runControlserver(): void {
    // Validate user access
    if (!validateControlserverAccess()) {
        outputControlserverAccessDenied();
    }

    // Get and validate parameters
    $params = getControlserverParameters();
    if ($params === false) {
        outputControlserverParameterError();
    }

    $node = $params['node'];
    $cmd = $params['cmd'];

    // Load node configuration
    $nodeConfig = loadControlserverNodeConfig($node);
    if ($nodeConfig === false) {
        $iniFilePath = getControlserverIniPath();
        if (!file_exists($iniFilePath)) {
            outputControlserverIniNotFound($iniFilePath);
        }
        
        $config = parse_ini_file($iniFilePath, true);
        if ($config === false) {
            outputControlserverIniParseError($iniFilePath);
        }
        
        if (!isset($config[$node])) {
            outputControlserverNodeNotFound($node, $iniFilePath);
        }
        
        outputControlserverIncompleteConfig($node, $iniFilePath);
    }

    // Connect to AMI
    $fp = connectToControlserverAmi($nodeConfig);
    if ($fp === false) {
        if (!SimpleAmiClient::connect($nodeConfig['host'])) {
            outputControlserverConnectionError($nodeConfig['host']);
        }
        outputControlserverLoginError($nodeConfig['host'], $nodeConfig['user']);
    }

    // Execute command
    $result = executeControlserverCommand($fp, $node, $cmd);
    if ($result === false) {
        $cmdString = str_replace("%node%", $node, $cmd);
        SimpleAmiClient::logoff($fp);
        outputControlserverCommandError($cmdString, $node);
    }

    // Output result
    $cmdString = str_replace("%node%", $node, $cmd);
    outputControlserverResult($cmdString, $result);

    // Clean up connection
    SimpleAmiClient::logoff($fp);
}
