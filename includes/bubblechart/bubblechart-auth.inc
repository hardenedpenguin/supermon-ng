<?php
/**
 * Supermon-ng BubbleChart Authentication
 * 
 * Handles authentication, authorization, and validation for bubble chart operations.
 * Provides user access control and parameter validation.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Validate user access to bubblechart function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateBubblechartAccess(): bool {
    if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("BUBLUSER"))) {
        return false;
    }
    
    return true;
}

/**
 * Get and validate node parameters
 * 
 * @return array Array with node information and message
 */
function getBubblechartNodeInfo(): array {
    $node_from_post = trim(strip_tags($_POST['node'] ?? ''));
    $localnode_from_post = trim(strip_tags($_POST['localnode'] ?? ''));
    
    $node_to_use = '';
    $message = '';
    
    if ($node_from_post === '') {
        $node_to_use = $localnode_from_post;
    } else {
        $node_to_use = $node_from_post;
        $message = "<b>Opening Bubble Chart for node " . htmlspecialchars($node_from_post) . "</b>";
    }
    
    return [
        'node' => $node_to_use,
        'message' => $message
    ];
}

/**
 * Build stats URL
 * 
 * @param string $node Node number
 * @return string Complete stats URL
 */
function buildBubblechartStatsUrl(string $node): string {
    $stats_base_url = "http://stats.allstarlink.org/getstatus.cgi";
    return $stats_base_url . "?" . urlencode($node);
}
