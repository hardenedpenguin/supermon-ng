<?php
/**
 * Supermon-ng AstNodes Authentication
 * 
 * Handles authentication, authorization, and validation for Asterisk nodes viewing.
 * Provides user access control, permission validation, and file system validation.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Validate user access to astnodes function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateAstnodesAccess(): bool {
    if (!isset($_SESSION['sm61loggedin']) || $_SESSION['sm61loggedin'] !== true) {
        return false;
    }
    
    if (!get_user_auth("NINFUSER")) {
        return false;
    }
    
    return true;
}

/**
 * Validate ASTDB_TXT configuration
 * 
 * @return bool True if configuration is valid, false otherwise
 */
function validateAstnodesConfig(): bool {
    global $ASTDB_TXT;
    
    if (!isset($ASTDB_TXT)) {
        return false;
    }
    
    return true;
}

/**
 * Get file path for display
 * 
 * @return string File path
 */
function getAstnodesFilePath(): string {
    global $ASTDB_TXT;
    return $ASTDB_TXT;
}

/**
 * Validate file exists and is readable
 * 
 * @param string $filePath Path to the file
 * @return bool True if file is valid, false otherwise
 */
function validateAstnodesFile(string $filePath): bool {
    return file_exists($filePath) && is_readable($filePath);
}

/**
 * Read file content safely
 * 
 * @param string $filePath Path to the file
 * @return string|false File content or false on failure
 */
function readAstnodesFileContent(string $filePath): string|false {
    return file_get_contents($filePath);
}
