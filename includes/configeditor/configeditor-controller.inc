<?php
/**
 * Supermon-ng Configuration Editor Controller
 * 
 * Main controller for the configuration editor functionality that orchestrates authentication,
 * file list generation, and UI rendering.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include_once "includes/configeditor/configeditor-auth.inc";
include_once "includes/configeditor/configeditor-ui.inc";

/**
 * Initialize and run the configuration editor system
 * 
 * This is the main entry point for the configuration editor functionality.
 * Handles authentication, file list generation, and UI rendering.
 * 
 * @return void
 */
function runConfigeditor(): void {
    // Output the page head
    outputConfigeditorHead();

    // Validate user access
    if (!validateConfigeditorAccess()) {
        outputConfigeditorAccessDenied();
    } else {
        // Get the list of configuration files
        $files_to_list = getConfigeditorFiles();
        
        // Output the header with call sign
        global $CALL;
        outputConfigeditorHeader($CALL);
        
        // Output the file selection form
        outputConfigeditorFileForm($files_to_list);
    }

    // Output the page footer
    outputConfigeditorFooter();
}
