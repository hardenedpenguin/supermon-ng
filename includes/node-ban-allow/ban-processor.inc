<?php
/**
 * Node Ban/Allow Form Processor
 * 
 * Handles POST form processing for adding/removing nodes
 * from allow/deny lists via AMI database operations.
 */

/**
 * Process ban/allow form submission
 * 
 * @param resource $fp AMI connection
 * @param string $localnode The local node number
 */
function processBanAllowForm($fp, $localnode) {
    if (!empty($_POST["listtype"]) && !empty($_POST["node"]) && !empty($_POST["deleteadd"])) {
        $listtype_base = trim(strip_tags($_POST["listtype"]));
        $nodeToModify = trim(strip_tags($_POST["node"]));
        $comment = trim(strip_tags($_POST["comment"] ?? ''));
        $deleteadd = trim(strip_tags($_POST["deleteadd"]));

        // Validate inputs
        if (!in_array($listtype_base, ['allowlist', 'denylist'])) {
            die("<h3 class='error-message'>ERROR: Invalid list type.</h3>");
        }
        
        if (!preg_match('/^\d+$/', $nodeToModify)) {
            die("<h3 class='error-message'>ERROR: Invalid node number.</h3>");
        }
        
        if (!in_array($deleteadd, ['add', 'delete'])) {
            die("<h3 class='error-message'>ERROR: Invalid action.</h3>");
        }

        $DBname = $listtype_base . "/" . $localnode; 
        $cmdAction = ($deleteadd == "add") ? "put" : "del";

        $amiCmdString = "database $cmdAction $DBname $nodeToModify";
        if ($cmdAction == "put" && !empty($comment)) {
            $amiCmdString .= " \"" . addslashes($comment) . "\"";
        }
        
        $ret = sendCmdToAMI($fp, $amiCmdString);
        
        // Show result message
        if ($ret !== false) {
            echo "<div style='background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; border-radius: 4px;'>Command executed successfully.</div>";
        } else {
            echo "<div style='background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; margin: 10px 0; border-radius: 4px;'>Failed to execute command. Check Asterisk logs for details.</div>";
        }
    }
}
