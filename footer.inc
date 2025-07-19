<div class="clearer"></div>

<div id="footer">

<?php
$maintainer_text = "";
if (!empty($MAINTAINER)) {
    $maintainer_text = htmlspecialchars($MAINTAINER, ENT_QUOTES, 'UTF-8');
} else {
    $name_safe = isset($NAME) ? htmlspecialchars($NAME, ENT_QUOTES, 'UTF-8') : '';
    $call_safe = isset($CALL) ? htmlspecialchars($CALL, ENT_QUOTES, 'UTF-8') : '';
    $maintainer_text = "{$name_safe},   {$call_safe}";
}
?>
<b>System maintained by:   <i><?php echo $maintainer_text; ?></i></b>

</div>

<!-- Donate Button Section -->
<div id="donate-section" style="margin-top: 20px; text-align: center;">
    <button id="donatebutton" class="submit-large" onclick="openDonatePopup()" style="background-color: #6b4ce6; color: white; border: none; padding: 12px 24px; font-size: 1.1em; font-weight: bold; border-radius: 6px; cursor: pointer; transition: background-color 0.3s ease;">
        💝 Support This Project
    </button>
</div>

<script>
function openDonatePopup() {
    var width = 600;
    var height = 700;
    var left = (screen.width - width) / 2;
    var top = (screen.height - height) / 2;
    var popup = window.open('donate.php', 'Donate', 'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes');
    if (popup) {
        popup.focus();
    }
}
</script>

<br><br>
</body>
</html>