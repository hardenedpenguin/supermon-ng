<?php
include("includes/session.inc");
include("authusers.php");
include("user_files/global.inc");
include("includes/common.inc");

if (($_SESSION['sm61loggedin'] !== true) || (!get_user_auth("CFGEDUSER"))) {
    die ("<br><h3>ERROR: You Must login to use the 'Save' function!</h3>");
}

$target_filepath = $_POST['filename'] ?? null;
$new_content = $_POST['edit'] ?? '';

if (!$target_filepath || strpos($target_filepath, '..') !== false) {
    die("<br><h3>ERROR: Invalid file path specified for saving.</h3>");
}

$helper_script = "/usr/local/sbin/supermon_unified_file_editor.sh";

$cmd = "sudo " . escapeshellcmd($helper_script) . " " . escapeshellarg($target_filepath);

$output_stdout = [];
$output_stderr = [];
$return_var = -1;

$descriptorspec = array(
   0 => array("pipe", "r"),
   1 => array("pipe", "w"),
   2 => array("pipe", "w")
);

$process = proc_open($cmd, $descriptorspec, $pipes);

$stdout_data = '';
$stderr_data = '';

if (is_resource($process)) {
    fwrite($pipes[0], $new_content);
    fclose($pipes[0]);

    $stdout_data = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr_data = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $return_var = proc_close($process);
} else {
    $return_var = -1;
    $stderr_data = "Error: Could not execute the helper command. Check web server logs (e.g., Apache error log) for sudo or permission issues.";
}

?>
<html>
<head>
    <title>Save File Status</title>
    <!-- Modular CSS Files -->
    <link type='text/css' rel='stylesheet' href='css/base.css'>
    <link type='text/css' rel='stylesheet' href='css/layout.css'>
    <link type='text/css' rel='stylesheet' href='css/menu.css'>
    <link type='text/css' rel='stylesheet' href='css/tables.css'>
    <link type='text/css' rel='stylesheet' href='css/forms.css'>
    <link type='text/css' rel='stylesheet' href='css/widgets.css'>
    <link type='text/css' rel='stylesheet' href='css/responsive.css'>
    <!-- Custom CSS (load last to override defaults) -->
    <?php if (file_exists('css/custom.css')): ?>
    <link type='text/css' rel='stylesheet' href='css/custom.css'>
    <?php endif; ?>
</head>
<body>
<div class="log-viewer-container">
    <h2 class="log-viewer-title">Save Status for: <?php echo htmlspecialchars($target_filepath); ?></h2>

    <?php if ($return_var === 0): ?>
        <p class="log-viewer-info">File saved successfully!</p>
    <?php else: ?>
        <p class="log-viewer-error">Error saving file. Helper script exit code: <?php echo htmlspecialchars($return_var); ?></p>
    <?php endif; ?>

    <?php if (!empty($stdout_data)): ?>
        <h3>Helper Script Output (stdout):</h3>
        <pre class="log-viewer-pre"><?php echo htmlspecialchars(trim($stdout_data)); ?></pre>
    <?php endif; ?>

    <?php if (!empty($stderr_data)): ?>
        <h3 class="<?php echo ($return_var !== 0) ? 'log-viewer-error' : ''; ?>">Helper Script Output (stderr):</h3>
        <pre class="log-viewer-pre"><?php echo htmlspecialchars(trim($stderr_data)); ?></pre>
    <?php endif; ?>

    <br>
    <?php
    // Setup form data for "Edit Again" button
    $fields = [
        [
            'type' => 'hidden',
            'name' => 'file',
            'value' => $target_filepath,
            'label' => '',
            'attrs' => '',
            'wrapper_class' => ''
        ]
    ];
    $action = 'edit.php';
    $method = 'POST';
    $submit_label = 'Edit This File Again';
    $form_class = 'save-form';
    $submit_class = 'submit-large';
    ?>
    <?php include 'includes/form.inc'; ?>
    
    <?php
    // Setup form data for "Return to List" button
    $fields = [];
    $action = 'configeditor.php';
    $method = 'POST';
    $submit_label = 'Return to File List';
    $form_class = 'save-form';
    $submit_class = 'submit-large';
    ?>
    <?php include 'includes/form.inc'; ?>
</div>
</body>
</html>