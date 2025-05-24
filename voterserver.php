<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
date_default_timezone_set('America/Los_Angeles');

include('nodeinfo.inc');
include("user_files/global.inc");
include("common.inc");
include("authini.php");
include('amifunctions.inc');

if (empty($_GET['node'])) {
    echo "data: Unknown voter request!\n\n";
    ob_flush();
    flush();
    exit;
}

$node = trim(strip_tags($_GET['node']));

$db = $ASTDB_TXT;
$astdb = [];
if (file_exists($db)) {
    $fh = fopen($db, "r");
    if ($fh && flock($fh, LOCK_SH)) {
        while (($line = fgets($fh)) !== false) {
            $trimmed_line = trim($line);
            if (empty($trimmed_line)) {
                continue;
            }
            $arr = explode("|", $trimmed_line);
            if (isset($arr[0])) {
                $astdb[$arr[0]] = $arr;
            }
        }
        flock($fh, LOCK_UN);
        fclose($fh);
    } elseif ($fh) {
        fclose($fh);
    }
}

$SUPINI = get_ini_name($_SESSION['user']);

if (!file_exists($SUPINI)) {
    echo "data: Couldn't load INI file: $SUPINI\n\n";
    ob_flush();
    flush();
    exit;
}
$config = parse_ini_file($SUPINI, true);

if (!isset($config[$node])) {
    echo "data: Configuration for node $node not found in $SUPINI.\n\n";
    ob_flush();
    flush();
    exit;
}
$nodeConfig = $config[$node];

echo "data: Connecting...\n\n";
ob_flush();
flush();

$fp = SimpleAmiClient::connect($nodeConfig['host']);
if ($fp === false) {
    echo "data: Could not connect to Asterisk Manager.\n\n";
    ob_flush();
    flush();
    exit;
}

if (SimpleAmiClient::login($fp, $nodeConfig['user'], $nodeConfig['passwd']) === false) {
    echo "data: Could not login to Asterisk Manager.\n\n";
    ob_flush();
    flush();
    SimpleAmiClient::logoff($fp);
    exit;
}

$spinChars = ['*', '|', '/', '-', '\\'];
$spinIndex = 0;
$actionIDBase = "voter" . preg_replace('/[^a-zA-Z0-9]/', '', $node);

while (true) {
    $actionID = $actionIDBase . mt_rand(1000, 9999);
    
    $response = get_voter_status($fp, $actionID);
    if ($response === false) {
        echo "data: Error getting voter response or disconnected.\n\n";
        ob_flush();
        flush();
        SimpleAmiClient::logoff($fp);
        exit;
    }
    
    $lines = explode("\n", $response);
    $parsed_nodes_data = [];
    $parsed_voted_data = [];
    $currentNodeContext = null;

    foreach ($lines as $line) {
        $line = trim($line);
        if (strlen($line) === 0) {
            continue;
        }
        
        $parts = explode(": ", $line, 2);
        if (count($parts) < 2) continue;
        
        list($key, $value) = $parts;
        
        $$key = $value; 

        if ($key == "Node") {
            $currentNodeContext = $value;
            $parsed_nodes_data[$currentNodeContext] = [];
        }
    
        if ($key == "RSSI" && $currentNodeContext && isset($Client)) { 
            $parsed_nodes_data[$currentNodeContext][$Client]['rssi'] = isset($RSSI) ? $RSSI : 'N/A';
            $parsed_nodes_data[$currentNodeContext][$Client]['ip'] = isset($IP) ? $IP : 'N/A';
        }
    
        if ($key == 'Voted' && $currentNodeContext) {
            $parsed_voted_data[$currentNodeContext] = $value; 
        }
    }
    
    $message = printNode($node, $parsed_nodes_data, $parsed_voted_data, $nodeConfig);

    $ticToc = $spinChars[$spinIndex];
    $spinIndex = ($spinIndex + 1) % count($spinChars);

    echo "data: $message\n";
    echo "data: $ticToc\n\n";
    ob_flush();
    flush();
    
    usleep(150000);
}

SimpleAmiClient::logoff($fp);
exit;

function printNode($nodeNum, $nodesData, $votedData, $currentConfig) {
    global $fp;

    $message = '';
    $info = getAstInfo($fp, $nodeNum); 

    if (!empty($currentConfig['hideNodeURL'])) {
        $message .= "<table class='rtcm'><tr><th colspan=2><i>   Node $nodeNum - $info   </i></th></tr>";
    } else {
        $nodeURL = "http://stats.allstarlink.org/nodeinfo.cgi?node=$nodeNum";
        $message .= "<table class='rtcm'><tr><th colspan=2><i>   Node <a href=\"$nodeURL\" target=\"_blank\">$nodeNum</a> - $info   </i></th></tr>";
    }
    $message .= "<tr><th>Client</th><th>RSSI</th></tr>";

    if (!isset($nodesData[$nodeNum]) || empty($nodesData[$nodeNum])) {
        $message .= "<tr><td><div style='width: 120px;'> No clients </div></td>";
        $message .= "<td><div style='width: 339px;'> </div></td></tr>";
    } else {
        $clients = $nodesData[$nodeNum];
        $votedClient = isset($votedData[$nodeNum]) && $votedData[$nodeNum] !== 'none' ? $votedData[$nodeNum] : null;

        foreach($clients as $clientName => $client) {
            $rssi = isset($client['rssi']) ? (int)$client['rssi'] : 0;
            
            $bar_width_px = round(($rssi / 255) * 300);
            if ($rssi == 0) {
                $bar_width_px = 3;
            } else {
                $bar_width_px = max(1, $bar_width_px);
            }

            $barcolor = "#0099FF";
            $textcolor = 'white';

            if ($votedClient && strpos($clientName, $votedClient) !== false) {
                $barcolor = 'greenyellow';
                $textcolor = 'black';
            } elseif (strpos($clientName, 'Mix') !== false) {
                $barcolor = 'cyan';
                $textcolor = 'black';
            }

            $message .= "<tr>";
            $message .= "<td><div>" . htmlspecialchars($clientName) . "</div></td>";
            $message .= "<td><div class='text'> <div class='barbox_a'>";
            $message .= "<div class='bar' style='width: " . $bar_width_px . "px; background-color: $barcolor; color: $textcolor'>" . $rssi . "</div>";
            $message .= "</div></td></tr>";
        }
    }
    $message .= "<tr><td colspan=2> </td></tr>";
    $message .= "</table><br/>";
    
    return $message;
}

function get_voter_status($fp, $actionID) {
    $amiEOL = "\r\n";
    $action = "Action: VoterStatus" . $amiEOL;
    $action .= "ActionID: " . $actionID . $amiEOL . $amiEOL;

    if ($fp && fwrite($fp, $action) > 0) {
        return SimpleAmiClient::getResponse($fp, $actionID);
    } else {
        return false;
    }
}
?>