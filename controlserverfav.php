<?php
declare(strict_types=1);

include('session.inc');

if (!isset($_SESSION['sm61loggedin']) || $_SESSION['sm61loggedin'] !== true) {
    die("<br><h3>ERROR: You must login to use these functions!</h3>");
}

include('amifunctions.inc');
include('common.inc');
include('authini.php');

$node = trim(strip_tags(filter_input(INPUT_GET, 'node', FILTER_SANITIZE_STRING) ?? ''));
$cmd  = trim(strip_tags(filter_input(INPUT_GET, 'cmd', FILTER_SANITIZE_STRING) ?? ''));

if (empty($node) || empty($cmd)) {
    die("<br><h3>ERROR: 'node' and 'cmd' parameters are required.</h3>");
}

$supIniFile = get_ini_name($_SESSION['user']);
if (!file_exists($supIniFile)) {
    die("ERROR: Configuration file '$supIniFile' could not be found.");
}

$config = parse_ini_file($supIniFile, true);
if ($config === false) {
    die("ERROR: Could not parse configuration file '$supIniFile'.");
}

if (!isset($config[$node])) {
    die("ERROR: Node '$node' is not defined in '$supIniFile'.");
}

$nodeConfig = $config[$node];

$fp = null;
try {
    $fp = SimpleAmiClient::connect($nodeConfig['host']);
    if ($fp === false) {
        throw new RuntimeException("Could not connect to AMI host '{$nodeConfig['host']}' for node '$node'.");
    }

    if (SimpleAmiClient::login($fp, $nodeConfig['user'], $nodeConfig['passwd']) === false) {
        throw new RuntimeException("Could not login to AMI on '{$nodeConfig['host']}' for node '$node' with user '{$nodeConfig['user']}'.");
    }

    $cmdString = str_replace("%node%", $node, $cmd);

    $commandOutput = SimpleAmiClient::command($fp, $cmdString);

    if ($commandOutput === false) {
        throw new RuntimeException("Failed to execute command '$cmdString' or received an error response from node '$node'.");
    }

    print "<pre>\n===== " . htmlspecialchars($cmdString, ENT_QUOTES, 'UTF-8') . " =====\n";
    print htmlspecialchars($commandOutput, ENT_QUOTES, 'UTF-8');
    print "\n===== end =====\n</pre>\n";

} catch (RuntimeException $e) {
    die("<br><h3>ERROR: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</h3>");
} finally {
    if (isset($fp) && is_resource($fp)) {
        SimpleAmiClient::logoff($fp);
    }
}

?>