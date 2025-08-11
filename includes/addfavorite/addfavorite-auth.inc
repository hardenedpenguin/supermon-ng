<?php
/**
 * Supermon-ng Add Favorite Authentication
 * 
 * Handles authentication, authorization, and validation for add favorite operations.
 * Provides user access control, node validation, and astdb.txt lookup functionality.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Validate user access to add favorite function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateAddFavoriteAccess(): bool {
    if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("FAVUSER"))) {
        return false;
    }
    return true;
}

/**
 * Get and validate node parameter
 * 
 * @return string|false Validated node number or false if invalid
 */
function getValidatedNode(): string|false {
    $node_param = $_GET['node'] ?? '';
    $node = trim(strip_tags($node_param));

    if (!is_numeric($node) || $node === '') {
        return false;
    }

    return $node;
}

/**
 * Load astdb.txt and parse node information
 * 
 * @return array|false Parsed astdb data or false on failure
 */
function loadAstdbData() {
    global $ASTDB_TXT;
    
    if (!file_exists($ASTDB_TXT)) {
        return false;
    }

    $astdb = array();
    $lines = file($ASTDB_TXT, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    if ($lines === false) {
        return false;
    }

    foreach ($lines as $line) {
        $parts = explode('|', $line);
        if (count($parts) >= 4) {
            $node = trim($parts[0]);
            $callsign = trim($parts[1]);
            $description = trim($parts[2]);
            $location = trim($parts[3]);
            
            $astdb[$node] = array(
                'node' => $node,
                'callsign' => $callsign,
                'description' => $description,
                'location' => $location
            );
        }
    }

    return $astdb;
}

/**
 * Look up node information in astdb.txt
 * 
 * @param string $node Node number to look up
 * @return array|false Node information or false if not found
 */
function lookupNodeInfo($node) {
    $astdb = loadAstdbData();
    
    if ($astdb === false) {
        return false;
    }

    return isset($astdb[$node]) ? $astdb[$node] : false;
}

/**
 * Get user's favorites file path
 * 
 * @param string $user User identifier
 * @return string Path to user's favorites file
 */
function getUserFavoritesFile(string $user): string {
    return get_fav_ini_name($user);
}

/**
 * Check if node already exists in favorites
 * 
 * @param string $user User identifier
 * @param string $node Node number
 * @return bool True if node exists in favorites, false otherwise
 */
function nodeExistsInFavorites(string $user, string $node): bool {
    $favoritesFile = getUserFavoritesFile($user);
    
    if (!file_exists($favoritesFile)) {
        return false;
    }

    $config = parse_ini_file($favoritesFile, true);
    if ($config === false) {
        return false;
    }

    // Check general section
    if (isset($config['general']['label'])) {
        foreach ($config['general']['label'] as $label) {
            if (strpos($label, $node) !== false) {
                return true;
            }
        }
    }

    // Check node-specific section
    if (isset($config[$node]['label'])) {
        return true;
    }

    return false;
}
?>
