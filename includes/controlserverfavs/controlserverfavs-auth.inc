<?php
/**
 * Supermon-ng Control Server Favorites Authentication
 * 
 * Handles authentication, authorization, and validation for server favorites control operations.
 * Provides user access control, parameter validation, and AMI connection management with enhanced error handling.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Validate user access to control server favorites function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateControlserverfavsAccess(): bool {
    if (!isset($_SESSION['sm61loggedin']) || $_SESSION['sm61loggedin'] !== true) {
        return false;
    }
    return true;
}

/**
 * Get and validate request parameters with enhanced sanitization
 * 
 * @return array|false Array with 'node' and 'cmd' or false on failure
 */
function getControlserverfavsParameters(): array|false {
    $node = trim(strip_tags(filter_input(INPUT_GET, 'node', FILTER_SANITIZE_STRING) ?? ''));
    $cmd  = trim(strip_tags(filter_input(INPUT_GET, 'cmd', FILTER_SANITIZE_STRING) ?? ''));

    if (empty($node) || empty($cmd)) {
        return false;
    }

    return [
        'node' => $node,
        'cmd' => $cmd
    ];
}

/**
 * Load and validate node configuration with enhanced error handling
 * 
 * @param string $node Node name
 * @return array|false Node configuration or false on failure
 */
function loadControlserverfavsNodeConfig(string $node): array|false {
    $supIniFile = get_ini_name($_SESSION['user']);
    
    if (!file_exists($supIniFile)) {
        return false;
    }

    $config = parse_ini_file($supIniFile, true);
    if ($config === false) {
        return false;
    }

    if (!isset($config[$node])) {
        return false;
    }

    return $config[$node];
}

/**
 * Connect to AMI and authenticate with enhanced error handling
 * 
 * @param array $nodeConfig Node configuration array
 * @param string $node Node name
 * @return mixed AMI connection resource or false on failure
 * @throws RuntimeException On connection or authentication failure
 */
function connectToControlserverfavsAmi(array $nodeConfig, string $node): mixed {
    $fp = SimpleAmiClient::connect($nodeConfig['host']);
    if ($fp === false) {
        throw new RuntimeException("Could not connect to AMI host '{$nodeConfig['host']}' for node '$node'.");
    }

    if (SimpleAmiClient::login($fp, $nodeConfig['user'], $nodeConfig['passwd']) === false) {
        SimpleAmiClient::logoff($fp);
        throw new RuntimeException("Could not login to AMI on '{$nodeConfig['host']}' for node '$node' with user '{$nodeConfig['user']}'.");
    }

    return $fp;
}

/**
 * Execute command on AMI connection with enhanced error handling
 * 
 * @param mixed $fp AMI connection resource
 * @param string $node Node name
 * @param string $cmd Command to execute
 * @return string|false Command result or false on failure
 * @throws RuntimeException On command execution failure
 */
function executeControlserverfavsCommand(mixed $fp, string $node, string $cmd): string|false {
    $cmdString = str_replace("%node%", $node, $cmd);
    $commandOutput = SimpleAmiClient::command($fp, $cmdString);

    if ($commandOutput === false) {
        throw new RuntimeException("Failed to execute command '$cmdString' or received an error response from node '$node'.");
    }

    return $commandOutput;
}

/**
 * Get INI file path for error messages
 * 
 * @return string INI file path
 */
function getControlserverfavsIniPath(): string {
    return get_ini_name($_SESSION['user']);
}
