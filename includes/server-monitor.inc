<?php
/**
 * Server.php Main Monitoring Loop
 * 
 * This file contains the main monitoring loop logic
 * extracted from server.php for better organization.
 */

/**
 * Process a single node iteration - collect node data
 * @param array $fp Socket connections array
 * @param array $servers Server status array  
 * @param array $nodes Array of node IDs
 * @param array $config Configuration array
 * @return array Returns [$currentIterationData, $currentIterationNodeTime, $active_nodes_count]
 */
function processNodeIteration($fp, $servers, $nodes, $config) {
    $active_nodes_in_loop = 0;
    $currentIterationData = [];
    $currentIterationNodeTime = [];
    $j = 0;

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
                $currentRemoteDisplayData['elapsed'] = ' ';
                $currentRemoteDisplayData['last_keyed'] = ($remoteNodeData['last_keyed'] === "Never") ? 'Never' : ' ';

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
    
    return [$currentIterationData, $currentIterationNodeTime, $active_nodes_in_loop, $j];
}

/**
 * Send Server-Sent Events data to client
 * @param array $current Current node data
 * @param array $nodeTime Node timing data
 */
function sendSSEData($current, $nodeTime) {
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
}

/**
 * Send periodic timing updates
 * @param array $nodeTime Node timing data
 */
function sendPeriodicTimingUpdate($nodeTime) {
    if (!empty($nodeTime)) {
        echo "event: nodetimes\n";
        echo 'data: ' . json_encode($nodeTime) . "\n\n";
        ob_flush();
        flush();
    }
}

/**
 * Calculate dynamic loop timing based on node activity
 * @param int $j Node activity counter
 * @return int Loop time in seconds
 */
function calculateLoopTiming($j) {
    return max(1, intval(20 - ($j * 0.089)));
}

/**
 * Main monitoring loop - processes nodes and sends SSE data
 * @param array $fp Socket connections array
 * @param array $servers Server status array
 * @param array $nodes Array of node IDs  
 * @param array $config Configuration array
 */
function runMonitoringLoop($fp, $servers, $nodes, $config) {
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

        // Process all nodes for this iteration
        list($currentIterationData, $currentIterationNodeTime, $active_nodes_in_loop, $j) = 
            processNodeIteration($fp, $servers, $nodes, $config);
        
        $current = $currentIterationData;
        $nodeTime = $currentIterationNodeTime;

        // Check if we have any active nodes in first iteration
        if ($active_nodes_in_loop == 0 && $loop_iteration == 1) {
            error_log("Main loop: No active nodes to process in the first iteration. Check AMI connections. Exiting loop.");
            $data = ['status' => 'No nodes available for monitoring after connection phase.'];
            echo "event: error\n";
            echo 'data: ' . json_encode($data) . "\n\n";
            ob_flush();
            flush();
            break;
        }

        // Calculate dynamic loop timing
        $looptime = calculateLoopTiming($j);

        // Check if data changed and send updates
        $dataChanged = ($current !== $saved);
        if ($dataChanged) {
            $saved = $current;
            sendSSEData($current, $nodeTime);
            $x = 0;
            usleep(500000);
        } else {
            $x++;
            usleep(500000);
            if ($x >= ($looptime * 2)) {
                sendPeriodicTimingUpdate($nodeTime);
                $x = 0;
            }
        }
    }
}
?>
