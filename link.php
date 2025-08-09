<?php
include("includes/session.inc");

include("user_files/global.inc");
include("includes/common.inc");
include_once("authusers.php");
include_once("authini.php");
include("includes/link/link-functions.inc");
include("includes/link/link-config.inc");
include("includes/link/link-ui.inc");
include("includes/link/link-javascript.inc");
include("includes/link/link-tables.inc");

// Initialize link page and get configuration
list($nodes, $config, $astdb, $displayPrefs, $parms) = initializeLinkPage();

// Extract display preferences
$Displayed_Nodes = $displayPrefs['Displayed_Nodes'];
$Display_Count = $displayPrefs['Display_Count'];
$Show_All = $displayPrefs['Show_All'];
$Show_Detail = $displayPrefs['Show_Detail'];

include("includes/header.inc");

$isDetailed = ($Show_Detail == 1);
$SUBMITTER   = $isDetailed ? "submit" : "submit-large";
$SUBMIT_SIZE = $isDetailed ? "submit" : "submit-large";
$TEXT_SIZE   = $isDetailed ? "text-normal" : "text-large";


// Render welcome message
renderWelcomeMessage();



?>

<?php
// Render monitoring JavaScript
renderMonitoringJavaScript($parms, $displayPrefs);
?>

<?php
// Render control panel if user is logged in
if (($_SESSION['sm61loggedin'] ?? false) === true) {
    renderControlPanel($nodes, $astdb, $displayPrefs);
}

echo "<div id=\"list_link\"></div>";

// Render bottom utility buttons
renderBottomButtons($displayPrefs);

// Render node tables
renderNodeTables($nodes, $astdb, $displayPrefs);

// Render HamClock iframe if enabled
renderHamClock();

// Render detailed view spinner if enabled
if ($isDetailed) {
    print "<div id=\"spinny\"></div>";
}

// Render user information footer
renderUserInfo($displayPrefs);

include "includes/footer.inc";
?>
