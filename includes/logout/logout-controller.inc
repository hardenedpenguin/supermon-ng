<?php
/**
 * Supermon-ng Logout Controller
 * 
 * Main controller for the logout functionality that orchestrates session termination,
 * security cleanup, and UI generation for secure user logout.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include_once "includes/logout/logout-auth.inc";
include_once "includes/logout/logout-ui.inc";

/**
 * Initialize and run the logout system
 * 
 * This is the main entry point for the logout functionality.
 * Handles session termination, security cleanup, and UI generation.
 * 
 * @return void
 */
function runLogout(): void {
    // Perform complete logout process
    performLogout();
    
    // Generate styles
    generateLogoutStyles();
    
    // Generate logout content
    generateLogoutContent();
    
    // Generate JavaScript
    generateLogoutJavaScript();
}
