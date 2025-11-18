<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use SupermonNg\Domain\Entities\Node;
use SupermonNg\Services\AllStarConfigService;
use SupermonNg\Services\AstdbCacheService;
use SupermonNg\Services\IncludeManagerService;
use Ramsey\Uuid\Uuid;
use Exception;

class NodeController
{
    private LoggerInterface $logger;
    private AllStarConfigService $configService;
    private AstdbCacheService $astdbService;
    private IncludeManagerService $includeService;
    
    public function __construct(
        LoggerInterface $logger, 
        AllStarConfigService $configService, 
        AstdbCacheService $astdbService,
        IncludeManagerService $includeService
    ) {
        $this->logger = $logger;
        $this->configService = $configService;
        $this->astdbService = $astdbService;
        $this->includeService = $includeService;
    }

    public function list(Request $request, Response $response): Response
    {
        $this->logger->info('Fetching node list');
        
        try {
            // Get current user (or null if not logged in)
            $currentUser = $this->getCurrentUser();
            
            // Get available nodes from AllStar configuration
            $availableNodes = $this->configService->getAvailableNodes($currentUser);
            
            // Load ASTDB for node information
            $astdb = $this->astdbService->getAstdb();
            
            // Convert to the expected format with basic data (AMI data is expensive)
            $nodes = [];
            foreach ($availableNodes as $node) {
                $nodeId = $node['id'];
                
                // Get node info from ASTDB
                $nodeInfo = $this->getNodeInfoFromAstdb((string)$nodeId, $astdb);
                
                // Use ASTDB data for callsign and location
                $callsign = $nodeInfo['callsign'] ?? 'Unknown';
                $location = $nodeInfo['location'] ?? 'Unknown';
                $description = $nodeInfo['description'] ?? 'Unknown';
                
                // Header uses only the location field (not callsign + description + location)
                // This matches the original behavior where header shows just the node name/description
                $info = $location ?: $description ?: "Node $nodeId";
                
                // Skip AMI data for list view - it's too expensive
                // AMI data is fetched separately via getAmiStatus endpoint
                $amiData = [
                    'node' => $nodeId,
                    'info' => $info,
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
                
                // Determine if node is online based on AMI data
                $isOnline = ($amiData['status'] ?? 'offline') === 'online';
                
                // Process connected nodes
                $connectedNodes = [];
                if (!empty($amiData['remote_nodes'])) {
                    foreach ($amiData['remote_nodes'] as $remoteNode) {
                        $connectedNodes[] = [
                            'node' => $remoteNode['node'] ?? 'unknown',
                            'info' => $remoteNode['info'] ?? 'unknown',
                            'ip' => $remoteNode['ip'] ?? null,
                            'last_keyed' => date('Y-m-d H:i:s'),
                            'link' => $remoteNode['link'] ?? 'IAX',
                            'direction' => $remoteNode['direction'] ?? 'unknown',
                        ];
                    }
                }
                
                $nodes[] = [
                    'id' => $nodeId,
                    'node_number' => $nodeId,
                    'callsign' => $callsign,
                    'description' => $description, // ASTDB description field
                    'location' => $location,
                    'info' => $info, // Header uses callsign + description + location (skip empty parts)
                    'status' => $amiData['status'] ?? 'offline',
                    'last_heard' => $isOnline ? date('Y-m-d H:i:s') : null,
                    'connected_nodes' => $connectedNodes,
                    'cos_keyed' => $amiData['cos_keyed'] ?? '0',
                    'tx_keyed' => $amiData['tx_keyed'] ?? '0',
                    'cpu_temp' => $amiData['cpu_temp'] ?? null,
                    'alert' => $amiData['ALERT'] ?? null,
                    'wx' => $amiData['WX'] ?? null,
                    'disk' => $amiData['DISK'] ?? null,
                    'is_online' => $isOnline,
                    'is_keyed' => (($amiData['cos_keyed'] ?? '0') === '1' || ($amiData['tx_keyed'] ?? '0') === '1'),
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
        
        // Use lazy loading for single node info (Phase 5 optimization)
        $nodeInfo = $this->astdbService->getSingleNodeInfo((string)$nodeId);
        if ($nodeInfo) {
            // Convert to expected format
            $nodeInfo = [
                'description' => trim($nodeInfo['callsign'] . ' ' . $nodeInfo['description'] . ' ' . $nodeInfo['location']),
                'callsign' => $nodeInfo['callsign'],
                'frequency' => $nodeInfo['description'],
                'location' => $nodeInfo['location']
            ];
        } else {
            $nodeInfo = [
                'description' => 'Node not in database',
                'callsign' => 'Unknown',
                'frequency' => 'Unknown',
                'location' => 'Unknown'
            ];
        }

        // Get real node status using the existing lsnod command that works
        $lsnodData = $this->executeLsnodCommand($nodeId);
        $parsedData = $lsnodData['parsed_data'] ?? [];
        
        // Convert lsnod data to the expected format
        $statusInfo = [
            'status' => 'unknown',
            'last_heard' => date('Y-m-d H:i:s'),
            'connected_nodes' => null,
            'cos_keyed' => '0',
            'tx_keyed' => '0',
            'cpu_temp' => 'N/A',
            'alert' => null,
            'wx' => null,
            'disk' => null,
            'is_online' => false,
            'is_keyed' => false
        ];
        
        // Update with real data from lsnod
        if (!empty($parsedData['main_node'])) {
            $mainNode = $parsedData['main_node'];
            $statusInfo['status'] = $mainNode['status'] ?? 'unknown';
            $statusInfo['is_online'] = ($mainNode['status'] ?? '') === 'online';
            $statusInfo['cos_keyed'] = $mainNode['cos_keyed'] ?? '0';
            $statusInfo['tx_keyed'] = $mainNode['tx_keyed'] ?? '0';
            $statusInfo['cpu_temp'] = $mainNode['cpu_temp'] ?? 'N/A';
            $statusInfo['alert'] = $mainNode['alert'] ?? null;
            $statusInfo['wx'] = $mainNode['wx'] ?? null;
            $statusInfo['disk'] = $mainNode['disk'] ?? null;
        }
        
        // Get connected nodes using lazy loading (Phase 5 optimization)
        if (!empty($parsedData['nodes'])) {
            $connectedNodes = [];
            $connectedNodeIds = [];
            
            // Collect all connected node IDs first
            foreach ($parsedData['nodes'] as $connectedNode) {
                $connectedNodeId = $connectedNode['node_number'] ?? 'unknown';
                if ($connectedNodeId !== 'unknown') {
                    $connectedNodeIds[] = (string)$connectedNodeId;
                }
            }
            
            // Use lazy loading for multiple nodes
            $connectedNodeInfoMap = [];
            if (!empty($connectedNodeIds)) {
                $connectedNodeInfoMap = $this->astdbService->getMultipleNodeInfo($connectedNodeIds);
            }
            
            foreach ($parsedData['nodes'] as $connectedNode) {
                $connectedNodeId = $connectedNode['node_number'] ?? 'unknown';
                $connectedNodeInfo = $connectedNodeInfoMap[$connectedNodeId] ?? null;
                
                $info = 'unknown';
                if ($connectedNodeInfo) {
                    $info = trim($connectedNodeInfo['callsign'] . ' ' . $connectedNodeInfo['description'] . ' ' . $connectedNodeInfo['location']);
                }
                
                $connectedNodes[] = [
                    'node' => $connectedNodeId,
                    'info' => $info,
                    'ip' => null,
                    'last_keyed' => date('Y-m-d H:i:s'),
                    'link' => 'IAX',
                    'direction' => 'unknown',
                    'elapsed' => 'unknown',
                    'mode' => 'duplex',
                    'keyed' => '0'
                ];
            }
            $statusInfo['connected_nodes'] = $connectedNodes;
        }
        
        $node = [
            'id' => Uuid::uuid4()->toString(),
            'node_number' => $nodeId,
            'callsign' => $nodeInfo['callsign'] ?? 'Unknown',
            'description' => $nodeInfo['frequency'] ?? 'Unknown',
            'location' => $nodeInfo['location'] ?? 'Unknown',
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
            
            // Return connection to pool
            $this->returnAmiConnection($fp, $nodeConfig);

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
                $this->returnAmiConnection($fp, $nodeConfig);

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

        // CSRF token validation is handled by middleware
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
                $this->returnAmiConnection($fp, $nodeConfig);
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
            
            // Return connection to pool
            $this->returnAmiConnection($fp, $nodeConfig);

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
                $nodeConfig = $this->configService->getNodeConfig($nodeId, $currentUser);
                
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
        // Include the AMI functions using optimized service
        $this->includeService->includeAmiFunctions();
        $this->includeService->includeNodeInfo();
        // Helpers functionality now available as modern services
        
        $host = $nodeConfig['host'];
        $user = $nodeConfig['user'] ?? '';
        $password = $nodeConfig['passwd'] ?? '';
        
        // Try to connect to AMI using connection pooling
        $this->logger->debug("Attempting AMI connection", ['host' => $host, 'node_id' => $nodeId]);
        $socket = \SimpleAmiClient::getConnection($host, $user, $password);
        if ($socket === false) {
            $this->logger->warning("AMI connection failed", ['host' => $host, 'node_id' => $nodeId]);
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
        
        try {
            // Use lazy loading for single node info (Phase 5 optimization)
            $nodeInfo = $this->astdbService->getSingleNodeInfo($nodeId);
            $info = 'Node ' . $nodeId;
            if ($nodeInfo) {
                $info = trim($nodeInfo['callsign'] . ' ' . $nodeInfo['description'] . ' ' . $nodeInfo['location']);
            }
            
            // Get complete node data using XStat and SawStat
            $nodeData = $this->getNodeData($socket, $nodeId);
            
            // Return connection to pool
            \SimpleAmiClient::returnConnection($socket, $host, $user);
            
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
            // Return connection to pool
            \SimpleAmiClient::returnConnection($socket, $host, $user);
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
        } finally {
            // Return connection to pool
            \SimpleAmiClient::returnConnection($socket, $host, $user);
        }
    }
    
    /**
     * Get node data using XStat and SawStat AMI commands (like the original system)
     */
    private function getNodeData($socket, string $nodeId): array
    {
        // Include the necessary files for the original parsing logic using optimized service
        $this->includeService->includeAmiFunctions();
        $this->includeService->includeNodeInfo();
        // server-functions.inc functionality moved directly into this controller
        
        // Define the ECHOLINK_NODE_THRESHOLD constant if not defined
        if (!defined('ECHOLINK_NODE_THRESHOLD')) {
            define('ECHOLINK_NODE_THRESHOLD', 3000000);
        }
        
        // Initialize global variables that the original functions expect
        global $astdb, $elnk_cache, $irlp_cache;
        if (!isset($astdb)) $astdb = []; // We'll use lazy loading instead
        if (!isset($elnk_cache)) $elnk_cache = [];
        if (!isset($irlp_cache)) $irlp_cache = [];
        
        // Use modernized AMI commands (replacing legacy getNode function)
        $nodeData = $this->getNodeViaAmi($socket, $nodeId);
        
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
        
        // Collect all connected node IDs first for lazy loading
        $connectedNodeIds = [];
        foreach ($nodeData as $key => $nodeInfo) {
            if ($key != 1 && is_array($nodeInfo)) {
                $connectedNodeId = $nodeInfo['node'] ?? $key;
                if ($connectedNodeId !== 'unknown') {
                    $connectedNodeIds[] = (string)$connectedNodeId;
                }
            }
        }
        
        // Use lazy loading for multiple nodes (Phase 5 optimization)
        $connectedNodeInfoMap = [];
        if (!empty($connectedNodeIds)) {
            $connectedNodeInfoMap = $this->astdbService->getMultipleNodeInfo($connectedNodeIds);
        }
        
        // Extract remote nodes (all keys except 1)
        foreach ($nodeData as $key => $nodeInfo) {
            if ($key != 1 && is_array($nodeInfo)) {
                $connectedNodeId = $nodeInfo['node'] ?? $key;
                $connectedNodeInfo = $connectedNodeInfoMap[$connectedNodeId] ?? null;
                
                $info = 'Node ' . $connectedNodeId;
                if ($connectedNodeInfo) {
                    $info = trim($connectedNodeInfo['callsign'] . ' ' . $connectedNodeInfo['description'] . ' ' . $connectedNodeInfo['location']);
                }
                
                $remoteNodes[] = [
                    'node' => $connectedNodeId,
                    'info' => $info,
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
     * Get node data via AMI (modernized from legacy getNode function)
     */
    private function getNodeViaAmi($fp, string $node): array
    {
        // Check if socket is still valid
        if (!is_resource($fp) || get_resource_type($fp) !== 'stream') {
            error_log("getNodeViaAmi: Socket is not a valid resource for node $node");
            return [];
        }
        
        // Check socket status
        $socketStatus = stream_get_meta_data($fp);
        if ($socketStatus['eof'] || $socketStatus['timed_out']) {
            error_log("getNodeViaAmi: Socket is EOF or timed out for node $node");
            return [];
        }
        
        // Execute XStat command using action() method (Allmon3 style)
        $rptStatus = \SimpleAmiClient::action($fp, "RptStatus", [
            "COMMAND" => "XStat",
            "NODE" => $node
        ]);
        
        if ($rptStatus === false) {
            error_log("getNodeViaAmi: XStat action FAILED for node $node");
            $rptStatus = '';
        }

        // Execute SawStat command using action() method (Allmon3 style)
        $sawStatus = \SimpleAmiClient::action($fp, "RptStatus", [
            "COMMAND" => "SawStat",
            "NODE" => $node
        ]);
        
        if ($sawStatus === false) {
            error_log("getNodeViaAmi: SawStat action FAILED for node $node");
            $sawStatus = '';
        }
        
        return $this->parseNodeAmiData($fp, $node, $rptStatus, $sawStatus);
    }

    /**
     * Parse node data from AMI responses (modernized from legacy parseNode function)
     */
    private function parseNodeAmiData($fp, string $queriedNode, string $rptStatus, string $sawStatus): array
    {
        $curNodes = [];
        $parsedVars = [];
        
        // Parse XStat response
        if (!empty($rptStatus)) {
            $lines = explode("\n", $rptStatus);
            foreach ($lines as $line) {
                $line = trim($line);
                // Parse Var: lines
                if (strpos($line, 'Var: ') === 0) {
                    $varLine = substr($line, 5); // Remove 'Var: ' prefix
                    if (strpos($varLine, '=') !== false) {
                        list($key, $value) = explode('=', $varLine, 2);
                        $parsedVars[trim($key)] = trim($value);
                    }
                }
            }
        }
        
        // Parse XStat response for connected nodes (where the actual connection data is)
        $conns = [];
        
        if (!empty($rptStatus)) {
            $lines = explode("\n", $rptStatus);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Parse connection data - format: "Conn: 546054 73.6.70.88 6 OUT 31:49:24 ESTABLISHED"
                if (strpos($line, 'Conn: ') === 0) {
                    $connLine = substr($line, 6); // Remove 'Conn: ' prefix
                    $data = preg_split('/\s+/', $connLine);
                    if (!empty($data[0])) {
                        $conns[] = $data;
                    }
                }
            }
        }
        
        // Parse SawStat response for keyed timing data
        $keyups = [];
        
        if (!empty($sawStatus)) {
            $lines = explode("\n", $sawStatus);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Parse keyed data - format: "Conn: 546054 0 -1 -1"
                if (strpos($line, 'Conn: ') === 0) {
                    $connLine = substr($line, 6); // Remove 'Conn: ' prefix
                    $data = preg_split('/\s+/', $connLine);
                    if (isset($data[0]) && isset($data[1]) && isset($data[2]) && isset($data[3])) {
                        $keyups[$data[0]] = [
                            'node' => $data[0],
                            'isKeyed' => $data[1],
                            'keyed' => $data[2],
                            'unkeyed' => $data[3]
                        ];
                    }
                }
            }
        }
        
        // Parse modes from LinkedNodes in XStat response
        $modes = [];
        $allLinkedNodes = [];
        if (!empty($rptStatus) && preg_match("/LinkedNodes: (.*)/", $rptStatus, $matches)) {
            $longRangeLinks = preg_split("/, /", trim($matches[1]));
            foreach ($longRangeLinks as $line) {
                if (!empty($line)) {
                    $n_val = substr($line, 1);
                    $connectionType = substr($line, 0, 1);
                    $modes[$n_val]['mode'] = $connectionType;
                    
                    // Add to all linked nodes list (filter out private nodes < 2000)
                    if (is_numeric($n_val) && intval($n_val) >= 2000) {
                        $allLinkedNodes[] = $n_val;
                    }
                }
            }
        }
        
        // Build node data structure
        $mainNodeCosKeyed = ($parsedVars['RPT_RXKEYED'] ?? '0') === '1' ? 1 : 0;
        $mainNodeTxKeyed = ($parsedVars['RPT_TXKEYED'] ?? '0') === '1' ? 1 : 0;
        $mainNodeCpuTemp = $parsedVars['cpu_temp'] ?? null;
        $mainNodeCpuUp = $parsedVars['cpu_up'] ?? null;
        $mainNodeCpuLoad = $parsedVars['cpu_load'] ?? null;
        $mainNodeALERT = $parsedVars['ALERT'] ?? null;
        $mainNodeWX = $parsedVars['WX'] ?? null;
        $mainNodeDISK = $parsedVars['DISK'] ?? null;

        // Process connected nodes - first add direct connections
        if (count($conns) > 0) {
            foreach ($conns as $connData) {
                $n = $connData[0];
                if (empty($n)) continue;

                // XStat format: [node, ip, port, direction, elapsed, status]
                $ip = $connData[1] ?? '';
                $port = $connData[2] ?? '';
                $direction = $connData[3] ?? '';
                $elapsed = $connData[4] ?? '';
                $status = $connData[5] ?? '';

                $isEcholink = (is_numeric($n) && $n > ECHOLINK_NODE_THRESHOLD && empty($ip));

                $curNodes[$n]['node'] = $n;
                $curNodes[$n]['info'] = \getAstInfo($fp, $n);
                $curNodes[$n]['ip'] = $isEcholink ? "" : $ip;
                $curNodes[$n]['direction'] = $isEcholink ? ($connData[2] ?? '') : $direction;
                $curNodes[$n]['elapsed'] = $isEcholink ? ($connData[3] ?? '') : $elapsed;
                $curNodes[$n]['link'] = $isEcholink ? ($connData[4] ?? 'UNKNOWN') : $status;
                
                // Handle Echolink connection status based on mode
                if ($isEcholink) {
                    if (isset($modes[$n]['mode'])) {
                        $curNodes[$n]['link'] = ($modes[$n]['mode'] == 'C') ? "CONNECTING" : "ESTABLISHED";
                    }
                }
                
                $curNodes[$n]['keyed'] = 'n/a';
                $curNodes[$n]['last_keyed'] = '-1';
                
                // Use keyed timing data from SawStat if available
                if (isset($keyups[$n])) {
                    $curNodes[$n]['keyed'] = ($keyups[$n]['isKeyed'] == 1) ? 'yes' : 'no';
                    $curNodes[$n]['last_keyed'] = $keyups[$n]['keyed'];
                }
                
                // Set mode from LinkedNodes data if available, otherwise use default
                if (isset($modes[$n])) {
                    $curNodes[$n]['mode'] = $modes[$n]['mode'];
                } else {
                    $curNodes[$n]['mode'] = $isEcholink ? 'Echolink' : 'Allstar';
                }
            }
        }
        
        // Add local node stats
        $localStatsKey = 1;
        if (!isset($curNodes[$localStatsKey])) {
            $curNodes[$localStatsKey] = [];
        }
        
        $curNodes[$localStatsKey]['node'] = $curNodes[$localStatsKey]['node'] ?? $queriedNode;
        $curNodes[$localStatsKey]['info'] = $curNodes[$localStatsKey]['info'] ?? \getAstInfo($fp, $queriedNode);
        $curNodes[$localStatsKey]['cos_keyed'] = $mainNodeCosKeyed;
        $curNodes[$localStatsKey]['tx_keyed'] = $mainNodeTxKeyed;
        $curNodes[$localStatsKey]['cpu_temp'] = $mainNodeCpuTemp;
        $curNodes[$localStatsKey]['cpu_up'] = $mainNodeCpuUp;
        $curNodes[$localStatsKey]['cpu_load'] = $mainNodeCpuLoad;
        $curNodes[$localStatsKey]['ALERT'] = $mainNodeALERT;
        $curNodes[$localStatsKey]['WX'] = $mainNodeWX;
        $curNodes[$localStatsKey]['DISK'] = $mainNodeDISK;
        
        return $curNodes;
    }
    
    /**
     * Load ASTDB data using optimized caching service
     * 
     * Returns identical data structure as original loadAstDb() method
     * but with request-level caching for improved performance.
     */
    private function loadAstDb(string $filename): array
    {
        // Use the optimized AstdbCacheService for better performance
        return $this->astdbService->getAstdb();
    }

    /**
     * Get WebSocket port configuration for a node
     * Ports are assigned incrementally: basePort (8105) + node index
     */
    public function getWebSocketPort(Request $request, Response $response, array $args): Response
    {
        $nodeId = $args['id'] ?? null;
        
        if (!$nodeId) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Node ID required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        try {
            $currentUser = $this->getCurrentUser();
            $basePort = 8105; // Base port for WebSocket servers
            
            // Get all available nodes to determine index
            $nodes = $this->configService->getAvailableNodes($currentUser);
            
            // Find node index
            $nodeIndex = null;
            foreach ($nodes as $index => $node) {
                if ($node['id'] == $nodeId) {
                    $nodeIndex = $index;
                    break;
                }
            }
            
            if ($nodeIndex === null) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Node not found'
                ]));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }
            
            // Calculate port: basePort + index
            $port = $basePort + $nodeIndex;
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'node' => $nodeId,
                'port' => $port,
                'ws_url' => "ws://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/supermon-ng/ws/{$nodeId}"
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (Exception $e) {
            $this->logger->error("Error getting WebSocket port", [
                'node_id' => $nodeId,
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Internal server error'
            ]));
            
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Get WebSocket port configuration for all nodes
     */
    public function getAllWebSocketPorts(Request $request, Response $response): Response
    {
        try {
            $currentUser = $this->getCurrentUser();
            $basePort = 8105; // Base port for WebSocket servers
            
            // Get all available nodes
            $nodes = $this->configService->getAvailableNodes($currentUser);
            
            $portConfig = [];
            foreach ($nodes as $index => $node) {
                $nodeId = $node['id'];
                $port = $basePort + $index;
                
                $portConfig[$nodeId] = [
                    'node' => $nodeId,
                    'port' => $port,
                    'ws_url' => "ws://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/supermon-ng/ws/{$nodeId}"
                ];
            }
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'base_port' => $basePort,
                'nodes' => $portConfig
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (Exception $e) {
            $this->logger->error("Error getting all WebSocket ports", [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Internal server error'
            ]));
            
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
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
     * Get user configuration from INI file using AllStarConfigService
     */
    private function getUserConfig(?string $user): array
    {
        try {
            // Use the injected AllStarConfigService to get the correct INI file path and load config
            $availableNodes = $this->configService->getAvailableNodes($user);
            
            // Convert the available nodes format to the expected config format
            $config = [];
            foreach ($availableNodes as $node) {
                $nodeId = $node['id'];
                $config[$nodeId] = [
                    'host' => $node['host'],
                    'user' => $node['user'],
                    'passwd' => '', // Password is not returned by getAvailableNodes for security
                    'system' => $node['system'],
                    'menu' => $node['menu'],
                    'hideNodeURL' => $node['hideNodeURL']
                ];
                
                // Get the full node config with password
                try {
                    $fullNodeConfig = $this->configService->getNodeConfig((string)$nodeId, $user);
                    $config[$nodeId]['passwd'] = $fullNodeConfig['passwd'] ?? '';
                } catch (\Exception $e) {
                    // If we can't get the full config, continue without password
                    $this->logger->warning('Could not get full node config', [
                        'node_id' => $nodeId,
                        'user' => $user,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            return $config;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get user config via AllStarConfigService', [
                'user' => $user,
                'error' => $e->getMessage()
            ]);
            return [];
        }
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
     * Load node configuration from INI file using AllStarConfigService
     */
    private function loadNodeConfig(?string $user, string $localNode): ?array
    {
        try {
            // Use AllStarConfigService to get the correct node configuration
            $nodeConfig = $this->configService->getNodeConfig($localNode, $user);
            
            // Debug logging
            $this->logger->info('Loading node config via AllStarConfigService', [
                'user' => $user,
                'localNode' => $localNode,
                'hasConfig' => !empty($nodeConfig)
            ]);
            
            return $nodeConfig;
        } catch (\Exception $e) {
            $this->logger->error('Failed to load node config via AllStarConfigService', [
                'user' => $user,
                'localNode' => $localNode,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Connect to AMI using connection pooling
     */
    private function connectToAmi(array $nodeConfig, string $localNode): mixed
    {
        // Include AMI functions
        require_once __DIR__ . '/../../../includes/amifunctions.inc';
        
        // Use connection pooling instead of direct connect/login
        return \SimpleAmiClient::getConnection(
            $nodeConfig['host'], 
            $nodeConfig['user'], 
            $nodeConfig['passwd']
        );
    }

    /**
     * Return AMI connection to pool
     */
    private function returnAmiConnection($fp, array $nodeConfig): void
    {
        if ($fp && isset($nodeConfig['host']) && isset($nodeConfig['user'])) {
            \SimpleAmiClient::returnConnection($fp, $nodeConfig['host'], $nodeConfig['user']);
        }
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
     * Execute CPU statistics commands using custom scripts from user_files/sbin
     */
    private function executeCpuStatsCommands(): string
    {
        $userFilesDir = __DIR__ . '/../../../user_files';
        
        $commands = [
            '/usr/bin/date',
            file_exists("$userFilesDir/sbin/ssinfo") ? "export TERM=vt100 && $userFilesDir/sbin/ssinfo -" : '/usr/bin/uname -a',
            '/usr/bin/ip a',
            '/usr/bin/df -hT',
            file_exists("$userFilesDir/sbin/din") ? "$userFilesDir/sbin/din" : '/usr/bin/free -h',
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
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        $data = $request->getParsedBody();
        $localnode = $data['localnode'] ?? null;

        if (empty($localnode)) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Local node parameter is required.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
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
            // Get the external nodes file path from common.inc
            require_once __DIR__ . '/../../../includes/common.inc';
            global $EXTNODES;
            $extnodesPath = $EXTNODES ?? '/tmp/rpt_extnodes';
            
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
            // Get IRLP log file path from common.inc
            require_once __DIR__ . '/../../../includes/common.inc';
            global $IRLP_LOG;
            $irlpLogPath = $IRLP_LOG ?? '/var/log/irlp/messages';

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
            // Load system utilities from common.inc
            require_once __DIR__ . '/../../../includes/common.inc';
            global $SUDO, $JOURNALCTL, $SED;
            
            // Use variables from common.inc with fallbacks
            $sudoCmd = $SUDO ?? "export TERM=vt100 && /usr/bin/sudo";
            $journalctlCmd = $JOURNALCTL ?? "/usr/bin/journalctl";
            $sedCmd = $SED ?? "/usr/bin/sed";
            
            // Use sudo as required for journalctl access with sed filtering to remove sudo lines
            // Limit to last 100 entries and last 2 hours to improve performance
            $command = "$sudoCmd $journalctlCmd --no-pager --since \"2 hours ago\" -n 100 | $sedCmd -e \"/sudo/ d\"";

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
                    'description' => 'System Log (journalctl, last 2 hours, 100 entries)'
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
        $data = $request->getParsedBody();
        
        try {
            // Include required dependencies for the ban/allow system using optimized service
            // Session and CSRF now handled by middleware
            $this->includeService->includeAmiFunctions();
            $this->includeService->includeCommonInc();
            $this->includeService->includeAuthFiles();

            // Define get_user_auth function if it doesn't exist
            if (!function_exists('get_user_auth')) {
                function get_user_auth($permission) {
                    global $BANUSER, $CONNECTUSER, $DISCUSER, $MONUSER, $LMONUSER, $DTMFUSER, 
                           $ASTLKUSER, $RSTATUSER, $BUBLUSER, $FAVUSER, $CTRLUSER, $CFGEDUSER,
                           $ASTRELUSER, $ASTSTRUSER, $ASTSTPUSER, $FSTRESUSER, $RBTUSER,
                           $UPDUSER, $HWTOUSER, $WIKIUSER, $CSTATUSER, $ASTATUSER, $EXNUSER,
                           $ACTNUSER, $ALLNUSER, $DBTUSER, $GPIOUSER, $LLOGUSER,
                           $ASTLUSER, $CLOGUSER, $IRLPLOGUSER, $WLOGUSER, $WERRUSER, $SYSINFUSER, $SUSBUSER;
                    
                    $permissionVar = $permission;
                    if (isset($$permissionVar) && is_array($$permissionVar)) {
                        return in_array($_SESSION['user'] ?? '', $$permissionVar, true);
                    }
                    return false;
                }
            }

            // Define get_ini_name function if it doesn't exist
            if (!function_exists('get_ini_name')) {
                function get_ini_name($user) {
                    global $ININAME;
                    if (isset($ININAME[$user])) {
                        return __DIR__ . '/../../../user_files/' . $ININAME[$user];
                    }
                    return __DIR__ . '/../../../user_files/allmon.ini';
                }
            }

            $currentUser = $this->getCurrentUser();
            
            // Check authentication using modern system
            if (!$this->hasUserPermission($currentUser, 'BANUSER')) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'You are not authorized to manage node access control lists.']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $data = $request->getParsedBody();
            $localnode = $data['localnode'] ?? null;

            if (empty($localnode) || !preg_match('/^\d+$/', $localnode)) {
                $this->logger->error('Invalid localnode parameter', ['localnode' => $localnode]);
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Valid local node parameter is required.']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Load configuration using modern system
            $config = $this->loadNodeConfig($currentUser, $localnode);
            if (!$config) {
                $this->logger->error('Node configuration not found', ['localnode' => $localnode, 'user' => $currentUser]);
                $response->getBody()->write(json_encode(['success' => false, 'message' => "Node configuration not found for node $localnode."]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Establish AMI connection using modern config
            $fp = \SimpleAmiClient::connect($config['host']);
            if ($fp === false) {
                $this->logger->error('AMI connection failed', ['host' => $config['host']]);
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Could not connect to Asterisk Manager.']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            if (\SimpleAmiClient::login($fp, $config['user'], $config['passwd']) === false) {
                \SimpleAmiClient::logoff($fp);
                $this->logger->error('AMI authentication failed', ['host' => $config['host']]);
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Could not authenticate with Asterisk Manager.']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Get allowlist and denylist data using original functions
            $allowlistData = $this->getBanAllowListData($fp, 'allowlist', $localnode);
            $denylistData = $this->getBanAllowListData($fp, 'denylist', $localnode);

            // Cleanup AMI connection
            \SimpleAmiClient::logoff($fp);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'localnode' => $localnode,
                    'allowlist' => $allowlistData,
                    'denylist' => $denylistData,
                    'timestamp' => date('c')
                ],
                'message' => 'Node access control lists retrieved successfully'
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

    private function getBanAllowListData($fp, string $listType, string $localnode): array
    {
        $dbFamily = $listType . "/" . $localnode;
        $rawData = \SimpleAmiClient::command($fp, "database show " . $dbFamily);
        
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
            // Include AMI functions using optimized service
            $this->includeService->includeAmiFunctions();
            
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
        $this->logger->info('Reboot request', ['user' => $currentUser]);
        
        if (!$this->hasUserPermission($currentUser, 'RBTUSER')) {
            $this->logger->info('Reboot permission denied', ['user' => $currentUser]);
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'You are not authorized to reboot the server.']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        try {
            // Execute reboot command in background first
            $this->executeRebootInBackground();
            
            // Send success response
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'message' => 'Server reboot command initiated. The system will reboot shortly.',
                    'timestamp' => date('c')
                ],
                'message' => 'Server reboot initiated successfully'
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $this->logger->error('Failed to execute reboot command', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to execute reboot command: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    private function executeRebootInBackground(): void
    {
        try {
            // Create a temporary script to handle the delayed reboot
            $scriptContent = "#!/bin/bash\nsleep 10\nsudo /usr/sbin/reboot\n";
            $scriptPath = '/tmp/reboot_' . uniqid() . '.sh';
            
            // Write the script
            file_put_contents($scriptPath, $scriptContent);
            chmod($scriptPath, 0755);
            
            // Execute the script in background
            $command = "nohup $scriptPath > /dev/null 2>&1 &";
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);
            
            // Log the reboot attempt
            $this->logger->info('Server reboot command executed in background', [
                'command' => $command,
                'script_path' => $scriptPath,
                'return_code' => $returnCode,
                'output' => $output
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to execute reboot command in background', [
                'error' => $e->getMessage(),
                'command' => $command ?? 'unknown'
            ]);
        }
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

            // Get node configuration
            $config = $this->loadNodeConfig($currentUser, $localnode);
            
            if (!$config) {
                $response->getBody()->write(json_encode(['success' => false, 'message' => "Node $localnode configuration not found."]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Retrieve statistics data
            $statsData = $this->getAllStarStats($config);

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
        // Include AMI functions if not already loaded
        require_once __DIR__ . '/../../../includes/amifunctions.inc';
        
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
        try {
            $host = trim(shell_exec('hostname | awk -F. \'{printf ("%s", $1);}\' 2>/dev/null') ?: 'Unknown');
            $date = trim(shell_exec('date 2>/dev/null') ?: 'Unknown');
        } catch (\Exception $e) {
            $host = 'Unknown';
            $date = 'Unknown';
        }
        
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

            // Format connected nodes like the original
            $connectedNodesData = $this->formatConnectedNodes($xnode_info);
            
            $nodes[] = [
                'node_number' => $node_num,
                'connections_count' => $connectedNodesData['count'],
                'connected_nodes_formatted' => $connectedNodesData['formatted'],
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
            return $raw_output ?: '';
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

            // Get voter status using action() method (Allmon3 style)
            $voterResponse = $this->getVoterStatus($ami, $node);
            
            if ($voterResponse === false) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Failed to get voter status'
                ]));
                
                return $response
                    ->withStatus(500)
                    ->withHeader('Content-Type', 'application/json');
            }

            // Use Allmon3's parseVoterStatus function to generate HTML
            $voterData = \SimpleAmiClient::parseVoterStatus($voterResponse);
            $baseHtml = $voterData['html'] ?? '';
            
            // Add node header to voter HTML output
            $info = $this->getAstInfo($node);
            $nodeHeader = '';
            if (!empty($nodeConfig['hideNodeURL'])) {
                $nodeHeader = "<tr><th colspan=2><i>   Node $node - $info   </i></th></tr>";
            } else {
                $nodeURL = "http://stats.allstarlink.org/nodeinfo.cgi?node=$node";
                $nodeHeader = "<tr><th colspan=2><i>   Node <a href=\"$nodeURL\" target=\"_blank\">$node</a> - $info   </i></th></tr>";
            }
            
            // Insert header before the Client/RSSI header row
            $html = str_replace('<tr><th>Client</th><th>RSSI</th></tr>', $nodeHeader . '<tr><th>Client</th><th>RSSI</th></tr>', $baseHtml);

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
     * Get voter status via AMI using action() method (Allmon3 style)
     */
    private function getVoterStatus($ami, $node): string|false
    {
        $result = \SimpleAmiClient::action($ami, "VoterStatus", [
            "NODE" => $node
        ]);
        
        return $result;
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
            $versionResponse = \SimpleAmiClient::command($ami, "asterisk -V");
            if ($versionResponse && strpos($versionResponse, 'Asterisk') !== false) {
                return "Asterisk Node";
            }

            return "Voter Node";
        } catch (Exception $e) {
            return "Voter Node";
        }
    }

    /**
     * Get node status information using shell commands (more reliable)
     */
    private function getNodeStatusInfoFromShell(string $nodeId): array
    {
        $statusInfo = [
            'status' => 'unknown',
            'last_heard' => date('Y-m-d H:i:s'),
            'connected_nodes' => null,
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
            $statusCmd = "sudo /usr/sbin/asterisk -rx 'rpt status $nodeId'";
            $statusResponse = shell_exec($statusCmd);
            if ($statusResponse) {
                if (strpos($statusResponse, 'Online') !== false) {
                    $statusInfo['status'] = 'online';
                    $statusInfo['is_online'] = true;
                } elseif (strpos($statusResponse, 'Offline') !== false) {
                    $statusInfo['status'] = 'offline';
                    $statusInfo['is_online'] = false;
                }
            }

            // Get connected nodes with detailed information
            $connectedCmd = "sudo /usr/sbin/asterisk -rx 'rpt nodes $nodeId'";
            $connectedResponse = shell_exec($connectedCmd);
            if ($connectedResponse && !empty(trim($connectedResponse))) {
                $lines = explode("\n", $connectedResponse);
                $connectedNodes = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line) || strpos($line, 'CONNECTED NODES') !== false) {
                        continue;
                    }
                    
                    // Parse connected nodes (they're space-separated)
                    $nodes = preg_split('/\s+/', $line);
                    foreach ($nodes as $node) {
                        $node = trim($node);
                        if (!empty($node) && preg_match('/^[TR]\d+$/', $node)) {
                            $connectedNodes[] = [
                                'node' => $node,
                                'info' => $node,
                                'ip' => null,
                                'last_keyed' => date('Y-m-d H:i:s'),
                                'link' => 'IAX',
                                'direction' => 'unknown',
                                'elapsed' => 'unknown',
                                'mode' => 'duplex',
                                'keyed' => '0'
                            ];
                        }
                    }
                }
                if (!empty($connectedNodes)) {
                    $statusInfo['connected_nodes'] = $connectedNodes;
                }
            }

            // Get key status
            $keyCmd = "sudo /usr/sbin/asterisk -rx 'rpt keyed $nodeId'";
            $keyResponse = shell_exec($keyCmd);
            if ($keyResponse) {
                if (strpos($keyResponse, 'Keyed') !== false) {
                    $statusInfo['is_keyed'] = true;
                    $statusInfo['tx_keyed'] = '1';
                }
            }

        } catch (Exception $e) {
            $this->logger->error('Failed to get node status from shell', [
                'node' => $nodeId,
                'error' => $e->getMessage()
            ]);
        }

        return $statusInfo;
    }

    /**
     * Get node status information from AMI
     */
    private function getNodeStatusInfo($ami, string $nodeId): array
    {
        $statusInfo = [
            'status' => 'unknown',
            'last_heard' => date('Y-m-d H:i:s'),
            'connected_nodes' => null,
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
            $statusResponse = \SimpleAmiClient::command($ami, "asterisk -rx 'rpt status $nodeId'");
            if ($statusResponse) {
                if (strpos($statusResponse, 'Online') !== false) {
                    $statusInfo['status'] = 'online';
                    $statusInfo['is_online'] = true;
                } elseif (strpos($statusResponse, 'Offline') !== false) {
                    $statusInfo['status'] = 'offline';
                    $statusInfo['is_online'] = false;
                }
            }

            // Get connected nodes with detailed information
            $connectedResponse = \SimpleAmiClient::command($ami, "asterisk -rx 'rpt nodes $nodeId'");
            if ($connectedResponse) {
                $lines = explode("\n", $connectedResponse);
                $connectedNodes = [];
                foreach ($lines as $line) {
                    if (preg_match('/Node\s+(\d+)/', $line, $matches)) {
                        $connectedNodeId = $matches[1];
                        // Get additional info for each connected node
                        $nodeInfoResponse = \SimpleAmiClient::command($ami, "asterisk -rx 'rpt stats $connectedNodeId'");
                        $info = 'Unknown';
                        $ip = null;
                        if ($nodeInfoResponse) {
                            if (preg_match('/Info:\s*(.+)/', $nodeInfoResponse, $infoMatches)) {
                                $info = trim($infoMatches[1]);
                            }
                            if (preg_match('/IP:\s*(\d+\.\d+\.\d+\.\d+)/', $nodeInfoResponse, $ipMatches)) {
                                $ip = $ipMatches[1];
                            }
                        }
                        
                        $connectedNodes[] = [
                            'node' => $connectedNodeId,
                            'info' => $info,
                            'ip' => $ip,
                            'last_keyed' => date('Y-m-d H:i:s'),
                            'link' => 'IAX',
                            'direction' => 'unknown',
                            'elapsed' => 'unknown',
                            'mode' => 'duplex',
                            'keyed' => '0'
                        ];
                    }
                }
                $statusInfo['connected_nodes'] = $connectedNodes;
            }

            // Get key status
            $keyResponse = \SimpleAmiClient::command($ami, "asterisk -rx 'rpt keyed $nodeId'");
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

    /**
     * Format connected nodes data like the original legacy implementation
     */
    private function formatConnectedNodes($xnode_info): array
    {
        if (!$xnode_info) {
            return ['count' => '<NONE>', 'formatted' => '<NONE>'];
        }

        // Extract connection count from RPT_ALINKS (like original)
        $connectionCount = '<NONE>';
        $lines = explode("\n", $xnode_info);
        foreach ($lines as $line) {
            if (strpos($line, 'RPT_ALINKS') !== false) {
                // Extract and clean the connection info
                $parts = explode('RPT_ALINKS', $line);
                if (isset($parts[1])) {
                    $connectionCount = preg_replace('/[a-zA-Z=_]/', '', $parts[1]);
                    $connectionCount = str_replace(',', ': ', $connectionCount);
                    $connectionCount = trim($connectionCount);
                }
                break;
            }
        }

        // Extract connected nodes list (like original - line 3 of xnode output)
        $connectedNodes = '<NONE>';
        $cleanedLines = [];
        foreach ($lines as $line) {
            $prefix = "Output: ";
            if (strpos($line, $prefix) === 0) {
                $cleanedLines[] = substr($line, strlen($prefix));
            } else {
                $cleanedLines[] = $line;
            }
        }

        // Get the third line (index 2) which contains connected nodes
        if (isset($cleanedLines[2])) {
            $nodesList = trim($cleanedLines[2]);
            if (!empty($nodesList) && $nodesList !== '<NONE>') {
                $nodes = explode(', ', $nodesList);
                $nodeCount = count($nodes);
                $formattedNodes = sprintf(" %3s node(s) total:\n     ", $nodeCount);
                
                $k = 0;
                for ($j = 0; $j < $nodeCount; $j++) {
                    $formattedNodes .= sprintf("%8s", trim($nodes[$j]));
                    if ($j < $nodeCount - 1) {
                        $formattedNodes .= ", ";
                    }
                    $k++;
                    if ($k >= 10 && $j < $nodeCount - 1) {
                        $k = 0;
                        $formattedNodes .= "\n     ";
                    }
                }
                $connectedNodes = $formattedNodes;
            }
        }

        return [
            'count' => $connectionCount,
            'formatted' => $connectedNodes
        ];
    }

    /**
     * Handle lsnod requests - equivalent to /cgi-bin/sm_lsnodes
     */
    public function lsnodes(Request $request, Response $response, array $args): Response
    {
        $this->logger->info('lsnodes request', ['args' => $args]);
        
        try {
            $nodeId = $args['id'] ?? null;
            if (!$nodeId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Node ID is required'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Get current user for permission checking
            $currentUser = $this->getCurrentUser();
            
            // Check if user has permission to access this node
            if (!$this->hasUserPermission($currentUser, 'FAVUSER')) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Access denied'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            // Execute lsnod command via AMI
            $lsnodData = $this->executeLsnodCommand($nodeId);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $lsnodData,
                'node' => $nodeId,
                'timestamp' => date('c')
            ]));

            return $response->withHeader('Content-Type', 'application/json');

        } catch (Exception $e) {
            $this->logger->error('lsnodes error', ['error' => $e->getMessage(), 'node' => $nodeId ?? 'unknown']);
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to execute lsnod command: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Handle lsnod web interface - equivalent to /cgi-bin/lsnodes_web
     */
    public function lsnodesWeb(Request $request, Response $response, array $args): Response
    {
        $this->logger->info('lsnodes web request', ['args' => $args]);
        
        try {
            $nodeId = $args['id'] ?? null;
            if (!$nodeId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Node ID is required'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Get current user for permission checking
            $currentUser = $this->getCurrentUser();
            
            // Check if user has permission to access this node
            if (!$this->hasUserPermission($currentUser, 'FAVUSER')) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Access denied'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            // Execute lsnod command and format for web display
            $lsnodData = $this->executeLsnodCommand($nodeId);
            $webData = $this->formatLsnodForWeb($lsnodData, $nodeId);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $webData,
                'node' => $nodeId,
                'timestamp' => date('c')
            ]));

            return $response->withHeader('Content-Type', 'application/json');

        } catch (Exception $e) {
            $this->logger->error('lsnodes web error', ['error' => $e->getMessage(), 'node' => $nodeId ?? 'unknown', 'trace' => $e->getTraceAsString()]);
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to execute lsnod web command: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Execute lsnod command via AMI
     */
    private function executeLsnodCommand(string $nodeId): array
    {
        try {
            // Use the original supermon 1.0.6 approach with AMI commands
            $nodeConfig = $this->loadNodeConfig($this->getCurrentUser(), $nodeId);
            if (!$nodeConfig) {
                throw new \Exception("Configuration for node $nodeId not found");
            }

            // Connect to AMI
            $fp = $this->connectToAmi($nodeConfig, $nodeId);
            if (!$fp) {
                throw new \Exception("Could not connect to Asterisk Manager for node $nodeId");
            }

            // Get node data using AMI for lsnod (includes all linked nodes)
            $nodeData = $this->getNodeDataForLsnod($fp, $nodeId);
        
        // Clean up connection
        \SimpleAmiClient::logoff($fp);

            // Get registration data using shell command
            $registrationData = $this->getRegistrationData($nodeId);
            
            // Add registration data to node data
            $nodeData['iax_registry'] = $registrationData;

            // Format the data for lsnod display
            $parsedData = $this->formatLsnodDataFromAmi($nodeData, $nodeId);
            
            return [
                'raw_output' => [], // AMI data is already parsed
                'parsed_data' => $parsedData,
                'command' => 'ami_xstat_sawstat',
                'executed_at' => date('c')
            ];

        } catch (Exception $e) {
            $this->logger->error('Failed to execute lsnod via AMI', [
                'node' => $nodeId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get node data for lsnod display (direct connections only)
     * Keep it simple - just show direct connections
     */
    private function getNodeDataForLsnod($fp, string $node): array
    {
        // Get basic node data (direct connections and system stats only)
        $basicNodeData = $this->getNodeData($fp, $node);
        
        // Filter to only show direct connections (no linked nodes)
        $directConnections = [];
        foreach ($basicNodeData['remote_nodes'] ?? [] as $remoteNode) {
            // Only include direct connections (those with actual connection details)
            if (isset($remoteNode['ip']) && $remoteNode['ip'] !== 'N/A' && $remoteNode['ip'] !== 'Indirect') {
                $directConnections[] = $remoteNode;
            }
        }
        
        $basicNodeData['remote_nodes'] = $directConnections;
        
        return $basicNodeData;
    }


    /**
     * Get registration data using shell command (filtered for specific node)
     */
    private function getRegistrationData(string $nodeId): array
    {
        $command = "sudo /usr/sbin/asterisk -rx \"rpt show registrations\"";
        $output = shell_exec($command);
        
        if (!$output || empty(trim($output))) {
            return [];
        }
        
        $lines = explode("\n", $output);
        // Remove header line and summary line
        $lines = array_slice($lines, 1);
        $lines = array_filter($lines, function($line) {
            return !empty(trim($line)) && !str_contains($line, 'HTTP registrations');
        });
        
        $registrations = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Parse lines like "52.44.147.201:443                              546051      73.6.70.88:4572                          179  Registered"
            if (preg_match('/^(.+?)\s{2,}(\d+)\s+(.+?)\s+(\d+)\s+(.+)$/', $line, $matches)) {
                $host = trim($matches[1]);
                $username = trim($matches[2]);
                $perceived = trim($matches[3]);
                $refresh = trim($matches[4]);
                $state = trim($matches[5]);
                
                // SECURITY: Only include registrations for the specific node being queried
                if ($username === $nodeId) {
                    $registrations[] = [
                        'host' => $host,
                        'username' => $username,
                        'perceived' => $perceived,
                        'refresh' => $refresh,
                        'state' => $state
                    ];
                }
            }
        }
        
        return $registrations;
    }

    /**
     * Format AMI node data for lsnod display (like original supermon 1.0.6)
     */
    private function formatLsnodDataFromAmi(array $nodeData, string $nodeId): array
    {
        // Load astdb.txt for node descriptions
        $astdb = $this->loadAstDb(__DIR__ . '/../../../astdb.txt');
        
        // Get main node info
        $mainNodeInfo = $this->getNodeInfoFromAstdb($nodeId, $astdb);
        
        // Format connected nodes from remote_nodes
        $nodes = [];
        $connectedNodes = $nodeData['remote_nodes'] ?? [];
        
        foreach ($connectedNodes as $remoteNode) {
            $nodeNumber = $remoteNode['node'] ?? '';
            if (empty($nodeNumber) || intval($nodeNumber) < 2000) {
                continue; // Skip private nodes
            }
            
            $nodeInfo = $this->getNodeInfoFromAstdb($nodeNumber, $astdb);
            
            $nodes[] = [
                'node_number' => $nodeNumber,
                'description' => $nodeInfo['description'],
                'status' => 'Connected',
                'callsign' => $nodeInfo['callsign'],
                'frequency' => $nodeInfo['frequency'],
                'location' => $nodeInfo['location'],
                'peer_ip' => $remoteNode['ip'] ?? 'N/A',
                'reconnects' => 'N/A', // Not available in AMI data
                'direction' => $remoteNode['direction'] ?? 'N/A',
                'connect_time' => $remoteNode['elapsed'] ?? 'N/A',
                'connect_state' => $remoteNode['link'] ?? 'Connected'
            ];
        }
        
        // Format system state from main node data
        $systemState = [];
        if (isset($nodeData['cos_keyed'])) {
            $systemState[] = ['COS Keyed', $nodeData['cos_keyed'] ? 'Yes' : 'No'];
        }
        if (isset($nodeData['tx_keyed'])) {
            $systemState[] = ['TX Keyed', $nodeData['tx_keyed'] ? 'Yes' : 'No'];
        }
        if (isset($nodeData['cpu_temp'])) {
            $systemState[] = ['CPU Temp', $nodeData['cpu_temp']];
        }
        if (isset($nodeData['cpu_up'])) {
            $systemState[] = ['CPU Uptime', $nodeData['cpu_up']];
        }
        if (isset($nodeData['cpu_load'])) {
            $systemState[] = ['CPU Load', $nodeData['cpu_load']];
        }
        if (isset($nodeData['ALERT'])) {
            $systemState[] = ['Alert', $nodeData['ALERT']];
        }
        if (isset($nodeData['WX'])) {
            $systemState[] = ['Weather', $nodeData['WX']];
        }
        if (isset($nodeData['DISK'])) {
            $systemState[] = ['Disk', $nodeData['DISK']];
        }
        
        return [
            'main_node' => [
                'node_number' => $nodeId,
                'callsign' => $mainNodeInfo['callsign'],
                'frequency' => $mainNodeInfo['frequency'],
                'location' => $mainNodeInfo['location']
            ],
            'system_state' => $systemState,
            'nodes' => $nodes,
            'node_lstatus' => [], // Not used in AMI approach
            'iax_registry' => $nodeData['iax_registry'] ?? [] // Registration data from shell command
        ];
    }

    /**
     * Parse lsnod output from shell commands (original supermon approach)
     */
    private function parseLsnodOutputFromShell(array $results, string $nodeId): array
    {
        $nodes = [];
        $nodeStatus = [];
        $nodeLstatus = [];
        $iaxRegistry = [];
        $allConnectedNodeIds = [];
        $xnodeData = [];
        
        // Load astdb.txt for node descriptions
        $astdb = $this->loadAstDb(__DIR__ . '/../../../astdb.txt');
        
        // Parse each command result
        foreach ($results as $result) {
            $command = $result['command'];
            $output = $result['output'];
            
            if (str_contains($command, 'rpt nodes')) {
                // Parse connected nodes list - this shows ALL connected nodes
                $lines = explode("\n", $output);
                // Remove header lines (first 2 lines with asterisks)
                $lines = array_slice($lines, 2);
                
                $connectedNodeIds = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    // Parse lines like "T1000, T1050, T1776, T1975, T1995, T27462, T3855305, T40255"
                    $nodeIds = explode(',', $line);
                    foreach ($nodeIds as $nodeIdItem) {
                        $nodeIdItem = trim($nodeIdItem);
                        if (!empty($nodeIdItem)) {
                            // Remove the 'T' prefix if present
                            $nodeIdItem = ltrim($nodeIdItem, 'T');
                            if (is_numeric($nodeIdItem) && intval($nodeIdItem) >= 2000) {
                                $connectedNodeIds[] = $nodeIdItem;
                            }
                        }
                    }
                }
                
                // Store connected node IDs for later use - don't create entries yet
                // The actual entries will be created from rpt lstats data
                $allConnectedNodeIds = $connectedNodeIds;
            } elseif (str_contains($command, 'rpt lstats')) {
                // Parse directly connected nodes from lstats (this shows actual direct connections)
                $lines = explode("\n", $output);
                // Remove header lines - the output has 2 header lines
                $lines = array_slice($lines, 2);
                
                // Store lstats data for nodes that have direct connections
                $lstatsData = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    // Parse the lstats line: NODE PEER RECONNECTS DIRECTION CONNECT_TIME CONNECT_STATE
                    $parts = preg_split('/\s+/', $line);
                    
                    if (count($parts) >= 6) {
                        $connectedNodeId = $parts[0];
                        $peerIp = $parts[1];
                        $reconnects = $parts[2];
                        $direction = $parts[3];
                        $connectTime = $parts[4];
                        $connectState = $parts[5];
                        
                        if (is_numeric($connectedNodeId) && intval($connectedNodeId) >= 2000) {
                            $lstatsData[$connectedNodeId] = [
                                'peer_ip' => $peerIp,
                                'reconnects' => $reconnects,
                                'direction' => $direction,
                                'connect_time' => $connectTime,
                                'connect_state' => $connectState
                            ];
                            
                            // Populate node_lstatus array for the connections table (only first connection)
                            if (empty($nodeLstatus)) {
                                $nodeLstatus = [
                                    $connectedNodeId,
                                    $peerIp,
                                    $reconnects,
                                    $direction,
                                    $connectTime,
                                    $connectState
                                ];
                            }
                        }
                    }
                }
                
                // Now create entries for ALL connected nodes (from rpt nodes), using lstats and xnode data where available
                if (isset($allConnectedNodeIds)) {
                    foreach ($allConnectedNodeIds as $connectedNodeId) {
                        $nodeInfo = $this->getNodeInfoFromAstdb($connectedNodeId, $astdb);
                        
                        // Start with defaults
                        $connectionData = [
                            'peer_ip' => 'N/A',
                            'reconnects' => 'N/A',
                            'direction' => 'N/A',
                            'connect_time' => 'N/A',
                            'connect_state' => 'Connected'
                        ];
                        
                        // Use lstats data if available (direct connections)
                        if (isset($lstatsData[$connectedNodeId])) {
                            $connectionData = $lstatsData[$connectedNodeId];
                        }
                        // Use xnode direct connection data if available
                        elseif (isset($xnodeData['direct_connection']) && $xnodeData['direct_connection']['node_id'] === $connectedNodeId) {
                            $directConn = $xnodeData['direct_connection'];
                            $connectionData = [
                                'peer_ip' => $directConn['peer_ip'],
                                'reconnects' => $directConn['reconnects'],
                                'direction' => $directConn['direction'],
                                'connect_time' => $directConn['connect_time'],
                                'connect_state' => $directConn['connect_state']
                            ];
                        }
                        // Use xnode RPT_LINKS data to determine connection type
                        elseif (isset($xnodeData['rpt_links'][$connectedNodeId])) {
                            $connectionType = $xnodeData['rpt_links'][$connectedNodeId];
                            $connectionData['direction'] = $connectionType === 'T' ? 'TCP' : 'RPT';
                            $connectionData['connect_state'] = 'LINKED';
                        }
                        
                        $nodes[] = [
                            'node_number' => $connectedNodeId,
                            'description' => $nodeInfo['description'],
                            'status' => 'Connected',
                            'callsign' => $nodeInfo['callsign'],
                            'frequency' => $nodeInfo['frequency'],
                            'location' => $nodeInfo['location'],
                            'peer_ip' => $connectionData['peer_ip'],
                            'reconnects' => $connectionData['reconnects'],
                            'direction' => $connectionData['direction'],
                            'connect_time' => $connectionData['connect_time'],
                            'connect_state' => $connectionData['connect_state']
                        ];
                    }
                }
            } elseif (str_contains($command, 'rpt stats')) {
                // Parse node status - the output format is different than expected
                $lines = explode("\n", $output);
                // Remove the header line with asterisks
                $lines = array_slice($lines, 1);
                
                $nodeStatus = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    // Parse lines like "Selected system state............................: 0"
                    if (preg_match('/^(.+?)\s*\.+\s*:\s*(.+)$/', $line, $matches)) {
                        $key = trim($matches[1]);
                        $value = trim($matches[2]);
                        $nodeStatus[] = $key;
                        $nodeStatus[] = $value;
                    }
                }
            } elseif (str_contains($command, 'rpt lstats')) {
                // This is already handled above in the first rpt lstats condition
                // Skip duplicate processing
            } elseif (str_contains($command, 'rpt xnode')) {
                // Parse extended node information to get more detailed connection data
                $lines = explode("\n", $output);
                
                // Parse the first line which contains direct connection info
                if (count($lines) > 0) {
                    $firstLine = trim($lines[0]);
                    if (!empty($firstLine) && !str_contains($firstLine, 'T1000')) {
                        // This is a direct connection line: "48752     66.170.205.59       0           OUT        00:08:44            ESTABLISHED"
                        $parts = preg_split('/\s+/', $firstLine);
                        if (count($parts) >= 6) {
                            $directNodeId = $parts[0];
                            $directPeerIp = $parts[1];
                            $directReconnects = $parts[2];
                            $directDirection = $parts[3];
                            $directConnectTime = $parts[4];
                            $directConnectState = $parts[5];
                            
                            // Store this as the primary connection info
                            $xnodeDirectConnection = [
                                'node_id' => $directNodeId,
                                'peer_ip' => $directPeerIp,
                                'reconnects' => $directReconnects,
                                'direction' => $directDirection,
                                'connect_time' => $directConnectTime,
                                'connect_state' => $directConnectState
                            ];
                        }
                    }
                }
                
                // Parse RPT_LINKS variable to get connection types for all nodes
                $rptLinks = [];
                foreach ($lines as $line) {
                    if (str_starts_with($line, 'RPT_LINKS=')) {
                        $linksData = substr($line, 9); // Remove 'RPT_LINKS='
                        $links = explode(',', $linksData);
                        foreach ($links as $link) {
                            $link = trim($link);
                            if (!empty($link)) {
                                // Extract node ID and connection type
                                if (preg_match('/^([TR])(\d+)$/', $link, $matches)) {
                                    $connectionType = $matches[1]; // T or R
                                    $linkedNodeId = $matches[2];
                                    // Only store nodes >= 2000 to match our filtering
                                    if (intval($linkedNodeId) >= 2000) {
                                        $rptLinks[$linkedNodeId] = $connectionType;
                                    }
                                }
                            }
                        }
                        break;
                    }
                }
                
                // Store the extended node data for later use
                $xnodeData = [
                    'direct_connection' => $xnodeDirectConnection ?? null,
                    'rpt_links' => $rptLinks
                ];
                
                
            } elseif (str_contains($command, 'rpt show registrations')) {
                // Parse RPT registrations - get all registrations, not just for specific node
                $lines = explode("\n", $output);
                // Remove header line and summary line
                $lines = array_slice($lines, 1);
                $lines = array_filter($lines, function($line) {
                    return !empty(trim($line)) && !str_contains($line, 'HTTP registrations');
                });
                
                $allRegistrations = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    // Parse lines like "52.44.147.201:443                              546051      73.6.70.88:4572                          179  Registered"
                    // The format is: Host (with lots of spaces) Username (spaces) Perceived (spaces) Refresh (spaces) State
                    if (preg_match('/^(.+?)\s{2,}(\d+)\s+(.+?)\s+(\d+)\s+(.+)$/', $line, $matches)) {
                        $host = trim($matches[1]);
                        $username = trim($matches[2]);
                        $perceived = trim($matches[3]);
                        $refresh = trim($matches[4]);
                        $state = trim($matches[5]);
                        
                        $allRegistrations[] = [
                            'host' => $host,
                            'username' => $username,
                            'perceived' => $perceived,
                            'refresh' => $refresh,
                            'state' => $state
                        ];
                    }
                }
                
                $iaxRegistry = $allRegistrations;
            }
        }
        
        return [
            'nodes' => $nodes,
            'node_status' => $nodeStatus,
            'node_lstatus' => $nodeLstatus,
            'iax_registry' => $iaxRegistry,
            'total_nodes' => count($nodes)
        ];
    }

    /**
     * Get node information from astdb
     */
    private function getNodeInfoFromAstdb(string $nodeNum, array $astdb): array
    {
        $nodeNumInt = (int)$nodeNum;
        
        // Check if node is private (less than 2000)
        if ($nodeNumInt < 2000) {
            return [
                'description' => 'Private Node',
                'callsign' => 'Private',
                'frequency' => 'Private',
                'location' => 'Private'
            ];
        }
        
        // Look up in astdb (new format: associative array)
        if (isset($astdb[$nodeNum]) && is_array($astdb[$nodeNum])) {
            $info = $astdb[$nodeNum];
            $callsign = $info['callsign'] ?? '';
            $description = $info['description'] ?? '';
            $location = $info['location'] ?? '';
            
            // Create the same format as original getAstInfo function
            // Concatenate callsign, description, and location with spaces
            $fullInfo = trim($callsign . ' ' . $description . ' ' . $location);
            
            return [
                'description' => $fullInfo,
                'callsign' => $callsign,
                'frequency' => $description,
                'location' => $location
            ];
        }
        
        // Node not found in database
        return [
            'description' => 'Node not in database',
            'callsign' => 'Unknown',
            'frequency' => 'Unknown',
            'location' => 'Unknown'
        ];
    }

    /**
     * Parse lsnod command output (legacy method)
     */
    private function parseLsnodOutput(string $output): array
    {
        $lines = explode("\n", trim($output));
        $nodes = [];
        $currentNode = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Look for node entries (typically start with node number)
            if (preg_match('/^(\d+)\s+(.+)$/', $line, $matches)) {
                $nodeNumber = $matches[1];
                $description = $matches[2];
                
                $nodes[] = [
                    'node_number' => $nodeNumber,
                    'description' => $description,
                    'status' => 'unknown' // Would need additional parsing for status
                ];
            }
        }
        
        return [
            'total_nodes' => count($nodes),
            'nodes' => $nodes,
            'raw_lines' => $lines
        ];
    }

    /**
     * Format lsnod data for web display
     */
    private function formatLsnodForWeb(array $lsnodData, string $nodeId): array
    {
        $parsedData = $lsnodData['parsed_data'] ?? [];
        $nodes = $parsedData['nodes'] ?? [];
        $nodeStatus = $parsedData['node_status'] ?? [];
        $nodeLstatus = $parsedData['node_lstatus'] ?? [];
        $iaxRegistry = $parsedData['iax_registry'] ?? [];
        
        // Check if this is an error case
        if (isset($parsedData['error']) && $parsedData['error'] === 'lsnod not available') {
            return [
                'error' => true,
                'message' => $parsedData['message'] ?? 'lsnod functionality is not available',
                'node_count' => 0,
                'nodes' => [],
                'raw_data' => $lsnodData
            ];
        }
        
        // Parse system state from node status array (matching original supermon logic)
        $systemState = $this->parseSystemStateFromStatus($nodeStatus);
        
        // Get main node information from astdb (like the original supermon script)
        $astdb = $this->loadAstDb(__DIR__ . '/../../../astdb.txt');
        $mainNodeInfo = $this->getNodeInfoFromAstdb($nodeId, $astdb);
        
        return [
            'error' => false,
            'node_count' => count($nodes),
            'main_node' => [
                'node_number' => $nodeId,
                'callsign' => $mainNodeInfo['callsign'] ?? 'Unknown',
                'frequency' => $mainNodeInfo['frequency'] ?? 'Unknown', 
                'location' => $mainNodeInfo['location'] ?? 'Unknown'
            ],
            'nodes' => $nodes,
            'node_status' => $nodeStatus,
            'node_lstatus' => $nodeLstatus,
            'iax_registry' => $iaxRegistry,
            'raw_data' => $lsnodData,
            // System state fields for frontend
            'selected_system_state' => $systemState['selected_system_state'] ?? '0',
            'signal_on_input' => $systemState['signal_on_input'] ?? 'NO',
            'system' => $systemState['system'] ?? 'ENABLED',
            'parrot_mode' => $systemState['parrot_mode'] ?? 'DISABLED',
            'scheduler' => $systemState['scheduler'] ?? 'ENABLED',
            'tail_time' => $systemState['tail_time'] ?? 'STANDARD',
            'timeout_timer' => $systemState['timeout_timer'] ?? 'ENABLED',
            'incoming_connections' => $systemState['incoming_connections'] ?? 'ENABLED',
            'timeout_timer_state' => $systemState['timeout_timer_state'] ?? 'RESET',
            'timeouts_since_init' => $systemState['timeouts_since_init'] ?? '0',
            'identifier_state' => $systemState['identifier_state'] ?? 'CLEAN',
            'kerchunks_today' => $systemState['kerchunks_today'] ?? '0',
            'kerchunks_since_init' => $systemState['kerchunks_since_init'] ?? '0',
            'keyups_today' => $systemState['keyups_today'] ?? '0',
            'keyups_since_init' => $systemState['keyups_since_init'] ?? '0',
            'dtmf_commands_today' => $systemState['dtmf_commands_today'] ?? '0',
            'dtmf_commands_since_init' => $systemState['dtmf_commands_since_init'] ?? '0',
            'last_dtmf_command' => $systemState['last_dtmf_command'] ?? 'N/A',
            'tx_time_today' => $systemState['tx_time_today'] ?? '00:00:00:000',
            'tx_time_since_init' => $systemState['tx_time_since_init'] ?? '00:00:00:000',
            'uptime' => $systemState['uptime'] ?? '00:00:00',
            'nodes_connected' => $systemState['nodes_connected'] ?? '0',
            'autopatch' => $systemState['autopatch'] ?? 'ENABLED',
            'autopatch_state' => $systemState['autopatch_state'] ?? 'DOWN',
            'autopatch_called_number' => $systemState['autopatch_called_number'] ?? 'N/A',
            'reverse_patch' => $systemState['reverse_patch'] ?? 'DOWN',
            'user_linking_commands' => $systemState['user_linking_commands'] ?? 'ENABLED',
            'user_functions' => $systemState['user_functions'] ?? 'ENABLED'
        ];
    }

    /**
     * Parse system state from raw status array (matching original supermon logic)
     */
    private function parseSystemStateFromStatus(array $nodeStatus): array
    {
        // The node_status array contains alternating key-value pairs
        // Parse them into a proper associative array
        $systemState = [];
        
        for ($i = 0; $i < count($nodeStatus); $i += 2) {
            if (isset($nodeStatus[$i]) && isset($nodeStatus[$i + 1])) {
                $key = $nodeStatus[$i];
                $value = $nodeStatus[$i + 1];
                
                // Map the keys to the expected field names
                switch ($key) {
                    case 'Selected system state':
                        $systemState['selected_system_state'] = $value;
                        break;
                    case 'Signal on input':
                        $systemState['signal_on_input'] = $value;
                        break;
                    case 'System':
                        $systemState['system'] = $value;
                        break;
                    case 'Parrot Mode':
                        $systemState['parrot_mode'] = $value;
                        break;
                    case 'Scheduler':
                        $systemState['scheduler'] = $value;
                        break;
                    case 'Tail Time':
                        $systemState['tail_time'] = $value;
                        break;
                    case 'Time out timer':
                        $systemState['timeout_timer'] = $value;
                        break;
                    case 'Incoming connections':
                        $systemState['incoming_connections'] = $value;
                        break;
                    case 'Time out timer state':
                        $systemState['timeout_timer_state'] = $value;
                        break;
                    case 'Time outs since system initialization':
                        $systemState['timeouts_since_init'] = $value;
                        break;
                    case 'Identifier state':
                        $systemState['identifier_state'] = $value;
                        break;
                    case 'Kerchunks today':
                        $systemState['kerchunks_today'] = $value;
                        break;
                    case 'Kerchunks since system initialization':
                        $systemState['kerchunks_since_init'] = $value;
                        break;
                    case 'Keyups today':
                        $systemState['keyups_today'] = $value;
                        break;
                    case 'Keyups since system initialization':
                        $systemState['keyups_since_init'] = $value;
                        break;
                    case 'DTMF commands today':
                        $systemState['dtmf_commands_today'] = $value;
                        break;
                    case 'DTMF commands since system initialization':
                        $systemState['dtmf_commands_since_init'] = $value;
                        break;
                    case 'Last DTMF command executed':
                        $systemState['last_dtmf_command'] = $value;
                        break;
                    case 'TX time today':
                        $systemState['tx_time_today'] = $value;
                        break;
                    case 'TX time since system initialization':
                        $systemState['tx_time_since_init'] = $value;
                        break;
                    case 'Uptime':
                        $systemState['uptime'] = $value;
                        break;
                    case 'Nodes currently connected to us':
                        $systemState['nodes_connected'] = $value;
                        break;
                    case 'Autopatch':
                        $systemState['autopatch'] = $value;
                        break;
                    case 'Autopatch state':
                        $systemState['autopatch_state'] = $value;
                        break;
                    case 'Autopatch called number':
                        $systemState['autopatch_called_number'] = $value;
                        break;
                    case 'Reverse patch/IAXRPT connected':
                        $systemState['reverse_patch'] = $value;
                        break;
                    case 'User linking commands':
                        $systemState['user_linking_commands'] = $value;
                        break;
                    case 'User functions':
                        $systemState['user_functions'] = $value;
                        break;
                }
            }
        }
        
        return $systemState;
    }
}
