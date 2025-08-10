<?php
/**
 * Supermon-ng AstNodes UI
 * 
 * Provides the CSS styling and HTML rendering for the Asterisk nodes viewer interface.
 * Handles responsive design, file content display, and error message formatting.
 * 
 * @author Supermon-ng Team
 * @version 2.0.3
 * @since 1.0.0
 */

/**
 * Output configuration error
 */
function outputAstnodesConfigError(): void {
    die("Critical Configuration Error: \$ASTDB_TXT is not defined in common.inc. Please check the configuration.");
}

/**
 * Generate astnodes CSS styles
 */
function generateAstnodesStyles(): void {
    ?>
    <style>
    .astnodes-page {
        max-width: 1200px;
        margin: 20px auto;
        padding: 20px;
    }

    .astnodes-title {
        color: var(--text-color);
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 10px;
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 1.8em;
        font-weight: bold;
    }

    .file-header {
        font-weight: bold;
        margin-bottom: 5px;
        color: var(--link-color);
        font-size: 1.1em;
        background: var(--container-bg);
        padding: 10px;
        border-radius: 4px;
        border: 1px solid var(--border-color);
    }

    .file-content {
        font-family: monospace;
        font-size: 14px;
        background: var(--container-bg);
        color: var(--text-color);
        border: 1px solid var(--border-color);
        padding: 15px;
        white-space: pre-wrap;
        word-wrap: break-word;
        overflow-x: auto;
        border-radius: 4px;
        max-height: 600px;
        overflow-y: auto;
    }

    .error {
        color: var(--error-color);
        font-weight: bold;
    }

    .error-in-pre {
        color: var(--warning-color);
        font-weight: bold;
    }

    .access-denied {
        background: var(--container-bg);
        padding: 20px;
        border-radius: 8px;
        border: 1px solid var(--error-color);
        margin: 20px 0;
    }

    .access-denied h3 {
        color: var(--error-color);
        margin-top: 0;
    }

    .access-denied p {
        color: var(--text-color);
        margin-bottom: 0;
    }

    @media (max-width: 768px) {
        .astnodes-page {
            padding: 10px;
            margin: 10px;
        }
        
        .astnodes-title {
            font-size: 1.4em;
        }
        
        .file-content {
            font-size: 12px;
            padding: 10px;
        }
    }
    </style>
    <?php
}

/**
 * Generate astnodes page header
 */
function generateAstnodesHeader(): void {
    ?>
    <div class="astnodes-page">
        <h1 class="astnodes-title">AllStar Asterisk DB File Viewer</h1>
    <?php
}

/**
 * Generate file header display
 * 
 * @param string $filePath Path to the file
 */
function generateAstnodesFileHeader(string $filePath): void {
    echo '<div class="file-header">Displaying File: ' . htmlspecialchars($filePath) . '</div>';
}

/**
 * Generate file content display
 * 
 * @param string $filePath Path to the file
 * @param string|false $fileContent File content or false on failure
 */
function generateAstnodesFileContent(string $filePath, $fileContent): void {
    echo '<div class="file-content">';
    
    if ($fileContent !== false) {
        echo htmlspecialchars($fileContent);
    } else {
        echo '<span class="error-in-pre">ERROR: Could not read file content from ' . htmlspecialchars($filePath) . '.</span>';
    }
    
    echo '</div>';
}

/**
 * Generate file not found error
 * 
 * @param string $filePath Path to the file
 */
function generateAstnodesFileNotFoundError(string $filePath): void {
    echo '<div class="file-content">';
    echo '<span class="error-in-pre">ERROR: File not found or is not readable at the specified path: ' . htmlspecialchars($filePath) . '.</span>';
    echo '</div>';
}

/**
 * Generate access denied message
 */
function generateAstnodesAccessDenied(): void {
    ?>
    <div class="access-denied">
        <h3><span class="error">Access Denied!</span></h3>
        <p>You must be logged in and have the required permissions ("NINFUSER") to view this page.</p>
    </div>
    <?php
}

/**
 * Generate astnodes page footer
 */
function generateAstnodesFooter(): void {
    ?>
    </div>
    <?php
}
