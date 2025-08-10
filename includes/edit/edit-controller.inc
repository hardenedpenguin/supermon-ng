<?php
/**
 * Supermon-ng Edit Controller
 * 
 * Main controller for the edit functionality that orchestrates authentication,
 * validation, file reading, and UI rendering.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include_once "includes/edit/edit-auth.inc";
include_once "includes/edit/edit-ui.inc";

/**
 * Initialize and run the edit system
 * 
 * This is the main entry point for the edit functionality.
 * Handles authentication, validation, file reading, and UI rendering.
 * 
 * @return void
 */
function runEdit(): void {
    // Validate user access
    if (!validateEditAccess()) {
        die ("<br><h3>ERROR: You Must login to use the 'Edit' function!</h3>");
    }

    // Get and validate file parameter
    $file = getValidatedFile();
    if ($file === false) {
        die ("<br><h3>ERROR: No file specified for editing or invalid file path.</h3>");
    }

    // Check if file is view-only
    $isViewOnly = isViewOnlyFile($file);

    // Read file content
    $data = readFileContent($file);

    // Output the complete edit interface
    outputEditHead(basename($file));
    outputEditHeader($file);
    outputEditContent($data, $file, $isViewOnly);
    outputReturnForm();
    outputEditFooter();
}
