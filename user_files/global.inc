<?php
// Supermon2 global.inc file
// Set the values below to your parameters
// ONLY change text between quotes
// Remove leading // before $ to 
// make variable active.
// MAKE SURE there is a terminating semicolon!
// Change ONLY data between the quotes.
// If SUpermon2 does not start it is probably because
// you have syntax wrong in this file! Check
// /var/log/httpd/error_log

// These messages if defined appear below the header and above the node grid

// Welcome Message when not logged in
//$WELCOME_MSG="<p style='margin-top:1em;'><center><b>Welcome to my Supermon2 page</b><br><br>You can use any valid html code here<br>
//including color changes, links, email contacts<br>or anything else you want to advertise</center></p>";
//
// Welcome message when logged in
//$WELCOME_MSG_LOGGED="<p style='margin-top:1em;'><center><b>and likewise you can put info here for login guests</b></p>";
//
// Your callsign
$CALL = "CALL";
//
// Your name
$NAME = "NAME";
//
// Your location
$LOCATION = "<span style=\"color: #ff0000\">LOCATION</span>";
//
// Second line header title
$TITLE2 = "RPi2-3-4 Node";
//
// Third line header title
$TITLE3 = "Allstar/IRLP/Echolink System Manager";
//
// Background image -  image located in user_files directory
// By default it is a symbolic link to "Cape_Coral_yard.jpg"
// called background.jpg. To change remove the symbolic
// link and make a new one to your image file 
// 
// Leaving $BACKGROUND null "" results in BACKGROUND_COLOR (below)
$BACKGROUND = "user_files/background.jpg";
//
// Background color if no image
$BACKGROUND_COLOR = "blue";
//
// Height of background - matches image height
$BACKGROUND_HEIGHT = "131px";
//
// Maximum number of connections to show per node
// undefined or "0" = show all
//$Displayed_Nodes = "0";
//
// Show Connection totals (xx of yy) 
// "0" or undefined = no display, "1" = display
//$Display_Count = "0";
//
// Show ALL or Omit Never Keyed
// "0" = Omit Never Keyed, "1" or undefined = Show All 
//$Show_All = "1"
//
// Prefix to Name in Page title. Often Supermon
// "Node# -"
$SMSERVERNAME = "";
//
// NOTICE LOCALZIP for weather now defined in 
// /usr/local/sbin/supermon/node_info.ini
// See manual
//
// Refresh delay as of Supermon2 V1.06 is no longer needed
// or supported.
//
// Enable [Database] button For quick review of entire AllStar
// internal database.
//
$DATABASE_TXT = "/tmp/database.txt";
//
// The following commands are obsolete starting at Supermon2 V1.08
// and no longer used. See V1.08 update and manual
//
//  $STREAMING_NODE 
//  $ARCHIVING_NODE
//  $ARCHIVE_DIR 
//  $ARCHIVE_URL
//
// When your RPi2-3 system is running on a LAN network with a router/firewall,
// you do NOT need this variable set!
// If your RPi2-3 system is directly connected to the Internet without a firewall/router,
// Supermon can hang trying to get both the WAN and LAN addresses.
// Set this variable to “yes” to disable checking for a LAN address and report only the
// WAN address. This will eliminate the hanging of Supermon in this case.
//
$WANONLY = "";
//
// Display Background Color
// If not defined it is white
// Use name or code, Google html color
// Shades of gray look best
//
$DISPLAY_BACKGROUND = "#abb2b9";
//$DISPLAY_BACKGROUND = "black";
//
// Add logos to header - See manual
// for more info
//
// Image file name
//$LOGO_NAME="";
//
// Size adjustment
//$LOGO_SIZE="15%";
//
// Logo Positioning
//$LOGO_POSITION_RIGHT="12%";
//$LOGO_POSITION_TOP="20%";
//
// Logo URL
//$LOGO_URL="http://www.someplace.org/>";
//
// Add URL's for node info.
//
// Add header CALL URL
//$MY_URL = "http://someplace.org>";
//
// Each displayed node can have a url
// Node URL replace with $URL_node
// where node is a valid node.
//$URL_1998 = "https://someplace.org";
//$URL_1999 = "http://someplace.org>";
//
// Log Supermon2 login/out sessions
// Set to yes or no or make line a comment for no.
$SMLOG = "yes";

?>
