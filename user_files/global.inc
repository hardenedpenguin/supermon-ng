<?php
/**
 * Supermon-ng Global Configuration
 *
 * This file contains global settings for the Supermon-ng web interface.
 * Only the options below are read by the application. All other variables
 * in this file are ignored.
 *
 * HOW TO USE:
 * 1. Uncomment the settings you want to configure
 * 2. Replace placeholder values with your actual settings
 * 3. Refresh the web interface to see changes
 *
 * SUPPORTED OPTIONS (used by the app):
 * - CALL, NAME, LOCATION, TITLE2, TITLE3, SMSERVERNAME
 * - WELCOME_MSG, WELCOME_MSG_LOGGED
 * - BACKGROUND_COLOR, BACKGROUND_HEIGHT, DISPLAY_BACKGROUND
 * - CALLSIGN_COLOR, TITLE_LOGGED_COLOR, TITLE_NOT_LOGGED_COLOR
 * - LOGO_NAME, LOGO_SIZE, LOGO_POSITION_RIGHT, LOGO_POSITION_TOP, LOGO_URL
 * - MY_URL, DVM_URL
 *
 * Header background image: place a file named header-background.jpg (or .png, .gif, .webp)
 * in user_files/ to use a custom header image. Otherwise the default is used.
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
$SMSERVERNAME = "Supermon-ng";

// ========================================
// WELCOME MESSAGES
// ========================================

// Welcome message for non-logged-in users (supports HTML)
// $WELCOME_MSG = "<p style='margin-top:1em;'><center><b>Welcome to my Supermon-ng page</b></center></p>";

// Welcome message for logged-in users (supports HTML)
// $WELCOME_MSG_LOGGED = "<p style='margin-top:1em;'><center><b>Welcome back!</b></center></p>";

// ========================================
// BACKGROUND & APPEARANCE
// ========================================

// Background color (used when no custom header image; color name, hex, or RGB)
$BACKGROUND_COLOR = "black";

// Background image height for header (should match your header-background image)
$BACKGROUND_HEIGHT = "164px";

// Display background color for content areas
$DISPLAY_BACKGROUND = "black";

// ========================================
// HEADER TEXT COLORS
// ========================================

// $CALLSIGN_COLOR = "#00ff00";
// $TITLE_LOGGED_COLOR = "#ffff00";
// $TITLE_NOT_LOGGED_COLOR = "#ffffff";

// ========================================
// LOGO CONFIGURATION
// ========================================

// $LOGO_NAME = "your_logo.png";
// $LOGO_SIZE = "15%";
// $LOGO_POSITION_RIGHT = "12%";
// $LOGO_POSITION_TOP = "20%";
// $LOGO_URL = "http://www.yourwebsite.org/";

// ========================================
// URL CONFIGURATION
// ========================================

// Your main website URL (displayed in header)
// $MY_URL = "http://yourwebsite.org/";

// ========================================
// DIGITAL VOICE INTEGRATION
// ========================================

// Digital Voice Dashboard URL (button hidden if not set)
// $DVM_URL = "../dvswitch";

?>
