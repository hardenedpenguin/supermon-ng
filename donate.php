<?php
include("session.inc");
include("authusers.php");
include("common.inc");
include("user_files/global.inc");

$title = "Support Supermon-ng Development";

?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($title); ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="generator" content="By hand with a text editor">
    <meta name="description" content="SuperMon-ng Donation Options">
    <meta name="keywords" content="allstar monitor, app_rpt, asterisk">
    <meta name="author" content="Tim Sawyer, WD6AWP">
    <meta name="mods" content="New features, IRLP capability, Paul Aidukas, KN2R">
    <link type="text/css" rel="stylesheet" href="supermon-ng.css">
    <link type="text/css" rel="stylesheet" href="js/jquery-ui.css">
    <script src="js/jquery.min.js"></script>
    <script src="js/jquery-ui.min.js"></script>
    <script src="js/sweetalert2.min.js"></script>
    <link rel="stylesheet" href="js/sweetalert2.min.css"/>

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
</head>
<body>
    <div id="header" class="header-dynamic" style="background-image: url(<?php echo htmlspecialchars($BACKGROUND ?? '', ENT_QUOTES, 'UTF-8'); ?>); background-color:<?php echo htmlspecialchars($BACKGROUND_COLOR ?? '', ENT_QUOTES, 'UTF-8'); ?>;<?php if (isset($BACKGROUND_HEIGHT)) echo ' height:' . htmlspecialchars($BACKGROUND_HEIGHT, ENT_QUOTES, 'UTF-8') . ';'; ?>">
        <div id="headerTitle-large"><i><?php echo htmlspecialchars(($CALL ?? '') . " - " . ($TITLE_LOGGED ?? '')); ?></i></div>
        <div id="header3Tag-large"><i></i></div>
        <div id="header2Tag-large"><i><?php echo htmlspecialchars($TITLE3 ?? ''); ?></i></div>
        <div id="headerImg"><a href="https://www.allstarlink.org" target="_blank"><img src="allstarlink.jpg" width="70%" class="img-borderless" alt="AllStar Logo"></a></div>
    </div>

    <div style="padding: 20px; min-height: 400px; display: flex; flex-direction: column;">
        <h2 style="text-align: center; color: var(--text-color); margin-bottom: 15px;">Support This Project</h2>
        <p style="text-align: center; color: var(--text-color); margin-bottom: 20px; font-size: 0.9em;">If you find this system useful, please consider a donation to help with maintenance and development.</p>
        
        <!-- PayPal Option -->
        <div class="donate-option" style="margin: 10px 0; padding: 15px;">
            <h3 style="color: var(--text-color); margin-bottom: 5px; font-size: 1em;">PayPal</h3>
            <p class="donate-info" style="margin: 5px 0;">Donate securely via PayPal</p>
            <form action="https://www.paypal.com/donate" method="post" target="_blank" style="display: inline-block;">
                <input type="hidden" name="business" value="H2XYYRGQ9Q92E" />
                <input type="hidden" name="no_recurring" value="0" />
                <input type="hidden" name="item_name" value="Help to Support the Continued Development" />
                <input type="hidden" name="currency_code" value="USD" />
                <button type="submit" name="submit" class="donate-button paypal-button">
                    Donate with PayPal
                </button>
                <img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />
            </form>
        </div>
        
        <!-- CashApp Option -->
        <div class="donate-option" style="margin: 10px 0; padding: 15px;">
            <h3 style="color: var(--text-color); margin-bottom: 5px; font-size: 1em;">CashApp</h3>
            <p class="donate-info" style="margin: 5px 0;">Send money via CashApp to $anarchpeng</p>
            <a href="https://cash.app/$anarchpeng" target="_blank" class="donate-button cashapp-button">
                Send via CashApp
            </a>
        </div>
        
        <!-- Zelle Option -->
        <div class="donate-option" style="margin: 10px 0; padding: 15px;">
            <h3 style="color: var(--text-color); margin-bottom: 5px; font-size: 1em;">Zelle</h3>
            <p class="donate-info" style="margin: 5px 0;">Send money via Zelle using your bank's app</p>
            <button onclick="showZelleInfo()" class="donate-button zelle-button">
                Get Zelle Info
            </button>
        </div>
        
        <!-- Spacer to push close button to bottom -->
        <div style="flex-grow: 1;"></div>
        
        <!-- Close button at bottom -->
        <div style="text-align: center; margin-top: 20px;">
            <input type="button" style="padding: 8px 16px; font-size: 0.9em; background-color: var(--table-header-bg); color: var(--text-color); border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer;" Value="Close" onclick="self.close()">
        </div>
    </div>

    <!-- Zelle Info Modal -->
    <div id="zelle-modal" class="zelle-modal">
        <div class="zelle-modal-content">
            <h3 style="margin: 0 0 15px 0; color: var(--text-color);">Zelle Information</h3>
            <p style="margin: 0 0 10px 0; color: var(--text-color); font-size: 0.9em;">Email: <strong>geekypenguin@gmail.com</strong></p>
            <p style="margin: 0 0 15px 0; color: var(--text-color); font-size: 0.9em;">Name: <strong>Jory Pratt</strong></p>
            <button onclick="hideZelleInfo()" style="padding: 8px 16px; background-color: var(--table-header-bg); color: var(--text-color); border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer;">Close</button>
        </div>
    </div>

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
</body>
</html> 