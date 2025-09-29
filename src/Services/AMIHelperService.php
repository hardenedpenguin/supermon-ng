<?php

declare(strict_types=1);

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;

/**
 * AMI (Asterisk Manager Interface) Helper Service
 * 
 * Modernized service for standardizing AMI connections and command execution
 * with proper error handling and connection management.
 */
class AMIHelperService
{
    private array $connections = [];
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Connect to a node via AMI
     */
    public function connectToNode(string|array $nodeConfig): mixed
    {
        if (is_string($nodeConfig)) {
            // If string provided, treat as node ID and get config
            $nodeConfig = $this->getNodeConfig($nodeConfig);
            if (!$nodeConfig) {
                $this->logger->error("Node configuration not found", ['node' => $nodeConfig]);
                return false;
            }
        }
        
        if (!isset($nodeConfig['host']) || !isset($nodeConfig['user']) || !isset($nodeConfig['passwd'])) {
            $this->logger->error("Incomplete node configuration", ['config' => $nodeConfig]);
            return false;
        }
        
        $host = $nodeConfig['host'];
        
        // Reuse existing connection if available
        if (isset($this->connections[$host])) {
            return $this->connections[$host];
        }
        
        // Include AMI functions for connection
        require_once __DIR__ . '/../../../includes/amifunctions.inc';
        
        $fp = \SimpleAmiClient::connect($host);
        if ($fp === false) {
            $this->logger->error("Failed to connect to AMI", ['host' => $host]);
            return false;
        }
        
        if (\SimpleAmiClient::login($fp, $nodeConfig['user'], $nodeConfig['passwd']) === false) {
            \SimpleAmiClient::logoff($fp);
            $this->logger->error("Failed to login to AMI", ['host' => $host, 'user' => $nodeConfig['user']]);
            return false;
        }
        
        $this->connections[$host] = $fp;
        return $fp;
    }
    
    /**
     * Execute a command via AMI
     */
    public function executeCommand($connection, string $command): mixed
    {
        if (!is_resource($connection)) {
            $this->logger->error("Invalid AMI connection provided");
            return false;
        }
        
        require_once __DIR__ . '/../../../includes/amifunctions.inc';
        
        $result = \SimpleAmiClient::command($connection, $command);
        if ($result === false) {
            $this->logger->error("AMI command failed", ['command' => $command]);
        }
        
        return $result;
    }
    
    /**
     * Disconnect from AMI
     */
    public function disconnect($connection): void
    {
        if (is_resource($connection)) {
            require_once __DIR__ . '/../../../includes/amifunctions.inc';
            \SimpleAmiClient::logoff($connection);
            
            // Remove from connection pool
            foreach ($this->connections as $host => $conn) {
                if ($conn === $connection) {
                    unset($this->connections[$host]);
                    break;
                }
            }
        }
    }
    
    /**
     * Get node configuration (placeholder - should be implemented based on your config system)
     */
    private function getNodeConfig(string $nodeId): array|false
    {
        // This would typically load from your configuration service
        // For now, return false to maintain compatibility
        return false;
    }
    
    /**
     * Disconnect all connections
     */
    public function disconnectAll(): void
    {
        foreach ($this->connections as $connection) {
            $this->disconnect($connection);
        }
        $this->connections = [];
    }
}
