<?php

include("session.inc");
include("user_files/global.inc");
include("common.inc");
include("authusers.php");

// --- Configuration ---
$logFilePath = isset($WEB_ERROR_LOG) ? $WEB_ERROR_LOG : null;

// --- Authentication Check ---
$isLoggedIn = isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true;
$isAuthorized = $isLoggedIn && function_exists('get_user_auth') && get_user_auth("WERRUSER");

// --- Log Parsing Regex ---
$logRegex = '/^\[(?<timestamp>.*?)\] (?:\[(?<module>[^:]+):(?<level_m>[^\]]+)\]|\[(?<level>[^\]]+)\])(?: \[pid (?<pid>\d+)(?::tid (?<tid>\d+))?\])?(?: \[client (?<client>.*?)\])? (?<message>.*)$/';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Server error_log Viewer</title>
    <style>
        body {
            background-color: #000000; /* Black background */
            color: #FFFFFF; /* White text */
            font-family: Consolas, Monaco, 'Andale Mono', 'Ubuntu Mono', monospace; /* Monospaced font */
            margin: 15px;
        }
        h2 {
            color: #FFFFFF;
            border-bottom: 1px solid #FFFFFF;
            padding-bottom: 5px;
            margin-bottom: 20px;
        }
        h3 {
            color: #00FF00;
            border-bottom: 1px solid #00FF00;
            padding-bottom: 5px;
        }
        .error-message {
            color: #FF8080; /* Brighter Red for errors */
            font-weight: bold;
            background-color: #330000;
            padding: 10px;
            border: 1px solid #FF8080;
        }
        .file-info {
            color: #66CCFF;
            margin-bottom: 10px;
            font-weight: bold;
            font-size: 1.1em;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 13px;
            table-layout: fixed; /* Crucial for respecting widths */
        }
        th, td {
            border: 1px solid #555555;
            padding: 5px 8px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word; /* Allow breaking long words if necessary */
        }
        th {
            background-color: #222222;
            color: #00FF00;
            position: sticky;
            top: 0;
            z-index: 10;
            white-space: nowrap;
        }
        th.level-header {
             text-align: center;
        }

        /* --- Column Widths (Keep previous settings) --- */
        td.line-number { width: 55px; text-align: right; color: #AAAAAA; user-select: none; white-space: nowrap; flex-shrink: 0; }
        td.log-timestamp { width: 260px; white-space: nowrap; color: #cccccc; flex-shrink: 0; overflow: hidden; text-overflow: ellipsis; }
        td.log-client { width: 175px; color: #ADD8E6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex-shrink: 0; }
        td.log-details { white-space: pre-wrap; }

        /* --- Base Style for Level Column --- */
        td.log-level {
            width: 80px;
            white-space: nowrap;
            flex-shrink: 0;
            text-align: center;
            /* Default text color (fallback) */
            color: #FFFFFF;
            /* Remove base bolding if any */
            font-weight: normal;
            /* Default background is transparent, showing row color */
            background-color: transparent;
        }

        /* --- Highlighted Levels: Error, Warning, Notice --- */
        td.log-level-error,
        td.log-level-crit,
        td.log-level-alert,
        td.log-level-emerg {
            color: #FFBDBD; /* Lighter red text for better contrast on dark red bg */
            background-color: #4d0000; /* Dark Red background */
            font-weight: bold; /* Make text bold */
        }
        td.log-level-warn,
        td.log-level-warning {
             color: #FFE999; /* Lighter yellow text for better contrast on dark orange bg */
             background-color: #594000; /* Dark Orange/Brown background */
             font-weight: bold; /* Make text bold */
        }
        td.log-level-notice {
            color: #C0E8FF; /* Lighter blue text for better contrast on dark blue bg */
            background-color: #002b4d; /* Dark Blue background */
            font-weight: bold; /* Make text bold */
        }

        /* --- Standard / Subdued Levels: Info, Debug --- */
        td.log-level-info {
             color: #cccccc; /* Light Grey text (less prominent) */
             /* Inherits transparent background */
             font-weight: normal;
        }
        td.log-level-debug {
            color: #aaaaaa; /* Dim Grey text */
            /* Inherits transparent background */
            font-weight: normal;
        }

        /* --- Styles for Unparsed Level Cells --- */
        td.unparsed.log-level-unparsed {
            text-align: center; /* Keep centering */
            color: #aaaaaa; /* Dim grey */
            font-style: italic; /* Differentiate unparsed */
            background-color: transparent; /* Ensure no accidental background */
            font-weight: normal;
        }

        /* --- Row Styling --- */
        tbody tr:nth-child(odd) { background-color: #111111; }
        tbody tr:nth-child(even) { background-color: #080808; }
        tbody tr:hover { background-color: #333333; } /* Row hover remains subtle */
        /* Optional: Slightly enhance hover effect on highlighted cells */
         tbody tr:hover td.log-level-error,
         tbody tr:hover td.log-level-crit,
         tbody tr:hover td.log-level-alert,
         tbody tr:hover td.log-level-emerg { background-color: #6e0000; } /* Slightly lighter dark red */
         tbody tr:hover td.log-level-warn,
         tbody tr:hover td.log-level-warning { background-color: #7a5f00; } /* Slightly lighter dark orange */
         tbody tr:hover td.log-level-notice { background-color: #004475; } /* Slightly lighter dark blue */


    </style>
</head>
<body>

    <h2>Web Server Error Log</h2>

<?php
    if ($isAuthorized) {
        if ($logFilePath && file_exists($logFilePath)) {
            if (is_readable($logFilePath)) {
                echo "<div class='file-info'>Viewing Log File: " . htmlspecialchars($logFilePath) . "</div>";

                $lines = file($logFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

                if ($lines !== false && count($lines) > 0) {
                    echo "<table>";
                    echo "<thead>";
                    echo "<tr>";
                    // --- TH styles match TD widths ---
                    echo "<th style='width: 55px;'>Line</th>";
                    echo "<th style='width: 260px;'>Timestamp</th>";
                    echo "<th style='width: 80px;' class='level-header'>Level</th>";
                    echo "<th style='width: 175px;'>Client</th>";
                    echo "<th>Details</th>";
                    echo "</tr>";
                    echo "</thead>";
                    echo "<tbody>";

                    foreach ($lines as $index => $line) {
                        $lineNumber = $index + 1;
                        $matched = preg_match($logRegex, $line, $matches);

                        echo "<tr>";
                        echo "<td class='line-number'>{$lineNumber}</td>";

                        if ($matched) {
                            // --- Level Processing (PHP Logic remains the same) ---
                            $timestamp = htmlspecialchars($matches['timestamp'] ?? '');
                            $level_raw_captured = $matches['level_m'] ?? ($matches['level'] ?? '');
                            $level_raw = strtolower(trim($level_raw_captured));
                            $level_display = htmlspecialchars(strtoupper($level_raw));
                            $level_class_suffix = preg_replace('/[^a-z0-9]+/', '-', $level_raw);
                            $level_class = !empty($level_class_suffix) ? 'log-level-' . $level_class_suffix : '';
                            $client = htmlspecialchars($matches['client'] ?? '');
                            $message = htmlspecialchars($matches['message'] ?? '');

                            // Construct the classes string for the TD
                            $level_td_classes = 'log-level';
                            if (!empty($level_class)) {
                                $level_td_classes .= ' ' . $level_class;
                            }

                            echo "<td class='log-timestamp' title='{$timestamp}'>{$timestamp}</td>";
                             // Apply the combined classes for layout, color, background, font-weight
                            echo "<td class='{$level_td_classes}'>{$level_display}</td>";
                            echo "<td class='log-client' title='" . ($client ? $client : 'N/A') . "'>{$client}</td>";
                            echo "<td class='log-details'>{$message}</td>";
                        } else {
                            // --- Unparsed Line Handling ---
                            $sanitizedLine = htmlspecialchars($line);
                            echo "<td class='log-timestamp unparsed' title='N/A'>N/A</td>";
                            // Ensure the unparsed level cell gets the right classes for styling
                            echo "<td class='log-level unparsed log-level-unparsed'>N/A</td>";
                            echo "<td class='log-client unparsed' title='N/A'>N/A</td>";
                            echo "<td class='log-details unparsed'>{$sanitizedLine}</td>";
                        }
                        echo "</tr>";
                    }
                    echo "</tbody>";
                    echo "</table>";
                } elseif ($lines !== false && count($lines) === 0) {
                     echo "<p>Log file exists but is currently empty.</p>";
                } else {
                    echo "<p class='error-message'>ERROR: Could not read the log file content.</p>";
                }
            } else {
                echo "<p class='error-message'>ERROR: Log file not readable: " . htmlspecialchars($logFilePath) . "</p>";
            }
        } else {
            if ($logFilePath) {
                 echo "<p class='error-message'>ERROR: Log file not found: " . htmlspecialchars($logFilePath) . "</p>";
            } else {
                 echo "<p class='error-message'>ERROR: Log file path (WEB_ERROR_LOG) not defined.</p>";
            }
        }
    } else {
        echo "<p class='error-message'>ERROR: Not authorized.</p>";
    }
?>

</body>
</html>
