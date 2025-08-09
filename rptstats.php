<?php

include("includes/session.inc");
require_once("amifunctions.inc");
include("authusers.php");
include("includes/common.inc");
include("authini.php");

function show_rpt_stats($fp, $node)
{
    $commandOutput = SimpleAmiClient::command($fp, "rpt stats $node");

    if ($commandOutput !== false && !empty(trim($commandOutput))) {
        echo htmlspecialchars(trim($commandOutput)) . "\n";
    } else {
        echo htmlspecialchars("<NONE_OR_EMPTY_STATS>") . "\n";
    }
}

$node_param = isset($_GET['node']) ? (int)trim(strip_tags($_GET['node'])) : 0;
$localnode_param = isset($_GET['localnode']) ? (int)trim(strip_tags($_GET['localnode'])) : 0;

$isAuthenticated = (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && get_user_auth("RSTATUSER"));

if (!$isAuthenticated) {
    $error_node_identifier = "Unknown";
    if ($localnode_param > 0) {
        $error_node_identifier = $localnode_param;
    } elseif ($node_param > 0) {
        $error_node_identifier = $node_param;
    }
    $title = "AllStar 'rpt stats' for node: " . htmlspecialchars($error_node_identifier) . " - Authentication Required";
    ?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $title; ?></title>
    <!-- Modular CSS Files -->
<link type="text/css" rel="stylesheet" href="css/base.css">
<link type="text/css" rel="stylesheet" href="css/layout.css">
<link type="text/css" rel="stylesheet" href="css/menu.css">
<link type="text/css" rel="stylesheet" href="css/tables.css">
<link type="text/css" rel="stylesheet" href="css/forms.css">
<link type="text/css" rel="stylesheet" href="css/widgets.css">
<link type="text/css" rel="stylesheet" href="css/responsive.css">
<!-- Custom CSS (load last to override defaults) -->
<?php if (file_exists('css/custom.css')): ?>
<link type="text/css" rel="stylesheet" href="css/custom.css">
<?php endif; ?>
</head>
<body>
    <br><h3>ERROR: You Must login to use this function!</h3>
</body>
</html>
    <?php
    exit();
}

if ($node_param > 0) {
    header("Location: http://stats.allstarlink.org/stats/$node_param");
    exit();
} elseif ($localnode_param > 0) {
    $title = "AllStar 'rpt stats' for node: " . htmlspecialchars($localnode_param);
    ?>
<html>
<head>
<!-- Modular CSS Files -->
<link type="text/css" rel="stylesheet" href="css/base.css">
<link type="text/css" rel="stylesheet" href="css/layout.css">
<link type="text/css" rel="stylesheet" href="css/menu.css">
<link type="text/css" rel="stylesheet" href="css/tables.css">
<link type="text/css" rel="stylesheet" href="css/forms.css">
<link type="text/css" rel="stylesheet" href="css/widgets.css">
<link type="text/css" rel="stylesheet" href="css/responsive.css">
<!-- Custom CSS (load last to override defaults) -->
<?php if (file_exists('css/custom.css')): ?>
<link type="text/css" rel="stylesheet" href="css/custom.css">
<?php endif; ?>
<title><?php echo $title; ?></title>
</head>
<body>
<pre class="rptstatus"><?php

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
?>
</pre>
</body>
</html>
    <?php
} else {
    $title = "AllStar 'rpt stats' - Error";
    ?>
<html>
<head>
<!-- Modular CSS Files -->
<link type="text/css" rel="stylesheet" href="css/base.css">
<link type="text/css" rel="stylesheet" href="css/layout.css">
<link type="text/css" rel="stylesheet" href="css/menu.css">
<link type="text/css" rel="stylesheet" href="css/tables.css">
<link type="text/css" rel="stylesheet" href="css/forms.css">
<link type="text/css" rel="stylesheet" href="css/widgets.css">
<link type="text/css" rel="stylesheet" href="css/responsive.css">
<!-- Custom CSS (load last to override defaults) -->
<?php if (file_exists('css/custom.css')): ?>
<link type="text/css" rel="stylesheet" href="css/custom.css">
<?php endif; ?>
<title><?php echo htmlspecialchars($title); ?></title>
</head>
<body>
<pre>
Error: No valid node specified. Please provide a 'node' or ensure 'localnode' is correctly configured.
</pre>
</body>
</html>
    <?php
}
?>
