<?php
/**
 * Supermon-ng Configuration Editor
 * 
 * Provides a web-based interface for selecting and editing configuration files.
 * Allows authenticated users to choose from a comprehensive list of configuration
 * files for various systems including Supermon-ng, AllStar, IRLP, EchoLink, and DvSwitch.
 * 
 * Features:
 * - User authentication and authorization (CFGEDUSER permission required)
 * - Comprehensive file list with categorized sections
 * - Dynamic file existence checking for conditional display
 * - Support for multiple system configurations
 * - Refresh functionality and window management
 * - Integration with edit.php for file editing
 * - Organized file categories with visual separators
 * 
 * Security: Requires CFGEDUSER permission for configuration file access
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("authusers.php");
include("user_files/global.inc");
include("includes/common.inc");
include("includes/configeditor/configeditor-controller.inc");

$SUPERMON_DIR = "/var/www/html/supermon-ng";

// Run the configuration editor system
runConfigeditor();
?>