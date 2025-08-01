<?php

include("includes/session.inc");
include("includes/common.inc");
include("authusers.php");

// Set a description for this specific log page, used in the title and heading.
$log_description = "System Log (journalctl, last 24 hours, sudo lines filtered)";

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <!-- Set the title shown in the browser tab -->
    <title><?php echo htmlspecialchars($log_description); ?></title>
    <link type="text/css" rel="stylesheet" href="supermon-ng.css">
</head>
<body>

<div class="log-viewer-container">
    <?php
    // Verify if the user is logged in AND has the specific 'LLOGUSER' permission.
    if (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && get_user_auth("LLOGUSER")) {

        // Show the log description as a heading on the page.
        echo "<div class=\"log-viewer-title\">" . htmlspecialchars($log_description) . "</div>\n";

        // Ensure the necessary command paths loaded from common.inc are available.
        if (!isset($SUDO) || !isset($JOURNALCTL) || !isset($SED)) {
             echo "<div class=\"log-viewer-error\">Configuration Error: Required command variables (SUDO, JOURNALCTL, SED) are not defined in common.inc.</div>";
        } else {
            $cmd = "$SUDO $JOURNALCTL --no-pager --since \"1 day ago\" | $SED -e \"/sudo/ d\"";
            echo '<pre class="log-viewer-output">';
            passthru($cmd . " 2>&1");
            echo '</pre>';
        }

    } else {
        // If the user is not logged in or doesn't have the required permission, show an error message.
        echo "<div class=\"log-viewer-error\"><h3>ERROR: You must be logged in with appropriate permissions to view this log!</h3></div>";
    }
    ?>
</div>

</body>
</html>
