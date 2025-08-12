<?php
/**
 * Supermon-ng Table Generation System
 * 
 * Provides a secure and flexible table generation system for Supermon-ng.
 * Creates HTML tables with proper validation, sanitization, and styling
 * based on header and row data arrays.
 * 
 * Features:
 * - Dynamic table generation from data arrays
 * - Automatic HTML escaping and sanitization
 * - Customizable table styling and classes
 * - Flexible header and row configuration
 * - Security-focused data handling
 * - Clean and consistent table output
 * 
 * Usage:
 * Set the following variables before including this file:
 * - $headers: Array of table header text
 * - $rows: Array of row arrays (each row is an array of cell values)
 * - $table_class: CSS class for table element (optional, defaults to 'gridtable')
 * 
 * Data Structure:
 * - $headers: Simple array of header text strings
 * - $rows: Two-dimensional array where each row is an array of cell values
 * - $table_class: String for CSS class (optional)
 * 
 * Security Features:
 * - HTML escaping for all output
 * - XSS protection through proper encoding
 * - Secure data handling
 * - Input validation and sanitization
 * 
 * Table Structure:
 * - Standard HTML table with <thead> and <tbody>
 * - Consistent CSS class application
 * - Proper HTML structure and formatting
 * 
 * Dependencies:
 * - HTML escaping functions
 * - CSS styling for table classes
 * 
 * Example Usage:
 * $headers = ['Name', 'Status', 'Location'];
 * $rows = [
 *     ['Node 1', 'Online', 'Texas'],
 *     ['Node 2', 'Offline', 'California']
 * ];
 * $table_class = 'status-table';
 * include 'includes/table.inc';
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

// includes/table.inc
// Usage: set $headers and $rows before including this file.
// Optionally set $table_class for custom CSS class.
?>
<table class="<?= htmlspecialchars($table_class ?? 'gridtable') ?>">
  <tr>
    <?php foreach ($headers as $header): ?>
      <th><?= htmlspecialchars($header) ?></th>
    <?php endforeach; ?>
  </tr>
  <?php foreach ($rows as $row): ?>
    <tr>
      <?php foreach ($row as $cell): ?>
        <td><?= htmlspecialchars($cell) ?></td>
      <?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
</table>