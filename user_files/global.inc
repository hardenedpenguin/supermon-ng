<?php
/**
 * Supermon-ng Global Configuration
 * 
 * This file contains global settings and configuration options for Supermon-ng.
 * All settings here apply to the entire system and affect the user interface,
 * appearance, and functionality of the Supermon-ng web interface.
 * 
 * HOW TO USE:
 * 1. Uncomment the settings you want to configure
 * 2. Replace placeholder values with your actual settings
 * 3. Test your changes by refreshing the web interface
 * 4. Refer to the documentation for detailed explanations
 * 
 * IMPORTANT NOTES:
 * - All paths should be absolute or relative to the Supermon-ng root
 * - HTML is supported in text fields for formatting
 * - Colors can be specified as names, hex codes, or RGB values
 * - File paths must be accessible by the web server
 */

// ========================================
// BASIC IDENTITY & INFORMATION
// ========================================

// Your callsign (displayed in header and titles)
$CALL = "YOURCALL";

// Your name (displayed in various locations)
$NAME = "Your Name";

// Your location (supports HTML formatting)
$LOCATION = "<span style=\"color: #ffffff\">Your Location</span>";

// ========================================
// PAGE TITLES & HEADERS
// ========================================

// Second line header title (main title)
$TITLE2 = "ASL3+ Management Dashboard";

// Third line header title (subtitle)
$TITLE3 = "AllStarLink/IRLP/EchoLink/Digital - Bridging Control Center";

// Prefix for page titles (appears before node numbers)
// Example: "Supermon - " would show "Supermon - 546051"
$SMSERVERNAME = "Supermon-ng";

// ========================================
// WELCOME MESSAGES
// ========================================

// Welcome message for non-logged-in users
// Supports full HTML formatting
// $WELCOME_MSG = "<p style='margin-top:1em;'><center><b>Welcome to my Supermon-ng page</b><br><br>You can use any valid HTML code here<br>including color changes, links, email contacts<br>or anything else you want to advertise</center></p>";

// Welcome message for logged-in users
// Supports full HTML formatting
// $WELCOME_MSG_LOGGED = "<p style='margin-top:1em;'><center><b>Welcome back! Here's your personalized dashboard</b><br><br>Customize this message for your logged-in users</center></p>";

// ========================================
// BACKGROUND & APPEARANCE
// ========================================

// Background image file (relative to user_files directory)
// Leave empty ("") to use background color instead
$BACKGROUND = "user_files/background.jpg";

// Background color (used when no image is specified)
// Can be color name, hex code, or RGB value
$BACKGROUND_COLOR = "black";

// Background image height (should match your image)
$BACKGROUND_HEIGHT = "164px";

// Display background color for content areas
// Use color names or hex codes (gray shades work well)
$DISPLAY_BACKGROUND = "black";

// ========================================
// HEADER TEXT COLORS
// ========================================

// Color for callsign text in header
// Can be color name, hex code, or RGB value
// $CALLSIGN_COLOR = "#00ff00";

// Color for title text when user is logged in
// Can be color name, hex code, or RGB value
// $TITLE_LOGGED_COLOR = "#ffff00";

// Color for title text when user is not logged in
// Can be color name, hex code, or RGB value
// $TITLE_NOT_LOGGED_COLOR = "#ffffff";

// ========================================
// LOGO CONFIGURATION
// ========================================

// Logo image file name (relative to user_files directory)
// $LOGO_NAME = "your_logo.png";

// Logo size adjustment (percentage of original size)
// $LOGO_SIZE = "15%";

// Logo positioning (percentage from right edge)
// $LOGO_POSITION_RIGHT = "12%";

// Logo positioning (percentage from top edge)
// $LOGO_POSITION_TOP = "20%";

// Logo clickable URL (where logo links to)
// $LOGO_URL = "http://www.yourwebsite.org/";

// ========================================
// URL CONFIGURATION
// ========================================

// Your main website URL (displayed in header)
// $MY_URL = "http://yourwebsite.org/";

// Node-specific URLs (replace NODE with actual node number)
// These URLs appear as clickable links for each node
// $URL_1998 = "https://yournode1998.org";
// $URL_1999 = "http://yournode1999.org/";
// $URL_546051 = "https://w5gle.org";

// ========================================
// DATABASE & SYSTEM FEATURES
// ========================================

// Database file path for [Database] button
// Enables quick review of entire AllStar internal database
// Set to empty ("") to disable the Database button
$DATABASE_TXT = "/tmp/database.txt";

// Network configuration for direct internet connections
// Set to "yes" if your system is directly connected to internet without firewall
// This prevents Supermon from hanging when checking LAN/WAN addresses
// Leave empty ("") for normal LAN/firewall setups
$WANONLY = "";

// ========================================
// LOGGING CONFIGURATION
// ========================================

// Enable/disable Supermon-ng login/out session logging
// Options: "yes", "no", or comment out for no logging
$SMLOG = "yes";

// ========================================
// DIGITAL VOICE INTEGRATION
// ========================================

// Digital Voice Dashboard URL
// Dashboard button will not be displayed if not configured
// $DVM_URL = "../dvswitch";

// ========================================
// CUSTOMIZATION EXAMPLES
// ========================================

// Example: Custom welcome message with styling
// $WELCOME_MSG = "<div style='background: #1a1a1a; padding: 20px; border-radius: 10px; margin: 20px;'><h2 style='color: #00ff00;'>Welcome to W5GLE AllStar Network</h2><p style='color: #ffffff;'>Monitor and control our AllStar nodes from anywhere in the world.</p></div>";

// Example: Node-specific URLs
// $URL_546051 = "https://w5gle.org";
// $URL_546052 = "https://w5gle.org/hub";

// Example: Custom logo configuration
// $LOGO_NAME = "w5gle_logo.png";
// $LOGO_SIZE = "20%";
// $LOGO_POSITION_RIGHT = "10%";
// $LOGO_POSITION_TOP = "15%";
// $LOGO_URL = "https://w5gle.org";

?>
