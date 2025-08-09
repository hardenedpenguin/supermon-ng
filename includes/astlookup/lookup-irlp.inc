<?php
/**
 * IRLP Lookup Functions
 * 
 * Handles IRLP database lookups by callsign and node number
 */

/**
 * Search IRLP database by callsign
 */
function do_irlp_callsign_search($lookup) {
    global $IRLP_CALLS, $IRLP, $ZCAT, $AWK;

    if ($IRLP) {
        $text = "IRLP Callsign Search for: \"$lookup\"";
        echo "<tr><td colspan='5' class='lookup-section-header'>$text</td></tr>";

        $res = `$ZCAT $IRLP_CALLS | $AWK '-F|' 'BEGIN{IGNORECASE=1} $2 ~ /$lookup/ {printf ("%s\x18", $0);}'`;

        process_irlp_result($res);
    }
}

/**
 * Search IRLP database by node number
 */
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

/**
 * Process and display IRLP lookup results
 */
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
