<?php
/**
 * Supermon-ng Asterisk Log UI
 * 
 * Provides the user interface components for the Asterisk log viewer including
 * HTML structure, CSS styling, and content rendering.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Output Asterisk log page HTML head section
 */
function outputAstlogHead(): void {
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Asterisk Messages Log</title>
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
 * Output Asterisk log page header
 */
function outputAstlogHeader(): void {
    ?>
<div class="log-viewer-container">
    <h1 class="log-viewer-title">Asterisk Messages Log Viewer</h1>
    <?php
}

/**
 * Output Asterisk log content
 * 
 * @param string $file Log file path
 * @param string|false $content Log file content or false on failure
 */
function outputAstlogContent(string $file, $content): void {
    if ($content === false) {
        echo '<p class="log-viewer-error">Error: Asterisk log file not found or is not readable by the web server.</p>';
        echo '<p class="log-viewer-error">Checked path: ' . htmlspecialchars($file) . '</p>';
        if (file_exists($file) && !is_readable($file)) {
            echo '<p class="log-viewer-error">Hint: Check file permissions. The web server user needs read access.</p>';
        }
    } else {
        echo '<p class="log-viewer-info">Displaying log file: ' . htmlspecialchars($file) . '</p>';
        echo '<hr>';
        echo '<pre class="log-viewer-pre">';
        echo htmlspecialchars($content);
        echo '</pre>';
    }
}

/**
 * Output access denied message
 */
function outputAstlogAccessDenied(): void {
    echo '<h3 class="log-viewer-error">ERROR: You must be logged in and authorized to view this log!</h3>';
}

/**
 * Output configuration error message
 */
function outputAstlogConfigError(): void {
    echo '<p class="log-viewer-error">Error: Asterisk log file path variable ($ASTERISK_LOG) is not defined in global.inc.</p>';
}

/**
 * Output Asterisk log page closing tags
 */
function outputAstlogFooter(): void {
    ?>
</div>
</body>
</html>
    <?php
}
