<?php
/**
 * Supermon-ng Favorites UI
 * 
 * Provides the user interface components for the favorites panel including
 * HTML structure, CSS styling, JavaScript functionality, and content rendering.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Output favorites page HTML head section
 * 
 * @param string $title Page title
 */
function outputFavoritesHead(string $title): void {
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
 * Output favorites JavaScript functionality
 */
function outputFavoritesScript(): void {
    ?>
    <script>
    $(document).ready(function() {
        $("#cpMain").show();
        
        $('#cpExecute').click(function() {
            var localNode = $('#localnode').val();
            var cpCommand = $('#cpSelect').val();
            
            $.get('controlserverfavs.php?node=' + encodeURIComponent(localNode) + '&cmd=' + encodeURIComponent(cpCommand), function( data ) {
                alertify.success(data);
            });
        });
    });    
    </script>
    <?php
}

/**
 * Output favorites header
 * 
 * @param string $title Page title
 */
function outputFavoritesHeader(string $title): void {
    global $BACKGROUND, $BACKGROUND_COLOR, $BACKGROUND_HEIGHT, $CALL, $TITLE_LOGGED, $TITLE3;
    ?>
    <div id="header" class="header-dynamic" style="background-image: url(<?php echo htmlspecialchars($BACKGROUND ?? '', ENT_QUOTES, 'UTF-8'); ?>); background-color:<?php echo htmlspecialchars($BACKGROUND_COLOR ?? '', ENT_QUOTES, 'UTF-8'); ?>;<?php if (isset($BACKGROUND_HEIGHT)) echo ' height:' . htmlspecialchars($BACKGROUND_HEIGHT, ENT_QUOTES, 'UTF-8') . ';'; ?>">
        <div id="headerTitle-large"><i><?php echo htmlspecialchars(($CALL ?? '') . " - " . ($TITLE_LOGGED ?? '')); ?></i></div>
        <div id="header3Tag-large"><i><?php echo htmlspecialchars($title); ?></i></div>
        <div id="header2Tag-large"><i><?php echo htmlspecialchars($TITLE3 ?? ''); ?></i></div>
        <div id="headerImg"><a href="https://www.allstarlink.org" target="_blank"><img src="allstarlink.jpg" width="70%" class="img-borderless" alt="AllStar Logo"></a></div>
    </div>
    <?php
}

/**
 * Output favorites main content
 * 
 * @param string $node Node number
 * @param array $cpCommands Favorites commands configuration
 */
function outputFavoritesMain(string $node, array $cpCommands): void {
    ?>
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
    <?php
}

/**
 * Output favorites footer
 * 
 * @param string $FAVINI Favorites INI file path
 */
function outputFavoritesFooter(string $FAVINI): void {
    ?>
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
    <?php
}
