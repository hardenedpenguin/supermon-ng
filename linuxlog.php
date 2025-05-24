<?php

include("session.inc");
include("common.inc");
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
    <style>
        /* Basic styling for a dark theme (black background, white text) */
        body {
            font-family: "Courier New", Courier, monospace; /* Use monospace font for logs */
            background-color: #000000;
            color: #FFFFFF;
            margin: 0;
            padding: 15px;
        }
        .log-container { /* Box around the log content */
            border: 1px solid #444444;
            padding: 15px;
            border-radius: 4px;
        }
        .log-title { /* Style for the main heading */
            color: #6495ED; /* Blue color */
            font-size: 1.3em;
            font-weight: bold;
            margin-bottom: 15px;
            border-bottom: 1px solid #444444; /* Line under title */
            padding-bottom: 8px;
        }
        pre.log-output { /* Style for the actual log text */
            font-family: inherit; /* Use body's monospace font */
            font-size: 14px;
            line-height: 1.4;
            background-color: transparent; /* Show body background */
            color: #FFFFFF; /* White text */
            border: none;
            padding: 0;
            margin: 0;
            white-space: pre-wrap; /* Wrap long lines */
            word-wrap: break-word;
        }
        .error-message { /* Style for displaying errors */
            color: #FF6347; /* Reddish color */
            font-weight: bold;
            text-align: center;
            padding: 20px;
            background-color: #1a1a1a; /* Dark background for emphasis */
            border: 1px solid #FF6347;
            border-radius: 4px;
            margin: 20px;
        }
    </style>
</head>
<body>

<div class="log-container">
    <?php
    // Verify if the user is logged in AND has the specific 'LLOGUSER' permission.
    if (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && get_user_auth("LLOGUSER")) {

        // Show the log description as a heading on the page.
        echo "<div class=\"log-title\">" . htmlspecialchars($log_description) . "</div>\n";

        // Ensure the necessary command paths loaded from common.inc are available.
        if (!isset($SUDO) || !isset($JOURNALCTL) || !isset($SED)) {
             echo "<div class=\"error-message\">Configuration Error: Required command variables (SUDO, JOURNALCTL, SED) are not defined in common.inc.</div>";
        } else {
            $cmd = "$SUDO $JOURNALCTL --no-pager --since \"1 day ago\" | $SED -e \"/sudo/ d\"";
            echo '<pre class="log-output">';
            passthru($cmd . " 2>&1");
            echo '</pre>';
        }

    } else {
        // If the user is not logged in or doesn't have the required permission, show an error message.
        echo "<div class=\"error-message\"><h3>ERROR: You must be logged in with appropriate permissions to view this log!</h3></div>";
    }
    ?>
</div>

</body>
</html>
