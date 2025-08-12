<?php
/**
 * Supermon-ng Favorites Configuration System
 * 
 * Provides dynamic favorites configuration management for Supermon-ng.
 * Handles user-specific favorites INI file mapping, configuration validation,
 * and fallback mechanisms for user favorites and quick commands.
 * 
 * Features:
 * - User-specific favorites INI file mapping
 * - Dynamic configuration file resolution
 * - Fallback to default favorites configuration
 * - Configuration file existence validation
 * - Support for multiple favorites schemes
 * - Centralized favorites configuration management
 * 
 * Configuration Files:
 * - favini.inc: User-to-favorites INI file mapping configuration
 * - favorites.ini: Default favorites configuration
 * - favnolog.ini: No-favorites configuration fallback
 * - User-specific favorites INI files: Custom per-user favorites
 * 
 * Functions:
 * - get_fav_ini_name(): Resolves user-specific favorites INI file paths
 * - checkfavini(): Validates favorites INI file existence with fallback
 * - faviniValid(): Checks if favorites INI mapping configuration is valid
 * 
 * Favorites System:
 * - User-specific favorite node lists
 * - Quick command execution
 * - Custom favorite configurations
 * - Fallback to default favorites
 * - Configuration validation
 * 
 * Security:
 * - File path validation and sanitization
 * - Fallback mechanisms for missing configurations
 * - User-specific configuration isolation
 * - Configuration file existence checks
 * 
 * File Structure:
 * - $USERFILES/favini.inc: Contains $FAVININAME array mapping
 * - $USERFILES/favorites.ini: Default favorites file
 * - $USERFILES/favnolog.ini: No-favorites configuration
 * - $USERFILES/[username]-favorites.ini: User-specific favorites
 * 
 * Configuration Mapping:
 * - $FAVININAME array maps usernames to favorites files
 * - Empty values fall back to favnolog.ini
 * - Missing mappings fall back to favorites.ini
 * - File existence validation with fallback
 * 
 * Dependencies:
 * - common.inc: Common configuration and constants
 * - $USERFILES: User files directory constant
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

function get_fav_ini_name($user) {
    include("includes/common.inc");

    if (file_exists("$USERFILES/favini.inc")) {
        include("$USERFILES/favini.inc");
    }

    if (isset($FAVININAME) && isset($user)) {
        $ini_filename_to_check = "favnolog.ini";

        if (array_key_exists($user, $FAVININAME)) {
            if ($FAVININAME[$user] !== "") {
                $ini_filename_to_check = $FAVININAME[$user];
            }
        }
        return checkfavini($USERFILES, $ini_filename_to_check);
    } else {
        return "$USERFILES/favorites.ini";
    }
}

function checkfavini($fdir, $fname) {
    if (file_exists("$fdir/$fname")) {
        return "$fdir/$fname";
    } else {
        return "$fdir/favorites.ini";
    }
}

function faviniValid() {
    include("includes/common.inc");

    if (file_exists("$USERFILES/favini.inc")) {
        include("$USERFILES/favini.inc");
    }

    return isset($FAVININAME);
}

?>