<?php
/**
 * Supermon-ng Save Controller
 * 
 * Main controller for the save functionality that orchestrates authentication,
 * validation, file saving, and UI rendering.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include_once "includes/save/save-auth.inc";
include_once "includes/save/save-ui.inc";

/**
 * Initialize and run the save system
 * 
 * This is the main entry point for the save functionality.
 * Handles authentication, validation, file saving, and UI rendering.
 * 
 * @return void
 */
function runSave(): void {
    // Validate user access
    if (!validateSaveAccess()) {
        die ("<br><h3>ERROR: You Must login to use the 'Save' function!</h3>");
    }

    // Get and validate save parameters
    $params = getValidatedSaveParams();
    if ($params === false) {
        die("<br><h3>ERROR: Invalid file path specified for saving.</h3>");
    }

    $target_filepath = $params['filepath'];
    $new_content = $params['content'];

    // Execute file save operation
    $saveResults = executeFileSave($target_filepath, $new_content);

    // Output the complete save interface
    outputSaveHead();
    outputSaveStatus($target_filepath, $saveResults);
    outputEditAgainForm($target_filepath);
    outputReturnToListForm();
    outputSaveFooter();
}
