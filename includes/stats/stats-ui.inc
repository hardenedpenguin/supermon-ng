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
            margin: 0;
            padding: 0;
        }
        body {
            color: white;
            padding: 15px;
            background-color: #000000 !important;
            font-family: 'Courier New', Consolas, monospace;
            font-size: 13px;
            line-height: 1.4;
            margin: 0;
            width: 100%;
            min-height: 100vh;
            box-sizing: border-box;
        }
        .stats-container {
            width: 100%;
            max-width: none;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .ast-status-header-line {
            color: #888;
        }
        .ast-status-highlight {
            color: #00ff00;
            font-weight: bold;
        }
        .ast-status-section-title {
            color: #ffff00;
            font-weight: bold;
        }
        .ast-status-error-msg {
            color: #ff4444;
            font-weight: bold;
        }
        .ast-status-none-indicator {
            color: #888;
            font-style: italic;
        }
        .ast-status-count {
            color: #00ffff;
            font-weight: bold;
        }
        .nodeNum {
            color: #00ff00;
            font-weight: bold;
        }
        .error-message {
            color: #ff4444;
            font-weight: bold;
            font-size: 16px;
        }
    </style>
</head>
<body>
<div class="stats-container">
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
</div>
</body>
</html>
<?php
}
