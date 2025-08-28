<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use SupermonNg\Domain\Entities\Node;
use SupermonNg\Services\AllStarConfigService;
use Ramsey\Uuid\Uuid;

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
        $nodeId = $args['id'] ?? null;
        $data = $request->getParsedBody();
        
        if (!$nodeId) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Node ID is required'
            ]));
            
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $targetNode = $data['target_node'] ?? null;
        
        if (!$targetNode) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Target node is required'
            ]));
            
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        try {
            // Verify the node exists in configuration
            if (!$this->configService->nodeExists($nodeId)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => "Node $nodeId is not configured"
                ]));
                
                return $response
                    ->withStatus(404)
                    ->withHeader('Content-Type', 'application/json');
            }

            // Get AMI configuration for the node
            $amiConfig = $this->configService->getAmiConfig($nodeId);
            
            $this->logger->info("Connecting node", [
                'node_id' => $nodeId,
                'target_node' => $targetNode,
                'ami_host' => $amiConfig['host'],
                'ami_port' => $amiConfig['port']
            ]);

            // TODO: Implement actual AMI connection and command
            // For now, return success response
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => "Node $nodeId connected to $targetNode",
                'data' => [
                    'node_id' => $nodeId,
                    'target_node' => $targetNode,
                    'status' => 'connected',
                    'ami_config' => [
                        'host' => $amiConfig['host'],
                        'port' => $amiConfig['port']
                    ],
                    'timestamp' => date('c')
                ]
            ]));

            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (Exception $e) {
            $this->logger->error('Failed to connect node', [
                'node_id' => $nodeId,
                'target_node' => $targetNode,
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Failed to connect node',
                'message' => $e->getMessage()
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    public function disconnect(Request $request, Response $response, array $args): Response
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

        $this->logger->info("Disconnecting node", ['node_id' => $nodeId]);

        // Mock disconnect response
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => "Node $nodeId disconnected",
            'data' => [
                'node_id' => $nodeId,
                'status' => 'disconnected',
                'timestamp' => date('c')
            ]
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function monitor(Request $request, Response $response, array $args): Response
    {
        $nodeId = $args['id'] ?? null;
        $data = $request->getParsedBody();
        
        if (!$nodeId) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Node ID is required'
            ]));
            
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $targetNode = $data['target_node'] ?? null;
        
        if (!$targetNode) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Target node is required'
            ]));
            
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $this->logger->info("Monitoring node", [
            'node_id' => $nodeId,
            'target_node' => $targetNode
        ]);

        // Mock monitor response
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => "Node $nodeId monitoring $targetNode",
            'data' => [
                'node_id' => $nodeId,
                'target_node' => $targetNode,
                'status' => 'monitoring',
                'timestamp' => date('c')
            ]
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function localMonitor(Request $request, Response $response, array $args): Response
    {
        $nodeId = $args['id'] ?? null;
        $data = $request->getParsedBody();
        
        if (!$nodeId) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Node ID is required'
            ]));
            
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $targetNode = $data['target_node'] ?? null;
        
        if (!$targetNode) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Target node is required'
            ]));
            
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $this->logger->info("Local monitoring node", [
            'node_id' => $nodeId,
            'target_node' => $targetNode
        ]);

        // Mock local monitor response
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => "Node $nodeId locally monitoring $targetNode",
            'data' => [
                'node_id' => $nodeId,
                'target_node' => $targetNode,
                'status' => 'local_monitoring',
                'timestamp' => date('c')
            ]
        ]));

        return $response->withHeader('Content-Type', 'application/json');
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
}
