<?php
/**
 * EchoLink Lookup Functions
 * 
 * Handles EchoLink database lookups by callsign and node number
 */

/**
 * Search EchoLink database by callsign
 */
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

/**
 * Search EchoLink database by node number
 */
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

/**
 * Process and display EchoLink lookup results
 */
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
