<?php

include("session.inc");
include("amifunctions.inc");
include("user_files/global.inc");
include("common.inc");
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

if (!empty($processedOutput)) {
    $processedOutput = preg_replace('/^Output:.*\R?/m', '', $processedOutput);
    $processedOutput = trim($processedOutput);
}

?>
<html>
<head>
<title>AllStar Node Database Contents - <?php echo htmlspecialchars($localnode); ?></title>
<link rel="stylesheet" type="text/css" href="supermon-ng.css">
</head>
<body>

<div class="container">
    <?php
    $today = exec(escapeshellcmd("date"));
    ?>
    <div class="db-header">
        <b><u><?php echo htmlspecialchars($today); ?> - Database from node - <?php echo htmlspecialchars($localnode); ?></u></b>
    </div>

    <?php
    if ($databaseOutput === false) {
        echo "<p class='status-message'>ERROR: Could not retrieve database content from AMI.</p>";
    } elseif (empty($processedOutput)) {
        echo "<p class='status-message'>--- NO DATABASE CONTENT RETURNED (or output was empty after cleaning) ---</p>";
    } else {
        echo "<div class='db-content'>" . nl2br(htmlspecialchars($processedOutput)) . "</div>";
    }
    ?>
</div>

</body>
</html>