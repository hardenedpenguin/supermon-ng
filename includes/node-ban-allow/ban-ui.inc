<?php
/**
 * Node Ban/Allow UI Components
 * 
 * Handles HTML template rendering and form display
 * for node access control interface.
 */

/**
 * Render the HTML head section
 * 
 * @param string $localnode The local node number for title
 */
function renderBanAllowHead($localnode) {
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
<title>Allow/Deny Nodes - <?php echo htmlspecialchars($localnode); ?></title>
</head>
    <?php
}

/**
 * Render the page body start and title
 * 
 * @param string $localnode The local node number
 */
function renderBanAllowBodyStart($localnode) {
    ?>
<body class="ban-allow-page">

<p class="ban-allow-title"><b>Allow/Deny AllStar Nodes at node <?php echo htmlspecialchars($localnode); ?></b></p>
    <?php
}

/**
 * Render the ban/allow form
 * 
 * @param string $localnode The local node number
 * @param string $Node The node value to pre-fill (may be empty)
 */
function renderBanAllowForm($localnode, $Node) {
    ?>
<center>
<form action="node-ban-allow.php?localnode=<?php echo htmlspecialchars($localnode); ?>" method="post">
    <?php if (function_exists('csrf_token_field')) echo csrf_token_field(); ?>
    <table class="ban-allow-table">
        <tr>
            <td class="ban-allow-cell-left">
                <b>Node to Add/Delete:</b><br>
                <input type="text" name="node" value="<?php echo htmlspecialchars($Node); ?>" maxlength="7" size="5" pattern="\d+" required>
            </td>
            <td class="ban-allow-cell-right">
                <b>Comment:</b><br>
                <input type="text" name="comment" maxlength="50" size="20">
            </td>
        </tr>
        <tr>
            <td class="ban-allow-cell-left">
                <b>List Type:</b><br>
                <input type="radio" name="listtype" value="allowlist" checked> Allow List<br>
                <input type="radio" name="listtype" value="denylist"> Deny List
            </td>
            <td class="ban-allow-cell-right">
                <b>Action:</b><br>
                <input type="radio" name="deleteadd" value="add" checked> Add<br>
                <input type="radio" name="deleteadd" value="delete"> Delete
            </td>
        </tr>
        <tr>
            <td colspan="2" class="ban-allow-cell-center">
                <input type="submit" value="Execute" class="ban-allow-button">
            </td>
        </tr>
    </table>
</form>
</center>
    <?php
}

/**
 * Render the page footer
 */
function renderBanAllowFooter() {
    ?>

</body>
</html>
    <?php
}
