<?php
/**
 * Supermon-ng Database Authentication
 * 
 * Handles authentication, authorization, and validation for database operations.
 * Provides user access control, node validation, and AMI connection management.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Validate user access to database function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateDatabaseAccess(): bool {
    if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("DBTUSER"))) {
        return false;
    }
    return true;
}

/**
 * Get and validate localnode parameter
 * 
 * @return string|false Validated localnode or false if invalid
 */
function getValidatedLocalNode(): string|false {
    $localnode = trim(strip_tags($_GET['localnode'] ?? ''));
    
    if (empty($localnode)) {
        return false;
    }
    
    return $localnode;
}

/**
 * Load and validate user configuration
 * 
 * @param string $user User identifier
 * @param string $localnode Local node identifier
 * @return array|false Configuration data or false on failure
 */
function loadDatabaseConfig(string $user, string $localnode): array|false {
    $supIniFile = get_ini_name($user);

    if (!file_exists($supIniFile)) {
        return false;
    }

    $config = parse_ini_file($supIniFile, true);

    if (!isset($config[$localnode])) {
        return false;
    }

    $amiConfig = $config[$localnode];

    if (!isset($amiConfig['host']) || !isset($amiConfig['user']) || !isset($amiConfig['passwd'])) {
        return false;
    }

    return [
        'supIniFile' => $supIniFile,
        'config' => $config,
        'amiConfig' => $amiConfig
    ];
}

/**
 * Establish AMI connection and authenticate
 * 
 * @param array $amiConfig AMI configuration data
 * @return \resource|false File pointer to AMI connection or false on failure
 */
function connectToAsteriskManager(array $amiConfig): \resource|false {
    $fp = SimpleAmiClient::connect($amiConfig['host']);
    if ($fp === false) {
        return false;
    }

    if (SimpleAmiClient::login($fp, $amiConfig['user'], $amiConfig['passwd']) === false) {
        SimpleAmiClient::logoff($fp);
        return false;
    }

    return $fp;
}

/**
 * Retrieve database content from AMI
 * 
 * @param \resource $fp AMI connection file pointer
 * @return string|false Database output or false on failure
 */
function retrieveDatabaseContent(\resource $fp): string|false {
    $databaseOutput = SimpleAmiClient::command($fp, "database show");
    SimpleAmiClient::logoff($fp);
    
    return $databaseOutput;
}
