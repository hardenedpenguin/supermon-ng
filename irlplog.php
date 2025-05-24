<?php
include("session.inc");
include("user_files/global.inc");
include("common.inc");
include("authusers.php");

$is_logged_in_and_authorized = (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && get_user_auth("IRLPLOGUSER"));

?>
<html>
<head>
<title>IRLP messages Log</title>
<link rel="stylesheet" type="text/css" href="supermon-ng.css">
<style>
    pre {
        font-size: 16px;
        white-space: pre-wrap;
        word-wrap: break-word;
    }
</style>
</head>
<body>

<?php if ($is_logged_in_and_authorized): ?>
    <pre>
<?php
    $file = $IRLP_LOG;
    echo "File: " . htmlspecialchars($file) . "\n";
    echo "-----------------------------------------------------------------\n";

    if (file_exists($file)) {
        echo htmlspecialchars(file_get_contents($file));
    } else {
        echo "\n\nIRLP Log is not available.\n";
    }
?>
    </pre>
<?php else: ?>
    <p class="error-message">ERROR: You Must login to use this function!</p>
<?php endif; ?>

</body>
</html>