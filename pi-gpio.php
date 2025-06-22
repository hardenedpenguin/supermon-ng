<?php

include("session.inc");
include("authusers.php");

if ($_SESSION['sm61loggedin'] !== true) {
    die("<br><h3>ERROR: You Must login to use the 'Pi GPIO' function!</h3>");
}

if (!get_user_auth("GPIOUSER")) {
    die("<br><h3>ERROR: You Must be authorized to use the 'Pi GPIO' function!</h3>");
}

?>
<html>
<head>
    <link type="text/css" rel="stylesheet" href="supermon-ng.css">
</head>
<body class="gpio-page">
    <center>
        <p class="gpio-title"><b>RPi GPIO Status</b></p>

        <?php
        if ((isset($_GET["direction"])) && ($_GET["direction"] != "")) {
            $Direction = $_GET["direction"];
            $Bit       = $_GET["bit"];
            $State     = $_GET["state"];
            $Pullup    = $_GET["pullup"];

            // Ensure output message is white
            print "<p class=\"gpio-status\">Direction - <b>$Direction</b>     Bit - <b>$Bit</b>    Pullup - <b>$Pullup</b>    State - <b>$State</b></p>";

            if ($Direction == "In") {
                // Sanitize input before using in exec is highly recommended for security
                // For simplicity here, assuming $Bit is validated by the dropdown
                exec("gpio mode " . escapeshellarg($Bit) . " input");
                if ($Pullup == "Yes") {
                    exec("gpio mode " . escapeshellarg($Bit) . " up");
                } else {
                    exec("gpio mode " . escapeshellarg($Bit) . " down");
                }
            } else {
                 // Sanitize input before using in exec
                exec("gpio mode " . escapeshellarg($Bit) . " output");
                // Sanitize State as well (should be 0 or 1)
                $validState = ($State == '1') ? '1' : '0';
                exec("gpio write " . escapeshellarg($Bit) . " " . $validState);
            }
        }
        ?>

        <form action="pi-gpio.php" method="get">
            <table class="gpio-table">
                <tr>
                    <td valign="top">
                        <b>Select Input or Output</b><br><br>
                        <input type="radio" class="gpio-radio" name="direction" value="In" checked> Input
                        <input type="radio" class="gpio-radio gpio-radio-spaced" name="direction" value="Out"> Output
                    </td>
                    <td valign="top">
                           <b>Pullup</b><br><br>
                        <input type="radio" class="gpio-radio" name="pullup" value="No" checked> No
                        <input type="radio" class="gpio-radio gpio-radio-spaced" name="pullup" value="Yes"> Yes
                    </td>
                    <td valign="top">
                        <b>Select Bit</b><br><br>
                        <select name="bit" class="gpio-select">
                            <option value="0"> 0</option>
                            <option value="1"> 1</option>
                            <option value="2"> 2</option>
                            <option value="3"> 3</option>
                            <option value="4"> 4</option>
                            <option value="5"> 5</option>
                            <option value="6"> 6</option>
                            <option value="7"> 7</option>
                            <option value="21">21</option>
                            <option value="22">22</option>
                            <option value="23">23</option>
                            <option value="24">24</option>
                            <option value="25">25</option>
                            <option value="26">26</option>
                            <option value="27">27</option>
                            <option value="28">28</option>
                            <option value="29">29</option>
                        </select>
                    </td>
                    <td valign="top">
                           <b>State</b><br><br>
                        <input type="radio" class="gpio-radio" name="state" value="0" checked> 0
                        <input type="radio" class="gpio-radio gpio-radio-spaced" name="state" value="1"> 1
                    </td>
                </tr>
                <tr>
                    <td colspan="4" align="center">
                        <input type="submit" class="submit-large" value="Update">
                         
                        <input type="button" class="submit-large" Value="Close Window" onclick="self.close()">
                    </td>
                </tr>
            </table>
        </form>

        <div class="gpio-output">
            <?php
            // Execute the command and capture output
            // Using escapeshellcmd for security although 'gpio readall' is static
            $command = 'gpio readall';
            $data = shell_exec(escapeshellcmd($command));
            // Ensure the output is HTML-safe and display within pre tags
            print "<pre>" . htmlspecialchars($data, ENT_QUOTES, 'UTF-8') . "</pre>";
            ?>
        </div>

        <input type="button" class="submit-large" Value="View GPIO howto" onclick="window.open('http://www.crompton.com/hamradio/hamvoip-howto/GPIO_how-to.pdf')">

    </center>
</body>
</html>
