<?php
/**
 * Supermon-ng ExtNodes Authentication
 * 
 * Handles authentication, authorization, and validation for external nodes viewing.
 * Provides user access control and file system validation.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Validate user access to extnodes function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateExtnodesAccess(): bool {
    if (!isset($_SESSION['sm61loggedin']) || $_SESSION['sm61loggedin'] !== true || !get_user_auth("EXNUSER")) {
        return false;
    }
    
    return true;
}

/**
 * Get external nodes file path
 * 
 * @return string File path
 */
function getExtnodesFilePath(): string {
    global $EXTNODES;
    return $EXTNODES;
}

/**
 * Validate file exists
 * 
 * @param string $filePath Path to the file
 * @return bool True if file exists, false otherwise
 */
function validateExtnodesFile(string $filePath): bool {
    return file_exists($filePath);
}

/**
 * Read file content safely
 * 
 * @param string $filePath Path to the file
 * @return string|false File content or false on failure
 */
function readExtnodesFileContent(string $filePath): string|false {
    return file_get_contents($filePath);
}
