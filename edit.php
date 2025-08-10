<?php
/**
 * Supermon-ng File Editor
 * 
 * Provides a web-based interface for editing configuration files and other text files.
 * Allows authenticated users to view and edit files through a secure web interface
 * with proper validation and security checks.
 * 
 * Features:
 * - User authentication and authorization (CFGEDUSER permission required)
 * - File path validation and security checks
 * - View-only mode for protected files
 * - Safe file reading with error handling
 * - Form-based editing interface
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
include("includes/edit/edit-controller.inc");

// Run the edit system
runEdit();