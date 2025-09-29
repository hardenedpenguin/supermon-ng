<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use SupermonNg\Services\RealtimeEventPublisher;

/**
 * WebSocket controller for managing real-time connections
 */
class WebSocketController
{
    private LoggerInterface $logger;
    private RealtimeEventPublisher $eventPublisher;
    
    public function __construct(LoggerInterface $logger, RealtimeEventPublisher $eventPublisher)
    {
        $this->logger = $logger;
        $this->eventPublisher = $eventPublisher;
    }
    
    /**
     * Get WebSocket connection information
     */
    public function getConnectionInfo(Request $request, Response $response): Response
    {
        $this->logger->info('WebSocket connection info request');
        
        // Get WebSocket server info
        $wsHost = $_ENV['WEBSOCKET_HOST'] ?? 'localhost';
        $wsPort = $_ENV['WEBSOCKET_PORT'] ?? 9091;
        $wsSecure = $_ENV['WEBSOCKET_SECURE'] ?? false;
        
        $protocol = $wsSecure ? 'wss' : 'ws';
        $wsUrl = "{$protocol}://{$wsHost}:{$wsPort}";
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'websocket_url' => $wsUrl,
                'host' => $wsHost,
                'port' => $wsPort,
                'secure' => $wsSecure,
                'supported_topics' => [
                    'node_status',
                    'node_list', 
                    'system_info',
                    'menu_update',
                    'ami_status',
                    'config_update',
                    'node_data',
                    'heartbeat',
                    'errors',
                    'stats'
                ]
            ]
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Trigger manual node status update
     */
    public function triggerNodeUpdate(Request $request, Response $response): Response
    {
        $this->logger->info('Manual node update trigger');
        
        $body = $request->getParsedBody();
        $nodeId = $body['node_id'] ?? null;
        
        if (!$nodeId) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Node ID required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        // Trigger node status update
        $this->eventPublisher->publishNodeStatus($nodeId, [
            'status' => 'updating',
            'message' => 'Manual update triggered'
        ]);
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => "Node update triggered for node $nodeId"
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Get WebSocket server statistics
     */
    public function getStats(Request $request, Response $response): Response
    {
        $this->logger->info('WebSocket stats request');
        
        // This would typically get stats from the WebSocket service
        // For now, we'll return basic info
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'server_status' => 'running',
                'connection_count' => 0, // Would be populated by actual WebSocket service
                'active_topics' => [],
                'uptime' => time() - ($_SERVER['REQUEST_TIME'] ?? time())
            ]
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Test WebSocket connection
     */
    public function testConnection(Request $request, Response $response): Response
    {
        $this->logger->info('WebSocket connection test');
        
        // Send a test event
        $this->eventPublisher->publishUserNotification('test', 'connection_test', [
            'message' => 'WebSocket connection test',
            'timestamp' => time()
        ]);
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Test event sent'
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
}
