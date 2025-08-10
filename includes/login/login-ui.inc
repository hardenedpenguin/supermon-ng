<?php
/**
 * Supermon-ng Login UI
 * 
 * Provides the user interface components for the login page including
 * CSS styling, form structure, and responsive design elements.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Output login page CSS styles
 */
function outputLoginCSS(): void {
    ?>
<style>
.login-page {
    max-width: 400px;
    margin: 50px auto;
    background: var(--container-bg);
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
    border: 1px solid var(--border-color);
}

.login-title {
    text-align: center;
    margin-bottom: 30px;
    color: var(--text-color);
    font-size: 1.5em;
    font-weight: bold;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    color: var(--text-color);
    font-weight: bold;
}

.form-group input[type="text"],
.form-group input[type="password"] {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    box-sizing: border-box;
    background: var(--input-bg);
    color: var(--input-text);
    font-size: 16px;
}

.form-group input[type="text"]:focus,
.form-group input[type="password"]:focus {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

.login-btn {
    width: 100%;
    padding: 12px;
    background: var(--primary-color);
    color: var(--text-color);
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
    transition: background-color 0.3s ease;
}

.login-btn:hover {
    background: var(--link-hover);
}

.login-error {
    color: var(--error-color);
    background: rgba(211, 47, 47, 0.1);
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 20px;
    border: 1px solid var(--error-color);
}

.login-attempts {
    color: var(--text-color);
    font-size: 14px;
    text-align: center;
    margin-top: 10px;
    opacity: 0.8;
}

@media (max-width: 768px) {
    .login-page {
        margin: 20px auto;
        padding: 20px;
    }
    
    .login-title {
        font-size: 1.3em;
    }
}
</style>
    <?php
}

/**
 * Output login form HTML
 * 
 * @param string|null $error_message Error message to display
 */
function outputLoginForm(?string $error_message = null): void {
    ?>
<div class="login-page">
    <h2 class="login-title">Supermon-ng Login</h2>
    
    <?php if ($error_message): ?>
        <div class="login-error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    
    <?php
    // Setup form data for reusable form include
    $fields = [
        [
            'type' => 'text',
            'name' => 'user',
            'value' => '',
            'label' => 'Username:',
            'attrs' => 'id="user" required',
            'wrapper_class' => 'form-group',
            'label_for' => 'user'
        ],
        [
            'type' => 'password',
            'name' => 'passwd',
            'value' => '',
            'label' => 'Password:',
            'attrs' => 'id="passwd" required',
            'wrapper_class' => 'form-group',
            'label_for' => 'passwd'
        ]
    ];
    $action = '';
    $method = 'post';
    $submit_label = 'Login';
    $form_class = 'login-form';
    $submit_class = 'login-btn';
    ?>
    <?php include 'includes/form.inc'; ?>
    
    <div class="login-attempts">
        <?php 
        $remaining = get_remaining_attempts('login', 5, 900);
        echo "Remaining login attempts: {$remaining}";
        ?>
    </div>
</div>
    <?php
}
