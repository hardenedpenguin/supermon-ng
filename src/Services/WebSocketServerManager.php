<?php

namespace SupermonNg\Services;

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\LoopInterface;
use React\EventLoop\Factory as LoopFactory;
use Psr\Log\LoggerInterface;
use SupermonNg\Services\AstdbCacheService;
use SupermonNg\Services\WebSocketRouterService;
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
    private AstdbCacheService $astdbService;
    private LoopInterface $loop;
    
    /** @var NodeWebSocketService[] */
    private array $nodeServices = [];
    
    /** @var IoServer[] */
    private array $servers = [];
    
    private int $basePort;
    private array $nodePorts = []; // Maps nodeId => port
    private ?IoServer $routerServer = null; // Single router server on basePort
    
    public function __construct(
        LoggerInterface $logger,
        AllStarConfigService $configService,
        AstdbCacheService $astdbService,
        int $basePort = 8105
    ) {
        $this->logger = $logger;
        $this->configService = $configService;
        $this->astdbService = $astdbService;
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
     * Get node service by node ID
     */
    public function getNodeService(string $nodeId): ?NodeWebSocketService
    {
        return $this->nodeServices[$nodeId] ?? null;
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
        
        // Create WebSocket service for each node (as internal services, not separate servers)
        foreach ($nodes as $node) {
            $nodeId = $node['id'];
            
            try {
                // Get full node configuration
                $nodeConfig = $this->configService->getNodeConfig($nodeId, null);
                
                // Calculate port for reference (though we won't create separate servers)
                $portOffset = count($this->nodeServices);
                $port = $this->basePort + $portOffset;
                $this->nodePorts[$nodeId] = $port;
                
                // Create node WebSocket service (internal service, not a separate server)
                $nodeService = new NodeWebSocketService(
                    $nodeId,
                    $nodeConfig,
                    $port, // Port for reference only
                    $this->logger,
                    $this->loop,
                    $this->astdbService
                );
                
                $this->nodeServices[$nodeId] = $nodeService;
                
                $this->logger->info("Created WebSocket service for node", [
                    'node_id' => $nodeId,
                    'port_reference' => $port,
                    'host' => $nodeConfig['host'] ?? 'unknown'
                ]);
                
                // Start AMI polling for this node
                $nodeService->start();
                
            } catch (Exception $e) {
                $this->logger->error("Failed to create WebSocket service for node", [
                    'node_id' => $nodeId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Create single router server on base port (8105) that routes based on node ID in path
        $this->createRouterServer();
        
        $this->logger->info("WebSocket Server Manager started", [
            'total_servers' => count($this->servers),
            'router_port' => $this->basePort,
            'ports' => $this->nodePorts
        ]);
    }
    
    /**
     * Create a router server on the base port that routes connections based on node ID
     */
    private function createRouterServer(): void
    {
        try {
            $routerService = new WebSocketRouterService($this->logger, $this);
            
            // Create Ratchet server with router
            $wsServer = new WsServer($routerService);
            $httpServer = new HttpServer($wsServer);
            $server = IoServer::factory($httpServer, $this->basePort, '0.0.0.0', $this->loop);
            
            $this->routerServer = $server;
            
            $this->logger->info("WebSocket router server started", [
                'port' => $this->basePort,
                'nodes' => array_keys($this->nodeServices)
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to start WebSocket router server", [
                'port' => $this->basePort,
                'error' => $e->getMessage()
            ]);
        }
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
        
        // Stop router server
        if ($this->routerServer instanceof IoServer) {
            try {
                $this->routerServer->socket->close();
            } catch (Exception $e) {
                $this->logger->error("Error stopping router server", [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Stop all individual node servers (if any)
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

