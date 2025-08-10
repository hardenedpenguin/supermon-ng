<?php
/**
 * Supermon-ng Database Controller
 * 
 * Main controller for the database functionality that orchestrates authentication,
 * validation, AMI connection, data processing, and UI rendering.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

include_once "includes/database/database-auth.inc";
include_once "includes/database/database-processor.inc";
include_once "includes/database/database-ui.inc";

/**
 * Initialize and run the database system
 * 
 * This is the main entry point for the database functionality.
 * Handles authentication, validation, AMI connection, and data display.
 * 
 * @return void
 */
function runDatabase(): void {
    // Validate user access
    if (!validateDatabaseAccess()) {
        die ("<br><h3 class='error-message'>ERROR: You Must login to use the 'Database' function!</h3>");
    }

    // Get and validate localnode parameter
    $localnode = getValidatedLocalNode();
    if ($localnode === false) {
        die("<h3 class='error-message'>ERROR: 'localnode' parameter is required.</h3>");
    }

    // Load and validate user configuration
    $config = loadDatabaseConfig($_SESSION['user'], $localnode);
    if ($config === false) {
        die("<h3 class='error-message'>ERROR: Configuration file could not be loaded or node is not defined.</h3>");
    }

    // Establish AMI connection
    $fp = connectToAsteriskManager($config['amiConfig']);
    if ($fp === false) {
        die("<h3 class='error-message'>ERROR: Could not connect to or authenticate with Asterisk Manager.</h3>");
    }

    // Retrieve database content
    $databaseOutput = retrieveDatabaseContent($fp);
    
    // Process database output
    $dbEntries = processDatabaseOutput($databaseOutput);
    $processedOutput = ($databaseOutput === false) ? "" : trim($databaseOutput);

    // Output the complete database interface
    outputDatabaseHead($localnode);
    outputDatabaseHeader($localnode);
    outputDatabaseContent($databaseOutput, $dbEntries, $processedOutput);
    outputDatabaseFooter();
}
