<?php
/**
 * Supermon-ng File Saver
 * 
 * Provides a web-based interface for saving edited files through a secure
 * helper script with proper validation and error handling.
 * 
 * Features:
 * - User authentication and authorization (CFGEDUSER permission required)
 * - File path validation and security checks
 * - Secure file saving using sudo helper script
 * - Comprehensive error reporting and status display
 * - Form-based navigation for continued editing
 * 
 * Security: Requires CFGEDUSER permission, prevents directory traversal
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("authusers.php");
include("user_files/global.inc");
include("includes/common.inc");
include("includes/save/save-controller.inc");

// Run the save system
runSave();