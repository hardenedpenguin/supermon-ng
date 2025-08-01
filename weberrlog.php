<?php

include("includes/session.inc");
include("user_files/global.inc");
include("includes/common.inc");
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
    <link type="text/css" rel="stylesheet" href="supermon-ng.css">
</head>
<body>

    <h2 class="log-viewer-title">Web Server Error Log</h2>

<?php
    if ($isAuthorized) {
        if ($logFilePath && file_exists($logFilePath)) {
            if (is_readable($logFilePath)) {
                echo "<div class='log-viewer-info'>Viewing Log File: " . htmlspecialchars($logFilePath) . "</div>";

                $lines = file($logFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

                if ($lines !== false && count($lines) > 0) {
                    echo "<table class='weberrlog-table'>";
                    echo "<thead>";
                    echo "<tr>";
                    echo "<th class='weberrlog-line-header'>Line</th>";
                    echo "<th class='weberrlog-timestamp-header'>Timestamp</th>";
                    echo "<th class='weberrlog-level-header'>Level</th>";
                    echo "<th class='weberrlog-client-header'>Client</th>";
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
                    echo "<p class='log-viewer-error'>ERROR: Could not read the log file content.</p>";
                }
            } else {
                echo "<p class='log-viewer-error'>ERROR: Log file not readable: " . htmlspecialchars($logFilePath) . "</p>";
            }
        } else {
            if ($logFilePath) {
                 echo "<p class='log-viewer-error'>ERROR: Log file not found: " . htmlspecialchars($logFilePath) . "</p>";
            } else {
                 echo "<p class='log-viewer-error'>ERROR: Log file path (WEB_ERROR_LOG) not defined.</p>";
            }
        }
    } else {
        echo "<p class='log-viewer-error'>ERROR: Not authorized.</p>";
    }
?>

</body>
</html>
