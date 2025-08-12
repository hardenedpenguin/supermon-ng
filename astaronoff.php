<?php
/**
 * Supermon-ng Asterisk Start/Stop Control
 * 
 * Provides functionality to start and stop the AllStar Asterisk service
 * remotely through secure command execution. Allows authenticated users to
 * control the Asterisk service state without direct server access.
 * 
 * Features:
 * - Remote Asterisk service start/stop control
 * - User authentication and authorization
 * - Permission-based access control (ASTSTRUSER/ASTSTPUSER)
 * - Secure command execution with sudo privileges
 * - Real-time status reporting
 * - Comprehensive error handling
 * 
 * Commands:
 * - Start AllStar: "sudo /usr/bin/astup.sh"
 * - Stop AllStar: "sudo /usr/bin/astdn.sh"
 * 
 * Security:
 * - Session validation and authentication required
 * - ASTSTRUSER permission for start operations
 * - ASTSTPUSER permission for stop operations
 * - Input sanitization and validation
 * - Secure command execution with sudo
 * - Comprehensive access control
 * 
 * Button Actions:
 * - astaron: Starts AllStar service (requires ASTSTRUSER permission)
 * - astaroff: Stops AllStar service (requires ASTSTPUSER permission)
 * 
 * Process Flow:
 * 1. User authentication and session validation
 * 2. Button action validation and sanitization
 * 3. Permission checking for specific action
 * 4. Secure command execution
 * 5. Status reporting and output display
 * 
 * Dependencies:
 * - session.inc: Session management
 * - authusers.php: User authentication and permissions
 * - External scripts: astup.sh, astdn.sh
 * 
 * Warning: This function can cause service interruption.
 * Start/stop operations should be performed with caution.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("authusers.php");

if (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true) {

    $button = trim(strip_tags($_POST['button'] ?? ''));
    $out = [];

    if ($button === 'astaron' && get_user_auth("ASTSTRUSER")) {
        print "<b>Starting up AllStar... </b> ";
        exec(escapeshellcmd('sudo /usr/bin/astup.sh'), $out);
        print_r($out);
    } elseif ($button === 'astaroff' && get_user_auth("ASTSTPUSER")) {
        print "<b>Shutting down AllStar... </b> ";
        exec(escapeshellcmd('sudo /usr/bin/astdn.sh'), $out);
        print_r($out);
    }

} else {
    print "<br><h3>ERROR: You Must login to use the 'AST START' or 'AST STOP' functions!</h3>";
}

?>