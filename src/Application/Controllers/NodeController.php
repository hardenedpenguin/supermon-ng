<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use SupermonNg\Domain\Entities\Node;
use SupermonNg\Services\AllStarConfigService;
use Ramsey\Uuid\Uuid;
use Exception;

class NodeController
{
    private LoggerInterface $logger;
    private AllStarConfigService $configService;

    public function __construct(LoggerInterface $logger, AllStarConfigService $configService)
    {
        $this->logger = $logger;
        $this->configService = $configService;
    }

    public function list(Request $request, Response $response): Response
    {
        $this->logger->info('Fetching node list');
        
        try {
            // Get current user (or null if not logged in)
            $currentUser = $this->getCurrentUser();
            
            // Get available nodes from AllStar configuration
            $availableNodes = $this->configService->getAvailableNodes($currentUser);
            
            // Convert to the expected format
            $nodes = [];
            foreach ($availableNodes as $node) {
                $nodes[] = [
                    'id' => $node['id'],
                    'node_number' => $node['id'],
                    'callsign' => 'N/A', // Would come from ASTDB
                    'description' => $node['system'],
                    'location' => 'N/A', // Would come from ASTDB
                    'status' => 'unknown', // Would be determined by AMI connection
                    'last_heard' => null,
                    'connected_nodes' => null,
                    'cos_keyed' => null,
                    'tx_keyed' => null,
                    'cpu_temp' => null,
                    'alert' => null,
                    'wx' => null,
                    'disk' => null,
                    'is_online' => false,
                    'is_keyed' => false,
                    'created_at' => date('c'),
                    'updated_at' => date('c'),
                ];
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $nodes,
                'count' => count($nodes),
                'timestamp' => date('c'),
                'config_source' => $currentUser ? 'user_specific' : 'allmon.ini'
            ]));

            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (Exception $e) {
            $this->logger->error('Failed to fetch node list', ['error' => $e->getMessage()]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Failed to load node configuration',
                'message' => $e->getMessage()
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    public function available(Request $request, Response $response): Response
    {
        $this->logger->info('Fetching available nodes from AllStar configuration');
        
        try {
            // Get current user (or null if not logged in)
            $currentUser = $this->getCurrentUser();
            $availableNodes = $this->configService->getAvailableNodes($currentUser);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $availableNodes,
                'count' => count($availableNodes),
                'timestamp' => date('c'),
                'config_source' => $currentUser ? 'user_specific' : 'allmon.ini'
            ]));

            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (Exception $e) {
            $this->logger->error('Failed to fetch available nodes', ['error' => $e->getMessage()]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Failed to load available nodes',
                'message' => $e->getMessage()
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $nodeId = $args['id'] ?? null;
        
        if (!$nodeId) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Node ID is required'
            ]));
            
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $this->logger->info("Fetching node details", ['node_id' => $nodeId]);

        // Mock data for now
        $node = [
            'id' => Uuid::uuid4()->toString(),
            'node_number' => $nodeId,
            'callsign' => 'W1AW',
            'description' => 'ARRL HQ',
            'location' => 'Newington, CT',
            'status' => 'online',
            'last_heard' => date('Y-m-d H:i:s'),
            'connected_nodes' => '123456,789012',
            'cos_keyed' => '0',
            'tx_keyed' => '0',
            'cpu_temp' => '45.2',
            'alert' => null,
            'wx' => null,
            'disk' => null,
            'is_online' => true,
            'is_keyed' => false,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $node,
            'timestamp' => date('c')
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function status(Request $request, Response $response, array $args): Response
    {
        $nodeId = $args['id'] ?? null;
        
        if (!$nodeId) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Node ID is required'
            ]));
            
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $this->logger->info("Fetching node status", ['node_id' => $nodeId]);

        // Mock status data
        $status = [
            'node_id' => $nodeId,
            'status' => 'online',
            'last_heard' => date('Y-m-d H:i:s'),
            'connected_nodes' => '123456,789012',
            'cos_keyed' => '0',
            'tx_keyed' => '0',
            'cpu_temp' => '45.2',
            'alert' => null,
            'wx' => null,
            'disk' => null,
            'timestamp' => date('c')
        ];

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $status
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function connect(Request $request, Response $response, array $args): Response
    {
        return $this->executeNodeAction($request, $response, $args, 'connect');
    }

    public function disconnect(Request $request, Response $response, array $args): Response
    {
        return $this->executeNodeAction($request, $response, $args, 'disconnect');
    }

    public function monitor(Request $request, Response $response, array $args): Response
    {
        return $this->executeNodeAction($request, $response, $args, 'monitor');
    }

    public function localMonitor(Request $request, Response $response, array $args): Response
    {
        return $this->executeNodeAction($request, $response, $args, 'localmonitor');
    }

    public function dtmf(Request $request, Response $response, array $args): Response
    {
        return $this->executeDtmfAction($request, $response, $args);
    }

    /**
     * Execute DTMF action
     */
    private function executeDtmfAction(Request $request, Response $response, array $args): Response
    {
        // Get and validate parameters
        $data = $request->getParsedBody();
        $localNode = $data['localnode'] ?? null;
        $dtmfCommand = $data['dtmf'] ?? null;
        
        // Validate local node
        if (!$localNode || !preg_match("/^\d+$/", (string)$localNode)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Please provide a valid local node number.'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Validate DTMF command
        if (!$dtmfCommand || empty(trim($dtmfCommand))) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Please provide a DTMF command.'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Check user permissions
        $currentUser = $this->getCurrentUser();
        if (!$this->hasUserPermission($currentUser, 'DTMFUSER')) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'You are not authorized to perform DTMF commands.'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        try {
            // Load node configuration
            $nodeConfig = $this->loadNodeConfig($currentUser, (string)$localNode);
            if (!$nodeConfig) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "Configuration for local node $localNode not found."
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Connect to AMI
            $fp = $this->connectToAmi($nodeConfig, (string)$localNode);
            if (!$fp) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "Could not connect to Asterisk Manager for node $localNode."
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Execute DTMF command
            $commandResult = $this->executeDtmfCommand($fp, (string)$localNode, $dtmfCommand);
            
            // Clean up connection
            \SimpleAmiClient::logoff($fp);

            // Return success response
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => "DTMF command '$dtmfCommand' executed successfully on node $localNode",
                'data' => [
                    'action' => 'dtmf',
                    'local_node' => $localNode,
                    'dtmf_command' => $dtmfCommand,
                    'ami_response' => $commandResult,
                    'timestamp' => date('c')
                ]
            ]));

            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->logger->error('Failed to execute DTMF command', [
                'local_node' => $localNode,
                'dtmf_command' => $dtmfCommand,
                'error' => $e->getMessage()
            ]);

            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to execute DTMF command: ' . $e->getMessage()
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Execute DTMF command via AMI
     */
    private function executeDtmfCommand(mixed $fp, string $localNode, string $dtmfCommand): string|false
    {
        $asteriskCommand = "rpt fun " . $localNode . " " . $dtmfCommand;
        return \SimpleAmiClient::command($fp, $asteriskCommand);
    }

    /**
     * Execute node action (connect, disconnect, monitor, localmonitor)
     */
    private function executeNodeAction(Request $request, Response $response, array $args, string $action): Response
    {
        // Get and validate parameters
        $data = $request->getParsedBody();
        $localNode = $data['localnode'] ?? null;
        $remoteNode = $data['remotenode'] ?? null;
        $permInput = $data['perm'] ?? null;
        
        // Validate local node
        if (!$localNode || !preg_match("/^\d+$/", (string)$localNode)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Please provide a valid local node number.'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Check user permissions
        $currentUser = $this->getCurrentUser();
        if (!$this->hasUserPermission($currentUser, $this->getActionPermission($action))) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => "You are not authorized to perform the '$action' action."
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        try {
            // Load node configuration
            $nodeConfig = $this->loadNodeConfig($currentUser, (string)$localNode);
            if (!$nodeConfig) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "Configuration for local node $localNode not found."
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Connect to AMI
            $fp = $this->connectToAmi($nodeConfig, (string)$localNode);
            if (!$fp) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "Could not connect to Asterisk Manager for node $localNode."
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Process action and get ilink command
            $actionResult = $this->processAction($action, $permInput, (string)$localNode, $remoteNode, $currentUser);
            if (!$actionResult) {
                \SimpleAmiClient::logoff($fp);
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "Invalid action or insufficient permissions for '$action'."
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $ilink = $actionResult['ilink'];
            $message = $actionResult['message'];

            // Execute AMI command
            $commandResult = $this->executeAmiCommand($fp, $ilink, (string)$localNode, $remoteNode, $action);
            
            // Clean up connection
            \SimpleAmiClient::logoff($fp);

            // Return success response
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => $message,
                'data' => [
                    'action' => $action,
                    'local_node' => $localNode,
                    'remote_node' => $remoteNode,
                    'ilink' => $ilink,
                    'ami_response' => $commandResult,
                    'timestamp' => date('c')
                ]
            ]));

            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->logger->error('Failed to execute node action', [
                'action' => $action,
                'local_node' => $localNode,
                'remote_node' => $remoteNode,
                'error' => $e->getMessage()
            ]);

            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to execute action: ' . $e->getMessage()
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        }
    }



    /**
     * Get AMI status for nodes (real-time data)
     */
    public function getAmiStatus(Request $request, Response $response): Response
    {
        $this->logger->info('Fetching AMI status for nodes');
        
        try {
            // Get query parameters for node selection
            $queryParams = $request->getQueryParams();
            $nodeIds = $queryParams['nodes'] ?? '';
            
            if (empty($nodeIds)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'No nodes specified'
                ]));
                
                return $response
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json');
            }
            
            // Parse node IDs (comma-separated)
            $nodeIdArray = array_map('trim', explode(',', $nodeIds));
            
            // Get current user for configuration
            $currentUser = $this->getCurrentUser();
            $availableNodes = $this->configService->getAvailableNodes($currentUser);
            
            // Filter to only requested nodes that are available
            $requestedNodes = array_filter($availableNodes, function($node) use ($nodeIdArray) {
                return in_array($node['id'], $nodeIdArray);
            });
            
            $amiData = [];
            
            foreach ($requestedNodes as $node) {
                $nodeId = (string)$node['id'];
                $nodeConfig = $this->configService->getNodeConfig($nodeId);
                
                if (!$nodeConfig || !isset($nodeConfig['host'])) {
                    // Node not configured, return basic info
                    $amiData[$nodeId] = [
                        'node' => $nodeId,
                        'info' => 'Node not configured',
                        'status' => 'unknown',
                        'cos_keyed' => 0,
                        'tx_keyed' => 0,
                        'cpu_temp' => null,
                        'cpu_up' => null,
                        'cpu_load' => null,
                        'ALERT' => null,
                        'WX' => null,
                        'DISK' => null,
                        'remote_nodes' => []
                    ];
                    continue;
                }
                
                // Try to get AMI data for this node
                $nodeAmiData = $this->getNodeAmiData($nodeConfig, $nodeId);
                $amiData[$nodeId] = $nodeAmiData;
            }
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $amiData,
                'timestamp' => date('c')
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch AMI status', ['error' => $e->getMessage()]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Failed to fetch AMI status',
                'message' => $e->getMessage()
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Get AMI data for a specific node
     */
    private function getNodeAmiData(array $nodeConfig, string $nodeId): array
    {
        // Include the legacy AMI functions
        require_once __DIR__ . '/../../../includes/amifunctions.inc';
        require_once __DIR__ . '/../../../includes/nodeinfo.inc';
        require_once __DIR__ . '/../../../includes/helpers.inc';
        
        // Load ASTDB
        $astdbFile = __DIR__ . '/../../../astdb.txt';
        $astdb = [];
        if (file_exists($astdbFile)) {
            $astdb = $this->loadAstDb($astdbFile);
        }
        
        $host = $nodeConfig['host'];
        $user = $nodeConfig['user'] ?? '';
        $password = $nodeConfig['passwd'] ?? '';
        
        // Try to connect to AMI
        $socket = \SimpleAmiClient::connect($host);
        if ($socket === false) {
            return [
                'node' => $nodeId,
                'info' => 'AMI connection failed',
                'status' => 'offline',
                'cos_keyed' => 0,
                'tx_keyed' => 0,
                'cpu_temp' => null,
                'cpu_up' => null,
                'cpu_load' => null,
                'ALERT' => null,
                'WX' => null,
                'DISK' => null,
                'remote_nodes' => []
            ];
        }
        
        // Try to login
        $loginResult = \SimpleAmiClient::login($socket, $user, $password);
        if ($loginResult !== true) {
            \SimpleAmiClient::logoff($socket);
            return [
                'node' => $nodeId,
                'info' => 'AMI login failed',
                'status' => 'auth_failed',
                'cos_keyed' => 0,
                'tx_keyed' => 0,
                'cpu_temp' => null,
                'cpu_up' => null,
                'cpu_load' => null,
                'ALERT' => null,
                'WX' => null,
                'DISK' => null,
                'remote_nodes' => []
            ];
        }
        
        try {
            // Get node info
            $info = \getAstInfo($socket, $nodeId);
            
            // Get complete node data using XStat and SawStat
            $nodeData = $this->getNodeData($socket, $nodeId);
            
            \SimpleAmiClient::logoff($socket);
            
            return [
                'node' => $nodeId,
                'info' => $info,
                'status' => 'online',
                'cos_keyed' => $nodeData['cos_keyed'] ?? 0,
                'tx_keyed' => $nodeData['tx_keyed'] ?? 0,
                'cpu_temp' => $nodeData['cpu_temp'] ?? null,
                'cpu_up' => $nodeData['cpu_up'] ?? null,
                'cpu_load' => $nodeData['cpu_load'] ?? null,
                'ALERT' => $nodeData['ALERT'] ?? null,
                'WX' => $nodeData['WX'] ?? null,
                'DISK' => $nodeData['DISK'] ?? null,
                'remote_nodes' => $nodeData['remote_nodes'] ?? []
            ];
            
        } catch (\Exception $e) {
            \SimpleAmiClient::logoff($socket);
            $this->logger->error('Error getting AMI data for node', [
                'node_id' => $nodeId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'node' => $nodeId,
                'info' => 'Error: ' . $e->getMessage(),
                'status' => 'error',
                'cos_keyed' => 0,
                'tx_keyed' => 0,
                'cpu_temp' => null,
                'cpu_up' => null,
                'cpu_load' => null,
                'ALERT' => null,
                'WX' => null,
                'DISK' => null,
                'remote_nodes' => []
            ];
        }
    }
    
    /**
     * Get node data using XStat and SawStat AMI commands (like the original system)
     */
    private function getNodeData($socket, string $nodeId): array
    {
        // Include the necessary files for the original parsing logic
        require_once __DIR__ . '/../../../includes/amifunctions.inc';
        require_once __DIR__ . '/../../../includes/nodeinfo.inc';
        require_once __DIR__ . '/../../../includes/sse/server-functions.inc';
        
        // Define the ECHOLINK_NODE_THRESHOLD constant if not defined
        if (!defined('ECHOLINK_NODE_THRESHOLD')) {
            define('ECHOLINK_NODE_THRESHOLD', 3000000);
        }
        
        // Initialize global variables that the original functions expect
        global $astdb, $elnk_cache, $irlp_cache;
        if (!isset($astdb)) $astdb = $this->loadAstDb();
        if (!isset($elnk_cache)) $elnk_cache = [];
        if (!isset($irlp_cache)) $irlp_cache = [];
        
        // Use the original getNode function which uses XStat and SawStat
        $nodeData = \getNode($socket, $nodeId);
        
        if (empty($nodeData)) {
            return [
                'cos_keyed' => 0,
                'tx_keyed' => 0,
                'cpu_temp' => null,
                'cpu_up' => null,
                'cpu_load' => null,
                'ALERT' => null,
                'WX' => null,
                'DISK' => null,
                'remote_nodes' => []
            ];
        }
        
        // Extract main node data (key 1 contains the main node info)
        $mainNodeData = $nodeData[1] ?? [];
        $remoteNodes = [];
        
        // Extract remote nodes (all keys except 1)
        foreach ($nodeData as $key => $nodeInfo) {
            if ($key != 1 && is_array($nodeInfo)) {
                $remoteNodes[] = [
                    'node' => $nodeInfo['node'] ?? $key,
                    'info' => $nodeInfo['info'] ?? null,
                    'link' => $nodeInfo['link'] ?? null,
                    'ip' => $nodeInfo['ip'] ?? null,
                    'direction' => $nodeInfo['direction'] ?? null,
                    'keyed' => $nodeInfo['keyed'] ?? null,
                    'mode' => $nodeInfo['mode'] ?? null,
                    'elapsed' => $nodeInfo['elapsed'] ?? null,
                    'last_keyed' => $nodeInfo['last_keyed'] ?? null
                ];
            }
        }
        
        return [
            'cos_keyed' => $mainNodeData['cos_keyed'] ?? 0,
            'tx_keyed' => $mainNodeData['tx_keyed'] ?? 0,
            'cpu_temp' => $mainNodeData['cpu_temp'] ?? null,
            'cpu_up' => $mainNodeData['cpu_up'] ?? null,
            'cpu_load' => $mainNodeData['cpu_load'] ?? null,
            'ALERT' => $mainNodeData['ALERT'] ?? null,
            'WX' => $mainNodeData['WX'] ?? null,
            'DISK' => $mainNodeData['DISK'] ?? null,
            'remote_nodes' => $remoteNodes
        ];
    }
    
    /**
     * Load ASTDB file
     */
    private function loadAstDb(string $filename): array
    {
        $astdb = [];
        
        if (!file_exists($filename)) {
            return $astdb;
        }
        
        $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 4) {
                $nodeId = trim($parts[0]);
                $astdb[$nodeId] = $parts;
            }
        }
        
        return $astdb;
    }

    /**
     * Get the currently logged in user from session
     */
    private function getCurrentUser(): ?string
    {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is logged in via session
        if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
            return $_SESSION['user'];
        }
        
        // Check if user is logged in via HTTP Basic Auth
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            return $_SERVER['PHP_AUTH_USER'];
        }
        
        // Check if user is logged in via .htaccess/.htpasswd
        if (isset($_SERVER['REMOTE_USER'])) {
            return $_SERVER['REMOTE_USER'];
        }
        
        return null;
    }

    /**
     * Get permission required for an action
     */
    private function getActionPermission(string $action): string
    {
        $permissions = [
            'connect' => 'CONNECTUSER',
            'disconnect' => 'DISCUSER',
            'monitor' => 'MONUSER',
            'localmonitor' => 'LMONUSER'
        ];
        
        return $permissions[$action] ?? 'CONNECTUSER';
    }

    /**
     * Check if user has permission
     */
    private function hasUserPermission(?string $user, string $permission): bool
    {
        // Include necessary files
        require_once __DIR__ . '/../../../includes/common.inc';
        
        // If no user, use default permissions
        if (!$user) {
            $defaultPermissions = [
                'CONNECTUSER' => true,
                'DISCUSER' => true,
                'MONUSER' => true,
                'LMONUSER' => true,
                'DTMFUSER' => true,
                'PERMUSER' => true
            ];
            return $defaultPermissions[$permission] ?? false;
        }
        
        // For now, use default permissions for all users
        // TODO: Implement proper user-specific permission checking
        $defaultPermissions = [
            'CONNECTUSER' => true,
            'DISCUSER' => true,
            'MONUSER' => true,
            'LMONUSER' => true,
            'DTMFUSER' => true,
            'PERMUSER' => true
        ];
        return $defaultPermissions[$permission] ?? false;
    }

    /**
     * Load node configuration from INI file
     */
    private function loadNodeConfig(?string $user, string $localNode): ?array
    {
        // Include necessary files
        require_once __DIR__ . '/../../../includes/common.inc';
        
        // Determine INI file path
        $iniFile = null;
        if ($user) {
            // Try user-specific INI file
            $userIniFile = __DIR__ . '/../../../user_files/' . $user . '.ini';
            if (file_exists($userIniFile)) {
                $iniFile = $userIniFile;
            }
        }
        
        // Fallback to allmon.ini
        if (!$iniFile) {
            $allmonIni = __DIR__ . '/../../../user_files/allmon.ini';
            if (file_exists($allmonIni)) {
                $iniFile = $allmonIni;
            }
        }
        
        // Fallback to anarchy-allmon.ini
        if (!$iniFile) {
            $anarchyIni = __DIR__ . '/../../../user_files/anarchy-allmon.ini';
            if (file_exists($anarchyIni)) {
                $iniFile = $anarchyIni;
            }
        }
        
        // Debug logging
        $this->logger->info('Loading node config', [
            'user' => $user,
            'localNode' => $localNode,
            'iniFile' => $iniFile,
            'fileExists' => $iniFile ? file_exists($iniFile) : false
        ]);
        
        if (!$iniFile || !file_exists($iniFile)) {
            $this->logger->error('No valid INI file found', [
                'user' => $user,
                'localNode' => $localNode
            ]);
            return null;
        }
        
        $config = parse_ini_file($iniFile, true);
        
        if (!isset($config[$localNode])) {
            $this->logger->error('Node not found in config', [
                'user' => $user,
                'localNode' => $localNode,
                'iniFile' => $iniFile,
                'availableNodes' => array_keys($config)
            ]);
            return null;
        }
        
        return $config[$localNode];
    }

    /**
     * Connect to AMI
     */
    private function connectToAmi(array $nodeConfig, string $localNode): mixed
    {
        // Include AMI functions
        require_once __DIR__ . '/../../../includes/amifunctions.inc';
        
        $fp = \SimpleAmiClient::connect($nodeConfig['host']);
        if ($fp === false) {
            return false;
        }

        if (\SimpleAmiClient::login($fp, $nodeConfig['user'], $nodeConfig['passwd']) === false) {
            \SimpleAmiClient::logoff($fp);
            return false;
        }

        return $fp;
    }

    /**
     * Process action and determine ilink command
     */
    private function processAction(string $action, ?string $permInput, string $localNode, ?string $remoteNode, ?string $user): ?array
    {
        $actionsConfig = [
            'connect' => [
                'auth' => 'CONNECTUSER',
                'ilink_normal' => 3,
                'ilink_perm' => 13,
                'verb' => 'Connecting',
                'structure' => '%s %s to %s'
            ],
            'monitor' => [
                'auth' => 'MONUSER',
                'ilink_normal' => 2,
                'ilink_perm' => 12,
                'verb' => 'Monitoring',
                'structure' => '%s %s from %s'
            ],
            'localmonitor' => [
                'auth' => 'LMONUSER',
                'ilink_normal' => 8,
                'ilink_perm' => 18,
                'verb' => 'Local Monitoring',
                'structure' => '%s %s from %s'
            ],
            'disconnect' => [
                'auth' => 'DISCUSER',
                'ilink_normal' => 11,
                'ilink_perm' => 11,
                'verb' => 'Disconnect',
                'structure' => '%s %s from %s'
            ]
        ];
        
        if (!isset($actionsConfig[$action])) {
            return null;
        }
        
        $actionConfig = $actionsConfig[$action];
        
        // Check if user has permission for this action
        if (!$this->hasUserPermission($user, $actionConfig['auth'])) {
            return null;
        }
        
        // Check if this is a permanent action
        $isPermanentAction = ($permInput === 'on' && $this->hasUserPermission($user, 'PERMUSER'));
        
        $ilink = $isPermanentAction ? $actionConfig['ilink_perm'] : $actionConfig['ilink_normal'];
        $verbPrefix = $isPermanentAction ? "Permanently " : "";
        $currentVerb = $verbPrefix . $actionConfig['verb'];
        
        if ($action === 'connect') {
            $message = sprintf($actionConfig['structure'], $currentVerb, $localNode, $remoteNode);
        } else {
            $message = sprintf($actionConfig['structure'], $currentVerb, $remoteNode, $localNode);
        }
        
        return [
            'ilink' => $ilink,
            'message' => $message,
            'button' => $action
        ];
    }

    /**
     * Execute AMI command
     */
    private function executeAmiCommand(mixed $fp, int $ilink, string $localNode, ?string $remoteNode, string $action): string
    {
        $commandToSend = "rpt cmd $localNode ilink $ilink";
        if (!empty($remoteNode) || ($action === 'disconnect' && !empty($remoteNode))) {
            $commandToSend .= " $remoteNode";
        }
        
        return \SimpleAmiClient::command($fp, trim($commandToSend));
    }
}
