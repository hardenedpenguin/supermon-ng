<?php
/**
 * Supermon-ng Display Configuration UI
 * 
 * Provides the user interface components for the display configuration including
 * HTML structure, CSS styling, and content rendering.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Output display configuration page HTML head section
 */
function outputDisplayConfigHead(): void {
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Supermon Display Settings</title>
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
<body class="display-config-page">
    <?php
}

/**
 * Output display configuration title
 */
function outputDisplayConfigTitle(): void {
    echo '<center>';
    echo '<p class="display-config-title"><b>Supermon Display Settings</b></p>';
}

/**
 * Output refresh parent script if form was submitted
 * 
 * @param bool $form_submitted_successfully Whether form was submitted
 */
function outputDisplayConfigRefreshScript(bool $form_submitted_successfully): void {
    if ($form_submitted_successfully) {
        echo '<script type="text/javascript">';
        echo 'refreshParent();';
        echo '</script>';
    }
}

/**
 * Output display configuration form
 * 
 * @param array $values Individual setting values
 */
function outputDisplayConfigForm(array $values): void {
    ?>
<form action="display-config.php" method="get">
<table class="display-config-table">
<tr>
<td valign="top">
 Display Detailed View<br>
    <input type="radio" class="display-config-radio display-config-radio-top" name="show_detailed" value="1" <?= ($values['sdetail'] === "1") ? 'checked' : '' ?>> YES
    <input type="radio" class="display-config-radio display-config-radio-spaced" name="show_detailed" value="0" <?= ($values['sdetail'] === "0") ? 'checked' : '' ?>> NO<br>
</td>
</tr><tr>
<td valign="top">
 Show the number of connections (Displays x of y)<br>
    <input type="radio" class="display-config-radio display-config-radio-top" name="show_number" value="1" <?= ($values['snum'] === "1") ? 'checked' : '' ?>> YES
    <input type="radio" class="display-config-radio display-config-radio-spaced" name="show_number" value="0" <?= ($values['snum'] === "0") ? 'checked' : '' ?>> NO<br>
</td>
</tr><tr>
<td valign="top">
 Show ALL Connections (NO omits NEVER Keyed)<br>
    <input type="radio" class="display-config-radio display-config-radio-top" name="show_all" value="1" <?= ($values['sall'] === "1") ? 'checked' : '' ?>> YES
    <input type="radio" class="display-config-radio display-config-radio-spaced" name="show_all" value="0" <?= ($values['sall'] === "0") ? 'checked' : '' ?>> NO<br>
</td>
</tr><tr>
<td valign="top">
 Maximum Number of Connections to Display in Each Node (0=ALL)<br><br>
 <input type="text" class="display-config-input" name="number_displayed" value="<?= htmlspecialchars($values['ndisp'], ENT_QUOTES, 'UTF-8') ?>" maxlength="4" size="3">
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
    <?php
}

/**
 * Output display configuration page closing tags
 */
function outputDisplayConfigFooter(): void {
    ?>
</body>
</html>
    <?php
}
