<?php
/**
 * AllStar Statistics UI Rendering
 * 
 * Handles HTML output, page structure, and CSS includes
 */

/**
 * Render the HTML head section with CSS includes and custom styling
 */
function renderStatsHead() {
?>
<html>
<head>
    <title>AllStar Status</title>
    <!-- Modular CSS Files -->
    <link rel="stylesheet" type="text/css" href="css/base.css">
    <link rel="stylesheet" type="text/css" href="css/layout.css">
    <link rel="stylesheet" type="text/css" href="css/menu.css">
    <link rel="stylesheet" type="text/css" href="css/tables.css">
    <link rel="stylesheet" type="text/css" href="css/forms.css">
    <link rel="stylesheet" type="text/css" href="css/widgets.css">
    <link rel="stylesheet" type="text/css" href="css/responsive.css">
    <!-- Custom CSS (load last to override defaults) -->
    <?php if (file_exists('css/custom.css')): ?>
    <link rel="stylesheet" type="text/css" href="css/custom.css">
    <?php endif; ?>
    <style>
        html {
            background-color: #000000 !important;
        }
        body {
             color: white;
             padding: 10px;
             background-color: #000000 !important;
        }
        .ast-status-pre {
             background-color: #000000 !important;
        }
    </style>
</head>
<body>
<pre class="ast-status-pre">
<?php
}

/**
 * Render the main stats content section
 */
function renderStatsContent($fp) {
    // Render page header
    page_header();
    
    // Display all status sections
    show_all_nodes($fp);
    show_peers($fp);
    show_channels($fp);
    show_netstats($fp);
}

/**
 * Render the HTML footer
 */
function renderStatsFooter() {
?>
</pre>
</body>
</html>
<?php
}
