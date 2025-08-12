<?php
/**
 * Supermon-ng Server Reboot
 * 
 * Provides system reboot functionality for the AllStar server.
 * Allows authenticated users with proper permissions to initiate
 * a system reboot through secure command execution.
 * 
 * Features:
 * - User authentication and session validation
 * - Permission-based access control (RBTUSER permission required)
 * - Secure command execution with sudo privileges
 * - Immediate system reboot initiation
 * - Access denied messaging for unauthorized users
 * 
 * Security:
 * - Requires valid session and authentication
 * - RBTUSER permission validation required
 * - Uses sudo for privileged command execution
 * - Comprehensive access control and validation
 * - Clear error messaging for unauthorized access
 * 
 * Command Execution:
 * - Uses "sudo /usr/sbin/reboot" command
 * - Executes with system privileges
 * - Initiates immediate system reboot
 * - No confirmation dialog (immediate execution)
 * 
 * Dependencies:
 * - session.inc: Session management
 * - authusers.php: User authentication and permissions
 * 
 * Warning: This is a security-critical function that can cause
 * system downtime. Access should be carefully controlled.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

include("includes/session.inc");
include("authusers.php");

if (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && get_user_auth("RBTUSER")) {
   echo "<b>Rebooting Server!</b>";
   $statcmd = escapeshellcmd("sudo /usr/sbin/reboot");
   exec($statcmd);
} else {
   echo "<br><h3>ERROR: You Must login to use the 'Server REBOOT' function!</h3>";
}
?>