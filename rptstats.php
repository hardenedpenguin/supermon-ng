<?php
/**
 * AllStar RPT Stats Interface
 * 
 * Provides web interface for displaying AllStar repeater statistics.
 * Supports both local AMI stats retrieval and external AllStar Link stats.
 * Modularized for better maintainability and security.
 */

// Include modular RPT stats components
include("includes/rptstats/rptstats-config.inc");
include("includes/rptstats/rptstats-ami.inc");
include("includes/rptstats/rptstats-ui.inc");
include("includes/rptstats/rptstats-processor.inc");

// Initialize configuration and get parameters
list($node_param, $localnode_param, $isAuthenticated) = initializeRPTStatsConfig();

// Check authentication first
if (!$isAuthenticated) {
    renderAuthErrorPage($localnode_param, $node_param);
}

// Process based on parameters
if ($node_param > 0) {
    // Redirect to external AllStar Link stats
    header("Location: http://stats.allstarlink.org/stats/$node_param");
    exit();
} elseif ($localnode_param > 0) {
    // Process local node stats via AMI
    renderStatsPageHeader($localnode_param);
    processLocalNodeStats($localnode_param);
    renderStatsPageFooter();
} else {
    // No valid parameters provided
    renderParameterErrorPage();
}
?>