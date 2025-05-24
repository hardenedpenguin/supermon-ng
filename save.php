<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include("session.inc");
include("authusers.php");

if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("CFGEDUSER"))) {
    http_response_code(403);
    echo "<!DOCTYPE html><html><head><title>Error</title>";
    echo "<link type='text/css' rel='stylesheet' href='supermon-ng.css'>";
    echo "</head><body>";
    echo "<h3>ERROR: You Must login to use the 'Save' function!</h3>";
    echo "</body></html>";
    exit;
}

$edit_content = $_POST["edit"] ?? '';
$filename = $_POST["filename"] ?? '';

$edit_content = str_replace("\r", "", $edit_content);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Configuration Save</title>
    <link type="text/css" rel="stylesheet" href="supermon-ng.css">
</head>
<body>

<h1>Configuration Save Result</h1>

<form method="POST" action="configeditor.php">
    <input name="return" tabindex="50" type="submit" class="submit-large" value="Return to Index">
</form>
<hr>

<?php

if (empty(trim($filename))) {
    echo "<strong>ERROR: Filename was not provided or is empty.</strong><br>";
} elseif (!file_exists($filename)) {
    echo "<strong>ERROR: The file <em>" . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') . "</em> does not exist.</strong>";
} elseif (is_writable($filename)) {
    $backup_filename = $filename . ".bak";
    if (copy($filename, $backup_filename)) {
        echo "<strong>Success, backup file created <em>(" . htmlspecialchars($backup_filename, ENT_QUOTES, 'UTF-8') . ")</em></strong><br>";
    } else {
        echo "<strong>Warning: Could not create backup file <em>(" . htmlspecialchars($backup_filename, ENT_QUOTES, 'UTF-8') . ")</em>. Proceeding with save...</strong><br>";
    }

    if (file_put_contents($filename, $edit_content) !== false) {
        echo "<strong>Success, wrote edits to file <em>(" . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') . ")</em>:</strong><br><br>";
        echo nl2br(htmlspecialchars($edit_content, ENT_QUOTES, 'UTF-8'));
        echo "<br>";
    } else {
        echo "<strong>Cannot write to file <em>(" . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') . ")</em></strong>";
        echo "</body></html>";
        exit;
    }
} else {
    echo "<strong>The file <em>(" . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') . ")</em> is not writable.</strong>";
}
?>

</body>
</html>