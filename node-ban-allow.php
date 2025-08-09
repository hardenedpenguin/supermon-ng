<?php
/**
 * Node Ban/Allow Management
 * 
 * Provides interface for managing AllStar node access control lists.
 * Allows adding/removing nodes from allowlist and denylist via AMI database operations.
 * Modularized for better maintainability and security.
 */

// Include modular node ban/allow components
include("includes/node-ban-allow/ban-config.inc");
include("includes/node-ban-allow/ban-ami.inc");
include("includes/node-ban-allow/ban-processor.inc");
include("includes/node-ban-allow/ban-ui.inc");
include("includes/node-ban-allow/ban-display.inc");

// Initialize ban/allow configuration and security
list($Node, $localnode, $config, $fp) = initializeBanAllow();

// Process form submission if present
processBanAllowForm($fp, $localnode);

// Render HTML head
renderBanAllowHead($localnode);

// Start page body
renderBanAllowBodyStart($localnode);

// Render the ban/allow form
renderBanAllowForm($localnode, $Node);

// Display current deny and allow lists
displayDenyList($fp, $localnode);
displayAllowList($fp, $localnode);

// Render page footer
renderBanAllowFooter();

// Cleanup AMI connection
cleanupBanAllowAMI($fp);
?>