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
    $arr = [];
    $never_heard = [];
    $sortedNodesResult = [];

    if (!is_array($nodes_to_sort)) {
        return [];
    }

    foreach ($nodes_to_sort as $nodeNum => $row) {
        if (!is_array($row) || !isset($row['last_keyed'])) {
            continue;
        }
        if ($row['last_keyed'] == '-1' || $row['last_keyed'] === 'Never') {
            $never_heard[$nodeNum] = 'Never heard';
        } else {
            $arr[$nodeNum] = (int)$row['last_keyed'];
        }
    }

    if (count($arr) > 0) {
        asort($arr, SORT_NUMERIC);
    }
    
    $merged_for_sorting = $arr;
    if (count($never_heard) > 0) {
        ksort($never_heard, SORT_NUMERIC);
        foreach ($never_heard as $nodeNum => $status_text) {
            $merged_for_sorting[$nodeNum] = -1; 
        }
    }
    
    foreach ($merged_for_sorting as $nodeNum => $last_keyed_seconds_or_flag) {
        if (!isset($nodes_to_sort[$nodeNum])) {
            error_log("sortNodes: Mismatch - nodeNum '$nodeNum' from sorted keys not in original \$nodes_to_sort.");
            continue;
        }
        $originalNodeData = $nodes_to_sort[$nodeNum];
        if ($last_keyed_seconds_or_flag > -1 && isset($originalNodeData['last_keyed']) && is_numeric($originalNodeData['last_keyed'])) {
            $t = (int)$originalNodeData['last_keyed'];
            $h = floor($t / 3600);
            $m = floor(($t / 60) % 60);
            $s = $t % 60;
            $originalNodeData['last_keyed'] = sprintf("%03d:%02d:%02d", $h, $m, $s);
        } else {
            $originalNodeData['last_keyed'] = "Never";
        }
        $sortedNodesResult[$nodeNum] = $originalNodeData;
    }
    return $sortedNodesResult;
}

function parseNode($fp, $queriedNode, $rptStatus, $sawStatus) {
    global $astdb, $elnk_cache, $irlp_cache;
    $curNodes = [];
    $conns = [];
    $parsedVars = [];

    $linesRpt = explode("\n", $rptStatus ?? '');
    foreach ($linesRpt as $line) {
        $line = trim($line);
        if (preg_match('/^Var: ([^=]+)=(.*)/', $line, $varMatch)) {
            $parsedVars[trim($varMatch[1])] = trim($varMatch[2]);
        } elseif (preg_match('/Conn: (.*)/', $line, $matches)) {
            $arr = preg_split("/\s+/", trim($matches[1]));
            if (isset($arr[0]) && is_numeric($arr[0]) && $arr[0] > ECHOLINK_NODE_THRESHOLD) {
                if (count($arr) >= 4) {
                     $conns[] = [
                        $arr[0], "", $arr[1] ?? null, $arr[2] ?? null, $arr[3] ?? null, $arr[4] ?? null
                    ];
                }
            } else {
                if (count($arr) >= 1) $conns[] = $arr;
            }
        }
    }

    $keyups = [];
    $linesSaw = explode("\n", $sawStatus ?? '');
    foreach ($linesSaw as $line) {
        if (preg_match('/Conn: (.*)/', trim($line), $matches)) {
            $arr = preg_split("/\s+/", trim($matches[1]));
            if (isset($arr[0]) && isset($arr[1]) && isset($arr[2]) && isset($arr[3])) {
                $keyups[$arr[0]] = ['node' => $arr[0], 'isKeyed' => $arr[1], 'keyed' => $arr[2], 'unkeyed' => $arr[3]];
            }
        }
    }

    $modes = [];
    if (!empty($rptStatus) && preg_match("/LinkedNodes: (.*)/", $rptStatus, $matches)) {
        $longRangeLinks = preg_split("/, /", trim($matches[1]));
        foreach ($longRangeLinks as $line) {
            if (!empty($line)) {
                $n_val = substr($line, 1);
                $modes[$n_val]['mode'] = substr($line, 0, 1);
            }
        }
    }

    $mainNodeCpuTemp = $parsedVars['cpu_temp'] ?? null;
    $mainNodeCpuUp = $parsedVars['cpu_up'] ?? null;
    $mainNodeCpuLoad = $parsedVars['cpu_load'] ?? null;
    $mainNodeALERT = $parsedVars['ALERT'] ?? null;
    $mainNodeWX = $parsedVars['WX'] ?? null;
    $mainNodeDISK = $parsedVars['DISK'] ?? null;

    if (count($conns) > 0) {
        foreach ($conns as $connData) {
            $n = $connData[0];
            if (empty($n)) continue;

            $isEcholink = (is_numeric($n) && $n > ECHOLINK_NODE_THRESHOLD && ($connData[1] ?? '') === "");

            $curNodes[$n]['node'] = $n;
            $curNodes[$n]['info'] = getAstInfo($fp, $n);
            $curNodes[$n]['ip'] = $isEcholink ? "" : ($connData[1] ?? null);
            $curNodes[$n]['direction'] = $isEcholink ? ($connData[2] ?? null) : ($connData[3] ?? null);
            $curNodes[$n]['elapsed'] = $isEcholink ? ($connData[3] ?? null) : ($connData[4] ?? null);
            $curNodes[$n]['link'] = $isEcholink ? ($connData[4] ?? 'UNKNOWN') : ($connData[5] ?? null);

            if ($isEcholink) {
                if (isset($modes[$n]['mode'])) {
                    $curNodes[$n]['link'] = ($modes[$n]['mode'] == 'C') ? "CONNECTING" : "ESTABLISHED";
                }
            }
            
            $curNodes[$n]['keyed'] = 'n/a';
            $curNodes[$n]['last_keyed'] = '-1';

            if (isset($keyups[$n])) {
                $curNodes[$n]['keyed'] = ($keyups[$n]['isKeyed'] == 1) ? 'yes' : 'no';
                $curNodes[$n]['last_keyed'] = $keyups[$n]['keyed'];
            }

            if (isset($modes[$n])) {
                $curNodes[$n]['mode'] = $modes[$n]['mode'];
            } else {
                $curNodes[$n]['mode'] = $isEcholink ? 'Echolink' : 'Allstar';
            }
        }
    }
    
    $localStatsKey = 1; 
    if (!isset($curNodes[$localStatsKey])) {
        $curNodes[$localStatsKey] = [];
    }
    $curNodes[$localStatsKey]['node'] = $curNodes[$localStatsKey]['node'] ?? $queriedNode;
    $curNodes[$localStatsKey]['info'] = $curNodes[$localStatsKey]['info'] ?? getAstInfo($fp, $queriedNode);


    $curNodes[$localStatsKey]['cos_keyed'] = (($parsedVars['RPT_RXKEYED'] ?? '0') === '1') ? 1 : 0;
    $curNodes[$localStatsKey]['tx_keyed'] = (($parsedVars['RPT_TXKEYED'] ?? '0') === '1') ? 1 : 0;
    
    $curNodes[$localStatsKey]['cpu_temp'] = $mainNodeCpuTemp;
    $curNodes[$localStatsKey]['cpu_up'] = $mainNodeCpuUp;
    $curNodes[$localStatsKey]['cpu_load'] = $mainNodeCpuLoad;
    $curNodes[$localStatsKey]['ALERT'] = $mainNodeALERT;
    $curNodes[$localStatsKey]['WX'] = $mainNodeWX;
    $curNodes[$localStatsKey]['DISK'] = $mainNodeDISK;

    if (empty($conns) && count($curNodes) === 1 && isset($curNodes[$localStatsKey])) {
         $curNodes[$localStatsKey]['info'] = "NO CONNECTIONS";
    } elseif (empty($curNodes) && !empty($queriedNode)) {
        $curNodes[$localStatsKey]['node'] = $queriedNode;
        $curNodes[$localStatsKey]['info'] = "NO CONNECTIONS";
        $curNodes[$localStatsKey]['cos_keyed'] = 0;
        $curNodes[$localStatsKey]['tx_keyed'] = 0;
    }
    
    return $curNodes;
}
?>
