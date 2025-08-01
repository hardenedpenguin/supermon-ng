<?php

include('includes/session.inc');

if ($_SESSION['sm61loggedin'] !== true)  {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please login to use connect/disconnect functions.']);
    exit;
}

include('authusers.php');
include('includes/global.inc');
include('includes/amifunctions.inc');
include('includes/common.inc');
include('authini.php');

$remotenode = @trim(strip_tags($_POST['remotenode']));
$perm_input = @trim(strip_tags($_POST['perm']));
$button = @trim(strip_tags($_POST['button']));
$localnode = @trim(strip_tags($_POST['localnode']));

if (!preg_match("/^\d+$/", $localnode)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please provide a valid local node number.']);
    exit;
}

$SUPINI = get_ini_name($_SESSION['user']);
if (!file_exists($SUPINI)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "Couldn't load $SUPINI file."]);
    exit;
}
$config = parse_ini_file($SUPINI, true);

if (!isset($config[$localnode])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "Configuration for local node $localnode not found in $SUPINI."]);
    exit;
}

$fp = SimpleAmiClient::connect($config[$localnode]['host']);
if (FALSE === $fp) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "Could not connect to Asterisk Manager on host specified for node $localnode."]);
    exit;
}

if (FALSE === SimpleAmiClient::login($fp, $config[$localnode]['user'], $config[$localnode]['passwd'])) {
    SimpleAmiClient::logoff($fp);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "Could not login to Asterisk Manager for node $localnode."]);
    exit;
}

$actions_config = [
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

$ilink = null;
$message = '';

if (isset($actions_config[$button])) {
    $action = $actions_config[$button];

    if (get_user_auth($action['auth'])) {
        $is_permanent_action = ($perm_input == 'on' && get_user_auth("PERMUSER"));

        $ilink = $is_permanent_action ? $action['ilink_perm'] : $action['ilink_normal'];
        $verb_prefix = $is_permanent_action ? "Permanently " : "";
        $current_verb = $verb_prefix . $action['verb'];

        if ($button == 'connect') {
            $message = sprintf($action['structure'], $current_verb, $localnode, $remotenode);
        } else {
            $message = sprintf($action['structure'], $current_verb, $remotenode, $localnode);
        }
        
        // Success response
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $message]);

    } else {
        SimpleAmiClient::logoff($fp);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => "You are not authorized to perform the '$button' action."]);
        exit;
    }
} else {
    SimpleAmiClient::logoff($fp);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "Invalid action specified: '$button'."]);
    exit;
}

if ($ilink !== null) {
    $command_to_send = "rpt cmd $localnode ilink $ilink";
    if (!empty($remotenode) || ($button == 'disconnect' && !empty($remotenode)) ) {
        $command_to_send .= " $remotenode";
    }
    
    $AMI_response = SimpleAmiClient::command($fp, trim($command_to_send));
    
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: Action determined but ilink command number not set. No command sent.']);
    exit;
}

SimpleAmiClient::logoff($fp);

?>