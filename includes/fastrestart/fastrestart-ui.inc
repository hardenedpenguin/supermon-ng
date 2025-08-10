<?php
/**
 * Supermon-ng FastRestart UI
 * 
 * Provides the output formatting and error display components for the fast restart interface.
 * Handles command result display and error message formatting.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Set HTML content type header
 */
function setFastrestartContentType(): void {
    header('Content-Type: text/html');
}

/**
 * Output access denied error
 */
function outputFastrestartAccessDenied(): void {
    setFastrestartContentType();
    print "<br><h3>ERROR: You must login and be authorized to use the 'RESTART' function!</h3>";
    exit;
}

/**
 * Output localnode parameter error
 */
function outputFastrestartLocalnodeError(): void {
    setFastrestartContentType();
    die("<b>Error: 'localnode' parameter is missing or empty.</b>");
}

/**
 * Output INI file not found error
 * 
 * @param string $iniFilePath Path to the INI file
 */
function outputFastrestartIniNotFoundError(string $iniFilePath): void {
    setFastrestartContentType();
    die("<b>Error: Configuration file '" . htmlspecialchars($iniFilePath) . "' not found.</b>");
}

/**
 * Output INI parse error
 * 
 * @param string $iniFilePath Path to the INI file
 */
function outputFastrestartIniParseError(string $iniFilePath): void {
    setFastrestartContentType();
    die("<b>Error: Could not parse configuration file '" . htmlspecialchars($iniFilePath) . "'. Check its syntax.</b>");
}

/**
 * Output node not found error
 * 
 * @param string $localnode Local node identifier
 * @param string $iniFilePath Path to the INI file
 */
function outputFastrestartNodeNotFoundError(string $localnode, string $iniFilePath): void {
    setFastrestartContentType();
    die("<b>Error: Node '" . htmlspecialchars($localnode) . "' is not defined in '" . htmlspecialchars($iniFilePath) . "'.</b>");
}

/**
 * Output incomplete configuration error
 * 
 * @param string $localnode Local node identifier
 * @param string $iniFilePath Path to the INI file
 * @param string $missingKey Missing configuration key
 */
function outputFastrestartIncompleteConfigError(string $localnode, string $iniFilePath, string $missingKey): void {
    setFastrestartContentType();
    die("<b>Error: Missing or empty configuration key '{$missingKey}' for node '" . htmlspecialchars($localnode) . "' in '" . htmlspecialchars($iniFilePath) . "'.</b>");
}

/**
 * Output AMI connection error
 * 
 * @param string $host AMI host
 */
function outputFastrestartAmiConnectionError(string $host): void {
    die("<b>Error: Could not connect to Asterisk Manager on host '" . htmlspecialchars($host) . "'. Check AMI settings and Asterisk status.</b>");
}

/**
 * Output AMI login error
 * 
 * @param string $localnode Local node identifier
 */
function outputFastrestartAmiLoginError(string $localnode): void {
    die("<b>Error: Could not login to Asterisk Manager for node '" . htmlspecialchars($localnode) . "'. Check AMI credentials.</b>");
}

/**
 * Output command execution error
 * 
 * @param string $localnode Local node identifier
 */
function outputFastrestartCommandError(string $localnode): void {
    die("<b>Error: Failed to send the restart command to Asterisk for node '" . htmlspecialchars($localnode) . "'. Asterisk might not have received the command or the AMI action failed.</b>");
}

/**
 * Output unexpected error
 * 
 * @param string $message Error message
 */
function outputFastrestartUnexpectedError(string $message): void {
    setFastrestartContentType();
    die("<b>An unexpected error occurred: " . htmlspecialchars($message) . "</b>");
}

/**
 * Output successful restart message
 * 
 * @param string $localnode Local node identifier
 */
function outputFastrestartSuccess(string $localnode): void {
    setFastrestartContentType();
    print "<b>Fast Restarting Asterisk Now at '" . htmlspecialchars($localnode) . "'. Check Asterisk logs for confirmation.</b>";
}
