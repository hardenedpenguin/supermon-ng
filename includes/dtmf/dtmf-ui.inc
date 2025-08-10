<?php
/**
 * Supermon-ng DTMF UI
 * 
 * Provides the output formatting and error display components for the DTMF interface.
 * Handles command result display and error message formatting.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Output access denied error
 */
function outputDtmfAccessDenied(): void {
    die("<br><h3>ERROR: You Must login to use this function!</h3>");
}

/**
 * Output unauthorized error
 */
function outputDtmfUnauthorized(): void {
    die("<br><h3>ERROR: You are not authorized to use the 'DTMF' function!</h3>");
}

/**
 * Output DTMF parameter error
 */
function outputDtmfParameterError(): void {
    die("Please provide a DTMF command.\n");
}

/**
 * Output local node parameter error
 */
function outputDtmfLocalNodeError(): void {
    die("Please provide a local node identifier.\n");
}

/**
 * Output session configuration error
 */
function outputDtmfSessionError(): void {
    die("User session or INI configuration function (get_ini_name) not available.\n");
}

/**
 * Output INI file not found error
 * 
 * @param string $iniFilePath Path to the INI file
 */
function outputDtmfIniNotFoundError(string $iniFilePath): void {
    die("Couldn't load INI file: " . htmlspecialchars($iniFilePath) . "\n");
}

/**
 * Output INI parse error
 * 
 * @param string $iniFilePath Path to the INI file
 */
function outputDtmfIniParseError(string $iniFilePath): void {
    die("Error parsing INI file: " . htmlspecialchars($iniFilePath) . "\n");
}

/**
 * Output node not found error
 * 
 * @param string $localnode Local node identifier
 * @param string $iniFilePath Path to the INI file
 */
function outputDtmfNodeNotFoundError(string $localnode, string $iniFilePath): void {
    die("Node " . htmlspecialchars($localnode) . " is not in " . htmlspecialchars($iniFilePath) . " file.\n");
}

/**
 * Output incomplete configuration error
 * 
 * @param string $localnode Local node identifier
 */
function outputDtmfIncompleteConfigError(string $localnode): void {
    die("Incomplete configuration for node " . htmlspecialchars($localnode) . " in INI file (missing host, user, or passwd).\n");
}

/**
 * Output AMI client not found error
 */
function outputDtmfAmiClientNotFoundError(): void {
    die("AMI Client class (SimpleAmiClient) not found. Check amifunctions.inc.\n");
}

/**
 * Output AMI connection error
 * 
 * @param string $host AMI host
 */
function outputDtmfAmiConnectionError(string $host): void {
    die("Could not connect to Asterisk Manager at host: " . htmlspecialchars($host) . "\n");
}

/**
 * Output AMI login error
 * 
 * @param string $user AMI user
 */
function outputDtmfAmiLoginError(string $user): void {
    die("Could not login to Asterisk Manager for user: " . htmlspecialchars($user) . "\n");
}

/**
 * Output command execution error
 * 
 * @param string $dtmf DTMF command
 * @param string $localnode Local node identifier
 */
function outputDtmfCommandError(string $dtmf, string $localnode): void {
    die("Error executing DTMF command '" . htmlspecialchars($dtmf) . "' on node " . htmlspecialchars($localnode) . ". Asterisk reported an error or timed out.\n");
}

/**
 * Output unexpected error
 * 
 * @param string $message Error message
 */
function outputDtmfUnexpectedError(string $message): void {
    die("An unexpected error occurred during AMI communication: " . htmlspecialchars($message) . "\n");
}

/**
 * Output successful command result
 * 
 * @param string $dtmf DTMF command
 * @param string $localnode Local node identifier
 * @param string $commandOutput Command output
 */
function outputDtmfSuccess(string $dtmf, string $localnode, string $commandOutput): void {
    if (empty(trim($commandOutput))) {
        print "<b>DTMF command '" . htmlspecialchars($dtmf) . "' executed successfully on node " . htmlspecialchars($localnode) . "</b>";
    } else {
        print "<b>DTMF command '" . htmlspecialchars($dtmf) . "' executed on node " . htmlspecialchars($localnode) . "</b><br>";
        print "<pre>" . htmlspecialchars($commandOutput) . "</pre>";
    }
}
