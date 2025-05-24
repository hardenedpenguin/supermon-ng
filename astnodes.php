<?php
include("session.inc");
include("common.inc");
include("authusers.php");

if (!isset($ASTDB_TXT)) {
    die("Critical Configuration Error: \$ASTDB_TXT is not defined in common.inc. Please check the configuration.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AllStar astdb.txt File Contents</title>
    <style>
        body {
            font-family: sans-serif;
            background-color: #000000;
            color: #eeeeee;
            margin: 0;
            padding: 20px;
        }
        h1 {
            color: #ffffff;
            border-bottom: 1px solid #444;
            padding-bottom: 10px;
            margin-top: 0;
        }
        .file-header {
            font-weight: bold;
            margin-bottom: 5px;
            color: #6495ED;
            font-size: 1.1em;
        }
        hr {
            margin-top: 5px;
            margin-bottom: 15px;
            border: 0;
            border-top: 1px solid #444;
        }
        pre {
            font-family: monospace;
            font-size: 16px;
            background-color: #1a1a1a;
            color: #f0f0f0;
            border: 1px solid #444;
            padding: 15px;
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow-x: auto;
            border-radius: 4px;
        }
        .error {
            color: #FF6347;
            font-weight: bold;
        }
        .error-in-pre {
            color: #FFA07A;
            font-weight: bold;
        }
        a {
            color: #ADD8E6;
        }
        a:visited {
            color: #B19CD9;
        }
    </style>
</head>
<body>

<h1>AllStar Asterisk DB File Viewer</h1>

<?php
if (isset($_SESSION['sm61loggedin']) && $_SESSION['sm61loggedin'] === true && get_user_auth("NINFUSER")) {
    $filePath = $ASTDB_TXT;
    echo '<div class="file-header">Displaying File: ' . htmlspecialchars($filePath) . '</div>';
    echo '<hr>';
    echo '<pre>';

    if (file_exists($filePath) && is_readable($filePath)) {
        $fileContent = file_get_contents($filePath);
        if ($fileContent !== false) {
            echo htmlspecialchars($fileContent);
        } else {
            echo '<span class="error-in-pre">ERROR: Could not read file content from ' . htmlspecialchars($filePath) . '.</span>';
        }
    } else {
        echo '<span class="error-in-pre">ERROR: File not found or is not readable at the specified path: ' . htmlspecialchars($filePath) . '.</span>';
    }

    echo '</pre>';
} else {
    echo '<h3><span class="error">Access Denied!</span></h3>';
    echo '<p>You must be logged in and have the required permissions ("NINFUSER") to view this page.</p>';
}
?>

</body>
</html>
