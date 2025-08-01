<?php
include("includes/session.inc");
include("user_files/global.inc");
include("includes/common.inc");
include("authusers.php");

$is_authorized = (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true) && (get_user_auth("SMLOGUSER"));
?>
<html>
<head>
<title>Supermon-ng Login/out Log</title>
<link type="text/css" rel="stylesheet" href="supermon-ng.css">
</head>
<body>

<?php if ($is_authorized): ?>
    <?php
    $log_file_path = $SMLOGNAME; 
    ?>
    <p class="log-viewer-info">File: <?php echo htmlspecialchars($log_file_path); ?></p>
    
    <div class="log-viewer-title">
        <p>Supermon-ng Login/Out LOG</p>
    </div>

    <?php
    $log_content_array = @file($log_file_path);

    if ($log_content_array === false): ?>
        <p class="log-viewer-error">Error: Could not read the log file (<?php echo htmlspecialchars($log_file_path); ?>) or it is empty.</p>
    <?php else:
        $reversed_log_content = array_reverse($log_content_array);
        foreach ($reversed_log_content as $line):
    ?>
            <p class="log-viewer-entry"><?php echo nl2br(htmlspecialchars(rtrim($line, "\r\n"))); ?></p>
    <?php 
        endforeach; 
    endif; 
    ?>

<?php else: ?>
    <p class="log-viewer-error">
        <br><h3>ERROR: You Must login to use this function!</h3>
    </p>
<?php endif; ?>

</body>
</html>