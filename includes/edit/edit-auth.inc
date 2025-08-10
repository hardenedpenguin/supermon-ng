<?php
/**
 * Supermon-ng Edit Authentication
 * 
 * Handles authentication, authorization, and validation for file editing operations.
 * Provides user access control, file path validation, and security checks.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Validate user access to edit function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateEditAccess(): bool {
    if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("CFGEDUSER"))) {
        return false;
    }
    return true;
}

/**
 * Get and validate file parameter
 * 
 * @return string|false Validated file path or false if invalid
 */
function getValidatedFile(): string|false {
    $file = $_POST["file"] ?? null;

    if (!$file) {
        return false;
    }

    // Security check: prevent directory traversal
    if (strpos($file, '..') !== false) {
        return false;
    }

    return $file;
}

/**
 * Check if file is view-only
 * 
 * @param string $file File path to check
 * @return bool True if file is view-only, false otherwise
 */
function isViewOnlyFile(string $file): bool {
    $view_only_files = [
        "/usr/local/bin/AUTOSKY/AutoSky-log.txt",
        "/var/www/html/supermon-ng/user_files/IMPORTANT-README"
    ];

    return in_array($file, $view_only_files);
}

/**
 * Read file content safely
 * 
 * @param string $file File path to read
 * @return string File content or error message
 */
function readFileContent(string $file): string {
    if (!file_exists($file) || !is_readable($file)) {
        return 'ERROR: File does not exist or is not readable: ' . htmlspecialchars($file);
    }

    $fh = fopen($file, 'r');
    if (!$fh) {
        return 'ERROR: Could not open file: ' . htmlspecialchars($file) . '. Check permissions or path.';
    }

    $filesize = filesize($file);
    if ($filesize > 0) {
        $data = fread($fh, $filesize);
    } elseif ($filesize === 0) {
        $data = '';
    } else {
        $data = 'ERROR: Could not determine file size or read file!';
    }
    
    fclose($fh);
    return $data;
}
