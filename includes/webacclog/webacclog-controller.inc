<?php
/**
 * Supermon-ng Web Access Log Controller
 * 
 * Main controller for the web access log functionality that orchestrates authentication,
 * validation, log file reading, and UI rendering.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

include_once "includes/webacclog/webacclog-auth.inc";
include_once "includes/webacclog/webacclog-ui.inc";

/**
 * Initialize and run the web access log system
 * 
 * This is the main entry point for the web access log functionality.
 * Handles authentication, validation, log file reading, and UI rendering.
 * 
 * @return void
 */
function runWebacclog(): void {
    // Output the page head
    outputWebacclogHead();

    // Validate user access
    if (!validateWebacclogAccess()) {
        outputWebacclogAccessDenied();
    } else {
        // Get and validate log file path
        $file = getValidatedWebacclogPath();
        if ($file === false) {
            outputWebacclogInvalidPath();
        } else {
            // Show log file info
            outputWebacclogFileInfo($file);

            // Check if WEB_ACCESS_LOG is defined
            global $WEB_ACCESS_LOG;
            if (isset($WEB_ACCESS_LOG) && !empty($WEB_ACCESS_LOG)) {
                // Read log content
                $logLines = readWebacclogContent($file);
                
                if ($logLines && count($logLines) > 0) {
                    // Display log content in table
                    outputWebacclogTable($logLines);
                } else {
                    outputWebacclogNoEntries();
                }
            } else {
                outputWebacclogPathError();
            }
        }
    }

    // Output the page footer
    outputWebacclogFooter();
}
