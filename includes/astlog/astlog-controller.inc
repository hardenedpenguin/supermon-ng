<?php
/**
 * Supermon-ng Asterisk Log Controller
 * 
 * Main controller for the Asterisk log functionality that orchestrates authentication,
 * validation, log file reading, and UI rendering.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include_once "includes/astlog/astlog-auth.inc";
include_once "includes/astlog/astlog-ui.inc";

/**
 * Initialize and run the Asterisk log system
 * 
 * This is the main entry point for the Asterisk log functionality.
 * Handles authentication, validation, log file reading, and UI rendering.
 * 
 * @return void
 */
function runAstlog(): void {
    // Output the page head and header
    outputAstlogHead();
    outputAstlogHeader();

    // Validate user access
    if (!validateAstlogAccess()) {
        outputAstlogAccessDenied();
    } else {
        // Get and validate log file path
        $file = getValidatedAstlogPath();
        if ($file === false) {
            outputAstlogConfigError();
        } else {
            // Read log file content
            $content = readAstlogContent($file);
            outputAstlogContent($file, $content);
        }
    }

    // Output the page footer
    outputAstlogFooter();
}
