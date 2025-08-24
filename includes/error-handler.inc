<?php
/**
 * Error Handling and Logging System
 * 
 * Provides centralized error handling, logging, and user-friendly error display
 * for the Supermon-ng application.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
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
    private static $performanceLog = '/tmp/supermon-ng-performance.log';
    private static $startTime;
    private static $requestId;
    
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
        
        if (isset($config['performance_log'])) {
            self::$performanceLog = $config['performance_log'];
        }
        
        // Generate request ID for tracking
        self::$requestId = uniqid('req_', true);
        self::$startTime = microtime(true);
        
        // Set up PHP error handler
        set_error_handler([self::class, 'handlePHPError']);
        set_exception_handler([self::class, 'handleException']);
        
        // Log request start
        self::logPerformance('REQUEST_START', [
            'request_id' => self::$requestId,
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
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
        $requestId = self::$requestId ?? 'unknown';
        
        $logEntry = sprintf(
            "[%s] [%s] [Request: %s] [User: %s] [IP: %s] [URI: %s] %s",
            $timestamp,
            $level,
            $requestId,
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
     * Log performance metrics
     * 
     * @param string $event Event name
     * @param array $data Performance data
     */
    public static function logPerformance($event, $data = []) 
    {
        $timestamp = microtime(true);
        $requestId = self::$requestId ?? 'unknown';
        
        $logEntry = sprintf(
            "[%s] [%s] [Request: %s] %s",
            date('Y-m-d H:i:s'),
            $event,
            $requestId,
            json_encode($data)
        ) . PHP_EOL;
        
        file_put_contents(self::$performanceLog, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log request completion with performance metrics
     */
    public static function logRequestCompletion() 
    {
        if (self::$startTime === null) {
            return;
        }
        
        $duration = microtime(true) - self::$startTime;
        $memoryUsage = memory_get_peak_usage(true);
        
        self::logPerformance('REQUEST_COMPLETE', [
            'duration_ms' => round($duration * 1000, 2),
            'memory_peak_mb' => round($memoryUsage / 1024 / 1024, 2),
            'status_code' => http_response_code()
        ]);
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
            htmlspecialchars($class, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        );
    }
    
    /**
     * Handle PHP errors
     * 
     * @param int $errno Error number
     * @param string $errstr Error string
     * @param string $errfile Error file
     * @param int $errline Error line
     * @return bool True to prevent default error handler
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
        
        $level = $errorTypes[$errno] ?? 'UNKNOWN';
        
        self::logError($errstr, [
            'file' => $errfile,
            'line' => $errline,
            'error_number' => $errno
        ], $level);
        
        // Don't display errors in production
        if (self::$debugMode) {
            echo self::displayUserError("PHP $level: $errstr in $errfile on line $errline", 'error');
        }
        
        return true; // Prevent default error handler
    }
    
    /**
     * Handle uncaught exceptions
     * 
     * @param Throwable $exception The exception
     */
    public static function handleException($exception) 
    {
        self::logError($exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'code' => $exception->getCode()
        ], 'EXCEPTION');
        
        // Log request completion
        self::logRequestCompletion();
        
        // Display user-friendly error
        if (self::$debugMode) {
            echo self::displayUserError(
                'An unexpected error occurred: ' . $exception->getMessage(),
                'error'
            );
        } else {
            echo self::displayUserError(
                'An unexpected error occurred. Please try again later.',
                'error'
            );
        }
    }
    
    /**
     * Get request ID for tracking
     * 
     * @return string Request ID
     */
    public static function getRequestId() 
    {
        return self::$requestId ?? 'unknown';
    }
    
    /**
     * Log AMI operation
     * 
     * @param string $operation Operation name
     * @param string $node Node identifier
     * @param float $duration Duration in seconds
     * @param bool $success Success status
     * @param string $error Error message if failed
     */
    public static function logAMIOperation($operation, $node, $duration, $success, $error = '') 
    {
        self::logPerformance('AMI_OPERATION', [
            'operation' => $operation,
            'node' => $node,
            'duration_ms' => round($duration * 1000, 2),
            'success' => $success,
            'error' => $error
        ]);
    }
    
    /**
     * Get error count (for performance monitoring)
     * 
     * @param int $hours Hours to look back (default: all time)
     * @return int Number of errors
     */
    public static function getErrorCount($hours = null) 
    {
        // Simple implementation - in production you'd parse the log file
        return 0;
    }
    
    /**
     * Get errors by level (for performance monitoring)
     * 
     * @return array Errors grouped by level
     */
    public static function getErrorsByLevel() 
    {
        // Simple implementation - in production you'd parse the log file
        return [
            'ERROR' => 0,
            'WARNING' => 0,
            'INFO' => 0,
            'DEBUG' => 0
        ];
    }
    
    /**
     * Log file operation
     * 
     * @param string $operation Operation name
     * @param string $file File path
     * @param float $duration Duration in seconds
     * @param bool $success Success status
     * @param string $error Error message if failed
     */
    public static function logFileOperation($operation, $file, $duration, $success, $error = '') 
    {
        self::logPerformance('FILE_OPERATION', [
            'operation' => $operation,
            'file' => $file,
            'duration_ms' => round($duration * 1000, 2),
            'success' => $success,
            'error' => $error
        ]);
    }
}

// Note: Error handler initialization is now optional
// To enable, call: ErrorHandler::init();
// To register shutdown, call: register_shutdown_function([ErrorHandler::class, 'logRequestCompletion']);
?>



