<?php

include("includes/session.inc");
include("includes/amifunctions.inc");
include("user_files/global.inc");
include("includes/common.inc");
include("authusers.php");
include("authini.php");

if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("DBTUSER"))) {
    die ("<br><h3 class='error-message'>ERROR: You Must login to use the 'Database' function!</h3>");
}

$localnode = trim(strip_tags($_GET['localnode'] ?? ''));

$supIniFile = get_ini_name($_SESSION['user']);

if (!file_exists($supIniFile)) {
    die("<h3 class='error-message'>ERROR: Configuration file '" . htmlspecialchars($supIniFile) . "' could not be loaded.</h3>");
}

$config = parse_ini_file($supIniFile, true);

if (empty($localnode)) {
    die("<h3 class='error-message'>ERROR: 'localnode' parameter is required.</h3>");
}

if (!isset($config[$localnode])) {
    die("<h3 class='error-message'>ERROR: Node '" . htmlspecialchars($localnode) . "' is not defined in '" . htmlspecialchars($supIniFile) . "'.</h3>");
}

$amiConfig = $config[$localnode];

if (!isset($amiConfig['host']) || !isset($amiConfig['user']) || !isset($amiConfig['passwd'])) {
    die("<h3 class='error-message'>ERROR: AMI configuration for node '" . htmlspecialchars($localnode) . "' is incomplete (missing host, user, or passwd).</h3>");
}

$fp = SimpleAmiClient::connect($amiConfig['host']);
if ($fp === false) {
    die("<h3 class='error-message'>ERROR: Could not connect to Asterisk Manager on host '" . htmlspecialchars($amiConfig['host']) . "'.</h3>");
}

if (SimpleAmiClient::login($fp, $amiConfig['user'], $amiConfig['passwd']) === false) {
    SimpleAmiClient::logoff($fp);
    die("<h3 class='error-message'>ERROR: Could not login to Asterisk Manager on host '" . htmlspecialchars($amiConfig['host']) . "' with user '" . htmlspecialchars($amiConfig['user']) . "'.</h3>");
}

$databaseOutput = SimpleAmiClient::command($fp, "database show");

SimpleAmiClient::logoff($fp);

$processedOutput = ($databaseOutput === false) ? "" : trim($databaseOutput);
$dbEntries = [];

if (!empty($processedOutput)) {
    $processedOutput = preg_replace('/^Output: /m', '', $processedOutput);
    $lines = explode("\n", trim($processedOutput));

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $dbEntries[] = [
                'key' => trim($parts[0]),
                'value' => trim($parts[1])
            ];
        }
    }
}

?>
<html>
<head>
<title>AllStar Node Database Contents - <?php echo htmlspecialchars($localnode); ?></title>
<link rel="stylesheet" type="text/css" href="supermon-ng.css">
<style>
.db-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    font-family: monospace;
    font-size: 0.9em;
}
.db-table th, .db-table td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
    vertical-align: top;
}
.db-table th {
    background-color: #f2f2f2;
    font-weight: bold;
    color: #333;
}
.db-table tbody tr {
    background-color: #39FF14;
    color: #000000;
}
.db-table tbody tr:hover {
    background-color: #00C000;
    color: #FFFFFF;
}
.db-table td.db-key {
}
.db-table td.db-value {
    word-break: break-word;
}
</style>
</head>
<body>

<div class="container">
    <?php
    $today = date("D M j G:i:s T Y");
    ?>
    <div class="db-header">
        <b><u><?php echo htmlspecialchars($today); ?> - Database from node - <?php echo htmlspecialchars($localnode); ?></u></b>
    </div>

    <?php
    if ($databaseOutput === false) {
        echo "<p class='status-message error-message'>ERROR: Could not retrieve database content from AMI.</p>";
    } elseif (empty($dbEntries)) {
        if (empty(trim($processedOutput))) {
            echo "<p class='status-message'>--- NO DATABASE CONTENT RETURNED (or output was empty after cleaning) ---</p>";
        } else {
            echo "<p class='status-message'>--- NO KEY-VALUE PAIRS FOUND IN DATABASE OUTPUT ---</p>";
        }
    } else {
        echo "<table class='db-table'>";
        echo "<thead><tr><th>Key</th><th>Value</th></tr></thead>";
        echo "<tbody>";
        foreach ($dbEntries as $entry) {
            echo "<tr>";
            echo "<td class='db-key'>" . htmlspecialchars($entry['key']) . "</td>";
            echo "<td class='db-value'>" . htmlspecialchars($entry['value']) . "</td>";
            echo "</tr>";
        }
        echo "</tbody>";
        echo "</table>";
    }
    ?>
</div>

</body>
</html>