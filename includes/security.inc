<?php
/**
 * Security Configuration and Utilities
 * Centralized security settings for Supermon-ng
 */

// Security configuration constants
define('SECURITY_MAX_LOGIN_ATTEMPTS', 5);
define('SECURITY_LOGIN_TIMEOUT', 900); // 15 minutes
define('SECURITY_SESSION_TIMEOUT', 28800); // 8 hours
define('SECURITY_PASSWORD_MIN_LENGTH', 8);
define('SECURITY_CSRF_TOKEN_LENGTH', 32);

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
    $log_file = '/var/log/supermon-security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user = $_SESSION['user'] ?? 'anonymous';
    
    $log_entry = sprintf(
        "[%s] %s - IP: %s, User: %s, Event: %s, Details: %s\n",
        $timestamp,
        $level,
        $ip,
        $user,
        $event,
        $details
    );
    
    if (is_writable(dirname($log_file)) || is_writable($log_file)) {
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Check if request is from allowed IP
 * @param array $allowed_ips Array of allowed IP addresses or CIDR ranges
 * @return bool True if allowed, false otherwise
 */
function is_allowed_ip($allowed_ips = []) {
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (empty($allowed_ips)) {
        return true; // No restrictions
    }
    
    foreach ($allowed_ips as $allowed_ip) {
        if (strpos($allowed_ip, '/') !== false) {
            // CIDR range
            if (ip_in_range($client_ip, $allowed_ip)) {
                return true;
            }
        } else {
            // Single IP
            if ($client_ip === $allowed_ip) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Check if IP is in CIDR range
 * @param string $ip The IP to check
 * @param string $cidr The CIDR range
 * @return bool True if in range, false otherwise
 */
function ip_in_range($ip, $cidr) {
    list($subnet, $mask) = explode('/', $cidr);
    $ip_binary = ip2long($ip);
    $subnet_binary = ip2long($subnet);
    $mask_binary = ~((1 << (32 - $mask)) - 1);
    
    return ($ip_binary & $mask_binary) === ($subnet_binary & $mask_binary);
}

/**
 * Generate secure random string
 * @param int $length The length of the string
 * @return string The random string
 */
function generate_secure_random_string($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Validate password strength
 * @param string $password The password to validate
 * @return array Array with 'valid' => bool and 'message' => string
 */
function validate_password_strength($password) {
    if (strlen($password) < SECURITY_PASSWORD_MIN_LENGTH) {
        return [
            'valid' => false,
            'message' => 'Password must be at least ' . SECURITY_PASSWORD_MIN_LENGTH . ' characters long.'
        ];
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        return [
            'valid' => false,
            'message' => 'Password must contain at least one uppercase letter.'
        ];
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        return [
            'valid' => false,
            'message' => 'Password must contain at least one lowercase letter.'
        ];
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        return [
            'valid' => false,
            'message' => 'Password must contain at least one number.'
        ];
    }
    
    return [
        'valid' => true,
        'message' => 'Password meets strength requirements.'
    ];
}

/**
 * Clean up old rate limit files
 * @param int $max_age Maximum age in seconds (default 24 hours)
 */
function cleanup_rate_limit_files($max_age = 86400) {
    $temp_dir = sys_get_temp_dir();
    $pattern = $temp_dir . '/rate_limit_*.txt';
    
    $files = glob($pattern);
    $current_time = time();
    
    foreach ($files as $file) {
        $file_time = filemtime($file);
        if ($current_time - $file_time > $max_age) {
            unlink($file);
        }
    }
}

// Clean up old rate limit files on script execution
cleanup_rate_limit_files();
?> 