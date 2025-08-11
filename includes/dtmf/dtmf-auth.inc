<?php
/**
 * Supermon-ng DTMF Authentication
 * 
 * Handles authentication, authorization, and validation for DTMF operations.
 * Provides user access control, parameter validation, and AMI connection management.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Validate user access to DTMF function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateDtmfAccess(): bool {
    if (!isset($_SESSION['sm61loggedin']) || $_SESSION['sm61loggedin'] !== true) {
        return false;
    }
    
    if (!function_exists('get_user_auth') || !get_user_auth("DTMFUSER")) {
        return false;
    }
    
    return true;
}

/**
 * Get and validate DTMF parameters
 * 
 * @return array|false Array with validated parameters or false on failure
 */
function getDtmfParameters(): array|false {
    $dtmf = trim(strip_tags($_POST['node'] ?? ''));
    $localnode = trim(strip_tags($_POST['localnode'] ?? ''));
    
    if (empty($dtmf)) {
        return false;
    }
    
    if (empty($localnode)) {
        return false;
    }
    
    return [
        'dtmf' => $dtmf,
        'localnode' => $localnode
    ];
}

/**
 * Load and validate node configuration
 * 
 * @param string $localnode Local node identifier
 * @return array|false Node configuration or false on failure
 */
function loadDtmfNodeConfig(string $localnode): array|false {
    if (!isset($_SESSION['user']) || !function_exists('get_ini_name')) {
        return false;
    }
    
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
    
    if (!isset($nodeConfig['host']) || !isset($nodeConfig['user']) || !isset($nodeConfig['passwd'])) {
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
function connectToDtmfAmi(array $nodeConfig): mixed {
    if (!class_exists('SimpleAmiClient')) {
        return false;
    }
    
    $amiSocket = SimpleAmiClient::connect($nodeConfig['host']);
    if ($amiSocket === false) {
        return false;
    }
    
    if (SimpleAmiClient::login($amiSocket, $nodeConfig['user'], $nodeConfig['passwd']) === false) {
        SimpleAmiClient::logoff($amiSocket);
        return false;
    }
    
    return $amiSocket;
}

/**
 * Execute DTMF command
 * 
 * @param mixed $amiSocket AMI connection resource
 * @param string $localnode Local node identifier
 * @param string $dtmf DTMF command
 * @return string|false Command output or false on failure
 */
function executeDtmfCommand(mixed $amiSocket, string $localnode, string $dtmf): string|false {
    $asteriskCommand = "rpt fun " . $localnode . " " . $dtmf;
    return SimpleAmiClient::command($amiSocket, $asteriskCommand);
}

/**
 * Get INI file path for error messages
 * 
 * @return string INI file path
 */
function getDtmfIniPath(): string {
    return get_ini_name($_SESSION['user']);
}
