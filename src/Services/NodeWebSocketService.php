<?php

namespace SupermonNg\Services;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Exception;

/**
 * Node WebSocket Service
 * 
 * Manages WebSocket connections for a single node.
 * Maintains persistent AMI connection and broadcasts real-time data.
 * Matches Allmon3's per-node WebSocket architecture.
 */
class NodeWebSocketService implements MessageComponentInterface
{
    private string $nodeId;
    private array $nodeConfig;
    private int $port;
    private LoggerInterface $logger;
    private LoopInterface $loop;
    
    /** @var ConnectionInterface[] */
    private array $clients = [];
    
    private $amiConnection = null;
    private bool $amiConnected = false;
    private int $pollInterval = 1; // seconds
    private ?string $lastData = null;
    
    public function __construct(
        string $nodeId,
        array $nodeConfig,
        int $port,
        LoggerInterface $logger,
        LoopInterface $loop
    ) {
        $this->nodeId = $nodeId;
        $this->nodeConfig = $nodeConfig;
        $this->port = $port;
        $this->logger = $logger;
        $this->loop = $loop;
    }
    
    /**
     * Get the port this service is listening on
     */
    public function getPort(): int
    {
        return $this->port;
    }
    
    /**
     * Get the node ID
     */
    public function getNodeId(): string
    {
        return $this->nodeId;
    }
    
    /**
     * Start the WebSocket server and AMI polling
     */
    public function start(): void
    {
        $this->logger->info("Starting WebSocket service for node", [
            'node_id' => $this->nodeId,
            'port' => $this->port,
            'host' => $this->nodeConfig['host'] ?? 'unknown'
        ]);
        
        // Connect to AMI
        $this->connectToAmi();
        
        // Set up periodic polling
        $this->loop->addPeriodicTimer($this->pollInterval, function () {
            $this->pollAndBroadcast();
        });
    }
    
    /**
     * Connect to AMI for this node
     */
    private function connectToAmi(): void
    {
        if ($this->amiConnected && $this->amiConnection !== null) {
            return;
        }
        
        try {
            require_once __DIR__ . '/../../includes/amifunctions.inc';
            
            $host = $this->nodeConfig['host'] ?? 'localhost:5038';
            $user = $this->nodeConfig['user'] ?? 'admin';
            $password = $this->nodeConfig['passwd'] ?? '';
            
            $this->amiConnection = \SimpleAmiClient::getConnection($host, $user, $password);
            
            if ($this->amiConnection === false) {
                $this->logger->error("Failed to connect to AMI for node", [
                    'node_id' => $this->nodeId,
                    'host' => $host
                ]);
                $this->amiConnected = false;
                return;
            }
            
            $this->amiConnected = true;
            $this->logger->info("Connected to AMI for node", [
                'node_id' => $this->nodeId,
                'host' => $host
            ]);
            
        } catch (Exception $e) {
            $this->logger->error("AMI connection error for node", [
                'node_id' => $this->nodeId,
                'error' => $e->getMessage()
            ]);
            $this->amiConnected = false;
        }
    }
    
    /**
     * Poll AMI for data and broadcast to clients
     */
    private function pollAndBroadcast(): void
    {
        if (!$this->amiConnected || $this->amiConnection === null) {
            // Try to reconnect
            $this->connectToAmi();
            if (!$this->amiConnected) {
                return;
            }
        }
        
        if (empty($this->clients)) {
            // No clients connected, skip polling
            return;
        }
        
        try {
            // Poll node status data
            $data = $this->fetchNodeStatus();
            
            if ($data !== null) {
                $this->lastData = $data;
                $this->broadcast($data);
            }
            
        } catch (Exception $e) {
            $this->logger->error("Error polling AMI for node", [
                'node_id' => $this->nodeId,
                'error' => $e->getMessage()
            ]);
            
            // Mark AMI as disconnected, will reconnect on next poll
            $this->amiConnected = false;
        }
    }
    
    /**
     * Fetch node status from AMI
     */
    private function fetchNodeStatus(): ?string
    {
        if ($this->amiConnection === null) {
            return null;
        }
        
        try {
            // Get XStat data
            $xstatResponse = \SimpleAmiClient::action($this->amiConnection, "RptStatus", [
                "COMMAND" => "XStat",
                "NODE" => $this->nodeId
            ]);
            
            if ($xstatResponse === false) {
                return null;
            }
            
            // Get SawStat data
            $sawStatResponse = \SimpleAmiClient::action($this->amiConnection, "RptStatus", [
                "COMMAND" => "SawStat",
                "NODE" => $this->nodeId
            ]);
            
            // Combine and format as JSON
            $data = [
                'node' => $this->nodeId,
                'timestamp' => time(),
                'xstat' => $xstatResponse,
                'sawstat' => $sawStatResponse !== false ? $sawStatResponse : ''
            ];
            
            return json_encode($data);
            
        } catch (Exception $e) {
            $this->logger->error("Error fetching node status", [
                'node_id' => $this->nodeId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Broadcast data to all connected clients
     */
    private function broadcast(string $data): void
    {
        foreach ($this->clients as $client) {
            try {
                $client->send($data);
            } catch (Exception $e) {
                $this->logger->error("Error sending data to client", [
                    'node_id' => $this->nodeId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Handle new WebSocket connection
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients[$conn->resourceId] = $conn;
        
        $this->logger->info("Client connected to node WebSocket", [
            'node_id' => $this->nodeId,
            'client_id' => $conn->resourceId,
            'total_clients' => count($this->clients)
        ]);
        
        // Send last known data immediately if available
        if ($this->lastData !== null) {
            $conn->send($this->lastData);
        }
    }
    
    /**
     * Handle incoming WebSocket message
     */
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        // Handle client messages (subscribe/unsubscribe, etc.)
        try {
            $data = json_decode($msg, true);
            
            if (isset($data['action'])) {
                switch ($data['action']) {
                    case 'ping':
                        $from->send(json_encode(['action' => 'pong']));
                        break;
                    case 'subscribe':
                        // Already subscribed on connection
                        $from->send(json_encode(['action' => 'subscribed', 'node' => $this->nodeId]));
                        break;
                }
            }
        } catch (Exception $e) {
            $this->logger->warning("Error processing client message", [
                'node_id' => $this->nodeId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle WebSocket connection close
     */
    public function onClose(ConnectionInterface $conn): void
    {
        unset($this->clients[$conn->resourceId]);
        
        $this->logger->info("Client disconnected from node WebSocket", [
            'node_id' => $this->nodeId,
            'client_id' => $conn->resourceId,
            'total_clients' => count($this->clients)
        ]);
    }
    
    /**
     * Handle WebSocket errors
     */
    public function onError(ConnectionInterface $conn, Exception $e): void
    {
        $this->logger->error("WebSocket error for node", [
            'node_id' => $this->nodeId,
            'client_id' => $conn->resourceId,
            'error' => $e->getMessage()
        ]);
        
        $conn->close();
    }
    
    /**
     * Cleanup: close AMI connection
     */
    public function cleanup(): void
    {
        $this->logger->info("Cleaning up WebSocket service for node", [
            'node_id' => $this->nodeId
        ]);
        
        if ($this->amiConnection !== null) {
            try {
                $host = $this->nodeConfig['host'] ?? 'localhost:5038';
                $user = $this->nodeConfig['user'] ?? 'admin';
                \SimpleAmiClient::returnConnection($this->amiConnection, $host, $user);
            } catch (Exception $e) {
                $this->logger->error("Error returning AMI connection", [
                    'node_id' => $this->nodeId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->amiConnection = null;
        $this->amiConnected = false;
    }
}

