<?php

namespace SupermonNg\Services;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\WebSocket\WsServerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Exception;

/**
 * WebSocket Router Service
 * 
 * Routes WebSocket connections based on node ID in the path.
 * Accepts connections like: ws://localhost:8105/546051
 * Routes to the appropriate NodeWebSocketService.
 */
class WebSocketRouterService implements MessageComponentInterface
{
    private LoggerInterface $logger;
    private WebSocketServerManager $serverManager;
    
    /** @var ConnectionInterface[] Map of connection resourceId => nodeId */
    private array $connectionNodeMap = [];
    
    public function __construct(
        LoggerInterface $logger,
        WebSocketServerManager $serverManager
    ) {
        $this->logger = $logger;
        $this->serverManager = $serverManager;
    }
    
    /**
     * Handle new WebSocket connection
     * Extract node ID from path and route to appropriate service
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        $this->logger->info("WebSocket connection opened", [
            'client_id' => $conn->resourceId,
            'remote_address' => $conn->remoteAddress ?? 'unknown'
        ]);
        
        // Get the request to extract the path
        // Ratchet stores the HTTP request in the connection object
        $request = null;
        $path = '/';
        
        // Try multiple methods to get the HTTP request
        // Method 1: Reflection to access httpRequest property
        try {
            $reflection = new \ReflectionObject($conn);
            $properties = $reflection->getProperties();
            foreach ($properties as $property) {
                $property->setAccessible(true);
                $value = $property->getValue($conn);
                if ($value instanceof RequestInterface) {
                    $request = $value;
                    break;
                }
            }
            
            // Also try specifically for httpRequest
            if (!$request && $reflection->hasProperty('httpRequest')) {
                $property = $reflection->getProperty('httpRequest');
                $property->setAccessible(true);
                $request = $property->getValue($conn);
            }
        } catch (\ReflectionException $e) {
            $this->logger->debug("Reflection failed", ['error' => $e->getMessage()]);
        }
        
        // Method 2: Direct property access
        if (!$request && isset($conn->httpRequest)) {
            $request = $conn->httpRequest;
        }
        
        // Method 3: Check if connection has getRequest method
        if (!$request && method_exists($conn, 'getRequest')) {
            $request = $conn->getRequest();
        }
        
        if ($request instanceof RequestInterface) {
            $path = $request->getUri()->getPath();
            $this->logger->info("Extracted path from HTTP request", [
                'client_id' => $conn->resourceId,
                'path' => $path,
                'full_uri' => (string)$request->getUri()
            ]);
        } else {
            $this->logger->warning("WebSocket connection without request object", [
                'client_id' => $conn->resourceId,
                'connection_class' => get_class($conn),
                'available_properties' => array_keys(get_object_vars($conn))
            ]);
            // Store connection temporarily - we'll route on first message
            $this->connectionNodeMap[$conn->resourceId] = null;
            return;
        }
        
        // Extract node ID from path (e.g., /546051 or /supermon-ng/ws/546051)
        $nodeId = $this->extractNodeIdFromPath($path);
        
        if (!$nodeId) {
            $this->logger->warning("WebSocket connection without valid node ID in path", [
                'client_id' => $conn->resourceId,
                'path' => $path,
                'extracted_parts' => explode('/', trim($path, '/'))
            ]);
            $conn->close();
            return;
        }
        
        // Get the node service for this node ID
        $nodeService = $this->serverManager->getNodeService($nodeId);
        
        if (!$nodeService) {
            $availableNodes = array_keys($this->serverManager->getAllNodePorts());
            $this->logger->warning("WebSocket connection for unknown node", [
                'client_id' => $conn->resourceId,
                'node_id' => $nodeId,
                'path' => $path,
                'available_nodes' => $availableNodes
            ]);
            $conn->close();
            return;
        }
        
        // Store mapping
        $this->connectionNodeMap[$conn->resourceId] = $nodeId;
        
        // Route to the node service
        $nodeService->onOpen($conn);
        
        $this->logger->info("WebSocket connection routed to node", [
            'client_id' => $conn->resourceId,
            'node_id' => $nodeId,
            'path' => $path
        ]);
    }
    
    /**
     * Handle incoming WebSocket message
     */
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $nodeId = $this->connectionNodeMap[$from->resourceId] ?? null;
        
        // If node ID is null, this might be the first message with node identification
        if ($nodeId === null) {
            try {
                $data = json_decode($msg, true);
                if (isset($data['node']) || isset($data['nodeId'])) {
                    $nodeId = $data['node'] ?? $data['nodeId'];
                    $this->connectionNodeMap[$from->resourceId] = $nodeId;
                    $this->logger->info("WebSocket node identified from first message", [
                        'client_id' => $from->resourceId,
                        'node_id' => $nodeId
                    ]);
                }
            } catch (Exception $e) {
                // Not JSON, ignore
            }
        }
        
        if (!$nodeId) {
            $this->logger->warning("WebSocket message from unknown connection", [
                'client_id' => $from->resourceId
            ]);
            return;
        }
        
        $nodeService = $this->serverManager->getNodeService($nodeId);
        if ($nodeService) {
            $nodeService->onMessage($from, $msg);
        }
    }
    
    /**
     * Handle WebSocket connection close
     */
    public function onClose(ConnectionInterface $conn): void
    {
        $nodeId = $this->connectionNodeMap[$conn->resourceId] ?? null;
        
        if ($nodeId) {
            $nodeService = $this->serverManager->getNodeService($nodeId);
            if ($nodeService) {
                $nodeService->onClose($conn);
            }
            unset($this->connectionNodeMap[$conn->resourceId]);
        }
    }
    
    /**
     * Handle WebSocket errors
     */
    public function onError(ConnectionInterface $conn, Exception $e): void
    {
        $nodeId = $this->connectionNodeMap[$conn->resourceId] ?? null;
        
        if ($nodeId) {
            $nodeService = $this->serverManager->getNodeService($nodeId);
            if ($nodeService) {
                $nodeService->onError($conn, $e);
            }
        } else {
            $this->logger->error("WebSocket error for unknown connection", [
                'client_id' => $conn->resourceId,
                'error' => $e->getMessage()
            ]);
        }
        
        $conn->close();
    }
    
    /**
     * Extract node ID from path
     * Handles paths like: /546051, /supermon-ng/ws/546051, /ws/546051
     */
    private function extractNodeIdFromPath(string $path): ?string
    {
        // Remove leading/trailing slashes
        $path = trim($path, '/');
        
        // Split by slashes
        $parts = explode('/', $path);
        
        // Look for a numeric node ID (usually the last part)
        foreach (array_reverse($parts) as $part) {
            if (preg_match('/^\d+$/', $part)) {
                return $part;
            }
        }
        
        return null;
    }
}

