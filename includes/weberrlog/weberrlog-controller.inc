<?php
/**
 * Supermon-ng Web Error Log Controller
 * 
 * Main controller for the web error log functionality that orchestrates authentication,
 * validation, log file reading, parsing, and UI rendering.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include_once "includes/weberrlog/weberrlog-auth.inc";
include_once "includes/weberrlog/weberrlog-ui.inc";

/**
 * Initialize and run the web error log system
 * 
 * This is the main entry point for the web error log functionality.
 * Handles authentication, validation, log file reading, parsing, and UI rendering.
 * 
 * @return void
 */
function runWeberrlog(): void {
    // Output the page head
    outputWeberrlogHead();

    // Validate user access
    if (!validateWeberrlogAccess()) {
        outputWeberrlogAccessDenied();
    } else {
        // Get and validate log file path
        $logFilePath = getValidatedWeberrlogPath();
        if ($logFilePath === false) {
            outputWeberrlogPathError();
        } else {
            // Validate log file exists and is readable
            $fileStatus = validateWeberrlogFile($logFilePath);
            if (!$fileStatus['readable']) {
                outputWeberrlogFileError($fileStatus['error']);
            } else {
                // Show log file info
                outputWeberrlogFileInfo($logFilePath);

                // Read log file content
                $lines = readWeberrlogContent($logFilePath);
                if ($lines === false) {
                    outputWeberrlogReadError();
                } elseif (count($lines) === 0) {
                    outputWeberrlogEmpty();
                } else {
                    // Parse and display log content
                    $parsedData = parseWeberrlogLines($lines);
                    outputWeberrlogTable($parsedData);
                }
            }
        }
    }

    // Output the page footer
    outputWeberrlogFooter();
}
