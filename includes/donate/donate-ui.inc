<?php
/**
 * Supermon-ng Donate UI
 * 
 * Provides the CSS styling, HTML rendering, and JavaScript functionality for the donation interface.
 * Handles responsive design, donation forms, and modal functionality.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

/**
 * Generate donate CSS styles
 */
function generateDonateStyles(): void {
    ?>
    <style>
    .donate-option {
        margin: 15px 0;
        padding: 20px;
        background-color: var(--container-bg);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        text-align: center;
    }
    
    .donate-button {
        display: inline-block;
        padding: 12px 24px;
        margin: 10px;
        border-radius: 6px;
        font-weight: bold;
        text-decoration: none;
        transition: background-color 0.3s ease;
        border: none;
        cursor: pointer;
        font-size: 1em;
    }
    
    .paypal-button {
        background-color: #0070ba;
        color: white;
    }
    
    .cashapp-button {
        background-color: #00d632;
        color: white;
    }
    
    .zelle-button {
        background-color: #6b4ce6;
        color: white;
    }
    
    .donate-info {
        margin: 10px 0;
        color: var(--text-color);
        font-size: 0.9em;
    }
    
    .zelle-modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    
    .zelle-modal-content {
        background-color: var(--container-bg);
        margin: 15% auto;
        padding: 20px;
        border-radius: 8px;
        width: 80%;
        max-width: 400px;
        border: 1px solid var(--border-color);
    }
    </style>
    <?php
}

/**
 * Generate HTML head section
 * 
 * @param string $title Page title
 * @param array $metaInfo Meta information array
 */
function generateDonateHead(string $title, array $metaInfo): void {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title><?php echo htmlspecialchars($title); ?></title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <meta name="generator" content="By hand with a text editor">
        <meta name="description" content="<?php echo htmlspecialchars($metaInfo['description']); ?>">
        <meta name="keywords" content="<?php echo htmlspecialchars($metaInfo['keywords']); ?>">
        <meta name="author" content="<?php echo htmlspecialchars($metaInfo['author']); ?>">
        <meta name="mods" content="<?php echo htmlspecialchars($metaInfo['mods']); ?>">
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
        <link type="text/css" rel="stylesheet" href="js/jquery-ui.css">
        <script src="js/jquery.min.js"></script>
        <script src="js/jquery-ui.min.js"></script>
        <script src="js/sweetalert2.min.js"></script>
        <link rel="stylesheet" href="js/sweetalert2.min.css"/>
    <?php
}

/**
 * Generate header section
 */
function generateDonateHeader(): void {
    global $BACKGROUND, $BACKGROUND_COLOR, $BACKGROUND_HEIGHT, $CALL, $TITLE_LOGGED, $TITLE3;
    ?>
    <div id="header" class="header-dynamic" style="background-image: url(<?php echo htmlspecialchars($BACKGROUND ?? '', ENT_QUOTES, 'UTF-8'); ?>); background-color:<?php echo htmlspecialchars($BACKGROUND_COLOR ?? '', ENT_QUOTES, 'UTF-8'); ?>;<?php if (isset($BACKGROUND_HEIGHT)) echo ' height:' . htmlspecialchars($BACKGROUND_HEIGHT, ENT_QUOTES, 'UTF-8') . ';'; ?>">
        <div id="headerTitle-large"><i><?php echo htmlspecialchars(($CALL ?? '') . " - " . ($TITLE_LOGGED ?? '')); ?></i></div>
        <div id="header3Tag-large"><i></i></div>
        <div id="header2Tag-large"><i><?php echo htmlspecialchars($TITLE3 ?? ''); ?></i></div>
        <div id="headerImg"><a href="https://www.allstarlink.org" target="_blank"><img src="allstarlink.jpg" width="70%" class="img-borderless" alt="AllStar Logo"></a></div>
    </div>
    <?php
}

/**
 * Generate donation options
 * 
 * @param array $options Donation options array
 */
function generateDonateOptions(array $options): void {
    foreach ($options as $key => $option) {
        ?>
        <div class="donate-option" style="margin: 10px 0; padding: 15px;">
            <h3 style="color: var(--text-color); margin-bottom: 5px; font-size: 1em;"><?php echo htmlspecialchars($option['title']); ?></h3>
            <p class="donate-info" style="margin: 5px 0;"><?php echo htmlspecialchars($option['description']); ?></p>
            <?php if ($key === 'paypal'): ?>
                <form action="<?php echo htmlspecialchars($option['action']); ?>" method="<?php echo htmlspecialchars($option['method']); ?>" target="<?php echo htmlspecialchars($option['target']); ?>" style="display: inline-block;">
                    <?php foreach ($option['hidden_fields'] as $field => $value): ?>
                        <input type="hidden" name="<?php echo htmlspecialchars($field); ?>" value="<?php echo htmlspecialchars($value); ?>" />
                    <?php endforeach; ?>
                    <button type="submit" name="submit" class="donate-button <?php echo htmlspecialchars($option['button_class']); ?>">
                        <?php echo htmlspecialchars($option['button_text']); ?>
                    </button>
                    <img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />
                </form>
            <?php else: ?>
                <a href="<?php echo htmlspecialchars($option['action']); ?>" target="<?php echo htmlspecialchars($option['target']); ?>" class="donate-button <?php echo htmlspecialchars($option['button_class']); ?>">
                    <?php echo htmlspecialchars($option['button_text']); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }
}

/**
 * Generate Zelle modal
 * 
 * @param array $zelleInfo Zelle information array
 */
function generateZelleModal(array $zelleInfo): void {
    ?>
    <div id="zelle-modal" class="zelle-modal">
        <div class="zelle-modal-content">
            <h3 style="margin: 0 0 15px 0; color: var(--text-color);">Zelle Information</h3>
            <p style="margin: 0 0 10px 0; color: var(--text-color); font-size: 0.9em;">Email: <strong><?php echo htmlspecialchars($zelleInfo['email']); ?></strong></p>
            <p style="margin: 0 0 15px 0; color: var(--text-color); font-size: 0.9em;">Name: <strong><?php echo htmlspecialchars($zelleInfo['name']); ?></strong></p>
            <button onclick="hideZelleInfo()" style="padding: 8px 16px; background-color: var(--table-header-bg); color: var(--text-color); border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer;">Close</button>
        </div>
    </div>
    <?php
}

/**
 * Generate donate JavaScript
 */
function generateDonateJavaScript(): void {
    ?>
    <script>
    function showZelleInfo() {
        document.getElementById('zelle-modal').style.display = 'block';
    }
    
    function hideZelleInfo() {
        document.getElementById('zelle-modal').style.display = 'none';
    }
    
    // Close modal when clicking outside of it
    window.onclick = function(event) {
        var modal = document.getElementById('zelle-modal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
    </script>
    <?php
}
