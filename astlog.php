<?php
include("session.inc");
include("user_files/global.inc");
include("common.inc");
include("authusers.php");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Asterisk Messages Log</title>
    <link type="text/css" rel="stylesheet" href="supermon-ng.css">
</head>
<body>

<div class="log-viewer-container">
    <h1 class="log-viewer-title">Asterisk Messages Log Viewer</h1>

    <?php
    if (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && function_exists('get_user_auth') && get_user_auth("ASTLUSER")) {

        if (isset($ASTERISK_LOG)) {
            $file = $ASTERISK_LOG;

            if (file_exists($file) && is_readable($file)) {
                echo '<p class="log-viewer-info">Displaying log file: ' . htmlspecialchars($file) . '</p>';
                echo '<hr>';
                echo '<pre class="log-viewer-pre">';
                $content = file_get_contents($file);
                if ($content === false) {
                    echo '<span class="log-viewer-error">Error: Could not read file content after verifying readability.</span>';
                } else {
                    echo htmlspecialchars($content);
                }
                echo '</pre>';
            } else {
                echo '<p class="log-viewer-error">Error: Asterisk log file not found or is not readable by the web server.</p>';
                echo '<p class="log-viewer-error">Checked path: ' . htmlspecialchars($file) . '</p>';
                if (file_exists($file) && !is_readable($file)) {
                     echo '<p class="log-viewer-error">Hint: Check file permissions. The web server user needs read access.</p>';
                }
            }
        } else {
            echo '<p class="log-viewer-error">Error: Asterisk log file path variable ($ASTERISK_LOG) is not defined in global.inc.</p>';
        }

    } else {
        echo '<h3 class="log-viewer-error">ERROR: You must be logged in and authorized to view this log!</h3>';
    }
    ?>

</div>

</body>
</html>
