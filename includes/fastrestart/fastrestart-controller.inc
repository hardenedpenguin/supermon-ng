<?php
/**
 * Supermon-ng FastRestart Controller
 * 
 * Main controller for the fastrestart functionality that orchestrates authentication,
 * parameter validation, AMI connection, and restart command execution.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

include_once "includes/fastrestart/fastrestart-auth.inc";
include_once "includes/fastrestart/fastrestart-ui.inc";

/**
 * Initialize and run the fastrestart system
 * 
 * This is the main entry point for the fastrestart functionality.
 * Handles authentication, parameter validation, AMI connection, and restart execution.
 * 
 * @return void
 */
function runFastrestart(): void {
    // Validate user access
    if (!validateFastrestartAccess()) {
        outputFastrestartAccessDenied();
    }

    // Get and validate localnode parameter
    $localnode = getFastrestartLocalnode();
    if ($localnode === false) {
        outputFastrestartLocalnodeError();
    }

    // Load node configuration
    $nodeConfig = loadFastrestartNodeConfig($localnode);
    if ($nodeConfig === false) {
        $iniFilePath = getFastrestartIniPath();
        outputFastrestartNodeNotFoundError($localnode, $iniFilePath);
    }

    $fp = null;

    try {
        // Connect to AMI
        $fp = connectToFastrestartAmi($nodeConfig);
        if ($fp === false) {
            outputFastrestartAmiConnectionError($nodeConfig['host']);
        }

        // Execute restart command
        $amiResponse = executeFastrestartCommand($fp);
        
        if ($amiResponse === false) {
            outputFastrestartCommandError($localnode);
        }

        // Output success message
        outputFastrestartSuccess($localnode);

    } catch (Exception $e) {
        outputFastrestartUnexpectedError($e->getMessage());
    } finally {
        // Clean up AMI connection
        if ($fp && $fp !== false) {
            SimpleAmiClient::logoff($fp);
        }
    }
}
