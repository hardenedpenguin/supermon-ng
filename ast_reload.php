<?php
/**
 * Supermon-ng Asterisk Configuration Reload
 * 
 * Provides functionality to reload Asterisk configuration files remotely
 * through AMI (Asterisk Manager Interface). Allows authenticated users to
 * reload rpt.conf, iax.conf, and extensions.conf without direct server access.
 * 
 * Features:
 * - Remote Asterisk configuration reload
 * - User authentication and authorization (ASTRELUSER permission required)
 * - AMI connection management and authentication
 * - Multiple configuration file reload support
 * - Real-time status reporting
 * - Secure command execution
 * - Comprehensive error handling
 * 
 * Configuration Files Reloaded:
 * - rpt.conf: AllStar repeater configuration
 * - iax.conf: IAX2 protocol configuration
 * - extensions.conf: Dialplan configuration
 * 
 * Security:
 * - Session validation and authentication required
 * - ASTRELUSER permission validation
 * - Input sanitization and validation
 * - Secure AMI connections
 * - Command execution validation
 * - Comprehensive error reporting
 * 
 * AMI Commands:
 * - "rpt reload": Reloads rpt.conf configuration
 * - "iax2 reload": Reloads iax.conf configuration
 * - "extensions reload": Reloads extensions.conf configuration
 * 
 * Process Flow:
 * 1. User authentication and permission validation
 * 2. Input parameter validation (localnode, button action)
 * 3. Configuration file loading and validation
 * 4. AMI connection establishment
 * 5. Configuration reload execution
 * 6. Status reporting and cleanup
 * 
 * Dependencies:
 * - session.inc: Session management
 * - amifunctions.inc: AMI connection functions
 * - authusers.php: User authentication
 * - authini.php: Authentication configuration
 * 
 * Warning: This function can cause temporary service interruption
 * during configuration reload. Use with caution.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include('includes/session.inc');
include('includes/amifunctions.inc');
include('authusers.php');
include('authini.php');

if (!isset($_SESSION['sm61loggedin']) || $_SESSION['sm61loggedin'] !== true || !get_user_auth("ASTRELUSER")) {
    die ("<br><h3>ERROR: You must login with ASTRELUSER privileges to use this function!</h3>");
}

$localnode = isset($_POST['localnode']) ? trim(strip_tags($_POST['localnode'])) : null;
$buttonAction = isset($_POST['button']) ? trim(strip_tags($_POST['button'])) : null;

if (empty($localnode)) {
    die("<br><h3>ERROR: 'localnode' not specified.</h3>");
}

if (empty($buttonAction)) {
    die("<br><h3>ERROR: No action specified (button press expected).</h3>");
}

$supIniFile = get_ini_name($_SESSION['user']);

if (!file_exists($supIniFile)) {
    die("Couldn't load supervisor INI file: $supIniFile");
}

$config = parse_ini_file($supIniFile, true);

if (!isset($config[$localnode])) {
    die("Node $localnode is not defined in $supIniFile.");
}

$amiHost = $config[$localnode]['host'] ?? null;
$amiUser = $config[$localnode]['user'] ?? null;
$amiPass = $config[$localnode]['passwd'] ?? null;

if (empty($amiHost) || empty($amiUser) || empty($amiPass)) {
    die("AMI host, user, or password not configured for node $localnode in $supIniFile.");
}

$fp = SimpleAmiClient::connect($amiHost);
if ($fp === FALSE) {
    die("Could not connect to Asterisk Manager at $amiHost for node $localnode.");
}

if (SimpleAmiClient::login($fp, $amiUser, $amiPass) === FALSE) {
    SimpleAmiClient::logoff($fp);
    die("Could not login to Asterisk Manager for node $localnode with user $amiUser.");
}

$outputMessages = [];

if ($buttonAction == 'astreload') {
    $outputMessages[] = "<b>Reloading configurations for node - $localnode:</b>";

    if (SimpleAmiClient::command($fp, "rpt reload") !== false) {
        $outputMessages[] = "- rpt.conf reloaded successfully.";
    } else {
        $outputMessages[] = "- FAILED to reload rpt.conf.";
    }
    sleep(1);

    if (SimpleAmiClient::command($fp, "iax2 reload") !== false) {
        $outputMessages[] = "- iax.conf reloaded successfully.";
    } else {
        $outputMessages[] = "- FAILED to reload iax.conf.";
    }
    sleep(1);

    if (SimpleAmiClient::command($fp, "extensions reload") !== false) {
        $outputMessages[] = "- extensions.conf reloaded successfully.";
    } else {
        $outputMessages[] = "- FAILED to reload extensions.conf.";
    }
} else {
    $outputMessages[] = "Unknown action: " . htmlspecialchars($buttonAction);
}

SimpleAmiClient::logoff($fp);

foreach ($outputMessages as $message) {
    print $message . "<br>\n";
}

?>