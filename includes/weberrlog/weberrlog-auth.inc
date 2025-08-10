<?php
/**
 * Supermon-ng Web Error Log Authentication
 * 
 * Handles authentication, authorization, and validation for web error log viewing.
 * Provides user access control, log file validation, and log parsing functionality.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Validate user access to web error log function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateWeberrlogAccess(): bool {
    $isLoggedIn = isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true;
    $isAuthorized = $isLoggedIn && function_exists('get_user_auth') && get_user_auth("WERRUSER");
    return $isAuthorized;
}

/**
 * Get and validate web error log file path
 * 
 * @return string|false Validated log file path or false if invalid
 */
function getValidatedWeberrlogPath(): string|false {
    global $WEB_ERROR_LOG;
    
    if (!isset($WEB_ERROR_LOG)) {
        return false;
    }
    
    return $WEB_ERROR_LOG;
}

/**
 * Validate log file exists and is readable
 * 
 * @param string $logFilePath Path to the log file
 * @return array Status array with 'exists', 'readable', and 'error' keys
 */
function validateWeberrlogFile(string $logFilePath): array {
    $status = [
        'exists' => false,
        'readable' => false,
        'error' => ''
    ];
    
    if (!file_exists($logFilePath)) {
        $status['error'] = "Log file not found: " . htmlspecialchars($logFilePath);
        return $status;
    }
    
    $status['exists'] = true;
    
    if (!is_readable($logFilePath)) {
        $status['error'] = "Log file not readable: " . htmlspecialchars($logFilePath);
        return $status;
    }
    
    $status['readable'] = true;
    return $status;
}

/**
 * Parse web error log lines using regex
 * 
 * @param array $lines Array of log file lines
 * @return array Array of parsed log entries with headers and rows
 */
function parseWeberrlogLines(array $lines): array {
    $logRegex = '/^\[(?<timestamp>.*?)\] (?:\[(?<module>[^:]+):(?<level_m>[^\]]+)\]|\[(?<level>[^\]]+)\])(?: \[pid (?<pid>\d+)(?::tid (?<tid>\d+))?\])?(?: \[client (?<client>.*?)\])? (?<message>.*)$/';
    
    $headers = ['Line', 'Timestamp', 'Level', 'Client', 'Details'];
    $rows = [];
    
    foreach ($lines as $index => $line) {
        $lineNumber = $index + 1;
        $matched = preg_match($logRegex, $line, $matches);
        
        if ($matched) {
            $timestamp = htmlspecialchars($matches['timestamp'] ?? '');
            $level_raw_captured = $matches['level_m'] ?? ($matches['level'] ?? '');
            $level_raw = strtolower(trim($level_raw_captured));
            $level_display = htmlspecialchars(strtoupper($level_raw));
            $client = htmlspecialchars($matches['client'] ?? '');
            $message = htmlspecialchars($matches['message'] ?? '');
            
            $rows[] = [
                $lineNumber,
                $timestamp,
                $level_display,
                $client,
                $message
            ];
        } else {
            $sanitizedLine = htmlspecialchars($line);
            $rows[] = [
                $lineNumber,
                'N/A',
                'N/A',
                'N/A',
                $sanitizedLine
            ];
        }
    }
    
    return [
        'headers' => $headers,
        'rows' => $rows
    ];
}

/**
 * Read web error log file content
 * 
 * @param string $logFilePath Path to the log file
 * @return array|false Array of lines or false on failure
 */
function readWeberrlogContent(string $logFilePath): array|false {
    $lines = file($logFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    if ($lines === false) {
        return false;
    }
    
    return $lines;
}
