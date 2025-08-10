<?php
/**
 * Supermon-ng Asterisk Log Authentication
 * 
 * Handles authentication, authorization, and validation for Asterisk log viewing.
 * Provides user access control and log file validation.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Validate user access to Asterisk log function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateAstlogAccess(): bool {
    if (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && 
        function_exists('get_user_auth') && get_user_auth("ASTLUSER")) {
        return true;
    }
    return false;
}

/**
 * Get and validate Asterisk log file path
 * 
 * @return string|false Validated log file path or false if invalid
 */
function getValidatedAstlogPath(): string|false {
    global $ASTERISK_LOG;
    
    if (!isset($ASTERISK_LOG)) {
        return false;
    }
    
    return $ASTERISK_LOG;
}

/**
 * Read Asterisk log file content safely
 * 
 * @param string $file Log file path
 * @return string|false File content or false on failure
 */
function readAstlogContent(string $file): string|false {
    if (!file_exists($file) || !is_readable($file)) {
        return false;
    }
    
    $content = file_get_contents($file);
    if ($content === false) {
        return false;
    }
    
    return $content;
}
