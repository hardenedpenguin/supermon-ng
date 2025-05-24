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
    <style type="text/css">
        body {
            font-family: sans-serif;
            background-color: #000000;
            color: #ffffff;
            margin: 0;
            padding: 20px;
        }
        .log-container {
            background-color: #1a1a1a;
            border: 1px solid #444444;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(255,255,255,0.1);
        }
        pre {
            font-family: monospace;
            font-size: 14px;
            white-space: pre-wrap;
            word-wrap: break-word;
            background-color: #2b2b2b;
            color: #f0f0f0;
            padding: 10px;
            border: 1px solid #555555;
            max-height: 80vh;
            overflow-y: auto;
        }
        h1, h2, h3 {
             color: #ffffff;
        }
        .error {
            color: #ff6666;
            font-weight: bold;
        }
        .info {
            color: #3399ff;
            font-weight: bold;
            margin-bottom: 10px;
        }
        hr {
            border: 0;
            height: 1px;
            background-color: #555555;
            margin: 15px 0;
        }
        a {
            color: #66ccff;
        }
        a:hover {
            color: #99ddff;
        }
    </style>
</head>
<body>

<div class="log-container">
    <h1>Asterisk Messages Log Viewer</h1>

    <?php
    if (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && function_exists('get_user_auth') && get_user_auth("ASTLUSER")) {

        if (isset($ASTERISK_LOG)) {
            $file = $ASTERISK_LOG;

            if (file_exists($file) && is_readable($file)) {
                echo '<p class="info">Displaying log file: ' . htmlspecialchars($file) . '</p>';
                echo '<hr>';
                echo '<pre>';
                $content = file_get_contents($file);
                if ($content === false) {
                    echo '<span class="error">Error: Could not read file content after verifying readability.</span>';
                } else {
                    echo htmlspecialchars($content);
                }
                echo '</pre>';
            } else {
                echo '<p class="error">Error: Asterisk log file not found or is not readable by the web server.</p>';
                echo '<p class="error">Checked path: ' . htmlspecialchars($file) . '</p>';
                if (file_exists($file) && !is_readable($file)) {
                     echo '<p class="error">Hint: Check file permissions. The web server user needs read access.</p>';
                }
            }
        } else {
            echo '<p class="error">Error: Asterisk log file path variable ($ASTERISK_LOG) is not defined in global.inc.</p>';
        }

    } else {
        echo '<h3 class="error">ERROR: You must be logged in and authorized to view this log!</h3>';
    }
    ?>

</div>

</body>
</html>
