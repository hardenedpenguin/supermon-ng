<?php

include("session.inc");
include("authusers.php");
include("user_files/global.inc");
include("common.inc");

if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("CFGEDUSER"))) {
    die ("<br><h3>ERROR: You Must login to use the 'Edit' function!</h3>");
}

$file = $_POST["file"] ?? null;

if (!$file) {
    die ("<br><h3>ERROR: No file specified for editing.</h3>");
}

if (strpos($file, '..') !== false) {
    die("<br><h3>ERROR: Invalid file path.</h3>");
}

$view_only_files = [
    "/usr/local/bin/AUTOSKY/AutoSky-log.txt",
    "/var/www/html/supermon-ng/user_files/IMPORTANT-README"
];

$is_view_only = in_array($file, $view_only_files);

$data = '';
if (file_exists($file) && is_readable($file)) {
    $fh = fopen($file, 'r');
    if ($fh) {
        $filesize = filesize($file);
        if ($filesize > 0) {
            $data = fread($fh, $filesize);
        } elseif ($filesize === 0) {
            $data = '';
        } else {
            $data = 'ERROR: Could not determine file size or read file!';
        }
        fclose($fh);
    } else {
        $data = 'ERROR: Could not open file: ' . htmlspecialchars($file) . '. Check permissions or path.';
    }
} else {
    $data = 'ERROR: File does not exist or is not readable: ' . htmlspecialchars($file);
}

?>
<html>
<head>
    <title>Edit File: <?php echo htmlspecialchars(basename($file)); ?></title>
    <link type='text/css' rel='stylesheet' href='supermon-ng.css'>
</head>
<body class="edit-page">
<div class="container">

    <h2>Editing: <?php echo htmlspecialchars($file); ?></h2>

    <?php if (strpos($data, 'ERROR:') === 0): ?>
        <p class="edit-error"><?php echo htmlspecialchars($data); ?></p>
    <?php else: ?>
        <form class="edit-form" action="save.php" method="post" name="savefile" target="_self">
            <textarea name="edit" class="edit-textarea" wrap="off"><?php echo htmlspecialchars($data); ?></textarea>
            <input name="filename" type="hidden" value="<?php echo htmlspecialchars($file); ?>">
            <br><br>
            <?php if (!$is_view_only): ?>
                <input name="Submit" type="submit" class="submit-large" value=" WRITE Edits to File ">
            <?php else: ?>
                <p><b>This file is for viewing only. Changes cannot be saved through this interface.</b></p>
            <?php endif; ?>
        </form>
    <?php endif; ?>

    <form class="edit-form" name="REFRESH" method="POST" action="configeditor.php">
        <input name="return" tabindex="50" type="submit" class="submit-large" value="Return to File List">
    </form>

</div>
</body>
</html>