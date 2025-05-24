<?php

include("session.inc");
include("authusers.php");

if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("CFGEDUSER"))) {
    die ("<br><h3>ERROR: You Must login to use the 'Edit' function!</h3>");
}

$file = $_POST["file"];
echo $file;
$fh = fopen($file, 'r') or die('<br><br> ERROR: Could not open file: ' . $file . '<br><br> Does not exist or is Protected.');
$data = fread($fh, filesize($file)) or die('<br><br> ERROR: Could not read file!');
fclose($fh);
$nldata = nl2br($data);

if (is_writable($file)) {
    $write_ok = 1;
?>
    <form style="display:inline;" action="save.php" method="post" name="savefile" target="_self">
        <link type='text/css' rel='stylesheet' href='supermon-ng.css'>
        <textarea name="edit" style="font-size:16px; width:100%; height:88%;" wrap="off"><?php echo $data; ?></textarea>
        <input name="filename" type="hidden" value="<?php echo $file; ?>">
        <br><input name="Submit" type="submit" class="submit-large" value=" WRITE your Edits ">
    </form>
    <form style='display:inline;' name="REFRESH" method="POST" action="configeditor.php">
        <link type='text/css' rel='stylesheet' href='supermon-ng.css'>
        <input name="return" tabindex=50 type="submit" class="submit-large" value="Return to Index without Writing">
    </form>
<?php
} else {
    echo "<p> File is <b>READ ONLY</b> - To edit, use vi or nano in a Linux shell</p>";
    echo "<form name='REFRESH' method='POST' action='configeditor.php'>";
    echo "<link type='text/css' rel='stylesheet' href='supermon-ng.css'>";
    echo "<input name='return' tabindex=50 type='submit' class='submit-large' value='Return to Index'></form>";
    $write_ok = 0;
?>
    <form action="save.php" method="post" name="savefile" target="_self">
        <link type='text/css' rel='stylesheet' href='supermon-ng.css'>
        <textarea name="edit" style="width:100%; height:87%;" wrap="off"><?php echo $data; ?></textarea>
        <input name="filename" type="hidden" value="<?php echo $file; ?>">
        <br><br>
    </form>
<?php
}
?>
