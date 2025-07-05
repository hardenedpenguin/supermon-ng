<?php

include('session.inc');
include('csrf.inc');

if ($_SESSION['sm61loggedin'] !== true)  {
    die("<h3 class='error-message'>ERROR: Please login to use connect/disconnect functions.</h3>");
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
    die("<h3 class='error-message'>ERROR: Please provide a valid local node number.</h3>");
}

if (!preg_match("/^\d+$/", $remotenode)) {
    die("<h3 class='error-message'>ERROR: Please provide a valid remote node number.</h3>");
}

// Validate perm input - can be empty, 'perm', or 'temp'
if (!empty($perm_input) && !in_array($perm_input, ['perm', 'temp'])) {
    die("<h3 class='error-message'>ERROR: Invalid permission type.</h3>");
}

if (!in_array($button, ['connect', 'monitor', 'permanent', 'localmonitor', 'disconnect'])) {
    die("<h3 class='error-message'>ERROR: Invalid button action.</h3>");
}

$SUPINI = get_ini_name($_SESSION['user']);
if (!file_exists($SUPINI)) {
    die("<h3 class='error-message'>ERROR: Couldn't load $SUPINI file.</h3>");
}
$config = parse_ini_file($SUPINI, true);

if (!isset($config[$localnode])) {
    die("<h3 class='error-message'>ERROR: Configuration for local node $localnode not found in $SUPINI.</h3>");
}

$fp = SimpleAmiClient::connect($config[$localnode]['host']);
if (FALSE === $fp) {
    die("<h3 class='error-message'>ERROR: Could not connect to Asterisk Manager on host specified for node $localnode.</h3>");
}

if (FALSE === SimpleAmiClient::login($fp, $config[$localnode]['user'], $config[$localnode]['passwd'])) {
    SimpleAmiClient::logoff($fp);
    die("<h3 class='error-message'>ERROR: Could not login to Asterisk Manager for node $localnode.</h3>");
}

// Determine if this is a permanent connection based on perm parameter
$is_permanent = ($perm_input === 'perm');

$cmd = "";
switch ($button) {
    case "connect":
        $cmd = $is_permanent ? "ilink $localnode $remotenode 13" : "ilink $localnode $remotenode 3";
        break;
    case "monitor":
        $cmd = $is_permanent ? "ilink $localnode $remotenode 12" : "ilink $localnode $remotenode 2";
        break;
    case "permanent":
        $cmd = "ilink $localnode $remotenode 13"; // Always permanent
        break;
    case "localmonitor":
        $cmd = $is_permanent ? "ilink $localnode $remotenode 18" : "ilink $localnode $remotenode 8";
        break;
    case "disconnect":
        $cmd = "ilink $localnode $remotenode 11";
        break;
    default:
        die("<h3 class='error-message'>ERROR: Invalid button action.</h3>");
}

$result = SimpleAmiClient::command($fp, $cmd);

// Log the command and result for debugging
error_log("Supermon-ng: Button=$button, LocalNode=$localnode, RemoteNode=$remotenode, Command=$cmd, Result=" . ($result ?: 'FALSE'));

if ($result === FALSE) {
    SimpleAmiClient::logoff($fp);
    die("<h3 class='error-message'>ERROR: Failed to execute command.</h3>");
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
    $action = strtoupper($button);
    
    $wrtStr = sprintf(
        "Supermon-ng <b>%s</b> Host-%s <b>user-%s</b> at %s from IP-%s - LocalNode-%s RemoteNode-%s Perm-%s\n",
        $action,
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
    'message' => "Command executed successfully: " . htmlspecialchars($cmd, ENT_QUOTES, 'UTF-8'),
    'result' => htmlspecialchars($result, ENT_QUOTES, 'UTF-8')
]);
?>