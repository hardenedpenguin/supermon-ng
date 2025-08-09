<?php
/**
 * RPT Stats AMI Functions
 * 
 * Handles AMI communication for AllStar rpt stats commands.
 */

/**
 * Show RPT statistics for a specific node
 * 
 * @param resource $fp AMI connection
 * @param int $node Node number to get stats for
 */
function show_rpt_stats($fp, $node) {
    $commandOutput = SimpleAmiClient::command($fp, "rpt stats $node");

    if ($commandOutput !== false && !empty(trim($commandOutput))) {
        echo htmlspecialchars(trim($commandOutput)) . "\n";
    } else {
        echo htmlspecialchars("<NONE_OR_EMPTY_STATS>") . "\n";
    }
}
