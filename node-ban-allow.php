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

if (!empty($_POST["listtype"])) {
	$listtype_base = $_POST["listtype"];
	$nodeToModify = $_POST["node"];
	$comment = $_POST["comment"];
	$deleteadd = $_POST["deleteadd"];

	$DBname = $listtype_base . "/" . $localnode; 
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
<title>Allow/Deny Nodes - <?php echo htmlspecialchars($localnode); ?></title>
</head>
<body style="background-color: black; color: white;">

<p style="text-align:center; font-size: 1.5em; color: #3399FF;"><b>Allow/Deny AllStar Nodes at node <?php echo htmlspecialchars($localnode); ?></b></p>

<center>
<form action="node-ban-allow.php?ban-node=<?php echo htmlspecialchars($Node); ?>&localnode=<?php echo htmlspecialchars($localnode); ?>" method="post">
<table cellspacing="20" style="margin-top:0; font-size:22px;">
<tr>
<td align="top" style="text-align:center;">
 <input type="radio" style="transform: scale(2);" name="listtype" value="denylist" checked> Denied - denylist
 <input type="radio" style="margin-left:30px; transform: scale(2);" name="listtype" value="allowlist"> Allowed - allowlist<br>
</td></tr>
<tr><td style="text-align:center;">
Enter Node number -  
 <input type="text" name="node" value="<?php echo htmlspecialchars($Node); ?>" maxlength="7" size="5">
</td></tr>
<tr><td style="text-align:center;">
Enter comment -
 <input type="text" name="comment" maxlength="30" size="22">
</td></tr>
<tr>
<td style="text-align:center;">
 <input type="radio" style="transform: scale(2);" name="deleteadd" value="add" checked> Add
 <input type="radio" style="margin-left:30px; transform: scale(2);" name="deleteadd" value="delete"> Delete<br>
</td>
</tr>
<tr><td align="center">
<input type="submit" class="submit-large" value="Update">
   
<input type="button" class="submit-large" Value="Close Window" onclick="self.close()">
</td></tr>
<tr><td style="text-align:left;">Current Nodes in the Denied - denylist (for node <?php echo htmlspecialchars($localnode); ?>):
<?php
$denylistDBFamily = "denylist/" . $localnode;
$dataDenylist = getDataFromAMI($fp, "database show " . $denylistDBFamily); 

if ($dataDenylist === false || trim($dataDenylist) === "") {
	print "<p>---NONE---</p>";
} else {
    $dataDenylist = str_replace('          ', ' ', $dataDenylist);
	print "<pre>" . htmlspecialchars(trim($dataDenylist)) . "</pre>";
} 
?>
</td></tr>
<tr>
<td style="text-align:left;">Current Nodes in the Allowed - allowlist (for node <?php echo htmlspecialchars($localnode); ?>):
<?php
$allowlistDBFamily = "allowlist/" . $localnode;
$dataAllowlist = getDataFromAMI($fp, "database show " . $allowlistDBFamily);

if ($dataAllowlist === false || trim($dataAllowlist) === "") {
	print "<p>---NONE---</p>";
} else {
    $dataAllowlist = str_replace('          ', ' ', $dataAllowlist);
	print "<pre>" . htmlspecialchars(trim($dataAllowlist)) . "</pre>";
}

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