<?php
/*
 * This PHP script handles requests for displaying an AllstarLink "Bubble Chart".
 * It first authenticates the user. Based on POST parameters (`node` or
 * `localnode`), it determines the target Allstar node number. It then
 * generates JavaScript to open a new browser window (popup) to
 * `stats.allstarlink.org`, passing the determined node number to display
 * its status/bubble chart. If the `node` parameter is used, a brief
 * message is displayed on the current page before the popup opens.
 */
include("includes/session.inc");
include("authusers.php");

if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("BUBLUSER"))) {
    die ("<br><h3>ERROR: You Must login to use the 'Bubble Chart' function!</h3>");
}

$node_from_post = trim(strip_tags($_POST['node'] ?? ''));
$localnode_from_post = trim(strip_tags($_POST['localnode'] ?? ''));

$node_to_use = '';
$message = '';

if ($node_from_post === '') {
    $node_to_use = $localnode_from_post;
} else {
    $node_to_use = $node_from_post;
    $message = "<b>Opening Bubble Chart for node " . htmlspecialchars($node_from_post) . "</b>";
}

$stats_base_url = "http://stats.allstarlink.org/getstatus.cgi";
$full_stats_url = $stats_base_url . "?" . urlencode($node_to_use);

if ($message !== '') {
    echo $message . "<br />\n";
}

echo "<script>window.open('" . $full_stats_url . "');</script>\n";

?>