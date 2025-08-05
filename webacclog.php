<?php

include("includes/session.inc");
include("includes/security.inc");
include("user_files/global.inc");
include("includes/common.inc");
include("authusers.php");
include("authini.php");

if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("WLOGUSER"))) {
    die ("<br><h3 class='error-message'>ERROR: You Must login to use the 'Web Access Log' function!</h3>");
}

// Safe command execution function
function safe_exec($command, $args = '') {
    $escaped_command = escapeshellcmd($command);
    if (!empty($args)) {
        $escaped_args = escapeshellarg($args);
        $full_command = "{$escaped_command} {$escaped_args}";
    } else {
        $full_command = $escaped_command;
    }
    
    $output = [];
    $return_var = 0;
    exec($full_command . " 2>/dev/null", $output, $return_var);
    
    if ($return_var !== 0) {
        return false;
    }
    
    return implode("\n", $output);
}

// Validate file path
function is_safe_file_path($path) {
    // Only allow specific log files
    $allowed_paths = [
        '/var/log/apache2/access.log',
        '/var/log/httpd/access_log',
        '/var/log/nginx/access.log'
    ];
    
    return in_array($path, $allowed_paths) && file_exists($path);
}

$file = $WEB_ACCESS_LOG ?? '/var/log/apache2/access.log';

if (!is_safe_file_path($file)) {
    die("<h3 class='error-message'>ERROR: Invalid or inaccessible log file path.</h3>");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Web Server access_log Viewer</title>
    <!-- Modular CSS Files -->
<link type="text/css" rel="stylesheet" href="css/base.css">
<link type="text/css" rel="stylesheet" href="css/layout.css">
<link type="text/css" rel="stylesheet" href="css/menu.css">
<link type="text/css" rel="stylesheet" href="css/tables.css">
<link type="text/css" rel="stylesheet" href="css/forms.css">
<link type="text/css" rel="stylesheet" href="css/widgets.css">
<link type="text/css" rel="stylesheet" href="css/responsive.css">
<!-- Custom CSS (load last to override defaults) -->
<link type="text/css" rel="stylesheet" href="css/custom.css">
</head>
<body>

<h1 class="log-viewer-title">Web Server Access Log</h1>

<div class="log-viewer-info">Viewing Log File: <?php echo htmlspecialchars($file); ?></div>

<?php
// Check if user is logged in and authorized
if (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && get_user_auth("WLOGUSER")) {

    // Check if $WEB_ACCESS_LOG is defined
    if (isset($WEB_ACCESS_LOG) && !empty($WEB_ACCESS_LOG)) {
        $logLines = false; // Initialize logLines variable

        // Try to read the file directly first
        if (is_readable($file)) {
            $logContent = file_get_contents($file);
            if ($logContent !== false) {
                $logLines = explode("\n", $logContent);
                $logLines = array_slice($logLines, -100); // Show last 100 lines
                $logLines = array_reverse($logLines); // Most recent first
            } else {
                $logLines = [];
            }
        } else {
            // Fallback to sudo if direct read fails
            $sudo_command = "sudo tail -100 " . escapeshellarg($file);
            echo "<p><i>Attempting to read via: <code>" . htmlspecialchars($sudo_command) . "</code></i></p>";
            
            $logContent = safe_exec("sudo", "tail -100 " . escapeshellarg($file));
            if ($logContent !== false) {
                $logLines = explode("\n", $logContent);
                $logLines = array_reverse($logLines); // Most recent first
            } else {
                $logLines = [];
            }
        }

        // --- Process and display $logLines if successfully read ---
        if ($logLines !== false && !empty($logLines)) {
            echo '<table class="webacclog-table">';
            echo '<thead><tr>';
            echo '<th>Timestamp</th>';
            echo '<th>IP Address</th>';
            echo '<th>Request</th>';
            echo '<th>Status</th>';
            echo '<th>User Agent</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($logLines as $line) {
                // Should already be trimmed if using explode, but good practice if using file()
                $trimmedLine = trim($line);
                if (empty($trimmedLine)) continue;

                // Simple regex to parse common log format
                if (preg_match('/^(\S+) \S+ \S+ \[([^\]]+)\] "([^"]*)" (\d+) (\d+|-) "([^"]*)" "([^"]*)"$/', $trimmedLine, $matches)) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($matches[2]) . '</td>'; // Timestamp
                    echo '<td>' . htmlspecialchars($matches[1]) . '</td>'; // IP Address
                    echo '<td>' . htmlspecialchars($matches[3]) . '</td>'; // Request
                    echo '<td>' . htmlspecialchars($matches[4]) . '</td>'; // Status
                    echo '<td>' . htmlspecialchars($matches[7]) . '</td>'; // User Agent
                    echo '</tr>';
                } else {
                    // Show unparsed line
                    echo '<tr><td colspan="5"><em>[Unparsed Line]:</em> ' . htmlspecialchars($trimmedLine) . '</td></tr>';
                }
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="log-viewer-error">No log entries found or unable to read log file.</div>';
        }
    } else {
         echo '<div class="log-viewer-error">ERROR: The `WEB_ACCESS_LOG` path is not defined in `global.inc`.</div>';
    }

} else {
    echo '<div class="log-viewer-error">ERROR: You must login with sufficient privileges to use this function!</div>';
}
?>

</body>
</html>
