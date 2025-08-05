<?php

include("includes/session.inc");
include('includes/amifunctions.inc');
include("includes/common.inc");
include("authusers.php");
include("authini.php");
include("includes/csrf.inc");

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
<!-- Modular CSS Files -->
<link type="text/css" rel="stylesheet" href="css/base.css">
<link type="text/css" rel="stylesheet" href="css/layout.css">
<link type="text/css" rel="stylesheet" href="css/menu.css">
<link type="text/css" rel="stylesheet" href="css/tables.css">
<link type="text/css" rel="stylesheet" href="css/forms.css">
<link type="text/css" rel="stylesheet" href="css/widgets.css">
<link type="text/css" rel="stylesheet" href="css/responsive.css">
<!-- Custom CSS (load last to override defaults) -->
<link type="text/css" rel="stylesheet" href="css/custom.css">
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
    $nodeToLookup = strtoupper($nodeToLookup);
    $intnode = (int)$nodeToLookup;
    
    if (!empty($nodeToLookup)) {
        echo "<div class='lookup-results'>";
        echo "<h3>Lookup Results for: " . htmlspecialchars($nodeToLookup) . "</h3>";
        
        echo "<div class='lookup-command'>";
        echo "<table class='lookup-results-table'>";
        
        if ("$intnode" != "$nodeToLookup") {
            // Do lookup by callsign
            do_allstar_callsign_search($fp, $nodeToLookup, $localnode);
            
            if ($perm != "on") {
                do_echolink_callsign_search($fp, $nodeToLookup);
                do_irlp_callsign_search($nodeToLookup);
            }
        } else if ($intnode > 80000 && $intnode < 90000) {
            // Lookup by IRLP node number
            do_irlp_number_search($intnode);
        } else if ($intnode > 3000000) {
            // Lookup by echolink node number
            do_echolink_number_search($fp, $intnode);
        } else {
            // Lookup by AllStar node number
            do_allstar_number_search($fp, $intnode, $localnode);
        }
        
        echo "</table>";
        echo "</div>";
        
        echo "</div>";
    }
}

// Local Functions...

function do_allstar_callsign_search($fp, $lookup, $localnode) {
    global $ASTDB_TXT, $CAT, $AWK;

    $text = "AllStar Callsign Search for: \"$lookup\"";
    echo "<tr><td colspan='5' class='lookup-section-header'>$text</td></tr>";

    $res = `$CAT $ASTDB_TXT | $AWK '-F|' 'BEGIN{IGNORECASE=1} $2 ~ /$lookup/ {printf ("%s\x18", $0);}'`;

    process_allstar_result($fp, $res, $localnode);
}

function do_allstar_number_search($fp, $lookup, $localnode) {
    global $ASTDB_TXT, $CAT, $AWK;

    $text = "AllStar Node Number Search for: \"$lookup\"";
    echo "<tr><td colspan='5' class='lookup-section-header'>$text</td></tr>";

    $res = `$CAT $ASTDB_TXT | $AWK '-F|' 'BEGIN{IGNORECASE=1} $1 ~ /$lookup/ {printf ("%s\x18", $0);}'`;

    process_allstar_result($fp, $res, $localnode);
}

function process_allstar_result($fp, $res, $localnode) {
    global $HEAD, $SED, $AWK, $GREP, $DNSQUERY;

    if ("$res" == "") {
        echo "<tr><td colspan='5' class='lookup-no-results'>....Nothing Found....</td></tr>";
        return;
    }

    $table = explode("\x18", $res);
    array_pop($table);

    foreach ($table as $row) {
        echo "<tr class='lookup-result-row'>";

        $column = explode("|", $row);
        $node = trim($column[0]);
        $call = trim($column[1]);
        $desc = trim($column[2]);
        $qth = trim($column[3]);
        
        $AMI2 = `$DNSQUERY $node`;
        $N = `echo -n "$AMI2" | $AWK -F "," '{printf $1}'`;

        $G = `echo -n "$N" | $GREP 'NOT-FOUND'`;
        if (strlen("$G") >= 9)
            $N = "NOT FOUND";

        echo "<td class='lookup-node'>$node</td>";
        echo "<td class='lookup-callsign'>$call</td>";
        echo "<td class='lookup-description'>$desc</td>";
        echo "<td class='lookup-location'>$qth</td>";
        echo "<td class='lookup-status'>$N</td>";
        echo "</tr>";
    }
}

function do_echolink_callsign_search($fp, $lookup) {
    global $AWK, $GREP, $MBUFFER;

    $AMI = SimpleAmiClient::command($fp, "echolink dbdump");

    $descriptorspec = array(
        0 => array("pipe", "r"),
        1 => array("pipe", "w"),
        2 => array("file", "/dev/null", "w")
    );

    $cmd = "$GREP 'No such command' | $MBUFFER -q -Q -m 1M";

    $process = proc_open($cmd, $descriptorspec, $pipes);

    fwrite($pipes[0], $AMI);
    fclose($pipes[0]);

    $G = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    proc_close($process);

    if (strlen("$G") < 14) {
        $text = "EchoLink Callsign Search for: \"$lookup\"";
        echo "<tr><td colspan='5' class='lookup-section-header'>$text</td></tr>";

        $cmd = "$AWK '-F|' 'BEGIN{IGNORECASE=1} $2 ~ /$lookup/ {printf (\"%s\x18\", $0);}' | $MBUFFER -q -Q -m 1M";

        $process = proc_open($cmd, $descriptorspec, $pipes);

        fwrite($pipes[0], $AMI);
        fclose($pipes[0]);

        $res = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        proc_close($process);

        process_echolink_result($res);
    }
}

function do_echolink_number_search($fp, $echonode) {
    global $AWK, $GREP, $MBUFFER;

    $lookup = (int)substr("$echonode", 1);

    $AMI = SimpleAmiClient::command($fp, "echolink dbdump");

    $descriptorspec = array(
        0 => array("pipe", "r"),
        1 => array("pipe", "w"),
        2 => array("file", "/dev/null", "w")
    );

    $cmd = "$GREP 'No such command' | $MBUFFER -q -Q -m 1M";

    $process = proc_open($cmd, $descriptorspec, $pipes);

    fwrite($pipes[0], $AMI);
    fclose($pipes[0]);

    $G = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    proc_close($process);

    if (strlen("$G") < 14) {
        $text = "EchoLink Node Number Search for: \"$lookup\"";
        echo "<tr><td colspan='5' class='lookup-section-header'>$text</td></tr>";

        $cmd = "$AWK '-F|' 'BEGIN{IGNORECASE=1} $1 ~ /$lookup/ {printf (\"%s\x18\", $0);}' | $MBUFFER -q -Q -m 1M";

        $process = proc_open($cmd, $descriptorspec, $pipes);

        fwrite($pipes[0], $AMI);
        fclose($pipes[0]);

        $res = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        proc_close($process);

        process_echolink_result($res);
    }
}

function process_echolink_result($res) {
    if ("$res" == "") {
        echo "<tr><td colspan='5' class='lookup-no-results'>....Nothing Found....</td></tr>";
        return;
    }

    $table = explode("\x18", $res);
    array_pop($table);

    foreach ($table as $row) {
        echo "<tr class='lookup-result-row'>";

        $column = explode("|", $row);
        $node = trim($column[0]);
        $call = trim($column[1]);
        $ipaddr = trim($column[2]);

        echo "<td colspan='2' class='lookup-node'>$node</td>";
        echo "<td colspan='2' class='lookup-callsign'>$call</td>";
        echo "<td colspan='1' class='lookup-ip'>$ipaddr</td>";
        echo "</tr>";
    }
}

function do_irlp_callsign_search($lookup) {
    global $IRLP_CALLS, $IRLP, $ZCAT, $AWK;

    if ($IRLP) {
        $text = "IRLP Callsign Search for: \"$lookup\"";
        echo "<tr><td colspan='5' class='lookup-section-header'>$text</td></tr>";

        $res = `$ZCAT $IRLP_CALLS | $AWK '-F|' 'BEGIN{IGNORECASE=1} $2 ~ /$lookup/ {printf ("%s\x18", $0);}'`;

        process_irlp_result($res);
    }
}

function do_irlp_number_search($irlpnode) {
    global $IRLP_CALLS, $IRLP, $ZCAT, $AWK;

    if ($IRLP) {
        $lookup = (int)substr("$irlpnode", 1);

        $text = "IRLP Node Number Search for: \"$lookup\"";
        echo "<tr><td colspan='5' class='lookup-section-header'>$text</td></tr>";

        $res = `$ZCAT $IRLP_CALLS | $AWK '-F|' 'BEGIN{IGNORECASE=1} $1 ~ /$lookup/ {printf ("%s\x18", $0);}'`;

        process_irlp_result($res);
    }
}

function process_irlp_result($res) {
    if ("$res" == "") {
        echo "<tr><td colspan='5' class='lookup-no-results'>....Nothing Found....</td></tr>";
        return;
    }

    $table = explode("\x18", $res);
    array_pop($table);

    foreach ($table as $row) {
        echo "<tr class='lookup-result-row'>";

        $column = explode("|", $row);
        $node = trim($column[0]);
        $call = trim($column[1]);
        $qth = trim($column[2] . ", " . $column[3] . " " . $column[4]);

        echo "<td colspan='2' class='lookup-node'>$node</td>";
        echo "<td colspan='2' class='lookup-callsign'>$call</td>";
        echo "<td colspan='1' class='lookup-location'>$qth</td>";
        echo "</tr>";
    }
}
?>

<?php
SimpleAmiClient::logoff($fp);
?>

</body>
</html> 