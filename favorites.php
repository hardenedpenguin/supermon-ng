<?php
/**
 * Supermon-ng Favorites Panel
 * 
 * Provides a web-based interface for managing user favorites and quick commands.
 * Allows authenticated users to execute predefined commands on specific nodes
 * through a user-friendly favorites panel.
 * 
 * Features:
 * - User authentication and authorization (FAVUSER permission required)
 * - Node-specific favorites configuration
 * - Dynamic command loading from favorites INI files
 * - AJAX-based command execution
 * - Responsive web interface
 * 
 * Security: Requires FAVUSER permission
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("authusers.php");
include("includes/common.inc");
include("user_files/global.inc");
include("favini.php");
include("includes/favorites/favorites-controller.inc");

// Run the favorites system
runFavorites();