<?php

include("session.inc");
include('amifunctions.inc');
include("common.inc");
include("authusers.php");
include("authini.php");

if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("BANUSER")))  {
    die ("<br><h3>ERROR: You Must login to use the 'Restrict' function!</h3>");
}

$Node = trim(strip_tags($_GET['ban-node']));
$localnode = @trim(strip_tags($_GET['localnode']));

$SUPINI = get_ini_name($_SESSION['user']);

if (!file_exists($SUPINI)) {
	die("Couldn't load $SUPINI file.\n");
}

$config = parse_ini_file($SUPINI, true);

if (empty($localnode) || !isset($config[$localnode])) {
	die("Node $localnode is not in $SUPINI file or not specified.");
}

if (($fp = SimpleAmiClient::connect($config[$localnode]['host'])) === FALSE) {
	die("Could not connect to Asterisk Manager.");
}

if (SimpleAmiClient::login($fp, $config[$localnode]['user'], $config[$localnode]['passwd']) === FALSE) {
	SimpleAmiClient::logoff($fp);
	die("Could not login to Asterisk Manager.");
}

function sendCmdToAMI($fp, $cmd)
{
    return SimpleAmiClient::command($fp, $cmd);
}

function getDataFromAMI($fp, $cmd)
{
    return SimpleAmiClient::command($fp, $cmd);
}

if (!empty($_POST["whiteblack"])) {
	$whiteblack = $_POST["whiteblack"];
	$nodeToModify = $_POST["node"];
	$comment = $_POST["comment"];
	$deleteadd = $_POST["deleteadd"];

	$DBname = ($whiteblack == "whitelist") ? "whitelist" : "blacklist";
	$cmdAction = ($deleteadd == "add") ? "put" : "del";

	$amiCmdString = "database $cmdAction $DBname $nodeToModify";
	if ($cmdAction == "put") {
		$amiCmdString .= " \"$comment\"";
	}
	
	$ret = sendCmdToAMI($fp, $amiCmdString);
}

?>
<html>
<head>
<link type="text/css" rel="stylesheet" href="supermon-ng.css">
<title>Allow/Restrict Nodes - <?php echo htmlspecialchars($localnode); ?></title>
</head>
<body style="background-color: black; color: white;">

<p style="text-align:center;font-size: 1.5em;"><b>Allow/Restrict AllStar Nodes at node <?php echo htmlspecialchars($localnode); ?></b></p>

<center>
<form action="node-ban-allow.php?ban-node=<?php echo htmlspecialchars($Node); ?>&localnode=<?php echo htmlspecialchars($localnode); ?>" method="post">
<table cellspacing="20" style="margin-top:0; font-size:22px;">
<tr>
<td align="top">
 <input type="radio" style="transform: scale(2);" name="whiteblack" value="blacklist" checked> Restricted - blacklist
 <input type="radio" style="margin-left:30px; transform: scale(2);" name="whiteblack" value="whitelist"> Allowed - whitelist<br>
</td></tr>
<tr><td>
Enter Node number -  
 <input type="text" name="node" value="<?php echo htmlspecialchars($Node); ?>" maxlength="7" size="5">
</td></tr>
<tr><td>
Enter comment -
 <input type="text" name="comment" maxlength="30" size="22">
</td></tr>
<tr>
<td>
 <input type="radio" style="transform: scale(2);" name="deleteadd" value="add" checked> Add
 <input type="radio" style="margin-left:30px; transform: scale(2);" name="deleteadd" value="delete"> Delete<br>
</td>
</tr>
<tr><td>Current Nodes in the Restricted - blacklist:
<?php
$dataBlacklist = getDataFromAMI($fp, "database show blacklist");

if ($dataBlacklist === false || trim($dataBlacklist) === "") {
	print "<p>---NONE---</p>";
} else {
    $dataBlacklist = str_replace('          ', ' ', $dataBlacklist);
	print "<pre>" . htmlspecialchars(trim($dataBlacklist)) . "</pre>";
} 
?>
</td></tr>
<tr><td align="center">
<input type="submit" class="submit-large" value="Update">
   
<input type="button" class="submit-large" Value="Close Window" onclick="self.close()">
</td></tr>
<tr>
<td>Current Nodes in the Allowed - whitelist:
<?php
$dataWhitelist = getDataFromAMI($fp, "database show whitelist");

if ($dataWhitelist === false || trim($dataWhitelist) === "") {
	print "<p>---NONE---</p>";
} else {
    $dataWhitelist = str_replace('          ', ' ', $dataWhitelist);
	print "<pre>" . htmlspecialchars(trim($dataWhitelist)) . "</pre>";
}

print "<center><b>White or Blacklist must be defined<br>in iax.conf file of node - " . htmlspecialchars($localnode) . "</b></center>";

?>
</td></tr>
</table>
</center>
</form>
<?php
if ($fp) {
    SimpleAmiClient::logoff($fp);
}
?>
</body>
</html>