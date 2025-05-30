<?php

$defaults = [
    'number-displayed' => "0",
    'show-number'      => "0",
    'show-all'         => "1",
    'show-detailed'    => "1"
];

$settings = $defaults;

if (isset($_COOKIE['display-data']) && is_array($_COOKIE['display-data'])) {
    foreach ($defaults as $key => $defaultValue) {
        if (isset($_COOKIE['display-data'][$key])) {
            $settings[$key] = $_COOKIE['display-data'][$key];
        }
    }
}

function validateSettings(array &$currentSettings, array $defaultValues): void
{
    if (!is_numeric($currentSettings['number-displayed']) || (int)$currentSettings['number-displayed'] < 0) {
        $currentSettings['number-displayed'] = $defaultValues['number-displayed'];
    }
    foreach (['show-number', 'show-all', 'show-detailed'] as $key) {
        if (!in_array($currentSettings[$key], ["0", "1"])) {
            $currentSettings[$key] = $defaultValues[$key];
        }
    }
}

validateSettings($settings, $defaults);

$form_submitted_successfully = false;

if (isset($_GET["number_displayed"]) && $_GET["number_displayed"] !== "") {
    $form_submitted_successfully = true;

    $settings['number-displayed'] = $_GET["number_displayed"];
    $settings['show-number']      = $_GET["show_number"]   ?? $defaults['show-number'];
    $settings['show-all']         = $_GET["show_all"]      ?? $defaults['show-all'];
    $settings['show-detailed']    = $_GET["show_detailed"] ?? $defaults['show-detailed'];

    validateSettings($settings, $defaults);
    
    $expiretime = 2147483645;
    $cookie_path = "/";

    foreach ($settings as $key => $value) {
        setcookie("display-data[{$key}]", $value, $expiretime, $cookie_path);
    }
}

$ndisp   = $settings['number-displayed'];
$snum    = $settings['show-number'];
$sall    = $settings['show-all'];
$sdetail = $settings['show-detailed'];

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Supermon Display Settings</title>
    <link type="text/css" rel="stylesheet" href="supermon-ng.css">
    <style>
        input[type="text"] {
            background-color: #333;
            color: white;
            border: 1px solid #555;
        }
        input[type="radio"] {
            accent-color: #007bff;
        }
        .submit-large {
            background-color: #555;
            color: white;
            border: 1px solid #777;
        }
        .submit-large:hover {
            background-color: #666;
        }
    </style>
    <script>
        function refreshParent() {
            if (window.opener && !window.opener.closed) {
                try {
                    window.opener.location.reload();
                } catch (e) {
                    
                }
            }
        }
        window.addEventListener('unload', refreshParent);
    </script>
</head>
<body style="background-color:black; color:white;">
<center>
<p style="font-size:1.5em;margin-bottom:0;"><b>Supermon Display Settings</b></p>

<?php if ($form_submitted_successfully): ?>
    <script type="text/javascript">
        refreshParent();
    </script>
<?php endif; ?>

<form action="display-config.php" method="get">
<table cellspacing="15" style="margin-top:0; font-size:22px;">
<tr>
<td valign="top">
 Display Detailed View<br>
    <input type="radio" style="transform: scale(2); margin-top:6px;" name="show_detailed" value="1" <?= ($sdetail === "1") ? 'checked' : '' ?>> YES
    <input type="radio" style="transform: scale(2); margin-top:14px; margin-left:20px;" name="show_detailed" value="0" <?= ($sdetail === "0") ? 'checked' : '' ?>> NO<br>
</td>
</tr><tr>
<td valign="top">
 Show the number of connections (Displays x of y)<br>
    <input type="radio" style="transform: scale(2); margin-top:6px;" name="show_number" value="1" <?= ($snum === "1") ? 'checked' : '' ?>> YES
    <input type="radio" style="transform: scale(2); margin-top:14px; margin-left:20px;" name="show_number" value="0" <?= ($snum === "0") ? 'checked' : '' ?>> NO<br>
</td>
</tr><tr>
<td valign="top">
 Show ALL Connections (NO omits NEVER Keyed)<br>
    <input type="radio" style="transform: scale(2); margin-top:6px;" name="show_all" value="1" <?= ($sall === "1") ? 'checked' : '' ?>> YES
    <input type="radio" style="transform: scale(2); margin-top:14px; margin-left:20px;" name="show_all" value="0" <?= ($sall === "0") ? 'checked' : '' ?>> NO<br>
</td>
</tr><tr>
<td valign="top">
 Maximum Number of Connections to Display in Each Node (0=ALL)<br><br>
 <input type="text" style="transform: scale(1.5); margin-left:20px;" name="number_displayed" value="<?= htmlspecialchars($ndisp, ENT_QUOTES, 'UTF-8') ?>" maxlength="4" size="3">
</td>
</tr>
<tr>
<td align="center">
<input type="submit" class="submit-large" value="Update">
   
<input type="button" class="submit-large" Value="Close Window" onclick="self.close()">
</td>
</tr>
</table>
</form>
</center>
</body>
</html>