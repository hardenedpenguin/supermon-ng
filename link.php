<?php
/**
 * Supermon-ng Main Link Interface
 * 
 * This is the primary interface for Supermon-ng, providing comprehensive
 * AllStar node monitoring, control, and management functionality.
 * Serves as the main dashboard for authenticated users to interact with
 * AllStar nodes through a web-based interface.
 * 
 * Features:
 * - Real-time node status monitoring and display
 * - Node connection management (connect, disconnect, monitor)
 * - DTMF command execution for remote node control
 * - Favorites management (add, delete, execute)
 * - Configuration editor access
 * - System restart functionality
 * - Node information display and database access
 * - External AllStarLink website integration
 * - Responsive design for mobile and desktop
 * - AJAX-based real-time updates
 * - User permission-based feature access
 * 
 * Security:
 * - Session validation and authentication required
 * - Permission-based feature access (CONNECTUSER, DTMFUSER, etc.)
 * - CSRF protection for form submissions
 * - Input validation and sanitization
 * - Secure AMI connections for node operations
 * - User-specific favorites and configuration files
 * 
 * Interface Components:
 * - Node selection dropdown with search functionality
 * - Control buttons for various node operations
 * - Real-time status tables with color-coded indicators
 * - Favorites panel for quick access to common operations
 * - Configuration editor for system settings
 * - External links to AllStarLink resources
 * 
 * Node Status Indicators:
 * - Idle (gColor): Normal operation
 * - PTT (tColor): Push-to-talk active
 * - COS (lColor): Carrier-operated squelch active
 * 
 * Dependencies:
 * - session.inc: Session management
 * - global.inc: Global configuration
 * - common.inc: Common functions and constants
 * - authusers.php: User authentication
 * - authini.php: Authentication configuration
 * - link/*.inc: Link-specific functionality modules
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

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
