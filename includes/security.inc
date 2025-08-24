<?php
/**
 * Security Configuration and Utilities
 * Centralized security settings for Supermon-ng
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 */

// Security configuration constants
define('SECURITY_MAX_LOGIN_ATTEMPTS', 5);
define('SECURITY_LOGIN_TIMEOUT', 900); // 15 minutes
define('SECURITY_SESSION_TIMEOUT', 28800); // 8 hours
define('SECURITY_PASSWORD_MIN_LENGTH', 8);
define('SECURITY_CSRF_TOKEN_LENGTH', 32);
define('SECURITY_RATE_LIMIT_WINDOW', 60); // 1 minute
define('SECURITY_RATE_LIMIT_MAX_REQUESTS', 100); // requests per window

// Allowed file extensions for uploads (if any)
define('SECURITY_ALLOWED_EXTENSIONS', ['txt', 'log', 'ini']);

// Allowed command paths for system operations
define('SECURITY_ALLOWED_COMMANDS', [
    'hostname' => ['/usr/bin/hostname', '/bin/hostname'],
    'awk' => ['/usr/bin/awk', '/bin/awk'],
    'date' => ['/usr/bin/date', '/bin/date'],
    'cat' => ['/usr/bin/cat', '/bin/cat'],
    'grep' => ['/usr/bin/grep', '/bin/grep'],
    'sed' => ['/usr/bin/sed', '/bin/sed'],
    'head' => ['/usr/bin/head', '/bin/head'],
    'tail' => ['/usr/bin/tail', '/bin/tail'],
    'curl' => ['/usr/bin/curl', '/bin/curl'],
    'cut' => ['/usr/bin/cut', '/bin/cut'],
    'ip' => ['/usr/bin/ip', '/bin/ip'],
    'uptime' => ['/usr/bin/uptime', '/bin/uptime'],
    'gpio' => ['/usr/bin/gpio', '/usr/local/bin/gpio'],
    'sudo' => ['/usr/bin/sudo'],
    'asterisk' => ['/usr/sbin/asterisk', '/usr/bin/asterisk']
]);

// Allowed log file paths
define('SECURITY_ALLOWED_LOG_FILES', [
    '/var/log/apache2/access.log',
    '/var/log/apache2/error.log',
    '/var/log/httpd/access_log',
    '/var/log/httpd/error_log',
    '/var/log/nginx/access.log',
    '/var/log/nginx/error.log',
    '/var/log/asterisk/messages.log',
    '/home/irlp/log/messages'
]);

/**
 * Rate Limiting Class
 */
class RateLimiter 
{
    private static $rateLimitFile = '/tmp/supermon-ng-rate-limit.json';
    
    /**
     * Check if request is within rate limits
     * 
     * @param string $identifier Request identifier (IP, user, etc.)
     * @param int $maxRequests Maximum requests per window
     * @param int $window Window size in seconds
     * @return bool True if within limits, false if rate limited
     */
    public static function check($identifier, $maxRequests = null, $window = null) 
    {
        if ($maxRequests === null) {
            $maxRequests = SECURITY_RATE_LIMIT_MAX_REQUESTS;
        }
        
        if ($window === null) {
            $window = SECURITY_RATE_LIMIT_WINDOW;
        }
        
        $data = self::loadRateLimitData();
        $now = time();
        $key = md5($identifier);
        
        if (!isset($data[$key])) {
            $data[$key] = [
                'requests' => [],
                'blocked_until' => 0
            ];
        }
        
        // Check if currently blocked
        if ($data[$key]['blocked_until'] > $now) {
            return false;
        }
        
        // Clean old requests outside window
        $data[$key]['requests'] = array_filter(
            $data[$key]['requests'],
            function($timestamp) use ($now, $window) {
                return $timestamp > ($now - $window);
            }
        );
        
        // Check if within limits
        if (count($data[$key]['requests']) >= $maxRequests) {
            // Block for window duration
            $data[$key]['blocked_until'] = $now + $window;
            self::saveRateLimitData($data);
            
            // Log rate limit violation
            if (class_exists('ErrorHandler') && method_exists('ErrorHandler', 'logError')) {
                ErrorHandler::logError("Rate limit exceeded", [
                    'identifier' => $identifier,
                    'requests' => count($data[$key]['requests']),
                    'max_requests' => $maxRequests,
                    'window' => $window
                ], 'SECURITY');
            }
            
            return false;
        }
        
        // Add current request
        $data[$key]['requests'][] = $now;
        self::saveRateLimitData($data);
        
        return true;
    }
    
    /**
     * Load rate limit data
     * 
     * @return array Rate limit data
     */
    private static function loadRateLimitData() 
    {
        if (!file_exists(self::$rateLimitFile)) {
            return [];
        }
        
        $content = file_get_contents(self::$rateLimitFile);
        if ($content === false) {
            return [];
        }
        
        $data = json_decode($content, true);
        return $data ?: [];
    }
    
    /**
     * Save rate limit data
     * 
     * @param array $data Rate limit data
     */
    private static function saveRateLimitData($data) 
    {
        $content = json_encode($data);
        if ($content === false) {
            return;
        }
        
        file_put_contents(self::$rateLimitFile, $content, LOCK_EX);
    }
    
    /**
     * Clean up old rate limit data
     * 
     * @param int $maxAge Maximum age in seconds
     */
    public static function cleanup($maxAge = 3600) 
    {
        $data = self::loadRateLimitData();
        $now = time();
        $cleaned = false;
        
        foreach ($data as $key => $entry) {
            // Remove old requests
            $oldCount = count($entry['requests']);
            $entry['requests'] = array_filter(
                $entry['requests'],
                function($timestamp) use ($now, $maxAge) {
                    return $timestamp > ($now - $maxAge);
                }
            );
            
            // Remove entries with no recent activity
            if (empty($entry['requests']) && $entry['blocked_until'] < $now) {
                unset($data[$key]);
                $cleaned = true;
            } elseif (count($entry['requests']) !== $oldCount) {
                $data[$key] = $entry;
                $cleaned = true;
            }
        }
        
        if ($cleaned) {
            self::saveRateLimitData($data);
        }
    }
}

/**
 * Security Monitor Class
 */
class SecurityMonitor 
{
    private static $suspiciousPatterns = [
        '/\.\./', // Directory traversal
        '/<script/i', // XSS attempts
        '/union\s+select/i', // SQL injection
        '/exec\s*\(/i', // Command injection
        '/system\s*\(/i', // Command injection
        '/shell_exec/i', // Command injection
        '/passthru/i', // Command injection
        '/eval\s*\(/i', // Code injection
        '/base64_decode/i', // Encoded payloads
    ];
    
    /**
     * Monitor request for security threats
     * 
     * @param array $request Request data
     * @return array Security alerts
     */
    public static function monitorRequest($request) 
    {
        $alerts = [];
        
        // Check for suspicious patterns in all request data
        foreach ($request as $key => $value) {
            if (is_string($value)) {
                $threats = self::detectThreats($value);
                if (!empty($threats)) {
                    $alerts[] = [
                        'type' => 'suspicious_pattern',
                        'field' => $key,
                        'value' => substr($value, 0, 100), // Truncate for logging
                        'threats' => $threats,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                    ];
                }
            }
        }
        
        // Check for unusual request patterns
        if (self::isUnusualRequest($request)) {
            $alerts[] = [
                'type' => 'unusual_request',
                'details' => 'Unusual request pattern detected',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Detect threats in input string
     * 
     * @param string $input Input string to check
     * @return array Detected threats
     */
    private static function detectThreats($input) 
    {
        $threats = [];
        
        foreach (self::$suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $threats[] = $pattern;
            }
        }
        
        return $threats;
    }
    
    /**
     * Check if request is unusual
     * 
     * @param array $request Request data
     * @return bool True if unusual
     */
    private static function isUnusualRequest($request) 
    {
        // Check for unusually long inputs
        foreach ($request as $value) {
            if (is_string($value) && strlen($value) > 10000) {
                return true;
            }
        }
        
        // Check for unusual HTTP methods
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['GET', 'POST'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Log security alert
     * 
     * @param array $alert Security alert data
     */
    public static function logAlert($alert) 
    {
        if (class_exists('ErrorHandler') && method_exists('ErrorHandler', 'logError')) {
            ErrorHandler::logError("Security alert: " . $alert['type'], $alert, 'SECURITY');
        }
    }
}

/**
 * Validate file path for security
 * @param string $path The file path to validate
 * @param array $allowed_paths Array of allowed paths
 * @return bool True if path is safe, false otherwise
 */
function validate_file_path($path, $allowed_paths = null) {
    if ($allowed_paths === null) {
        $allowed_paths = SECURITY_ALLOWED_LOG_FILES;
    }
    
    // Normalize path
    $path = realpath($path);
    if ($path === false) {
        return false;
    }
    
    // Check if path is in allowed list
    foreach ($allowed_paths as $allowed_path) {
        $allowed_path = realpath($allowed_path);
        if ($allowed_path !== false && $path === $allowed_path) {
            return true;
        }
    }
    
    return false;
}

/**
 * Validate command path
 * @param string $command The command name
 * @param string $path The path to validate
 * @return bool True if path is safe, false otherwise
 */
function validate_command_path($command, $path) {
    if (!isset(SECURITY_ALLOWED_COMMANDS[$command])) {
        return false;
    }
    
    $allowed_paths = SECURITY_ALLOWED_COMMANDS[$command];
    $real_path = realpath($path);
    
    if ($real_path === false) {
        return false;
    }
    
    foreach ($allowed_paths as $allowed_path) {
        $allowed_real_path = realpath($allowed_path);
        if ($allowed_real_path !== false && $real_path === $allowed_real_path) {
            return true;
        }
    }
    
    return false;
}

/**
 * Sanitize output for HTML display
 * @param string $input The input to sanitize
 * @return string Sanitized output
 */
function sanitize_output($input) {
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * Validate node number
 * @param string $node The node number to validate
 * @return bool True if valid, false otherwise
 */
function validate_node_number($node) {
    return preg_match('/^\d+$/', $node) && strlen($node) <= 7;
}

/**
 * Log security event
 * @param string $event The security event
 * @param string $details Additional details
 * @param string $level The log level (INFO, WARNING, ERROR)
 */
function log_security_event($event, $details = '', $level = 'INFO') {
    if (class_exists('ErrorHandler') && method_exists('ErrorHandler', 'logError')) {
        ErrorHandler::logError("Security event: $event", [
            'details' => $details,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user' => $_SESSION['user'] ?? 'anonymous'
        ], $level);
    }
}

/**
 * Validate and sanitize input
 * @param mixed $input Input to validate
 * @param string $type Type of validation
 * @param array $options Validation options
 * @return mixed Sanitized input or false if invalid
 */
function validate_input($input, $type = 'string', $options = []) {
    switch ($type) {
        case 'string':
            $input = trim(strip_tags($input));
            if (isset($options['max_length']) && strlen($input) > $options['max_length']) {
                return false;
            }
            if (isset($options['min_length']) && strlen($input) < $options['min_length']) {
                return false;
            }
            if (isset($options['pattern']) && !preg_match($options['pattern'], $input)) {
                return false;
            }
            return $input;
            
        case 'int':
            $input = filter_var($input, FILTER_VALIDATE_INT);
            if ($input === false) return false;
            
            if (isset($options['min']) && $input < $options['min']) return false;
            if (isset($options['max']) && $input > $options['max']) return false;
            return $input;
            
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL);
            
        case 'url':
            return filter_var($input, FILTER_VALIDATE_URL);
            
        case 'alpha':
            $input = trim(strip_tags($input));
            return preg_match('/^[a-zA-Z]+$/', $input) ? $input : false;
            
        case 'alphanumeric':
            $input = trim(strip_tags($input));
            return preg_match('/^[a-zA-Z0-9]+$/', $input) ? $input : false;
            
        default:
            return trim(strip_tags($input));
    }
}

/**
 * Check if user is rate limited
 * @param string $identifier Rate limit identifier
 * @return bool True if rate limited
 */
function is_rate_limited($identifier = null) {
    if ($identifier === null) {
        $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    return !RateLimiter::check($identifier);
}

// Note: Security monitoring initialization is now optional
// To enable, call: init_security_monitoring();
// To register cleanup, call: register_shutdown_function([RateLimiter::class, 'cleanup']);
?> 