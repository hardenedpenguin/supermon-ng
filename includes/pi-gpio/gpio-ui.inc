<?php
/**
 * Pi GPIO UI Components
 * 
 * Handles HTML template rendering and form display
 * for GPIO control interface.
 */

/**
 * Render the HTML head section
 */
function renderGPIOHead() {
    ?>
<html>
<head>
    <title>Pi GPIO Control</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
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
    <?php
}

/**
 * Render the page body start and title
 */
function renderGPIOBodyStart() {
    ?>
<body>
    <p class="page-title">Pi GPIO Control</p>
    <br>
    <?php
}

/**
 * Render the GPIO control form
 */
function renderGPIOForm() {
    ?>
    <form method="post" action="">
        <table class="gpio-table">
            <tr>
                <td>GPIO Pin:</td>
                <td><input type="number" id="gpio_pin" name="Bit" min="0" max="40" required></td>
            </tr>
            <tr>
                <td>State:</td>
                <td>
                    <select name="State" required>
                        <option value="">Select State</option>
                        <option value="input">Input</option>
                        <option value="output">Output</option>
                        <option value="up">Pull Up</option>
                        <option value="down">Pull Down</option>
                        <option value="0">Write 0</option>
                        <option value="1">Write 1</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <input type="submit" value="Execute" class="gpio-button">
                </td>
            </tr>
        </table>
    </form>
    
    <br>
    <h3>GPIO Status</h3>
    <?php
}

/**
 * Render the page footer
 */
function renderGPIOFooter() {
    ?>
</body>
</html>
    <?php
}
