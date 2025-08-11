<?php
/**
 * Supermon-ng AstNodes Controller
 * 
 * Main controller for the astnodes functionality that orchestrates authentication,
 * file validation, and content display for Asterisk database file viewing.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
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
        
        // Check view parameter
        $view = $_GET['view'] ?? 'all';
        
        if ($view === 'active') {
            // Show active nodes
            generateAstnodesActiveHeader();
            $activeNodes = getActiveNodes();
            generateAstnodesActiveContent($activeNodes);
        } else {
            // Show all nodes from file
            generateAstnodesFileHeader($filePath);
            
            // Validate and read file
            if (validateAstnodesFile($filePath)) {
                $fileContent = readAstnodesFileContent($filePath);
                generateAstnodesFileContent($filePath, $fileContent);
            } else {
                generateAstnodesFileNotFoundError($filePath);
            }
        }
    } else {
        generateAstnodesAccessDenied();
    }

    // Generate page footer
    generateAstnodesFooter();
}
