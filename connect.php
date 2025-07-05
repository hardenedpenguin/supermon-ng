<?php

include('session.inc');
include('csrf.inc');

if ($_SESSION['sm61loggedin'] !== true)  {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please login to use connect/disconnect functions.']);
    exit;
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
}

include('authusers.php');
include('user_files/global.inc');
include('amifunctions.inc');
include('common.inc');
include('authini.php');

$remotenode = trim(strip_tags($_POST['remotenode'] ?? ''));
$perm_input = trim(strip_tags($_POST['perm'] ?? ''));
$button = trim(strip_tags($_POST['button'] ?? ''));
$localnode = trim(strip_tags($_POST['localnode'] ?? ''));

// Validate inputs
if (!preg_match("/^\d+$/", $localnode)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please provide a valid local node number.']);
    exit;
}

if (!preg_match("/^\d+$/", $remotenode)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please provide a valid remote node number.']);
    exit;
}

// Validate perm input - can be empty, 'perm', 'temp', or 'on'
if (!empty($perm_input) && !in_array($perm_input, ['perm', 'temp', 'on'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid permission type.']);
    exit;
}

// Define actions configuration
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
    'permanent' => [
        'auth' => 'CONNECTUSER',
        'ilink_normal' => 13,
        'ilink_perm' => 13,
        'verb' => 'Permanently Connecting',
        'structure' => '%s %s to %s'
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

$ilink = null;
$message = '';

if (isset($actions_config[$button])) {
    $action = $actions_config[$button];

    if (get_user_auth($action['auth'])) {
        $is_permanent_action = ($perm_input == 'on' && get_user_auth("PERMUSER"));

        $ilink = $is_permanent_action ? $action['ilink_perm'] : $action['ilink_normal'];
        $verb_prefix = $is_permanent_action ? "Permanently " : "";
        $current_verb = $verb_prefix . $action['verb'];

        if ($button == 'connect' || $button == 'permanent') {
            $message = sprintf($action['structure'], $current_verb, $localnode, $remotenode);
        } else {
            $message = sprintf($action['structure'], $current_verb, $remotenode, $localnode);
        }

        // Build the AMI command
        if ($button == 'disconnect') {
            $cmd = "rpt fun $localnode *0$remotenode";
        } else {
            $cmd = "rpt fun $localnode *$ilink$remotenode";
        }

        // Debug logging
        error_log("Supermon-ng Debug: Button=$button, LocalNode=$localnode, RemoteNode=$remotenode, ILink=$ilink, Command=$cmd");

        $result = SimpleAmiClient::command($fp, $cmd);

        // Log the full result for debugging
        error_log("Supermon-ng Debug: Result=" . ($result ? $result : 'FALSE'));
        error_log("Supermon-ng Debug: Full command sent: $cmd");
        error_log("Supermon-ng Debug: Button type: $button, ILink number: $ilink");

        if ($result === FALSE) {
            SimpleAmiClient::logoff($fp);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to execute command.']);
            exit;
        }

        SimpleAmiClient::logoff($fp);

        // Log the action
        if (isset($SMLOG) && $SMLOG === "yes" && isset($SMLOGNAME)) {
            $hostname = gethostname();
            if ($hostname === false) {
                $hostname = 'unknown_host';
            } else {
                $hostnameParts = explode('.', $hostname);
                $hostname = $hostnameParts[0];
            }
            
            try {
                $dateTime = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
                $myday = $dateTime->format('l, F j, Y T - H:i:s');
            } catch (Exception $e) {
                $myday = 'N/A_DATE';
            }

            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown_ip';
            $action_name = strtoupper($button);
            
            $wrtStr = sprintf(
                "Supermon-ng <b>%s</b> Host-%s <b>user-%s</b> at %s from IP-%s - LocalNode-%s RemoteNode-%s Perm-%s\n",
                $action_name,
                htmlspecialchars($hostname, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($_SESSION['user'] ?? 'unknown', ENT_QUOTES, 'UTF-8'),
                $myday,
                htmlspecialchars($ip, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($localnode, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($remotenode, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($perm_input, ENT_QUOTES, 'UTF-8')
            );

            if (file_put_contents($SMLOGNAME, $wrtStr, FILE_APPEND | LOCK_EX) === false) {
                error_log("Failed to write to SMLOGNAME: {$SMLOGNAME}");
            }
        }

        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'result' => htmlspecialchars($result, ENT_QUOTES, 'UTF-8')
        ]);

    } else {
        SimpleAmiClient::logoff($fp);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => "You are not authorized to perform the '$button' action."]);
    }
} else {
    SimpleAmiClient::logoff($fp);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "Invalid action specified: '$button'."]);
}
?>