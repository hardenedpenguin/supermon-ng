<?php
/**
 * Supermon-ng Supermon Log UI
 * 
 * Provides the user interface components for the Supermon log viewer including
 * HTML structure, CSS styling, and content rendering.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Output Supermon log page HTML head section
 */
function outputSmlogHead(): void {
    ?>
<html>
<head>
<title>Supermon-ng Login/out Log</title>
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
    <?php
}

/**
 * Output Supermon log page header
 * 
 * @param string $log_file_path Log file path
 */
function outputSmlogHeader(string $log_file_path): void {
    ?>
    <p class="log-viewer-info">File: <?php echo htmlspecialchars($log_file_path); ?></p>
    
    <div class="log-viewer-title">
        <p>Supermon-ng Login/Out LOG</p>
    </div>
    <?php
}

/**
 * Output Supermon log content
 * 
 * @param string $log_file_path Log file path
 * @param array|false $log_content_array Log content array or false on failure
 */
function outputSmlogContent(string $log_file_path, $log_content_array): void {
    if ($log_content_array === false) {
        echo '<p class="log-viewer-error">Error: Could not read the log file (' . htmlspecialchars($log_file_path) . ') or it is empty.</p>';
    } else {
        $reversed_log_content = processSmlogContent($log_content_array);
        foreach ($reversed_log_content as $line) {
            echo '<p class="log-viewer-entry">' . nl2br(htmlspecialchars(rtrim($line, "\r\n"))) . '</p>';
        }
    }
}

/**
 * Output access denied message
 */
function outputSmlogAccessDenied(): void {
    echo '<p class="log-viewer-error"><br><h3>ERROR: You Must login to use this function!</h3></p>';
}

/**
 * Output Supermon log page closing tags
 */
function outputSmlogFooter(): void {
    ?>
</body>
</html>
    <?php
}
