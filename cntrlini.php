<?php

/**
 * Gets the control panel INI file path based on the username.
 *
 * It first checks for a user-specific configuration in an array $CNTRLININAME,
 * which is expected to be defined in "$USERFILES/cntrlini.inc".
 * If the array doesn't exist, or the user isn't properly defined within it,
 * it falls back to "$USERFILES/cntrlnolog.ini" or ultimately "$USERFILES/controlpanel.ini".
 * The existence of the final INI file is verified by checkcntrlini.
 *
 * @param string $user The username to look up the INI file for.
 * @return string The full path to the determined INI file.
 */
function get_cntrl_ini_name($user) {
    include_once("common.inc");

    $cntrlIniIncPath = "$USERFILES/cntrlini.inc";
    if (file_exists($cntrlIniIncPath)) {
        include_once($cntrlIniIncPath);
    }

    if (!isset($CNTRLININAME) || !isset($user)) {
        return "$USERFILES/controlpanel.ini";
    }

    $iniFilenameToUse = "cntrlnolog.ini";

    if (array_key_exists($user, $CNTRLININAME)) {
        if (!empty($CNTRLININAME[$user])) {
            $iniFilenameToUse = $CNTRLININAME[$user];
        }
    }

    return checkcntrlini($USERFILES, $iniFilenameToUse);
}

/**
 * Checks if a specific INI file exists and returns its path, or a default path.
 *
 * Given a directory and a filename, it constructs the full path.
 * If that file exists, its path is returned.
 * Otherwise, the path to "$fdir/controlpanel.ini" is returned as a fallback.
 *
 * @param string $fdir The directory where the INI files are located.
 * @param string $fname The filename of the INI file to check.
 * @return string The full path to the existing INI file or the default controlpanel.ini.
 */
function checkcntrlini($fdir, $fname) {
    $specificIniPath = "$fdir/$fname";
    if (file_exists($specificIniPath)) {
        return $specificIniPath;
    } else {
        return "$fdir/controlpanel.ini";
    }
}

/**
 * Checks if the control INI name configuration array ($CNTRLININAME) is valid (i.e., set).
 *
 * This function attempts to include "$USERFILES/cntrlini.inc" which should define
 * the $CNTRLININAME array. It then checks if this array has been set.
 *
 * @return bool True if $CNTRLININAME is set, false otherwise.
 */
function cntrliniValid() {
    include_once("common.inc");

    $cntrlIniIncPath = "$USERFILES/cntrlini.inc";
    if (file_exists($cntrlIniIncPath)) {
        include_once($cntrlIniIncPath);
    }
    return isset($CNTRLININAME);
}

?>