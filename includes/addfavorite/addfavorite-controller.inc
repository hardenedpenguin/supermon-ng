<?php
/**
 * Supermon-ng Add Favorite Controller
 * 
 * Main controller for the add favorite functionality that orchestrates authentication,
 * validation, node lookup, and favorites file management.
 * 
 * @author Supermon-ng Team
 * @version 3.0.0
 * @since 1.0.0
 */

include_once "includes/addfavorite/addfavorite-auth.inc";
include_once "includes/addfavorite/addfavorite-ui.inc";

/**
 * Initialize and run the add favorite system
 * 
 * This is the main entry point for the add favorite functionality.
 * Handles authentication, validation, node lookup, and form processing.
 * 
 * @return void
 */
function runAddFavorite(): void {
    // Validate user access
    if (!validateAddFavoriteAccess()) {
        die("<br><h3>ERROR: You Must login to use the 'Add Favorite' function!</h3>");
    }

    // Get and validate node parameter
    $node = getValidatedNode();
    if ($node === false) {
        die ("Please provide a properly formatted URI. (ie addfavorite.php?node=1234)");
    }

    $title = "Add Favorite - Node $node";

    // Look up node information
    $nodeInfo = lookupNodeInfo($node);
    if ($nodeInfo === false) {
        die("Node $node not found in astdb.txt database.");
    }

    // Check if node already exists in favorites
    $user = $_SESSION['user'] ?? 'admin';
    $alreadyExists = nodeExistsInFavorites($user, $node);

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = processAddFavoriteForm($user, $node, $nodeInfo);
        if ($result['success']) {
            $title = "Favorite Added Successfully";
        } else {
            $title = "Error Adding Favorite";
        }
    }

    // Output the complete add favorite interface
    outputAddFavoriteHead($title);
    outputAddFavoriteScript();
    ?>
</head>
<body>
    <?php
    outputAddFavoriteHeader($title);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($result)) {
        outputAddFavoriteResult($result);
    } else {
        outputAddFavoriteForm($node, $nodeInfo, $alreadyExists);
    }
    
    outputAddFavoriteFooter();
    ?>
</body>
</html>
    <?php
}

/**
 * Process the add favorite form submission
 * 
 * @param string $user User identifier
 * @param string $node Node number
 * @param array $nodeInfo Node information from astdb.txt
 * @return array Result of the operation
 */
function processAddFavoriteForm(string $user, string $node, array $nodeInfo): array {
    $favoritesFile = getUserFavoritesFile($user);
    $customLabel = trim($_POST['custom_label'] ?? '');
    $addToGeneral = ($_POST['add_to_general'] ?? '') === '1';
    
    // Use custom label if provided, otherwise generate from node info
    if (empty($customLabel)) {
        $customLabel = $nodeInfo['callsign'] . ' ' . $nodeInfo['description'] . ' ' . $node;
    }
    
    // Generate command
    $command = "rpt cmd %node% ilink 13 " . $node;
    
    // Load existing favorites
    $config = [];
    if (file_exists($favoritesFile)) {
        $config = parse_ini_file($favoritesFile, true);
        if ($config === false) {
            $config = [];
        }
    }
    
    // Ensure general section exists
    if (!isset($config['general'])) {
        $config['general'] = [];
    }
    
    // Add to favorites
    if ($addToGeneral) {
        // Add to general section
        if (!isset($config['general']['label'])) {
            $config['general']['label'] = [];
        }
        if (!isset($config['general']['cmd'])) {
            $config['general']['cmd'] = [];
        }
        
        $config['general']['label'][] = $customLabel;
        $config['general']['cmd'][] = $command;
    } else {
        // Add to node-specific section
        if (!isset($config[$node])) {
            $config[$node] = [];
        }
        if (!isset($config[$node]['label'])) {
            $config[$node]['label'] = [];
        }
        if (!isset($config[$node]['cmd'])) {
            $config[$node]['cmd'] = [];
        }
        
        $config[$node]['label'][] = $customLabel;
        $config[$node]['cmd'][] = $command;
    }
    
    // Write back to file
    $result = writeIniFile($favoritesFile, $config);
    
    if ($result) {
        return [
            'success' => true,
            'message' => "Node $node has been successfully added to your favorites as \"$customLabel\".",
            'node' => $node,
            'label' => $customLabel
        ];
    } else {
        return [
            'success' => false,
            'message' => "Error: Could not write to favorites file. Please check file permissions.",
            'node' => $node
        ];
    }
}

/**
 * Write INI configuration to file
 * 
 * @param string $filename File path
 * @param array $config Configuration array
 * @return bool True on success, false on failure
 */
function writeIniFile(string $filename, array $config): bool {
    $content = "";
    
    foreach ($config as $section => $values) {
        $content .= "[$section]\n";
        
        if (is_array($values)) {
            foreach ($values as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $item) {
                        $content .= "$key[] = \"$item\"\n";
                    }
                } else {
                    $content .= "$key = \"$value\"\n";
                }
            }
        }
        
        $content .= "\n";
    }
    
    return file_put_contents($filename, $content) !== false;
}
?>
