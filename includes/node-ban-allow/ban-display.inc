<?php
/**
 * Node Ban/Allow Data Display
 * 
 * Handles display of current allowlist and denylist data
 * from AMI database queries.
 */

/**
 * Display the deny list table
 * 
 * @param resource $fp AMI connection
 * @param string $localnode The local node number
 */
function displayDenyList($fp, $localnode) {
    ?>
<table class="ban-allow-table">
<tr>
<td class="ban-allow-cell-left">Current Nodes in the Denied - denylist (for node <?php echo htmlspecialchars($localnode); ?>):
<?php
    // For denylist table
    $denylistDBFamily = "denylist/" . $localnode;
    $rawDataDeny = getDataFromAMI($fp, "database show " . $denylistDBFamily);
    if ($rawDataDeny === false || trim($rawDataDeny) === "") {
        print "<p>---NONE---</p>";
    } else {
        $lines = explode("\n", $rawDataDeny);
        $headers = ['Node', 'Comment'];
        $rows = [];
        foreach ($lines as $line) {
            $processedLine = trim($line);
            if (strpos($processedLine, "Output: ") === 0) {
                $processedLine = substr($processedLine, strlen("Output: "));
                $processedLine = trim($processedLine);
            }
            if (preg_match('/^\d+\s+results found\.?$/i', $processedLine)) {
                continue;
            }
            $parts = explode(' ', $processedLine, 2);
            $rows[] = [
                htmlspecialchars($parts[0] ?? ''),
                htmlspecialchars($parts[1] ?? '')
            ];
        }
        $table_class = 'ban-allow-table';
        include 'includes/table.inc';
    } 
?>
</td></tr>
    <?php
}

/**
 * Display the allow list
 * 
 * @param resource $fp AMI connection
 * @param string $localnode The local node number
 */
function displayAllowList($fp, $localnode) {
    ?>
<tr>
<td class="ban-allow-cell-left">Current Nodes in the Allowed - allowlist (for node <?php echo htmlspecialchars($localnode); ?>):
<?php
    $allowlistDBFamily = "allowlist/" . $localnode;
    $rawDataAllow = getDataFromAMI($fp, "database show " . $allowlistDBFamily);

    if ($rawDataAllow === false || trim($rawDataAllow) === "") {
        print "<p>---NONE---</p>";
    } else {
        $lines = explode("\n", $rawDataAllow);
        $outputLines = [];
        foreach ($lines as $line) {
            $processedLine = trim($line);
            if (strpos($processedLine, "Output: ") === 0) {
                $processedLine = substr($processedLine, strlen("Output: "));
                $processedLine = trim($processedLine); 
            }
            
            if (preg_match('/^\d+\s+results found\.?$/i', $processedLine)) {
                continue; 
            }

            if (trim($processedLine) !== "") {
                $processedLine = str_replace('          ', ' ', $processedLine);
                $outputLines[] = $processedLine;
            }
        }

        if (empty($outputLines)) {
            print "<p>---NONE---</p>";
        } else {
            $finalOutput = implode("\n", $outputLines);
            print "<pre>" . htmlspecialchars(trim($finalOutput)) . "</pre>";
        }
    } 
?>
</td></tr>
</table>
    <?php
}
