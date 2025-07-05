<?php


include("session.inc");
include("user_files/global.inc");
include("common.inc");
include("authusers.php");
include("authini.php");


if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("CTRLUSER"))) {
    die("<br><h3>ERROR: You Must login to use the 'Control Panel' function!</h3>");
}


$node = @trim(strip_tags($_GET['node']));
$localnode = @trim(strip_tags($_POST['localnode']));


if ($localnode !== '') {
    $node = $localnode;
}


if (!is_numeric($node)) {
    die("Please provide a properly formated URI. (ie controlpanel.php?node=1234)");
}


$title = "AllStar $node Control Panel";


if ($_SESSION['sm61loggedin'] === true) {


    $SUPINI = get_ini_name($_SESSION['user']);


    if (!file_exists("$SUPINI")) {
        die("Couldn't load file $SUPINI.\n");
    }
    $allmonConfig = parse_ini_file("$SUPINI", true);


    if (!file_exists("$USERFILES/controlpanel.ini")) {
        die("Couldn't load file controlpanel.ini.\n");
    }
    $cpConfig = parse_ini_file("$USERFILES/controlpanel.ini", true);


    $cpCommands = $cpConfig['general'];


    if (isset($cpConfig[$node])) {
        foreach ($cpConfig[$node] as $type => $arr) {
            if ($type == 'labels') {
                foreach ($arr as $label) {
                    $cpCommands['labels'][] = $label;
                }
            } elseif ($type == 'cmds') {
                foreach ($arr as $cmd) {
                    $cpCommands['cmds'][] = $cmd;
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $title; ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="generator" content="By hand with a text editor">
    <meta name="description" content="AllStar Control Panel">
    <meta name="keywords" content="allstar monitor, app_rpt, asterisk">
    <meta name="author" content="Tim Sawyer, WD6AWP">
    <meta name="mods" content="New features, IRLP capability, Paul Aidukas, KN2R">
    <link type="text/css" rel="stylesheet" href="supermon-ng.css">
    <link type="text/css" rel="stylesheet" href="js/jquery-ui.css">
    <script src="js/jquery.min.js"></script>
    <script src="js/jquery-ui.min.js"></script>

    <script src="js/sweetalert2.min.js"></script>
    <link rel="stylesheet" href="js/sweetalert2.min.css"/>

    <script>
        $(document).ready(function () {

            <?php if ($_SESSION['sm61loggedin'] !== true) { ?>
            Swal.fire({
                icon: 'error',
                title: 'Access Denied',
                text: 'Must login to use the Control Panel.'
            });

            <?php } else { ?>
            $("#cpMain").show();

            <?php } ?>

            $('#cpExecute').click(function () {
                var localNode = $('#localnode').val();
                var cpCommand = $('#cpSelect').val();

                $.get('controlserver.php?node=' + localNode + '&cmd=' + cpCommand, function (data) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data,
                        timer: 3000,
                        timerProgressBar: true
                    });
                });
            });
        });
    </script>
</head>
<body>
<div id="header" class="header-dynamic" style="background-image: url(<?php echo $BACKGROUND; ?>); background-color:<?php echo $BACKGROUND_COLOR; ?>; height: <?php echo $BACKGROUND_HEIGHT; ?>;">
    <div id="headerTitle" class="headerTitle-large"><i><?php echo "$CALL - $TITLE_LOGGED"; ?></i></div>
    <div id="header4Tag" class="header4Tag-large"><i><?php echo $title ?></i></div>
    <div id="header2Tag" class="header2Tag-large"><i><?php echo $TITLE3; ?></i></div>
    <div id="headerImg"><a href="https://www.allstarlink.org" target="_blank"><img src="allstarlink.jpg" width="70%" alt="Allstar Logo"></a></div>
</div>
<br>
<div id="cpMain">
    <center>Sending Command to node <?php echo $node ?></center>
    <br>
    Control command (select one): <select name="cpSelection" class="submit-large" id="cpSelect">
        <?php
        for ($i = 0; $i < count($cpCommands['labels']); $i++) {
            print "<option value=\"" . $cpCommands['cmds'][$i] . "\">" . $cpCommands['labels'][$i] . "</option>\n";
        }
        ?>
    </select>
    <input type="hidden" id="localnode" value="<?php echo $node ?>">
    <input type="button" class="submit-large" value="Execute" id="cpExecute">
    <br/><br>
    <div id="cpResult">
        </div>
</div>
<br>
<div class="bottom-left-30">
    <center>
        <input type="button" class="submit-large" Value="Close Window" onclick="self.close()">
        <br><br>
        <?php include "footer.inc"; ?>
    </center>
</div>
</body>
</html>
