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
 * 
 * Note: When wrapped by WsServer, we only implement MessageComponentInterface.
 * The request is available via the connection object after the upgrade.
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
        
        $this->logger->info("WebSocketRouterService constructed", [
            'nodes_available' => count($serverManager->getAllNodePorts())
        ]);
    }
    
    /**
     * Handle new WebSocket connection
     * Extract node ID from path and route to appropriate service
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        try {
            $this->logger->info("=== WebSocket onOpen called ===", [
                'client_id' => $conn->resourceId,
                'remote_address' => $conn->remoteAddress ?? 'unknown',
                'connection_class' => get_class($conn),
                'timestamp' => microtime(true)
            ]);
        
        // WsServer stores the HTTP request in the connection object
        // We need to extract it using reflection
        $request = null;
        $path = '/';
        
        try {
            $reflection = new \ReflectionObject($conn);
            
            // Try to find request in properties
            $properties = $reflection->getProperties();
            foreach ($properties as $property) {
                $property->setAccessible(true);
                $value = $property->getValue($conn);
                if ($value instanceof RequestInterface) {
                    $request = $value;
                    $path = $request->getUri()->getPath();
                    $this->logger->info("Extracted path from connection property", [
                        'client_id' => $conn->resourceId,
                        'path' => $path,
                        'property' => $property->getName(),
                        'full_uri' => (string)$request->getUri()
                    ]);
                    break;
                }
            }
            
            // Try specifically for httpRequest property (common in Ratchet)
            if (!$request && $reflection->hasProperty('httpRequest')) {
                $property = $reflection->getProperty('httpRequest');
                $property->setAccessible(true);
                $request = $property->getValue($conn);
                if ($request instanceof RequestInterface) {
                    $path = $request->getUri()->getPath();
                    $this->logger->info("Extracted path from httpRequest property", [
                        'client_id' => $conn->resourceId,
                        'path' => $path,
                        'full_uri' => (string)$request->getUri()
                    ]);
                }
            }
            
            // If connection is wrapped (e.g., by WsServer), try to unwrap it
            if (!$request) {
                // Check if connection has a wrapped connection
                foreach ($properties as $property) {
                    $property->setAccessible(true);
                    $value = $property->getValue($conn);
                    if ($value instanceof ConnectionInterface && $value !== $conn) {
                        // Recursively check wrapped connection
                        $wrappedReflection = new \ReflectionObject($value);
                        $wrappedProperties = $wrappedReflection->getProperties();
                        foreach ($wrappedProperties as $wrappedProperty) {
                            $wrappedProperty->setAccessible(true);
                            $wrappedValue = $wrappedProperty->getValue($value);
                            if ($wrappedValue instanceof RequestInterface) {
                                $request = $wrappedValue;
                                $path = $request->getUri()->getPath();
                                $this->logger->info("Extracted path from wrapped connection", [
                                    'client_id' => $conn->resourceId,
                                    'path' => $path,
                                    'wrapped_property' => $wrappedProperty->getName()
                                ]);
                                break 2;
                            }
                        }
                        if ($wrappedReflection->hasProperty('httpRequest')) {
                            $wrappedProperty = $wrappedReflection->getProperty('httpRequest');
                            $wrappedProperty->setAccessible(true);
                            $wrappedRequest = $wrappedProperty->getValue($value);
                            if ($wrappedRequest instanceof RequestInterface) {
                                $request = $wrappedRequest;
                                $path = $request->getUri()->getPath();
                                $this->logger->info("Extracted path from wrapped httpRequest", [
                                    'client_id' => $conn->resourceId,
                                    'path' => $path
                                ]);
                                break;
                            }
                        }
                    }
                }
            }
        } catch (\ReflectionException $e) {
            $this->logger->error("Reflection failed to extract request", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        if (!$request) {
            $this->logger->warning("WebSocket connection without request object - cannot extract path", [
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
            // Log at INFO level - invalid connections are expected (health checks, wrong URLs, etc.)
            $this->logger->info("WebSocket connection rejected: no valid node ID in path", [
                'client_id' => $conn->resourceId,
                'path' => $path,
                'remote_address' => $conn->remoteAddress ?? 'unknown',
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
        } catch (Exception $e) {
            $this->logger->error("Error in onOpen", [
                'client_id' => $conn->resourceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            try {
                $conn->close();
            } catch (Exception $closeException) {
                // Ignore close errors
            }
        }
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
        
        $this->logger->error("WebSocket error", [
            'client_id' => $conn->resourceId,
            'node_id' => $nodeId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        if ($nodeId) {
            $nodeService = $this->serverManager->getNodeService($nodeId);
            if ($nodeService) {
                $nodeService->onError($conn, $e);
            }
        }
        
        try {
            $conn->close();
        } catch (Exception $closeException) {
            $this->logger->error("Error closing connection", [
                'error' => $closeException->getMessage()
            ]);
        }
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

