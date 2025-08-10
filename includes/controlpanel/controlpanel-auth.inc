<?php
/**
 * Supermon-ng Control Panel Authentication
 * 
 * Handles authentication, authorization, and validation for the control panel.
 * Provides user access control and node parameter validation.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Validate user access to control panel
 * 
 * @return bool True if user has access, false otherwise
 */
function validateControlPanelAccess(): bool {
    if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("CTRLUSER"))) {
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
    $node = @trim(strip_tags($_GET['node']));
    $localnode = @trim(strip_tags($_POST['localnode']));

    if ($localnode !== '') {
        $node = $localnode;
    }

    if (!is_numeric($node)) {
        return false;
    }

    return $node;
}

/**
 * Load user configuration files
 * 
 * @param string $user User identifier
 * @return array|false Configuration data or false on failure
 */
function loadUserConfig(string $user): array|false {
    $SUPINI = get_ini_name($user);

    if (!file_exists("$SUPINI")) {
        return false;
    }
    
    $allmonConfig = parse_ini_file("$SUPINI", true);
    
    global $USERFILES;
    if (!file_exists("$USERFILES/controlpanel.ini")) {
        return false;
    }
    
    $cpConfig = parse_ini_file("$USERFILES/controlpanel.ini", true);
    
    return [
        'allmon' => $allmonConfig,
        'controlpanel' => $cpConfig
    ];
}

/**
 * Process control panel commands configuration
 * 
 * @param array $cpConfig Control panel configuration
 * @param string $node Node number
 * @return array Processed commands configuration
 */
function processControlPanelCommands(array $cpConfig, string $node): array {
    $cpCommands = $cpConfig['general'];

    if (isset($cpConfig[$node])) {
        foreach ($cpConfig[$node] as $type => $arr) {
            if ($type == 'labels') {
                foreach ($arr as $label) {
                    $cpCommands['labels'][] = $label;
                }
            } elseif ($type == 'cmds') {
                foreach ($arr as $cmd) {
                    $cpCommands['cmds'][] = $cmd;
                }
            }
        }
    }

    return $cpCommands;
}
