<?php
include("includes/session.inc");

include("user_files/global.inc");
include("includes/common.inc");
include_once("authusers.php");
include_once("authini.php");
include("includes/link/link-functions.inc");
include("includes/link/link-config.inc");
include("includes/link/link-ui.inc");
include("includes/link/link-javascript.inc");
include("includes/link/link-tables.inc");

// Initialize link page and get configuration
list($nodes, $config, $astdb, $displayPrefs, $parms) = initializeLinkPage();

// Extract display preferences
$Displayed_Nodes = $displayPrefs['Displayed_Nodes'];
$Display_Count = $displayPrefs['Display_Count'];
$Show_All = $displayPrefs['Show_All'];
$Show_Detail = $displayPrefs['Show_Detail'];

include("includes/header.inc");

$isDetailed = ($Show_Detail == 1);
$SUBMITTER   = $isDetailed ? "submit" : "submit-large";
$SUBMIT_SIZE = $isDetailed ? "submit" : "submit-large";
$TEXT_SIZE   = $isDetailed ? "text-normal" : "text-large";


// Render welcome message
renderWelcomeMessage();



?>

<?php
// Render monitoring JavaScript
renderMonitoringJavaScript($parms, $displayPrefs);
?>

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
