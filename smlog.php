<?php
include("session.inc");
include("user_files/global.inc");
include("common.inc");
include("authusers.php");

$is_authorized = (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true) && (get_user_auth("SMLOGUSER"));
?>
<html>
<head>
<title>Supermon-ng Login/out Log</title>
<style>
    body { font-family: sans-serif; }
    .log-file-info, .log-entry, .error-message { font-size: 14px; }
    .log-title { font-size: 20px; font-weight: bold; text-decoration: underline; text-align: center; }
    .error-message h3 { margin-top: 0; margin-bottom: 0; }
</style>
</head>
<body>

<?php if ($is_authorized): ?>
    <?php
    $log_file_path = $SMLOGNAME; 
    ?>
    <p class="log-file-info">File: <?php echo htmlspecialchars($log_file_path); ?></p>
    
    <div class="log-title">
        <p>Supermon-ng Login/Out LOG</p>
    </div>

    <?php
    $log_content_array = @file($log_file_path);

    if ($log_content_array === false): ?>
        <p class="error-message">Error: Could not read the log file (<?php echo htmlspecialchars($log_file_path); ?>) or it is empty.</p>
    <?php else:
        $reversed_log_content = array_reverse($log_content_array);
        foreach ($reversed_log_content as $line):
    ?>
            <p class="log-entry"><?php echo nl2br(htmlspecialchars(rtrim($line, "\r\n"))); ?></p>
    <?php 
        endforeach; 
    endif; 
    ?>

<?php else: ?>
    <p class="error-message">
        <br><h3>ERROR: You Must login to use this function!</h3>
    </p>
<?php endif; ?>

</body>
</html>