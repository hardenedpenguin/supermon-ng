<?php
/**
 * AllStar Lookup Functions
 * 
 * Handles AllStar database lookups by callsign and node number
 */

/**
 * Search AllStar database by callsign
 */
function do_allstar_callsign_search($fp, $lookup, $localnode) {
    global $ASTDB_TXT, $CAT, $AWK;

    $text = "AllStar Callsign Search for: \"$lookup\"";
    echo "<tr><td colspan='5' class='lookup-section-header'>$text</td></tr>";

    $res = `$CAT $ASTDB_TXT | $AWK '-F|' 'BEGIN{IGNORECASE=1} $2 ~ /$lookup/ {printf ("%s\x18", $0);}'`;

    process_allstar_result($fp, $res, $localnode);
}

/**
 * Search AllStar database by node number
 */
function do_allstar_number_search($fp, $lookup, $localnode) {
    global $ASTDB_TXT, $CAT, $AWK;

    $text = "AllStar Node Number Search for: \"$lookup\"";
    echo "<tr><td colspan='5' class='lookup-section-header'>$text</td></tr>";

    $res = `$CAT $ASTDB_TXT | $AWK '-F|' 'BEGIN{IGNORECASE=1} $1 ~ /$lookup/ {printf ("%s\x18", $0);}'`;

    process_allstar_result($fp, $res, $localnode);
}

/**
 * Process and display AllStar lookup results
 */
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
