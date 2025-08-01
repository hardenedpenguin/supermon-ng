<?php
include("includes/session.inc");
include('includes/amifunctions.inc');
include('user_files/global.inc');
include('authusers.php');
include('includes/common.inc');
include('authini.php');
include('includes/csrf.inc');

if (!isset($_SESSION['sm61loggedin']) || $_SESSION['sm61loggedin'] !== true) {
    die("<br><h3>ERROR: You Must login to use this function!</h3>");
}
if (!function_exists('get_user_auth') || !get_user_auth("DTMFUSER")) {
    die("<br><h3>ERROR: You are not authorized to use the 'DTMF' function!</h3>");
}

// Validate CSRF token
require_csrf();

$dtmf = trim(strip_tags($_POST['node'] ?? ''));
$localnode = trim(strip_tags($_POST['localnode'] ?? ''));

if (empty($dtmf)) {
    die("Please provide a DTMF command.\n");
}
if (empty($localnode)) {
    die("Please provide a local node identifier.\n");
}

if (!isset($_SESSION['user']) || !function_exists('get_ini_name')) {
    die("User session or INI configuration function (get_ini_name) not available.\n");
}
$iniFilePath = get_ini_name($_SESSION['user']);

if (!file_exists($iniFilePath)) {
    die("Couldn't load INI file: " . htmlspecialchars($iniFilePath) . "\n");
}

$config = parse_ini_file($iniFilePath, true);
if ($config === false) {
    die("Error parsing INI file: " . htmlspecialchars($iniFilePath) . "\n");
}

if (!isset($config[$localnode])) {
    die("Node " . htmlspecialchars($localnode) . " is not in " . htmlspecialchars($iniFilePath) . " file.\n");
}

$nodeConfig = $config[$localnode];

if (!isset($nodeConfig['host']) || !isset($nodeConfig['user']) || !isset($nodeConfig['passwd'])) {
    die("Incomplete configuration for node " . htmlspecialchars($localnode) . " in INI file (missing host, user, or passwd).\n");
}

if (!class_exists('SimpleAmiClient')) {
    die("AMI Client class (SimpleAmiClient) not found. Check amifunctions.inc.\n");
}

$amiSocket = null;

try {
    $amiSocket = SimpleAmiClient::connect($nodeConfig['host']);
    if ($amiSocket === false) {
        die("Could not connect to Asterisk Manager at host: " . htmlspecialchars($nodeConfig['host']) . "\n");
    }

    if (SimpleAmiClient::login($amiSocket, $nodeConfig['user'], $nodeConfig['passwd']) === false) {
        die("Could not login to Asterisk Manager for user: " . htmlspecialchars($nodeConfig['user']) . "\n");
    }

    $asteriskCommand = "rpt fun " . $localnode . " " . $dtmf;
    $commandOutput = SimpleAmiClient::command($amiSocket, $asteriskCommand);

    if ($commandOutput === false) {
        die("Error executing DTMF command '" . htmlspecialchars($dtmf) . "' on node " . htmlspecialchars($localnode) . ". Asterisk reported an error or timed out.\n");
    }

    // Check if the command was successful
    if (empty(trim($commandOutput))) {
        print "<b>DTMF command '" . htmlspecialchars($dtmf) . "' executed successfully on node " . htmlspecialchars($localnode) . "</b>";
    } else {
        print "<b>DTMF command '" . htmlspecialchars($dtmf) . "' executed on node " . htmlspecialchars($localnode) . "</b><br>";
        print "<pre>" . htmlspecialchars($commandOutput) . "</pre>";
    }

} catch (Exception $e) {
    die("An unexpected error occurred during AMI communication: " . htmlspecialchars($e->getMessage()) . "\n");
} finally {
    if (is_resource($amiSocket)) {
        SimpleAmiClient::logoff($amiSocket);
    }
}

?>