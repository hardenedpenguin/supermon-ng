<?php
include("includes/session.inc");
include("authusers.php");

if (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && get_user_auth("RBTUSER")) {
   echo "<b>Rebooting Server!</b>";
   $statcmd = "sudo /usr/sbin/reboot";
   exec($statcmd);
} else {
   echo "<br><h3>ERROR: You Must login to use the 'Server REBOOT' function!</h3>";
}
?>