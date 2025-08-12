<?php
/**
 * Supermon-ng User Authentication and Authorization System
 * 
 * Provides comprehensive user authentication and permission-based access control
 * for Supermon-ng. Handles user authorization for specific actions, buttons,
 * and features based on user roles and permissions defined in configuration files.
 * 
 * Features:
 * - Session-based user authentication
 * - Permission-based access control
 * - Dynamic authorization checking
 * - User role management
 * - Configuration-driven permissions
 * - Fallback authorization mechanisms
 * - Secure permission validation
 * 
 * Authorization System:
 * - Button/action-based permissions
 * - User role arrays in authusers.inc
 * - Session user validation
 * - Dynamic permission checking
 * - Default authorization fallback
 * 
 * Configuration Files:
 * - authusers.inc: User permission arrays
 * - common.inc: Common configuration and constants
 * - Session management: User session validation
 * 
 * Permission Structure:
 * - Each permission is an array of authorized users
 * - Permission names match button/action identifiers
 * - Users are matched against session user
 * - Strict comparison for security
 * 
 * Security Features:
 * - Session validation and authentication
 * - File existence validation
 * - Array type checking
 * - Strict user comparison
 * - Fallback authorization handling
 * - Secure permission validation
 * 
 * Functions:
 * - get_user_auth(): Checks user authorization for specific actions
 * 
 * Usage Example:
 * - get_user_auth("CONNECTUSER") - Checks connect permission
 * - get_user_auth("DTMFUSER") - Checks DTMF permission
 * - get_user_auth("ADMIN") - Checks admin permission
 * 
 * Dependencies:
 * - common.inc: Common configuration and constants
 * - Session management: User session validation
 * - authusers.inc: User permission configuration
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Checks if the current session user is authorized for a specific action or "button".
 *
 * Authorization is determined by checking if the `$_SESSION['user']` exists within an array
 * named after the `$button` parameter. This array is expected to be defined in a file
 * located at `$USERFILES/authusers.inc`.
 *
 * If `$USERFILES/authusers.inc` does not exist, the function defaults to returning true,
 * effectively granting authorization.
 *
 * The `common.inc` file is included and is assumed to handle session initialization
 * and define the `$USERFILES` constant or variable.
 * The `authusers.inc` file, if it exists, should define arrays like:
 *   $view_reports_button = ['admin', 'manager'];
 *   $edit_settings_button = ['admin'];
 * etc., where the variable name matches the `$button` parameter passed to this function.
 *
 * @param string $button The identifier for the button/action being checked.
 *                       This string is used to dynamically access a variable of the same name
 *                       (e.g., if $button is "admin_users", it looks for an array $admin_users).
 * @return bool True if the user is authorized (or if authusers.inc doesn't exist).
 *              False if $_SESSION['user'] is not set, or the specific auth list for
 *              the button ($$button) is not defined or not an array, or the user is
 *              not in the specific button's list in authusers.inc.
 */
function get_user_auth($button) {
    include("includes/common.inc");

    $auth_file_path = "$USERFILES/authusers.inc";

    if (!file_exists($auth_file_path)) {
        return true;
    }

    include($auth_file_path);

    if (isset($_SESSION['user'], $$button) && is_array($$button)) {
        return in_array($_SESSION['user'], $$button, true);
    }

    return false;
}

?>