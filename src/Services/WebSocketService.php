<?php

declare(strict_types=1);

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

/**
 * WebSocket service for real-time updates
 * Replaces polling with push-based notifications
 */
class WebSocketService implements MessageComponentInterface
{
    private LoggerInterface $logger;
    private array $clients = [];
    private array $subscribedTopics = [];
    private array $userSessions = [];
    
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    /**
     * Start the WebSocket server
     */
    public function start(int $port = 8080): void
    {
        $this->logger->info("Starting WebSocket server on port $port");
        
        $server = IoServer::factory(
            new HttpServer(
                new WsServer($this)
            ),
            $port
        );
        
        $server->run();
    }
    
    /**
     * Handle new WebSocket connections
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients[$conn->resourceId] = $conn;
        $this->logger->info("New WebSocket connection", ['resource_id' => $conn->resourceId]);
        
        // Send welcome message
        $conn->send(json_encode([
            'type' => 'connection',
            'status' => 'connected',
            'message' => 'WebSocket connection established'
        ]));
    }
    
    /**
     * Handle incoming WebSocket messages
     */
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['type'])) {
            $from->send(json_encode([
                'type' => 'error',
                'message' => 'Invalid message format'
            ]));
            return;
        }
        
        switch ($data['type']) {
            case 'subscribe':
                $this->handleSubscription($from, $data);
                break;
                
            case 'unsubscribe':
                $this->handleUnsubscription($from, $data);
                break;
                
            case 'auth':
                $this->handleAuthentication($from, $data);
                break;
                
            case 'ping':
                $from->send(json_encode(['type' => 'pong']));
                break;
                
            default:
                $from->send(json_encode([
                    'type' => 'error',
                    'message' => 'Unknown message type'
                ]));
        }
    }
    
    /**
     * Handle WebSocket disconnections
     */
    public function onClose(ConnectionInterface $conn): void
    {
        $resourceId = $conn->resourceId;
        
        // Clean up subscriptions
        foreach ($this->subscribedTopics as $topic => $subscribers) {
            if (isset($subscribers[$resourceId])) {
                unset($this->subscribedTopics[$topic][$resourceId]);
                
                // Remove topic if no subscribers
                if (empty($this->subscribedTopics[$topic])) {
                    unset($this->subscribedTopics[$topic]);
                }
            }
        }
        
        // Clean up user session
        if (isset($this->userSessions[$resourceId])) {
            unset($this->userSessions[$resourceId]);
        }
        
        // Remove client
        unset($this->clients[$resourceId]);
        
        $this->logger->info("WebSocket connection closed", ['resource_id' => $resourceId]);
    }
    
    /**
     * Handle WebSocket errors
     */
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $this->logger->error("WebSocket error", [
            'resource_id' => $conn->resourceId,
            'error' => $e->getMessage()
        ]);
        
        $conn->close();
    }
    
    /**
     * Handle topic subscription
     */
    private function handleSubscription(ConnectionInterface $conn, array $data): void
    {
        if (!isset($data['topic'])) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Topic required for subscription'
            ]));
            return;
        }
        
        $topic = $data['topic'];
        $resourceId = $conn->resourceId;
        
        // Initialize topic if not exists
        if (!isset($this->subscribedTopics[$topic])) {
            $this->subscribedTopics[$topic] = [];
        }
        
        // Add subscription
        $this->subscribedTopics[$topic][$resourceId] = $conn;
        
        $this->logger->info("Client subscribed to topic", [
            'resource_id' => $resourceId,
            'topic' => $topic
        ]);
        
        $conn->send(json_encode([
            'type' => 'subscribed',
            'topic' => $topic,
            'message' => "Subscribed to $topic"
        ]));
    }
    
    /**
     * Handle topic unsubscription
     */
    private function handleUnsubscription(ConnectionInterface $conn, array $data): void
    {
        if (!isset($data['topic'])) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Topic required for unsubscription'
            ]));
            return;
        }
        
        $topic = $data['topic'];
        $resourceId = $conn->resourceId;
        
        if (isset($this->subscribedTopics[$topic][$resourceId])) {
            unset($this->subscribedTopics[$topic][$resourceId]);
            
            // Remove topic if no subscribers
            if (empty($this->subscribedTopics[$topic])) {
                unset($this->subscribedTopics[$topic]);
            }
            
            $this->logger->info("Client unsubscribed from topic", [
                'resource_id' => $resourceId,
                'topic' => $topic
            ]);
            
            $conn->send(json_encode([
                'type' => 'unsubscribed',
                'topic' => $topic,
                'message' => "Unsubscribed from $topic"
            ]));
        }
    }
    
    /**
     * Handle WebSocket authentication
     */
    private function handleAuthentication(ConnectionInterface $conn, array $data): void
    {
        if (!isset($data['token'])) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Authentication token required'
            ]));
            return;
        }
        
        // Validate token (implement your auth logic here)
        $user = $this->validateToken($data['token']);
        
        if ($user) {
            $this->userSessions[$conn->resourceId] = $user;
            
            $conn->send(json_encode([
                'type' => 'authenticated',
                'user' => $user,
                'message' => 'Authentication successful'
            ]));
            
            $this->logger->info("WebSocket client authenticated", [
                'resource_id' => $conn->resourceId,
                'user' => $user['username'] ?? 'unknown'
            ]);
        } else {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Authentication failed'
            ]));
        }
    }
    
    /**
     * Broadcast message to all subscribers of a topic
     */
    public function broadcastToTopic(string $topic, array $data): int
    {
        $sent = 0;
        
        if (!isset($this->subscribedTopics[$topic])) {
            return $sent;
        }
        
        $message = json_encode($data);
        
        foreach ($this->subscribedTopics[$topic] as $resourceId => $conn) {
            try {
                $conn->send($message);
                $sent++;
            } catch (\Exception $e) {
                $this->logger->warning("Failed to send message to client", [
                    'resource_id' => $resourceId,
                    'topic' => $topic,
                    'error' => $e->getMessage()
                ]);
                
                // Remove failed connection
                unset($this->subscribedTopics[$topic][$resourceId]);
                unset($this->clients[$resourceId]);
            }
        }
        
        // Clean up topic if no subscribers
        if (empty($this->subscribedTopics[$topic])) {
            unset($this->subscribedTopics[$topic]);
        }
        
        $this->logger->debug("Broadcast message to topic", [
            'topic' => $topic,
            'sent_count' => $sent,
            'total_subscribers' => count($this->subscribedTopics[$topic] ?? [])
        ]);
        
        return $sent;
    }
    
    /**
     * Send message to specific user
     */
    public function sendToUser(string $username, array $data): bool
    {
        foreach ($this->userSessions as $resourceId => $user) {
            if (($user['username'] ?? '') === $username) {
                if (isset($this->clients[$resourceId])) {
                    try {
                        $this->clients[$resourceId]->send(json_encode($data));
                        return true;
                    } catch (\Exception $e) {
                        $this->logger->warning("Failed to send message to user", [
                            'username' => $username,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get connection statistics
     */
    public function getStats(): array
    {
        return [
            'total_connections' => count($this->clients),
            'authenticated_users' => count($this->userSessions),
            'active_topics' => count($this->subscribedTopics),
            'topics' => array_keys($this->subscribedTopics)
        ];
    }
    
    /**
     * Validate authentication token
     */
    private function validateToken(string $token): ?array
    {
        // Implement your token validation logic here
        // This is a placeholder - you'll need to implement actual JWT validation
        // For now, we'll do basic session validation
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Simple token validation (replace with proper JWT validation)
        if ($token === 'demo-token' || isset($_SESSION['user'])) {
            return [
                'username' => $_SESSION['user'] ?? 'demo-user',
                'authenticated' => true,
                'timestamp' => time()
            ];
        }
        
        return null;
    }
}
