<?php
// includes/form_field.inc
// Usage: set $field (array) before including this file
$type = $field['type'] ?? 'text';
$name = $field['name'] ?? '';
$value = $field['value'] ?? '';
$label = $field['label'] ?? '';
$attrs = $field['attrs'] ?? '';
$wrapper_class = $field['wrapper_class'] ?? 'form-group';
$label_class = $field['label_class'] ?? '';
?>
<?php if ($type === 'hidden'): ?>
  <input type="hidden" name="<?= htmlspecialchars($name) ?>" value="<?= htmlspecialchars($value) ?>" <?= $attrs ?>>
<?php else: ?>
  <div class="<?= htmlspecialchars($wrapper_class) ?>">
    <?php if (!empty($label)): ?>
      <label<?= !empty($label_class) ? ' class="' . htmlspecialchars($label_class) . '"' : '' ?><?= !empty($field['label_for']) ? ' for="' . htmlspecialchars($field['label_for']) . '"' : '' ?>>
        <?= htmlspecialchars($label) ?>
      </label>
    <?php endif; ?>
    <?php if ($type === 'textarea'): ?>
      <textarea name="<?= htmlspecialchars($name) ?>" <?= $attrs ?>><?= htmlspecialchars($value) ?></textarea>
    <?php elseif ($type === 'select'): ?>
      <select name="<?= htmlspecialchars($name) ?>" <?= $attrs ?>>
        <?php if (isset($field['options']) && is_array($field['options'])): ?>
          <?php foreach ($field['options'] as $option_value => $option_label): ?>
            <option value="<?= htmlspecialchars($option_value) ?>"<?= ($option_value == $value) ? ' selected' : '' ?>><?= htmlspecialchars($option_label) ?></option>
          <?php endforeach; ?>
        <?php endif; ?>
      </select>
    <?php else: ?>
      <input type="<?= htmlspecialchars($type) ?>" name="<?= htmlspecialchars($name) ?>" value="<?= htmlspecialchars($value) ?>" <?= $attrs ?>>
    <?php endif; ?>
  </div>
<?php endif; ?>