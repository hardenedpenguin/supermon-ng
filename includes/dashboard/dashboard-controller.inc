<?php
/**
 * Supermon-ng Dashboard Controller
 * 
 * Main controller for the dashboard that orchestrates the display
 * of welcome content, instructions, and system information.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include_once "includes/dashboard/dashboard-content.inc";

/**
 * Initialize and display the main dashboard
 * 
 * This is the main entry point for the dashboard functionality.
 * Displays the welcome page with system information and instructions.
 * 
 * @return void
 */
function displayDashboard(): void {
    // Output all dashboard content sections
    outputDashboardWelcome();
    outputNodeInstructions();
    outputVoterInfo();
    outputSystemChanges();
}
