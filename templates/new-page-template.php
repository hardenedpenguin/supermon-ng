<?php
/**
 * New Page Template for Supermon-ng
 * 
 * Use this template as a starting point when creating new pages.
 * Copy this file, rename it, and modify as needed for your functionality.
 * 
 * Instructions:
 * 1. Copy this file to the root directory with a descriptive name (e.g., my-feature.php)
 * 2. Update the page title and metadata
 * 3. Modify the permission check if needed
 * 4. Add your page content between the container div tags
 * 5. Remove these instruction comments
 * 
 * @author Your Name Here
 * @version 2.0.3
 */

// Include required files (always include these)
include_once "includes/session.inc";
include_once "includes/common.inc";
include_once "includes/helpers.inc";
include_once "includes/error-handler.inc";
include_once "includes/config.inc";
include_once "authusers.php";

// Authentication check (required for all pages)
SecurityHelper::requireLogin("You must login to access this page.");

// Permission check (modify as needed for your page)
// Available permissions: ADMIN, CFGEDUSER, DTMFUSER, ASTLKUSER, BANUSER, GPIOUSER
SecurityHelper::requirePermission("ADMIN", "You do not have permission to access this page.");

// CSRF protection (add if your page has forms)
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     require_csrf();
// }

// Page configuration
$pageTitle = "My New Feature";  // Change this to your page title
$pageDescription = "Description of what this page does";  // Brief description

// Process form submission or other POST/GET data
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Example form processing
    // Remove this example and add your own logic
    
    $exampleInput = ValidationHelper::sanitizeInput($_POST['example_input'] ?? '', 'string');
    
    if (empty($exampleInput)) {
        $error = ErrorHandler::displayUserError("Example input is required.");
    } else {
        // Process the input here
        $message = ErrorHandler::displayUserError("Form processed successfully!", 'success');
        
        // Log the action
        ErrorHandler::logUserAction("Used new feature", ['input' => $exampleInput]);
    }
}

// Get any data needed for the page
// Example: Load configuration or data
try {
    // Example data loading
    $exampleData = [
        'item1' => 'Value 1',
        'item2' => 'Value 2',
        'item3' => 'Value 3'
    ];
    
} catch (Exception $e) {
    $error = ErrorHandler::handleDatabaseError($e->getMessage());
    $exampleData = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Supermon-ng</title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    
    <!-- Standard CSS includes (load in this order) -->
    <link type="text/css" rel="stylesheet" href="css/base.css">
    <link type="text/css" rel="stylesheet" href="css/layout.css">
    <link type="text/css" rel="stylesheet" href="css/menu.css">
    <link type="text/css" rel="stylesheet" href="css/tables.css">
    <link type="text/css" rel="stylesheet" href="css/forms.css">
    <link type="text/css" rel="stylesheet" href="css/widgets.css">
    <link type="text/css" rel="stylesheet" href="css/responsive.css">
    <!-- Custom CSS (always load last) -->
    <?php if (file_exists('css/custom.css')): ?>
    <link type="text/css" rel="stylesheet" href="css/custom.css">
    <?php endif; ?>
    
    <!-- Optional: Include JavaScript if needed -->
    <script src="js/jquery.min.js"></script>
    <script src="js/utils.js"></script>
    
    <!-- Page-specific styles (if needed) -->
    <style>
        .my-feature-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .example-card {
            background-color: var(--container-bg);
            border: 1px solid var(--border-color);
            border-radius: 5px;
            padding: 15px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <!-- Include standard header -->
    <?php include 'includes/header.inc'; ?>
    
    <!-- Page content starts here -->
    <div class="container my-feature-container">
        <!-- Page title -->
        <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
        
        <!-- Display messages -->
        <?php if (!empty($message)): ?>
            <?= $message ?>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <?= $error ?>
        <?php endif; ?>
        
        <!-- Example content section -->
        <div class="example-card">
            <h2>Feature Description</h2>
            <p>This is where you describe what your feature does and how to use it.</p>
            
            <!-- Example form (remove if not needed) -->
            <form method="post" action="">
                <?php if (function_exists('csrf_token_field')) echo csrf_token_field(); ?>
                
                <div class="form-group">
                    <label for="example_input">Example Input:</label>
                    <input type="text" id="example_input" name="example_input" 
                           value="<?= htmlspecialchars($_POST['example_input'] ?? '') ?>" 
                           placeholder="Enter something..." required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="submit">Submit Example</button>
                </div>
            </form>
        </div>
        
        <!-- Example data display -->
        <?php if (!empty($exampleData)): ?>
        <div class="example-card">
            <h2>Example Data</h2>
            
            <!-- Using the TableRenderer component -->
            <?php
            // Example of using the new table renderer
            $headers = ['Item', 'Value'];
            $rows = [];
            
            foreach ($exampleData as $key => $value) {
                $rows[] = [$key, $value];
            }
            
            // Simple table
            echo TableRenderer::renderSimple($headers, $rows, ['class' => 'gridtable']);
            ?>
        </div>
        <?php endif; ?>
        
        <!-- Example of using plugins -->
        <?php
        // Execute custom plugin if available
        $customContent = execute_plugin('my_custom_plugin', [
            'page' => 'new-feature',
            'data' => $exampleData
        ]);
        
        if ($customContent): ?>
        <div class="example-card">
            <h2>Custom Plugin Content</h2>
            <?= $customContent ?>
        </div>
        <?php endif; ?>
        
        <!-- Navigation/action buttons -->
        <div class="action-buttons" style="margin-top: 20px; text-align: center;">
            <a href="index.php" class="submit">Back to Dashboard</a>
            
            <!-- Example of permission-based button display -->
            <?php if (SecurityHelper::hasPermission('ADMIN')): ?>
                <a href="admin-settings.php" class="submit">Admin Settings</a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Include standard footer -->
    <?php include 'includes/footer.inc'; ?>
    
    <!-- Page-specific JavaScript (if needed) -->
    <script>
        // Example JavaScript for your page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('New feature page loaded');
            
            // Example: Form validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const input = document.getElementById('example_input');
                    if (input.value.trim() === '') {
                        e.preventDefault();
                        alert('Please enter a value');
                        input.focus();
                    }
                });
            }
            
            // Example: AJAX functionality
            // You can add AJAX calls here using jQuery or fetch API
        });
    </script>
</body>
</html>
