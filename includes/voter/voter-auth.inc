<?php
/**
 * Supermon-ng Voter Authentication
 * 
 * Handles parameter validation and node processing for voter functionality.
 * Provides input validation, node parsing, and data preparation for voter display.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Get and validate voter node parameter
 * 
 * @return string|false Validated node input or false on failure
 */
function getVoterNodeInput(): string|false {
    $nodeInput = isset($_GET['node']) ? trim(strip_tags($_GET['node'])) : '';
    
    if (empty($nodeInput)) {
        return false;
    }
    
    return $nodeInput;
}

/**
 * Parse and validate voter nodes
 * 
 * @param string $nodeInput Raw node input string
 * @return array|false Array of validated nodes or false on failure
 */
function parseVoterNodes(string $nodeInput): array|false {
    $passedNodes = array_filter(explode(',', $nodeInput), 'strlen');
    
    if (empty($passedNodes)) {
        return false;
    }
    
    return array_values($passedNodes);
}

/**
 * Prepare JavaScript nodes array
 * 
 * @param array $nodes Array of validated nodes
 * @return array JavaScript-safe nodes array
 */
function prepareVoterJsNodes(array $nodes): array {
    return array_values($nodes);
}

/**
 * Get safe node for HTML output
 * 
 * @param string $node Node number
 * @return string HTML-safe node string
 */
function getVoterSafeNode(string $node): string {
    return htmlspecialchars($node, ENT_QUOTES, 'UTF-8');
}
