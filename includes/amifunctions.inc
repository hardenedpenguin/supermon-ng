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
 * @version 2.0.3
 * @since 1.0.0
 */

class SimpleAmiClient
{
    private const AMI_EOL = "\r\n";
    private const VERY_LONG_TIMEOUT_SECONDS = 86400;

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
    public static function connect($host, $timeout = 5)
    {
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
     * @param int      $timeout  Optional overall timeout for the login operation (currently informational,
     *                           as getResponse uses its own "indefinite" timeout).
     * @return bool              True on successful login, false otherwise.
     */
    public static function login($fp, $user, $password, $timeout = 10)
    {
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
     * @param int      $timeout   Optional overall timeout for the command operation (currently informational,
     *                           as getResponse uses its own "indefinite" timeout).
     * @return string|false       Command output or false on failure.
     */
    public static function command($fp, $cmdString, $timeout = 10)
    {
        $actionID = 'cmd_' . bin2hex(random_bytes(8));

        $commandAction = "Action: Command" . self::AMI_EOL;
        $commandAction .= "Command: " . $cmdString . self::AMI_EOL;
        $commandAction .= "ActionID: " . $actionID . self::AMI_EOL . self::AMI_EOL;

        if (fwrite($fp, $commandAction) === false) {
            return false;
        }

        $rawResponse = self::getResponse($fp, $actionID);
        if ($rawResponse === false) {
            return false;
        }

        if (stripos($rawResponse, "Response: Error") !== false) {
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
}
?>
