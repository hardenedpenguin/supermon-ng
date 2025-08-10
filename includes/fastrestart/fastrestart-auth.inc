<?php
/**
 * Supermon-ng FastRestart Authentication
 * 
 * Handles authentication, authorization, and validation for fast restart operations.
 * Provides user access control, parameter validation, and AMI connection management.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Validate user access to fastrestart function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateFastrestartAccess(): bool {
    if (!isset($_SESSION['sm61loggedin']) || $_SESSION['sm61loggedin'] !== true || !get_user_auth("FSTRESUSER")) {
        return false;
    }
    
    return true;
}

/**
 * Get and validate localnode parameter
 * 
 * @return string|false Validated localnode or false on failure
 */
function getFastrestartLocalnode(): string|false {
    if (!isset($_POST['localnode']) || empty(trim($_POST['localnode']))) {
        return false;
    }
    
    return trim(strip_tags($_POST['localnode']));
}

/**
 * Load and validate node configuration
 * 
 * @param string $localnode Local node identifier
 * @return array|false Node configuration or false on failure
 */
function loadFastrestartNodeConfig(string $localnode): array|false {
    $iniFilePath = get_ini_name($_SESSION['user']);
    
    if (!file_exists($iniFilePath)) {
        return false;
    }
    
    $config = parse_ini_file($iniFilePath, true);
    if ($config === false) {
        return false;
    }
    
    if (!isset($config[$localnode])) {
        return false;
    }
    
    $nodeConfig = $config[$localnode];
    
    $requiredKeys = ['host', 'user', 'passwd'];
    foreach ($requiredKeys as $key) {
        if (!isset($nodeConfig[$key]) || $nodeConfig[$key] === '') {
            return false;
        }
    }
    
    return $nodeConfig;
}

/**
 * Connect to AMI and authenticate
 * 
 * @param array $nodeConfig Node configuration array
 * @return \resource|false AMI connection resource or false on failure
 */
function connectToFastrestartAmi(array $nodeConfig): \resource|false {
    $fp = SimpleAmiClient::connect($nodeConfig['host']);
    if ($fp === false) {
        return false;
    }
    
    if (SimpleAmiClient::login($fp, $nodeConfig['user'], $nodeConfig['passwd']) === false) {
        SimpleAmiClient::logoff($fp);
        return false;
    }
    
    return $fp;
}

/**
 * Execute restart command
 * 
 * @param \resource $fp AMI connection resource
 * @return string|false Command response or false on failure
 */
function executeFastrestartCommand($fp): string|false {
    return SimpleAmiClient::command($fp, "restart now");
}

/**
 * Get INI file path for error messages
 * 
 * @return string INI file path
 */
function getFastrestartIniPath(): string {
    return get_ini_name($_SESSION['user']);
}
