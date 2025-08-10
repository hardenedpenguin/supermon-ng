<?php
/**
 * Supermon-ng IRLP Log Authentication
 * 
 * Handles authentication, authorization, and validation for IRLP log viewing.
 * Provides user access control and log file validation.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Validate user access to IRLP log function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateIrlplogAccess(): bool {
    if (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && 
        get_user_auth("IRLPLOGUSER")) {
        return true;
    }
    return false;
}

/**
 * Get and validate IRLP log file path
 * 
 * @return string|false Validated log file path or false if invalid
 */
function getValidatedIrlplogPath(): string|false {
    global $IRLP_LOG;
    
    if (!isset($IRLP_LOG)) {
        return false;
    }
    
    return $IRLP_LOG;
}

/**
 * Read IRLP log file content safely
 * 
 * @param string $file Log file path
 * @return string|false File content or false on failure
 */
function readIrlplogContent(string $file): string|false {
    if (!file_exists($file)) {
        return false;
    }
    
    $content = file_get_contents($file);
    if ($content === false) {
        return false;
    }
    
    return $content;
}
