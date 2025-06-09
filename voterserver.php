<?php
@set_time_limit(0);

include_once("session.inc");
session_write_close();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

date_default_timezone_set('America/Los_Angeles');

include_once('nodeinfo.inc');
include_once("user_files/global.inc");
include_once("common.inc");
include_once("authini.php");
include_once('amifunctions.inc');

$db = $ASTDB_TXT;
$astdb = [];
if (file_exists($db)) {
    $fh = fopen($db, "r");
    if ($fh && flock($fh, LOCK_SH)) {
        while (($line = fgets($fh)) !== false) {
            $trimmed_line = trim($line);
            if (empty($trimmed_line)) continue;
            $arr = explode("|", $trimmed_line);
            if (isset($arr[0])) $astdb[$arr[0]] = $arr;
        }
        flock($fh, LOCK_UN); fclose($fh);
    }
}

if (empty($_GET['node'])) {
    echo "data: [FATAL] 'node' parameter is missing.\n\n";
    ob_flush(); flush(); exit;
}
$node = trim(strip_tags($_GET['node']));

$ini_file_path = '';
$user_context = 'public';

if (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && !empty($_SESSION['user'])) {
    $ini_file_path = get_ini_name($_SESSION['user']);
    $user_context = "user '{$_SESSION['user']}'";
} else {
    $ini_file_path = "$USERFILES/allmon.ini";
}

if (!file_exists($ini_file_path)) {
    echo "data: [FATAL] Configuration file not found for {$user_context}: {$ini_file_path}\n\n";
    ob_flush(); flush(); exit;
}

$config = parse_ini_file($ini_file_path, true);
if ($config === false) {
    echo "data: [FATAL] Error parsing configuration file: {$ini_file_path}\n\n";
    ob_flush(); flush(); exit;
}

if (!isset($config[$node])) {
    echo "data: [FATAL] Configuration for node '$node' not found in {$user_context} context.\n\n";
    ob_flush(); flush(); exit;
}
$nodeConfig = $config[$node];

$fp = SimpleAmiClient::connect($nodeConfig['host']);
if ($fp === false) {
    echo "data: [FATAL] Could not connect to Asterisk Manager on host: {$nodeConfig['host']}.\n\n";
    ob_flush(); flush(); exit;
}

if (SimpleAmiClient::login($fp, $nodeConfig['user'], $nodeConfig['passwd']) === false) {
    echo "data: [FATAL] Could not login to Asterisk Manager. Check user/password.\n\n";
    ob_flush(); flush();
    SimpleAmiClient::logoff($fp);
    exit;
}

$spinChars = ['*', '|', '/', '-', '\\'];
$spinIndex = 0;
$actionIDBase = "voter" . preg_replace('/[^a-zA-Z0-9]/', '', $node);

while (true) {
    if (connection_aborted()) {
        break;
    }

    $actionID = $actionIDBase . mt_rand(1000, 9999);
    $response = get_voter_status($fp, $actionID);

    if ($response === false) {
        $error_data = json_encode(['html' => 'Error: Disconnected from Asterisk server.', 'spinner' => 'X']);
        echo "id: " . time() . "\n";
        echo "data: " . $error_data . "\n\n";
        ob_flush(); flush();
        break;
    }

    list($parsed_nodes_data, $parsed_voted_data) = parse_voter_response($response);
    $html_message = format_node_html($node, $parsed_nodes_data, $parsed_voted_data, $nodeConfig);
    $spinner = $spinChars[$spinIndex];
    $spinIndex = ($spinIndex + 1) % count($spinChars);

    $payload = json_encode([
        'html' => $html_message,
        'spinner' => $spinner
    ]);

    echo "id: " . time() . "\n";
    echo "data: " . $payload . "\n\n";

    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();

    sleep(1);
}

SimpleAmiClient::logoff($fp);
exit;

function parse_voter_response($response) {
    $lines = explode("\n", $response);
    $parsed_nodes_data = [];
    $parsed_voted_data = [];
    $currentNodeContext = null;
    $Client = $RSSI = $IP = null;

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        $parts = explode(": ", $line, 2);
        if (count($parts) < 2) continue;
        
        list($key, $value) = $parts;

        switch ($key) {
            case 'Node':
                $currentNodeContext = $value;
                if (!isset($parsed_nodes_data[$currentNodeContext])) {
                    $parsed_nodes_data[$currentNodeContext] = [];
                }
                break;
            case 'Client':
                $Client = $value;
                break;
            case 'RSSI':
                $RSSI = $value;
                break;
            case 'IP':
                $IP = $value;
                if ($currentNodeContext && $Client) {
                    $parsed_nodes_data[$currentNodeContext][$Client] = [
                        'rssi' => $RSSI ?? 'N/A',
                        'ip'   => $IP ?? 'N/A'
                    ];
                    $Client = $RSSI = $IP = null;
                }
                break;
            case 'Voted':
                if ($currentNodeContext) {
                    $parsed_voted_data[$currentNodeContext] = $value;
                }
                break;
        }
    }
    return [$parsed_nodes_data, $parsed_voted_data];
}

function format_node_html($nodeNum, $nodesData, $votedData, $currentConfig) {
    global $fp, $astdb;
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
            $bar_width_px = ($rssi == 0) ? 3 : max(1, $bar_width_px);
            
            $barcolor = "#0099FF"; $textcolor = 'white';
            if ($votedClient && strpos($clientName, $votedClient) !== false) {
                $barcolor = 'greenyellow'; $textcolor = 'black';
            } elseif (strpos($clientName, 'Mix') !== false) {
                $barcolor = 'cyan'; $textcolor = 'black';
            }

            $message .= "<tr>";
            $message .= "<td><div>" . htmlspecialchars($clientName) . "</div></td>";
            $message .= "<td><div class='text'> <div class='barbox_a'>";
            $message .= "<div class='bar' style='text-align: center; width: " . $bar_width_px . "px; background-color: $barcolor; color: $textcolor'>" . $rssi . "</div>";
            $message .= "</div></td></tr>";
        }
    }
    $message .= "<tr><td colspan=2> </td></tr>";
    $message .= "</table><br/>";
    
    return str_replace(["\r", "\n"], '', $message);
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
