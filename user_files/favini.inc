<?php
/**
 * Supermon-ng Favorites INI File Mapping
 * 
 * This file maps usernames to specific favorites.ini configuration files.
 * Each user can have their own customized favorites and quick commands.
 * 
 * HOW TO USE:
 * 1. Uncomment the lines you want to use
 * 2. Replace 'username' with actual login usernames
 * 3. Replace 'filename.ini' with your custom favorites INI file names
 * 4. Create the corresponding favorites INI files in the user_files directory
 * 
 * DEFAULT BEHAVIOR:
 * - If no mappings are active, all users use favorites.ini
 * - If mappings exist, only mapped users get custom files
 * - Unmapped users fall back to favorites.ini
 * 
 * EXAMPLES:
 * - $FAVININAME['john'] = "john-favorites.ini";     // John uses john-favorites.ini
 * - $FAVININAME['admin'] = "admin-favorites.ini";   // Admin uses admin-favorites.ini
 * - $FAVININAME['operator'] = "operator-fav.ini";   // Operator uses operator-fav.ini
 * 
 * NOTES:
 * - File names are case-sensitive
 * - All favorites INI files must be in the user_files directory
 * - Users without mappings use the default favorites.ini
 * - Empty or commented mappings = use default favorites.ini
 */

// ========================================
// USER FAVORITES MAPPINGS
// ========================================
// Uncomment and modify the lines below to map users to specific favorites files

// Example: Map 'admin' user to admin-specific favorites
// $FAVININAME['admin'] = "admin-favorites.ini";

// Example: Map 'operator' user to operator-specific favorites
// $FAVININAME['operator'] = "operator-favorites.ini";

// Example: Map 'john' user to john-specific favorites
// $FAVININAME['john'] = "john-favorites.ini";

// Example: Map 'jane' user to jane-specific favorites
// $FAVININAME['jane'] = "jane-favorites.ini";

// ========================================
// ADD YOUR USER MAPPINGS BELOW
// ========================================
// Replace 'your_username' with actual usernames
// Replace 'your_filename.ini' with your favorites INI file names

$FAVININAME['anarchy'] = "anarchy-favorites.ini";

?>
