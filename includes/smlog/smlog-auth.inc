<?php
/**
 * Supermon-ng Supermon Log Authentication
 * 
 * Handles authentication, authorization, and validation for Supermon log viewing.
 * Provides user access control and log file validation.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Validate user access to Supermon log function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateSmlogAccess(): bool {
    if ((isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true) && 
        (get_user_auth("SMLOGUSER"))) {
        return true;
    }
    return false;
}

/**
 * Get and validate Supermon log file path
 * 
 * @return string|false Validated log file path or false if invalid
 */
function getValidatedSmlogPath(): string|false {
    global $SMLOGNAME;
    
    if (!isset($SMLOGNAME)) {
        return false;
    }
    
    return $SMLOGNAME;
}

/**
 * Read Supermon log file content safely
 * 
 * @param string $log_file_path Log file path
 * @return array|false Log content array or false on failure
 */
function readSmlogContent(string $log_file_path): array|false {
    $log_content_array = @file($log_file_path);
    
    if ($log_content_array === false) {
        return false;
    }
    
    return $log_content_array;
}

/**
 * Process log content for display (reverse order)
 * 
 * @param array $log_content_array Log content array
 * @return array Processed log content in reverse order
 */
function processSmlogContent(array $log_content_array): array {
    return array_reverse($log_content_array);
}
