<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

// Include common.inc for global variables
require_once __DIR__ . '/../../../includes/common.inc';

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

        // Get node configuration
        try {
            $nodeConfig = $this->configService->getNodeConfig($nodeId);
            if (!$nodeConfig) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Node configuration not found'
                ]));
                
                return $response
                    ->withStatus(404)
                    ->withHeader('Content-Type', 'application/json');
            }
        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Node configuration not found: ' . $e->getMessage()
            ]));
            
            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        // Get real node status from AMI
        $ami = $this->connectToAmi($nodeConfig, $nodeId);
        if (!$ami) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Failed to connect to AMI'
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }

        // Get node status information
        $statusInfo = $this->getNodeStatusInfo($ami, $nodeId);
        
        $node = [
            'id' => Uuid::uuid4()->toString(),
            'node_number' => $nodeId,
            'callsign' => $nodeConfig['callsign'] ?? 'Unknown',
            'description' => $nodeConfig['description'] ?? 'Unknown',
            'location' => $nodeConfig['location'] ?? 'Unknown',
            'status' => $statusInfo['status'] ?? 'unknown',
            'last_heard' => $statusInfo['last_heard'] ?? date('Y-m-d H:i:s'),
            'connected_nodes' => $statusInfo['connected_nodes'] ?? '',
            'cos_keyed' => $statusInfo['cos_keyed'] ?? '0',
            'tx_keyed' => $statusInfo['tx_keyed'] ?? '0',
            'cpu_temp' => $statusInfo['cpu_temp'] ?? 'N/A',
            'alert' => $statusInfo['alert'] ?? null,
            'wx' => $statusInfo['wx'] ?? null,
            'disk' => $statusInfo['disk'] ?? null,
            'is_online' => $statusInfo['is_online'] ?? false,
            'is_keyed' => $statusInfo['is_keyed'] ?? false,
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

        // Get node configuration
        try {
            $nodeConfig = $this->configService->getNodeConfig($nodeId);
            if (!$nodeConfig) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Node configuration not found'
                ]));
                
                return $response
                    ->withStatus(404)
                    ->withHeader('Content-Type', 'application/json');
            }
        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Node configuration not found: ' . $e->getMessage()
            ]));
            
            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        // Get real node status from AMI
        $ami = $this->connectToAmi($nodeConfig, $nodeId);
        if (!$ami) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Failed to connect to AMI'
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }

        // Get node status information
        $statusInfo = $this->getNodeStatusInfo($ami, $nodeId);
        
        $status = [
            'node_id' => $nodeId,
            'status' => $statusInfo['status'] ?? 'unknown',
            'last_heard' => $statusInfo['last_heard'] ?? date('Y-m-d H:i:s'),
            'connected_nodes' => $statusInfo['connected_nodes'] ?? '',
            'cos_keyed' => $statusInfo['cos_keyed'] ?? '0',
            'tx_keyed' => $statusInfo['tx_keyed'] ?? '0',
            'cpu_temp' => $statusInfo['cpu_temp'] ?? 'N/A',
            'alert' => $statusInfo['alert'] ?? null,
            'wx' => $statusInfo['wx'] ?? null,
            'disk' => $statusInfo['disk'] ?? null,
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
     * Handle RPT Stats requests
     * Supports both external AllStar Link stats and local AMI stats
     */
    public function rptstats(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $node = $data['node'] ?? null;
        $localnode = $data['localnode'] ?? null;

        // Check authentication
        $currentUser = $this->getCurrentUser();
        if (!$this->hasUserPermission($currentUser, 'RSTATUSER')) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'You are not authorized to access RPT statistics.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // External node stats - redirect to AllStar Link
        if ($node && is_numeric($node) && $node > 0) {
            $externalUrl = "http://stats.allstarlink.org/stats/$node";
            $response->getBody()->write(json_encode([
                'success' => true,
                'type' => 'external',
                'url' => $externalUrl,
                'message' => "Redirecting to external AllStar Link stats for node $node"
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Local node stats via AMI
        if ($localnode && is_numeric($localnode) && $localnode > 0) {
            try {
                $nodeConfig = $this->loadNodeConfig($currentUser, (string)$localnode);
                if (!$nodeConfig) {
                    $response->getBody()->write(json_encode(['success' => false, 'message' => "Configuration for local node $localnode not found."]));
                    return $response->withHeader('Content-Type', 'application/json');
                }

                $fp = $this->connectToAmi($nodeConfig, (string)$localnode);
                if (!$fp) {
                    $response->getBody()->write(json_encode(['success' => false, 'message' => "Could not connect to Asterisk Manager for node $localnode."]));
                    return $response->withHeader('Content-Type', 'application/json');
                }

                $statsResult = $this->executeRptStatsCommand($fp, (string)$localnode);
                \SimpleAmiClient::logoff($fp);

                $response->getBody()->write(json_encode([
                    'success' => true,
                    'type' => 'local',
                    'data' => [
                        'node' => $localnode,
                        'stats' => $statsResult,
                        'timestamp' => date('c')
                    ],
                    'message' => "RPT stats retrieved successfully for node $localnode"
                ]));
                return $response->withHeader('Content-Type', 'application/json');

            } catch (\Exception $e) {
                $this->logger->error('Failed to retrieve RPT stats', ['local_node' => $localnode, 'error' => $e->getMessage()]);
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to retrieve RPT stats: ' . $e->getMessage()]));
                return $response->withHeader('Content-Type', 'application/json');
            }
        }

        // No valid parameters
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'No valid node specified. Please provide a node or localnode parameter.']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Execute RPT stats command via AMI
     */
    private function executeRptStatsCommand(mixed $fp, string $localNode): string
    {
        $commandOutput = \SimpleAmiClient::command($fp, "rpt stats $localNode");
        
        if ($commandOutput !== false && !empty(trim($commandOutput))) {
            return trim($commandOutput);
        } else {
            return "<NONE_OR_EMPTY_STATS>";
        }
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
     * Get user configuration from INI file
     */
    private function getUserConfig(?string $user): array
    {
        // Include necessary files
        require_once __DIR__ . '/../../../includes/common.inc';
        
        // Determine INI file path
        $iniFile = null;
        if ($user) {
            // Try user-specific INI file
            $userIniFile = '/var/www/html/supermon-ng/user_files/' . $user . '.ini';
            if (file_exists($userIniFile)) {
                $iniFile = $userIniFile;
            }
        }
        
        // Fallback to allmon.ini
        if (!$iniFile) {
            $allmonIni = '/var/www/html/supermon-ng/user_files/allmon.ini';
            if (file_exists($allmonIni)) {
                $iniFile = $allmonIni;
            }
        }
        
        // Fallback to default user INI file
        if (!$iniFile) {
            $defaultIni = '/var/www/html/supermon-ng/user_files/default-allmon.ini';
            if (file_exists($defaultIni)) {
                $iniFile = $defaultIni;
            }
        }
        
        if (!$iniFile || !file_exists($iniFile)) {
            return [];
        }
        
        $config = parse_ini_file($iniFile, true);
        return $config ?: [];
    }

    /**
     * Check if user has permission
     */
    private function hasUserPermission(?string $user, string $permission): bool
    {
        // If no user is provided, use default permissions for unauthenticated users
        if (!$user) {
            $defaultPermissions = [
                'CONNECTUSER' => true,
                'DISCUSER' => true,
                'MONUSER' => true,
                'LMONUSER' => true,
                'DTMFUSER' => false,
                'ASTLKUSER' => true,
                'RSTATUSER' => true,
                'BUBLUSER' => true,
                'FAVUSER' => true,
                'CTRLUSER' => false,
                'CFGEDUSER' => true,
                'ASTRELUSER' => false,
                'ASTSTRUSER' => false,
                'ASTSTPUSER' => false,
                'FSTRESUSER' => false,
                'RBTUSER' => false,
                'UPDUSER' => true,
                'HWTOUSER' => true,
                'WIKIUSER' => true,
                'CSTATUSER' => true,
                'ASTATUSER' => true,
                'EXNUSER' => true,
                'NINFUSER' => true,
                'ACTNUSER' => true,
                'ALLNUSER' => true,
                'DBTUSER' => true,
                'GPIOUSER' => false,
                'LLOGUSER' => true,
                'ASTLUSER' => true,
                'CLOGUSER' => true,
                'IRLPLOGUSER' => true,
                'WLOGUSER' => true,
                'WERRUSER' => true,
                'BANUSER' => false,
                'SYSINFUSER' => true,
                'SUSBUSER' => false
            ];
            
            return $defaultPermissions[$permission] ?? false;
        }

        // For authenticated users, check against authusers.inc
        $authFile = 'user_files/authusers.inc';
        
        if (!file_exists($authFile)) {
            // If no auth file exists, grant all permissions
            return true;
        }

        // Include the auth file to get permission arrays
        include $authFile;
        
        // Check if the permission array exists and user is in it
        if (isset($$permission) && is_array($$permission)) {
            return in_array($user, $$permission, true);
        }
        
        return false;
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
            $userIniFile = '/var/www/html/supermon-ng/user_files/' . $user . '.ini';
            if (file_exists($userIniFile)) {
                $iniFile = $userIniFile;
            }
        }
        
        // Fallback to allmon.ini
        if (!$iniFile) {
            $allmonIni = '/var/www/html/supermon-ng/user_files/allmon.ini';
            if (file_exists($allmonIni)) {
                $iniFile = $allmonIni;
            }
        }
        
        // Fallback to default user INI file
        if (!$iniFile) {
            $defaultIni = '/var/www/html/supermon-ng/user_files/default-allmon.ini';
            if (file_exists($defaultIni)) {
                $iniFile = $defaultIni;
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

    /**
     * Get CPU and system statistics
     */
    public function cpustats(Request $request, Response $response, array $args): Response
    {
        $currentUser = $this->getCurrentUser();
        if (!$this->hasUserPermission($currentUser, 'CSTATUSER')) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'You are not authorized to access CPU statistics.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        try {
            $statsContent = $this->executeCpuStatsCommands();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'content' => $statsContent,
                    'timestamp' => date('c')
                ],
                'message' => 'CPU statistics retrieved successfully'
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve CPU statistics', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to retrieve CPU statistics: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Execute CPU statistics commands
     * 
     * Note: When moving to server, replace with:
     * - '/usr/bin/uname -a' -> 'export TERM=vt100 && sudo /usr/bin/ssinfo - '
     * - '/usr/bin/free -h' -> '/usr/bin/din'
     * - '/usr/bin/uptime' -> (remove or keep)
     */
    private function executeCpuStatsCommands(): string
    {
        $commands = [
            '/usr/bin/date',
            '/usr/bin/uname -a',  // Server: 'export TERM=vt100 && sudo /usr/bin/ssinfo - '
            '/usr/bin/ip a',
            '/usr/bin/df -hT',
            '/usr/bin/free -h',   // Server: '/usr/bin/din'
            '/usr/bin/uptime',
            '/usr/bin/top -b -n1'
        ];

        $content = '';
        foreach ($commands as $command) {
            $output = $this->executeCpuStatsCommand($command);
            $content .= $this->formatCpuStatsOutput($command, $output);
        }

        return $content;
    }

    /**
     * Get database contents for a specific node
     */
    public function database(Request $request, Response $response, array $args): Response
    {
        require_once __DIR__ . '/../../../includes/amifunctions.inc';
        
        $currentUser = $this->getCurrentUser();
        if (!$this->hasUserPermission($currentUser, 'DBTUSER')) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'You are not authorized to access database contents.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $data = $request->getParsedBody();
        $localnode = $data['localnode'] ?? null;

        if (empty($localnode)) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Local node parameter is required.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        try {
            // Get user configuration
            $config = $this->getUserConfig($currentUser);
            if (!isset($config[$localnode])) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Node configuration not found.']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $amiConfig = $config[$localnode];
            if (!isset($amiConfig['host']) || !isset($amiConfig['user']) || !isset($amiConfig['passwd'])) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Invalid AMI configuration for node.']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Connect to AMI and retrieve database
            $databaseOutput = $this->retrieveDatabaseFromAmi($amiConfig);
            $dbEntries = $this->processDatabaseOutput($databaseOutput);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'localnode' => $localnode,
                    'entries' => $dbEntries,
                    'raw_output' => $databaseOutput,
                    'timestamp' => date('c')
                ],
                'message' => 'Database contents retrieved successfully'
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve database contents', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to retrieve database contents: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Retrieve database contents from AMI
     */
    private function retrieveDatabaseFromAmi(array $amiConfig): string
    {
        $fp = \SimpleAmiClient::connect($amiConfig['host']);
        if ($fp === false) {
            throw new \Exception('Could not connect to Asterisk Manager');
        }

        if (\SimpleAmiClient::login($fp, $amiConfig['user'], $amiConfig['passwd']) === false) {
            \SimpleAmiClient::logoff($fp);
            throw new \Exception('Could not authenticate with Asterisk Manager');
        }

        $databaseOutput = \SimpleAmiClient::command($fp, "database show");
        \SimpleAmiClient::logoff($fp);
        
        return $databaseOutput ?: '';
    }

    /**
     * Process raw database output into structured entries
     */
    private function processDatabaseOutput(string $databaseOutput): array
    {
        $processedOutput = trim($databaseOutput);
        $dbEntries = [];

        if (!empty($processedOutput)) {
            $processedOutput = preg_replace('/^Output: /m', '', $processedOutput);
            $lines = explode("\n", trim($processedOutput));

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $dbEntries[] = [
                        'key' => trim($parts[0]),
                        'value' => trim($parts[1])
                    ];
                }
            }
        }

        return $dbEntries;
    }

    /**
     * Get external nodes file contents
     */
    public function extnodes(Request $request, Response $response, array $args): Response
    {
        $currentUser = $this->getCurrentUser();
        if (!$this->hasUserPermission($currentUser, 'EXNUSER')) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'You are not authorized to access external nodes.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        try {
            // Get the external nodes file path from configuration
            $extnodesPath = $this->config['extnodes'] ?? '/etc/asterisk/rpt_extnodes';
            
            if (!file_exists($extnodesPath)) {
                $response->getBody()->write(json_encode([
                    'success' => false, 
                    'message' => 'External nodes file not found: ' . $extnodesPath
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $fileContent = file_get_contents($extnodesPath);
            
            if ($fileContent === false) {
                $response->getBody()->write(json_encode([
                    'success' => false, 
                    'message' => 'Failed to read external nodes file'
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'file_path' => $extnodesPath,
                    'content' => $fileContent,
                    'timestamp' => date('c')
                ],
                'message' => 'External nodes file retrieved successfully'
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve external nodes file', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to retrieve external nodes file: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Execute a single CPU statistics command
     */
    private function executeCpuStatsCommand(string $command): string
    {
        $output = shell_exec($command . ' 2>&1');
        return $output ?: '';
    }

    /**
     * Format CPU statistics command output
     */
    private function formatCpuStatsOutput(string $command, string $output): string
    {
        $formatted = "Command: " . htmlspecialchars($command) . "\n";
        $formatted .= "-----------------------------------------------------------------\n";
        $formatted .= htmlspecialchars($output);
        $formatted .= "\n\n";
        return $formatted;
    }

    public function fastrestart(Request $request, Response $response, array $args): Response
    {
        $currentUser = $this->getCurrentUser();
        if (!$this->hasUserPermission($currentUser, 'FSTRESUSER')) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'You are not authorized to perform fast restart operations.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $data = $request->getParsedBody();
        $localnode = $data['localnode'] ?? null;

        if (empty($localnode)) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Local node parameter is required.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        try {
            // Get user configuration
            $config = $this->loadNodeConfig($currentUser, $localnode);
            if (!$config) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Node configuration not found.']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $amiConfig = $config;
            if (!isset($amiConfig['host']) || !isset($amiConfig['user']) || !isset($amiConfig['passwd'])) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Invalid AMI configuration for node.']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Connect to AMI and execute restart
            $restartResult = $this->executeFastRestart($amiConfig);

            if ($restartResult['success']) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'data' => [
                        'localnode' => $localnode,
                        'message' => $restartResult['message'],
                        'timestamp' => date('c')
                    ],
                    'message' => 'Fast restart command executed successfully'
                ]));
            } else {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => $restartResult['message']
                ]));
            }
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->logger->error('Failed to execute fast restart', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to execute fast restart: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    private function executeFastRestart(array $amiConfig): array
    {
        $fp = \SimpleAmiClient::connect($amiConfig['host']);
        if ($fp === false) {
            return [
                'success' => false,
                'message' => 'Could not connect to Asterisk Manager on host ' . $amiConfig['host']
            ];
        }

        if (\SimpleAmiClient::login($fp, $amiConfig['user'], $amiConfig['passwd']) === false) {
            \SimpleAmiClient::logoff($fp);
            return [
                'success' => false,
                'message' => 'Could not authenticate with Asterisk Manager'
            ];
        }

        $restartOutput = \SimpleAmiClient::command($fp, "restart now");
        \SimpleAmiClient::logoff($fp);

        if ($restartOutput === false) {
            return [
                'success' => false,
                'message' => 'Failed to send restart command to Asterisk'
            ];
        }

        return [
            'success' => true,
            'message' => 'Fast restart command sent successfully. Asterisk is restarting now.',
            'output' => $restartOutput
        ];
    }

    public function irlplog(Request $request, Response $response, array $args): Response
    {
        $currentUser = $this->getCurrentUser();
        if (!$this->hasUserPermission($currentUser, 'IRLPLOGUSER')) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'You are not authorized to view IRLP logs.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        try {
            // Get IRLP log file path from configuration
            $irlpLogPath = $this->config['irlp_log'] ?? '/home/irlp/log/messages';

            if (!file_exists($irlpLogPath)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'IRLP log file not found: ' . $irlpLogPath
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $fileContent = file_get_contents($irlpLogPath);

            if ($fileContent === false) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Failed to read IRLP log file'
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'file_path' => $irlpLogPath,
                    'content' => $fileContent,
                    'timestamp' => date('c')
                ],
                'message' => 'IRLP log file retrieved successfully'
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve IRLP log file', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to retrieve IRLP log file: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    public function linuxlog(Request $request, Response $response, array $args): Response
    {
        $currentUser = $this->getCurrentUser();
        if (!$this->hasUserPermission($currentUser, 'LLOGUSER')) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'You are not authorized to view Linux system logs.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        try {
            // Hardcode the values from common.inc since global variables aren't working
            $SUDO = "export TERM=vt100 && /usr/bin/sudo";
            $JOURNALCTL = "/usr/bin/journalctl";
            $SED = "/usr/bin/sed";
            
            // Use sudo as required for journalctl access with sed filtering to remove sudo lines
            $command = "$SUDO $JOURNALCTL --no-pager --since \"1 day ago\" | $SED -e \"/sudo/ d\"";

            // Execute the command using exec for better error handling
            $output = [];
            $returnCode = 0;
            exec($command . " 2>&1", $output, $returnCode);
            $outputString = implode("\n", $output);
            


            if (empty($outputString)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Failed to execute Linux log command - no output (return code: ' . $returnCode . ')'
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'command' => $command,
                    'content' => $outputString,
                    'timestamp' => date('c'),
                    'description' => 'System Log (journalctl, last 24 hours)'
                ],
                'message' => 'Linux system log retrieved successfully'
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve Linux system log', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to retrieve Linux system log: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    public function banallow(Request $request, Response $response, array $args): Response
    {
        try {
            $currentUser = $this->getCurrentUser();
            $this->logger->info('Ban/Allow request started', ['user' => $currentUser]);
            
            if (!$this->hasUserPermission($currentUser, 'BANUSER')) {
                $this->logger->info('Ban/Allow permission denied', ['user' => $currentUser]);
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'You are not authorized to manage node access control lists.']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $data = $request->getParsedBody();
            $localnode = $data['localnode'] ?? null;
            $this->logger->info('Ban/Allow request data', ['localnode' => $localnode, 'data' => $data]);

            if (empty($localnode) || !preg_match('/^\d+$/', $localnode)) {
                $this->logger->error('Invalid localnode parameter', ['localnode' => $localnode]);
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Valid local node parameter is required.']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // For now, return a simple test response to see if the endpoint works
            $this->logger->info('Ban/Allow returning test response');
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'localnode' => $localnode,
                    'allowlist' => ['entries' => []],
                    'denylist' => ['entries' => []],
                    'timestamp' => date('c')
                ],
                'message' => 'Test response - Ban/Allow endpoint working'
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->logger->error('Ban/Allow exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Ban/Allow error: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    public function banallowAction(Request $request, Response $response, array $args): Response
    {
        $currentUser = $this->getCurrentUser();
        if (!$this->hasUserPermission($currentUser, 'BANUSER')) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'You are not authorized to manage node access control lists.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $data = $request->getParsedBody();
        $localnode = $data['localnode'] ?? null;
        $node = $data['node'] ?? null;
        $listtype = $data['listtype'] ?? null;
        $deleteadd = $data['deleteadd'] ?? null;
        $comment = $data['comment'] ?? '';

        // Validate inputs
        if (empty($localnode) || !preg_match('/^\d+$/', $localnode)) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Valid local node parameter is required.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        if (empty($node) || !preg_match('/^\d+$/', $node)) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Valid node number is required.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        if (!in_array($listtype, ['allowlist', 'denylist'])) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Invalid list type.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        if (!in_array($deleteadd, ['add', 'delete'])) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Invalid action.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        try {
            // Get user configuration
            $config = $this->getUserConfig($currentUser);
            if (!isset($config[$localnode])) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Node configuration not found.']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $amiConfig = $config[$localnode];
            if (!isset($amiConfig['host']) || !isset($amiConfig['user']) || !isset($amiConfig['passwd'])) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Invalid AMI configuration for node.']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Execute the ban/allow action
            $result = $this->executeBanAllowAction($amiConfig, $localnode, $node, $listtype, $deleteadd, $comment);

            if ($result['success']) {
                // Get updated lists
                $allowlistData = $this->getBanAllowList($amiConfig, 'allowlist', $localnode);
                $denylistData = $this->getBanAllowList($amiConfig, 'denylist', $localnode);

                $response->getBody()->write(json_encode([
                    'success' => true,
                    'data' => [
                        'localnode' => $localnode,
                        'node' => $node,
                        'action' => $deleteadd,
                        'listtype' => $listtype,
                        'comment' => $comment,
                        'allowlist' => $allowlistData,
                        'denylist' => $denylistData,
                        'timestamp' => date('c')
                    ],
                    'message' => $result['message']
                ]));
            } else {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => $result['message']
                ]));
            }
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->logger->error('Failed to execute ban/allow action', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to execute ban/allow action: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    private function getBanAllowList(array $amiConfig, string $listType, string $localnode): array
    {
        try {
            // Include AMI functions
            require_once __DIR__ . '/../../../includes/amifunctions.inc';
            
            $this->logger->info('Connecting to AMI for Ban/Allow', [
                'host' => $amiConfig['host'],
                'user' => $amiConfig['user'],
                'listType' => $listType,
                'localnode' => $localnode
            ]);
            
            $fp = \SimpleAmiClient::connect($amiConfig['host']);
            if ($fp === false) {
                $this->logger->error('AMI connection failed', [
                    'host' => $amiConfig['host'],
                    'listType' => $listType
                ]);
                return ['error' => 'Could not connect to Asterisk Manager'];
            }

            if (\SimpleAmiClient::login($fp, $amiConfig['user'], $amiConfig['passwd']) === false) {
                \SimpleAmiClient::logoff($fp);
                $this->logger->error('AMI authentication failed', [
                    'host' => $amiConfig['host'],
                    'user' => $amiConfig['user'],
                    'listType' => $listType
                ]);
                return ['error' => 'Could not authenticate with Asterisk Manager'];
            }

        $dbFamily = $listType . "/" . $localnode;
        $rawData = \SimpleAmiClient::command($fp, "database show " . $dbFamily);
        \SimpleAmiClient::logoff($fp);

        if ($rawData === false || trim($rawData) === "") {
            return ['entries' => []];
        }

        $lines = explode("\n", $rawData);
        $entries = [];

        foreach ($lines as $line) {
            $processedLine = trim($line);
            if (strpos($processedLine, "Output: ") === 0) {
                $processedLine = substr($processedLine, strlen("Output: "));
                $processedLine = trim($processedLine);
            }
            
            if (preg_match('/^\d+\s+results found\.?$/i', $processedLine)) {
                continue;
            }

            if (trim($processedLine) !== "") {
                $parts = explode(' ', $processedLine, 2);
                $entries[] = [
                    'node' => $parts[0] ?? '',
                    'comment' => $parts[1] ?? ''
                ];
            }
        }

        return ['entries' => $entries];
        } catch (\Exception $e) {
            $this->logger->error('Ban/Allow list error', [
                'error' => $e->getMessage(),
                'listType' => $listType,
                'localnode' => $localnode
            ]);
            return ['error' => 'Failed to retrieve ' . $listType . ': ' . $e->getMessage()];
        }
    }

    private function executeBanAllowAction(array $amiConfig, string $localnode, string $node, string $listtype, string $deleteadd, string $comment): array
    {
        // Include AMI functions
        require_once __DIR__ . '/../../../includes/amifunctions.inc';
        
        $fp = \SimpleAmiClient::connect($amiConfig['host']);
        if ($fp === false) {
            return [
                'success' => false,
                'message' => 'Could not connect to Asterisk Manager'
            ];
        }

        if (\SimpleAmiClient::login($fp, $amiConfig['user'], $amiConfig['passwd']) === false) {
            \SimpleAmiClient::logoff($fp);
            return [
                'success' => false,
                'message' => 'Could not authenticate with Asterisk Manager'
            ];
        }

        $dbName = $listtype . "/" . $localnode;
        $cmdAction = ($deleteadd == "add") ? "put" : "del";
        
        $amiCmdString = "database $cmdAction $dbName $node";
        if ($cmdAction == "put" && !empty($comment)) {
            $amiCmdString .= " \"" . addslashes($comment) . "\"";
        }

        $result = \SimpleAmiClient::command($fp, $amiCmdString);
        \SimpleAmiClient::logoff($fp);

        if ($result !== false) {
            $actionText = $deleteadd === 'add' ? 'added to' : 'removed from';
            return [
                'success' => true,
                'message' => "Node $node successfully $actionText the $listtype."
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to execute command. Check Asterisk logs for details.'
            ];
        }
    }

    public function pigpio(Request $request, Response $response, array $args): Response
    {
        $currentUser = $this->getCurrentUser();
        if (!$this->hasUserPermission($currentUser, 'GPIOUSER')) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'You are not authorized to control GPIO pins.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        try {
            // Get GPIO status
            $gpioStatus = $this->getGPIOStatus();

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'gpio_status' => $gpioStatus,
                    'timestamp' => date('c')
                ],
                'message' => 'GPIO status retrieved successfully'
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve GPIO status', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to retrieve GPIO status: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    public function pigpioAction(Request $request, Response $response, array $args): Response
    {
        $currentUser = $this->getCurrentUser();
        if (!$this->hasUserPermission($currentUser, 'GPIOUSER')) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'You are not authorized to control GPIO pins.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $data = $request->getParsedBody();
        $pin = $data['pin'] ?? null;
        $state = $data['state'] ?? null;

        // Validate inputs
        if (!is_numeric($pin) || $pin < 0 || $pin > 40) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Invalid GPIO pin number. Must be 0-40.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        if (!in_array($state, ['input', 'output', 'up', 'down', '0', '1'])) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Invalid GPIO state.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        try {
            // Execute GPIO command
            $result = $this->executeGPIOCommand($pin, $state);

            if ($result['success']) {
                // Get updated GPIO status
                $gpioStatus = $this->getGPIOStatus();

                $response->getBody()->write(json_encode([
                    'success' => true,
                    'data' => [
                        'pin' => $pin,
                        'state' => $state,
                        'gpio_status' => $gpioStatus,
                        'timestamp' => date('c')
                    ],
                    'message' => $result['message']
                ]));
            } else {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => $result['message']
                ]));
            }
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->logger->error('Failed to execute GPIO command', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to execute GPIO command: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    private function getGPIOStatus(): array
    {
        $command = "gpio readall";
        $output = shell_exec($command . " 2>/dev/null");

        if ($output === null) {
            return ['error' => 'Could not read GPIO status. Make sure gpio command is available.'];
        }

        $lines = explode("\n", trim($output));
        $pins = [];

        foreach ($lines as $line) {
            if (preg_match('/^(\d+)\s+(\w+)\s+(\w+)$/', trim($line), $matches)) {
                $pins[] = [
                    'pin' => $matches[1],
                    'mode' => $matches[2],
                    'value' => $matches[3]
                ];
            }
        }

        return ['pins' => $pins];
    }

    private function executeGPIOCommand(int $pin, string $state): array
    {
        $escapedPin = escapeshellarg($pin);
        $escapedState = escapeshellarg($state);

        switch ($state) {
            case 'input':
                $command = "gpio mode {$escapedPin} input";
                break;
            case 'up':
                $command = "gpio mode {$escapedPin} up";
                break;
            case 'down':
                $command = "gpio mode {$escapedPin} down";
                break;
            case 'output':
                $command = "gpio mode {$escapedPin} output";
                break;
            case '0':
            case '1':
                $command = "gpio write {$escapedPin} {$escapedState}";
                break;
            default:
                return [
                    'success' => false,
                    'message' => 'Invalid GPIO state.'
                ];
        }

        $output = shell_exec($command . " 2>/dev/null");
        $returnVar = 0;
        exec($command . " 2>/dev/null", $output, $returnVar);

        if ($returnVar === 0) {
            $actionText = match($state) {
                'input' => 'set to input mode',
                'output' => 'set to output mode',
                'up' => 'set to pull-up mode',
                'down' => 'set to pull-down mode',
                '0' => 'set to LOW (0)',
                '1' => 'set to HIGH (1)',
                default => 'configured'
            };
            
            return [
                'success' => true,
                'message' => "GPIO pin {$pin} successfully {$actionText}."
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to execute GPIO command. Check if gpio command is available and you have proper permissions.'
            ];
        }
    }

    public function reboot(Request $request, Response $response, array $args): Response
    {
        $currentUser = $this->getCurrentUser();
        if (!$this->hasUserPermission($currentUser, 'RBTUSER')) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'You are not authorized to reboot the server.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        try {
            // Send success response immediately
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'message' => 'Server reboot command initiated. The system will reboot shortly.',
                    'timestamp' => date('c')
                ],
                'message' => 'Server reboot initiated successfully'
            ]));
            
            // Execute reboot command in background after response is sent
            $this->executeRebootInBackground();
            
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->logger->error('Failed to execute reboot command', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to execute reboot command: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    private function executeRebootInBackground(): void
    {
        // Execute reboot command in background to avoid blocking the response
        $command = "sudo /usr/sbin/reboot > /dev/null 2>&1 &";
        exec($command);
        
        // Log the reboot attempt
        $this->logger->info('Server reboot command executed in background');
    }

    public function smlog(Request $request, Response $response, array $args): Response
    {
        $currentUser = $this->getCurrentUser();
        if (!$this->hasUserPermission($currentUser, 'SMLOGUSER')) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'You are not authorized to view Supermon logs.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        try {
            // Get log file path and content
            $result = $this->getSmlogContent();

            if ($result['success']) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'data' => [
                        'log_file_path' => $result['log_file_path'],
                        'log_content' => $result['log_content'],
                        'timestamp' => date('c')
                    ],
                    'message' => 'Supermon log content retrieved successfully'
                ]));
            } else {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => $result['message']
                ]));
            }
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve Supermon log content', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to retrieve Supermon log content: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    private function getSmlogContent(): array
    {
        // Include necessary files to get the log file path
        require_once __DIR__ . '/../../../includes/common.inc';
        
        // Get the log file path from common.inc
        $log_file_path = '/tmp/SMLOG.txt'; // Default path from common.inc
        
        if (!file_exists($log_file_path)) {
            return [
                'success' => false,
                'message' => 'Supermon log file does not exist: ' . $log_file_path
            ];
        }

        // Read log file content
        $log_content = @file($log_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($log_content === false) {
            return [
                'success' => false,
                'message' => 'Could not read the Supermon log file: ' . $log_file_path
            ];
        }

        // Process log content (reverse chronological order)
        $reversed_log_content = array_reverse($log_content);
        
        // Escape HTML characters for safe display
        $escaped_log_content = array_map(function($line) {
            return htmlspecialchars(trim($line), ENT_QUOTES, 'UTF-8');
        }, $reversed_log_content);

        return [
            'success' => true,
            'log_file_path' => $log_file_path,
            'log_content' => $escaped_log_content,
            'message' => 'Supermon log content retrieved successfully'
        ];
    }

    public function stats(Request $request, Response $response, array $args): Response
    {
        $currentUser = $this->getCurrentUser();
        if (!$this->hasUserPermission($currentUser, 'ASTATUSER')) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'You are not authorized to view AllStar statistics.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        try {
            $body = $request->getParsedBody() ?? [];
            $localnode = $body['localnode'] ?? null;

            if (empty($localnode)) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Local node parameter is required.']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Get user configuration
            $config = $this->getUserConfig($currentUser);
            
            if (!isset($config[$localnode])) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => "Node $localnode is not in your configuration file."]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Retrieve statistics data
            $statsData = $this->getAllStarStats($config[$localnode]);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $statsData,
                'message' => 'AllStar statistics retrieved successfully'
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve AllStar statistics', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to retrieve AllStar statistics: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    private function getAllStarStats(array $nodeConfig): array
    {
        // Connect to AMI
        $fp = \SimpleAmiClient::connect($nodeConfig['host']);
        if ($fp === false) {
            throw new \Exception("Could not connect to Asterisk Manager on host {$nodeConfig['host']}");
        }

        try {
            $loginSuccess = \SimpleAmiClient::login($fp, $nodeConfig['user'], $nodeConfig['passwd']);
            if ($loginSuccess === false) {
                throw new \Exception("Could not login to Asterisk Manager using user {$nodeConfig['user']}");
            }

            // Get all statistics sections
            $stats = [
                'header' => $this->getStatsHeader(),
                'all_nodes' => $this->getAllNodesStats($fp),
                'peers' => $this->getPeersStats($fp),
                'channels' => $this->getChannelsStats($fp),
                'netstats' => $this->getNetstatsStats($fp)
            ];

            return $stats;

        } finally {
            \SimpleAmiClient::logoff($fp);
        }
    }

    private function getStatsHeader(): array
    {
        $host = trim(shell_exec('hostname | awk -F. \'{printf ("%s", $1);}\' 2>/dev/null') ?: 'Unknown');
        $date = trim(shell_exec('date 2>/dev/null') ?: 'Unknown');
        
        return [
            'host' => $host,
            'date' => $date
        ];
    }

    private function getAllNodesStats($fp): array
    {
        $nodes_output = \SimpleAmiClient::command($fp, "rpt localnodes");
        
        if ($nodes_output === false) {
            return ['error' => 'Failed to execute rpt localnodes command'];
        }

        if (trim($nodes_output) === '') {
            return ['message' => 'No local nodes reported by Asterisk'];
        }

        $nodelist = explode("\n", $nodes_output);
        $nodes = [];

        foreach ($nodelist as $line) {
            $node_num_raw_candidate = $line;
            $prefix = "Output: ";
            if (strpos($line, $prefix) === 0) {
                $node_num_raw_candidate = substr($line, strlen($prefix));
            }

            $node_num_raw = trim($node_num_raw_candidate);
            if (empty($node_num_raw) || $node_num_raw === "Node" || $node_num_raw === "----" || !ctype_digit($node_num_raw)) {
                continue;
            }

            $node_num = $node_num_raw;
            $xnode_info = \SimpleAmiClient::command($fp, "rpt xnode $node_num");
            $lstats_info = \SimpleAmiClient::command($fp, "rpt lstats $node_num");

            $nodes[] = [
                'node_number' => $node_num,
                'xnode_info' => $this->cleanAmiOutput($xnode_info),
                'lstats_info' => $this->cleanAmiOutput($lstats_info)
            ];
        }

        return ['nodes' => $nodes];
    }

    private function getPeersStats($fp): array
    {
        $peers_output = \SimpleAmiClient::command($fp, "iax2 show peers");
        
        if ($peers_output === false) {
            return ['error' => 'Failed to retrieve IAX2 peer info'];
        }

        if (trim($peers_output) === '') {
            return ['message' => 'No IAX2 peers reported'];
        }

        return ['peers' => $this->cleanAmiOutput($peers_output)];
    }

    private function getChannelsStats($fp): array
    {
        $channels_output = \SimpleAmiClient::command($fp, "iax2 show channels");
        
        if ($channels_output === false) {
            return ['error' => 'Failed to retrieve IAX2 channel info'];
        }

        if (trim($channels_output) === '') {
            return ['message' => 'No IAX2 channels reported'];
        }

        return ['channels' => $this->cleanAmiOutput($channels_output)];
    }

    private function getNetstatsStats($fp): array
    {
        $netstats_output = \SimpleAmiClient::command($fp, "iax2 show netstats");
        
        if ($netstats_output === false) {
            return ['error' => 'Failed to retrieve IAX2 netstats info'];
        }

        if (trim($netstats_output) === '') {
            return ['message' => 'No IAX2 netstats reported'];
        }

        return ['netstats' => $this->cleanAmiOutput($netstats_output)];
    }

    private function cleanAmiOutput($raw_output): string
    {
        if ($raw_output === null || trim($raw_output) === '') {
            return $raw_output;
        }
        
        $lines = explode("\n", $raw_output);
        $cleaned_lines = [];
        $prefix = "Output: ";
        
        foreach ($lines as $line) {
            if (strpos($line, $prefix) === 0) {
                $cleaned_lines[] = substr($line, strlen($prefix));
            } else {
                $cleaned_lines[] = $line;
            }
        }
        
        return implode("\n", $cleaned_lines);
    }

    public function webacclog(Request $request, Response $response, array $args): Response
    {
        $currentUser = $this->getCurrentUser();
        if (!$this->hasUserPermission($currentUser, 'WLOGUSER')) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'You are not authorized to view web access logs.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        try {
            // Get log file path and content
            $result = $this->getWebacclogContent();

            if ($result['success']) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'data' => [
                        'log_file_path' => $result['log_file_path'],
                        'log_lines' => $result['log_lines'],
                        'timestamp' => date('c')
                    ],
                    'message' => 'Web access log content retrieved successfully'
                ]));
            } else {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => $result['message']
                ]));
            }
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve web access log content', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to retrieve web access log content: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    private function getWebacclogContent(): array
    {
        // Include necessary files to get the log file path
        require_once __DIR__ . '/../../../includes/common.inc';
        
        // Get the log file path from common.inc
        global $WEB_ACCESS_LOG;
        $log_file_path = $WEB_ACCESS_LOG ?? '/var/log/apache2/access.log';
        

        
        // Validate file path for security
        if (!$this->isSafeWebLogPath($log_file_path)) {
            return [
                'success' => false,
                'message' => 'Invalid or unsafe log file path: ' . $log_file_path
            ];
        }

        // Read log file content with fallback methods
        $logLines = $this->readWebacclogContent($log_file_path);
        
        if ($logLines === false) {
            return [
                'success' => false,
                'message' => 'Could not read the web access log file: ' . $log_file_path
            ];
        }

        return [
            'success' => true,
            'log_file_path' => $log_file_path,
            'log_lines' => $logLines,
            'message' => 'Web access log content retrieved successfully'
        ];
    }

    private function isSafeWebLogPath(string $path): bool
    {
        // Only allow specific log files
        $allowed_paths = [
            '/var/log/apache2/access.log',
            '/var/log/httpd/access_log',
            '/var/log/nginx/access.log'
        ];
        
        return in_array($path, $allowed_paths) && file_exists($path);
    }

    private function readWebacclogContent(string $file): array|false
    {
        $logLines = false;



        // Try to read the file directly first
        if (is_readable($file)) {
            $logContent = file_get_contents($file);
            if ($logContent !== false) {
                $logLines = explode("\n", $logContent);
                $logLines = array_slice($logLines, -100); // Show last 100 lines
                $logLines = array_reverse($logLines); // Most recent first
                $logLines = array_filter($logLines); // Remove empty lines
                $logLines = array_values($logLines); // Reindex array to 0, 1, 2, etc.
                

            } else {
                $logLines = [];

            }
        } else {
            // Fallback to sudo if direct read fails
            $logContent = $this->safeExec("sudo", "tail -100 " . escapeshellarg($file));
            if ($logContent !== false) {
                $logLines = explode("\n", $logContent);
                $logLines = array_reverse($logLines); // Most recent first
                $logLines = array_filter($logLines); // Remove empty lines
                $logLines = array_values($logLines); // Reindex array to 0, 1, 2, etc.
                

            } else {
                $logLines = [];

            }
        }

        return $logLines;
    }

    private function safeExec(string $command, string $args = ''): string|false
    {
        $escaped_command = escapeshellcmd($command);
        if (!empty($args)) {
            $full_command = "{$escaped_command} {$args}";
        } else {
            $full_command = $escaped_command;
        }
        
        $output = [];
        $return_var = 0;
        exec($full_command . " 2>/dev/null", $output, $return_var);
        
        if ($return_var !== 0) {
            return false;
        }
        
        return implode("\n", $output);
    }

    public function weberrlog(Request $request, Response $response, array $args): Response
    {
        $currentUser = $this->getCurrentUser();
        if (!$this->hasUserPermission($currentUser, 'WERRUSER')) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'You are not authorized to view web error logs.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        try {
            // Get log file path and content
            $result = $this->getWeberrlogContent();

            if ($result['success']) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'data' => [
                        'log_file_path' => $result['log_file_path'],
                        'parsed_data' => $result['parsed_data'],
                        'timestamp' => date('c')
                    ],
                    'message' => 'Web error log content retrieved successfully'
                ]));
            } else {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => $result['message']
                ]));
            }
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve web error log content', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to retrieve web error log content: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    private function getWeberrlogContent(): array
    {
        // Include necessary files to get the log file path
        require_once __DIR__ . '/../../../includes/common.inc';
        
        // Get the log file path from common.inc
        $log_file_path = $WEB_ERROR_LOG ?? '/var/log/apache2/error.log';

        // Validate log file exists and is readable
        $fileStatus = $this->validateWeberrlogFile($log_file_path);
        if (!$fileStatus['readable']) {
            return [
                'success' => false,
                'message' => $fileStatus['error']
            ];
        }

        // Read log file content
        $lines = $this->readWeberrlogContent($log_file_path);
        if ($lines === false) {
            return [
                'success' => false,
                'message' => 'Could not read the web error log file: ' . $log_file_path
            ];
        }

        if (count($lines) === 0) {
            return [
                'success' => false,
                'message' => 'Web error log file is empty: ' . $log_file_path
            ];
        }

        // Parse log lines
        $parsedData = $this->parseWeberrlogLines($lines);

        return [
            'success' => true,
            'log_file_path' => $log_file_path,
            'parsed_data' => $parsedData,
            'message' => 'Web error log content retrieved successfully'
        ];
    }

    private function validateWeberrlogFile(string $logFilePath): array
    {
        $status = [
            'exists' => false,
            'readable' => false,
            'error' => ''
        ];
        
        if (!file_exists($logFilePath)) {
            $status['error'] = "Log file not found: " . $logFilePath;
            return $status;
        }
        
        $status['exists'] = true;
        
        if (!is_readable($logFilePath)) {
            $status['error'] = "Log file not readable: " . $logFilePath;
            return $status;
        }
        
        $status['readable'] = true;
        return $status;
    }

    private function readWeberrlogContent(string $logFilePath): array|false
    {
        $lines = file($logFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            return false;
        }
        
        return $lines;
    }

    private function parseWeberrlogLines(array $lines): array
    {
        $logRegex = '/^\[(?<timestamp>.*?)\] (?:\[(?<module>[^:]+):(?<level_m>[^\]]+)\]|\[(?<level>[^\]]+)\])(?: \[pid (?<pid>\d+)(?::tid (?<tid>\d+))?\])?(?: \[client (?<client>.*?)\])? (?<message>.*)$/';
        
        $headers = ['Line', 'Timestamp', 'Level', 'Client', 'Details'];
        $rows = [];
        
        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            $matched = preg_match($logRegex, $line, $matches);
            
            if ($matched) {
                $timestamp = $matches['timestamp'] ?? '';
                $level_raw_captured = $matches['level_m'] ?? ($matches['level'] ?? '');
                $level_raw = strtolower(trim($level_raw_captured));
                $level_display = strtoupper($level_raw);
                $client = $matches['client'] ?? '';
                $message = $matches['message'] ?? '';
                
                $rows[] = [
                    $lineNumber,
                    $timestamp,
                    $level_display,
                    $client,
                    $message
                ];
            } else {
                $rows[] = [
                    $lineNumber,
                    'N/A',
                    'N/A',
                    'N/A',
                    $line
                ];
            }
        }
        
        return [
            'headers' => $headers,
            'rows' => $rows
        ];
    }

    /**
     * Get voter status for a node
     */
    public function voterStatus(Request $request, Response $response, array $args): Response
    {
        require_once __DIR__ . '/../../../includes/amifunctions.inc';
        require_once __DIR__ . '/../../../includes/nodeinfo.inc';
        
        try {
            $node = $request->getQueryParams()['node'] ?? null;
            
            if (!$node) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Node parameter is required'
                ]));
                
                return $response
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json');
            }

            // Validate node format
            if (!preg_match('/^\d+(,\d+)*$/', $node)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Invalid node format'
                ]));
                
                return $response
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json');
            }

            // Get node configuration
            $nodeConfig = $this->configService->getNodeConfig($node);
            if (!$nodeConfig) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Node configuration not found'
                ]));
                
                return $response
                    ->withStatus(404)
                    ->withHeader('Content-Type', 'application/json');
            }

            // Connect to AMI
            $ami = $this->connectToAmi($nodeConfig, $node);
            if (!$ami) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Failed to connect to AMI'
                ]));
                
                return $response
                    ->withStatus(500)
                    ->withHeader('Content-Type', 'application/json');
            }

            // Get voter status
            $actionID = 'voter' . preg_replace('/[^a-zA-Z0-9]/', '', $node) . mt_rand(1000, 9999);
            $voterResponse = $this->getVoterStatus($ami, $actionID);
            
            if ($voterResponse === false) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Failed to get voter status'
                ]));
                
                return $response
                    ->withStatus(500)
                    ->withHeader('Content-Type', 'application/json');
            }

            // Parse voter response
            list($nodesData, $votedData) = $this->parseVoterResponse($voterResponse);
            
            // Format HTML for the node
            $html = $this->formatVoterHTML($node, $nodesData, $votedData, $nodeConfig);

            $response->getBody()->write(json_encode([
                'success' => true,
                'html' => $html,
                'spinner' => '*'
            ]));

            return $response->withHeader('Content-Type', 'application/json');

        } catch (Exception $e) {
            error_log("Voter status error: " . $e->getMessage());
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Internal server error'
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Get voter status via AMI
     */
    private function getVoterStatus($ami, $actionID): string|false
    {
        $result = \SimpleAmiClient::command($ami, "VoterStatus");
        
        if ($result !== false) {
            return $result;
        }
        
        return false;
    }

    /**
     * Parse voter response from AMI
     */
    private function parseVoterResponse(string $response): array
    {
        $lines = explode("\n", $response);
        $parsedNodesData = [];
        $parsedVotedData = [];
        $currentNodeContext = null;
        $currentClientData = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = explode(": ", $line, 2);
            if (count($parts) < 2) continue;
            
            list($key, $value) = $parts;

            switch ($key) {
                case 'Node':
                    if ($currentNodeContext && !empty($currentClientData) && isset($currentClientData['name'])) {
                        $parsedNodesData[$currentNodeContext][$currentClientData['name']] = $currentClientData;
                    }
                    $currentNodeContext = $value;
                    $currentClientData = [];
                    if (!isset($parsedNodesData[$currentNodeContext])) {
                        $parsedNodesData[$currentNodeContext] = [];
                    }
                    break;

                case 'Client':
                    if ($currentNodeContext && !empty($currentClientData) && isset($currentClientData['name'])) {
                        $parsedNodesData[$currentNodeContext][$currentClientData['name']] = $currentClientData;
                    }
                    // Check for the "Mix" suffix BEFORE cleaning it
                    $isMix = (strpos($value, ' Mix') !== false);
                    
                    // Clean all known suffixes from the client name
                    $cleanName = preg_replace('/(\sMaster\sActiveMaster|\sLocal\sLocal|\sMix)$/', '', $value);
                    
                    // Store the clean name and the isMix flag
                    $currentClientData = ['name' => $cleanName, 'isMix' => $isMix, 'rssi' => 'N/A', 'ip' => 'N/A'];
                    break;
                    
                case 'RSSI':
                    if (isset($currentClientData['name'])) {
                        $currentClientData['rssi'] = $value;
                    }
                    break;
                    
                case 'IP':
                    if (isset($currentClientData['name'])) {
                        $currentClientData['ip'] = $value;
                    }
                    break;

                case 'Voted':
                    if ($currentNodeContext) {
                        $parsedVotedData[$currentNodeContext] = $value;
                    }
                    break;
            }
        }

        if ($currentNodeContext && !empty($currentClientData) && isset($currentClientData['name'])) {
            $parsedNodesData[$currentNodeContext][$currentClientData['name']] = $currentClientData;
        }

        return [$parsedNodesData, $parsedVotedData];
    }

    /**
     * Format voter HTML for display
     */
    private function formatVoterHTML(string $nodeNum, array $nodesData, array $votedData, array $currentConfig): string
    {
        $message = '';
        $info = $this->getAstInfo($nodeNum); 

        if (!empty($currentConfig['hideNodeURL'])) {
            $message .= "<table class='rtcm'><tr><th colspan=2><i>   Node $nodeNum - $info   </i></th></tr>";
        } else {
            $nodeURL = "http://stats.allstarlink.org/nodeinfo.cgi?node=$nodeNum";
            $message .= "<table class='rtcm'><tr><th colspan=2><i>   Node <a href=\"$nodeURL\" target=\"_blank\">$nodeNum</a> - $info   </i></th></tr>";
        }
        $message .= "<tr><th>Client</th><th>RSSI</th></tr>";

        if (!isset($nodesData[$nodeNum]) || empty($nodesData[$nodeNum])) {
            $message .= "<tr><td><div class='voter-no-clients'>&nbsp;No clients&nbsp;</div></td>";
            $message .= "<td><div class='voter-empty-bar'>&nbsp;</div></td></tr>";
        } else {
            $clients = $nodesData[$nodeNum];
            $votedClient = isset($votedData[$nodeNum]) && $votedData[$nodeNum] !== 'none' ? $votedData[$nodeNum] : null;

            foreach($clients as $clientName => $client) {
                $rssi = isset($client['rssi']) ? (int)$client['rssi'] : 0;
                $bar_width_px = round(($rssi / 255) * 300); 
                $bar_width_px = ($rssi == 0) ? 3 : max(1, $bar_width_px);
                
                $barcolor = "#0099FF"; 
                $textcolor = 'white'; 
                
                if ($votedClient && $clientName === $votedClient) {
                    $barcolor = 'greenyellow'; 
                    $textcolor = 'black';
                } elseif (isset($client['isMix']) && $client['isMix'] === true) {
                    $barcolor = 'cyan'; 
                    $textcolor = 'black';
                }

                $message .= "<tr>";
                $message .= "<td><div>" . htmlspecialchars($clientName) . "</div></td>";
                $message .= "<td><div class='text'> <div class='barbox_a'>";
                $message .= "<div class='bar' style='width: " . $bar_width_px . "px; background-color: $barcolor; color: $textcolor'>" . $rssi . "</div>";
                $message .= "</div></td></tr>";
            }
        }
        $message .= "<tr><td colspan=2> </td></tr>";
        $message .= "</table><br/>";
        
        return str_replace(["\r", "\n"], '', $message);
    }

    /**
     * Get Asterisk info for a node
     */
    private function getAstInfo(string $nodeNum): string
    {
        // Get real Asterisk info from AMI
        try {
            $nodeConfig = $this->configService->getNodeConfig($nodeNum);
            if (!$nodeConfig) {
                return "Unknown Node";
            }

            $ami = $this->connectToAmi($nodeConfig, $nodeNum);
            if (!$ami) {
                return "Voter Node";
            }

            // Get Asterisk version info
            $versionResponse = \SimpleAmiClient::command($ami, "Command", ["Command" => "asterisk -V"]);
            if ($versionResponse && strpos($versionResponse, 'Asterisk') !== false) {
                return "Asterisk Node";
            }

            return "Voter Node";
        } catch (Exception $e) {
            return "Voter Node";
        }
    }

    /**
     * Get node status information from AMI
     */
    private function getNodeStatusInfo($ami, string $nodeId): array
    {
        $statusInfo = [
            'status' => 'unknown',
            'last_heard' => date('Y-m-d H:i:s'),
            'connected_nodes' => '',
            'cos_keyed' => '0',
            'tx_keyed' => '0',
            'cpu_temp' => 'N/A',
            'alert' => null,
            'wx' => null,
            'disk' => null,
            'is_online' => false,
            'is_keyed' => false
        ];

        try {
            // Get node status
            $statusResponse = \SimpleAmiClient::command($ami, "Command", ["Command" => "asterisk -rx 'rpt status $nodeId'"]);
            if ($statusResponse) {
                if (strpos($statusResponse, 'Online') !== false) {
                    $statusInfo['status'] = 'online';
                    $statusInfo['is_online'] = true;
                } elseif (strpos($statusResponse, 'Offline') !== false) {
                    $statusInfo['status'] = 'offline';
                    $statusInfo['is_online'] = false;
                }
            }

            // Get connected nodes
            $connectedResponse = \SimpleAmiClient::command($ami, "Command", ["Command" => "asterisk -rx 'rpt nodes $nodeId'"]);
            if ($connectedResponse) {
                $lines = explode("\n", $connectedResponse);
                $connectedNodes = [];
                foreach ($lines as $line) {
                    if (preg_match('/Node\s+(\d+)/', $line, $matches)) {
                        $connectedNodes[] = $matches[1];
                    }
                }
                $statusInfo['connected_nodes'] = implode(',', $connectedNodes);
            }

            // Get key status
            $keyResponse = \SimpleAmiClient::command($ami, "Command", ["Command" => "asterisk -rx 'rpt keyed $nodeId'"]);
            if ($keyResponse) {
                if (strpos($keyResponse, 'Keyed') !== false) {
                    $statusInfo['is_keyed'] = true;
                    $statusInfo['tx_keyed'] = '1';
                }
            }

        } catch (Exception $e) {
            $this->logger->error("Error getting node status info", ['node_id' => $nodeId, 'error' => $e->getMessage()]);
        }

        return $statusInfo;
    }
}
