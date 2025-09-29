<?php

declare(strict_types=1);

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;

/**
 * Real-time event publisher for broadcasting updates to WebSocket clients
 * Replaces polling with push-based notifications
 */
class RealtimeEventPublisher
{
    private LoggerInterface $logger;
    private WebSocketService $webSocketService;
    
    public function __construct(LoggerInterface $logger, WebSocketService $webSocketService)
    {
        $this->logger = $logger;
        $this->webSocketService = $webSocketService;
    }
    
    /**
     * Publish node status update
     */
    public function publishNodeStatus(string $nodeId, array $statusData): void
    {
        $event = [
            'type' => 'node_status_update',
            'timestamp' => time(),
            'node_id' => $nodeId,
            'data' => $statusData
        ];
        
        $sent = $this->webSocketService->broadcastToTopic("node_status", $event);
        
        $this->logger->debug("Published node status update", [
            'node_id' => $nodeId,
            'sent_to_clients' => $sent
        ]);
    }
    
    /**
     * Publish node list update
     */
    public function publishNodeList(array $nodes): void
    {
        $event = [
            'type' => 'node_list_update',
            'timestamp' => time(),
            'data' => $nodes
        ];
        
        $sent = $this->webSocketService->broadcastToTopic("node_list", $event);
        
        $this->logger->debug("Published node list update", [
            'node_count' => count($nodes),
            'sent_to_clients' => $sent
        ]);
    }
    
    /**
     * Publish system info update
     */
    public function publishSystemInfo(array $systemInfo): void
    {
        $event = [
            'type' => 'system_info_update',
            'timestamp' => time(),
            'data' => $systemInfo
        ];
        
        $sent = $this->webSocketService->broadcastToTopic("system_info", $event);
        
        $this->logger->debug("Published system info update", [
            'sent_to_clients' => $sent
        ]);
    }
    
    /**
     * Publish menu update
     */
    public function publishMenuUpdate(string $username, array $menuData): void
    {
        $event = [
            'type' => 'menu_update',
            'timestamp' => time(),
            'username' => $username,
            'data' => $menuData
        ];
        
        $sent = $this->webSocketService->broadcastToTopic("menu_update", $event);
        
        $this->logger->debug("Published menu update", [
            'username' => $username,
            'sent_to_clients' => $sent
        ]);
    }
    
    /**
     * Publish AMI connection status
     */
    public function publishAmiStatus(string $nodeId, string $status, ?string $message = null): void
    {
        $event = [
            'type' => 'ami_status_update',
            'timestamp' => time(),
            'node_id' => $nodeId,
            'status' => $status,
            'message' => $message
        ];
        
        $sent = $this->webSocketService->broadcastToTopic("ami_status", $event);
        
        $this->logger->debug("Published AMI status update", [
            'node_id' => $nodeId,
            'status' => $status,
            'sent_to_clients' => $sent
        ]);
    }
    
    /**
     * Publish user-specific notification
     */
    public function publishUserNotification(string $username, string $type, array $data): void
    {
        $event = [
            'type' => 'user_notification',
            'timestamp' => time(),
            'notification_type' => $type,
            'data' => $data
        ];
        
        $sent = $this->webSocketService->sendToUser($username, $event);
        
        $this->logger->debug("Published user notification", [
            'username' => $username,
            'notification_type' => $type,
            'sent' => $sent
        ]);
    }
    
    /**
     * Publish error notification
     */
    public function publishError(string $errorType, string $message, ?array $context = null): void
    {
        $event = [
            'type' => 'error_notification',
            'timestamp' => time(),
            'error_type' => $errorType,
            'message' => $message,
            'context' => $context
        ];
        
        $sent = $this->webSocketService->broadcastToTopic("errors", $event);
        
        $this->logger->debug("Published error notification", [
            'error_type' => $errorType,
            'sent_to_clients' => $sent
        ]);
    }
    
    /**
     * Publish connection statistics
     */
    public function publishConnectionStats(): void
    {
        $stats = $this->webSocketService->getStats();
        
        $event = [
            'type' => 'connection_stats',
            'timestamp' => time(),
            'data' => $stats
        ];
        
        $sent = $this->webSocketService->broadcastToTopic("stats", $event);
        
        $this->logger->debug("Published connection stats", [
            'sent_to_clients' => $sent
        ]);
    }
    
    /**
     * Publish heartbeat to keep connections alive
     */
    public function publishHeartbeat(): void
    {
        $event = [
            'type' => 'heartbeat',
            'timestamp' => time(),
            'server_time' => date('c')
        ];
        
        $sent = $this->webSocketService->broadcastToTopic("heartbeat", $event);
        
        $this->logger->debug("Published heartbeat", [
            'sent_to_clients' => $sent
        ]);
    }
    
    /**
     * Publish configuration update
     */
    public function publishConfigUpdate(string $configType, array $configData): void
    {
        $event = [
            'type' => 'config_update',
            'timestamp' => time(),
            'config_type' => $configType,
            'data' => $configData
        ];
        
        $sent = $this->webSocketService->broadcastToTopic("config_update", $event);
        
        $this->logger->debug("Published config update", [
            'config_type' => $configType,
            'sent_to_clients' => $sent
        ]);
    }
    
    /**
     * Publish node data batch update
     */
    public function publishNodeDataBatch(array $nodeData): void
    {
        $event = [
            'type' => 'node_data_batch',
            'timestamp' => time(),
            'data' => $nodeData
        ];
        
        $sent = $this->webSocketService->broadcastToTopic("node_data", $event);
        
        $this->logger->debug("Published node data batch", [
            'node_count' => count($nodeData),
            'sent_to_clients' => $sent
        ]);
    }
}
