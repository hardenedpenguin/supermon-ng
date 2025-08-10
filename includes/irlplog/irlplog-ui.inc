<?php
/**
 * Supermon-ng IRLP Log UI
 * 
 * Provides the user interface components for the IRLP log viewer including
 * HTML structure, CSS styling, and content rendering.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Output IRLP log page HTML head section
 */
function outputIrlplogHead(): void {
    ?>
<html>
<head>
<title>IRLP messages Log</title>
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
 * Output IRLP log content
 * 
 * @param string $file Log file path
 * @param string|false $content Log file content or false on failure
 */
function outputIrlplogContent(string $file, $content): void {
    ?>
    <pre class="log-viewer-pre">
<?php
    echo "File: " . htmlspecialchars($file) . "\n";
    echo "-----------------------------------------------------------------\n";

    if ($content === false) {
        echo "\n\nIRLP Log is not available.\n";
    } else {
        echo htmlspecialchars($content);
    }
?>
    </pre>
    <?php
}

/**
 * Output access denied message
 */
function outputIrlplogAccessDenied(): void {
    echo '<p class="log-viewer-error">ERROR: You Must login to use this function!</p>';
}

/**
 * Output IRLP log page closing tags
 */
function outputIrlplogFooter(): void {
    ?>
</body>
</html>
    <?php
}
