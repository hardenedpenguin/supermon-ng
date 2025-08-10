<?php
/**
 * Supermon-ng Supermon Log Controller
 * 
 * Main controller for the Supermon log functionality that orchestrates authentication,
 * validation, log file reading, and UI rendering.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

include_once "includes/smlog/smlog-auth.inc";
include_once "includes/smlog/smlog-ui.inc";

/**
 * Initialize and run the Supermon log system
 * 
 * This is the main entry point for the Supermon log functionality.
 * Handles authentication, validation, log file reading, and UI rendering.
 * 
 * @return void
 */
function runSmlog(): void {
    // Output the page head
    outputSmlogHead();

    // Validate user access
    if (!validateSmlogAccess()) {
        outputSmlogAccessDenied();
    } else {
        // Get and validate log file path
        $log_file_path = getValidatedSmlogPath();
        if ($log_file_path === false) {
            echo '<p class="log-viewer-error">Error: Supermon log file path variable ($SMLOGNAME) is not defined in global.inc.</p>';
        } else {
            // Output header
            outputSmlogHeader($log_file_path);
            
            // Read and output log content
            $log_content_array = readSmlogContent($log_file_path);
            outputSmlogContent($log_file_path, $log_content_array);
        }
    }

    // Output the page footer
    outputSmlogFooter();
}
