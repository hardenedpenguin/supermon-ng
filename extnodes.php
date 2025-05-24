<?php
include("session.inc");
include("common.inc");
include("authusers.php");

if (
    !isset($_SESSION['sm61loggedin']) ||
    $_SESSION['sm61loggedin'] !== true ||
    !get_user_auth("EXNUSER")
) {
?>
<!DOCTYPE html>
<html>
<head>
    <title>Access Denied</title>
</head>
<body>
    <h3>ERROR: You Must login to use this function!</h3>
</body>
</html>
<?php
    exit;
}

$filePath = $EXTNODES;
?>
<!DOCTYPE html>
<html>
<head>
    <title>AllStar rpt_extnodes contents</title>
</head>
<body>
<pre>
<?php
    echo "File: " . htmlspecialchars($filePath, ENT_QUOTES, 'UTF-8') . "\n";
    echo "-----------------------------------------------------------------\n";

    if (file_exists($filePath)) {
        echo file_get_contents($filePath);
    } else {
        echo "\n\nAllStar rpt_extnodes table is not available.\n";
    }
?>
</pre>
</body>
</html>