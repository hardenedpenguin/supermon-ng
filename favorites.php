<?php
include("session.inc");
include("authusers.php");
include("common.inc");
include("user_files/global.inc");
include("favini.php");

if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("FAVUSER")))  {
     die("<br><h3>ERROR: You Must login to use the 'Favorites Panel' function!</h3>");
}

$node_param = $_GET['node'] ?? '';
$node = trim(strip_tags($node_param));

if (!is_numeric($node) || $node === '') {
    die ("Please provide a properly formated URI. (ie favorites.php?node=1234)");
}

$title = "AllStar $node Favorites Panel";

$FAVINI = get_fav_ini_name($_SESSION['user']);

if (!file_exists($FAVINI))  {
    die("Couldn't load $FAVINI file.\n");
}

$cpConfig = parse_ini_file($FAVINI, true);
if ($cpConfig === false) {
    die("Error parsing $FAVINI file. Please check its syntax.\n");
}

$generalLabels = $cpConfig['general']['label'] ?? [];
$generalCmds   = $cpConfig['general']['cmd'] ?? [];

$nodeLabels = [];
$nodeCmds   = [];

if (isset($cpConfig[$node])) {
    $nodeLabels = $cpConfig[$node]['label'] ?? [];
    $nodeCmds   = $cpConfig[$node]['cmd'] ?? [];
}

$cpCommands = [
    'label' => array_merge($generalLabels, $nodeLabels),
    'cmd'   => array_merge($generalCmds, $nodeCmds)
];

?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($title); ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="generator" content="By hand with a text editor">
    <meta name="description" content="SuperMon-ng Favorites Panel">
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
    $(document).ready(function() {
        $("#cpMain").show();
        
        $('#cpExecute').click(function() {
            var localNode = $('#localnode').val();
            var cpCommand = $('#cpSelect').val();
            
            $.get('controlserverfavs.php?node=' + encodeURIComponent(localNode) + '&cmd=' + encodeURIComponent(cpCommand), function( data ) {
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
    <div id="header" class="header-dynamic" style="background-image: url(<?php echo htmlspecialchars($BACKGROUND ?? '', ENT_QUOTES, 'UTF-8'); ?>); background-color:<?php echo htmlspecialchars($BACKGROUND_COLOR ?? '', ENT_QUOTES, 'UTF-8'); ?>;<?php if (isset($BACKGROUND_HEIGHT)) echo ' height:' . htmlspecialchars($BACKGROUND_HEIGHT, ENT_QUOTES, 'UTF-8') . ';'; ?>">
        <div id="headerTitle-large"><i><?php echo htmlspecialchars(($CALL ?? '') . " - " . ($TITLE_LOGGED ?? '')); ?></i></div>
        <div id="header3Tag-large"><i><?php echo htmlspecialchars($title); ?></i></div>
        <div id="header2Tag-large"><i><?php echo htmlspecialchars($TITLE3 ?? ''); ?></i></div>
        <div id="headerImg"><a href="https://www.allstarlink.org" target="_blank"><img src="allstarlink.jpg" width="70%" class="img-borderless" alt="AllStar Logo"></a></div>
    </div>

    <div id="cpMain">
        <br>
        <center>Sending Command to node <?php echo htmlspecialchars($node); ?></center>
        <br>
        Favorite (select one): 
        <select class="submit-large" name="cpSelection" id="cpSelect">
        <?php 
        if (!empty($cpCommands['label']) && is_array($cpCommands['label']) && 
            !empty($cpCommands['cmd']) && is_array($cpCommands['cmd']) &&
            count($cpCommands['label']) === count($cpCommands['cmd'])) {
            
            for($i=0; $i < count($cpCommands['label']); $i++) {
                $cmdValue = htmlspecialchars($cpCommands['cmd'][$i], ENT_QUOTES, 'UTF-8');
                $labelDisplay = htmlspecialchars($cpCommands['label'][$i], ENT_NOQUOTES, 'UTF-8');
                echo "<option value=\"{$cmdValue}\">{$labelDisplay}</option>\n";
            }
        } else {
            if (empty($cpCommands['label']) && empty($cpCommands['cmd'])) {
                echo "<option value=\"\">No favorites configured.</option>\n";
            } else {
                echo "<option value=\"\">Error: Favorites configuration mismatch.</option>\n";
            }
        }
        ?>
        </select>
        <input type="hidden" id="localnode" value="<?php echo htmlspecialchars($node, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="button" class="submit-large" value="Send Command" id="cpExecute">
        <br/><br>
        <div id="cpResult">
        </div>
    </div>

    <div class="bottom-left-30">
        <center>
            <input type="button" class="submit-large" Value="Close Window" onclick="self.close()">
            <br><br>
            <center>
                Using the <?php echo htmlspecialchars($FAVINI); ?> file
            </center><br>
            <?php include "footer.inc"; ?>
        </center>
    </div>
</body>
</html>