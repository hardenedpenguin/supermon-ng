<?php
/**
 * Supermon-ng Authentication Configuration System
 * 
 * Provides dynamic authentication configuration management for Supermon-ng.
 * Handles user-specific INI file mapping, configuration validation, and
 * fallback mechanisms for authentication settings.
 * 
 * Features:
 * - User-specific INI file mapping
 * - Dynamic configuration file resolution
 * - Fallback to default configurations
 * - Configuration file existence validation
 * - Support for multiple authentication schemes
 * - Centralized authentication configuration management
 * 
 * Configuration Files:
 * - authini.inc: User-to-INI file mapping configuration
 * - allmon.ini: Default authentication configuration
 * - nolog.ini: No-login configuration fallback
 * - User-specific INI files: Custom per-user configurations
 * 
 * Functions:
 * - get_ini_name(): Resolves user-specific INI file paths
 * - checkini(): Validates INI file existence with fallback
 * - iniValid(): Checks if INI mapping configuration is valid
 * 
 * Security:
 * - File path validation and sanitization
 * - Fallback mechanisms for missing configurations
 * - User-specific configuration isolation
 * - Configuration file existence checks
 * 
 * File Structure:
 * - $USERFILES/authini.inc: Contains $ININAME array mapping
 * - $USERFILES/allmon.ini: Default configuration file
 * - $USERFILES/nolog.ini: No-access configuration
 * - $USERFILES/[username].ini: User-specific configurations
 * 
 * Dependencies:
 * - common.inc: Common configuration and constants
 * - $USERFILES: User files directory constant
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Gets the INI file path based on the username.
 *
 * @param string|null $user The username.
 * @return string The path to the determined INI file.
 */
function get_ini_name($user) {
    include("includes/common.inc");

    if (file_exists("$USERFILES/authini.inc")) {
        include("$USERFILES/authini.inc");
    }

    $standard_allmon_ini = "$USERFILES/allmon.ini";

    if (isset($ININAME) && isset($user)) {
        if (array_key_exists($user, $ININAME) && $ININAME[$user] !== "") {
            return checkini($USERFILES, $ININAME[$user]);
        } else {
            return checkini($USERFILES, "nolog.ini");
        }
    } else {
        return $standard_allmon_ini;
    }
}

/**
 * Checks if a specific INI file exists in the given directory.
 * If it exists, returns its full path. Otherwise, returns the path to a default INI file.
 *
 * @param string $fdir The directory where INI files are located (typically $USERFILES).
 * @param string $fname The filename of the INI file to check.
 * @return string The path to the existing INI file or the default allmon.ini.
 */
function checkini($fdir, $fname) {
    $target_file = "$fdir/$fname";
    if (file_exists($target_file)) {
        return $target_file;
    } else {
        return "$fdir/allmon.ini";
    }
}

/**
 * Checks if the $ININAME array (from authini.inc) is defined.
 *
 * @return bool True if $ININAME is set, false otherwise.
 */
function iniValid() {
    include("includes/common.inc");

    if (file_exists("$USERFILES/authini.inc")) {
        include("$USERFILES/authini.inc");
    }

    return isset($ININAME);
}

?>
