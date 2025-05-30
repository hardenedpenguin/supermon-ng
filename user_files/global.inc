<?php

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
// Your location, example Houston, Texas
$LOCATION = "<span style=\"color: #ff0000\">LOCATION</span>";
//
// Second line header title
$TITLE2 = "ASL3+ Management Dashboard";
//
// Third line header title
$TITLE3 = "AllStarLink/IRLP/EchoLink/Digital - Bridging Control Center";
//
// Leaving $BACKGROUND null "" results in BACKGROUND_COLOR (below)
$BACKGROUND = "user_files/background.jpg";
//
// Background color if no image
$BACKGROUND_COLOR = "black";
//
// Height of background - matches image height
$BACKGROUND_HEIGHT = "164px";
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
// Enable [Database] button For quick review of entire AllStar
// internal database.
//
$DATABASE_TXT = "/tmp/database.txt";
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
// Display Background Color, If not defined it is white
// Use name or code, Google html color shades of gray look best
//
$DISPLAY_BACKGROUND = "black";
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

// Enable or Disable hamclock, set url to use to embed
$HAMCLOCK_ENABLED = "False";
$HAMCLOCK_URL = "";

// Provide a url for the Digital Dashboard
// Dashboard button will not displayed if not configured!!
// $DVM_URL = "../dvswitch";

?>
