<?php
/**
 * Supermon-ng Global Configuration
 * 
 * This file contains global configuration variables for the Supermon-ng application.
 * Modify these values to customize your installation.
 */

// =============================================================================
// SYSTEM MAINTAINER INFORMATION
// =============================================================================

// Your name (as it should appear in the footer)
$NAME = "Your Name";

// Your callsign (as it should appear in the footer)
$CALL = "YOUR-CALL";

// Alternative: Use a single maintainer string instead
// $MAINTAINER = "Your Name, YOUR-CALL";

// =============================================================================
// SYSTEM INFORMATION
// =============================================================================

// System location
$LOCATION = "Your Location";

// System title (appears in browser tab and headers)
$TITLE2 = "Supermon-ng System";

// Additional title line
$TITLE3 = "AllStar Network Monitor";

// Server name
$SMSERVERNAME = "Supermon-ng Server";

// Welcome message
$WELCOME_MSG = "Welcome to Supermon-ng";

// =============================================================================
// NETWORK SETTINGS
// =============================================================================

// AllStar database URL
$ALLSTAR_DB_URL = "http://allmondb.allstarlink.org/";

// Private node mode (true/false)
$PRIVATE_NODE = false;

// WAN-only mode for direct internet connections
$WANONLY = "";

// =============================================================================
// LOGGING CONFIGURATION
// =============================================================================

// Enable session logging
$SMLOG = "yes";

// Log level (debug, info, warning, error)
$LOG_LEVEL = "info";

// =============================================================================
// SECURITY SETTINGS
// =============================================================================

// Session timeout (seconds)
$SESSION_TIMEOUT = 3600;

// CSRF token timeout (seconds)
$CSRF_TIMEOUT = 1800;

// Rate limiting (requests per minute)
$RATE_LIMIT = 60;

// =============================================================================
// CACHE SETTINGS
// =============================================================================

// Cache driver (file, redis, memcached)
$CACHE_DRIVER = "file";

// Cache TTL (seconds)
$CACHE_TTL = 300;

// =============================================================================
// DEVELOPMENT SETTINGS
// =============================================================================

// Enable debug mode (development only)
$DEBUG = false;

?>
