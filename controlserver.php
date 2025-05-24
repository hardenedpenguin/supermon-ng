<?php
include("session.inc");

if (!isset($_SESSION['sm61loggedin']) || $_SESSION['sm61loggedin'] !== true) {
    die ("<br><h3>ERROR: You Must login to use these functions!</h3>");
}

include('amifunctions.inc');
include('user_files/global.inc');
include('common.inc');
include('authini.php');

$node = isset($_GET['node']) ? trim(strip_tags($_GET['node'])) : '';
$cmd  = isset($_GET['cmd']) ? trim(strip_tags($_GET['cmd'])) : '';

if (empty($node) || empty($cmd)) {
    die("Error: 'node' and 'cmd' parameters are required.");
}

$iniFilePath = get_ini_name($_SESSION['user']);

if (!file_exists($iniFilePath)) {
    die("Couldn't load INI file: " . htmlspecialchars($iniFilePath));
}

$config = parse_ini_file($iniFilePath, true);
if ($config === false) {
    die("Error parsing INI file: " . htmlspecialchars($iniFilePath));
}

if (!isset($config[$node])) {
    die("Node " . htmlspecialchars($node) . " is not in INI file: " . htmlspecialchars($iniFilePath));
}
$nodeConfig = $config[$node];

if (!isset($nodeConfig['host'], $nodeConfig['user'], $nodeConfig['passwd'])) {
    die("Node " . htmlspecialchars($node) . " configuration is incomplete (missing host, user, or passwd) in " . htmlspecialchars($iniFilePath) . ".");
}

$fp = SimpleAmiClient::connect($nodeConfig['host']);
if (!$fp) {
    die("Could not connect to host: " . htmlspecialchars($nodeConfig['host']));
}

if (!SimpleAmiClient::login($fp, $nodeConfig['user'], $nodeConfig['passwd'])) {
    SimpleAmiClient::logoff($fp);
    die("Could not login to AMI on " . htmlspecialchars($nodeConfig['host']) . " with user: " . htmlspecialchars($nodeConfig['user']));
}

$cmdString = str_replace("%node%", $node, $cmd);

$rptStatus = SimpleAmiClient::command($fp, $cmdString);

if ($rptStatus !== false) {
    print "<pre>\n===== " . htmlspecialchars($cmdString) . " =====\n";
    print htmlspecialchars($rptStatus);
    print "</pre>\n";
} else {
    SimpleAmiClient::logoff($fp);
    die("Failed to execute command '" . htmlspecialchars($cmdString) . "' or received an error from node " . htmlspecialchars($node) . ".");
}

SimpleAmiClient::logoff($fp);

?>