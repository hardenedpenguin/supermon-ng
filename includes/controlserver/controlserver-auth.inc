<?php
/**
 * Supermon-ng Control Server Authentication
 * 
 * Handles authentication, authorization, and validation for server control operations.
 * Provides user access control, parameter validation, and AMI connection management.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Validate user access to control server function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateControlserverAccess(): bool {
    if (!isset($_SESSION['sm61loggedin']) || $_SESSION['sm61loggedin'] !== true) {
        return false;
    }
    return true;
}

/**
 * Get and validate request parameters
 * 
 * @return array|false Array with 'node' and 'cmd' or false on failure
 */
function getControlserverParameters(): array|false {
    $node = isset($_GET['node']) ? trim(strip_tags($_GET['node'])) : '';
    $cmd  = isset($_GET['cmd']) ? trim(strip_tags($_GET['cmd'])) : '';

    if (empty($node) || empty($cmd)) {
        return false;
    }

    return [
        'node' => $node,
        'cmd' => $cmd
    ];
}

/**
 * Load and validate node configuration
 * 
 * @param string $node Node name
 * @return array|false Node configuration or false on failure
 */
function loadControlserverNodeConfig(string $node): array|false {
    $iniFilePath = get_ini_name($_SESSION['user']);

    if (!file_exists($iniFilePath)) {
        return false;
    }

    $config = parse_ini_file($iniFilePath, true);
    if ($config === false) {
        return false;
    }

    if (!isset($config[$node])) {
        return false;
    }

    $nodeConfig = $config[$node];

    if (!isset($nodeConfig['host'], $nodeConfig['user'], $nodeConfig['passwd'])) {
        return false;
    }

    return $nodeConfig;
}

/**
 * Connect to AMI and authenticate
 * 
 * @param array $nodeConfig Node configuration array
 * @return mixed AMI connection resource or false on failure
 */
function connectToControlserverAmi(array $nodeConfig): mixed {
    $fp = SimpleAmiClient::connect($nodeConfig['host']);
    if (!$fp) {
        return false;
    }

    if (!SimpleAmiClient::login($fp, $nodeConfig['user'], $nodeConfig['passwd'])) {
        SimpleAmiClient::logoff($fp);
        return false;
    }

    return $fp;
}

/**
 * Execute command on AMI connection
 * 
 * @param mixed $fp AMI connection resource
 * @param string $node Node name
 * @param string $cmd Command to execute
 * @return string|false Command result or false on failure
 */
function executeControlserverCommand(mixed $fp, string $node, string $cmd): string|false {
    $cmdString = str_replace("%node%", $node, $cmd);
    return SimpleAmiClient::command($fp, $cmdString);
}

/**
 * Get INI file path for error messages
 * 
 * @return string INI file path
 */
function getControlserverIniPath(): string {
    return get_ini_name($_SESSION['user']);
}
