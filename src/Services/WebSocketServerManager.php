<?php

namespace SupermonNg\Services;

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\LoopInterface;
use React\EventLoop\Factory as LoopFactory;
use Psr\Log\LoggerInterface;
use Exception;

/**
 * WebSocket Server Manager
 * 
 * Manages multiple WebSocket servers (one per node) in a single process.
 * Matches Allmon3's architecture: one service, one process, multiple WebSocket servers.
 */
class WebSocketServerManager
{
    private LoggerInterface $logger;
    private AllStarConfigService $configService;
    private LoopInterface $loop;
    
    /** @var NodeWebSocketService[] */
    private array $nodeServices = [];
    
    /** @var IoServer[] */
    private array $servers = [];
    
    private int $basePort;
    private array $nodePorts = []; // Maps nodeId => port
    
    public function __construct(
        LoggerInterface $logger,
        AllStarConfigService $configService,
        int $basePort = 8105
    ) {
        $this->logger = $logger;
        $this->configService = $configService;
        $this->basePort = $basePort;
        $this->loop = LoopFactory::create();
    }
    
    /**
     * Get the event loop
     */
    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }
    
    /**
     * Get port mapping for a node
     */
    public function getNodePort(string $nodeId): ?int
    {
        return $this->nodePorts[$nodeId] ?? null;
    }
    
    /**
     * Get all node port mappings
     */
    public function getAllNodePorts(): array
    {
        return $this->nodePorts;
    }
    
    /**
     * Start all WebSocket servers for configured nodes
     */
    public function start(): void
    {
        $this->logger->info("Starting WebSocket Server Manager", [
            'base_port' => $this->basePort
        ]);
        
        // Get all available nodes from default allmon.ini
        $nodes = $this->configService->getAvailableNodes(null);
        
        if (empty($nodes)) {
            $this->logger->warning("No nodes found in configuration");
            return;
        }
        
        $this->logger->info("Found nodes for WebSocket servers", [
            'count' => count($nodes)
        ]);
        
        $portOffset = 0;
        
        // Create WebSocket server for each node
        foreach ($nodes as $node) {
            $nodeId = $node['id'];
            
            try {
                // Get full node configuration
                $nodeConfig = $this->configService->getNodeConfig($nodeId, null);
                
                // Assign port incrementally
                $port = $this->basePort + $portOffset;
                $this->nodePorts[$nodeId] = $port;
                
                // Create node WebSocket service
                $nodeService = new NodeWebSocketService(
                    $nodeId,
                    $nodeConfig,
                    $port,
                    $this->logger,
                    $this->loop
                );
                
                // Create Ratchet server for this node
                $wsServer = new WsServer($nodeService);
                $httpServer = new HttpServer($wsServer);
                $server = IoServer::factory($httpServer, $port, '0.0.0.0', $this->loop);
                
                $this->nodeServices[$nodeId] = $nodeService;
                $this->servers[$nodeId] = $server;
                
                $this->logger->info("Started WebSocket server for node", [
                    'node_id' => $nodeId,
                    'port' => $port,
                    'host' => $nodeConfig['host'] ?? 'unknown'
                ]);
                
                // Start AMI polling for this node
                $nodeService->start();
                
                $portOffset++;
                
            } catch (Exception $e) {
                $this->logger->error("Failed to start WebSocket server for node", [
                    'node_id' => $nodeId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->logger->info("WebSocket Server Manager started", [
            'total_servers' => count($this->servers),
            'ports' => $this->nodePorts
        ]);
    }
    
    /**
     * Run the event loop
     */
    public function run(): void
    {
        $this->loop->run();
    }
    
    /**
     * Stop all servers and cleanup
     */
    public function stop(): void
    {
        $this->logger->info("Stopping WebSocket Server Manager");
        
        // Cleanup all node services
        foreach ($this->nodeServices as $nodeId => $service) {
            try {
                $service->cleanup();
            } catch (Exception $e) {
                $this->logger->error("Error cleaning up node service", [
                    'node_id' => $nodeId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Stop all servers
        foreach ($this->servers as $nodeId => $server) {
            try {
                if ($server instanceof IoServer) {
                    $server->socket->close();
                }
            } catch (Exception $e) {
                $this->logger->error("Error stopping server", [
                    'node_id' => $nodeId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Stop the event loop
        $this->loop->stop();
        
        $this->logger->info("WebSocket Server Manager stopped");
    }
    
    /**
     * Handle graceful shutdown signals
     */
    public function setupSignalHandlers(): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
            pcntl_signal(SIGHUP, [$this, 'handleShutdown']);
        }
    }
    
    /**
     * Handle shutdown signals
     */
    public function handleShutdown(int $signal): void
    {
        $this->logger->info("Received shutdown signal", ['signal' => $signal]);
        $this->stop();
        exit(0);
    }
}

