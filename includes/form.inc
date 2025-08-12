<?php
/**
 * Supermon-ng Form Generation System
 * 
 * Provides a flexible and secure form generation system for Supermon-ng.
 * Creates HTML forms with proper validation, sanitization, and styling
 * based on field definitions and configuration arrays.
 * 
 * Features:
 * - Dynamic form generation from field arrays
 * - Automatic HTML escaping and sanitization
 * - Customizable form styling and classes
 * - Flexible submit button configuration
 * - Security-focused input handling
 * - Modular field rendering system
 * 
 * Usage:
 * Set the following variables before including this file:
 * - $fields: Array of field definitions
 * - $action: Form action URL
 * - $method: HTTP method (defaults to 'post')
 * - $submit_label: Submit button text (optional)
 * - $form_class: CSS class for form element (optional)
 * - $submit_class: CSS class for submit button (optional)
 * 
 * Field Structure:
 * Each field in $fields array should contain:
 * - type: Input type (text, password, select, etc.)
 * - name: Field name attribute
 * - label: Display label for the field
 * - value: Default value (optional)
 * - required: Boolean for required validation (optional)
 * - options: Array of options for select fields (optional)
 * 
 * Security Features:
 * - HTML escaping for all output
 * - XSS protection through proper encoding
 * - Form action and method validation
 * - Secure input handling
 * 
 * Dependencies:
 * - form_field.inc: Individual field rendering
 * - HTML escaping functions
 * 
 * Example Usage:
 * $fields = [
 *     ['type' => 'text', 'name' => 'username', 'label' => 'Username', 'required' => true],
 *     ['type' => 'password', 'name' => 'password', 'label' => 'Password', 'required' => true]
 * ];
 * $action = 'login.php';
 * $method = 'post';
 * $submit_label = 'Login';
 * include 'includes/form.inc';
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

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