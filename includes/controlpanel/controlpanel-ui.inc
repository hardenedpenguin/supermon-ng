<?php
/**
 * Supermon-ng Control Panel UI
 * 
 * Provides the user interface components for the control panel including
 * HTML structure, CSS styling, and JavaScript functionality.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Output control panel HTML head section
 * 
 * @param string $title Page title
 */
function outputControlPanelHead(string $title): void {
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
    <!-- Modular CSS Files -->
<link type="text/css" rel="stylesheet" href="css/base.css">
<link type="text/css" rel="stylesheet" href="css/layout.css">
<link type="text/css" rel="stylesheet" href="css/menu.css">
<link type="text/css" rel="stylesheet" href="css/tables.css">
<link type="text/css" rel="stylesheet" href="css/forms.css">
<link type="text/css" rel="stylesheet" href="css/widgets.css">
<link type="text/css" rel="stylesheet" href="css/responsive.css">
<!-- Custom CSS (load last to override defaults) -->
<?php if (file_exists('css/custom.css')): ?>
<link type="text/css" rel="stylesheet" href="css/custom.css">
<?php endif; ?>
    <link type="text/css" rel="stylesheet" href="js/jquery-ui.css">
    <script src="js/jquery.min.js"></script>
    <script src="js/jquery-ui.min.js"></script>

    <script src="js/sweetalert2.min.js"></script>
    <link rel="stylesheet" href="js/sweetalert2.min.css"/>
    <?php
}

/**
 * Output control panel JavaScript
 * 
 * @param bool $isLoggedIn Whether user is logged in
 */
function outputControlPanelScript(bool $isLoggedIn): void {
    ?>
    <script>
        $(document).ready(function () {

            <?php if (!$isLoggedIn) { ?>
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
                    alertify.success(data);
                });
            });
        });
    </script>
    <?php
}

/**
 * Output control panel header
 * 
 * @param string $title Page title
 */
function outputControlPanelHeader(string $title): void {
    global $BACKGROUND, $BACKGROUND_COLOR, $BACKGROUND_HEIGHT, $CALL, $TITLE_LOGGED, $TITLE3;
    ?>
<div id="header" class="header-dynamic" style="background-image: url(<?php echo $BACKGROUND; ?>); background-color:<?php echo $BACKGROUND_COLOR; ?>; height: <?php echo $BACKGROUND_HEIGHT; ?>;">
    <div id="headerTitle" class="headerTitle-large"><i><?php echo "$CALL - $TITLE_LOGGED"; ?></i></div>
    <div id="header4Tag" class="header4Tag-large"><i><?php echo $title ?></i></div>
    <div id="header2Tag" class="header2Tag-large"><i><?php echo $TITLE3; ?></i></div>
    <div id="headerImg"><a href="https://www.allstarlink.org" target="_blank"><img src="allstarlink.jpg" width="70%" alt="Allstar Logo"></a></div>
</div>
    <?php
}

/**
 * Output control panel main content
 * 
 * @param string $node Node number
 * @param array $cpCommands Control panel commands
 */
function outputControlPanelMain(string $node, array $cpCommands): void {
    ?>
<br>
<div id="cpMain">
    <center>Sending Command to node <?php echo htmlspecialchars($node) ?></center>
    <br>
    <?php if (isset($cpCommands['labels']) && is_array($cpCommands['labels']) && count($cpCommands['labels']) > 0): ?>
        Control command (select one): <select name="cpSelection" class="submit-large" id="cpSelect">
            <?php
            for ($i = 0; $i < count($cpCommands['labels']); $i++) {
                if (isset($cpCommands['cmds'][$i]) && isset($cpCommands['labels'][$i])) {
                    print "<option value=\"" . htmlspecialchars($cpCommands['cmds'][$i]) . "\">" . htmlspecialchars($cpCommands['labels'][$i]) . "</option>\n";
                }
            }
            ?>
        </select>
        <input type="hidden" id="localnode" value="<?php echo htmlspecialchars($node) ?>">
        <input type="button" class="submit-large" value="Execute" id="cpExecute">
    <?php else: ?>
        <div class="info-message">
            <strong>No Control Panel Commands Configured</strong><br>
            To use the Control Panel, you need to configure commands in:<br>
            <code>user_files/controlpanel.ini</code><br><br>
            Edit the file and uncomment the commands you want to use by removing the semicolon (;) from the beginning of the lines.
        </div>
    <?php endif; ?>
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
    <?php
}
