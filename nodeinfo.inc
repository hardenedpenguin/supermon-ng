<?php
require_once 'amifunctions.inc';

define('IRLP_CACHE_LIFETIME_FOUND', 600);
define('IRLP_CACHE_LIFETIME_NOT_FOUND', 30);
define('ELNK_CACHE_LIFETIME_FOUND', 300);
define('ELNK_CACHE_LIFETIME_NOT_FOUND', 30);

global $irlp_cache, $elnk_cache, $astdb;
global $IRLP_CALLS, $ZCAT, $AWK;

if (!isset($irlp_cache)) {
    $irlp_cache = [];
}
if (!isset($elnk_cache)) {
    $elnk_cache = [];
}
if (!isset($astdb)) {
    $astdb = [];
}

function irlp_cache_lookup($irlpnode)
{
    global $irlp_cache, $IRLP_CALLS, $ZCAT, $AWK;

    $lookup_read_key = (int)substr((string)$irlpnode, 1);
    $lookup2_display_node = $lookup_read_key;
    $lookup3_subchannel_digit = substr((string)$lookup2_display_node, 3, 1);

    if (isset($irlp_cache[$lookup_read_key])) {
        $cached_entry = $irlp_cache[$lookup_read_key];
        if (time() > $cached_entry[0]) {
            unset($irlp_cache[$lookup_read_key]);
        } else {
            return $cached_entry[1] . " [IRLP $lookup2_display_node] " . $cached_entry[2];
        }
    }

    $lookup_search_and_write_key = $lookup_read_key;
    if ($lookup_read_key >= 9000 && $lookup3_subchannel_digit > '0') {
        $lookup_search_and_write_key = (int)substr_replace((string)$lookup_read_key, '0', 3, 1);
    }

    if (empty($ZCAT) || empty($IRLP_CALLS) || empty($AWK)) {
        $irlp_cache[$lookup_search_and_write_key] = [
            time() + IRLP_CACHE_LIFETIME_NOT_FOUND,
            "No info (config error)",
            "No info"
        ];
        return "No info (config error) [IRLP $lookup2_display_node] No info";
    }

    $shell_command = "$ZCAT " . escapeshellarg($IRLP_CALLS) . " | $AWK '-F|' 'BEGIN{IGNORECASE=1} \$1 ~ /" . $lookup_search_and_write_key . "/ {printf (\"%s\\x18\", \$0);}'";
    $res = shell_exec($shell_command);

    $table = [];
    if ($res !== null && $res !== '') {
        $table = explode("\x18", $res);
        array_pop($table);
    }

    if (!empty($table)) {
        $first_row_string = $table[0];
        $row_columns = explode("|", $first_row_string);

        if (count($row_columns) >= 5) {
            $node_from_file = trim($row_columns[0]);
            if ($node_from_file == $lookup_search_and_write_key) {
                $callsign = trim($row_columns[1]);
                $qth = trim($row_columns[2] . ", " . $row_columns[3] . " " . $row_columns[4]);
                $info_callsign = $callsign;

                if ($lookup_search_and_write_key >= 9000 && $lookup3_subchannel_digit > '0') {
                    $info_callsign = "REF" . $lookup2_display_node;
                }

                $irlp_cache[$lookup_search_and_write_key] = [
                    time() + IRLP_CACHE_LIFETIME_FOUND,
                    $info_callsign,
                    $qth
                ];
                return $info_callsign . " [IRLP $lookup2_display_node] " . $qth;
            }
        }
    }

    $irlp_cache[$lookup_search_and_write_key] = [
        time() + IRLP_CACHE_LIFETIME_NOT_FOUND,
        "No info",
        "No info"
    ];
    return "No info [IRLP $lookup2_display_node] No info";
}

function echolink_cache_lookup($fp, $echonode)
{
    global $elnk_cache;

    $lookup_node = (int)substr((string)$echonode, 1);

    if (isset($elnk_cache[$lookup_node])) {
        $cached_entry = $elnk_cache[$lookup_node];
        if (time() > $cached_entry[0]) {
            unset($elnk_cache[$lookup_node]);
        } else {
            return $cached_entry[1] . " [EchoLink $lookup_node] (" . $cached_entry[2] . ")";
        }
    }

    $command_string = "echolink dbget nodename $lookup_node";
    $ami_response_string = SimpleAmiClient::command($fp, $command_string);

    if ($ami_response_string === false || $ami_response_string === '') {
        $elnk_cache[$lookup_node] = [
            time() + ELNK_CACHE_LIFETIME_NOT_FOUND,
            "No info (cmd fail)",
            "No info"
        ];
        return "No info (cmd fail) [EchoLink $lookup_node] (No info)";
    }

    $rows = explode("\n", $ami_response_string);

    if (!empty($rows) && !empty($rows[0])) {
        $columns = explode("|", $rows[0]);
        if (count($columns) >= 3 && trim($columns[0]) == $lookup_node) {
            $callsign = trim($columns[1]);
            $location = trim($columns[2]);

            $elnk_cache[$lookup_node] = [
                time() + ELNK_CACHE_LIFETIME_FOUND,
                $callsign,
                $location
            ];
            return "$callsign [EchoLink $lookup_node] ($location)";
        }
    }

    $elnk_cache[$lookup_node] = [
        time() + ELNK_CACHE_LIFETIME_NOT_FOUND,
        "No info",
        "No info"
    ];
    return "No info [EchoLink $lookup_node] (No info)";
}

function getAstInfo($fp, $nodeNum)
{
    global $astdb;

    if (isset($astdb[$nodeNum])) {
        $dbNode = $astdb[$nodeNum];
        $info = (isset($dbNode[1]) ? $dbNode[1] : '') .
                (isset($dbNode[2]) ? ' ' . $dbNode[2] : '') .
                (isset($dbNode[3]) ? ' ' . $dbNode[3] : '');
        return trim($info);
    }

    if (is_numeric($nodeNum)) {
        $nodeNumNumeric = (int)$nodeNum;
        if ($nodeNumNumeric > 3000000) {
            return echolink_cache_lookup($fp, $nodeNum);
        }
        if ($nodeNumNumeric > 80000) {
            return irlp_cache_lookup($nodeNum);
        }
        return 'Node not in local database';
    }

    if (is_string($nodeNum) && strpos($nodeNum, '-P') !== false) {
        return 'AllStar Phone Portal user';
    }

    return 'IaxRpt or Web Transceiver client';
}

?>