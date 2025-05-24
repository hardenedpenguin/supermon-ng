<?php

/**
 * Gets the INI file path based on the username.
 *
 * @param string|null $user The username.
 * @return string The path to the determined INI file.
 */
function get_ini_name($user) {
    include("common.inc");

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
    include("common.inc");

    if (file_exists("$USERFILES/authini.inc")) {
        include("$USERFILES/authini.inc");
    }

    return isset($ININAME);
}

?>
