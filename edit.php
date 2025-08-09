<?php

include("includes/session.inc");
include("authusers.php");
include("user_files/global.inc");
include("includes/common.inc");

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
<div class="container">

    <h2>Editing: <?php echo htmlspecialchars($file); ?></h2>

    <?php if (strpos($data, 'ERROR:') === 0): ?>
        <p class="edit-error"><?php echo htmlspecialchars($data); ?></p>
    <?php else: ?>
        <?php
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
        $submit_label = $is_view_only ? false : ' WRITE Edits to File ';
        $form_class = 'edit-form';
        $submit_class = 'submit-large';
        ?>
        <?php include 'includes/form.inc'; ?>
        <?php if ($is_view_only): ?>
            <p><b>This file is for viewing only. Changes cannot be saved through this interface.</b></p>
        <?php endif; ?>
    <?php endif; ?>

    <?php
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
    ?>
    <?php include 'includes/form.inc'; ?>

</div>
</body>
</html>