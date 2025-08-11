<?php
/**
 * Supermon-ng Add Favorite UI
 * 
 * Handles the user interface components for the add favorite functionality.
 * Provides HTML output, form rendering, and result display.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Output the HTML head section
 * 
 * @param string $title Page title
 * @return void
 */
function outputAddFavoriteHead(string $title): void {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" type="text/css" href="css/base.css">
    <link rel="stylesheet" type="text/css" href="css/forms.css">
    <link rel="stylesheet" type="text/css" href="css/tables.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #121212;
            color: #E0E0E0;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #1A1A1A;
            border: 1px solid #374151;
            border-radius: 8px;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #374151;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #374151;
            border-radius: 4px;
            background-color: #2A2A2A;
            color: #E0E0E0;
            box-sizing: border-box;
        }
        input[type="checkbox"] {
            margin-right: 8px;
        }
        .button-group {
            text-align: center;
            margin-top: 20px;
        }
        input[type="submit"], input[type="button"] {
            background-color: #4A90E2;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin: 0 5px;
        }
        input[type="submit"]:hover, input[type="button"]:hover {
            background-color: #5BA0F2;
        }
        .node-info {
            background-color: #2A2A2A;
            border: 1px solid #374151;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .node-info h3 {
            margin-top: 0;
            color: #4A90E2;
        }
        .node-info p {
            margin: 5px 0;
        }
        .success {
            background-color: rgba(16, 185, 129, 0.1);
            border: 1px solid #10B981;
            color: #10B981;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .error {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid #EF4444;
            color: #EF4444;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .warning {
            background-color: rgba(245, 124, 0, 0.1);
            border: 1px solid #F59E0B;
            color: #F59E0B;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
    <?php
}

/**
 * Output the JavaScript section
 * 
 * @return void
 */
function outputAddFavoriteScript(): void {
    ?>
    <script>
        function closeWindow() {
            window.close();
        }
        
        function refreshParent() {
            if (window.opener && !window.opener.closed) {
                window.opener.location.reload();
            }
        }
    </script>
    <?php
}

/**
 * Output the page header
 * 
 * @param string $title Page title
 * @return void
 */
function outputAddFavoriteHeader(string $title): void {
    ?>
    <div class="container">
        <div class="header">
            <h2><?php echo htmlspecialchars($title); ?></h2>
        </div>
    <?php
}

/**
 * Output the add favorite form
 * 
 * @param string $node Node number
 * @param array $nodeInfo Node information from astdb.txt
 * @param bool $alreadyExists Whether node already exists in favorites
 * @return void
 */
function outputAddFavoriteForm(string $node, array $nodeInfo, bool $alreadyExists): void {
    ?>
        <div class="node-info">
            <h3>Node Information</h3>
            <p><strong>Node:</strong> <?php echo htmlspecialchars($node); ?></p>
            <p><strong>Callsign:</strong> <?php echo htmlspecialchars($nodeInfo['callsign']); ?></p>
            <p><strong>Description:</strong> <?php echo htmlspecialchars($nodeInfo['description']); ?></p>
            <p><strong>Location:</strong> <?php echo htmlspecialchars($nodeInfo['location']); ?></p>
        </div>

        <?php if ($alreadyExists): ?>
        <div class="warning">
            <strong>Warning:</strong> This node already exists in your favorites.
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="custom_label">Custom Label (optional):</label>
                <input type="text" id="custom_label" name="custom_label" 
                       placeholder="<?php echo htmlspecialchars($nodeInfo['callsign'] . ' ' . $nodeInfo['description'] . ' ' . $node); ?>"
                       value="<?php echo htmlspecialchars($nodeInfo['callsign'] . ' ' . $nodeInfo['description'] . ' ' . $node); ?>">
                <small>Leave blank to use the default label above</small>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="add_to_general" value="1" checked>
                    Add to General Favorites (available for all nodes)
                </label>
                <small>If unchecked, this favorite will only be available for the current node</small>
            </div>

            <div class="button-group">
                <input type="submit" value="Add to Favorites">
                <input type="button" value="Cancel" onclick="closeWindow()">
            </div>
        </form>
    <?php
}

/**
 * Output the result of the add favorite operation
 * 
 * @param array $result Result array from form processing
 * @return void
 */
function outputAddFavoriteResult(array $result): void {
    $cssClass = $result['success'] ? 'success' : 'error';
    ?>
        <div class="<?php echo $cssClass; ?>">
            <?php echo htmlspecialchars($result['message']); ?>
        </div>

        <div class="button-group">
            <?php if ($result['success']): ?>
            <input type="button" value="Refresh Parent Window" onclick="refreshParent(); closeWindow();">
            <?php endif; ?>
            <input type="button" value="Close" onclick="closeWindow()">
        </div>
    <?php
}

/**
 * Output the page footer
 * 
 * @return void
 */
function outputAddFavoriteFooter(): void {
    ?>
    </div>
    <?php
}
?>
