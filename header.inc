<?php
include("user_files/global.inc");
include("common.inc");

if (!isset($SHOWPW)) {
	$SHOWPW = true;
}

if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = "";
}

$Displayed_Nodes = null;
$Display_Count = null;
$Show_All = null;
$Show_Detail = "1";

if (isset($_COOKIE['display-data'])) {
    foreach ($_COOKIE['display-data'] as $name => $value) {
        $name_clean = htmlspecialchars($name);
        $value_clean = htmlspecialchars($value);
        switch ($name_clean) {
            case "number-displayed":
               $Displayed_Nodes = $value_clean;
               break;
            case "show-number":
               $Display_Count = $value_clean;
               break;
            case "show-all":
               $Show_All = $value_clean;
               break;
            case "show-detailed":
               $Show_Detail = $value_clean;
               break;
        }
    }
}

if (!isset($_SESSION['sm61loggedin'])) {
    $_SESSION['sm61loggedin'] = false;
}

$uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode(basename($uri_path));

if (!empty($SMSERVERNAME)) {
    $pageTitle = $SMSERVERNAME . " | Supermon-ng | ";
} else {
    $pageTitle = strtoupper($_SERVER['SERVER_NAME']) . " | Supermon | ";
}

if (preg_match("/voter\.php\?node=(\d+)$/", $_SERVER['REQUEST_URI'], $matches)) {
    $pageTitle .= "RTCM " . $matches[1];
} elseif (preg_match("/index\.php\?nodes=(.+)$/", $_SERVER['REQUEST_URI'], $matches)) {
    $pageTitle .= $matches[1];
} elseif (preg_match("/link\.php\?nodes=(.+)$/", $_SERVER['REQUEST_URI'], $matches)) {
    $pageTitle .= $matches[1];
} elseif (strpos($uri, "about") !== false) {
    $pageTitle .= "About";
}

$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$home = ($script_dir == '/' || $script_dir == '\\') ? '/' : $script_dir;

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// Content Security Policy
$csp = "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
        "style-src 'self' 'unsafe-inline'; " .
        "img-src 'self' data:; " .
        "font-src 'self'; " .
        "connect-src 'self'; " .
        "frame-ancestors 'none';";
header("Content-Security-Policy: " . $csp);

?>
<!DOCTYPE html>
<html>
<head>
<title><?php echo htmlspecialchars($pageTitle); ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="generator" content="By hand with vi text editor">
<meta name="description" content="AllStar node manager">
<meta name="keywords" content="allstar monitor, app_rpt, asterisk">
<meta name="robots" content="noindex, nofollow">
<meta name="author" content="Tim Sawyer, WD6AWP">
<meta name="mods" content="New features, IRLP capability, Paul Aidukas, KN2R">
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
<link rel="icon" href="favicon.ico" type="image/x-icon">
<link type="text/css" rel="stylesheet" href="supermon-ng.css">
<link type="text/css" rel="stylesheet" href="js/jquery-ui.css">
<script src="js/jquery.min.js"></script>
<script src="js/jquery-ui.min.js"></script>
<script src="js/sweetalert2.min.js"></script>
<link rel="stylesheet" href="js/sweetalert2.min.css"/>

<!-- Set JavaScript variables from PHP -->
<script type="text/javascript">
window.supermonConfig = {
    isLoggedIn: <?php echo json_encode($_SESSION['sm61loggedin'] ?? false); ?>,
    currentUser: <?php echo json_encode($_SESSION['user'] ?? ''); ?>,
    csrfToken: <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>,
    homeUrl: <?php echo json_encode($home); ?>
};
</script>

<!-- Load modern CSS -->
<link rel="stylesheet" href="js/modern-styles.css"/>

<!-- Load JavaScript files -->
<script src="js/sweetalert2-config.js"></script>
<script src="js/utils.js"></script>
<script src="js/auth.js"></script>
<script src="js/app.js"></script>
<script src="js/modern-header.js"></script>

<!-- PWA Manifest -->
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#007bff">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Supermon-ng">
<link rel="apple-touch-icon" href="/favicon.ico">

<?php
// Set header title variables for JavaScript
echo "<script>\n";
echo "var headerTitle = " . json_encode($TITLE_LOGGED ?? 'Supermon-ng') . ";\n";
echo "var headerTitle2 = " . json_encode($TITLE2 ?? '') . ";\n";
echo "var headerTitle3 = " . json_encode($TITLE3 ?? '') . ";\n";
echo "</script>\n";
?>
</head>

<body style="width: 100%; max-width: 880px; margin: auto; <?php echo isset($DISPLAY_BACKGROUND) ? "background-color:$DISPLAY_BACKGROUND;" : ""; ?>">

<?php
$header_div_style = "margin-top:5px;";
if (isset($BACKGROUND)) $header_div_style .= " background-image: url($BACKGROUND);";
if (isset($BACKGROUND_COLOR)) $header_div_style .= " background-color: $BACKGROUND_COLOR;";
if (isset($BACKGROUND_HEIGHT)) $header_div_style .= " height: $BACKGROUND_HEIGHT;";

if ($Show_Detail != "1") {
    $header_div_style = "xfont-size:22px; margin-top: 10px;" . $header_div_style;
}

print "<div id=\"header\" style=\"$header_div_style\">";

$headerTitleClass = ($Show_Detail == "1") ? "headerTitle" : "headerTitle-large";
$headerTitle2Class = ($Show_Detail == "1") ? "headerTitle2" : "headerTitle2-large";
$headerTagClass = ($Show_Detail == "1") ? "headerTag" : "headerTag-large";
$header2TagClass = ($Show_Detail == "1") ? "header2Tag" : "header2Tag-large";

$currentTitle = ($_SESSION['sm61loggedin'] === true) ? $TITLE_LOGGED : $TITLE_NOT_LOGGED;
print "<div id=\"$headerTitleClass\"><a href=\"index.php\"><i>$currentTitle</i></a></div>";

if (isset($MY_URL)) {
    $target = (substr($MY_URL, -1) == ">") ? 'target="_blank"' : '';
    $myUrlClean = (substr($MY_URL, -1) == ">") ? substr_replace($MY_URL,"",-1) : $MY_URL;
    print "<div id=\"$headerTitle2Class\"><a href=\"$myUrlClean\" $target><i>$CALL</i></a></div>";
} else {
    print "<div id=\"$headerTitle2Class\"><i>$CALL</i></div>";
}

print "<div id=\"$headerTagClass\"><i>$LOCATION<br>$TITLE2</i></div>";

if ($_SESSION['sm61loggedin'] === true) {
   print "<div id=\"$header2TagClass\"><i>$TITLE3</i></div>";
}
?>

<?php if ($_SESSION['sm61loggedin'] === true): ?>
<div id="headerLogout">
    <div id="headerLogout2">
        <a href="#" id="logoutlink">Logout <?php echo htmlspecialchars($_SESSION["user"]); ?></span></a>
    </div>
</div>
<?php else: ?>
<div id="headerLogin">
    <a href="#" id="loginlink">Login</a>  
</div>
<?php endif; ?>

<div id="headerImg"><a href="https://www.allstarlink.org" target="_blank"><img src="allstarlink.jpg" width="70%" style="border: 0px;" alt="Allstar Logo"></a></div>

<?php
if (isset($LOGO_NAME, $LOGO_SIZE, $LOGO_POSITION_RIGHT, $LOGO_POSITION_TOP)) {
    $logo_style = "border: 0px; position:absolute; top:{$LOGO_POSITION_TOP}; right:{$LOGO_POSITION_RIGHT};";
    $logo_img = "<img src=\"$LOGO_NAME\" width=\"$LOGO_SIZE\" style=\"$logo_style\" alt=\"Logo\">";
    if (isset($LOGO_URL)) {
        $logo_target = (substr($LOGO_URL, -1) == ">") ? 'target="_blank"' : '';
        $logoUrlClean = (substr($LOGO_URL, -1) == ">") ? substr_replace($LOGO_URL,"",-1) : $LOGO_URL;
        print "<div><a href=\"$logoUrlClean\" $logo_target>$logo_img</a></div>";
    } else {
        print "<div>$logo_img</div>";
    }
}
?>
</div>

<div class="clearer"></div>

<div style="display:none;" id="login">
    <form id="myform" action="" method="post">
        <fieldset> 
            <label class="exit" style="float:right;clear:both;"><input type="radio" id="close_login" name="close_login" onclick="hideLoginUi()"/><span><b>X</b></span></label>
            <p style="font-size:1.5em;color:white;margin-top:1em;margin-bottom:0.1em;text-align:center;"><b>Supermon-ng</b></p>
            <p style="font-size:1.2em;color:white;margin-top:0;margin-bottom:.5em;text-align:center;"><b>Manager Log in</b></p>
            <input style="font-weight:bold;margin-bottom:.5em;" type="text" id="user" name="user" placeholder="Username" autocapitalize="none" required size="10">
            <br>
            <input style="font-weight:bold;" type="password" id="passwd" name="passwd" placeholder="Password" required size="10">
            <br>
            <?php if ($SHOWPW): ?>
                <label style="font-weight:bold;color:white;"><input style="margin-top:1em;" id="checkbox" name="show_password" type="checkbox" onclick="togglePasswordVisibility()">Show Password</label>
            <?php endif; ?>
            <br>
            <label style="font-weight:bold;color:white;"><input type="checkbox" name="clear_form" style="font-weight:bold;margin-top:.5em;" onclick="clearLoginForm()">Clear</label>
            <input class="login" style="font-weight:bold;float:right;clear:both;margin-top:.5em;" type="submit" name="submit" value="submit">
        </fieldset>
    </form>
</div>

<?php include "menu.inc" ?>
<div id="test_area"></div>

</body>
</html>
