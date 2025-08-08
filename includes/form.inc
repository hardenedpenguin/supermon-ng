<?php
// includes/form.inc
// Usage: set $fields (array of field arrays), $action, $method, $submit_label before including
// Optional: $form_class, $submit_class for custom styling
?>
<form action="<?= htmlspecialchars($action) ?>" method="<?= htmlspecialchars($method ?? 'post') ?>"<?= !empty($form_class) ? ' class="' . htmlspecialchars($form_class) . '"' : '' ?>>
  <?php foreach ($fields as $field) { $GLOBALS['field'] = $field; include 'includes/form_field.inc'; } ?>
  <?php if (isset($submit_label) && $submit_label !== false): ?>
    <?php if (!empty($submit_class)): ?>
      <button type="submit" class="<?= htmlspecialchars($submit_class) ?>"><?= htmlspecialchars($submit_label ?? 'Submit') ?></button>
    <?php else: ?>
      <input type="submit" value="<?= htmlspecialchars($submit_label ?? 'Submit') ?>">
    <?php endif; ?>
  <?php endif; ?>
</form>