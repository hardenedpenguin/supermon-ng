<?php
/**
 * Server.php Helper Functions
 * 
 * This file contains helper functions extracted from server.php
 * for better organization and maintainability.
 * 
 * Functions:
 * - isConnectionHealthy() - Check if AMI socket connection is healthy
 * - getNode() - Fetch node data via AMI commands (XStat/SawStat)
 * - sortNodes() - Sort connected nodes by various criteria
 * - parseNode() - Parse and format node data for JSON output
 */

function isConnectionHealthy($fp) {
    if (!is_resource($fp) || get_resource_type($fp) !== 'stream') {
        return false;
    }
    
    $socketStatus = stream_get_meta_data($fp);
    if ($socketStatus['eof'] || $socketStatus['timed_out']) {
        return false;
    }
    
    // Test with a simple ping command
    $testCommand = "Action: Ping\r\nActionID: health" . mt_rand() . "\r\n\r\n";
    $testResult = @fwrite($fp, $testCommand);
    if ($testResult === false) {
        $error = error_get_last();
        if (($error['errno'] ?? 0) === 32) { // Broken pipe
            return false;
        }
    }
    
    return true;
}

function getNode($fp, $node) {
    // Check if socket is still valid
    if (!is_resource($fp) || get_resource_type($fp) !== 'stream') {
        error_log("getNode: Socket is not a valid resource for node $node");
        return parseNode($fp, $node, '', '');
    }
    
    // Check socket status
    $socketStatus = stream_get_meta_data($fp);
    if ($socketStatus['eof'] || $socketStatus['timed_out']) {
        error_log("getNode: Socket is EOF or timed out for node $node");
        return parseNode($fp, $node, '', '');
    }
    

    
    $actionRand = mt_rand();
    $rptStatus = '';
    $sawStatus = '';
    $eol = "\r\n";

    $actionID_xstat = 'xstat' . $actionRand;
    $xstatCommand = "Action: RptStatus{$eol}COMMAND: XStat{$eol}NODE: {$node}{$eol}ActionID: {$actionID_xstat}{$eol}{$eol}";
    
    $xstatBytesWritten = @fwrite($fp, $xstatCommand);
    if ($xstatBytesWritten === false || $xstatBytesWritten === 0) {
        $error = error_get_last();
        $errno = $error['errno'] ?? 0;
        $errstr = $error['errstr'] ?? 'Unknown error';
        
        if ($errno === 32) { // Broken pipe
            error_log("getNode: XStat fwrite FAILED for node $node - BROKEN PIPE (errno=32)");
        } else {
            error_log("getNode: XStat fwrite FAILED for node $node - bytes written: " . ($xstatBytesWritten === false ? 'false' : $xstatBytesWritten) . ", errno: $errno, error: $errstr");
        }
        return parseNode($fp, $node, '', '');
    }
    
        $rptStatus = SimpleAmiClient::getResponse($fp, $actionID_xstat);
        if ($rptStatus === false) {
             error_log("getNode: XStat SimpleAmiClient::getResponse FAILED or timed out for node $node, actionID $actionID_xstat");
             $rptStatus = '';
    }

    $actionID_sawstat = 'sawstat' . $actionRand;
    $sawStatCommand = "Action: RptStatus{$eol}COMMAND: SawStat{$eol}NODE: {$node}{$eol}ActionID: {$actionID_sawstat}{$eol}{$eol}";
    
    $sawStatBytesWritten = @fwrite($fp, $sawStatCommand);
    if ($sawStatBytesWritten === false || $sawStatBytesWritten === 0) {
        $error = error_get_last();
        $errno = $error['errno'] ?? 0;
        $errstr = $error['errstr'] ?? 'Unknown error';
        
        if ($errno === 32) { // Broken pipe
            error_log("getNode: SawStat fwrite FAILED for node $node - BROKEN PIPE (errno=32)");
        } else {
            error_log("getNode: SawStat fwrite FAILED for node $node - bytes written: " . ($sawStatBytesWritten === false ? 'false' : $sawStatBytesWritten) . ", errno: $errno, error: $errstr");
        }
        return parseNode($fp, $node, $rptStatus, '');
    }
    
        $sawStatus = SimpleAmiClient::getResponse($fp, $actionID_sawstat);
        if ($sawStatus === false) {
            error_log("getNode: SawStat SimpleAmiClient::getResponse FAILED or timed out for node $node, actionID $actionID_sawstat");
            $sawStatus = '';
        }
    
    return parseNode($fp, $node, $rptStatus, $sawStatus);
}

function sortNodes($nodes_to_sort) {
    if (!is_array($nodes_to_sort) || empty($nodes_to_sort)) {
        return [];
    }

    uksort($nodes_to_sort, function($a, $b) {
        // Handle numeric comparison for most nodes
        if (is_numeric($a) && is_numeric($b)) {
            return intval($a) - intval($b);
        }
        
        // Handle mixed or string comparison
        return strcasecmp($a, $b);
    });

    return $nodes_to_sort;
}

function parseNode($fp, $queriedNode, $rptStatus, $sawStatus) {
    global $elnk_cache, $irlp_cache, $astdb;

    if (empty($queriedNode)) {
        error_log("parseNode: queriedNode is empty");
        return [];
    }

    $remote_nodes = array();
    $retData = array();

    $keyed = 'No';
    $mode = '';
    $local_tx_keyed = '';
    $totaltx = 0;
    $totalrx = 0;
    $totalkeyups = 0;
    $rpt_uptime = '';
    
    if (!empty($rptStatus)) {
        $lines = explode("\n", $rptStatus);
        foreach ($lines as $line) {
            if (strpos($line, "Rpt Stats") !== false) {
                continue;
            }
            if (strpos($line, "XMIT") !== false) {
                $local_tx_keyed = (strpos($line, " Keyed") !== false) ? 'Yes' : 'No';
                $keyed = $local_tx_keyed;
            }
            if (strpos($line, "MODE") !== false) {
                if (strpos($line, "Warm Standby") !== false) {
                    $mode = 'Warm Standby';
                } elseif (strpos($line, "Standby") !== false) {
                    $mode = 'Standby';
                } elseif (strpos($line, "Normal") !== false) {
                    $mode = 'Normal';
                } else {
                    $mode = 'Unknown';
                }
            }
            if (preg_match('/TOTALS:\s+(\d+)\s+(\d+)\s+(\d+)/', $line, $matches)) {
                $totaltx = intval($matches[1]);
                $totalrx = intval($matches[2]); 
                $totalkeyups = intval($matches[3]);
            }
            if (preg_match('/Rpt Uptime\s*:\s*(.+)/', $line, $matches)) {
                $rpt_uptime = trim($matches[1]);
            }
        }
    }

    $connected_nodes = array();
    if (!empty($sawStatus)) {
        $lines = explode("\n", $sawStatus);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, "Remote") !== false) continue;
            
            if (preg_match('/^(\d+)\s+(.*?)\s+([\d:]+|\*)\s+(\w+)\s+(\w+)\s+([\w\s]+)$/', $line, $matches)) {
                $nodeNum = trim($matches[1]);
                $info = trim($matches[2]);
                $elapsed = trim($matches[3]);
                $direction = trim($matches[4]);
                $nodeKeyed = trim($matches[5]);
                $mode_info = trim($matches[6]);
                
                $remote_nodes[$nodeNum] = array(
                    'elapsed' => $elapsed,
                    'node' => $nodeNum,
                    'info' => $info,
                    'keyed' => $nodeKeyed,
                    'mode' => $mode_info,
                    'direction' => $direction,
                    'last_keyed' => 'Never'
                );
                
                if ($nodeKeyed === 'YES' || $nodeKeyed === 'Yes') {
                    $keyed = 'Yes';
                }
            }
        }
    }

    // Process each connected node
    foreach ($remote_nodes as $nodeNum => $nodeData) {
        $nodeInfo = '';
        $nodeLink = '';
        $nodeIp = '';
        
        // Get node info from ASTDB
        if (isset($astdb[$nodeNum]) && is_array($astdb[$nodeNum])) {
            $nodeInfo = implode(' ', array_slice($astdb[$nodeNum], 1, 3));
        }

        // Handle EchoLink nodes
        if (intval($nodeNum) >= ECHOLINK_NODE_THRESHOLD) {
            if (!isset($elnk_cache[$nodeNum])) {
                $nodeInfoTemp = getEchoLinkInfo($nodeNum);
                $elnk_cache[$nodeNum] = $nodeInfoTemp;
            }
            $nodeInfo = $elnk_cache[$nodeNum];
            $nodeLink = "http://echolink.org/logins.jsp?callsign=" . urlencode($nodeInfo);
        }

        // Handle IRLP nodes (9-prefix)
        if (substr($nodeNum, 0, 1) === '9') {
            if (!isset($irlp_cache[$nodeNum])) {
                $nodeInfoTemp = getIrlpInfo($nodeNum);
                $irlp_cache[$nodeNum] = $nodeInfoTemp;
            }
            $nodeInfo = $irlp_cache[$nodeNum];
            $nodeLink = "http://status.irlp.net/index.php?CALLSIGN=" . urlencode($nodeInfo);
        }
        
        $connected_nodes[$nodeNum] = array(
            'node' => $nodeNum,
            'info' => $nodeInfo,
            'link' => $nodeLink,
            'ip' => $nodeIp,
            'direction' => $nodeData['direction'],
            'elapsed' => $nodeData['elapsed'],
            'keyed' => $nodeData['keyed'],
            'mode' => $nodeData['mode'],
            'last_keyed' => $nodeData['last_keyed']
        );
    }

    $connected_nodes = sortNodes($connected_nodes);

    $retData = array(
        'node' => $queriedNode,
        'keyed' => $keyed,
        'mode' => $mode,
        'local_tx_keyed' => $local_tx_keyed,
        'total_tx' => $totaltx,
        'total_rx' => $totalrx,
        'total_keyups' => $totalkeyups,
        'uptime' => $rpt_uptime,
        'remote_nodes' => $connected_nodes
    );

    return $retData;
}
?>
