<?php
/**
 * Supermon-ng IRLP Log Controller
 * 
 * Main controller for the IRLP log functionality that orchestrates authentication,
 * validation, log file reading, and UI rendering.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

include_once "includes/irlplog/irlplog-auth.inc";
include_once "includes/irlplog/irlplog-ui.inc";

/**
 * Initialize and run the IRLP log system
 * 
 * This is the main entry point for the IRLP log functionality.
 * Handles authentication, validation, log file reading, and UI rendering.
 * 
 * @return void
 */
function runIrlplog(): void {
    // Output the page head
    outputIrlplogHead();

    // Validate user access
    if (!validateIrlplogAccess()) {
        outputIrlplogAccessDenied();
    } else {
        // Get and validate log file path
        $file = getValidatedIrlplogPath();
        if ($file === false) {
            echo '<p class="log-viewer-error">Error: IRLP log file path variable ($IRLP_LOG) is not defined in global.inc.</p>';
        } else {
            // Read and output log content
            $content = readIrlplogContent($file);
            outputIrlplogContent($file, $content);
        }
    }

    // Output the page footer
    outputIrlplogFooter();
}
