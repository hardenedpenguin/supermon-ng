<?php
/**
 * Supermon-ng BubbleChart UI
 * 
 * Provides the output formatting and JavaScript generation for the bubble chart interface.
 * Handles message display and popup window generation.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Output access denied error
 */
function outputBubblechartAccessDenied(): void {
    die("<br><h3>ERROR: You Must login to use the 'Bubble Chart' function!</h3>");
}

/**
 * Generate bubble chart output
 * 
 * @param string $message Message to display
 * @param string $statsUrl Stats URL for popup
 */
function generateBubblechartOutput(string $message, string $statsUrl): void {
    if ($message !== '') {
        echo $message . "<br />\n";
    }
    
    echo "<script>window.open('" . $statsUrl . "');</script>\n";
}
