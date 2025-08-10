<?php
/**
 * Supermon-ng Database UI
 * 
 * Provides the user interface components for the database display including
 * HTML structure, CSS styling, and content rendering.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Output database page HTML head section
 * 
 * @param string $localnode Local node identifier
 */
function outputDatabaseHead(string $localnode): void {
    ?>
<html>
<head>
<title>AllStar Node Database Contents - <?php echo htmlspecialchars($localnode); ?></title>
<!-- Modular CSS Files -->
<link type="text/css" rel="stylesheet" href="css/base.css">
<link type="text/css" rel="stylesheet" href="css/layout.css">
<link type="text/css" rel="stylesheet" href="css/menu.css">
<link type="text/css" rel="stylesheet" href="css/tables.css">
<link type="text/css" rel="stylesheet" href="css/forms.css">
<link type="text/css" rel="stylesheet" href="css/widgets.css">
<link type="text/css" rel="stylesheet" href="css/responsive.css">
<!-- Custom CSS (load last to override defaults) -->
<?php if (file_exists('css/custom.css')): ?>
<link type="text/css" rel="stylesheet" href="css/custom.css">
<?php endif; ?>
<style>
.db-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    font-family: monospace;
    font-size: 0.9em;
}
.db-table th, .db-table td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
    vertical-align: top;
}
.db-table th {
    background-color: #f2f2f2;
    font-weight: bold;
    color: #333;
}
.db-table tbody tr {
    background-color: #39FF14;
    color: #000000;
}
.db-table tbody tr:hover {
    background-color: #00C000;
    color: #FFFFFF;
}
.db-table td.db-key {
}
.db-table td.db-value {
    word-break: break-word;
}
</style>
</head>
<body>
    <?php
}

/**
 * Output database header information
 * 
 * @param string $localnode Local node identifier
 */
function outputDatabaseHeader(string $localnode): void {
    $today = date("D M j G:i:s T Y");
    ?>
<div class="container">
    <div class="db-header">
        <b><u><?php echo htmlspecialchars($today); ?> - Database from node - <?php echo htmlspecialchars($localnode); ?></u></b>
    </div>
    <?php
}

/**
 * Output database content
 * 
 * @param string|false $databaseOutput Raw database output from AMI
 * @param array $dbEntries Processed database entries
 * @param string $processedOutput Cleaned output string
 */
function outputDatabaseContent($databaseOutput, array $dbEntries, string $processedOutput): void {
    $statusMessage = getDatabaseStatusMessage($databaseOutput, $dbEntries, $processedOutput);
    
    if (!empty($statusMessage)) {
        if (strpos($statusMessage, 'ERROR:') === 0) {
            echo "<p class='status-message error-message'>" . htmlspecialchars($statusMessage) . "</p>";
        } else {
            echo "<p class='status-message'>" . htmlspecialchars($statusMessage) . "</p>";
        }
    } else {
        // Display database table
        $tableData = prepareDatabaseTableData($dbEntries);
        $headers = $tableData['headers'];
        $rows = $tableData['rows'];
        $table_class = $tableData['table_class'];
        include 'includes/table.inc';
    }
}

/**
 * Output database page closing tags
 */
function outputDatabaseFooter(): void {
    ?>
</div>
</body>
</html>
    <?php
}
