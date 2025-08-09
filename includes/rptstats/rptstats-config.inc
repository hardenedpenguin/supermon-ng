<?php
/**
 * RPT Stats Configuration and Security
 * 
 * Handles authentication, parameter validation, and initial setup
 * for AllStar rpt stats functionality.
 */

/**
 * Initialize RPT stats configuration and security
 * 
 * @return array Returns [$node_param, $localnode_param, $isAuthenticated] 
 */
function initializeRPTStatsConfig() {
    // Include required dependencies
    include("includes/session.inc");
    require_once("amifunctions.inc");
    include("authusers.php");
    include("includes/common.inc");
    include("authini.php");

    // Extract and validate parameters
    $node_param = isset($_GET['node']) ? (int)trim(strip_tags($_GET['node'])) : 0;
    $localnode_param = isset($_GET['localnode']) ? (int)trim(strip_tags($_GET['localnode'])) : 0;

    // Check authentication
    $isAuthenticated = (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && get_user_auth("RSTATUSER"));

    return [$node_param, $localnode_param, $isAuthenticated];
}
