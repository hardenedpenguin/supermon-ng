<?php
/**
 * Supermon-ng Connect Authentication
 * 
 * Handles authentication, authorization, and validation for connection operations.
 * Provides user access control, parameter validation, and AMI connection management.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Validate user access to connect function
 * 
 * @return bool True if user has access, false otherwise
 */
function validateConnectAccess(): bool {
    if ($_SESSION['sm61loggedin'] !== true) {
        return false;
    }
    return true;
}

/**
 * Get and validate POST parameters
 * 
 * @return array|false Array with validated parameters or false on failure
 */
function getConnectParameters(): array|false {
    $remotenode = @trim(strip_tags($_POST['remotenode']));
    $perm_input = @trim(strip_tags($_POST['perm']));
    $button = @trim(strip_tags($_POST['button']));
    $localnode = @trim(strip_tags($_POST['localnode']));

    if (!preg_match("/^\d+$/", $localnode)) {
        return false;
    }

    return [
        'remotenode' => $remotenode,
        'perm_input' => $perm_input,
        'button' => $button,
        'localnode' => $localnode
    ];
}

/**
 * Load and validate node configuration
 * 
 * @param string $localnode Local node number
 * @return array|false Node configuration or false on failure
 */
function loadConnectNodeConfig(string $localnode): array|false {
    $SUPINI = get_ini_name($_SESSION['user']);
    
    if (!file_exists($SUPINI)) {
        return false;
    }
    
    $config = parse_ini_file($SUPINI, true);
    
    if (!isset($config[$localnode])) {
        return false;
    }
    
    return $config[$localnode];
}

/**
 * Connect to AMI and authenticate
 * 
 * @param array $nodeConfig Node configuration array
 * @param string $localnode Local node number
 * @return \resource|false AMI connection resource or false on failure
 */
function connectToAmi(array $nodeConfig, string $localnode): \resource|false {
    $fp = SimpleAmiClient::connect($nodeConfig['host']);
    if (FALSE === $fp) {
        return false;
    }

    if (FALSE === SimpleAmiClient::login($fp, $nodeConfig['user'], $nodeConfig['passwd'])) {
        SimpleAmiClient::logoff($fp);
        return false;
    }

    return $fp;
}

/**
 * Get actions configuration
 * 
 * @return array Actions configuration array
 */
function getConnectActionsConfig(): array {
    return [
        'connect' => [
            'auth' => 'CONNECTUSER',
            'ilink_normal' => 3,
            'ilink_perm' => 13,
            'verb' => 'Connecting',
            'structure' => '%s %s to %s'
        ],
        'monitor' => [
            'auth' => 'MONUSER',
            'ilink_normal' => 2,
            'ilink_perm' => 12,
            'verb' => 'Monitoring',
            'structure' => '%s %s from %s'
        ],
        'localmonitor' => [
            'auth' => 'LMONUSER',
            'ilink_normal' => 8,
            'ilink_perm' => 18,
            'verb' => 'Local Monitoring',
            'structure' => '%s %s from %s'
        ],
        'disconnect' => [
            'auth' => 'DISCUSER',
            'ilink_normal' => 11,
            'ilink_perm' => 11,
            'verb' => 'Disconnect',
            'structure' => '%s %s from %s'
        ]
    ];
}

/**
 * Process action and determine ilink command
 * 
 * @param string $button Action button
 * @param string $perm_input Permanent input value
 * @param string $localnode Local node number
 * @param string $remotenode Remote node number
 * @return array|false Action result or false on failure
 */
function processConnectAction(string $button, string $perm_input, string $localnode, string $remotenode): array|false {
    $actions_config = getConnectActionsConfig();
    
    if (!isset($actions_config[$button])) {
        return false;
    }
    
    $action = $actions_config[$button];
    
    if (!get_user_auth($action['auth'])) {
        return false;
    }
    
    $is_permanent_action = ($perm_input == 'on' && get_user_auth("PERMUSER"));
    
    $ilink = $is_permanent_action ? $action['ilink_perm'] : $action['ilink_normal'];
    $verb_prefix = $is_permanent_action ? "Permanently " : "";
    $current_verb = $verb_prefix . $action['verb'];
    
    if ($button == 'connect') {
        $message = sprintf($action['structure'], $current_verb, $localnode, $remotenode);
    } else {
        $message = sprintf($action['structure'], $current_verb, $remotenode, $localnode);
    }
    
    return [
        'ilink' => $ilink,
        'message' => $message,
        'button' => $button
    ];
}

/**
 * Execute AMI command
 * 
 * @param \resource $fp AMI connection resource
 * @param int $ilink ILink command number
 * @param string $localnode Local node number
 * @param string $remotenode Remote node number
 * @param string $button Action button
 * @return string|false Command response or false on failure
 */
function executeConnectCommand($fp, int $ilink, string $localnode, string $remotenode, string $button): string|false {
    $command_to_send = "rpt cmd $localnode ilink $ilink";
    if (!empty($remotenode) || ($button == 'disconnect' && !empty($remotenode))) {
        $command_to_send .= " $remotenode";
    }
    
    return SimpleAmiClient::command($fp, trim($command_to_send));
}

/**
 * Get INI file path for error messages
 * 
 * @return string INI file path
 */
function getConnectIniPath(): string {
    return get_ini_name($_SESSION['user']);
}
