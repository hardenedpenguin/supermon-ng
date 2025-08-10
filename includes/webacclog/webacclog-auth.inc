<?php
/**
 * Supermon-ng Web Access Log Authentication
 * 
 * Handles authentication, authorization, and validation for web access log viewing.
 * Provides user access control, log file validation, and secure command execution.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Validate user access to web access log function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateWebacclogAccess(): bool {
    if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("WLOGUSER"))) {
        return false;
    }
    return true;
}

/**
 * Safe command execution function
 * 
 * @param string $command Command to execute
 * @param string $args Command arguments
 * @return string|false Command output or false on failure
 */
function safe_exec($command, $args = '') {
    $escaped_command = escapeshellcmd($command);
    if (!empty($args)) {
        $escaped_args = escapeshellarg($args);
        $full_command = "{$escaped_command} {$escaped_args}";
    } else {
        $full_command = $escaped_command;
    }
    
    $output = [];
    $return_var = 0;
    exec($full_command . " 2>/dev/null", $output, $return_var);
    
    if ($return_var !== 0) {
        return false;
    }
    
    return implode("\n", $output);
}

/**
 * Validate file path for security
 * 
 * @param string $path File path to validate
 * @return bool True if path is safe, false otherwise
 */
function is_safe_file_path($path) {
    // Only allow specific log files
    $allowed_paths = [
        '/var/log/apache2/access.log',
        '/var/log/httpd/access_log',
        '/var/log/nginx/access.log'
    ];
    
    return in_array($path, $allowed_paths) && file_exists($path);
}

/**
 * Get and validate web access log file path
 * 
 * @return string|false Validated log file path or false if invalid
 */
function getValidatedWebacclogPath(): string|false {
    global $WEB_ACCESS_LOG;
    
    $file = $WEB_ACCESS_LOG ?? '/var/log/apache2/access.log';
    
    if (!is_safe_file_path($file)) {
        return false;
    }
    
    return $file;
}

/**
 * Read web access log content with fallback methods
 * 
 * @param string $file Log file path
 * @return array|false Array of log lines or false on failure
 */
function readWebacclogContent(string $file): array|false {
    $logLines = false;

    // Try to read the file directly first
    if (is_readable($file)) {
        $logContent = file_get_contents($file);
        if ($logContent !== false) {
            $logLines = explode("\n", $logContent);
            $logLines = array_slice($logLines, -100); // Show last 100 lines
            $logLines = array_reverse($logLines); // Most recent first
        } else {
            $logLines = [];
        }
    } else {
        // Fallback to sudo if direct read fails
        $logContent = safe_exec("sudo", "tail -100 " . escapeshellarg($file));
        if ($logContent !== false) {
            $logLines = explode("\n", $logContent);
            $logLines = array_reverse($logLines); // Most recent first
        } else {
            $logLines = [];
        }
    }

    return $logLines;
}

/**
 * Get sudo command for display purposes
 * 
 * @param string $file Log file path
 * @return string Sudo command string
 */
function getSudoCommand(string $file): string {
    return "sudo tail -100 " . escapeshellarg($file);
}
