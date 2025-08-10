<?php
/**
 * Supermon-ng Edit UI
 * 
 * Provides the user interface components for the file editing interface including
 * HTML structure, CSS styling, and content rendering.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Output edit page HTML head section
 * 
 * @param string $filename File name for title
 */
function outputEditHead(string $filename): void {
    ?>
<html>
<head>
    <title>Edit File: <?php echo htmlspecialchars($filename); ?></title>
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
<body class="edit-page">
    <?php
}

/**
 * Output edit page header
 * 
 * @param string $file File path
 */
function outputEditHeader(string $file): void {
    ?>
<div class="container">
    <h2>Editing: <?php echo htmlspecialchars($file); ?></h2>
    <?php
}

/**
 * Output edit form content
 * 
 * @param string $data File content or error message
 * @param string $file File path
 * @param bool $isViewOnly Whether file is view-only
 */
function outputEditContent(string $data, string $file, bool $isViewOnly): void {
    if (strpos($data, 'ERROR:') === 0) {
        echo "<p class=\"edit-error\">" . htmlspecialchars($data) . "</p>";
    } else {
        // Setup form data for reusable form include
        $fields = [
            [
                'type' => 'textarea',
                'name' => 'edit',
                'value' => $data,
                'label' => '',
                'attrs' => 'class="edit-textarea" wrap="off"',
                'wrapper_class' => ''
            ],
            [
                'type' => 'hidden',
                'name' => 'filename',
                'value' => $file,
                'label' => '',
                'attrs' => '',
                'wrapper_class' => ''
            ]
        ];
        $action = 'save.php';
        $method = 'post';
        $submit_label = $isViewOnly ? false : ' WRITE Edits to File ';
        $form_class = 'edit-form';
        $submit_class = 'submit-large';
        include 'includes/form.inc';
        
        if ($isViewOnly) {
            echo "<p><b>This file is for viewing only. Changes cannot be saved through this interface.</b></p>";
        }
    }
}

/**
 * Output return form
 */
function outputReturnForm(): void {
    // Setup form data for return button
    $fields = [
        [
            'type' => 'hidden',
            'name' => 'return',
            'value' => '1',
            'label' => '',
            'attrs' => 'tabindex="50"',
            'wrapper_class' => ''
        ]
    ];
    $action = 'configeditor.php';
    $method = 'POST';
    $submit_label = 'Return to File List';
    $form_class = 'edit-form';
    $submit_class = 'submit-large';
    include 'includes/form.inc';
}

/**
 * Output edit page closing tags
 */
function outputEditFooter(): void {
    ?>
</div>
</body>
</html>
    <?php
}
