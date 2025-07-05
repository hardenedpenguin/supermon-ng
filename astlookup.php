<?php

include("session.inc");
include('amifunctions.inc');
include("common.inc");
include("authusers.php");
include("authini.php");
include("csrf.inc");
include("nodeinfo.inc");

if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("ASTLKUSER")))  {
    die ("<br><h3 class='error-message'>ERROR: You Must login to use the 'Lookup' function!</h3>");
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
}

$lookupNode = trim(strip_tags($_GET['node'] ?? ''));
$localnode = trim(strip_tags($_GET['localnode'] ?? ''));
$perm = trim(strip_tags($_GET['perm'] ?? ''));

// Validate inputs
if (!preg_match('/^\d+$/', $localnode)) {
    die("<h3 class='error-message'>ERROR: Invalid local node parameter.</h3>");
}

if (empty($lookupNode)) {
    die("<h3 class='error-message'>ERROR: Please provide a node number or callsign to lookup.</h3>");
}

$SUPINI = get_ini_name($_SESSION['user']);

if (!file_exists($SUPINI)) {
    die("<h3 class='error-message'>ERROR: Couldn't load $SUPINI file.</h3>");
}

$config = parse_ini_file($SUPINI, true);

if (empty($localnode) || !isset($config[$localnode])) {
    die("<h3 class='error-message'>ERROR: Node $localnode is not in $SUPINI file or not specified.</h3>");
}

// Load the AllStar database
$db = $ASTDB_TXT;
$astdb = array();
if (file_exists($db)) {
    $fh = fopen($db, "r");
    if ($fh && flock($fh, LOCK_SH)) {
        while (($line = fgets($fh)) !== FALSE) {
            $arr_db = explode('|', trim($line));
            if (isset($arr_db[0])) {
                 $astdb[$arr_db[0]] = $arr_db;
            }
        }
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

if (($fp = SimpleAmiClient::connect($config[$localnode]['host'])) === FALSE) {
    die("<h3 class='error-message'>ERROR: Could not connect to Asterisk Manager.</h3>");
}

if (SimpleAmiClient::login($fp, $config[$localnode]['user'], $config[$localnode]['passwd']) === FALSE) {
    SimpleAmiClient::logoff($fp);
    die("<h3 class='error-message'>ERROR: Could not login to Asterisk Manager.</h3>");
}

function sendCmdToAMI($fp, $cmd)
{
    return SimpleAmiClient::command($fp, $cmd);
}

function getDataFromAMI($fp, $cmd)
{
    return SimpleAmiClient::command($fp, $cmd);
}

?>
<html>
<head>
<link type="text/css" rel="stylesheet" href="supermon-ng.css">
<title>AllStar Lookup - <?php echo htmlspecialchars($localnode); ?></title>
</head>
<body class="lookup-page">

<p class="lookup-title"><b>AllStar Node Lookup at node <?php echo htmlspecialchars($localnode); ?></b></p>

<center>
<form action="astlookup.php?node=<?php echo htmlspecialchars($lookupNode); ?>&localnode=<?php echo htmlspecialchars($localnode); ?>&perm=<?php echo htmlspecialchars($perm); ?>" method="post">
    <?php if (function_exists('csrf_token_field')) echo csrf_token_field(); ?>
    <table class="lookup-table">
        <tr>
            <td class="lookup-cell">
                <b>Node/Callsign to Lookup:</b><br>
                <input type="text" name="lookup_node" value="<?php echo htmlspecialchars($lookupNode); ?>" maxlength="20" size="15" required>
            </td>
        </tr>
        <tr>
            <td class="lookup-cell-center">
                <input type="submit" value="Lookup" class="lookup-button">
            </td>
        </tr>
    </table>
</form>
</center>

<?php
if (!empty($_POST["lookup_node"])) {
    $nodeToLookup = trim(strip_tags($_POST["lookup_node"]));
    
    if (!empty($nodeToLookup)) {
        echo "<div class='lookup-results'>";
        echo "<h3>Lookup Results for: " . htmlspecialchars($nodeToLookup) . "</h3>";
        
        // Use the proper AllStar lookup function
        $lookupResult = getAstInfo($fp, $nodeToLookup);
        
        echo "<div class='lookup-command'>";
        echo "<b>Lookup Result:</b><br>";
        echo "<pre>" . htmlspecialchars($lookupResult) . "</pre>";
        echo "</div>";
        
        if ($lookupResult !== 'Node not in local database' && 
            $lookupResult !== 'No info' && 
            strpos($lookupResult, 'No info') === false) {
            $foundResults = true;
        }
        
        if (!$foundResults) {
            echo "<p class='lookup-no-results'>No results found for " . htmlspecialchars($nodeToLookup) . "</p>";
        }
        
        echo "</div>";
    }
}
?>

<?php
SimpleAmiClient::logoff($fp);
?>

</body>
</html> 