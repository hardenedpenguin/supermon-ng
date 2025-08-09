<?php
/**
 * Server.php AMI Connection Management
 * 
 * This file contains AMI connection setup and management
 * logic extracted from server.php.
 */

/**
 * Establish AMI connections for all nodes
 * @param array $nodes Array of node IDs
 * @param array $config Configuration array
 * @return array Returns [$fp, $servers] where $fp is socket array and $servers is status array
 */
function establishAmiConnections($nodes, $config) {
    $fp = [];
    $servers = [];
    
    foreach ($nodes as $node) {
        $nodeConfig = $config[$node];
        $host = $nodeConfig['host'];
        
        if (!isset($servers[$host])) {
            $servers[$host] = 'n';
            
            $socket = SimpleAmiClient::connect($host);
            if ($socket === false) {
                $errMsg = "Could not connect to Asterisk Manager for node '$node' on host '$host'.";
                $data = ['host' => $host, 'node' => $node, 'status' => '   ' . $errMsg];
                error_log("AMI_CONNECT_FAIL: $errMsg");
            } else {
                $loginResult = SimpleAmiClient::login($socket, $nodeConfig['user'], $nodeConfig['passwd']);
                if ($loginResult === true) {
                    $fp[$host] = $socket;
                    $servers[$host] = 'y';
                    $data = ['host' => $host, 'node' => $node, 'status' => '   Connected to Asterisk Manager'];
                    error_log("AMI_CONNECT_SUCCESS: Connected to $host for node $node");
                } else {
                    $errMsg = "Could not login to Asterisk Manager for node '$node' on host '$host' with user '{$nodeConfig['user']}'.";
                    $data = ['host' => $host, 'node' => $node, 'status' => '   ' . $errMsg];
                    error_log("AMI_LOGIN_FAIL: $errMsg");
                    SimpleAmiClient::logoff($socket);
                }
            }
        } else {
            if ($servers[$host] === 'y') {
                $data = ['host' => $host, 'node' => $node, 'status' => '   Using existing connection'];
            } else {
                $data = ['host' => $host, 'node' => $node, 'status' => '   Connection failed (reusing previous failure)'];
            }
        }
        
        echo "event: connection\n";
        echo 'data: ' . json_encode($data) . "\n\n";
        ob_flush();
        flush();
    }

    if (empty($servers) || !in_array('y', $servers)) {
        error_log("No AMI servers successfully connected in server.php. Exiting.");
        $data = ['status' => 'Failed to connect to any Asterisk Managers.'];
        echo "event: error\n";
        echo 'data: ' . json_encode($data) . "\n\n";
        ob_flush();
        flush();
        exit;
    }
    
    return [$fp, $servers];
}

/**
 * Cleanup AMI connections
 * @param array $fp Array of socket connections
 * @param array $servers Array of server statuses
 */
function cleanupAmiConnections($fp, $servers) {
    foreach ($fp as $host => $socket) {
        if ($socket && is_resource($socket) && isset($servers[$host]) && $servers[$host] === 'y') {
            SimpleAmiClient::logoff($socket);
        }
    }
}
?>
