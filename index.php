<?php
/**
 * Supermon-ng Main Dashboard
 * 
 * This is the main entry point for the Supermon-ng web interface.
 * Displays the welcome page with information about AllStar Link monitoring
 * and links to various node management functions.
 * 
 * Features:
 * - Welcome message and system information
 * - Instructions for using the interface
 * - Links to documentation and external resources
 * - Node status overview
 * 
 * Security: Public access (authentication handled by individual features)
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("includes/header.inc");
include("includes/dashboard/dashboard-controller.inc");

// Display the main dashboard
displayDashboard();

include "includes/footer.inc";
