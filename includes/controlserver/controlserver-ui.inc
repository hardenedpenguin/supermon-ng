<?php
/**
 * Supermon-ng Control Server UI
 * 
 * Provides the output formatting and error display components for the control server.
 * Handles command result display and error message formatting.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Output access denied error message
 */
function outputControlserverAccessDenied(): void {
    die("<br><h3>ERROR: You Must login to use these functions!</h3>");
}

/**
 * Output parameter validation error message
 */
function outputControlserverParameterError(): void {
    die("Error: 'node' and 'cmd' parameters are required.");
}

/**
 * Output INI file not found error message
 * 
 * @param string $iniFilePath Path to the INI file
 */
function outputControlserverIniNotFound(string $iniFilePath): void {
    die("Couldn't load INI file: " . htmlspecialchars($iniFilePath));
}

/**
 * Output INI file parsing error message
 * 
 * @param string $iniFilePath Path to the INI file
 */
function outputControlserverIniParseError(string $iniFilePath): void {
    die("Error parsing INI file: " . htmlspecialchars($iniFilePath));
}

/**
 * Output node not found error message
 * 
 * @param string $node Node name
 * @param string $iniFilePath Path to the INI file
 */
function outputControlserverNodeNotFound(string $node, string $iniFilePath): void {
    die("Node " . htmlspecialchars($node) . " is not in INI file: " . htmlspecialchars($iniFilePath));
}

/**
 * Output incomplete configuration error message
 * 
 * @param string $node Node name
 * @param string $iniFilePath Path to the INI file
 */
function outputControlserverIncompleteConfig(string $node, string $iniFilePath): void {
    die("Node " . htmlspecialchars($node) . " configuration is incomplete (missing host, user, or passwd) in " . htmlspecialchars($iniFilePath) . ".");
}

/**
 * Output connection error message
 * 
 * @param string $host Host address
 */
function outputControlserverConnectionError(string $host): void {
    die("Could not connect to host: " . htmlspecialchars($host));
}

/**
 * Output login error message
 * 
 * @param string $host Host address
 * @param string $user Username
 */
function outputControlserverLoginError(string $host, string $user): void {
    die("Could not login to AMI on " . htmlspecialchars($host) . " with user: " . htmlspecialchars($user));
}

/**
 * Output command execution error message
 * 
 * @param string $cmdString Command string
 * @param string $node Node name
 */
function outputControlserverCommandError(string $cmdString, string $node): void {
    die("Failed to execute command '" . htmlspecialchars($cmdString) . "' or received an error from node " . htmlspecialchars($node) . ".");
}

/**
 * Output successful command result
 * 
 * @param string $cmdString Command string
 * @param string $result Command result
 */
function outputControlserverResult(string $cmdString, string $result): void {
    print "<pre>\n===== " . htmlspecialchars($cmdString) . " =====\n";
    print htmlspecialchars($result);
    print "</pre>\n";
}
