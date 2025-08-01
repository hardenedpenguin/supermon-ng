<?php

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