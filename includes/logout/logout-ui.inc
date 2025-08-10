<?php
/**
 * Supermon-ng Logout UI
 * 
 * Provides the CSS styling, HTML rendering, and JavaScript functionality for the logout interface.
 * Handles responsive design, logout confirmation, and client-side cleanup.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Generate logout CSS styles
 */
function generateLogoutStyles(): void {
    ?>
    <style>
    .logout-page {
        max-width: 400px;
        margin: 50px auto;
        background: var(--container-bg);
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        border: 1px solid var(--border-color);
        text-align: center;
    }

    .logout-title {
        color: var(--text-color);
        margin-bottom: 20px;
        font-size: 1.5em;
        font-weight: bold;
    }

    .logout-message {
        color: var(--text-color);
        margin-bottom: 30px;
        opacity: 0.9;
        line-height: 1.5;
    }

    .logout-btn {
        display: inline-block;
        padding: 12px 24px;
        background: var(--primary-color);
        color: var(--text-color);
        text-decoration: none;
        border-radius: 4px;
        font-size: 16px;
        font-weight: bold;
        transition: background-color 0.3s ease;
    }

    .logout-btn:hover {
        background: var(--link-hover);
        text-decoration: none;
        color: var(--text-color);
    }

    @media (max-width: 768px) {
        .logout-page {
            margin: 20px auto;
            padding: 20px;
        }
        
        .logout-title {
            font-size: 1.3em;
        }
    }
    </style>
    <?php
}

/**
 * Generate logout page content
 */
function generateLogoutContent(): void {
    ?>
    <div class="logout-page">
        <h2 class="logout-title">Successfully Logged Out</h2>
        <p class="logout-message">
            You have been successfully logged out of Supermon-ng.<br>
            All session data has been cleared.
        </p>
        <a href="index.php" class="logout-btn">Return to Login</a>
    </div>
    <?php
}

/**
 * Generate logout JavaScript
 */
function generateLogoutJavaScript(): void {
    ?>
    <script>
        // Clear any client-side storage
        if (typeof localStorage !== 'undefined') {
            localStorage.clear();
        }
        if (typeof sessionStorage !== 'undefined') {
            sessionStorage.clear();
        }
        
        // Redirect to login page after 3 seconds
        setTimeout(function() {
            window.location.href = 'index.php';
        }, 3000);
    </script>
    <?php
}
