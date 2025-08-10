<?php
/**
 * Supermon-ng Login Controller
 * 
 * Main controller for the login system that orchestrates authentication,
 * session management, and UI rendering. Handles both AJAX and form-based requests.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include_once "includes/login/login-processor.inc";
include_once "includes/login/login-ui.inc";

/**
 * Initialize and run the login system
 * 
 * This is the main entry point for the login functionality.
 * Handles POST requests for authentication and GET requests for the login form.
 * 
 * @return void
 */
function runLoginSystem(): void {
    // Handle login form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        processLogin();
        // If we reach here, it means there was an error (AJAX requests exit early)
        $error_message = "Login failed. Please try again.";
    } else {
        $error_message = null;
    }

    // Include header for consistent styling
    include "header.inc";
    
    // Output CSS and form
    outputLoginCSS();
    outputLoginForm($error_message);
    
    include "footer.inc";
}
