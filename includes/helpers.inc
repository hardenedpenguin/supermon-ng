<?php
/**
 * Helper Classes for Common Patterns
 * 
 * This file contains utility classes that standardize common operations
 * throughout the Supermon-ng application, making it easier for new
 * contributors to follow established patterns.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 */

/**
 * AMI (Asterisk Manager Interface) Helper Class
 * 
 * Standardizes AMI connections and command execution with proper
 * error handling and connection management.
 */
class AMIHelper 
{
    private static $connections = [];
    
    /**
     * Connect to a node via AMI
     * 
     * @param string|array $nodeConfig Node ID or config array with host/user/passwd
     * @return resource|false AMI connection resource or false on failure
     */
    public static function connectToNode($nodeConfig) 
    {
        if (is_string($nodeConfig)) {
            // If string provided, treat as node ID and get config
            $nodeConfig = self::getNodeConfig($nodeConfig);
            if (!$nodeConfig) {
                ErrorHandler::logError("Node configuration not found", ['node' => $nodeConfig]);
                return false;
            }
        }
        
        if (!isset($nodeConfig['host']) || !isset($nodeConfig['user']) || !isset($nodeConfig['passwd'])) {
            ErrorHandler::logError("Incomplete node configuration", ['config' => $nodeConfig]);
            return false;
        }
        
        $host = $nodeConfig['host'];
        
        // Reuse existing connection if available
        if (isset(self::$connections[$host])) {
            return self::$connections[$host];
        }
        
        $fp = SimpleAmiClient::connect($host);
        if ($fp === false) {
            ErrorHandler::logError("Failed to connect to AMI", ['host' => $host]);
            return false;
        }
        
        if (SimpleAmiClient::login($fp, $nodeConfig['user'], $nodeConfig['passwd']) === false) {
            SimpleAmiClient::logoff($fp);
            ErrorHandler::logError("Failed to login to AMI", ['host' => $host, 'user' => $nodeConfig['user']]);
            return false;
        }
        
        self::$connections[$host] = $fp;
        return $fp;
    }
    
    /**
     * Execute a command via AMI
     * 
     * @param resource $connection AMI connection resource
     * @param string $command Command to execute
     * @return string|false Command response or false on failure
     */
    public static function executeCommand($connection, $command) 
    {
        if (!is_resource($connection)) {
            ErrorHandler::logError("Invalid AMI connection provided");
            return false;
        }
        
        $result = SimpleAmiClient::command($connection, $command);
        if ($result === false) {
            ErrorHandler::logError("AMI command failed", ['command' => $command]);
        }
        
        return $result;
    }
    
    /**
     * Disconnect from AMI
     * 
     * @param resource $connection AMI connection resource
     */
    public static function disconnect($connection) 
    {
        if (is_resource($connection)) {
            SimpleAmiClient::logoff($connection);
            
            // Remove from connection pool
            foreach (self::$connections as $host => $conn) {
                if ($conn === $connection) {
                    unset(self::$connections[$host]);
                    break;
                }
            }
        }
    }
    
    /**
     * Get node configuration from user's ini file
     * 
     * @param string $nodeId Node identifier
     * @return array|false Node configuration or false if not found
     */
    private static function getNodeConfig($nodeId) 
    {
        if (!isset($_SESSION['user'])) {
            return false;
        }
        
        $iniFile = get_ini_name($_SESSION['user']);
        if (!file_exists($iniFile)) {
            return false;
        }
        
        $config = parse_ini_file($iniFile, true);
        return isset($config[$nodeId]) ? $config[$nodeId] : false;
    }
    
    /**
     * Clean up all connections
     */
    public static function disconnectAll() 
    {
        foreach (self::$connections as $connection) {
            if (is_resource($connection)) {
                SimpleAmiClient::logoff($connection);
            }
        }
        self::$connections = [];
    }
}

/**
 * Input Validation Helper Class
 * 
 * Standardizes input validation and sanitization across the application.
 */
class ValidationHelper 
{
    /**
     * Validate node ID
     * 
     * @param mixed $nodeId Input to validate
     * @return string|false Valid node ID or false if invalid
     */
    public static function validateNodeId($nodeId) 
    {
        $nodeId = trim(strip_tags($nodeId));
        if (preg_match('/^\d+$/', $nodeId) && $nodeId > 0) {
            return $nodeId;
        }
        return false;
    }
    
    /**
     * Validate and sanitize general input
     * 
     * @param mixed $input Input to validate
     * @param string $type Type of validation (string, int, email, etc.)
     * @param array $options Additional validation options
     * @return mixed Sanitized input or false if invalid
     */
    public static function sanitizeInput($input, $type = 'string', $options = []) 
    {
        switch ($type) {
            case 'string':
                $input = trim(strip_tags($input));
                if (isset($options['max_length']) && strlen($input) > $options['max_length']) {
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
     * Validate file path for security
     * 
     * @param string $path File path to validate
     * @param array $allowedPaths Array of allowed base paths
     * @return string|false Validated path or false if invalid
     */
    public static function validateFilePath($path, $allowedPaths = []) 
    {
        // Remove any ../ attempts
        if (strpos($path, '..') !== false) {
            return false;
        }
        
        // If no allowed paths specified, use default safe paths
        if (empty($allowedPaths)) {
            $allowedPaths = [
                '/var/log/asterisk/',
                '/var/log/apache2/',
                '/tmp/',
                realpath(dirname(__FILE__) . '/../user_files/')
            ];
        }
        
        $realPath = realpath($path);
        if (!$realPath) {
            return false;
        }
        
        foreach ($allowedPaths as $allowedPath) {
            if (strpos($realPath, realpath($allowedPath)) === 0) {
                return $realPath;
            }
        }
        
        return false;
    }
    
    /**
     * Validate GPIO pin number
     * 
     * @param mixed $pin GPIO pin to validate
     * @return int|false Valid pin number or false if invalid
     */
    public static function validateGPIOPin($pin) 
    {
        $pin = filter_var($pin, FILTER_VALIDATE_INT);
        if ($pin === false || $pin < 0 || $pin > 40) {
            return false;
        }
        return $pin;
    }
    
    /**
     * Validate GPIO state
     * 
     * @param string $state GPIO state to validate
     * @return string|false Valid state or false if invalid
     */
    public static function validateGPIOState($state) 
    {
        $validStates = ['0', '1', 'input', 'output', 'up', 'down'];
        $state = trim(strip_tags($state));
        return in_array($state, $validStates) ? $state : false;
    }
}

/**
 * Security Helper Class
 * 
 * Provides standardized security functions for authentication and authorization.
 */
class SecurityHelper 
{
    /**
     * Check if user is logged in
     * 
     * @return bool True if logged in, false otherwise
     */
    public static function isLoggedIn() 
    {
        return isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true;
    }
    
    /**
     * Require user to be logged in or redirect/die
     * 
     * @param string $message Custom error message
     * @param bool $redirect Whether to redirect to login page
     */
    public static function requireLogin($message = null, $redirect = false) 
    {
        if (!self::isLoggedIn()) {
            if ($redirect) {
                header('Location: login.php');
                exit;
            } else {
                $message = $message ?: "ERROR: You must login to access this page.";
                die("<h3>" . htmlspecialchars($message) . "</h3>");
            }
        }
    }
    
    /**
     * Check if user has specific permission
     * 
     * @param string $permission Permission to check
     * @return bool True if user has permission, false otherwise
     */
    public static function hasPermission($permission) 
    {
        if (!function_exists('get_user_auth')) {
            return false;
        }
        return get_user_auth($permission);
    }
    
    /**
     * Require specific permission or die
     * 
     * @param string $permission Permission required
     * @param string $message Custom error message
     */
    public static function requirePermission($permission, $message = null) 
    {
        if (!self::hasPermission($permission)) {
            $message = $message ?: "ERROR: You are not authorized to access this function.";
            die("<h3>" . htmlspecialchars($message) . "</h3>");
        }
    }
    
    /**
     * Generate secure random token
     * 
     * @param int $length Token length
     * @return string Random token
     */
    public static function generateToken($length = 32) 
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        } else {
            // Fallback for older PHP versions
            return bin2hex(openssl_random_pseudo_bytes($length / 2));
        }
    }
    
    /**
     * Hash password securely
     * 
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public static function hashPassword($password) 
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify password against hash
     * 
     * @param string $password Plain text password
     * @param string $hash Stored password hash
     * @return bool True if password matches, false otherwise
     */
    public static function verifyPassword($password, $hash) 
    {
        return password_verify($password, $hash);
    }
}

/**
 * File Helper Class
 * 
 * Provides standardized file operations with security checks.
 */
class FileHelper 
{
    /**
     * Safely read file with security checks
     * 
     * @param string $filepath Path to file
     * @param array $allowedPaths Array of allowed base paths
     * @return string|false File contents or false on failure
     */
    public static function safeReadFile($filepath, $allowedPaths = []) 
    {
        $validPath = ValidationHelper::validateFilePath($filepath, $allowedPaths);
        if (!$validPath) {
            ErrorHandler::logError("Attempted to read invalid file path", ['path' => $filepath]);
            return false;
        }
        
        if (!is_readable($validPath)) {
            ErrorHandler::logError("File not readable", ['path' => $validPath]);
            return false;
        }
        
        return file_get_contents($validPath);
    }
    
    /**
     * Safely write file with security checks
     * 
     * @param string $filepath Path to file
     * @param string $content Content to write
     * @param array $allowedPaths Array of allowed base paths
     * @return bool True on success, false on failure
     */
    public static function safeWriteFile($filepath, $content, $allowedPaths = []) 
    {
        $validPath = ValidationHelper::validateFilePath($filepath, $allowedPaths);
        if (!$validPath) {
            ErrorHandler::logError("Attempted to write to invalid file path", ['path' => $filepath]);
            return false;
        }
        
        $result = file_put_contents($validPath, $content, LOCK_EX);
        if ($result === false) {
            ErrorHandler::logError("Failed to write file", ['path' => $validPath]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Get file extension safely
     * 
     * @param string $filename Filename
     * @return string File extension (lowercase)
     */
    public static function getFileExtension($filename) 
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
    
    /**
     * Check if file type is allowed
     * 
     * @param string $filename Filename
     * @param array $allowedTypes Array of allowed extensions
     * @return bool True if allowed, false otherwise
     */
    public static function isAllowedFileType($filename, $allowedTypes = ['txt', 'log', 'ini', 'conf']) 
    {
        $extension = self::getFileExtension($filename);
        return in_array($extension, $allowedTypes);
    }
}
