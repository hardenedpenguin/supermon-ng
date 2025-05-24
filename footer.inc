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
<br><br>
</body>
</html>