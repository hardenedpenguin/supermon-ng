<?php
/**
 * Supermon-ng AstNodes Controller
 * 
 * Main controller for the astnodes functionality that orchestrates authentication,
 * file validation, and content display for Asterisk database file viewing.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include_once "includes/astnodes/astnodes-auth.inc";
include_once "includes/astnodes/astnodes-ui.inc";

/**
 * Initialize and run the astnodes system
 * 
 * This is the main entry point for the astnodes functionality.
 * Handles authentication, file validation, and content display.
 * 
 * @return void
 */
function runAstnodes(): void {
    // Validate configuration
    if (!validateAstnodesConfig()) {
        outputAstnodesConfigError();
    }

    // Generate styles
    generateAstnodesStyles();

    // Generate page header
    generateAstnodesHeader();

    // Validate user access
    if (validateAstnodesAccess()) {
        $filePath = getAstnodesFilePath();
        
        // Generate file header
        generateAstnodesFileHeader($filePath);
        
        // Validate and read file
        if (validateAstnodesFile($filePath)) {
            $fileContent = readAstnodesFileContent($filePath);
            generateAstnodesFileContent($filePath, $fileContent);
        } else {
            generateAstnodesFileNotFoundError($filePath);
        }
    } else {
        generateAstnodesAccessDenied();
    }

    // Generate page footer
    generateAstnodesFooter();
}
