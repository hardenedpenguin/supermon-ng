<?php

include('session.inc');

if ($_SESSION['sm61loggedin'] !== true)  {
    die("Please login to use connect/disconnect functions.\n");
}

include('authusers.php');
include('user_files/global.inc');
include('amifunctions.inc');
include('common.inc');
include('authini.php');

$remotenode = @trim(strip_tags($_POST['remotenode']));
$perm_input = @trim(strip_tags($_POST['perm']));
$button = @trim(strip_tags($_POST['button']));
$localnode = @trim(strip_tags($_POST['localnode']));

if (!preg_match("/^\d+$/", $localnode)) {
    die("Please provide a valid local node number.\n");
}

$SUPINI = get_ini_name($_SESSION['user']);
if (!file_exists($SUPINI)) {
    die("Couldn't load $SUPINI file.\n");
}
$config = parse_ini_file($SUPINI, true);

if (!isset($config[$localnode])) {
    die("Configuration for local node $localnode not found in $SUPINI.\n");
}

$fp = SimpleAmiClient::connect($config[$localnode]['host']);
if (FALSE === $fp) {
    die("Could not connect to Asterisk Manager on host specified for node $localnode.\n");
}

if (FALSE === SimpleAmiClient::login($fp, $config[$localnode]['user'], $config[$localnode]['passwd'])) {
    SimpleAmiClient::logoff($fp);
    die("Could not login to Asterisk Manager for node $localnode.\n");
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
        
        print "<b>$message</b>\n";

    } else {
        SimpleAmiClient::logoff($fp);
        die("You are not authorized to perform the '$button' action.\n");
    }
} else {
    SimpleAmiClient::logoff($fp);
    die("Invalid action specified: '$button'.\n");
}

if ($ilink !== null) {
    $command_to_send = "rpt cmd $localnode ilink $ilink";
    if (!empty($remotenode) || ($button == 'disconnect' && !empty($remotenode)) ) {
        $command_to_send .= " $remotenode";
    }
    
    $AMI_response = SimpleAmiClient::command($fp, trim($command_to_send));
    
} else {
    print "Error: Action determined but ilink command number not set. No command sent.\n";
}

SimpleAmiClient::logoff($fp);

?>