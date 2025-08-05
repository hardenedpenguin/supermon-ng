<?php
include("includes/session.inc");
include("user_files/global.inc");
include("includes/common.inc");
include("authusers.php");

$is_logged_in_and_authorized = (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && get_user_auth("IRLPLOGUSER"));

?>
<html>
<head>
<title>IRLP messages Log</title>
<!-- Modular CSS Files -->
<link type="text/css" rel="stylesheet" href="css/base.css">
<link type="text/css" rel="stylesheet" href="css/layout.css">
<link type="text/css" rel="stylesheet" href="css/menu.css">
<link type="text/css" rel="stylesheet" href="css/tables.css">
<link type="text/css" rel="stylesheet" href="css/forms.css">
<link type="text/css" rel="stylesheet" href="css/widgets.css">
<link type="text/css" rel="stylesheet" href="css/responsive.css">
<!-- Custom CSS (load last to override defaults) -->
<link type="text/css" rel="stylesheet" href="css/custom.css">
</head>
<body>

<?php if ($is_logged_in_and_authorized): ?>
    <pre class="log-viewer-pre">
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
    <p class="log-viewer-error">ERROR: You Must login to use this function!</p>
<?php endif; ?>

</body>
</html>