<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include("session.inc");
include('amifunctions.inc');
include("authusers.php");
include("authini.php");

if (!isset($_SESSION['sm61loggedin']) || $_SESSION['sm61loggedin'] !== true || !get_user_auth("FSTRESUSER")) {
    header('Content-Type: text/html');
    print "<br><h3>ERROR: You must login and be authorized to use the 'RESTART' function!</h3>";
    exit;
}

if (!isset($_POST['localnode']) || empty(trim($_POST['localnode']))) {
    header('Content-Type: text/html');
    die("<b>Error: 'localnode' parameter is missing or empty.</b>");
}
$localnode = trim(strip_tags($_POST['localnode']));

$iniFilePath = get_ini_name($_SESSION['user']);

if (!file_exists($iniFilePath)) {
    header('Content-Type: text/html');
    die("<b>Error: Configuration file '" . htmlspecialchars($iniFilePath) . "' not found.</b>");
}

$config = parse_ini_file($iniFilePath, true);
if ($config === false) {
    header('Content-Type: text/html');
    die("<b>Error: Could not parse configuration file '" . htmlspecialchars($iniFilePath) . "'. Check its syntax.</b>");
}

if (!isset($config[$localnode])) {
    header('Content-Type: text/html');
    die("<b>Error: Node '" . htmlspecialchars($localnode) . "' is not defined in '" . htmlspecialchars($iniFilePath) . "'.</b>");
}

$nodeConfig = $config[$localnode];

$requiredKeys = ['host', 'user', 'passwd'];
foreach ($requiredKeys as $key) {
    if (!isset($nodeConfig[$key]) || $nodeConfig[$key] === '') {
        header('Content-Type: text/html');
        die("<b>Error: Missing or empty configuration key '{$key}' for node '" . htmlspecialchars($localnode) . "' in '" . htmlspecialchars($iniFilePath) . "'.</b>");
    }
}

$fp = null;

try {
    $fp = SimpleAmiClient::connect($nodeConfig['host']);
    if ($fp === false) {
        die("<b>Error: Could not connect to Asterisk Manager on host '" . htmlspecialchars($nodeConfig['host']) . "'. Check AMI settings and Asterisk status.</b>");
    }

    if (SimpleAmiClient::login($fp, $nodeConfig['user'], $nodeConfig['passwd']) === false) {
        die("<b>Error: Could not login to Asterisk Manager for node '" . htmlspecialchars($localnode) . "'. Check AMI credentials.</b>");
    }

    $amiResponse = SimpleAmiClient::command($fp, "restart now");

    if ($amiResponse === false) {
        die("<b>Error: Failed to send the restart command to Asterisk for node '" . htmlspecialchars($localnode) . "'. Asterisk might not have received the command or the AMI action failed.</b>");
    }

    header('Content-Type: text/html');
    print "<b>Fast Restarting Asterisk Now at '" . htmlspecialchars($localnode) . "'. Check Asterisk logs for confirmation.</b>";

} catch (Exception $e) {
    header('Content-Type: text/html');
    die("<b>An unexpected error occurred: " . htmlspecialchars($e->getMessage()) . "</b>");
} finally {
    if ($fp && is_resource($fp)) {
        SimpleAmiClient::logoff($fp);
    }
}

?>