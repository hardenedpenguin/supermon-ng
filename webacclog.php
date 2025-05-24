<?php

include("session.inc");
include("user_files/global.inc");
include("common.inc");
include("authusers.php");

// --- Configuration ---
define('LOG_FORMAT_REGEX', '/^(\S+) (\S+) (\S+) \[([^\]]+)\] "([^"]+)" (\d{3}) (\S+) "([^"]*)" "([^"]*)"$/');
$logTableHeaders = [
    'Host', 'Identity', 'Auth User', 'Date/Time', 'Request', 'Status', 'Bytes', 'Referer', 'User Agent'
];

// Determine how to read the file (direct or sudo)
// Set this based on your chosen method (See previous explanation)
$read_method = 'direct'; // or 'sudo'

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Web Server access_log Viewer</title>
    <style>
        body {
            font-family: sans-serif;
            background-color: #000000; /* Black background */
            color: #ffffff; /* White text */
            margin: 15px;
        }
        .filename-display {
            /* Using a lighter blue for better contrast on black */
            color: #66ccff; /* Light/Cyan Blue text */
            font-weight: bold;
            font-size: 1.2em;
            margin-bottom: 10px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            /* Light grey border, visible on black */
            border: 1px solid #555555;
            font-size: 0.9em;
            margin-top: 10px;
            table-layout: fixed;
        }
        th, td {
            /* Light grey border for cells */
            border: 1px solid #555555;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
        }
        th {
            /* Dark grey background for headers */
            background-color: #2a2a2a;
            font-weight: bold;
            color: #00ff00; /* Ensure header text is green */
        }
        tr:nth-child(even) {
             /* Slightly different dark background for even rows */
            background-color: #1a1a1a;
        }
        tr:nth-child(odd) {
             /* Explicitly set odd rows in case of inheritance issues */
            background-color: #0f0f0f;
        }
        .error-message {
            /* Brighter red text for errors on black */
            color: #ffdddd;
            font-weight: bold;
            font-size: 1.1em;
             /* Dark red background, white/light border */
            border: 1px solid #ffaaaa;
            padding: 10px;
            background-color: #400000;
            margin-top: 15px;
        }
        code { /* Style for inline code like commands */
            background-color: #333;
            padding: 2px 4px;
            border-radius: 3px;
            color: #eeeeee;
        }
        em { /* Style for emphasis like [Unparsed Line] */
           color: #cccccc;
           font-style: italic;
        }
    </style>
</head>
<body>

<h1>Web Server Access Log</h1>

<?php
// Check if user is logged in and authorized
if (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && get_user_auth("WLOGUSER")) {

    // Check if $WEB_ACCESS_LOG is defined
    if (isset($WEB_ACCESS_LOG) && !empty($WEB_ACCESS_LOG)) {
        $file = $WEB_ACCESS_LOG;
        $logLines = false; // Initialize logLines variable

        echo '<div class="filename-display">Viewing Log File: ' . htmlspecialchars($file) . '</div>';

        // --- Choose read method ---
        if ($read_method === 'sudo') {
            $command = "/usr/bin/cat " . escapeshellarg($file);
            $sudo_command = "/usr/bin/sudo " . $command;
            echo "<p><i>Attempting to read via: <code>" . htmlspecialchars($sudo_command) . "</code></i></p>";
            $logContent = @shell_exec($sudo_command);

            if ($logContent === null || $logContent === false) {
                echo '<div class="error-message">ERROR: Failed to execute sudo command or no output received.<br>';
                echo 'Check web server error logs and sudo configuration (`visudo`).</div>';
            } elseif (trim($logContent) === '') {
                echo '<p>Log file appears to be empty (read via sudo).</p>';
            } else {
                $logLines = explode("\n", trim($logContent)); // Assign to $logLines
            }
        }
        else { // Assumes 'direct' or any other value
             if (file_exists($file) && is_readable($file)) {
                 $logLines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                 if ($logLines === false) {
                      echo '<div class="error-message">Error: Could not read log file content directly, although it seems to exist and be readable. Check permissions further.</div>';
                 } elseif (empty($logLines)) {
                      echo '<p>Log file is empty (read directly).</p>';
                 }
                 // If $logLines has content, proceed below
             } else {
                 echo '<div class="error-message">ERROR: Log file not found or is not readable by the web server user (' . exec('whoami') . ').<br>';
                 echo 'Expected location: ' . htmlspecialchars($file) . '<br>';
                 echo 'Consider adjusting group permissions or using ACLs (safer) or configuring sudo (less safe).</div>';
             }
        }

        // --- Process and display $logLines if successfully read ---
        if ($logLines !== false && !empty($logLines)) {
            echo '<table>';
            echo '<thead><tr>';
            foreach ($logTableHeaders as $header) {
                echo '<th>' . htmlspecialchars($header) . '</th>';
            }
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($logLines as $line) {
                // Should already be trimmed if using explode, but good practice if using file()
                $trimmedLine = trim($line);
                if (empty($trimmedLine)) continue;

                if (preg_match(LOG_FORMAT_REGEX, $trimmedLine, $matches)) {
                    echo '<tr>';
                    for ($i = 1; $i < count($matches); $i++) {
                        echo '<td>' . htmlspecialchars($matches[$i]) . '</td>';
                    }
                    echo '</tr>';
                } else {
                    // Ensure unparsed line text is readable on dark background
                    echo '<tr><td colspan="' . count($logTableHeaders) . '"><em>[Unparsed Line]:</em> ' . htmlspecialchars($trimmedLine) . '</td></tr>';
                }
            }
            echo '</tbody></table>';
        }
    } else {
         echo '<div class="error-message">ERROR: The `WEB_ACCESS_LOG` path is not defined in `global.inc`.</div>';
    }

} else {
    echo '<div class="error-message">ERROR: You must login with sufficient privileges to use this function!</div>';
}
?>

</body>
</html>
