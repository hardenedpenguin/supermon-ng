<?php

declare(strict_types=1);

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;

/**
 * Service for batching multiple AMI commands to reduce connection overhead
 */
class AmiBatchService
{
    private LoggerInterface $logger;
    
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    /**
     * Execute multiple AMI commands in a single connection
     */
    public function executeBatch(array $nodeConfig, array $commands): array
    {
        $host = $nodeConfig['host'];
        $user = $nodeConfig['user'] ?? '';
        $password = $nodeConfig['passwd'] ?? '';
        
        $this->logger->debug("Executing AMI batch", [
            'host' => $host,
            'command_count' => count($commands)
        ]);
        
        // Get connection from pool
        $fp = \SimpleAmiClient::getConnection($host, $user, $password);
        if ($fp === false) {
            $this->logger->error("Failed to get AMI connection for batch", ['host' => $host]);
            return array_fill(0, count($commands), ['error' => 'Connection failed']);
        }
        
        try {
            $results = [];
            
            foreach ($commands as $index => $command) {
                $this->logger->debug("Executing batch command", [
                    'index' => $index,
                    'command' => $command
                ]);
                
                $result = \SimpleAmiClient::command($fp, $command);
                
                if ($result === false) {
                    $results[] = [
                        'command' => $command,
                        'success' => false,
                        'error' => 'Command execution failed',
                        'index' => $index
                    ];
                } else {
                    $results[] = [
                        'command' => $command,
                        'success' => true,
                        'result' => $result,
                        'index' => $index
                    ];
                }
                
                // Small delay between commands to avoid overwhelming AMI
                usleep(15000); // 15ms
            }
            
            return $results;
            
        } finally {
            // Return connection to pool
            \SimpleAmiClient::returnConnection($fp, $host, $user);
        }
    }
    
    /**
     * Execute node data collection commands in batch
     */
    public function collectNodeData(array $nodeConfig, string $nodeId): array
    {
        $commands = [
            "rpt cmd {$nodeId} info",
            "rpt cmd {$nodeId} stat",
            "rpt cmd {$nodeId} cos",
            "rpt cmd {$nodeId} tx",
            "rpt cmd {$nodeId} temp",
            "rpt cmd {$nodeId} up",
            "rpt cmd {$nodeId} load",
            "rpt cmd {$nodeId} alert",
            "rpt cmd {$nodeId} wx",
            "rpt cmd {$nodeId} disk",
            "rpt cmd {$nodeId} nodes"
        ];
        
        return $this->executeBatch($nodeConfig, $commands);
    }
    
    /**
     * Execute reload commands in batch
     */
    public function executeReloadBatch(array $nodeConfig): array
    {
        $commands = [
            "rpt reload",
            "iax2 reload", 
            "extensions reload"
        ];
        
        return $this->executeBatch($nodeConfig, $commands);
    }
    
    /**
     * Execute multiple node status checks in parallel batches
     */
    public function collectMultipleNodeData(array $nodeConfigs): array
    {
        $results = [];
        
        // Group commands by host to minimize connections
        $hostGroups = [];
        foreach ($nodeConfigs as $nodeId => $config) {
            $host = $config['host'];
            if (!isset($hostGroups[$host])) {
                $hostGroups[$host] = [];
            }
            $hostGroups[$host][] = $nodeId;
        }
        
        // Execute batches for each host
        foreach ($hostGroups as $host => $nodeIds) {
            $config = $nodeConfigs[$nodeIds[0]]; // Use first node's config for this host
            
            $commands = [];
            foreach ($nodeIds as $nodeId) {
                $commands = array_merge($commands, [
                    "rpt cmd {$nodeId} info",
                    "rpt cmd {$nodeId} stat"
                ]);
            }
            
            $batchResults = $this->executeBatch($config, $commands);
            
            // Organize results by node
            $nodeIndex = 0;
            foreach ($nodeIds as $nodeId) {
                $results[$nodeId] = [
                    'info' => $batchResults[$nodeIndex * 2] ?? null,
                    'stat' => $batchResults[($nodeIndex * 2) + 1] ?? null
                ];
                $nodeIndex++;
            }
        }
        
        return $results;
    }
}
