<?php
/**
 * Supermon-ng Logout
 * 
 * Provides secure session termination and user logout functionality.
 * Implements comprehensive session cleanup, security measures, and audit logging
 * to ensure complete user session termination and system security.
 * 
 * Features:
 * - Comprehensive session termination and cleanup
 * - Audit logging with hostname, username, timestamp, and IP address
 * - Session cookie destruction with proper parameters
 * - Complete cookie cleanup for all stored cookies
 * - Security headers to prevent caching and replay attacks
 * - Client-side storage cleanup (localStorage, sessionStorage)
 * - Automatic redirect to login page after 3 seconds
 * - Responsive design for mobile and desktop viewing
 * - Error handling for logging failures
 * 
 * Security Measures:
 * - Complete session data clearing
 * - Session cookie destruction with secure parameters
 * - All cookie cleanup to prevent session persistence
 * - Cache control headers to prevent sensitive data caching
 * - Client-side storage cleanup
 * - Audit trail for security monitoring
 * - Proper error handling and logging
 * 
 * Logging:
 * - Logs logout events with detailed information
 * - Includes hostname, username, timestamp, and IP address
 * - Uses HTML formatting for log readability
 * - Implements file locking for concurrent access safety
 * - Error logging for failed write operations
 * 
 * Dependencies: session.inc, global.inc, common.inc, authini.php
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("user_files/global.inc");
include("includes/common.inc");
include("authini.php");
include("includes/logout/logout-controller.inc");

// Include header for consistent styling
include "header.inc";

// Run the logout system
runLogout();

include "footer.inc";
?>