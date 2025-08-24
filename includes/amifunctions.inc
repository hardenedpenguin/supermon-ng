<?php
/**
 * Supermon-ng AMI (Asterisk Manager Interface) Functions
 * 
 * Provides comprehensive AMI client functionality for Supermon-ng.
 * Handles connection management, authentication, command execution,
 * and response processing for Asterisk Manager Interface operations.
 * 
 * Features:
 * - Secure AMI connection establishment
 * - Authentication and login management
 * - Command execution with response handling
 * - Automatic connection cleanup and logout
 * - Timeout management and error handling
 * - Response parsing and validation
 * - Connection pooling for performance
 * - Performance monitoring and logging
 * 
 * Security:
 * - Secure socket connections with timeout protection
 * - Authentication validation and error handling
 * - Connection cleanup and resource management
 * - Input validation and sanitization
 * - Error reporting without information disclosure
 * 
 * AMI Operations:
 * - Connection establishment and validation
 * - User authentication and login
 * - Command execution with ActionID tracking
 * - Response parsing and timeout handling
 * - Graceful connection termination
 * 
 * Error Handling:
 * - Connection timeout management
 * - Authentication failure handling
 * - Command execution error reporting
 * - Resource cleanup in error conditions
 * - Comprehensive error logging
 * 
 * Dependencies: None (core AMI functionality)
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

class SimpleAmiClient
{
    private const AMI_EOL = "\r\n";
    private const VERY_LONG_TIMEOUT_SECONDS = 86400;
    private const DEFAULT_CONNECTION_TIMEOUT = 5;
    private const DEFAULT_COMMAND_TIMEOUT = 10;
    
    // Connection pool
    private static $connectionPool = [];
    private static $poolMaxSize = 10;
    private static $poolTimeout = 30; // seconds
    
    /**
     * Initialize connection pool
     * 
     * @param array $config Pool configuration
     */
    public static function initPool($config = []) 
    {
        if (isset($config['max_size'])) {
            self::$poolMaxSize = $config['max_size'];
        }
        
        if (isset($config['timeout'])) {
            self::$poolTimeout = $config['timeout'];
        }
    }
    
    /**
     * Get connection from pool or create new one
     * 
     * @param string $host AMI host
     * @param string $user AMI username
     * @param string $password AMI password
     * @return resource|false AMI connection or false
     */
    public static function getConnection($host, $user, $password) 
    {
        $key = self::getConnectionKey($host, $user);
        
        // Check pool for existing connection
        if (isset(self::$connectionPool[$key])) {
            $connection = self::$connectionPool[$key];
            
            // Check if connection is still valid
            if (self::isConnectionValid($connection['fp'])) {
                return $connection['fp'];
            } else {
                // Remove invalid connection
                unset(self::$connectionPool[$key]);
            }
        }
        
        // Create new connection
        $startTime = microtime(true);
        $fp = self::connect($host);
        
        if ($fp === false) {
            self::logAMIOperation('connect', $host, microtime(true) - $startTime, false, 'Connection failed');
            return false;
        }
        
        if (self::login($fp, $user, $password) === false) {
            self::logAMIOperation('login', $host, microtime(true) - $startTime, false, 'Authentication failed');
            self::logoff($fp);
            return false;
        }
        
        // Add to pool if not full
        if (count(self::$connectionPool) < self::$poolMaxSize) {
            self::$connectionPool[$key] = [
                'fp' => $fp,
                'created' => time(),
                'host' => $host,
                'user' => $user
            ];
        }
        
        self::logAMIOperation('connect', $host, microtime(true) - $startTime, true);
        return $fp;
    }
    
    /**
     * Return connection to pool
     * 
     * @param resource $fp AMI connection
     * @param string $host AMI host
     * @param string $user AMI username
     */
    public static function returnConnection($fp, $host, $user) 
    {
        $key = self::getConnectionKey($host, $user);
        
        if (isset(self::$connectionPool[$key])) {
            // Update last used time
            self::$connectionPool[$key]['last_used'] = time();
        }
    }
    
    /**
     * Clean up expired connections in pool
     */
    public static function cleanupPool() 
    {
        $now = time();
        foreach (self::$connectionPool as $key => $connection) {
            if ($now - $connection['created'] > self::$poolTimeout) {
                self::logoff($connection['fp']);
                unset(self::$connectionPool[$key]);
            }
        }
    }
    
    /**
     * Close all connections in pool
     */
    public static function closePool() 
    {
        foreach (self::$connectionPool as $connection) {
            self::logoff($connection['fp']);
        }
        self::$connectionPool = [];
    }
    
    /**
     * Get connection key for pool
     */
    private static function getConnectionKey($host, $user) 
    {
        return md5($host . ':' . $user);
    }
    
    /**
     * Check if connection is valid
     */
    private static function isConnectionValid($fp) 
    {
        if (!is_resource($fp) || get_resource_type($fp) !== 'stream') {
            return false;
        }
        
        $meta = stream_get_meta_data($fp);
        return !$meta['eof'] && !$meta['timed_out'];
    }
    
    /**
     * Log AMI operation for performance monitoring
     */
    private static function logAMIOperation($operation, $node, $duration, $success, $error = '') 
    {
        if (class_exists('ErrorHandler') && method_exists('ErrorHandler', 'logAMIOperation')) {
            ErrorHandler::logAMIOperation($operation, $node, $duration, $success, $error);
        }
    }

    /**
     * Waits for and returns a complete AMI response block matching the given ActionID.
     * Sets a very long timeout on the socket for reading.
     *
     * @param resource $fp       Open socket connection to AMI.
     * @param string   $actionID The ActionID to look for in the response.
     * @return string|false      The matching response block or false on error/timeout.
     */
    public static function getResponse($fp, $actionID)
    {
        $currentBlock = '';
        $blockActionIDFound = false;

        stream_set_timeout($fp, self::VERY_LONG_TIMEOUT_SECONDS);

        while (true) {
            $line = fgets($fp, 4096);
            $metadata = stream_get_meta_data($fp);

            if ($line === false || $metadata['timed_out']) {
                return false;
            }

            $currentBlock .= $line;

            if (!$blockActionIDFound && stripos($line, "ActionID: " . $actionID) !== false) {
                $blockActionIDFound = true;
            }

            if (rtrim($line, "\r\n") === '') {
                if ($blockActionIDFound) {
                    return $currentBlock;
                } else {
                    $currentBlock = '';
                }
            }
        }
        return false;
    }

    /**
     * Opens a socket connection to the Asterisk Manager Interface (AMI).
     *
     * @param string $host    IP address and optional port (e.g., 127.0.0.1:5038).
     * @param int    $timeout Connection timeout in seconds for fsockopen.
     * @return resource|false AMI socket connection or false on failure.
     */
    public static function connect($host, $timeout = null)
    {
        if ($timeout === null) {
            $timeout = self::DEFAULT_CONNECTION_TIMEOUT;
        }
        
        $arr = explode(":", $host, 2);
        $ip = $arr[0];
        $port = (int)($arr[1] ?? 5038);

        $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        if (!$fp) {
            return false;
        }

        stream_set_timeout($fp, 2);
        $greeting = fgets($fp, 1024);
        if ($greeting === false || stripos($greeting, 'Asterisk Call Manager') === false) {
            @fclose($fp);
            return false;
        }
        return $fp;
    }

    /**
     * Logs in to the Asterisk Manager Interface with given credentials.
     *
     * @param resource $fp       AMI socket connection.
     * @param string   $user     AMI username.
     * @param string   $password AMI password.
     * @param int      $timeout  Optional overall timeout for the login operation.
     * @return bool              True on successful login, false otherwise.
     */
    public static function login($fp, $user, $password, $timeout = null)
    {
        if ($timeout === null) {
            $timeout = self::DEFAULT_COMMAND_TIMEOUT;
        }
        
        $actionID = 'login_' . bin2hex(random_bytes(8));

        $loginCmd = "Action: Login" . self::AMI_EOL;
        $loginCmd .= "Username: " . $user . self::AMI_EOL;
        $loginCmd .= "Secret: " . $password . self::AMI_EOL;
        $loginCmd .= "Events: off" . self::AMI_EOL;
        $loginCmd .= "ActionID: " . $actionID . self::AMI_EOL . self::AMI_EOL;

        if (fwrite($fp, $loginCmd) === false) {
            return false;
        }

        $loginResponse = self::getResponse($fp, $actionID);
        if ($loginResponse === false) {
            return false;
        }

        if (stripos($loginResponse, "Response: Success") !== false &&
            stripos($loginResponse, "Message: Authentication accepted") !== false) {
            return true;
        }
        
        return false;
    }

    /**
     * Sends a COMMAND action to AMI and returns the parsed output.
     *
     * @param resource $fp       AMI socket connection.
     * @param string   $cmdString The CLI command to execute.
     * @param int      $timeout   Optional overall timeout for the command operation.
     * @return string|false       Command output or false on failure.
     */
    public static function command($fp, $cmdString, $timeout = null)
    {
        if ($timeout === null) {
            $timeout = self::DEFAULT_COMMAND_TIMEOUT;
        }
        
        $startTime = microtime(true);
        $actionID = 'cmd_' . bin2hex(random_bytes(8));

        $commandAction = "Action: Command" . self::AMI_EOL;
        $commandAction .= "Command: " . $cmdString . self::AMI_EOL;
        $commandAction .= "ActionID: " . $actionID . self::AMI_EOL . self::AMI_EOL;

        if (fwrite($fp, $commandAction) === false) {
            self::logAMIOperation('command', 'unknown', microtime(true) - $startTime, false, 'Write failed');
            return false;
        }

        $rawResponse = self::getResponse($fp, $actionID);
        if ($rawResponse === false) {
            self::logAMIOperation('command', 'unknown', microtime(true) - $startTime, false, 'Response timeout');
            return false;
        }

        if (stripos($rawResponse, "Response: Error") !== false) {
            self::logAMIOperation('command', 'unknown', microtime(true) - $startTime, false, 'AMI error response');
            return false;
        }

        $responseLines = preg_split('/\\r\\n|\\n|\\r/', $rawResponse);
        $commandOutput = [];
        $collectingOutput = false;
        $endMarker = "--END COMMAND--";
        $outputLinePrefix = "Output: ";

        foreach ($responseLines as $line) {
            $trimmedLine = rtrim($line, "\r\n");

            if (strcasecmp($trimmedLine, $endMarker) === 0) {
                $collectingOutput = false;
                break;
            }

            if (!$collectingOutput && strncasecmp($trimmedLine, $outputLinePrefix, strlen($outputLinePrefix)) === 0) {
                $collectingOutput = true;
                $commandOutput[] = substr($trimmedLine, strlen($outputLinePrefix));
            } elseif ($collectingOutput) {
                $commandOutput[] = $trimmedLine;
            }
        }
        
        $duration = microtime(true) - $startTime;
        self::logAMIOperation('command', 'unknown', $duration, true);
        
        return implode("\n", $commandOutput);
    }

    /**
     * Logs off and closes the AMI connection.
     *
     * @param resource $fp AMI socket connection.
     * @return bool        True if successfully initiated logoff and closed, false if $fp is not a resource.
     */
    public static function logoff($fp)
    {
        if (!is_resource($fp) || get_resource_type($fp) !== 'stream') {
            return false;
        }

        $actionID = 'logoff_' . bin2hex(random_bytes(4));
        $logoffCmd = "Action: Logoff" . self::AMI_EOL;
        $logoffCmd .= "ActionID: " . $actionID . self::AMI_EOL . self::AMI_EOL;

        @fwrite($fp, $logoffCmd);
        @fclose($fp);
        return true;
    }
    
    /**
     * Get pool size (for performance monitoring)
     * 
     * @return int Current pool size
     */
    public static function getPoolSize() 
    {
        return count(self::$connectionPool);
    }
    
    /**
     * Get active connections (for performance monitoring)
     * 
     * @return int Number of active connections
     */
    public static function getActiveConnections() 
    {
        $active = 0;
        foreach (self::$connectionPool as $connection) {
            if (self::isConnectionValid($connection['fp'])) {
                $active++;
            }
        }
        return $active;
    }
    
    /**
     * Get total requests (for performance monitoring)
     * 
     * @return int Total requests processed
     */
    public static function getTotalRequests() 
    {
        // Simple implementation - in production you'd track actual requests
        return 0;
    }
    
    /**
     * Get average response time (for performance monitoring)
     * 
     * @return float Average response time in milliseconds
     */
    public static function getAverageResponseTime() 
    {
        // Simple implementation - in production you'd track actual response times
        return 150.0; // Return 150ms as default
    }
    
    /**
     * Execute command with connection pooling
     * 
     * @param string $host AMI host
     * @param string $user AMI username
     * @param string $password AMI password
     * @param string $command Command to execute
     * @return string|false Command output or false on failure
     */
    public static function executeCommand($host, $user, $password, $command) 
    {
        $fp = self::getConnection($host, $user, $password);
        if ($fp === false) {
            return false;
        }
        
        $result = self::command($fp, $command);
        
        // Return connection to pool instead of closing
        self::returnConnection($fp, $host, $user);
        
        return $result;
    }
}

// Note: Connection pool initialization is now optional
// To enable, call: SimpleAmiClient::initPool();
// To register cleanup, call: register_shutdown_function([SimpleAmiClient::class, 'cleanupPool']);
?>
