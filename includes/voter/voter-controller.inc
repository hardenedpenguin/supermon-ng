<?php
/**
 * Supermon-ng Voter Controller
 * 
 * Main controller for the voter functionality that orchestrates parameter validation,
 * node processing, and UI generation for real-time voter monitoring.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

include_once "includes/voter/voter-auth.inc";
include_once "includes/voter/voter-ui.inc";

/**
 * Initialize and run the voter system
 * 
 * This is the main entry point for the voter functionality.
 * Handles parameter validation, node processing, and UI generation.
 * 
 * @return void
 */
function runVoter(): void {
    // Get and validate node input
    $nodeInput = getVoterNodeInput();
    if ($nodeInput === false) {
        outputVoterParameterError();
    }

    // Parse and validate nodes
    $passedNodes = parseVoterNodes($nodeInput);
    if ($passedNodes === false) {
        outputVoterNodesError();
    }

    // Prepare JavaScript nodes array
    $jsNodesArray = prepareVoterJsNodes($passedNodes);

    // Close session for better performance
    session_write_close();

    // Generate JavaScript setup
    generateVoterJavaScript($jsNodesArray);

    // Generate voter containers
    generateVoterContainers($passedNodes);

    // Generate voter information
    generateVoterInfo();
}
