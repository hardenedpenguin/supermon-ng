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

include("authini.php");

define('ECHOLINK_NODE_THRESHOLD', 3000000);

include('includes/amifunctions.inc');
include('includes/nodeinfo.inc');
include('includes/server-functions.inc');
include('includes/server-config.inc');
include('includes/server-ami.inc');
include("user_files/global.inc");
include("includes/common.inc");

// Initialize global caches
$elnk_cache = [];
$irlp_cache = [];

// Initialize server and get configuration
list($nodes, $config, $astdb) = initializeServer();

// Establish AMI connections
list($fp, $servers) = establishAmiConnections($nodes, $config);

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

// Cleanup AMI connections
cleanupAmiConnections($fp, $servers);
exit;

