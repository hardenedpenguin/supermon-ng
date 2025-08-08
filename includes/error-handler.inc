<?php
/**
 * Error Handling and Logging System
 * 
 * Provides centralized error handling, logging, and user-friendly error display
 * for the Supermon-ng application.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 */

/**
 * Centralized Error Handler Class
 * 
 * Manages error logging, user error display, and system debugging
 * in a consistent manner across the application.
 */
class ErrorHandler 
{
    private static $logFile = '/tmp/supermon-ng-errors.log';
    private static $debugMode = false;
    
    /**
     * Initialize error handler
     * 
     * @param array $config Configuration options
     */
    public static function init($config = []) 
    {
        if (isset($config['log_file'])) {
            self::$logFile = $config['log_file'];
        }
        
        if (isset($config['debug_mode'])) {
            self::$debugMode = $config['debug_mode'];
        }
        
        // Set up PHP error handler
        set_error_handler([self::class, 'handlePHPError']);
        set_exception_handler([self::class, 'handleException']);
    }
    
    /**
     * Log error message with context
     * 
     * @param string $message Error message
     * @param array $context Additional context information
     * @param string $level Error level (ERROR, WARNING, INFO, DEBUG)
     */
    public static function logError($message, $context = [], $level = 'ERROR') 
    {
        $timestamp = date('Y-m-d H:i:s');
        $user = $_SESSION['user'] ?? 'anonymous';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        
        $logEntry = sprintf(
            "[%s] [%s] [User: %s] [IP: %s] [URI: %s] %s",
            $timestamp,
            $level,
            $user,
            $ip,
            $uri,
            $message
        );
        
        if (!empty($context)) {
            $logEntry .= ' Context: ' . json_encode($context);
        }
        
        $logEntry .= PHP_EOL;
        
        // Write to log file
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Also write to PHP error log in debug mode
        if (self::$debugMode) {
            error_log("[Supermon-ng] $message " . json_encode($context));
        }
    }
    
    /**
     * Display user-friendly error message
     * 
     * @param string $message User-friendly error message
     * @param string $type Error type (error, warning, info, success)
     * @return string HTML error display
     */
    public static function displayUserError($message, $type = 'error') 
    {
        $classes = [
            'error' => 'error-message',
            'warning' => 'warning-message', 
            'info' => 'info-message',
            'success' => 'success-message'
        ];
        
        $class = $classes[$type] ?? 'error-message';
        
        return sprintf(
            '<div class="%s">%s</div>',
            htmlspecialchars($class),
            htmlspecialchars($message)
        );
    }
    
    /**
     * Handle AMI-related errors
     * 
     * @param string $error AMI error message
     * @param array $context Additional context
     * @return string User-friendly error message
     */
    public static function handleAMIError($error, $context = []) 
    {
        self::logError("AMI Error: $error", $context);
        return self::displayUserError("Unable to connect to node. Please check your configuration and try again.");
    }
    
    /**
     * Handle database/configuration errors
     * 
     * @param string $error Database error message
     * @param array $context Additional context
     * @return string User-friendly error message
     */
    public static function handleDatabaseError($error, $context = []) 
    {
        self::logError("Database Error: $error", $context);
        return self::displayUserError("Configuration error. Please check your settings.");
    }
    
    /**
     * Handle file operation errors
     * 
     * @param string $error File error message
     * @param array $context Additional context
     * @return string User-friendly error message
     */
    public static function handleFileError($error, $context = []) 
    {
        self::logError("File Error: $error", $context);
        return self::displayUserError("File operation failed. Please check permissions and try again.");
    }
    
    /**
     * Handle security-related errors
     * 
     * @param string $error Security error message
     * @param array $context Additional context
     * @return string User-friendly error message
     */
    public static function handleSecurityError($error, $context = []) 
    {
        self::logError("Security Error: $error", $context, 'SECURITY');
        return self::displayUserError("Access denied. Insufficient permissions.");
    }
    
    /**
     * Handle validation errors
     * 
     * @param string $field Field name that failed validation
     * @param string $value Value that failed validation
     * @param string $rule Validation rule that failed
     * @return string User-friendly error message
     */
    public static function handleValidationError($field, $value, $rule) 
    {
        self::logError("Validation Error", [
            'field' => $field,
            'value' => $value,
            'rule' => $rule
        ]);
        
        $friendlyMessages = [
            'required' => "The $field field is required.",
            'numeric' => "The $field field must be a number.",
            'email' => "Please enter a valid email address.",
            'min_length' => "The $field field is too short.",
            'max_length' => "The $field field is too long.",
            'pattern' => "The $field field format is invalid."
        ];
        
        $message = $friendlyMessages[$rule] ?? "The $field field is invalid.";
        return self::displayUserError($message, 'warning');
    }
    
    /**
     * Handle PHP errors
     * 
     * @param int $errno Error number
     * @param string $errstr Error message
     * @param string $errfile Error file
     * @param int $errline Error line
     * @return bool
     */
    public static function handlePHPError($errno, $errstr, $errfile, $errline) 
    {
        $errorTypes = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED'
        ];
        
        $type = $errorTypes[$errno] ?? 'UNKNOWN';
        
        self::logError("PHP $type: $errstr in $errfile:$errline");
        
        // Don't execute PHP internal error handler
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     * 
     * @param Exception $exception Uncaught exception
     */
    public static function handleException($exception) 
    {
        self::logError("Uncaught Exception: " . $exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
        
        if (self::$debugMode) {
            echo self::displayUserError("An unexpected error occurred. Check the error log for details.");
        } else {
            echo self::displayUserError("An unexpected error occurred. Please try again later.");
        }
    }
    
    /**
     * Create debug dump
     * 
     * @param mixed $data Data to dump
     * @param string $label Label for the dump
     */
    public static function debugDump($data, $label = 'Debug') 
    {
        if (self::$debugMode) {
            echo "<pre><strong>$label:</strong>\n";
            print_r($data);
            echo "</pre>";
        }
        
        self::logError("Debug Dump - $label", ['data' => $data], 'DEBUG');
    }
    
    /**
     * Log user action for audit trail
     * 
     * @param string $action Action performed
     * @param array $context Action context
     */
    public static function logUserAction($action, $context = []) 
    {
        self::logError("User Action: $action", $context, 'AUDIT');
    }
    
    /**
     * Clean up old log files
     * 
     * @param int $daysToKeep Number of days to keep logs
     */
    public static function cleanupLogs($daysToKeep = 30) 
    {
        $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
        
        if (file_exists(self::$logFile) && filemtime(self::$logFile) < $cutoffTime) {
            // Archive old log file
            $archiveName = self::$logFile . '.' . date('Y-m-d', filemtime(self::$logFile));
            rename(self::$logFile, $archiveName);
            
            // Compress archived log
            if (function_exists('gzopen')) {
                $gz = gzopen($archiveName . '.gz', 'w9');
                if ($gz) {
                    gzwrite($gz, file_get_contents($archiveName));
                    gzclose($gz);
                    unlink($archiveName);
                }
            }
        }
    }
    
    /**
     * Get error statistics
     * 
     * @param int $hours Hours to look back
     * @return array Error statistics
     */
    public static function getErrorStats($hours = 24) 
    {
        if (!file_exists(self::$logFile)) {
            return ['total' => 0, 'by_level' => []];
        }
        
        $cutoffTime = time() - ($hours * 3600);
        $lines = file(self::$logFile, FILE_IGNORE_NEW_LINES);
        $stats = ['total' => 0, 'by_level' => []];
        
        foreach ($lines as $line) {
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \[(\w+)\]/', $line, $matches)) {
                $timestamp = strtotime($matches[1]);
                $level = $matches[2];
                
                if ($timestamp >= $cutoffTime) {
                    $stats['total']++;
                    $stats['by_level'][$level] = ($stats['by_level'][$level] ?? 0) + 1;
                }
            }
        }
        
        return $stats;
    }
}

// Initialize error handler
ErrorHandler::init();
