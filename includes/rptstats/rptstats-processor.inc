<?php
/**
 * RPT Stats Processor
 * 
 * Handles the main processing logic for AllStar rpt stats,
 * including configuration loading, AMI connection, and stats retrieval.
 */

/**
 * Process stats request for local node
 * 
 * @param int $localnode_param Local node parameter
 */
function processLocalNodeStats($localnode_param) {
    $SUPINI = get_ini_name($_SESSION['user']);

    if (!file_exists($SUPINI)) {
        echo htmlspecialchars("ERROR: Couldn't load $SUPINI file.\n");
        echo "</pre></body></html>";
        exit();
    }

    $config = parse_ini_file($SUPINI, true);
    if ($config === false) {
        echo htmlspecialchars("ERROR: Error parsing $SUPINI file.\n");
        echo "</pre></body></html>";
        exit();
    }

    if (!isset($config[$localnode_param])) {
        echo htmlspecialchars("ERROR: Node $localnode_param is not in $SUPINI file.\n");
        echo "</pre></body></html>";
        exit();
    }

    $node_config = $config[$localnode_param];
    $fp = null;

    if (($fp = SimpleAmiClient::connect($node_config['host'])) === FALSE) {
        echo htmlspecialchars("ERROR: Could not connect to Asterisk Manager on host " . htmlspecialchars($node_config['host']) . ".\n");
        echo "</pre></body></html>";
        exit();
    }

    if (SimpleAmiClient::login($fp, $node_config['user'], $node_config['passwd']) === FALSE) {
        SimpleAmiClient::logoff($fp);
        echo htmlspecialchars("ERROR: Could not login to Asterisk Manager.\n");
        echo "</pre></body></html>";
        exit();
    }

    show_rpt_stats($fp, $localnode_param);

    SimpleAmiClient::logoff($fp);
}
