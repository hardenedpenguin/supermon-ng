<?php
/**
 * AllStar Statistics Page
 * 
 * Displays comprehensive AllStar node statistics including connections,
 * channels, network stats, and peer information via AMI.
 * Modularized for better maintainability and organization.
 */

// Include core dependencies
include("includes/session.inc");
include("includes/amifunctions.inc");
include("includes/common.inc");

// Include modular stats components
include("includes/stats/stats-config.inc");
include("includes/stats/stats-ui.inc");
include("includes/stats/stats-utils.inc");
include("includes/stats/stats-allstar.inc");
include("includes/stats/stats-channels.inc");

// Initialize stats page and get configuration
list($node, $config, $fp) = initializeStatsPage();

// Render HTML head and page structure
renderStatsHead();

// Render main statistics content
renderStatsContent($fp);

// Render footer and cleanup
renderStatsFooter();
cleanupStatsAMI($fp);
?>