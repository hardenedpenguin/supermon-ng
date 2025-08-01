<?php

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