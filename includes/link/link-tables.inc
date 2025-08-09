<?php
/**
 * Link.php Node Table Rendering
 * 
 * This file contains node table generation logic
 * extracted from link.php for better organization.
 */

/**
 * Render the main node tables structure
 * @param array $nodes Array of node IDs
 * @param array $astdb ASTDB data for node info
 * @param array $displaySettings Display configuration
 */
function renderNodeTables($nodes, $astdb, $displaySettings) {
    $isDetailed = ($displaySettings['Show_Detail'] == 1);
    
    echo "<div id=\"list_link\"></div>";
    
    echo "<center><table class=fxwidth>\n";
    foreach($nodes as $node) {
        $info = "Node not in database";
        if (isset($astdb[$node]) && isset($astdb[$node][1]) && isset($astdb[$node][2]) && isset($astdb[$node][3])) {
            $info = $astdb[$node][1] . ' ' . $astdb[$node][2] . ' ' . $astdb[$node][3];
        }

        $node_display_name = htmlspecialchars($node);
        $node_info_display = htmlspecialchars($info);
        $custom_node_url_base = 'URL_' . $node;

        if (isset(${$custom_node_url_base})) {
            $custom_url = ${$custom_node_url_base};
            $info_target_blank = "";
            if (substr($custom_url, -1) == ">") {
                $custom_url = substr_replace($custom_url, "", -1);
                $info_target_blank = "target=\"_blank\"";
            }
            $node_info_display = "<a href=\"" . htmlspecialchars($custom_url) . "\" $info_target_blank>" . htmlspecialchars($info) . "</a>";
        }

        echo "<tr><td>\n";
        renderSingleNodeTable($node, $node_display_name, $node_info_display, $isDetailed);
        echo "</td></tr>\n";
    }
    echo "</table></center>\n";
    echo "</div>\n";
}

/**
 * Render a single node table structure
 * @param string $node Node ID
 * @param string $node_display_name Display name for node
 * @param string $node_info_display Info display for node
 * @param bool $isDetailed Whether to show detailed view
 */
function renderSingleNodeTable($node, $node_display_name, $node_info_display, $isDetailed) {
    $table_id = "table_" . $node;
    $table_class = $isDetailed ? "gridtable" : "gridtable-large";
    
    echo "<table id=\"$table_id\" class=\"$table_class\">\n";
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th style=\"font-size:16px; background-color:#4682b4; color:black;\">$node_display_name - $node_info_display</th>\n";
    
    if ($isDetailed) {
        echo "<th>IP</th>\n";
    }
    echo "<th>Direction</th>\n";
    echo "<th>Elapsed</th>\n";
    if ($isDetailed) {
        echo "<th>Mode</th>\n";
        echo "<th>Last Keyed</th>\n";
    }
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";
    echo "<tr><td colspan=\"" . ($isDetailed ? "7" : "5") . "\">Loading node data...</td></tr>\n";
    echo "</tbody>\n";
    echo "</table>\n";
    echo "<br>\n";
}

/**
 * Render the detailed view spinner
 * @param bool $isDetailed Whether detailed view is enabled
 */
function renderDetailedSpinner($isDetailed) {
    if ($isDetailed) {
        print "<div id=\"spinny\"></div>";
    }
}
?>
