<?php
include("includes/session.inc");
include("authusers.php");

if (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true) {

    $button = trim(strip_tags($_POST['button'] ?? ''));
    $out = [];

    if ($button === 'astaron' && get_user_auth("ASTSTRUSER")) {
        print "<b>Starting up AllStar... </b> ";
        exec('sudo /usr/bin/astup.sh', $out);
        print_r($out);
    } elseif ($button === 'astaroff' && get_user_auth("ASTSTPUSER")) {
        print "<b>Shutting down AllStar... </b> ";
        exec('sudo /usr/bin/astdn.sh', $out);
        print_r($out);
    }

} else {
    print "<br><h3>ERROR: You Must login to use the 'AST START' or 'AST STOP' functions!</h3>";
}

?>