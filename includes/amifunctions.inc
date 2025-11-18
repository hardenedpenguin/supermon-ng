<?php
/**
 * Supermon-ng AMI (Asterisk Manager Interface) Functions
 * 
 * Provides comprehensive AMI client functionality for Supermon-ng.
 * Matches Allmon3's AMI implementation patterns for consistency.
 * 
 * Features:
 * - Secure AMI connection establishment
 * - Authentication and login management
 * - Command execution with response handling
 * - Generic action method (Allmon3 style)
 * - Automatic connection cleanup and logout
 * - Timeout management and error handling
 * - Response parsing and validation
 * - Connection pooling for performance
 * - Performance monitoring and logging
 * - Parsing methods for XStat, SawStat, VoterStatus
 * 
 * @author Supermon-ng Team
 * @version 4.0.0
 * @since 4.0.0
 */

/**
 * AMI Exception class (Allmon3 style)
 */
class AMIException extends Exception
{
    public const CONNECTION_FAILED = 1;
    public const AUTHENTICATION_FAILED = 2;
    public const COMMAND_FAILED = 3;
    public const PROTOCOL_ERROR = 4;
    public const TIMEOUT = 5;
    public const INVALID_RESPONSE = 6;
    
    public function __construct($message, $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

class SimpleAmiClient
{
    private const AMI_EOL = "\r\n";
    private const VERY_LONG_TIMEOUT_SECONDS = 86400;
    private const DEFAULT_CONNECTION_TIMEOUT = 3;
    private const DEFAULT_COMMAND_TIMEOUT = 8;
    
    // Connection pool
    private static $connectionPool = [];
    private static $poolMaxSize = 25;
    private static $poolTimeout = 45; // seconds
    
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
     * Generate ActionID (Allmon3 style - using uniqid with entropy)
     * 
     * @return string Unique ActionID
     */
    private static function generateActionID($prefix = '')
    {
        return $prefix . uniqid('', true) . '_' . mt_rand(1000, 9999);
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
     * Get AMI response - reads until \r\n\r\n (Allmon3 style)
     * Falls back to ActionID matching if ActionID is provided
     *
     * @param resource $fp       Open socket connection to AMI.
     * @param string|null $actionID Optional ActionID to look for in the response.
     * @return string|false      The matching response block or false on error/timeout.
     */
    public static function getResponse($fp, $actionID = null)
    {
        $response = '';
        $foundActionID = ($actionID === null);
        
        stream_set_timeout($fp, self::VERY_LONG_TIMEOUT_SECONDS);

        while (true) {
            $line = fgets($fp, 4096);
            $metadata = stream_get_meta_data($fp);

            if ($line === false || $metadata['timed_out']) {
                return false;
            }

            $response .= $line;

            // Check for ActionID match if specified
            if ($actionID !== null && !$foundActionID) {
                if (stripos($line, "ActionID: " . $actionID) !== false) {
                    $foundActionID = true;
                }
            }

            // Check for end of response (\r\n\r\n) - Allmon3 style
            if (substr($response, -4) === "\r\n\r\n") {
                if ($actionID === null || $foundActionID) {
                    return $response;
                }
                // Reset if ActionID doesn't match yet
                $response = '';
            }
        }
        
        return false;
    }

    /**
     * Opens a socket connection to the Asterisk Manager Interface (AMI).
     * Validates greeting explicitly (Allmon3 style).
     *
     * @param string $host    IP address and optional port (e.g., 127.0.0.1:5038).
     * @param int    $timeout Connection timeout in seconds for fsockopen.
     * @return resource|false AMI socket connection or false on failure.
     * @throws AMIException On connection failure
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
        
        // Explicit validation (Allmon3 style)
        if ($greeting === false || stripos($greeting, 'Asterisk Call Manager') === false) {
            @fclose($fp);
            return false;
        }
        
        return $fp;
    }

    /**
     * Logs in to the Asterisk Manager Interface with given credentials.
     * Uses uppercase protocol headers (Allmon3 style).
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
        
        $actionID = self::generateActionID('login_');

        // Uppercase protocol headers (Allmon3 style)
        $loginCmd = "ACTION: LOGIN" . self::AMI_EOL;
        $loginCmd .= "USERNAME: " . $user . self::AMI_EOL;
        $loginCmd .= "SECRET: " . $password . self::AMI_EOL;
        $loginCmd .= "EVENTS: 0" . self::AMI_EOL;
        $loginCmd .= "ActionID: " . $actionID . self::AMI_EOL . self::AMI_EOL;

        if (fwrite($fp, $loginCmd) === false) {
            return false;
        }

        $loginResponse = self::getResponse($fp, $actionID);
        if ($loginResponse === false) {
            return false;
        }

        // Check for success response
        if (stripos($loginResponse, "Response: Success") !== false &&
            stripos($loginResponse, "Message: Authentication accepted") !== false) {
            return true;
        }
        
        return false;
    }

    /**
     * Generic AMI action method (Allmon3 style - like asl_cmd_response)
     * Sends any AMI action with parameters and returns the raw response.
     *
     * @param resource $fp       AMI socket connection.
     * @param string   $action   AMI action name (e.g., "RptStatus", "VoterStatus").
     * @param array    $params   Action parameters (key => value pairs).
     * @param int|null $timeout  Optional overall timeout for the action operation.
     * @return string|false       Raw response or false on failure.
     */
    public static function action($fp, $action, $params = [], $timeout = null)
    {
        if ($timeout === null) {
            $timeout = self::DEFAULT_COMMAND_TIMEOUT;
        }
        
        $actionID = self::generateActionID('action_');
        
        // Build action command with uppercase headers (Allmon3 style)
        $cmd = "ACTION: " . strtoupper($action) . self::AMI_EOL;
        foreach ($params as $key => $value) {
            // Uppercase parameter keys (Allmon3 style)
            $cmd .= strtoupper($key) . ": " . $value . self::AMI_EOL;
        }
        $cmd .= "ActionID: " . $actionID . self::AMI_EOL . self::AMI_EOL;
        
        if (fwrite($fp, $cmd) === false) {
            return false;
        }
        
        return self::getResponse($fp, $actionID);
    }

    /**
     * Sends a COMMAND action to AMI and returns the parsed output.
     * Uses uppercase protocol headers (Allmon3 style).
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
        $actionID = self::generateActionID('cmd_');

        // Uppercase protocol headers (Allmon3 style)
        $commandAction = "ACTION: COMMAND" . self::AMI_EOL;
        $commandAction .= "COMMAND: " . $cmdString . self::AMI_EOL;
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

        // Parse command output (handles both Asterisk 20/ASL3 and Classic formats)
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $rawResponse));
        $commandOutput = [];
        $collectingOutput = false;
        $outputLinePrefix = "Output: ";
        $prefixLen = strlen($outputLinePrefix);

        foreach ($lines as $line) {
            $trimmedLine = rtrim($line);

            if (strcasecmp($trimmedLine, "--END COMMAND--") === 0) {
                break;
            }

            if (!$collectingOutput && strncasecmp($trimmedLine, $outputLinePrefix, $prefixLen) === 0) {
                $collectingOutput = true;
                $commandOutput[] = substr($trimmedLine, $prefixLen);
            } elseif ($collectingOutput) {
                $commandOutput[] = $trimmedLine;
            }
        }
        
        // Clear large arrays immediately to free memory
        unset($lines, $rawResponse);
        
        $result = implode("\n", $commandOutput);
        unset($commandOutput);
        
        $duration = microtime(true) - $startTime;
        self::logAMIOperation('command', 'unknown', $duration, true);
        
        return $result;
    }

    /**
     * Logs off and closes the AMI connection.
     * Uses uppercase protocol headers (Allmon3 style).
     *
     * @param resource $fp AMI socket connection.
     * @return bool        True if successfully initiated logoff and closed, false if $fp is not a resource.
     */
    public static function logoff($fp)
    {
        if (!is_resource($fp) || get_resource_type($fp) !== 'stream') {
            return false;
        }

        $actionID = self::generateActionID('logoff_');
        
        // Uppercase protocol headers (Allmon3 style)
        $logoffCmd = "ACTION: LOGOFF" . self::AMI_EOL;
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
    
    /**
     * Parse XStat response (Allmon3 style)
     * Parses RptStatus XStat command response into structured data.
     *
     * @param string $xstatResponse Raw XStat response from AMI
     * @param array $nodeDatabase Node database for lookups
     * @return array Parsed node connection data
     */
    public static function parseXStat($xstatResponse, $nodeDatabase = [])
    {
        $nodeConnections = [];
        $lines = preg_split('/[\r\n]+/', $xstatResponse);
        
        foreach ($lines as $line) {
            // Parse Conn: lines
            if (preg_match('/^Conn:\s+(.+)$/', $line, $matches)) {
                $connData = preg_split('/\s+/', trim($matches[1]), 6);
                
                if (count($connData) >= 5) {
                    $nodeName = $connData[0];
                    $nodeInfo = [
                        'IP' => null,
                        'DIR' => null,
                        'CTIME' => null,
                        'CSTATE' => null,
                        'PTT' => false,
                        'SSK' => -1,
                        'SSU' => -1,
                        'MODE' => 'UNSET',
                        'DESC' => 'Private or Unavailable'
                    ];
                    
                    // Handle IP presence (6 elements = has IP, 5 = no IP)
                    if (count($connData) == 6) {
                        $nodeInfo['IP'] = $connData[2];
                        $nodeInfo['DIR'] = $connData[3];
                        $nodeInfo['CTIME'] = $connData[4];
                        $nodeInfo['CSTATE'] = $connData[5];
                    } else {
                        $nodeInfo['DIR'] = $connData[2];
                        $nodeInfo['CTIME'] = $connData[3];
                        $nodeInfo['CSTATE'] = $connData[4];
                    }
                    
                    // Lookup node info from database
                    if (isset($nodeDatabase[$nodeName])) {
                        $db = $nodeDatabase[$nodeName];
                        $nodeInfo['DESC'] = trim(($db['CALL'] ?? '') . ' ' . ($db['DESC'] ?? '') . ' ' . ($db['LOC'] ?? ''));
                    } elseif (preg_match('/^3[0-9]{6}$/', $nodeName)) {
                        // Echolink node
                        $nodeInfo['DESC'] = 'Echolink Node';
                    } elseif (preg_match('/^.*-P$/', $nodeName)) {
                        // Phone portal
                        $nodeInfo['DESC'] = 'Allstar Telephone Portal User';
                        $nodeInfo['MODE'] = 'Transceive';
                    } elseif (preg_match('/^[A-Za-z]/', $nodeName)) {
                        // Direct client
                        $nodeInfo['DESC'] = 'Direct Client';
                    }
                    
                    $nodeConnections[$nodeName] = $nodeInfo;
                }
            }
            // Parse LinkedNodes for mode detection
            elseif (preg_match('/^LinkedNodes:\s*(.+)$/', $line, $matches)) {
                $linkedNodes = preg_split('/,/', $matches[1]);
                foreach ($linkedNodes as $link) {
                    $link = trim($link);
                    if (preg_match('/^([TRC])(\S+)/', $link, $linkMatch)) {
                        $mode = $linkMatch[1];
                        $nodeName = $linkMatch[2];
                        
                        if (isset($nodeConnections[$nodeName])) {
                            switch ($mode) {
                                case 'T':
                                    $nodeConnections[$nodeName]['MODE'] = 'Transceive';
                                    break;
                                case 'R':
                                    $nodeConnections[$nodeName]['MODE'] = 'Monitor';
                                    break;
                                case 'C':
                                    $nodeConnections[$nodeName]['MODE'] = 'Connecting';
                                    break;
                            }
                        }
                    }
                }
            }
            // Parse Var: lines for keyed status
            elseif (preg_match('/^Var:\s+RPT_(TX|TXE|RX)KEYED=([01])/', $line, $matches)) {
                // Store keyed status (can be used by caller)
                $keyedType = $matches[1];
                $keyedValue = ($matches[2] == '1');
                // This would be stored in a separate structure by the caller
            }
        }
        
        return $nodeConnections;
    }
    
    /**
     * Parse SawStat response (Allmon3 style)
     * Parses RptStatus SawStat command response for connection keyed timing data.
     *
     * @param string $sawStatResponse Raw SawStat response from AMI
     * @return array Parsed connection keyed data
     */
    public static function parseSawStat($sawStatResponse)
    {
        $connectionData = [];
        $lines = preg_split('/[\r\n]+/', $sawStatResponse);
        
        foreach ($lines as $line) {
            // Parse Conn: lines (format: Conn: NODE PTT SEC_SINCE_KEY SEC_SINCE_UNKEY)
            if (preg_match('/^Conn:\s+(\S+)\s+(\d+)\s+(\d+)\s+(\d+)/', $line, $matches)) {
                $nodeName = $matches[1];
                $connectionData[$nodeName] = [
                    'PTT' => (int)$matches[2],
                    'SSK' => (int)$matches[3],  // Seconds since key
                    'SSU' => (int)$matches[4]   // Seconds since unkey
                ];
            }
        }
        
        return $connectionData;
    }
    
    /**
     * Parse VoterStatus response (Allmon3 style)
     * Parses VoterStatus command response and generates HTML output.
     *
     * @param string $voterResponse Raw VoterStatus response from AMI
     * @return array ['html' => string, 'voters' => array, 'voted' => string|null]
     */
    public static function parseVoterStatus($voterResponse)
    {
        $voters = [];
        $voted = null;
        $currentClient = null;
        
        $lines = preg_split('/[\r\n]+/', $voterResponse);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Parse Client lines (Allmon3 format: "Client: <name>")
            if (preg_match('/^Client:\s*(.+)$/', $line, $matches)) {
                $rawClientName = trim($matches[1]);
                // Check for Mix suffix BEFORE cleaning (for color determination)
                $isMix = (strpos($rawClientName, ' Mix') !== false);
                // Clean known suffixes (like formatVoterHTML does)
                $clientName = preg_replace('/(\sMaster\sActiveMaster|\sLocal\sLocal|\sMix)$/', '', $rawClientName);
                $currentClient = $clientName;
                // Store with mix flag for later use
                $voters[$currentClient] = ['rssi' => 0, 'isMix' => $isMix];
            }
            // Parse RSSI lines (Allmon3 format: "RSSI: <value>")
            elseif (preg_match('/^RSSI:\s+(\d+)/', $line, $matches) && $currentClient !== null) {
                if (is_array($voters[$currentClient])) {
                    $voters[$currentClient]['rssi'] = (int)$matches[1];
                } else {
                    $voters[$currentClient] = (int)$matches[1];
                }
            }
            // Parse Voted line (Allmon3 format: "Voted: <client>")
            elseif (preg_match('/^Voted:\s+(.+)$/', $line, $matches)) {
                $votedClient = trim($matches[1]);
                // Clean the voted client name to match
                $voted = preg_replace('/(\sMaster\sActiveMaster|\sLocal\sLocal|\sMix)$/', '', $votedClient);
            }
        }
        
        // Generate HTML (Allmon3 style - using table format like original supermon)
        $html = '<table class="rtcm"><tr><th>Client</th><th>RSSI</th></tr>';
        
        if (empty($voters)) {
            $html .= '<tr><td><div class="voter-no-clients">&nbsp;No clients&nbsp;</div></td>';
            $html .= '<td><div class="voter-empty-bar">&nbsp;</div></td></tr>';
        } else {
            foreach ($voters as $clientId => $clientData) {
                // Handle both array format (with isMix) and simple int format (backward compatibility)
                $rssi = is_array($clientData) ? ($clientData['rssi'] ?? 0) : $clientData;
                $isMix = is_array($clientData) ? ($clientData['isMix'] ?? false) : false;
                
                // Calculate bar width in pixels (0-255 range, max 300px width)
                $bar_width_px = round(($rssi / 255) * 300);
                $bar_width_px = ($rssi == 0) ? 3 : max(1, $bar_width_px);
                
                // Determine bar color (Allmon3 style)
                $barcolor = "#0099FF"; // Blue for voting station
                $textcolor = 'white';
                
                // Check if this client is voted
                if ($voted && $clientId == $voted) {
                    $barcolor = 'greenyellow'; // Green for voted
                    $textcolor = 'black';
                } elseif ($isMix) {
                    $barcolor = 'cyan'; // Cyan for non-voting mix station
                    $textcolor = 'black';
                }
                
                $html .= '<tr>';
                $html .= '<td><div>' . htmlspecialchars($clientId) . '</div></td>';
                $html .= '<td><div class="text"><div class="barbox_a">';
                $html .= '<div class="bar" style="width: ' . $bar_width_px . 'px; background-color: ' . $barcolor . '; color: ' . $textcolor . '">' . $rssi . '</div>';
                $html .= '</div></div></td></tr>';
            }
        }
        
        $html .= '<tr><td colspan="2"> </td></tr>';
        $html .= '</table><br/>';
        
        return [
            'html' => $html,
            'voters' => $voters,
            'voted' => $voted
        ];
    }
}

// Note: Connection pool initialization is now optional
// To enable, call: SimpleAmiClient::initPool();
// To register cleanup, call: register_shutdown_function([SimpleAmiClient::class, 'cleanupPool']);
?>
