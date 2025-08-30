<?php
/**
 * Supermon-ng Control Panel
 * 
 * Provides a web-based control panel for managing AllStar nodes.
 * Allows authenticated users to send commands to specific nodes
 * through a user-friendly interface.
 * 
 * Features:
 * - User authentication and authorization
 * - Node-specific command execution
 * - Dynamic command loading from configuration
 * - AJAX-based command execution
 * - Responsive web interface
 * 
 * Security: Requires CTRLUSER permission
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("user_files/global.inc");
include("includes/common.inc");
include("authusers.php");
include("authini.php");
include("includes/controlpanel/controlpanel-controller.inc");

// Run the control panel system
runControlPanel();
