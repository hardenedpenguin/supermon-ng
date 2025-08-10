<?php
/**
 * Supermon-ng ExtNodes UI
 * 
 * Provides the HTML rendering and content display components for the external nodes interface.
 * Handles page generation and file content display.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Generate access denied page
 */
function generateExtnodesAccessDenied(): void {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Access Denied</title>
    </head>
    <body>
        <h3>ERROR: You Must login to use this function!</h3>
    </body>
    </html>
    <?php
}

/**
 * Generate extnodes page header
 */
function generateExtnodesHeader(): void {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>AllStar rpt_extnodes contents</title>
    </head>
    <body>
    <pre>
    <?php
}

/**
 * Generate file header display
 * 
 * @param string $filePath Path to the file
 */
function generateExtnodesFileHeader(string $filePath): void {
    echo "File: " . htmlspecialchars($filePath, ENT_QUOTES, 'UTF-8') . "\n";
    echo "-----------------------------------------------------------------\n";
}

/**
 * Generate file content display
 * 
 * @param string $filePath Path to the file
 * @param string|false $fileContent File content or false on failure
 */
function generateExtnodesFileContent(string $filePath, $fileContent): void {
    if ($fileContent !== false) {
        echo $fileContent;
    } else {
        echo "\n\nAllStar rpt_extnodes table is not available.\n";
    }
}

/**
 * Generate extnodes page footer
 */
function generateExtnodesFooter(): void {
    ?>
    </pre>
    </body>
    </html>
    <?php
}
