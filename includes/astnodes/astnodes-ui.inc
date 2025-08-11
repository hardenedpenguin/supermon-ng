<?php
/**
 * Supermon-ng AstNodes UI
 * 
 * Provides the CSS styling and HTML rendering for the Asterisk nodes viewer interface.
 * Handles responsive design, file content display, and error message formatting.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
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
    $view = $_GET['view'] ?? 'all';
    $title = ($view === 'active') ? "Active AllStar Nodes" : "AllStar Asterisk DB File Viewer";
    ?>
    <div class="astnodes-page">
        <h1 class="astnodes-title"><?php echo htmlspecialchars($title); ?></h1>
    <?php
}

/**
 * Generate active nodes header
 */
function generateAstnodesActiveHeader(): void {
    ?>
    <div class="file-header">Displaying currently active nodes from your configured systems</div>
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
 * Get active nodes from the current system
 * 
 * @return array Array of active node information
 */
function getActiveNodes(): array {
    $activeNodes = [];
    
    // Include necessary files for node monitoring
    if (!isset($_SESSION['user'])) {
        return $activeNodes;
    }
    
    $iniFilePath = get_ini_name($_SESSION['user']);
    if (!file_exists($iniFilePath)) {
        return $activeNodes;
    }
    
    $config = parse_ini_file($iniFilePath, true);
    if ($config === false) {
        return $activeNodes;
    }
    
    // Check each configured node
    foreach ($config as $nodeId => $nodeConfig) {
        if (!isset($nodeConfig['host']) || !isset($nodeConfig['user']) || !isset($nodeConfig['passwd'])) {
            continue;
        }
        
        // Try to connect to the node
        if (class_exists('SimpleAmiClient')) {
            $fp = SimpleAmiClient::connect($nodeConfig['host']);
            if ($fp !== false) {
                if (SimpleAmiClient::login($fp, $nodeConfig['user'], $nodeConfig['passwd']) !== false) {
                    // Node is active, get its info
                    $astInfo = SimpleAmiClient::command($fp, "rpt cmd $nodeId info");
                    SimpleAmiClient::logoff($fp);
                    
                    if ($astInfo !== false) {
                        $activeNodes[] = [
                            'node' => $nodeId,
                            'host' => $nodeConfig['host'],
                            'info' => $astInfo
                        ];
                    }
                } else {
                    SimpleAmiClient::logoff($fp);
                }
            }
        }
    }
    
    return $activeNodes;
}

/**
 * Generate active nodes content display
 * 
 * @param array $activeNodes Array of active node information
 */
function generateAstnodesActiveContent(array $activeNodes): void {
    echo '<div class="file-content">';
    
    if (empty($activeNodes)) {
        echo '<span class="error-in-pre">No active nodes found. Check your configuration and AMI connections.</span>';
    } else {
        echo "Active Nodes (" . count($activeNodes) . "):\n\n";
        
        foreach ($activeNodes as $node) {
            echo "Node: " . htmlspecialchars($node['node']) . "\n";
            echo "Host: " . htmlspecialchars($node['host']) . "\n";
            echo "Info: " . htmlspecialchars($node['info']) . "\n";
            echo str_repeat("-", 50) . "\n";
        }
    }
    
    echo '</div>';
}

/**
 * Generate astnodes page footer
 */
function generateAstnodesFooter(): void {
    ?>
    </div>
    <?php
}
