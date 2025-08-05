<?php

include("includes/security.inc");
include("includes/session.inc");
include("user_files/global.inc");
include("includes/common.inc");
include("authusers.php");
include("authini.php");

if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("GPIOUSER"))) {
    die ("<br><h3 class='error-message'>ERROR: You Must login to use the 'GPIO' function!</h3>");
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

// Validate GPIO pin number
function validate_gpio_pin($pin) {
    return is_numeric($pin) && $pin >= 0 && $pin <= 40;
}

// Validate GPIO state
function validate_gpio_state($state) {
    return in_array($state, ['0', '1', 'input', 'output', 'up', 'down']);
}

// Handle GPIO operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $Bit = $_POST['Bit'] ?? '';
    $State = $_POST['State'] ?? '';
    
    // Validate inputs
    if (!validate_gpio_pin($Bit)) {
        die("Invalid GPIO pin number.");
    }
    
    if (!validate_gpio_state($State)) {
        die("Invalid GPIO state.");
    }
    
    $escaped_bit = escapeshellarg($Bit);
    $escaped_state = escapeshellarg($State);
    
    switch ($State) {
        case 'input':
            safe_exec("gpio", "mode {$escaped_bit} input");
            break;
        case 'up':
            safe_exec("gpio", "mode {$escaped_bit} up");
            break;
        case 'down':
            safe_exec("gpio", "mode {$escaped_bit} down");
            break;
        case 'output':
            safe_exec("gpio", "mode {$escaped_bit} output");
            break;
        case '0':
        case '1':
            safe_exec("gpio", "write {$escaped_bit} {$escaped_state}");
            break;
    }
}

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
<link type="text/css" rel="stylesheet" href="css/custom.css">
</head>
<body>
    <p class="page-title">Pi GPIO Control</p>
    <br>
    
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
    // Safe GPIO status check
    $command = "gpio readall";
    $data = safe_exec($command);
    
    if ($data !== false) {
        print "<pre>" . htmlspecialchars($data, ENT_QUOTES, 'UTF-8') . "</pre>";
    } else {
        print "<p class='error-message'>Error: Could not read GPIO status. Make sure gpio command is available.</p>";
    }
    ?>
</body>
</html>
