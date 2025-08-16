<?php
// Supermon-ng API Entry Point
header('Content-Type: application/json');

// Simple router
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$path = preg_replace('#^api/?#', '', $path); // Remove 'api/' prefix if present
$method = $_SERVER['REQUEST_METHOD'];

// API key authentication (except for health)
if ($path !== 'health') {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? null);
    $validKey = getenv('API_KEY') ?: 'changeme'; // Set in .env or hardcoded for now
    if (!$apiKey || $apiKey !== $validKey) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: Invalid or missing API key']);
        exit;
    }
}

// Routing
switch ($path) {
    case 'health':
        echo json_encode(['status' => 'ok', 'timestamp' => time()]);
        break;
    case 'nodes':
        // Parse user_files/astdb.txt for real node data
        $astdbFile = __DIR__ . '/../user_files/astdb.txt';
        $nodes = [];
        if (file_exists($astdbFile) && is_readable($astdbFile)) {
            $lines = file($astdbFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $parts = explode('|', $line);
                if (count($parts) >= 4) {
                    $nodes[] = [
                        'id' => trim($parts[0]),
                        'callsign' => trim($parts[1]),
                        'description' => trim($parts[2]),
                        'location' => trim($parts[3])
                    ];
                }
            }
        }
        echo json_encode(['nodes' => $nodes]);
        break;
    case 'metrics':
        // Gather basic system and node metrics
        $metrics = [];
        // Uptime
        $metrics['uptime'] = trim(@shell_exec('uptime -p 2>/dev/null'));
        // Load average
        $metrics['load_average'] = trim(@shell_exec('uptime | awk -F"load average:" \'{print $2}\''));
        // Asterisk version
        $metrics['asterisk_version'] = trim(@shell_exec('asterisk -rx "core show version" 2>/dev/null | head -n1'));
        // Node count
        $astdbFile = __DIR__ . '/../user_files/astdb.txt';
        $metrics['node_count'] = (file_exists($astdbFile) && is_readable($astdbFile)) ? count(file($astdbFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;
        echo json_encode(['metrics' => $metrics]);
        break;
    case 'control':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            break;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        $node = $input['node'] ?? null;
        $command = $input['command'] ?? null;
        if (!$node || !$command) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing node or command parameter']);
            break;
        }
        // Lookup node config from user_files/global.inc or INI
        $user = 'admin'; // For now, use a default user or enhance for multi-user
        $iniFile = __DIR__ . '/../user_files/global.inc';
        if (!file_exists($iniFile)) {
            http_response_code(500);
            echo json_encode(['error' => 'Config file not found']);
            break;
        }
        $config = parse_ini_file($iniFile, true);
        if (!isset($config[$node])) {
            http_response_code(404);
            echo json_encode(['error' => 'Node not found in config']);
            break;
        }
        $amiConfig = $config[$node];
        if (!isset($amiConfig['host'], $amiConfig['user'], $amiConfig['passwd'])) {
            http_response_code(500);
            echo json_encode(['error' => 'Incomplete AMI config for node']);
            break;
        }
        require_once __DIR__ . '/../includes/amifunctions.inc';
        $fp = SimpleAmiClient::connect($amiConfig['host']);
        if ($fp === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Could not connect to AMI']);
            break;
        }
        if (!SimpleAmiClient::login($fp, $amiConfig['user'], $amiConfig['passwd'])) {
            SimpleAmiClient::logoff($fp);
            http_response_code(401);
            echo json_encode(['error' => 'AMI authentication failed']);
            break;
        }
        $output = SimpleAmiClient::command($fp, $command);
        SimpleAmiClient::logoff($fp);
        if ($output === false) {
            http_response_code(500);
            echo json_encode(['error' => 'AMI command failed']);
            break;
        }
        echo json_encode([
            'result' => 'Command executed',
            'node' => $node,
            'command' => $command,
            'output' => $output
        ]);
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
        break;
}
