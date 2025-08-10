<?php
/**
 * Supermon-ng Save UI
 * 
 * Provides the user interface components for the save status display including
 * HTML structure, CSS styling, and content rendering.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Output save page HTML head section
 */
function outputSaveHead(): void {
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
    <?php
}

/**
 * Output save status content
 * 
 * @param string $target_filepath File path that was saved
 * @param array $saveResults Save operation results
 */
function outputSaveStatus(string $target_filepath, array $saveResults): void {
    $return_var = $saveResults['return_var'];
    $stdout_data = $saveResults['stdout_data'];
    $stderr_data = $saveResults['stderr_data'];
    ?>
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
}

/**
 * Output edit again form
 * 
 * @param string $target_filepath File path to edit again
 */
function outputEditAgainForm(string $target_filepath): void {
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
    include 'includes/form.inc';
}

/**
 * Output return to list form
 */
function outputReturnToListForm(): void {
    // Setup form data for "Return to List" button
    $fields = [];
    $action = 'configeditor.php';
    $method = 'POST';
    $submit_label = 'Return to File List';
    $form_class = 'save-form';
    $submit_class = 'submit-large';
    include 'includes/form.inc';
}

/**
 * Output save page closing tags
 */
function outputSaveFooter(): void {
    ?>
</div>
</body>
</html>
    <?php
}
