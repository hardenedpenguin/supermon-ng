<?php
/**
 * Supermon-ng CPU Statistics Controller
 * 
 * Main controller for the CPU statistics functionality that orchestrates authentication,
 * command execution, and UI rendering.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

include_once "includes/cpustats/cpustats-auth.inc";
include_once "includes/cpustats/cpustats-ui.inc";

/**
 * Initialize and run the CPU statistics system
 * 
 * This is the main entry point for the CPU statistics functionality.
 * Handles authentication, command execution, and UI rendering.
 * 
 * @return void
 */
function runCpustats(): void {
    // Output the page head
    outputCpustatsHead();

    // Validate user access
    if (!validateCpustatsAccess()) {
        outputCpustatsAccessDenied();
    } else {
        // Get commands to execute
        $commands = getCpustatsCommands();
        
        // Execute commands and build content
        $content = '';
        foreach ($commands as $command) {
            $output = executeCpustatsCommand($command);
            $content .= formatCpustatsOutput($command, $output);
        }
        
        // Output the formatted content
        outputCpustatsContent($content);
    }

    // Output the page footer
    outputCpustatsFooter();
}
