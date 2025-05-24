<?php
include("session.inc");
include("authusers.php");

if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("BANUSER"))) {
    die ("<br><h3>ERROR: You Must login to use the 'Allow/Restrict function' function!</h3>");
}

$Node = isset($_GET['node']) ? trim(strip_tags($_GET['node'])) : '';
$localnode = isset($_GET['localnode']) ? trim(strip_tags($_GET['localnode'])) : '';

?>
<html>
<head>
    <title>Allow/Restrict AllStar Nodes</title>
    <link type="text/css" rel="stylesheet" href="supermon.css">
    <style>
        body { background-color: black; }
        .page-title { text-align: center; font-size: 1.5em; font-weight: bold; }
        .content-box { font-size: 18px; margin-left: 10px; margin-right: 10px; }
        .button-container { text-align: center; margin-top: 20px; }
    </style>
</head>
<body>

<p class="page-title">Allow/Restrict AllStar Nodes</p>

<div class="content-box">
    <p>
       This function can be used to temporarily or permanently block or
       allow remote nodes to connect to your node. Commonly called a
       blacklist or whitelist. Only one list can be in effect but both
       could be defined. You can either specify a list to allow (whitelist)
       or ban (blacklist). The blacklist is useful when there is an issue
       with a node that is causing problems at your end. This could be a
       node that has some technical issue that is keying or hanging up your
       node or perhaps someone who is not abiding by FCC or your rules.
       You should always try to contact the person you are blocking but in
       some cases that may not be possible. On the other hand a whitelist
       allows you to specify a list of nodes that CAN connect to your node.
       Only those nodes in the whitelist will be able to connect. In most
       situations you would use the blacklist blocking one or several nodes.
       If the blacklist is empty and active all nodes can connect, if the
       whitelist is empty and active no nodes can connect.
    </p>
    <p>
       You MUST setup either the white or blacklist in each servers
       iax.conf you want to execute the ban on.
    </p>
    <p>
       The database name is either "whitelist" or "blacklist" You <b>MUST</b>
       configure your extension.conf and iax.conf files as described at
       this URL in order for this to work. NOTE newer Hamvoip installations
       already have ths setup but you need to activate in the iax.conf file.
    </p>
    <p>
       <a href="http://wiki.allstarlink.org/wiki/Blacklist_or_whitelist" target="_blank" rel="noopener noreferrer">http://wiki.allstarlink.org/wiki/Blacklist_or_whitelist</a>
    </p>
</div>

<form action="node-ban-allow.php?ban-node=<?php echo htmlspecialchars($Node); ?>&localnode=<?php echo htmlspecialchars($localnode); ?>" method="post">
    <div class="button-container">
        <input type="submit" class="submit-large" value="Continue">
         
        <input type="button" class="submit-large" value="Close Window" onclick="self.close()">
    </div>
</form>

</body>
</html>