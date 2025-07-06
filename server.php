<?php
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

include('amifunctions.inc');
include('nodeinfo.inc');
include("user_files/global.inc");
include("common.inc");

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
        
        // Check if socket is still valid before using it
        if (!is_resource($hostFp) || get_resource_type($hostFp) !== 'stream') {
            error_log("Main loop: Socket for node $node is not a valid resource, skipping");
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
        error_log("getNode: XStat fwrite FAILED for node $node - bytes written: " . ($xstatBytesWritten === false ? 'false' : $xstatBytesWritten));
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
        error_log("getNode: SawStat fwrite FAILED for node $node - bytes written: " . ($sawStatBytesWritten === false ? 'false' : $sawStatBytesWritten));
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
