<?php
include("includes/session.inc");

include("user_files/global.inc");
include("includes/common.inc");
include_once("authusers.php");
include_once("authini.php");

$raw_nodes_param = trim(strip_tags($_GET['nodes'] ?? ''));
if (empty($raw_nodes_param)) {
    die ("Please provide a properly formated URI. (ie link.php?nodes=1234 | link.php?nodes=1234,2345)");
}
$passedNodes = array_filter(array_map('trim', explode(',', $raw_nodes_param)), 'strlen');
if (empty($passedNodes)) {
    die ("Please provide a properly formated URI. (ie link.php?nodes=1234 | link.php?nodes=1234,2345) - No valid nodes found after parsing.");
}
$parms = implode(',', $passedNodes);

$expiretime = 2147483645;

if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = "";
}

$cookie_data = $_COOKIE['display-data'] ?? [];

$Displayed_Nodes = isset($cookie_data['number-displayed']) ? htmlspecialchars($cookie_data['number-displayed']) : null;
$Display_Count   = isset($cookie_data['show-number']) ? htmlspecialchars($cookie_data['show-number']) : null;
$Show_All        = isset($cookie_data['show-all']) ? htmlspecialchars($cookie_data['show-all']) : null;
$Show_Detail     = isset($cookie_data['show-detailed']) ? htmlspecialchars($cookie_data['show-detailed']) : null;

if ($Displayed_Nodes === null || $Displayed_Nodes === "0") {
    $Displayed_Nodes = "999";
}
if ($Display_Count === null) {
    $Display_Count = 0;
}
if ($Show_All === null) {
    $Show_All = "1";
}
if ($Show_Detail === null) {
    $Show_Detail = "1";
}
setcookie("display-data[show-detailed]", $Show_Detail, $expiretime);

$SUPINI = get_ini_name($_SESSION['user']);

if (!file_exists($SUPINI)) {
    die("Couldn't load $SUPINI file.\n");
}
$config = parse_ini_file($SUPINI, true);

$nodes_check = array();
foreach ($passedNodes as $node_item) {
    if (isset($config[$node_item])) {
        $nodes_check[] = $node_item;
    }
}
if (empty($nodes_check)) {
    die("None of the provided nodes were found in the configuration file: $SUPINI");
}

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

include("includes/header.inc");

$nodes = $nodes_check;

$isDetailed = ($Show_Detail == 1);
$SUBMITTER   = $isDetailed ? "submit" : "submit-large";
$SUBMIT_SIZE = $isDetailed ? "submit" : "submit-large";
$TEXT_SIZE   = $isDetailed ? "text-normal" : "text-large";


if (!($_SESSION['sm61loggedin'] ?? false)) {
    if (isset($WELCOME_MSG)) {
        print $WELCOME_MSG;
    }
} else {
    if (isset($WELCOME_MSG_LOGGED)) {
        print $WELCOME_MSG_LOGGED;
    }
}

function print_auth_button($auth_perm, $css_class, $value, $id = "", $extra_attrs = "", $onclick_action = "") {
    if (get_user_auth($auth_perm)) {
        $id_attr = $id ? "id=\"$id\"" : "";
        $onclick_attr = $onclick_action ? "OnClick=\"$onclick_action\"" : "";
        $extra_attrs_spaced = ($extra_attrs && substr($extra_attrs, 0, 1) !== ' ') ? " " . $extra_attrs : $extra_attrs;
        print "<input type=\"button\" class=\"$css_class\" value=\"$value\" $id_attr{$extra_attrs_spaced} $onclick_attr>";
    }
}

?>

<script>
function toTop() {
    window.scrollTo(0, 0);
}
</script>

<script type="text/javascript">
$(document).ready(function() {
  if(typeof(EventSource)!=="undefined") {
    var source=new EventSource("server.php?nodes=<?php echo $parms; ?>");

    source.addEventListener('nodes', function(event) {
        var tabledata = JSON.parse(event.data);
        for (var localNode in tabledata) {
            if (tabledata[localNode].status && tabledata[localNode].status.includes("Node") && tabledata[localNode].status.includes("is not in")) {
                var tableID = 'table_' + tabledata[localNode].node;
                var colspan = (<?php echo (int) $Show_Detail; ?> == 1) ? 7 : 5;
                $('#' + tableID + ' tbody:first').html(`<tr><td colspan="${colspan}">${tabledata[localNode].status}</td></tr>`);
                continue;
            }

            var tablehtml = '';

            var total_nodes = 0;
            var shown_nodes = 0;
            var ndisp = <?php echo (int) $Displayed_Nodes; ?>;
            ndisp++;
            var sdisp = <?php echo (int) $Display_Count; ?>;
            var sall = <?php echo (int) $Show_All; ?>;
            var sdetail = <?php echo (int) $Show_Detail; ?>;

            var localNodeDataFromServer = tabledata[localNode];
            var cos_keyed = localNodeDataFromServer.cos_keyed || 0;
            var tx_keyed = localNodeDataFromServer.tx_keyed || 0;

            let headerStatusTextBase, headerCssClass, headerColspan2 = 1, headerColspan3 = 5;

            if (cos_keyed == 0) {
                if (tx_keyed == 0) { headerStatusTextBase = 'Idle'; headerCssClass = 'gColor'; }
                else { headerStatusTextBase = 'PTT-Keyed'; headerCssClass = 'tColor'; }
            } else {
                if (tx_keyed == 0) { headerStatusTextBase = 'COS-Detected'; headerCssClass = 'lColor'; }
                else {
                    headerStatusTextBase = 'COS-Detected and PTT-Keyed (Full-Duplex)'; headerCssClass = 'bColor';
                    headerColspan2 = 2; headerColspan3 = 4;
                }
            }
            
            let headerStatusDetails = '';
            if (localNodeDataFromServer.cpu_temp) {
                headerStatusDetails = `<br>${localNodeDataFromServer.ALERT || ''}<br>${localNodeDataFromServer.WX || ''}<br>CPU=${localNodeDataFromServer.cpu_temp} - ${localNodeDataFromServer.cpu_up || ''}<br>${localNodeDataFromServer.cpu_load || ''}<br>${localNodeDataFromServer.DISK || ''}`;
                if (headerStatusTextBase === 'PTT-Keyed') headerStatusTextBase = 'PTT-KEYED';
                if (headerStatusTextBase === 'COS-Detected') headerStatusTextBase = 'COS-DETECTED';
                if (headerStatusTextBase === 'COS-Detected and PTT-Keyed (Full-Duplex)') headerStatusTextBase = 'COS-Detected and PTT-Keyed (Full Duplex)';
            }
            
            tablehtml += `<tr class="${headerCssClass}"><td colspan="1" align="center">${localNode}</td><td colspan="${headerColspan2}" align="center"><b>${headerStatusTextBase}${headerStatusDetails}</b></td><td colspan="${headerColspan3}"></td></tr>`;

            if (localNodeDataFromServer.remote_nodes && Array.isArray(localNodeDataFromServer.remote_nodes)) {
                for (var row in localNodeDataFromServer.remote_nodes) {
                    let nodeData = localNodeDataFromServer.remote_nodes[row];
                    if (nodeData.info === "NO CONNECTION") {
                        tablehtml += '<tr><td colspan="' + (sdetail ? 7 : 5) + '">     No Connections.</td></tr>';
                    } else {
                        let nodeNum = nodeData.node;
                        if (nodeNum != 1 || (nodeData.info && nodeData.info !== "NO CONNECTION")) {
                            total_nodes++;
                            if (sall == 1 || (row < ndisp && (nodeData.last_keyed != "Never" || total_nodes < 2))) {
                                shown_nodes++;

                                var rowClass = '';
                                if (nodeData.keyed == 'yes') {
                                    rowClass = (nodeData.mode == 'R') ? 'rxkColor' : 'rColor';
                                } else if (nodeData.mode == 'C') {
                                    rowClass = 'cColor';
                                } else if (nodeData.mode == 'R') {
                                    rowClass = 'rxColor';
                                }
                                tablehtml += rowClass ? `<tr class="${rowClass}">` : '<tr>';

                                tablehtml += `<td id="t${localNode}c0r${row}" align="center" class="nodeNum" onclick="toTop()">${nodeData.node}</td>`;
                                tablehtml += `<td>${nodeData.info != "" ? nodeData.info : (nodeData.ip || '')}</td>`;

                                if (sdetail == 1) {
                                    tablehtml += `<td align="center" id="lkey${localNode}_${row}">${nodeData.last_keyed}</td>`;
                                }
                                tablehtml += `<td align="center">${nodeData.link || 'n/a'}</td>`;
                                tablehtml += `<td align="center">${nodeData.direction || 'n/a'}</td>`;

                                if (sdetail == 1) {
                                    tablehtml += `<td align="right" id="elap${localNode}_${row}">${nodeData.elapsed}</td>`;
                                }

                                var modeText = nodeData.mode;
                                if (nodeData.mode == 'R') modeText = 'RX Only';
                                else if (nodeData.mode == 'T') modeText = 'Transceive';
                                else if (nodeData.mode == 'C') modeText = 'Connecting';
                                else if (nodeData.mode == 'Echolink') modeText = 'Echolink';
                                else if (nodeData.mode == 'Local RX') modeText = 'Local RX';
                                tablehtml += `<td align="center">${modeText || 'n/a'}</td>`;
                                tablehtml += '</tr>';
                            }
                        }
                    }
                }
            } else if (localNodeDataFromServer.info === "NO CONNECTION") {
                 tablehtml += '<tr><td colspan="' + (sdetail ? 7 : 5) + '">     No Connections.</td></tr>';
            }

            if (sdisp === 1 && total_nodes >= shown_nodes && total_nodes > 1) {
                let countText = (shown_nodes == total_nodes) ?
                    `${total_nodes} nodes connected` :
                    `${shown_nodes} shown of ${total_nodes} nodes connected`;
                tablehtml += `<tr><td colspan="2">    ${countText}`;
                tablehtml += '    <a href="#" onclick="toTop()">^^^</a></td>' + (sdetail ? '<td colspan="5">' : '<td colspan="3">') + '</td></tr>';
            }
            $('#table_' + localNode + ' tbody:first').html(tablehtml);
        }
    });

    if (<?php echo (int) $Show_Detail; ?> == 1) {
        var spinny = "*";
        source.addEventListener('nodetimes', function(event) {
            var tabledata = JSON.parse(event.data);
            for (var localNode in tabledata) {
                if (tabledata[localNode].remote_nodes && Array.isArray(tabledata[localNode].remote_nodes)) {
                    for (var row in tabledata[localNode].remote_nodes) {
                        $('#lkey' + localNode + '_' + row).text(tabledata[localNode].remote_nodes[row].last_keyed);
                        $('#elap' + localNode + '_' + row).text(tabledata[localNode].remote_nodes[row].elapsed);
                    }
                }
            }
            const spinChars = ["*", "|", "/", "-", "\\"];
            let currentSpinIndex = spinChars.indexOf(spinny);
            spinny = spinChars[(currentSpinIndex + 1) % spinChars.length];
            $('#spinny').html(spinny);
        });
    }

    source.addEventListener('connection', function(event) {
        var statusdata = JSON.parse(event.data);
        var tableID = 'table_' + statusdata.node;
        var colspan = (<?php echo (int) $Show_Detail; ?> == 1) ? 7 : 5;
        $('#' + tableID + ' tbody:first').html(`<tr><td colspan="${colspan}">${statusdata.status}</td></tr>`);
    });

    source.addEventListener('error', function(event) {
        try {
            var errordata = JSON.parse(event.data);
            if (errordata.node) {
                var tableID = 'table_' + errordata.node;
                var colspan = (<?php echo (int) $Show_Detail; ?> == 1) ? 7 : 5;
                $('#' + tableID + ' tbody:first').html(`<tr><td colspan="${colspan}" class="error-inline">Error: ${errordata.status}</td></tr>`);
            } else if (errordata.status) {
                 var firstNodeTable = $('table[id^="table_"]:first');
                 if (firstNodeTable.length) {
                    var colspan = (<?php echo (int) $Show_Detail; ?> == 1) ? 7 : 5;
                    firstNodeTable.find('tbody:first').html(`<tr><td colspan="${colspan}" class="error-inline">Error: ${errordata.status}</td></tr>`);
                 } else {
                     $("#list_link").html("Error: " + errordata.status);
                 }
            }
        } catch (e) {
        }
    });

  } else {
    $("#list_link").html("Sorry, your browser does not support server-sent events...");
  }
});
</script>

<?php
if (($_SESSION['sm61loggedin'] ?? false) === true) {
?>
    <div id="connect_form">
    <center>
<?php
    if (count($nodes) > 0) {
        if (count($nodes) > 1) {
            print "<select id=\"localnode\" class=\"$SUBMIT_SIZE\">";
            foreach ($nodes as $node) {
                $info = (isset($astdb[$node]) && isset($astdb[$node][1]) && isset($astdb[$node][2]) && isset($astdb[$node][3])) ? ($astdb[$node][1] . ' ' . $astdb[$node][2] . ' ' . $astdb[$node][3]) : "Node not in database";
                print "<option class=\"$SUBMIT_SIZE\" value=\"$node\"> $node => $info </option>";
            }
            print "</select>";
        } else {
            print " <input class=\"$SUBMIT_SIZE\" type=\"hidden\" id=\"localnode\" value=\"{$nodes[0]}\">";
        }

        if (get_user_auth("PERMUSER")) {
            $perm_input_class = $isDetailed ? "perm-input-detailed" : "perm-input-large";
            $perm_label_class = $isDetailed ? "perm-label-detailed" : "perm-label-large";
            if (!$isDetailed) print "<br>";
            print "<input class=\"$perm_input_class\" type=\"text\" id=\"node\" name=\"node\">";
            print "<label class=\"$perm_label_class\"> Perm <input class=\"perm\" type=\"checkbox\" name=\"perm\"> </label><br>";
        } else {
             print "<input type=\"text\" id=\"node\" name=\"node\" class=\"$TEXT_SIZE\" placeholder=\"Node to connect/DTMF\">";
             if (!$isDetailed) print "<br>";
        }

        print_auth_button("CONNECTUSER", $SUBMIT_SIZE, "Connect", "connect", "class=\"button-margin-top\"");
        print_auth_button("DISCUSER", $SUBMIT_SIZE, "Disconnect", "disconnect");
        print_auth_button("MONUSER", $SUBMIT_SIZE, "Monitor", "monitor");
        print_auth_button("LMONUSER", $SUBMIT_SIZE, "Local Monitor", "localmonitor");

        if (!$isDetailed) print "<br>";

        print_auth_button("DTMFUSER", $SUBMITTER, "DTMF", "dtmf");
        print_auth_button("ASTLKUSER", $SUBMIT_SIZE, "Lookup", "astlookup");

        if ($isDetailed) {
            print_auth_button("RSTATUSER", "submit", "Rpt Stats", "rptstats");
            print_auth_button("BUBLUSER", "submit", "Bubble", "map");
        }

        print_auth_button("CTRLUSER", $SUBMITTER, "Control", "controlpanel");
        print_auth_button("FAVUSER", $SUBMIT_SIZE, "Favorites", "favoritespanel", "class=\"button-margin-bottom\"");
?>
        <script>
            function OpenActiveNodes() { window.open('http://stats.allstarlink.org'); }
            function OpenAllNodes() { window.open('https://www.allstarlink.org/nodelist'); }
            function OpenHelp() { window.open('https://wiki.allstarlink.org/wiki/Category:How_to'); }
            function OpenConfigEditor() { window.open('configeditor.php'); }
            function OpenWiki() { window.open('http://wiki.allstarlink.org'); }
        </script>
<?php
        if ($isDetailed) {
            echo "<hr class='button-separator'>";
            print_auth_button("CFGEDUSER", $SUBMITTER, "Configuration Editor", "", "class=\"button-margin-top\"", "OpenConfigEditor()");
            print_auth_button("ASTRELUSER", $SUBMITTER, "Iax/Rpt/DP RELOAD", "astreload");
            print_auth_button("ASTSTRUSER", $SUBMITTER, "AST START", "astaron");
            print_auth_button("ASTSTPUSER", $SUBMITTER, "AST STOP", "astaroff");
            print_auth_button("FSTRESUSER", $SUBMITTER, "RESTART", "fastrestart");
            print_auth_button("RBTUSER", $SUBMITTER, "Server REBOOT", "reboot", "class=\"button-margin-bottom\"");
            print "<br>";
            print_auth_button("HWTOUSER", "submit", "AllStar How To's", "", "class=\"button-margin-top\"", "OpenHelp()");
            print_auth_button("WIKIUSER", "submit", "AllStar Wiki", "", "", "OpenWiki()");
            print_auth_button("CSTATUSER", "submit", "CPU Status", "cpustats");
            print_auth_button("ASTATUSER", "submit", "AllStar Status", "stats");
            if ($EXTN ?? false) {
                print_auth_button("EXNUSER", "submit", "Registry", "extnodes");
            }
            print_auth_button("NINFUSER", "submit", "Node Info", "astnodes");
            print_auth_button("ACTNUSER", "submit", "Active Nodes", "", "", "OpenActiveNodes()");
            print_auth_button("ALLNUSER", "submit", "All Nodes", "", "class=\"button-margin-bottom\"", "OpenAllNodes()");
            if (!empty($DATABASE_TXT)) {
                 print_auth_button("DBTUSER", "submit", "Database", "database", "class=\"button-margin-bottom\"");
            }
            print "<br>";
            print_auth_button("GPIOUSER", $SUBMITTER, "GPIO", "openpigpio", "class=\"button-margin-top\"");
            print_auth_button("LLOGUSER", "submit", "Linux Log", "linuxlog");
            print_auth_button("ASTLUSER", "submit", "AST Log", "astlog");
            if ($IRLPLOG ?? false) {
                print_auth_button("IRLPLOGUSER", "submit", "IRLP Log", "irlplog");
            }
            print_auth_button("WLOGUSER", "submit", "Web Access Log", "webacclog");
            print_auth_button("WERRUSER", "submit", "Web Error Log", "weberrlog");
        }

        print_auth_button("BANUSER", $SUBMIT_SIZE, "Access List", "openbanallow", "class=\"button-margin-bottom\"");
?>
    </center>
    </div>
<?php
    }
}

echo "<div id=\"list_link\"></div>";

print "<p class=\"button-container\">";

print "<input type=\"button\" class=\"$SUBMIT_SIZE\" Value=\"Display Configuration\" onclick=\"window.open('display-config.php','DisplayConfiguration','status=no,location=no,toolbar=no,width=500,height=600,left=100,top=100')\">";

if (!empty($DVM_URL)) {
    $dvm_url_safe = htmlspecialchars($DVM_URL);
    print "  <input type=\"button\" class=\"$SUBMIT_SIZE\" Value=\"Digital Dashboard\" onclick=\"window.open('{$dvm_url_safe}','DigitalDashboard','status=no,location=no,toolbar=no,width=960,height=850,left=100,top=100')\">";
}

if (($_SESSION['sm61loggedin'] ?? false) && get_user_auth("SYSINFUSER")) {
    $WIDTH = $isDetailed ? 950 : 650;
    $HEIGHT = $isDetailed ? 550 : 750;
    print "  <input type=\"button\" class=\"$SUBMITTER\" Value=\"System Info\" onclick=\"window.open('system-info.php','SystemInfo','status=no,location=no,toolbar=yes,width=$WIDTH,height=$HEIGHT,left=100,top=100')\">";
}
print "</p>";

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
        <th>  Node  </th>
        <th>Node Information</th>
        <?php if ($isDetailed): ?><th>Received</th><?php endif; ?>
        <th>Link</th>
        <th>Dir</th>
        <?php if ($isDetailed): ?><th>Connected</th><?php endif; ?>
        <th>Mode</th>
    </tr>
    </thead>
    <tbody>
    <tr><td colspan="<?php echo $colspan_waiting; ?>">   Waiting...</td></tr>
    </tbody>
    </table><br />
    </td></tr>
<?php
}
?>
</table></center>
</div>

<?php
if (filter_var($HAMCLOCK_ENABLED ?? false, FILTER_VALIDATE_BOOLEAN)) {
    /**
     * Checks if a given IP address is in a private (internal) or reserved range.
     * @param string $ip The IP address to check.
     * @return bool True if the IP is internal, false otherwise.
     */
    function is_internal_ip($ip) {
        // The loopback address is always internal.
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }
        
        // Use PHP's built-in filter to check if the IP is NOT a public IP.
        // The FILTER_FLAG_NO_PRIV_RANGE and FILTER_FLAG_NO_RES_RANGE flags cause filter_var to return false
        // for private or reserved IPs. We return the opposite (!).
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    // Get the connecting client's IP address
    $client_ip = $_SERVER['REMOTE_ADDR'];
    $selected_hamclock_url = '';

    // Check if the client IP is internal and select the appropriate URL
    if (is_internal_ip($client_ip)) {
        if (!empty($HAMCLOCK_URL_INTERNAL)) {
            $selected_hamclock_url = $HAMCLOCK_URL_INTERNAL;
        }
    } else {
        if (!empty($HAMCLOCK_URL_EXTERNAL)) {
            $selected_hamclock_url = $HAMCLOCK_URL_EXTERNAL;
        }
    }

    // Only display the iframe if a valid URL has been selected
    if (!empty($selected_hamclock_url)) {
?>
    <div class="centered-margin-bottom">
        <iframe src="<?php echo htmlspecialchars($selected_hamclock_url); ?>" width="800" height="480" class="iframe-borderless"></iframe>
    </div>
<?php
    }
}

if ($isDetailed) {
    print "<div id=\"spinny\"></div>";
}

$user_ini_file = htmlspecialchars(get_ini_name($_SESSION['user']));
$remote_addr = htmlspecialchars($_SERVER['REMOTE_ADDR']);

if (empty($_SESSION['user'])) {
    print "<p class=\"$TEXT_SIZE\"><i>You are not logged in from IP-<b>{$remote_addr}</b> using ini file - '<b>{$user_ini_file}</b>'</i></p>";
} else {
    $current_user = htmlspecialchars($_SESSION["user"]);
    print "<p class=\"$TEXT_SIZE\"><i>You are logged as <b>{$current_user}</b> from IP-<b>{$remote_addr}</b> using ini file - '<b>{$user_ini_file}</b>'</i></p>";
}

include "includes/footer.inc";
?>
