<?php
/**
 * Supermon-ng Real-Time Server (Server-Sent Events)
 * 
 * This script provides real-time node monitoring data via Server-Sent Events (SSE).
 * It connects to Asterisk Manager Interface (AMI) to fetch live node status,
 * connection information, and activity data for AllStar Link nodes.
 * 
 * Features:
 * - Real-time node status updates
 * - Connected node information
 * - Activity monitoring (RX/TX status)
 * - EchoLink and IRLP integration
 * - Automatic data refresh
 * - Connection status monitoring
 * 
 * Protocol: Server-Sent Events (SSE)
 * Content-Type: text/event-stream
 * 
 * Query Parameters:
 * - nodes: Comma-separated list of node IDs to monitor
 * 
 * Security: Uses session-based authentication
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

@set_time_limit(0);
session_name("supermon61");
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
date_default_timezone_set('America/New_York');

include("authini.php");

define('ECHOLINK_NODE_THRESHOLD', 3000000);

include('includes/amifunctions.inc');
include('includes/nodeinfo.inc');
include('includes/server-functions.inc');
include("user_files/global.inc");
include("includes/common.inc");

if (empty($_GET['nodes'])) {
    error_log("Unknown request! Missing nodes parameter in server.php.");
    $data = ['status' => 'Unknown request! Missing nodes parameter.'];
    echo "event: error\n";
    echo 'data: ' . json_encode($data) . "\n\n";
    ob_flush();
    flush();
    if (session_status() == PHP_SESSION_ACTIVE) { session_write_close(); }
    exit;
}

$passedNodes = explode(',', trim(strip_tags($_GET['nodes'])));
$passedNodes = array_filter(array_map('trim', $passedNodes), 'strlen');

if (empty($passedNodes)) {
    error_log("No valid nodes in 'nodes' parameter after parsing in server.php.");
    $data = ['status' => 'No valid nodes provided in the request.'];
    echo "event: error\n";
    echo 'data: ' . json_encode($data) . "\n\n";
    ob_flush();
    flush();
    if (session_status() == PHP_SESSION_ACTIVE) { session_write_close(); }
    exit;
}

$db = $ASTDB_TXT ?? null;
$astdb = [];
if (isset($db) && file_exists($db)) {
    $fh = fopen($db, "r");
    if ($fh && flock($fh, LOCK_SH)) {
        while (($line = fgets($fh)) !== FALSE) {
            $arr = preg_split("/\|/", trim($line));
            if (isset($arr[0])) {
                $astdb[$arr[0]] = $arr;
            }
        }
        flock($fh, LOCK_UN);
        fclose($fh);
    } elseif ($fh) {
        error_log("ASTDB_TXT: Opened but flock failed for $db.");
        fclose($fh);
    } else {
         error_log("ASTDB_TXT: Could not open file $db for reading.");
    }
} else {
    error_log("ASTDB_TXT ('" . ($db ?? 'Not defined') . "') not defined or file does not exist.");
}

$elnk_cache = [];
$irlp_cache = [];

$SUPINI = get_ini_name($_SESSION['user'] ?? '');

if (!file_exists($SUPINI)) {
    $data = ['status' => "Critical Error: Couldn't load $SUPINI file."];
    error_log("CRITICAL ERROR: SUPINI file '$SUPINI' for user '" . ($_SESSION['user'] ?? 'Unknown') . "' does NOT exist.");
    echo "event: error\n";
    echo 'data: ' . json_encode($data) . "\n\n";
    ob_flush();
    flush();
    if (session_status() == PHP_SESSION_ACTIVE) { session_write_close(); }
    exit;
}

$config = parse_ini_file($SUPINI, true);
if ($config === false) {
    error_log("CRITICAL ERROR: parse_ini_file failed for '$SUPINI'. PHP error: " . print_r(error_get_last(), true));
    $data = ['status' => "Critical Error: Couldn't parse $SUPINI file. Check INI syntax."];
    echo "event: error\n";
    echo 'data: ' . json_encode($data) . "\n\n";
    ob_flush();
    flush();
    if (session_status() == PHP_SESSION_ACTIVE) { session_write_close(); }
    exit;
}

if (session_status() == PHP_SESSION_ACTIVE) {
    session_write_close();
}

$nodes = [];
foreach ($passedNodes as $node) {
    $trimmedNode = trim($node);
    if (isset($config[$trimmedNode])) {
        $nodes[] = $trimmedNode;
    } else {
        $data = ['node' => $trimmedNode, 'status' => "Node $trimmedNode is not in $SUPINI file"];
        error_log("Node '$trimmedNode' IS NOT VALID. Not found in $SUPINI.");
        echo "event: nodes\n";
        echo 'data: ' . json_encode([$trimmedNode => $data]) . "\n\n";
        ob_flush();
        flush();
    }
}

if (empty($nodes)) {
    error_log("No valid nodes to process after checking config.");
    $data = ['status' => 'No valid nodes configured or passed.'];
    echo "event: error\n";
    echo 'data: ' . json_encode($data) . "\n\n";
    ob_flush();
    flush();
    exit;
}

$servers = [];
$fp = [];

foreach ($nodes as $node) {
    $nodeConfig = $config[$node] ?? null;
    if (!$nodeConfig || !isset($nodeConfig['host'], $nodeConfig['user'], $nodeConfig['passwd'])) {
        $errMsg = "Missing critical configuration (host, user, or passwd) for node '$node' in $SUPINI.";
        $data = ['host' => ($nodeConfig['host'] ?? 'UnknownHost'), 'node' => $node, 'status' => '   ' . $errMsg];
        error_log("AMI_SETUP_ERROR: $errMsg.");
        echo "event: connection\n";
        echo 'data: ' . json_encode($data) . "\n\n";
        ob_flush();
        flush();
        continue;
    }

    $host = $nodeConfig['host'];

    if (!array_key_exists($host, $servers)) {
        $connectMsg = "Connecting to Asterisk Manager for node '$node' on host '$host'...";
        $data = ['host' => $host, 'node' => $node, 'status' => '   ' . $connectMsg];
        echo "event: connection\n";
        echo 'data: ' . json_encode($data) . "\n\n";
        ob_flush();
        flush();

        $socket = SimpleAmiClient::connect($host);
        if ($socket === FALSE) {
            $errMsg = "Could not connect to Asterisk Manager for node '$node' on host '$host'.";
            $data = ['host' => $host, 'node' => $node, 'status' => '   ' . $errMsg];
            error_log("AMI_CONNECT_FAIL: $errMsg");
        } else {
            if (SimpleAmiClient::login($socket, $nodeConfig['user'], $nodeConfig['passwd'])) {
                $servers[$host] = 'y';
                $fp[$host] = $socket;
                $successData = ['host' => $host, 'node' => $node, 'status' => '   Connected to Asterisk Manager.'];
                echo "event: connection\n";
                echo 'data: ' . json_encode($successData) . "\n\n";
                ob_flush();
                flush();
                continue;
            } else {
                $errMsg = "Could not login to Asterisk Manager for node '$node' on host '$host' with user '{$nodeConfig['user']}'.";
                $data = ['host' => $host, 'node' => $node, 'status' => '   ' . $errMsg];
                error_log("AMI_LOGIN_FAIL: $errMsg");
                SimpleAmiClient::logoff($socket);
            }
        }
        echo "event: connection\n";
        echo 'data: ' . json_encode($data) . "\n\n";
        ob_flush();
        flush();
    }
}

if (empty($servers)) {
    error_log("No AMI servers successfully connected in server.php. Exiting.");
    $data = ['status' => 'Failed to connect to any Asterisk Managers.'];
    echo "event: error\n";
    echo 'data: ' . json_encode($data) . "\n\n";
    ob_flush();
    flush();
    exit;
}

$current = [];
$saved = [];
$nodeTime = [];
$x = 0;
$loop_iteration = 0;


while (TRUE) {
    $loop_iteration++;

    if (connection_aborted()) {
        error_log("Client connection aborted by user in server.php. Exiting main loop.");
        break;
    }

    $j = 0;
    $active_nodes_in_loop = 0;
    $currentIterationData = [];
    $currentIterationNodeTime = [];

    foreach ($nodes as $node) {
        $nodeConfig = $config[$node];
        
        if (!isset($servers[$nodeConfig['host']]) || $servers[$nodeConfig['host']] !== 'y') {
            continue;
        }
        $active_nodes_in_loop++;

        $hostFp = $fp[$nodeConfig['host']];
        
        // Check if socket is still valid and healthy before using it
        if (!isConnectionHealthy($hostFp)) {
            error_log("Main loop: Socket for node $node is not healthy, skipping");
            continue;
        }
        
        $astInfo = getAstInfo($hostFp, $node);

        $currentIterationData[$node]['node'] = $node;
        $currentIterationData[$node]['info'] = $astInfo;
        $currentIterationNodeTime[$node]['node'] = $node;
        $currentIterationNodeTime[$node]['info'] = $astInfo;

        $rawConnectedNodes = getNode($hostFp, $node);

        $mainNodeSpecificDataKey = 1;
        
        $currentIterationData[$node]['cos_keyed'] = $rawConnectedNodes[$mainNodeSpecificDataKey]['cos_keyed'] ?? 0;
        $currentIterationData[$node]['tx_keyed'] = $rawConnectedNodes[$mainNodeSpecificDataKey]['tx_keyed'] ?? 0;
        $currentIterationData[$node]['cpu_temp'] = $rawConnectedNodes[$mainNodeSpecificDataKey]['cpu_temp'] ?? null;
        $currentIterationData[$node]['cpu_up'] = $rawConnectedNodes[$mainNodeSpecificDataKey]['cpu_up'] ?? null;
        $currentIterationData[$node]['cpu_load'] = $rawConnectedNodes[$mainNodeSpecificDataKey]['cpu_load'] ?? null;
        $currentIterationData[$node]['ALERT'] = $rawConnectedNodes[$mainNodeSpecificDataKey]['ALERT'] ?? null;
        $currentIterationData[$node]['WX'] = $rawConnectedNodes[$mainNodeSpecificDataKey]['WX'] ?? null;
        $currentIterationData[$node]['DISK'] = $rawConnectedNodes[$mainNodeSpecificDataKey]['DISK'] ?? null;

        $sortedConnectedNodes = sortNodes($rawConnectedNodes);

        $currentIterationData[$node]['remote_nodes'] = [];
        $currentIterationNodeTime[$node]['remote_nodes'] = [];
        $remoteNodeIndex = 0;
        if (is_array($sortedConnectedNodes)) {
            foreach ($sortedConnectedNodes as $remoteNodeNum => $remoteNodeData) {
                $currentIterationNodeTime[$node]['remote_nodes'][$remoteNodeIndex]['elapsed'] = $remoteNodeData['elapsed'];
                $currentIterationNodeTime[$node]['remote_nodes'][$remoteNodeIndex]['last_keyed'] = $remoteNodeData['last_keyed'];

                $currentRemoteDisplayData = $remoteNodeData;
                $currentRemoteDisplayData['elapsed'] = ' ';
                $currentRemoteDisplayData['last_keyed'] = ($remoteNodeData['last_keyed'] === "Never") ? 'Never' : ' ';

                $currentIterationData[$node]['remote_nodes'][$remoteNodeIndex] = [
                    'node'       => $currentRemoteDisplayData['node'] ?? $remoteNodeNum,
                    'info'       => $currentRemoteDisplayData['info'] ?? null,
                    'link'       => $currentRemoteDisplayData['link'] ?? null,
                    'ip'         => $currentRemoteDisplayData['ip'] ?? null,
                    'direction'  => $currentRemoteDisplayData['direction'] ?? null,
                    'keyed'      => $currentRemoteDisplayData['keyed'] ?? null,
                    'mode'       => $currentRemoteDisplayData['mode'] ?? null,
                    'elapsed'    => $currentRemoteDisplayData['elapsed'],
                    'last_keyed' => $currentRemoteDisplayData['last_keyed'],
                ];
                $remoteNodeIndex++;
            }
        }
        $j += $remoteNodeIndex;
        $j++;
    }
    
    $current = $currentIterationData;
    $nodeTime = $currentIterationNodeTime;

    if ($active_nodes_in_loop == 0 && $loop_iteration == 1) {
        error_log("Main loop: No active nodes to process in the first iteration. Check AMI connections. Exiting loop.");
        $data = ['status' => 'No nodes available for monitoring after connection phase.'];
        echo "event: error\n";
        echo 'data: ' . json_encode($data) . "\n\n";
        ob_flush();
        flush();
        break;
    }

    $looptime = max(1, intval(20 - ($j * 0.089)));

    $dataChanged = ($current !== $saved);
    if ($dataChanged) {
        $saved = $current;
        if (!empty($current)) {
            echo "event: nodes\n";
            echo 'data: ' . json_encode($current) . "\n\n";
        }
        if (!empty($nodeTime)) {
            echo "event: nodetimes\n";
            echo 'data: ' . json_encode($nodeTime) . "\n\n";
        }
        ob_flush();
        flush();
        $x = 0;
        usleep(500000);
    } else {
        $x++;
        usleep(500000);
        if ($x >= ($looptime * 2)) {
            if (!empty($nodeTime)) {
                echo "event: nodetimes\n";
                echo 'data: ' . json_encode($nodeTime) . "\n\n";
                ob_flush();
                flush();
            }
            $x = 0;
        }
    }
}

foreach ($fp as $host => $socket) {
    if ($socket && is_resource($socket) && isset($servers[$host]) && $servers[$host] === 'y') {
        SimpleAmiClient::logoff($socket);
    }
}
exit;

