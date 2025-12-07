<?php

namespace SupermonNg\Services;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use SupermonNg\Services\AstdbCacheService;
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
    
    /** @var ConnectionInterface[] */
    private array $clients = [];
    
    private $amiConnection = null;
    private bool $amiConnected = false;
    private int $pollInterval = 1; // seconds
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
            // No clients connected, skip polling
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
            
            // Parse the AMI responses into structured data
            $parsedData = $this->parseNodeAmiData($xstatResponse, $sawStatResponse !== false ? $sawStatResponse : '');
            
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
     * Parse XStat and SawStat responses into structured data
     * Similar to NodeController::parseNodeAmiData but adapted for WebSocket service
     */
    private function parseNodeAmiData(string $rptStatus, string $sawStatus): array
    {
        $parsedVars = [];
        $conns = [];
        $keyups = [];
        $modes = [];
        $allLinkedNodes = [];
        
        // Parse XStat response for Var: lines
        if (!empty($rptStatus)) {
            $lines = explode("\n", $rptStatus);
            foreach ($lines as $line) {
                $line = trim($line);
                // Parse Var: lines
                if (strpos($line, 'Var: ') === 0) {
                    $varLine = substr($line, 5);
                    if (strpos($varLine, '=') !== false) {
                        list($key, $value) = explode('=', $varLine, 2);
                        $parsedVars[trim($key)] = trim($value);
                    }
                }
                
                // Parse Conn: lines
                if (strpos($line, 'Conn: ') === 0) {
                    $connLine = substr($line, 6);
                    $data = preg_split('/\s+/', $connLine);
                    if (!empty($data[0])) {
                        $conns[] = $data;
                    }
                }
                
                // Parse LinkedNodes
                if (preg_match("/LinkedNodes: (.*)/", $line, $matches)) {
                    $longRangeLinks = preg_split("/, /", trim($matches[1]));
                    foreach ($longRangeLinks as $link) {
                        if (!empty($link)) {
                            $n_val = substr($link, 1);
                            $connectionType = substr($link, 0, 1);
                            $modes[$n_val]['mode'] = $connectionType;
                            
                            if (is_numeric($n_val) && intval($n_val) >= 2000) {
                                $allLinkedNodes[] = $n_val;
                            }
                        }
                    }
                }
            }
        }
        
        // Parse SawStat response for keyed timing data
        if (!empty($sawStatus)) {
            $lines = explode("\n", $sawStatus);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                if (strpos($line, 'Conn: ') === 0) {
                    $connLine = substr($line, 6);
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
        
        // Build remote nodes array
        $remoteNodes = [];
        $ECHOLINK_NODE_THRESHOLD = 3000000;
        
        // Collect all node IDs first for batch lookup (like NodeController does)
        $nodeIds = [];
        foreach ($conns as $connData) {
            $n = $connData[0];
            if (!empty($n)) {
                $nodeIds[] = (string)$n;
            }
        }
        
        // Batch lookup node info from ASTDB (more efficient than individual lookups)
        $nodeInfoMap = [];
        if (!empty($nodeIds)) {
            $nodeInfoMap = $this->astdbService->getMultipleNodeInfo($nodeIds);
        }
        
        foreach ($conns as $connData) {
            $n = $connData[0];
            if (empty($n)) continue;
            
            $ip = $connData[1] ?? '';
            $port = $connData[2] ?? '';
            $direction = $connData[3] ?? '';
            $elapsed = $connData[4] ?? '';
            $status = $connData[5] ?? '';
            
            $isEcholink = (is_numeric($n) && $n > $ECHOLINK_NODE_THRESHOLD && empty($ip));
            
            // Get node info from batch lookup map (ensure node ID is string for lookup)
            $nodeIdStr = (string)$n;
            $nodeInfo = $nodeInfoMap[$nodeIdStr] ?? null;
            $info = "Node $n";
            if ($nodeInfo) {
                $info = trim(($nodeInfo['callsign'] ?? '') . ' ' . ($nodeInfo['description'] ?? '') . ' ' . ($nodeInfo['location'] ?? ''));
            }
            
            $remoteNode = [
                'node' => $n,
                'info' => $info,
                'ip' => $isEcholink ? "" : $ip,
                'direction' => $isEcholink ? ($connData[2] ?? '') : $direction,
                'elapsed' => $isEcholink ? ($connData[3] ?? '') : $elapsed,
                'link' => $isEcholink ? ($connData[4] ?? 'UNKNOWN') : $status,
                'keyed' => 'n/a',
                'last_keyed' => '-1',
                'mode' => $isEcholink ? 'Echolink' : 'Allstar'
            ];
            
            // Handle Echolink connection status
            if ($isEcholink && isset($modes[$n]['mode'])) {
                $remoteNode['link'] = ($modes[$n]['mode'] == 'C') ? "CONNECTING" : "ESTABLISHED";
            }
            
            // Use keyed timing data from SawStat
            if (isset($keyups[$n])) {
                $remoteNode['keyed'] = ($keyups[$n]['isKeyed'] == 1) ? 'yes' : 'no';
                $remoteNode['last_keyed'] = $keyups[$n]['keyed'];
            }
            
            // Set mode from LinkedNodes
            if (isset($modes[$n])) {
                $remoteNode['mode'] = $modes[$n]['mode'];
            }
            
            $remoteNodes[] = $remoteNode;
        }
        
        // Extract main node stats
        $mainNodeCosKeyed = ($parsedVars['RPT_RXKEYED'] ?? '0') === '1' ? 1 : 0;
        $mainNodeTxKeyed = ($parsedVars['RPT_TXKEYED'] ?? '0') === '1' ? 1 : 0;
        
        return [
            'cos_keyed' => $mainNodeCosKeyed,
            'tx_keyed' => $mainNodeTxKeyed,
            'cpu_temp' => $parsedVars['cpu_temp'] ?? null,
            'cpu_up' => $parsedVars['cpu_up'] ?? null,
            'cpu_load' => $parsedVars['cpu_load'] ?? null,
            'ALERT' => $parsedVars['ALERT'] ?? null,
            'WX' => $parsedVars['WX'] ?? null,
            'DISK' => $parsedVars['DISK'] ?? null,
            'remote_nodes' => $remoteNodes
        ];
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
        
        if ($this->amiConnection !== null) {
            try {
                // Close dedicated connection (not returning to pool)
                \SimpleAmiClient::logoff($this->amiConnection);
            } catch (Exception $e) {
                $this->logger->error("Error closing AMI connection", [
                    'node_id' => $this->nodeId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->amiConnection = null;
        $this->amiConnected = false;
    }
}

