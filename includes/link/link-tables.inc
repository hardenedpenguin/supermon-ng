<?php
/**
 * Link.php Node Table Rendering
 * 
 * This file contains the exact node table generation logic
 * extracted from link.php for better organization.
 */

/**
 * Render the main node tables structure with the exact original logic
 * @param array $nodes Array of node IDs
 * @param array $astdb ASTDB data for node info
 * @param array $displaySettings Display configuration
 */
function renderNodeTables($nodes, $astdb, $displaySettings) {
    global $config;
    
    $isDetailed = ($displaySettings['Show_Detail'] == 1);
    
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
        
        $base_title_text = "Node";
        $node_link_html = $node_display_name;

        $is_private_or_hidden = ($info == "Node not in database") || (isset($config[$node]['hideNodeURL']) && $config[$node]['hideNodeURL'] == 1);

        if ($is_private_or_hidden) {
            $base_title_text = "Private Node";
            if (isset(${$custom_node_url_base})) {
                $custom_url_for_node = ${$custom_node_url_base};
                 if (substr($custom_url_for_node, -1) == ">") $custom_url_for_node = substr_replace($custom_url_for_node, "", -1);
                $node_link_html = "<a href=\"" . htmlspecialchars($custom_url_for_node) . "\" " . (strpos($node_info_display, "target=\"_blank\"") ? "target=\"_blank\"" : "") . ">$node_display_name</a>";
            }
        } else {
            $allstar_node_url = ($node < 2000) ? "" : "http://stats.allstarlink.org/nodeinfo.cgi?node=" . urlencode($node);
            if (!empty($allstar_node_url)) {
                $node_link_html = "<a href=\"" . htmlspecialchars($allstar_node_url) . "\" target=\"_blank\">$node_display_name</a>";
            } elseif (isset(${$custom_node_url_base})) {
                $custom_url_for_node = ${$custom_node_url_base};
                if (substr($custom_url_for_node, -1) == ">") $custom_url_for_node = substr_replace($custom_url_for_node, "", -1);
                $node_link_html = "<a href=\"" . htmlspecialchars($custom_url_for_node) . "\" " . (strpos($node_info_display, "target=\"_blank\"") ? "target=\"_blank\"" : "") . ">$node_display_name</a>";
            }
        }

        $title = "  $base_title_text $node_link_html => $node_info_display  ";
        
        $links_array = [];
        if (!$is_private_or_hidden) {
            if ($node >= 2000) {
                $bubbleChart = "http://stats.allstarlink.org/getstatus.cgi?" . urlencode($node);
                $links_array[] = "<a href=\"" . htmlspecialchars($bubbleChart) . "\" target=\"_blank\">Bubble Chart</a>";
            }
        }

        if (isset($config[$node]['lsnodes'])) {
            $links_array[] = "<a href=\"" . htmlspecialchars($config[$node]['lsnodes']) . "\" target=\"_blank\">lsNodes</a>";
        } elseif (isset($config[$node]['host']) && preg_match("/localhost|127\.0\.0\.1/", $config[$node]['host'] )) {
            $lsNodesChart = "/cgi-bin/lsnodes_web?node=" . urlencode($node);
            $links_array[] = "<a href=\"" . htmlspecialchars($lsNodesChart) . "\" target=\"_blank\">lsNodes</a>";
        }

        if (isset($config[$node]['listenlive'])) {
            $links_array[] = "<a href=\"" . htmlspecialchars($config[$node]['listenlive']) . "\" target=\"_blank\">Listen Live</a>";
        }
        if (isset($config[$node]['archive'])) {
            $links_array[] = "<a href=\"" . htmlspecialchars($config[$node]['archive']) . "\" target=\"_blank\">Archive</a>";
        }

        if (!empty($links_array)) {
            $title .= "<br>" . implode("  ", $links_array);
        }

        $colspan_waiting = $isDetailed ? 7 : 5;
        $table_class = $isDetailed ? 'gridtable' : 'gridtable-large';
?>
    <tr><td>
    <table class="<?php echo $table_class; ?>" id="table_<?php echo htmlspecialchars($node); ?>">
    <thead>
    <tr><th colspan="<?php echo $colspan_waiting; ?>"><i><?php echo $title; ?></i></th></tr>
    <tr>
        <th>  Node  </th>
        <th>Node Information</th>
        <?php if ($isDetailed): ?><th>Received</th><?php endif; ?>
        <th>Link</th>
        <th>Dir</th>
        <?php if ($isDetailed): ?><th>Connected</th><?php endif; ?>
        <th>Mode</th>
    </tr>
    </thead>
    <tbody>
    <tr><td colspan="<?php echo $colspan_waiting; ?>">   Waiting...</td></tr>
    </tbody>
    </table><br />
    </td></tr>
<?php
    }
    echo "</table></center>\n";
    echo "</div>\n";
}
?>