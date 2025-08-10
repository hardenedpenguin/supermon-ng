<?php
/**
 * Supermon-ng Control Server Favorites UI
 * 
 * Provides the output formatting and error display components for the control server favorites.
 * Handles command result display and error message formatting with enhanced styling.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Output access denied error message
 */
function outputControlserverfavsAccessDenied(): void {
    die("<br><h3>ERROR: You must login to use these functions!</h3>");
}

/**
 * Output parameter validation error message
 */
function outputControlserverfavsParameterError(): void {
    die("<br><h3>ERROR: 'node' and 'cmd' parameters are required.</h3>");
}

/**
 * Output INI file not found error message
 * 
 * @param string $supIniFile Path to the INI file
 */
function outputControlserverfavsIniNotFound(string $supIniFile): void {
    die("ERROR: Configuration file '$supIniFile' could not be found.");
}

/**
 * Output INI file parsing error message
 * 
 * @param string $supIniFile Path to the INI file
 */
function outputControlserverfavsIniParseError(string $supIniFile): void {
    die("ERROR: Could not parse configuration file '$supIniFile'.");
}

/**
 * Output node not found error message
 * 
 * @param string $node Node name
 * @param string $supIniFile Path to the INI file
 */
function outputControlserverfavsNodeNotFound(string $node, string $supIniFile): void {
    die("ERROR: Node '$node' is not defined in '$supIniFile'.");
}

/**
 * Output runtime error message
 * 
 * @param string $message Error message
 */
function outputControlserverfavsRuntimeError(string $message): void {
    die("<br><h3>ERROR: " . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</h3>");
}

/**
 * Output successful command result with enhanced formatting
 * 
 * @param string $cmdString Command string
 * @param string $commandOutput Command result
 */
function outputControlserverfavsResult(string $cmdString, string $commandOutput): void {
    print "<pre>\n===== " . htmlspecialchars($cmdString, ENT_QUOTES, 'UTF-8') . " =====\n";
    print htmlspecialchars($commandOutput, ENT_QUOTES, 'UTF-8');
    print "\n===== end =====\n</pre>\n";
}
