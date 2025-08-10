<?php
/**
 * Supermon-ng Favorites Authentication
 * 
 * Handles authentication, authorization, and validation for favorites operations.
 * Provides user access control, node validation, and favorites configuration management.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Validate user access to favorites function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateFavoritesAccess(): bool {
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
 * Load and validate favorites configuration
 * 
 * @param string $user User identifier
 * @param string $node Node number
 * @return array|false Configuration data or false on failure
 */
function loadFavoritesConfig(string $user, string $node): array|false {
    $FAVINI = get_fav_ini_name($user);

    if (!file_exists($FAVINI)) {
        return false;
    }

    $cpConfig = parse_ini_file($FAVINI, true);
    if ($cpConfig === false) {
        return false;
    }

    return [
        'FAVINI' => $FAVINI,
        'cpConfig' => $cpConfig
    ];
}

/**
 * Process favorites commands configuration
 * 
 * @param array $cpConfig Favorites configuration data
 * @param string $node Node number
 * @return array Processed commands configuration
 */
function processFavoritesCommands(array $cpConfig, string $node): array {
    $generalLabels = $cpConfig['general']['label'] ?? [];
    $generalCmds   = $cpConfig['general']['cmd'] ?? [];

    $nodeLabels = [];
    $nodeCmds   = [];

    if (isset($cpConfig[$node])) {
        $nodeLabels = $cpConfig[$node]['label'] ?? [];
        $nodeCmds   = $cpConfig[$node]['cmd'] ?? [];
    }

    return [
        'label' => array_merge($generalLabels, $nodeLabels),
        'cmd'   => array_merge($generalCmds, $nodeCmds)
    ];
}
