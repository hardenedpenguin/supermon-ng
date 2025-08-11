<?php
/**
 * Supermon-ng Delete Favorite Authentication
 * 
 * Handles authentication, authorization, and validation for delete favorite operations.
 * Provides user access control and favorites file management.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Validate user access to delete favorite function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateDeleteFavoriteAccess(): bool {
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
 * Get user's favorites file path
 * 
 * @param string $user User identifier
 * @return string Path to user's favorites file
 */
function getUserFavoritesFile(string $user): string {
    return get_fav_ini_name($user);
}

/**
 * Load user's favorites configuration
 * 
 * @param string $user User identifier
 * @return array|false Favorites configuration or false on failure
 */
function loadUserFavorites(string $user): array|false {
    $favoritesFile = getUserFavoritesFile($user);
    
    if (!file_exists($favoritesFile)) {
        return false;
    }

    $config = parse_ini_file($favoritesFile, true);
    if ($config === false) {
        return false;
    }

    return $config;
}

/**
 * Get all favorites for a user
 * 
 * @param string $user User identifier
 * @return array Array of favorites with section, index, label, and command
 */
function getAllUserFavorites($user) {
    $config = loadUserFavorites($user);
    $favorites = array();
    
    if ($config === false) {
        return $favorites;
    }

    // Process general section
    if (isset($config['general']['label']) && isset($config['general']['cmd'])) {
        $labels = $config['general']['label'];
        $cmds = $config['general']['cmd'];
        
        for ($i = 0; $i < count($labels); $i++) {
            array_push($favorites, array(
                'section' => 'general',
                'index' => $i,
                'label' => $labels[$i],
                'command' => isset($cmds[$i]) ? $cmds[$i] : '',
                'node' => extractNodeFromCommand(isset($cmds[$i]) ? $cmds[$i] : '')
            ));
        }
    }

    // Process node-specific sections
    foreach ($config as $section => $sectionData) {
        if ($section === 'general') {
            continue;
        }
        
        if (isset($sectionData['label']) && isset($sectionData['cmd'])) {
            $labels = $sectionData['label'];
            $cmds = $sectionData['cmd'];
            
            for ($i = 0; $i < count($labels); $i++) {
                array_push($favorites, array(
                    'section' => $section,
                    'index' => $i,
                    'label' => $labels[$i],
                    'command' => isset($cmds[$i]) ? $cmds[$i] : '',
                    'node' => extractNodeFromCommand(isset($cmds[$i]) ? $cmds[$i] : '')
                ));
            }
        }
    }

    return $favorites;
}

/**
 * Extract node number from command string
 * 
 * @param string $command Command string
 * @return string|false Node number or false if not found
 */
function extractNodeFromCommand(string $command): string|false {
    // Look for patterns like "ilink 13 12345" or "ilink 3 12345"
    if (preg_match('/ilink\s+\d+\s+(\d+)/', $command, $matches)) {
        return $matches[1];
    }
    
    return false;
}

/**
 * Delete a specific favorite entry
 * 
 * @param string $user User identifier
 * @param string $section Section name (general or node number)
 * @param int $index Index of the favorite to delete
 * @return bool True on success, false on failure
 */
function deleteFavoriteEntry(string $user, string $section, int $index): bool {
    $favoritesFile = getUserFavoritesFile($user);
    $config = loadUserFavorites($user);
    
    if ($config === false) {
        return false;
    }

    // Check if section and index exist
    if (!isset($config[$section]['label'][$index])) {
        return false;
    }

    // Remove the entry
    unset($config[$section]['label'][$index]);
    unset($config[$section]['cmd'][$index]);

    // Reindex arrays
    $config[$section]['label'] = array_values($config[$section]['label']);
    $config[$section]['cmd'] = array_values($config[$section]['cmd']);

    // Remove empty sections
    if (empty($config[$section]['label']) && $section !== 'general') {
        unset($config[$section]);
    }

    // Write back to file
    return writeIniFile($favoritesFile, $config);
}

/**
 * Write INI configuration to file
 * 
 * @param string $filename File path
 * @param array $config Configuration array
 * @return bool True on success, false on failure
 */
function writeIniFile($filename, $config) {
    $content = "";
    
    foreach ($config as $section => $values) {
        $content .= "[$section]\n";
        
        if (is_array($values)) {
            foreach ($values as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $item) {
                        $content .= "$key[] = \"$item\"\n";
                    }
                } else {
                    $content .= "$key = \"$value\"\n";
                }
            }
        }
        
        $content .= "\n";
    }
    
    return file_put_contents($filename, $content) !== false;
}
?>
