<?php
include("includes/session.inc");
include("authusers.php");
include("includes/common.inc");
?>
<html>
<head>
<title>CPU and System Status</title>
<!-- Modular CSS Files -->
<link rel="stylesheet" type="text/css" href="css/base.css">
<link rel="stylesheet" type="text/css" href="css/layout.css">
<link rel="stylesheet" type="text/css" href="css/menu.css">
<link rel="stylesheet" type="text/css" href="css/tables.css">
<link rel="stylesheet" type="text/css" href="css/forms.css">
<link rel="stylesheet" type="text/css" href="css/widgets.css">
<link rel="stylesheet" type="text/css" href="css/responsive.css">
<!-- Custom CSS (load last to override defaults) -->
<link rel="stylesheet" type="text/css" href="css/custom.css">
</head>
<body class="cpustats">
<?php
    if (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && get_user_auth("CSTATUSER")) {
        echo "<pre>";

        $commands_to_run = [
            "/usr/bin/date",
            "export TERM=vt100 && sudo $USERFILES/sbin/ssinfo - ",
            "/usr/bin/ip a",
            "$USERFILES/sbin/din",
            "/usr/bin/df -hT",
            "export TERM=vt100 && sudo /usr/bin/top -b -n1"
        ];

        foreach ($commands_to_run as $cmd) {
            echo "Command: " . htmlspecialchars($cmd) . "\n";
            echo "-----------------------------------------------------------------\n";
            ob_start();
            passthru($cmd);
            $output = ob_get_clean();
            echo htmlspecialchars($output);
            echo "\n\n";
        }
        echo "</pre>";

    } else {
        echo ("<br><h3>ERROR: You Must login to use this function!</h3>");
    }
?>
</body>
</html>
