<?php
/**
 * Supermon-ng Linux Log Controller
 * 
 * Main controller for the Linux log functionality that orchestrates authentication,
 * validation, command execution, and UI rendering.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include_once "includes/linuxlog/linuxlog-auth.inc";
include_once "includes/linuxlog/linuxlog-ui.inc";

/**
 * Initialize and run the Linux log system
 * 
 * This is the main entry point for the Linux log functionality.
 * Handles authentication, validation, command execution, and UI rendering.
 * 
 * @param string $log_description Description for the log page
 * @return void
 */
function runLinuxlog(string $log_description): void {
    // Output the page head
    outputLinuxlogHead($log_description);

    // Validate user access
    if (!validateLinuxlogAccess()) {
        outputLinuxlogAccessDenied();
    } else {
        // Show the log description as a heading on the page
        outputLinuxlogTitle($log_description);

        // Validate required command configuration
        if (!validateLinuxlogCommands()) {
            outputLinuxlogConfigError();
        } else {
            // Get and execute the Linux log command
            $command = getLinuxlogCommand();
            executeLinuxlogCommand($command);
        }
    }

    // Output the page footer
    outputLinuxlogFooter();
}
