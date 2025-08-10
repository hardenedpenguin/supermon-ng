<?php
/**
 * Supermon-ng DTMF Controller
 * 
 * Main controller for the DTMF functionality that orchestrates authentication,
 * parameter validation, AMI connection, and command execution.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

include_once "includes/dtmf/dtmf-auth.inc";
include_once "includes/dtmf/dtmf-ui.inc";

/**
 * Initialize and run the DTMF system
 * 
 * This is the main entry point for the DTMF functionality.
 * Handles authentication, parameter validation, AMI connection, and command execution.
 * 
 * @return void
 */
function runDtmf(): void {
    // Validate user access
    if (!validateDtmfAccess()) {
        if (!isset($_SESSION['sm61loggedin']) || $_SESSION['sm61loggedin'] !== true) {
            outputDtmfAccessDenied();
        }
        outputDtmfUnauthorized();
    }

    // Get and validate parameters
    $params = getDtmfParameters();
    if ($params === false) {
        $dtmf = trim(strip_tags($_POST['node'] ?? ''));
        if (empty($dtmf)) {
            outputDtmfParameterError();
        }
        outputDtmfLocalNodeError();
    }

    $dtmf = $params['dtmf'];
    $localnode = $params['localnode'];

    // Load node configuration
    $nodeConfig = loadDtmfNodeConfig($localnode);
    if ($nodeConfig === false) {
        if (!isset($_SESSION['user']) || !function_exists('get_ini_name')) {
            outputDtmfSessionError();
        }
        
        $iniFilePath = getDtmfIniPath();
        if (!file_exists($iniFilePath)) {
            outputDtmfIniNotFoundError($iniFilePath);
        }
        
        $config = parse_ini_file($iniFilePath, true);
        if ($config === false) {
            outputDtmfIniParseError($iniFilePath);
        }
        
        if (!isset($config[$localnode])) {
            outputDtmfNodeNotFoundError($localnode, $iniFilePath);
        }
        
        $nodeConfig = $config[$localnode];
        if (!isset($nodeConfig['host']) || !isset($nodeConfig['user']) || !isset($nodeConfig['passwd'])) {
            outputDtmfIncompleteConfigError($localnode);
        }
    }

    // Connect to AMI
    $amiSocket = connectToDtmfAmi($nodeConfig);
    if ($amiSocket === false) {
        if (!class_exists('SimpleAmiClient')) {
            outputDtmfAmiClientNotFoundError();
        }
        
        if (SimpleAmiClient::connect($nodeConfig['host']) === false) {
            outputDtmfAmiConnectionError($nodeConfig['host']);
        }
        
        outputDtmfAmiLoginError($nodeConfig['user']);
    }

    try {
        // Execute DTMF command
        $commandOutput = executeDtmfCommand($amiSocket, $localnode, $dtmf);
        
        if ($commandOutput === false) {
            outputDtmfCommandError($dtmf, $localnode);
        }

        // Output success result
        outputDtmfSuccess($dtmf, $localnode, $commandOutput);

    } catch (Exception $e) {
        outputDtmfUnexpectedError($e->getMessage());
    } finally {
        // Clean up AMI connection
        if (is_resource($amiSocket)) {
            SimpleAmiClient::logoff($amiSocket);
        }
    }
}
