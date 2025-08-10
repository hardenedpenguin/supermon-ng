<?php
/**
 * Supermon-ng Linux Log Authentication
 * 
 * Handles authentication, authorization, and validation for Linux system log viewing.
 * Provides user access control and command configuration validation.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Validate user access to Linux log function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateLinuxlogAccess(): bool {
    if (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && 
        get_user_auth("LLOGUSER")) {
        return true;
    }
    return false;
}

/**
 * Validate required command configuration
 * 
 * @return bool True if all required commands are configured, false otherwise
 */
function validateLinuxlogCommands(): bool {
    global $SUDO, $JOURNALCTL, $SED;
    
    if (!isset($SUDO) || !isset($JOURNALCTL) || !isset($SED)) {
        return false;
    }
    
    return true;
}

/**
 * Get the Linux log command with proper filtering
 * 
 * @return string The command to execute for retrieving Linux logs
 */
function getLinuxlogCommand(): string {
    global $SUDO, $JOURNALCTL, $SED;
    
    return "$SUDO $JOURNALCTL --no-pager --since \"1 day ago\" | $SED -e \"/sudo/ d\"";
}

/**
 * Execute Linux log command and return output
 * 
 * @param string $command The command to execute
 * @return void Outputs directly to browser
 */
function executeLinuxlogCommand(string $command): void {
    echo '<pre class="log-viewer-output">';
    passthru($command . " 2>&1");
    echo '</pre>';
}
