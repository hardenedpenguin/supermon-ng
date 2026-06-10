<?php

namespace SupermonNg\Services;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use SupermonNg\Services\AstdbCacheService;
use SupermonNg\Services\Ami\AmiXstatParserService;
use Exception;

/**
 * Node WebSocket Service
 * 
 * Manages WebSocket connections for a single node.
 * Maintains dedicated persistent AMI connection (not using connection pool)
 * and broadcasts real-time data. Matches Allmon3's per-node WebSocket architecture.
 * 
 * Note: Uses dedicated AMI connections instead of the connection pool to avoid
 * hogging pool connections that should be available for short-lived API requests.
 */
class NodeWebSocketService implements MessageComponentInterface
{
    private string $nodeId;
    private array $nodeConfig;
    private int $port;
    private LoggerInterface $logger;
    private LoopInterface $loop;
    private AstdbCacheService $astdbService;
    private AmiXstatParserService $amiParser;
    
    /** @var ConnectionInterface[] */
    private array $clients = [];
    
    private $amiConnection = null;
    private bool $amiConnected = false;
    private int $pollInterval = 1; // seconds
    private $pollTimer = null;
    private ?array $lastData = null; // Changed to array for diffing
    private int $lastAmiReconnectAttempt = 0; // Timestamp of last reconnect attempt
    private int $amiReconnectBackoff = 30; // Seconds between reconnect attempts
    private bool $amiReconnectLogged = false; // Track if we've logged the reconnect failure
    
    public function __construct(
        string $nodeId,
        array $nodeConfig,
        int $port,
        LoggerInterface $logger,
        LoopInterface $loop,
        AstdbCacheService $astdbService
    ) {
        $this->nodeId = $nodeId;
        $this->nodeConfig = $nodeConfig;
        $this->port = $port;
        $this->logger = $logger;
        $this->loop = $loop;
        $this->astdbService = $astdbService;
        $this->amiParser = new AmiXstatParserService();
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
     * Register the WebSocket service (AMI connects when the first client joins)
     */
    public function start(): void
    {
        $this->logger->info("Starting WebSocket service for node", [
            'node_id' => $this->nodeId,
            'port' => $this->port,
            'host' => $this->nodeConfig['host'] ?? 'unknown'
        ]);
    }

    /**
     * Start AMI polling when clients are connected
     */
    private function ensureAmiPolling(): void
    {
        if ($this->pollTimer !== null) {
            return;
        }

        $this->pollTimer = $this->loop->addPeriodicTimer($this->pollInterval, function () {
            $this->pollAndBroadcast();
        });

        $this->connectToAmi();
        $this->pollAndBroadcast();
    }

    /**
     * Stop AMI polling and disconnect when no clients remain
     */
    private function stopAmiPollingIfIdle(): void
    {
        if (!empty($this->clients)) {
            return;
        }

        if ($this->pollTimer !== null) {
            $this->loop->cancelTimer($this->pollTimer);
            $this->pollTimer = null;
        }

        $this->disconnectAmi();
    }

    /**
     * Close the dedicated AMI connection
     */
    private function disconnectAmi(): void
    {
        if ($this->amiConnection !== null) {
            try {
                \SimpleAmiClient::logoff($this->amiConnection);
            } catch (Exception $e) {
                $this->logger->debug("Error closing AMI connection", [
                    'node_id' => $this->nodeId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->amiConnection = null;
        $this->amiConnected = false;
    }
    
    /**
     * Connect to AMI for this node using dedicated persistent connection
     * (not using connection pool to avoid hogging pool connections)
     */
    private function connectToAmi(): void
    {
        if ($this->amiConnected && $this->amiConnection !== null) {
            return;
        }
        
        // Implement backoff: only attempt reconnection every N seconds
        $now = time();
        if ($now - $this->lastAmiReconnectAttempt < $this->amiReconnectBackoff) {
            return; // Too soon to retry
        }
        $this->lastAmiReconnectAttempt = $now;
        
        try {
            require_once __DIR__ . '/../../includes/amifunctions.inc';
            
            $host = $this->nodeConfig['host'] ?? 'localhost:5038';
            $user = $this->nodeConfig['user'] ?? 'admin';
            $password = $this->nodeConfig['passwd'] ?? '';
            
            // Use dedicated connection (not connection pool) for persistent WebSocket service
            $this->amiConnection = \SimpleAmiClient::connect($host);
            
            if ($this->amiConnection === false) {
                // Only log once per backoff period to reduce noise
                if (!$this->amiReconnectLogged) {
                    $this->logger->warning("Failed to connect to AMI for node (will retry every {$this->amiReconnectBackoff}s)", [
                        'node_id' => $this->nodeId,
                        'host' => $host
                    ]);
                    $this->amiReconnectLogged = true;
                }
                $this->amiConnected = false;
                return;
            }
            
            // Login to AMI
            if (\SimpleAmiClient::login($this->amiConnection, $user, $password) === false) {
                if (!$this->amiReconnectLogged) {
                    $this->logger->warning("Failed to login to AMI for node (will retry every {$this->amiReconnectBackoff}s)", [
                        'node_id' => $this->nodeId,
                        'host' => $host
                    ]);
                    $this->amiReconnectLogged = true;
                }
                \SimpleAmiClient::logoff($this->amiConnection);
                $this->amiConnection = null;
                $this->amiConnected = false;
                return;
            }
            
            $this->amiConnected = true;
            $this->amiReconnectLogged = false; // Reset on successful connection
            $this->logger->info("Connected to AMI for node (dedicated connection)", [
                'node_id' => $this->nodeId,
                'host' => $host
            ]);
            
        } catch (Exception $e) {
            if (!$this->amiReconnectLogged) {
                $this->logger->warning("AMI connection error for node (will retry every {$this->amiReconnectBackoff}s)", [
                    'node_id' => $this->nodeId,
                    'error' => $e->getMessage(),
                    'host' => $this->nodeConfig['host'] ?? 'unknown'
                ]);
                $this->amiReconnectLogged = true;
            }
            $this->amiConnected = false;
            if ($this->amiConnection !== null) {
                try {
                    \SimpleAmiClient::logoff($this->amiConnection);
                } catch (Exception $e2) {
                    // Ignore cleanup errors
                }
                $this->amiConnection = null;
            }
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
            $this->stopAmiPollingIfIdle();
            return;
        }
        
        try {
            // Poll node status data
            $data = $this->fetchNodeStatus();
            
            if ($data !== null) {
                // Only broadcast if data changed (data diffing)
                if ($this->hasDataChanged($data)) {
                    $this->lastData = $data;
                    $this->broadcast(json_encode($data));
                }
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
     * Fetch and parse node status from AMI
     * Returns structured data array or null on error
     */
    private function fetchNodeStatus(): ?array
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
            
            $parsed = $this->amiParser->parse(
                $xstatResponse,
                $sawStatResponse !== false ? $sawStatResponse : ''
            );
            $nodeIds = array_map(static fn (array $c) => (string) $c[0], $parsed->conns);
            $infoMap = $nodeIds !== [] ? $this->astdbService->getMultipleNodeInfo($nodeIds) : [];
            $resolveInfo = AmiXstatParserService::astdbInfoResolver($this->astdbService, $infoMap);
            $parsedData = $this->amiParser->buildWebSocketPayload($parsed, $this->nodeId, $resolveInfo);
            
            // Build structured response
            $data = [
                'node' => $this->nodeId,
                'timestamp' => time(),
                'status' => 'online',
                'cos_keyed' => $parsedData['cos_keyed'] ?? 0,
                'tx_keyed' => $parsedData['tx_keyed'] ?? 0,
                'cpu_temp' => $parsedData['cpu_temp'] ?? null,
                'cpu_up' => $parsedData['cpu_up'] ?? null,
                'cpu_load' => $parsedData['cpu_load'] ?? null,
                'ALERT' => $parsedData['ALERT'] ?? null,
                'WX' => $parsedData['WX'] ?? null,
                'DISK' => $parsedData['DISK'] ?? null,
                'remote_nodes' => $parsedData['remote_nodes'] ?? []
            ];
            
            return $data;
            
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
     * Check if data has changed compared to last broadcast
     * Implements data diffing to reduce unnecessary broadcasts
     */
    private function hasDataChanged(array $newData): bool
    {
        if ($this->lastData === null) {
            return true; // First time, always send
        }
        
        // Compare key fields that matter for UI updates
        $keyFields = ['cos_keyed', 'tx_keyed', 'cpu_temp', 'cpu_up', 'cpu_load', 'ALERT', 'WX', 'DISK'];
        
        foreach ($keyFields as $field) {
            if (($newData[$field] ?? null) !== ($this->lastData[$field] ?? null)) {
                return true;
            }
        }
        
        // Compare remote nodes count and keyed status
        $newRemoteCount = count($newData['remote_nodes'] ?? []);
        $oldRemoteCount = count($this->lastData['remote_nodes'] ?? []);
        
        if ($newRemoteCount !== $oldRemoteCount) {
            return true;
        }
        
        // Compare keyed status of remote nodes
        foreach ($newData['remote_nodes'] ?? [] as $newNode) {
            $nodeId = $newNode['node'] ?? null;
            if ($nodeId === null) continue;
            
            $oldNode = null;
            foreach ($this->lastData['remote_nodes'] ?? [] as $old) {
                if (($old['node'] ?? null) === $nodeId) {
                    $oldNode = $old;
                    break;
                }
            }
            
            // New node or keyed status changed
            if ($oldNode === null || ($newNode['keyed'] ?? 'n/a') !== ($oldNode['keyed'] ?? 'n/a')) {
                return true;
            }
        }
        
        return false; // No significant changes
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

        $this->ensureAmiPolling();
        
        // Send last known data immediately if available
        if ($this->lastData !== null) {
            $conn->send(json_encode($this->lastData));
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

        $this->stopAmiPollingIfIdle();
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
     * Cleanup: close dedicated AMI connection
     */
    public function cleanup(): void
    {
        $this->logger->info("Cleaning up WebSocket service for node", [
            'node_id' => $this->nodeId
        ]);
        
        if ($this->pollTimer !== null) {
            $this->loop->cancelTimer($this->pollTimer);
            $this->pollTimer = null;
        }

        $this->disconnectAmi();
    }
}

