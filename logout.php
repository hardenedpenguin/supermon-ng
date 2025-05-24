<?php
	include("session.inc");

	$userToLog = isset($_SESSION['user']) ? $_SESSION['user'] : '';
	logoutUser($userToLog);

	session_unset();

	$_SESSION['sm61loggedin'] = false;
	$_SESSION['user'] = "";

	print "Logged out.";

function logoutUser($user) {
    include_once("user_files/global.inc");
    include_once("common.inc");

    if (isset($SMLOG) && $SMLOG == "yes") {
        $hostname = gethostname();
        if ($hostname !== false) {
            $parts = explode('.', $hostname);
            $hostname = $parts[0];
        } else {
            $hostname = 'unknown-host';
        }

        $myday = date('l, F j, Y T - H:i:s');

        $wrtStr = "Supermon2<b> logout </b>Host-" . $hostname .
                  " <b>user-" . $user . " </b>at " . $myday . "\n";

        if (isset($SMLOGNAME)) {
            file_put_contents($SMLOGNAME, $wrtStr, FILE_APPEND);
        }
    }
}

?>