<?php

include("session.inc");
include('amifunctions.inc');
include("common.inc");
include("authusers.php");
include("authini.php");
include("csrf.inc");

if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("BANUSER")))  {
    die ("<br><h3 class='error-message'>ERROR: You Must login to use the 'Restrict' function!</h3>");
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
}

$Node = trim(strip_tags($_GET['node'] ?? $_GET['ban-node'] ?? ''));
$localnode = trim(strip_tags($_GET['localnode'] ?? ''));

// Debug: Show what parameters we received
error_log("Node-ban-allow Debug: Node='$Node', localnode='$localnode'");

// Validate inputs - only require localnode to be numeric, Node can be empty initially
if (!preg_match('/^\d+$/', $localnode)) {
    die("<h3 class='error-message'>ERROR: Invalid local node parameter.</h3>");
}

// Node parameter is optional (can be empty when page loads)
if (!empty($Node) && !preg_match('/^\d+$/', $Node)) {
    die("<h3 class='error-message'>ERROR: Invalid node parameter.</h3>");
}

$SUPINI = get_ini_name($_SESSION['user']);

if (!file_exists($SUPINI)) {
    die("<h3 class='error-message'>ERROR: Couldn't load $SUPINI file.</h3>");
}

$config = parse_ini_file($SUPINI, true);

if (empty($localnode) || !isset($config[$localnode])) {
    die("<h3 class='error-message'>ERROR: Node $localnode is not in $SUPINI file or not specified.</h3>");
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

if (!empty($_POST["listtype"]) && !empty($_POST["node"]) && !empty($_POST["deleteadd"])) {
    $listtype_base = trim(strip_tags($_POST["listtype"]));
    $nodeToModify = trim(strip_tags($_POST["node"]));
    $comment = trim(strip_tags($_POST["comment"] ?? ''));
    $deleteadd = trim(strip_tags($_POST["deleteadd"]));

    // Validate inputs
    if (!in_array($listtype_base, ['allowlist', 'denylist'])) {
        die("<h3 class='error-message'>ERROR: Invalid list type.</h3>");
    }
    
    if (!preg_match('/^\d+$/', $nodeToModify)) {
        die("<h3 class='error-message'>ERROR: Invalid node number.</h3>");
    }
    
    if (!in_array($deleteadd, ['add', 'delete'])) {
        die("<h3 class='error-message'>ERROR: Invalid action.</h3>");
    }

    $DBname = $listtype_base . "/" . $localnode; 
    $cmdAction = ($deleteadd == "add") ? "put" : "del";

    $amiCmdString = "database $cmdAction $DBname $nodeToModify";
    if ($cmdAction == "put" && !empty($comment)) {
        $amiCmdString .= " \"" . addslashes($comment) . "\"";
    }
    
    $ret = sendCmdToAMI($fp, $amiCmdString);
    
    // Show result message
    if ($ret !== false) {
        echo "<div style='background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; border-radius: 4px;'>Command executed successfully.</div>";
    } else {
        echo "<div style='background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; margin: 10px 0; border-radius: 4px;'>Failed to execute command. Check Asterisk logs for details.</div>";
    }
}

?>
<html>
<head>
<link type="text/css" rel="stylesheet" href="supermon-ng.css">
<title>Allow/Deny Nodes - <?php echo htmlspecialchars($localnode); ?></title>
</head>
<body class="ban-allow-page">

<p class="ban-allow-title"><b>Allow/Deny AllStar Nodes at node <?php echo htmlspecialchars($localnode); ?></b></p>

<center>
<form action="node-ban-allow.php?localnode=<?php echo htmlspecialchars($localnode); ?>" method="post">
    <?php if (function_exists('csrf_token_field')) echo csrf_token_field(); ?>
    <table class="ban-allow-table">
        <tr>
            <td class="ban-allow-cell-left">
                <b>Node to Add/Delete:</b><br>
                <input type="text" name="node" value="<?php echo htmlspecialchars($Node); ?>" maxlength="7" size="5" pattern="\d+" required>
            </td>
            <td class="ban-allow-cell-right">
                <b>Comment:</b><br>
                <input type="text" name="comment" maxlength="50" size="20">
            </td>
        </tr>
        <tr>
            <td class="ban-allow-cell-left">
                <b>List Type:</b><br>
                <input type="radio" name="listtype" value="allowlist" checked> Allow List<br>
                <input type="radio" name="listtype" value="denylist"> Deny List
            </td>
            <td class="ban-allow-cell-right">
                <b>Action:</b><br>
                <input type="radio" name="deleteadd" value="add" checked> Add<br>
                <input type="radio" name="deleteadd" value="delete"> Delete
            </td>
        </tr>
        <tr>
            <td colspan="2" class="ban-allow-cell-center">
                <input type="submit" value="Execute" class="ban-allow-button">
            </td>
        </tr>
    </table>
</form>
</center>

<table class="ban-allow-table">
<tr>
<td class="ban-allow-cell-left">Current Nodes in the Denied - denylist (for node <?php echo htmlspecialchars($localnode); ?>):
<?php
$denylistDBFamily = "denylist/" . $localnode;
$rawDataDeny = getDataFromAMI($fp, "database show " . $denylistDBFamily);

if ($rawDataDeny === false || trim($rawDataDeny) === "") {
    print "<p>---NONE---</p>";
} else {
    $lines = explode("\n", $rawDataDeny);
    $outputLines = [];
    foreach ($lines as $line) {
        $processedLine = trim($line);
        if (strpos($processedLine, "Output: ") === 0) {
            $processedLine = substr($processedLine, strlen("Output: "));
            $processedLine = trim($processedLine); 
        }
        
        if (preg_match('/^\d+\s+results found\.?$/i', $processedLine)) {
            continue; 
        }

        if (trim($processedLine) !== "") {
            $processedLine = str_replace('          ', ' ', $processedLine);
            $outputLines[] = $processedLine;
        }
    }

    if (empty($outputLines)) {
        print "<p>---NONE---</p>";
    } else {
        $finalOutput = implode("\n", $outputLines);
        print "<pre>" . htmlspecialchars(trim($finalOutput)) . "</pre>";
    }
} 
?>
</td></tr>
<tr>
<td class="ban-allow-cell-left">Current Nodes in the Allowed - allowlist (for node <?php echo htmlspecialchars($localnode); ?>):
<?php
$allowlistDBFamily = "allowlist/" . $localnode;
$rawDataAllow = getDataFromAMI($fp, "database show " . $allowlistDBFamily);

if ($rawDataAllow === false || trim($rawDataAllow) === "") {
    print "<p>---NONE---</p>";
} else {
    $lines = explode("\n", $rawDataAllow);
    $outputLines = [];
    foreach ($lines as $line) {
        $processedLine = trim($line);
        if (strpos($processedLine, "Output: ") === 0) {
            $processedLine = substr($processedLine, strlen("Output: "));
            $processedLine = trim($processedLine); 
        }
        
        if (preg_match('/^\d+\s+results found\.?$/i', $processedLine)) {
            continue; 
        }

        if (trim($processedLine) !== "") {
            $processedLine = str_replace('          ', ' ', $processedLine);
            $outputLines[] = $processedLine;
        }
    }

    if (empty($outputLines)) {
        print "<p>---NONE---</p>";
    } else {
        $finalOutput = implode("\n", $outputLines);
        print "<pre>" . htmlspecialchars(trim($finalOutput)) . "</pre>";
    }
} 
?>
</td></tr>
</table>

<?php
SimpleAmiClient::logoff($fp);
?>

</body>
</html>