<?php
/**
 * AllStar Lookup UI Rendering
 * 
 * Handles HTML output, form rendering, and results display
 */

/**
 * Render the HTML head section with CSS includes
 */
function renderLookupHead($localnode) {
?>
<html>
<head>
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
<title>AllStar Lookup - <?php echo htmlspecialchars($localnode); ?></title>
</head>
<body class="lookup-page">
<?php
}

/**
 * Render the main lookup form
 */
function renderLookupForm($lookupNode, $localnode, $perm) {
?>
<p class="lookup-title"><b>AllStar Node Lookup at node <?php echo htmlspecialchars($localnode); ?></b></p>

<center>
<form action="astlookup.php?node=<?php echo htmlspecialchars($lookupNode); ?>&localnode=<?php echo htmlspecialchars($localnode); ?>&perm=<?php echo htmlspecialchars($perm); ?>" method="post">
    <?php if (function_exists('csrf_token_field')) echo csrf_token_field(); ?>
    <table class="lookup-table">
        <tr>
            <td class="lookup-cell">
                <b>Node/Callsign to Lookup:</b><br>
                <input type="text" name="lookup_node" value="<?php echo htmlspecialchars($lookupNode); ?>" maxlength="20" size="15" required>
            </td>
        </tr>
        <tr>
            <td class="lookup-cell-center">
                <input type="submit" value="Lookup" class="lookup-button">
            </td>
        </tr>
    </table>
</form>
</center>
<?php
}

/**
 * Process and display lookup results based on POST data
 */
function processLookupResults($fp, $localnode, $perm) {
    if (!empty($_POST["lookup_node"])) {
        $nodeToLookup = trim(strip_tags($_POST["lookup_node"]));
        $nodeToLookup = strtoupper($nodeToLookup);
        $intnode = (int)$nodeToLookup;
        
        if (!empty($nodeToLookup)) {
            echo "<div class='lookup-results'>";
            echo "<h3>Lookup Results for: " . htmlspecialchars($nodeToLookup) . "</h3>";
            
            echo "<div class='lookup-command'>";
            echo "<table class='lookup-results-table'>";
            
            if ("$intnode" != "$nodeToLookup") {
                // Do lookup by callsign
                do_allstar_callsign_search($fp, $nodeToLookup, $localnode);
                
                if ($perm != "on") {
                    do_echolink_callsign_search($fp, $nodeToLookup);
                    do_irlp_callsign_search($nodeToLookup);
                }
            } else if ($intnode > 80000 && $intnode < 90000) {
                // Lookup by IRLP node number
                do_irlp_number_search($intnode);
            } else if ($intnode > 3000000) {
                // Lookup by echolink node number
                do_echolink_number_search($fp, $intnode);
            } else {
                // Lookup by AllStar node number
                do_allstar_number_search($fp, $intnode, $localnode);
            }
            
            echo "</table>";
            echo "</div>";
            
            echo "</div>";
        }
    }
}

/**
 * Render the HTML footer
 */
function renderLookupFooter() {
?>
</body>
</html>
<?php
}
