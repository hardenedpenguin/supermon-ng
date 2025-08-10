<?php
/**
 * Supermon-ng Connect Controller
 * 
 * Main controller for the connect functionality that orchestrates authentication,
 * parameter validation, AMI connection, and command execution.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

include_once "includes/connect/connect-auth.inc";
include_once "includes/connect/connect-ui.inc";

/**
 * Initialize and run the connect system
 * 
 * This is the main entry point for the connect functionality.
 * Handles authentication, parameter validation, AMI connection, and command execution.
 * 
 * @return void
 */
function runConnect(): void {
    // Validate user access
    if (!validateConnectAccess()) {
        outputConnectAccessDenied();
    }

    // Get and validate parameters
    $params = getConnectParameters();
    if ($params === false) {
        outputConnectParameterError();
    }

    $remotenode = $params['remotenode'];
    $perm_input = $params['perm_input'];
    $button = $params['button'];
    $localnode = $params['localnode'];

    // Load node configuration
    $nodeConfig = loadConnectNodeConfig($localnode);
    if ($nodeConfig === false) {
        $SUPINI = getConnectIniPath();
        if (!file_exists($SUPINI)) {
            outputConnectIniNotFound($SUPINI);
        }
        outputConnectNodeNotFound($localnode, $SUPINI);
    }

    // Connect to AMI
    $fp = connectToAmi($nodeConfig, $localnode);
    if ($fp === false) {
        if (SimpleAmiClient::connect($nodeConfig['host']) === FALSE) {
            outputConnectAmiConnectionError($localnode);
        }
        outputConnectAmiLoginError($localnode);
    }

    // Process action
    $action_result = processConnectAction($button, $perm_input, $localnode, $remotenode);
    if ($action_result === false) {
        $actions_config = getConnectActionsConfig();
        if (!isset($actions_config[$button])) {
            SimpleAmiClient::logoff($fp);
            outputConnectInvalidActionError($button);
        }
        SimpleAmiClient::logoff($fp);
        outputConnectUnauthorizedError($button);
    }

    $ilink = $action_result['ilink'];
    $message = $action_result['message'];

    // Output success response
    outputConnectSuccess($message);

    // Execute command if ilink is set
    if ($ilink !== null) {
        $AMI_response = executeConnectCommand($fp, $ilink, $localnode, $remotenode, $button);
    } else {
        SimpleAmiClient::logoff($fp);
        outputConnectIlinkError();
    }

    // Clean up connection
    SimpleAmiClient::logoff($fp);
}
