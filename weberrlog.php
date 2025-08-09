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
                    $headers = ['Line', 'Timestamp', 'Level', 'Client', 'Details'];
                    $rows = [];
                    foreach ($lines as $index => $line) {
                        $lineNumber = $index + 1;
                        $matched = preg_match($logRegex, $line, $matches);
                        if ($matched) {
                            $timestamp = htmlspecialchars($matches['timestamp'] ?? '');
                            $level_raw_captured = $matches['level_m'] ?? ($matches['level'] ?? '');
                            $level_raw = strtolower(trim($level_raw_captured));
                            $level_display = htmlspecialchars(strtoupper($level_raw));
                            $client = htmlspecialchars($matches['client'] ?? '');
                            $message = htmlspecialchars($matches['message'] ?? '');
                            $rows[] = [
                                $lineNumber,
                                $timestamp,
                                $level_display,
                                $client,
                                $message
                            ];
                        } else {
                            $sanitizedLine = htmlspecialchars($line);
                            $rows[] = [
                                $lineNumber,
                                'N/A',
                                'N/A',
                                'N/A',
                                $sanitizedLine
                            ];
                        }
                    }
                    $table_class = 'weberrlog-table';
                    include 'includes/table.inc';
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
