<?php
/**
 * Link.php UI Rendering Components
 * 
 * This file contains UI rendering functions extracted from link.php
 * for better organization and maintainability.
 */

/**
 * Render welcome message based on login status
 */
function renderWelcomeMessage() {
    global $WELCOME_MSG, $WELCOME_MSG_LOGGED;
    
    if (!($_SESSION['sm61loggedin'] ?? false)) {
        if (isset($WELCOME_MSG)) {
            print $WELCOME_MSG;
        }
    } else {
        if (isset($WELCOME_MSG_LOGGED)) {
            print $WELCOME_MSG_LOGGED;
        }
    }
}

/**
 * Render the main control panel with node dropdown and buttons
 * @param array $nodes Array of available nodes
 * @param array $astdb ASTDB data for node info
 * @param array $displaySettings Display configuration
 */
function renderControlPanel($nodes, $astdb, $displaySettings) {
    global $EXTN, $DATABASE_TXT, $IRLPLOG;
    
    $isDetailed = ($displaySettings['Show_Detail'] == 1);
    $SUBMITTER = $isDetailed ? "submit" : "submit-large";
    $SUBMIT_SIZE = $isDetailed ? "submit" : "submit-large";
    $TEXT_SIZE = $isDetailed ? "text-normal" : "text-large";
    
    ?>
    <div id="connect_form">
    <center>
    <?php
    if (count($nodes) > 0) {
        // Multiple node dropdown
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

        // Node input and permission controls
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

        // Primary control buttons
        print_auth_button("CONNECTUSER", $SUBMIT_SIZE, "Connect", "connect", "class=\"button-margin-top\"");
        print_auth_button("DISCUSER", $SUBMIT_SIZE, "Disconnect", "disconnect");
        print_auth_button("MONUSER", $SUBMIT_SIZE, "Monitor", "monitor");
        print_auth_button("LMONUSER", $SUBMIT_SIZE, "Local Monitor", "localmonitor");

        if (!$isDetailed) print "<br>";

        print_auth_button("DTMFUSER", $SUBMITTER, "DTMF", "dtmf");
        print_auth_button("ASTLKUSER", $SUBMIT_SIZE, "Lookup", "astlookup");

        if ($isDetailed) {
            print_auth_button("RSTATUSER", "submit", "Rpt Stats", "rptstats");
        }

        print_auth_button("CTRLUSER", $SUBMITTER, "Control", "controlpanel");
        print_auth_button("FAVUSER", $SUBMIT_SIZE, "Favorites", "favoritespanel");
        print_auth_button("FAVUSER", $SUBMIT_SIZE, "Add Favorite", "", "", "OpenAddFavorite()");
        print_auth_button("FAVUSER", $SUBMIT_SIZE, "Delete Favorite", "", "class=\"button-margin-bottom\"", "OpenDeleteFavorite()");
        
        // JavaScript for popup windows
        ?>
        <script>
        function OpenFavoritesPanel() {
            window.open('favorites.php', 'FavoritesPanel', 'status=no,location=no,toolbar=no,width=500,height=600,left=100,top=100');
        }
        
        function OpenConfigEditor() {
            window.open('configeditor.php', 'ConfigEditor', 'status=no,location=no,toolbar=no,width=800,height=700,left=100,top=100');
        }
        
        function OpenHelp() {
            window.open('https://allstarlink.org/howto.html', 'AllStarHelp', 'status=no,location=no,toolbar=yes,width=800,height=600,left=100,top=100');
        }
        
        function OpenWiki() {
            window.open('https://wiki.allstarlink.org', 'AllStarWiki', 'status=no,location=no,toolbar=yes,width=800,height=600,left=100,top=100');
        }
        
        function OpenActiveNodes() {
            window.open('https://stats.allstarlink.org/', 'ActiveNodes', 'status=no,location=no,toolbar=yes,width=1200,height=800,left=100,top=100');
        }
        
        function OpenAllNodes() {
            window.open('https://www.allstarlink.org/nodelist/', 'AllNodes', 'status=no,location=no,toolbar=yes,width=1200,height=800,left=100,top=100');
        }
        
        function OpenAddFavorite() {
            const nodeInput = $('#node').val();
            if (!nodeInput || nodeInput.trim() === '') {
                alert('Please enter a node number in the input box first.');
                return;
            }
            window.open('addfavorite.php?node=' + encodeURIComponent(nodeInput), 'AddFavorite', 'status=no,location=no,toolbar=no,width=600,height=500,left=100,top=100');
        }
        
        function OpenDeleteFavorite() {
            window.open('deletefavorite.php', 'DeleteFavorite', 'status=no,location=no,toolbar=no,width=600,height=500,left=100,top=100');
        }
        </script>
        <?php
        
        // Detailed view additional buttons
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
            
            global $EXTN;
            if ($EXTN ?? false) {
                print_auth_button("EXNUSER", "submit", "Registry", "extnodes");
            }
            print_auth_button("NINFUSER", "submit", "Node Info", "astnodes");
            print_auth_button("ACTNUSER", "submit", "Active Nodes", "", "", "OpenActiveNodes()");
            print_auth_button("ALLNUSER", "submit", "All Nodes", "", "class=\"button-margin-bottom\"", "OpenAllNodes()");
            
            global $DATABASE_TXT;
            if (!empty($DATABASE_TXT)) {
                 print_auth_button("DBTUSER", "submit", "Database", "database", "class=\"button-margin-bottom\"");
            }
            print "<br>";
            print_auth_button("GPIOUSER", $SUBMITTER, "GPIO", "openpigpio", "class=\"button-margin-top\"");
            print_auth_button("LLOGUSER", "submit", "Linux Log", "linuxlog");
            print_auth_button("ASTLUSER", "submit", "AST Log", "astlog");
            
            global $IRLPLOG;
            if ($IRLPLOG ?? false) {
                print_auth_button("IRLPUSER", "submit", "IRLP Log", "irlplog");
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

/**
 * Render bottom utility buttons (Display Configuration, Digital Dashboard, System Info)
 * @param array $displaySettings Display configuration
 */
function renderBottomButtons($displaySettings) {
    global $DVM_URL;
    
    $isDetailed = ($displaySettings['Show_Detail'] == 1);
    $SUBMITTER = $isDetailed ? "submit" : "submit-large";
    $SUBMIT_SIZE = $isDetailed ? "submit" : "submit-large";
    
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
}

/**
 * Render HamClock iframe if enabled
 */
function renderHamClock() {
    global $HAMCLOCK_ENABLED, $HAMCLOCK_URL_INTERNAL, $HAMCLOCK_URL_EXTERNAL;
    
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
}

/**
 * Render user information footer
 * @param array $displaySettings Display configuration
 */
function renderUserInfo($displaySettings) {
    $isDetailed = ($displaySettings['Show_Detail'] == 1);
    $TEXT_SIZE = $isDetailed ? "text-normal" : "text-large";
    
    $user_ini_file = htmlspecialchars(get_ini_name($_SESSION['user']));
    $remote_addr = htmlspecialchars($_SERVER['REMOTE_ADDR']);

    if (empty($_SESSION['user'])) {
        print "<p class=\"$TEXT_SIZE\"><i>You are not logged in from IP-<b>{$remote_addr}</b> using ini file - '<b>{$user_ini_file}</b>'</i></p>";
    } else {
        $current_user = htmlspecialchars($_SESSION["user"]);
        print "<p class=\"$TEXT_SIZE\"><i>You are logged as <b>{$current_user}</b> from IP-<b>{$remote_addr}</b> using ini file - '<b>{$user_ini_file}</b>'</i></p>";
    }
}
?>
