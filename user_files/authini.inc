<?php
/**
 * Supermon-ng Authentication INI File Mapping
 * 
 * This file maps usernames to specific allmon.ini configuration files.
 * Each user can have their own customized configuration file.
 * 
 * HOW TO USE:
 * 1. Uncomment the lines you want to use
 * 2. Replace 'username' with actual login usernames
 * 3. Replace 'filename.ini' with your custom INI file names
 * 4. Create the corresponding INI files in the user_files directory
 * 
 * EXAMPLES:
 * - $ININAME['john'] = "john-allmon.ini";     // John uses john-allmon.ini
 * - $ININAME['admin'] = "admin-config.ini";   // Admin uses admin-config.ini
 * - $ININAME['operator'] = "allmon.ini";      // Operator uses default allmon.ini
 * 
 * NOTES:
 * - If no mapping exists for a user, they will use the default allmon.ini
 * - File names are case-sensitive
 * - All INI files must be in the user_files directory
 * - Users without mappings fall back to allmon.ini
 */

// ========================================
// USER CONFIGURATION MAPPINGS
// ========================================
// Uncomment and modify the lines below to map users to specific INI files

// Example: Map 'admin' user to admin-specific configuration
// $ININAME['admin'] = "admin-allmon.ini";

// Example: Map 'operator' user to operator-specific configuration  
// $ININAME['operator'] = "operator-allmon.ini";

// Example: Map 'john' user to john-specific configuration
// $ININAME['john'] = "john-allmon.ini";

// Example: Map 'jane' user to jane-specific configuration
// $ININAME['jane'] = "jane-allmon.ini";

// ========================================
// ADD YOUR USER MAPPINGS BELOW
// ========================================
// Replace 'your_username' with actual usernames
// Replace 'your_filename.ini' with your INI file names

$ININAME['anarchy'] = "anarchy-allmon.ini";

?>
