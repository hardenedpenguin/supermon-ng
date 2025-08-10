<?php
/**
 * Supermon-ng ExtNodes Controller
 * 
 * Main controller for the extnodes functionality that orchestrates authentication,
 * file validation, and content display for external nodes viewing.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include_once "includes/extnodes/extnodes-auth.inc";
include_once "includes/extnodes/extnodes-ui.inc";

/**
 * Initialize and run the extnodes system
 * 
 * This is the main entry point for the extnodes functionality.
 * Handles authentication, file validation, and content display.
 * 
 * @return void
 */
function runExtnodes(): void {
    // Validate user access
    if (!validateExtnodesAccess()) {
        generateExtnodesAccessDenied();
        exit;
    }

    // Get file path
    $filePath = getExtnodesFilePath();
    
    // Generate page header
    generateExtnodesHeader();
    
    // Generate file header
    generateExtnodesFileHeader($filePath);
    
    // Validate and read file
    if (validateExtnodesFile($filePath)) {
        $fileContent = readExtnodesFileContent($filePath);
        generateExtnodesFileContent($filePath, $fileContent);
    } else {
        generateExtnodesFileContent($filePath, false);
    }
    
    // Generate page footer
    generateExtnodesFooter();
}
