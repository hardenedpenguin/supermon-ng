<?php
/**
 * Voter HTML Formatter
 * 
 * Handles HTML table generation for voter client status display
 */

/**
 * Generates the HTML table for a single voter node's status display.
 * It takes the cleanly parsed data, builds the table structure, calculates RSSI bar widths,
 * and determines the correct color for each bar based on its status flag (voted, voting, or mix).
 *
 * @global resource $fp          The active file pointer/socket connection to the Asterisk Manager.
 * @global array    $astdb       A cached array of the Asterisk database contents.
 * @param  string   $nodeNum       The node number for which to generate the HTML.
 * @param  array    $nodesData     The structured array of all nodes and their clients from parse_voter_response().
 * @param  array    $votedData     The array mapping nodes to their voted client from parse_voter_response().
 * @param  array    $currentConfig The configuration stanza for this specific node from the INI file.
 * @return string   A single-line HTML string representing the complete table for one node.
 */
function format_node_html($nodeNum, $nodesData, $votedData, $currentConfig) {
    global $fp, $astdb;
    $message = '';
    $info = getAstInfo($fp, $nodeNum); 

    if (!empty($currentConfig['hideNodeURL'])) {
        $message .= "<table class='rtcm'><tr><th colspan=2><i>   Node $nodeNum - $info   </i></th></tr>";
    } else {
        $nodeURL = "http://stats.allstarlink.org/nodeinfo.cgi?node=$nodeNum";
        $message .= "<table class='rtcm'><tr><th colspan=2><i>   Node <a href=\"$nodeURL\" target=\"_blank\">$nodeNum</a> - $info   </i></th></tr>";
    }
    $message .= "<tr><th>Client</th><th>RSSI</th></tr>";

    if (!isset($nodesData[$nodeNum]) || empty($nodesData[$nodeNum])) {
        $message .= "<tr><td><div class='voter-no-clients'>&nbsp;No clients&nbsp;</div></td>";
        $message .= "<td><div class='voter-empty-bar'>&nbsp;</div></td></tr>";
    } else {
        $clients = $nodesData[$nodeNum];
        $votedClient = isset($votedData[$nodeNum]) && $votedData[$nodeNum] !== 'none' ? $votedData[$nodeNum] : null;

        foreach($clients as $clientName => $client) {
            $rssi = isset($client['rssi']) ? (int)$client['rssi'] : 0;
            $bar_width_px = round(($rssi / 255) * 300); 
            $bar_width_px = ($rssi == 0) ? 3 : max(1, $bar_width_px);
            
            $barcolor = "#0099FF"; $textcolor = 'white'; 
            
            if ($votedClient && $clientName === $votedClient) {
                $barcolor = 'greenyellow'; $textcolor = 'black';
            } elseif (isset($client['isMix']) && $client['isMix'] === true) {
                $barcolor = 'cyan'; $textcolor = 'black';
            }

            $message .= "<tr>";
            // The $clientName is already clean from the parsing function
            $message .= "<td><div>" . htmlspecialchars($clientName) . "</div></td>";
            $message .= "<td><div class='text'> <div class='barbox_a'>";
            $message .= "<div class='bar' style='width: " . $bar_width_px . "px; background-color: $barcolor; color: $textcolor'>" . $rssi . "</div>";
            $message .= "</div></td></tr>";
        }
    }
    $message .= "<tr><td colspan=2> </td></tr>";
    $message .= "</table><br/>";
    
    return str_replace(["\r", "\n"], '', $message);
}
