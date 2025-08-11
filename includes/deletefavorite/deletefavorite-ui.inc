<?php
/**
 * Supermon-ng Delete Favorite UI
 * 
 * Handles the user interface components for the delete favorite functionality.
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
function outputDeleteFavoriteHead(string $title): void {
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
            max-width: 800px;
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
        .favorites-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .favorites-table th,
        .favorites-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #374151;
        }
        .favorites-table th {
            background-color: #2A2A2A;
            font-weight: bold;
            color: #4A90E2;
        }
        .favorites-table tr:hover {
            background-color: #2A2A2A;
        }
        .section-badge {
            background-color: #4A90E2;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .section-badge.general {
            background-color: #10B981;
        }
        .section-badge.node {
            background-color: #F59E0B;
        }
        .delete-btn {
            background-color: #EF4444;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .delete-btn:hover {
            background-color: #DC2626;
        }
        .button-group {
            text-align: center;
            margin-top: 20px;
        }
        input[type="button"] {
            background-color: #4A90E2;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            margin: 0 10px;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        input[type="button"]:hover {
            background-color: #5BA0F2;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
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
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #9CA3AF;
        }
        .empty-state h3 {
            color: #6B7280;
            margin-bottom: 10px;
        }
        .command-preview {
            font-family: monospace;
            background-color: #2A2A2A;
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 12px;
            color: #D1D5DB;
        }
    </style>
    <?php
}

/**
 * Output the JavaScript section
 * 
 * @return void
 */
function outputDeleteFavoriteScript(): void {
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
        
        function confirmDelete(section, index, label) {
            if (confirm('Are you sure you want to delete the favorite "' + label + '"?')) {
                document.getElementById('section').value = section;
                document.getElementById('index').value = index;
                document.getElementById('deleteForm').submit();
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
function outputDeleteFavoriteHeader($title) {
    $user = isset($_SESSION['user']) ? $_SESSION['user'] : 'admin';
    $favoritesFile = getUserFavoritesFile($user);
    $fileName = basename($favoritesFile);
    ?>
    <div class="container">
        <div class="header">
            <h2><?php echo htmlspecialchars($title); ?></h2>
        </div>
        
        <div class="node-info" style="margin-bottom: 20px;">
            <h3>Favorites File</h3>
            <p><strong>User:</strong> <?php echo htmlspecialchars($user); ?></p>
            <p><strong>File:</strong> <?php echo htmlspecialchars($fileName); ?></p>
            <p><strong>Path:</strong> <?php echo htmlspecialchars($favoritesFile); ?></p>
        </div>
    <?php
}

/**
 * Output the delete favorite form
 * 
 * @param array $favorites Array of user favorites
 * @param string $currentNode Current node number
 * @return void
 */
function outputDeleteFavoriteForm(array $favorites, string $currentNode): void {
    ?>
        <form id="deleteForm" method="POST" action="">
            <input type="hidden" id="section" name="section" value="">
            <input type="hidden" id="index" name="index" value="">
            
            <table class="favorites-table">
                <thead>
                    <tr>
                        <th>Section</th>
                        <th>Label</th>
                        <th>Command</th>
                        <th>Node</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($favorites as $favorite): ?>
                    <tr>
                        <td>
                            <span class="section-badge <?php echo $favorite['section'] === 'general' ? 'general' : 'node'; ?>">
                                <?php echo htmlspecialchars($favorite['section']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($favorite['label']); ?></td>
                        <td>
                            <div class="command-preview">
                                <?php echo htmlspecialchars($favorite['command']); ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($favorite['node']): ?>
                                <span class="section-badge node"><?php echo htmlspecialchars($favorite['node']); ?></span>
                            <?php else: ?>
                                <span style="color: #6B7280;">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="delete-btn" 
                                    onclick="confirmDelete('<?php echo htmlspecialchars($favorite['section']); ?>', 
                                                          <?php echo $favorite['index']; ?>, 
                                                          '<?php echo htmlspecialchars(addslashes($favorite['label'])); ?>')">
                                Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>

        <div class="button-group">
            <input type="button" value="Refresh Parent Window" onclick="refreshParent(); closeWindow();" style="background-color: #10B981;">
            <input type="button" value="Close" onclick="closeWindow()" style="background-color: #6B7280;">
        </div>
    <?php
}

/**
 * Output the result of the delete favorite operation
 * 
 * @param array $result Result array from form processing
 * @return void
 */
function outputDeleteFavoriteResult(array $result): void {
    $cssClass = $result['success'] ? 'success' : 'error';
    ?>
        <div class="<?php echo $cssClass; ?>">
            <?php echo htmlspecialchars($result['message']); ?>
        </div>
    <?php
}

/**
 * Output empty state when no favorites exist
 * 
 * @return void
 */
function outputDeleteFavoriteEmpty(): void {
    ?>
        <div class="empty-state">
            <h3>No Favorites Found</h3>
            <p>You don't have any favorites configured yet.</p>
            <p>Use the "Add Favorite" button to add nodes to your favorites list.</p>
        </div>

        <div class="button-group">
            <input type="button" value="Close" onclick="closeWindow()" style="background-color: #6B7280;">
        </div>
    <?php
}

/**
 * Output the page footer
 * 
 * @return void
 */
function outputDeleteFavoriteFooter(): void {
    ?>
    </div>
    <?php
}
?>
