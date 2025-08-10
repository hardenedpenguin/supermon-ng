<?php
/**
 * Supermon-ng Connect UI
 * 
 * Provides the JSON response formatting and error display components for the connect interface.
 * Handles AJAX response formatting and error message display.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Output access denied JSON response
 */
function outputConnectAccessDenied(): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please login to use connect/disconnect functions.']);
    exit;
}

/**
 * Output parameter validation error JSON response
 */
function outputConnectParameterError(): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please provide a valid local node number.']);
    exit;
}

/**
 * Output INI file not found error JSON response
 * 
 * @param string $SUPINI Path to the INI file
 */
function outputConnectIniNotFound(string $SUPINI): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "Couldn't load $SUPINI file."]);
    exit;
}

/**
 * Output node configuration not found error JSON response
 * 
 * @param string $localnode Local node number
 * @param string $SUPINI Path to the INI file
 */
function outputConnectNodeNotFound(string $localnode, string $SUPINI): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "Configuration for local node $localnode not found in $SUPINI."]);
    exit;
}

/**
 * Output AMI connection error JSON response
 * 
 * @param string $localnode Local node number
 */
function outputConnectAmiConnectionError(string $localnode): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "Could not connect to Asterisk Manager on host specified for node $localnode."]);
    exit;
}

/**
 * Output AMI login error JSON response
 * 
 * @param string $localnode Local node number
 */
function outputConnectAmiLoginError(string $localnode): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "Could not login to Asterisk Manager for node $localnode."]);
    exit;
}

/**
 * Output unauthorized action error JSON response
 * 
 * @param string $button Action button name
 */
function outputConnectUnauthorizedError(string $button): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "You are not authorized to perform the '$button' action."]);
    exit;
}

/**
 * Output invalid action error JSON response
 * 
 * @param string $button Action button name
 */
function outputConnectInvalidActionError(string $button): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "Invalid action specified: '$button'."]);
    exit;
}

/**
 * Output ilink command error JSON response
 */
function outputConnectIlinkError(): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: Action determined but ilink command number not set. No command sent.']);
    exit;
}

/**
 * Output successful action JSON response
 * 
 * @param string $message Success message
 */
function outputConnectSuccess(string $message): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => $message]);
}
