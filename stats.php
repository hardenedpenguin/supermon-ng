<?php

include("session.inc");
include("amifunctions.inc");
include("common.inc");
include("authusers.php");
include("authini.php");

?>
<html>
<head>
    <title>AllStar Status</title>
    <link rel="stylesheet" type="text/css" href="supermon-ng.css">
    <style>
        body {
             color: white;
             padding: 10px;
             background-color: #000000 !important;
        }
    </style>
</head>
<body>
<pre class="ast-status-pre">
<?php
    if (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && function_exists('get_user_auth') && get_user_auth("ASTATUSER")) {

        $SUPINI = get_ini_name($_SESSION['user']);

        if (!file_exists($SUPINI)) {
            die("<span class='ast-status-error-msg'>ERROR:</span> Couldn't load <span class='ast-status-highlight'>$SUPINI</span> ini file.\n");
        }

        $config = parse_ini_file($SUPINI, true);

        $node = isset($_GET['node']) ? trim(strip_tags($_GET['node'])) : null;

        if (empty($node)) {
             die("<span class='ast-status-error-msg'>ERROR:</span> 'node' parameter is missing in the URL.");
        }
        if (!isset($config[$node])) {
             die("<span class='ast-status-error-msg'>ERROR:</span> Node <span class='nodeNum'>$node</span> is not in <span class='ast-status-highlight'>$SUPINI</span> file.");
        }

        if (!class_exists('SimpleAmiClient')) {
            die("<span class='ast-status-error-msg'>ERROR:</span> SimpleAmiClient class not found. Ensure amifunctions.inc provides it.");
        }

        $fp = SimpleAmiClient::connect($config[$node]['host']);
        if ($fp === false) {
            die("<span class='ast-status-error-msg'>ERROR:</span> Could not connect to Asterisk Manager on host <span class='ast-status-highlight'>{$config[$node]['host']}</span>.");
        }

        $loginSuccess = SimpleAmiClient::login($fp, $config[$node]['user'], $config[$node]['passwd']);
        if ($loginSuccess === false) {
            SimpleAmiClient::logoff($fp);
            die("<span class='ast-status-error-msg'>ERROR:</span> Could not login to Asterisk Manager using user <span class='ast-status-highlight'>{$config[$node]['user']}</span>. Check credentials and manager.conf permissions.");
        }

        page_header();
        show_all_nodes($fp);
        show_peers($fp);
        show_channels($fp);
        show_netstats($fp);

        SimpleAmiClient::logoff($fp);

    } else {
        echo ("<h3 class='error-message'>ERROR: You Must login and have ASTATUSER permission to use this function!</h3>");
    }
?>
</pre>
</body>
</html>

<?php

// Helper function to remove "Output: " prefix from lines of a string
function clean_ami_output_for_display($raw_output) {
    if ($raw_output === null || trim($raw_output) === '') {
        return $raw_output;
    }
    $lines = explode("\n", $raw_output);
    $cleaned_lines = [];
    $prefix = "Output: ";
    foreach ($lines as $line) {
        if (strpos($line, $prefix) === 0) {
            $cleaned_lines[] = substr($line, strlen($prefix));
        } else {
            $cleaned_lines[] = $line;
        }
    }
    return implode("\n", $cleaned_lines);
}

function page_header()
{
    global $HOSTNAME, $AWK, $DATE;

    $HOSTNAME_CMD = isset($HOSTNAME) ? $HOSTNAME : 'hostname';
    $AWK_CMD = isset($AWK) ? $AWK : 'awk';
    $DATE_CMD = isset($DATE) ? $DATE : 'date';

    echo "<span class='ast-status-header-line'>#################################################################</span>\n";
    $host = trim(`$HOSTNAME_CMD | $AWK_CMD -F. '{printf ("%s", $1);}' 2>/dev/null`);
    $date = trim(`$DATE_CMD 2>/dev/null`);
    echo " <span class='ast-status-highlight'>" . htmlspecialchars($host) . "</span> AllStar Status: <span class='ast-status-highlight'>" . htmlspecialchars($date) . "</span>\n";
    echo "<span class='ast-status-header-line'>#################################################################</span>\n";
    echo "\n";
}

function show_all_nodes($fp)
{
    global $TAIL, $HEAD, $GREP, $SED;

    $TAIL_CMD = isset($TAIL) ? $TAIL : '/usr/bin/tail';
    $HEAD_CMD = isset($HEAD) ? $HEAD : '/usr/bin/head';
    $GREP_CMD = isset($GREP) ? $GREP : '/bin/grep';
    $SED_CMD = isset($SED) ? $SED : '/bin/sed';
    $ECHO_CMD = '/bin/echo';

    $nodes_output = SimpleAmiClient::command($fp, "rpt localnodes");

    if ($nodes_output === false) {
        echo "<span class='ast-status-error-msg'>Error:</span> Failed to execute 'rpt localnodes' command.\n";
        return;
    }
    if (trim($nodes_output) === '') {
         echo "<span class='ast-status-none-indicator'>No local nodes reported by Asterisk.</span>\n";
         return;
    }

    $nodelist = explode("\n", $nodes_output);
    $node_count = count($nodelist);

    $processed_node_count = 0;
    for ($i = 0; $i < $node_count; $i++) {
        $line = $nodelist[$i];
        $node_num_raw_candidate = $line;

        $prefix = "Output: "; // This prefix is for parsing node numbers from rpt localnodes output lines
        if (strpos($line, $prefix) === 0) {
            $node_num_raw_candidate = substr($line, strlen($prefix));
        }

        $node_num_raw = trim($node_num_raw_candidate);

        if (empty($node_num_raw) || $node_num_raw === "Node" || $node_num_raw === "----") {
             continue;
        }

        if (!ctype_digit($node_num_raw)) {
            continue;
        }

        $processed_node_count++;
        $node_num = $node_num_raw;

        $AMI1 = SimpleAmiClient::command($fp, "rpt xnode $node_num");
        if ($AMI1 === false) {
             echo "Node <span class='nodeNum'>$node_num</span>: <span class='ast-status-error-msg'>Error retrieving xnode info.</span>\n\n";
             continue;
         }
         if (trim($AMI1) === '') {
             echo "Node <span class='nodeNum'>$node_num</span>: <span class='ast-status-none-indicator'>No xnode info returned.</span>\n\n";
             continue;
         }

        $cmd_cnodes3 = "$ECHO_CMD -n " . escapeshellarg($AMI1) . " | $GREP_CMD \"^RPT_ALINKS\" | $SED_CMD 's/,/: /' | $SED_CMD 's/[a-zA-Z\=\_]//g'";
        $CNODES3 = trim(`$cmd_cnodes3 2>&1`);
        echo "Node <span class='nodeNum'>$node_num</span> connections => <span class='ast-status-highlight'>" . htmlspecialchars($CNODES3) . "</span>\n";

        echo "\n<span class='ast-status-section-title'>************************* CONNECTED NODES *************************</span>\n";

        $cmd_n3 = "$ECHO_CMD -n " . escapeshellarg($AMI1) . " | $TAIL_CMD --lines=+3 | $HEAD_CMD --lines=1";
        $N3 = trim(`$cmd_n3 2>&1`);
        
        // Strip "Output: " prefix if present from the single line $N3 before parsing
        $output_prefix_to_strip = "Output: ";
        if (strpos($N3, $output_prefix_to_strip) === 0) {
            $N3 = substr($N3, strlen($output_prefix_to_strip));
        }
        
        $res = explode(", ", $N3);
        $CNODES2 = count($res);
        $tmp = isset($res[0]) ? trim($res[0]) : '';

        if ("$tmp" != "<NONE>" && !empty($tmp) && $CNODES2 > 0) {
            printf(" <span class='ast-status-count'>%3s</span> node(s) total:\n     ", $CNODES2);
            $k = 0;
            for ($j = 0; $j < $CNODES2; $j++) {
                 printf("<span class='nodeNum'>%8s</span>", htmlspecialchars(trim($res[$j])));
                 if ($j < $CNODES2 - 1) { echo ", "; }
                 $k++;
                 if ($k >= 10 && $j < $CNODES2 - 1) { $k = 0; echo "\n     "; }
            }
            echo "\n\n";
        } else {
             echo "<span class='ast-status-none-indicator'>" . htmlspecialchars("<NONE>") . "</span>\n\n";
        }

        echo "<span class='ast-status-section-title'>***************************** LSTATS ******************************</span>\n";

        $AMI2 = SimpleAmiClient::command($fp, "rpt lstats $node_num");
         if ($AMI2 === false) {
             echo "<span class='ast-status-error-msg'>Error retrieving lstats info for node $node_num.</span>\n\n\n";
             continue;
         }
         if (trim($AMI2) === '') {
              echo "<span class='ast-status-none-indicator'>No lstats info returned for node $node_num.</span>\n\n\n";
             continue;
         }

        $cmd_lstats = "$ECHO_CMD -n " . escapeshellarg($AMI2) . " | $HEAD_CMD --lines=-1";
        $N = trim(`$cmd_lstats 2>&1`);
        $N = clean_ami_output_for_display($N); // Clean output before display
        echo htmlspecialchars($N) . "\n\n\n";

   }

   if ($processed_node_count == 0 && trim($nodes_output) !== '') {
        $nodes_output_cleaned = clean_ami_output_for_display($nodes_output); // Clean output before display
        echo "<span class='ast-status-error-msg'>Warning:</span> Node list retrieved, but no valid node numbers identified in the output:\n<pre class='ast-status-pre'>" . htmlspecialchars($nodes_output_cleaned) . "</pre>\n";
   }
}

function show_channels($fp)
{
    global $HEAD;
    $HEAD_CMD = isset($HEAD) ? $HEAD : '/usr/bin/head';
    $ECHO_CMD = '/bin/echo';

    $AMI1 = SimpleAmiClient::command($fp, "iax2 show channels");

    echo "<span class='ast-status-section-title'>**************************** CHANNELS *****************************</span>\n";

    if ($AMI1 === false) { echo "<span class='ast-status-error-msg'>Error retrieving IAX2 channel info.</span>\n\n"; return; }
    if (trim($AMI1) === '') { echo "<span class='ast-status-none-indicator'>No IAX2 channels reported.</span>\n\n"; return; }

    $cmd_channels = "$ECHO_CMD -n ". escapeshellarg($AMI1) ." | $HEAD_CMD --lines=-1";
    $channels = trim(`$cmd_channels 2>&1`);

    if (trim($channels) === '' && trim($AMI1) !== '') {
        // Fallback to AMI1 if channels is empty but AMI1 was not
        $display_output = clean_ami_output_for_display($AMI1);
        echo htmlspecialchars($display_output) . "\n\n";
    } else {
        $display_output = clean_ami_output_for_display($channels);
        echo htmlspecialchars($display_output) . "\n\n";
    }
}

function show_netstats($fp)
{
    global $HEAD;
    $HEAD_CMD = isset($HEAD) ? $HEAD : '/usr/bin/head';
    $ECHO_CMD = '/bin/echo';

    $AMI1 = SimpleAmiClient::command($fp, "iax2 show netstats");

    echo "<span class='ast-status-section-title'>**************************** NETSTATS *****************************</span>\n";

     if ($AMI1 === false) { echo "<span class='ast-status-error-msg'>Error retrieving IAX2 netstats info.</span>\n\n"; return; }
     if (trim($AMI1) === '') { echo "<span class='ast-status-none-indicator'>No IAX2 netstats reported.</span>\n\n"; return; }

    $cmd_netstats = "$ECHO_CMD -n ". escapeshellarg($AMI1) ." | $HEAD_CMD --lines=-1";
    $netstats = trim(`$cmd_netstats 2>&1`);

    if (trim($netstats) === '' && trim($AMI1) !== '') {
        // Fallback to AMI1 if netstats is empty but AMI1 was not
        $display_output = clean_ami_output_for_display($AMI1);
        echo htmlspecialchars($display_output) . "\n\n";
    } else {
        $display_output = clean_ami_output_for_display($netstats);
        echo htmlspecialchars($display_output) . "\n\n";
    }
}

function show_peers($fp)
{
    global $HEAD, $EGREP;
    $HEAD_CMD = isset($HEAD) ? $HEAD : '/usr/bin/head';
    $EGREP_CMD = isset($EGREP) ? $EGREP : '/bin/egrep';
    $ECHO_CMD = '/bin/echo';

    $AMI1 = SimpleAmiClient::command($fp, "iax2 show peers");

    echo "<span class='ast-status-section-title'>*************************** OTHER PEERS ***************************</span>\n";

    if ($AMI1 === false) { echo "<span class='ast-status-error-msg'>Error retrieving IAX2 peer info.</span>\n\n\n"; return; }
    if (trim($AMI1) === '') { echo "<span class='ast-status-none-indicator'>No IAX2 peers reported.</span>\n\n\n"; return; }

    $cmd_peers = "$ECHO_CMD -n ". escapeshellarg($AMI1) ." | $HEAD_CMD --lines=-1 | $EGREP_CMD -v '^Name|iax2 peers|Unspecified|^$'";
    $peers = trim(`$cmd_peers 2>&1`);

    if (!empty($peers)) {
        $peers = clean_ami_output_for_display($peers); // Clean output before display
        echo htmlspecialchars($peers) . "\n\n\n";
    } else {
        if (trim($AMI1) !== '') {
             echo "<span class='ast-status-none-indicator'>" . htmlspecialchars("<NONE after filtering>") . "</span>\n\n\n";
        } else {
             echo "<span class='ast-status-none-indicator'>" . htmlspecialchars("<NONE>") . "</span>\n\n\n";
        }
    }
}

?>