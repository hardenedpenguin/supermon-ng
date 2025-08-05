<?php
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