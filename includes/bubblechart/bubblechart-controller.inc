<?php
/**
 * Supermon-ng BubbleChart Controller
 * 
 * Main controller for the bubblechart functionality that orchestrates authentication,
 * parameter validation, and bubble chart display.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include_once "includes/bubblechart/bubblechart-auth.inc";
include_once "includes/bubblechart/bubblechart-ui.inc";

/**
 * Initialize and run the bubblechart system
 * 
 * This is the main entry point for the bubblechart functionality.
 * Handles authentication, parameter validation, and bubble chart display.
 * 
 * @return void
 */
function runBubblechart(): void {
    // Validate user access
    if (!validateBubblechartAccess()) {
        outputBubblechartAccessDenied();
    }

    // Get node information
    $nodeInfo = getBubblechartNodeInfo();
    $node_to_use = $nodeInfo['node'];
    $message = $nodeInfo['message'];

    // Build stats URL
    $statsUrl = buildBubblechartStatsUrl($node_to_use);

    // Generate output
    generateBubblechartOutput($message, $statsUrl);
}
