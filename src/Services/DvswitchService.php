<?php

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;
use Exception;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

/**
 * DVSwitch Service
 * 
 * Handles DVSwitch mode and talkgroup switching operations for multiple nodes
 */
class DvswitchService
{
    private LoggerInterface $logger;
    private AllStarConfigService $configService;
    private string $userFilesPath;
    private ?string $defaultDvswitchPath;
    private ?string $defaultConfigPath;
    private array $nodeModesCache = [];
    
    public function __construct(
        LoggerInterface $logger,
        AllStarConfigService $configService,
        ?string $defaultDvswitchPath = null,
        ?string $defaultConfigPath = null
    ) {
        $this->logger = $logger;
        $this->configService = $configService;
        $this->userFilesPath = $this->configService->getUserFilesPath();
        
        // Default paths
        $this->defaultDvswitchPath = $defaultDvswitchPath ?? '/opt/MMDVM_Bridge/dvswitch.sh';
        $this->defaultConfigPath = $defaultConfigPath ?? $this->userFilesPath . 'dvswitch_config.yml';
    }
    
    /**
     * Get DVSwitch path for a specific node
     */
    private function getDvswitchPathForNode(string $nodeId, ?string $username = null): string
    {
        try {
            $nodeConfig = $this->configService->getNodeConfig($nodeId, $username);
            
            // Check if node has a specific dvswitch_path configured
            if (isset($nodeConfig['dvswitch_path']) && !empty($nodeConfig['dvswitch_path'])) {
                return $nodeConfig['dvswitch_path'];
            }
        } catch (Exception $e) {
            $this->logger->debug("Could not get node config for DVSwitch path", [
                'node_id' => $nodeId,
                'error' => $e->getMessage()
            ]);
        }
        
        return $this->defaultDvswitchPath;
    }
    
    /**
     * Get config path for a specific node (supports per-node config files)
     */
    private function getConfigPathForNode(string $nodeId, ?string $username = null): string
    {
        // Try node-specific config first: dvswitch_config_{nodeId}.yml
        $nodeSpecificConfig = $this->userFilesPath . "dvswitch_config_{$nodeId}.yml";
        if (file_exists($nodeSpecificConfig)) {
            return $nodeSpecificConfig;
        }
        
        // Fallback to global config
        return $this->defaultConfigPath;
    }
    
    /**
     * Check if DVSwitch is configured for a specific node
     */
    public function isConfiguredForNode(string $nodeId, ?string $username = null): bool
    {
        $dvswitchPath = $this->getDvswitchPathForNode($nodeId, $username);
        return file_exists($dvswitchPath) && is_executable($dvswitchPath);
    }
    
    /**
     * Load modes from YAML config file for a specific node
     */
    private function loadModesForNode(string $nodeId, ?string $username = null): array
    {
        $cacheKey = "{$nodeId}_{$username}";
        
        // Check cache first
        if (isset($this->nodeModesCache[$cacheKey])) {
            return $this->nodeModesCache[$cacheKey];
        }
        
        $configPath = $this->getConfigPathForNode($nodeId, $username);
        $modes = [];
        
        try {
            if (!file_exists($configPath)) {
                $this->logger->debug('DVSwitch config not found', [
                    'node_id' => $nodeId,
                    'config_path' => $configPath
                ]);
                $this->nodeModesCache[$cacheKey] = [];
                return [];
            }
            
            $configContent = file_get_contents($configPath);
            $config = SymfonyYaml::parse($configContent);
            
            $modes = $config['modes'] ?? [];
            
            $this->logger->debug('DVSwitch modes loaded', [
                'node_id' => $nodeId,
                'modes_count' => count($modes),
                'config_path' => $configPath
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error loading DVSwitch config', [
                'node_id' => $nodeId,
                'error' => $e->getMessage(),
                'config_path' => $configPath
            ]);
            $modes = [];
        }
        
        $this->nodeModesCache[$cacheKey] = $modes;
        return $modes;
    }
    
    /**
     * Get all available modes for a specific node
     */
    public function getModes(string $nodeId, ?string $username = null): array
    {
        $modes = $this->loadModesForNode($nodeId, $username);
        
        $result = [];
        foreach ($modes as $mode) {
            $modeName = array_key_first($mode);
            $talkgroups = $mode[$modeName]['talkgroups'] ?? [];
            
            $result[] = [
                'name' => $modeName,
                'talkgroups' => array_map(function($tg) {
                    return [
                        'tgid' => $tg['tgid'] ?? '',
                        'alias' => $tg['alias'] ?? ''
                    ];
                }, $talkgroups)
            ];
        }
        
        return $result;
    }
    
    /**
     * Get talkgroups for a specific mode and node
     */
    public function getTalkgroupsForMode(string $nodeId, string $modeName, ?string $username = null): array
    {
        $modes = $this->loadModesForNode($nodeId, $username);
        
        foreach ($modes as $mode) {
            $name = array_key_first($mode);
            if ($name === $modeName) {
                $talkgroups = $mode[$name]['talkgroups'] ?? [];
                return array_map(function($tg) {
                    return [
                        'tgid' => $tg['tgid'] ?? '',
                        'alias' => $tg['alias'] ?? ''
                    ];
                }, $talkgroups);
            }
        }
        
        return [];
    }
    
    /**
     * Switch to a specific mode for a node
     */
    public function switchMode(string $nodeId, string $modeName, ?string $username = null): array
    {
        $dvswitchPath = $this->getDvswitchPathForNode($nodeId, $username);
        
        if (!file_exists($dvswitchPath)) {
            throw new Exception("DVSwitch script not found at: {$dvswitchPath} for node {$nodeId}");
        }
        
        if (!is_executable($dvswitchPath)) {
            throw new Exception("DVSwitch script is not executable: {$dvswitchPath} for node {$nodeId}");
        }
        
        // Validate mode exists for this node
        $modes = $this->getModes($nodeId, $username);
        $modeExists = false;
        foreach ($modes as $mode) {
            if ($mode['name'] === $modeName) {
                $modeExists = true;
                break;
            }
        }
        
        if (!$modeExists) {
            throw new Exception("Mode '{$modeName}' not found in configuration for node {$nodeId}");
        }
        
        // Execute dvswitch.sh mode command
        $command = escapeshellarg($dvswitchPath) . ' mode ' . escapeshellarg($modeName);
        $output = [];
        $returnVar = 0;
        
        exec($command . ' 2>&1', $output, $returnVar);
        
        if ($returnVar !== 0) {
            $error = implode("\n", $output);
            $this->logger->error('DVSwitch mode switch failed', [
                'node_id' => $nodeId,
                'mode' => $modeName,
                'command' => $command,
                'error' => $error,
                'return_code' => $returnVar
            ]);
            throw new Exception("Failed to switch mode: {$error}");
        }
        
        $this->logger->info('DVSwitch mode switched', [
            'node_id' => $nodeId,
            'mode' => $modeName,
            'output' => implode("\n", $output)
        ]);
        
        // Return talkgroups for the mode
        return [
            'success' => true,
            'node_id' => $nodeId,
            'mode' => $modeName,
            'message' => "Switched node {$nodeId} to mode: {$modeName}",
            'talkgroups' => $this->getTalkgroupsForMode($nodeId, $modeName, $username)
        ];
    }
    
    /**
     * Switch to a specific talkgroup for a node
     */
    public function switchTalkgroup(string $nodeId, string $tgid, ?string $username = null): array
    {
        $dvswitchPath = $this->getDvswitchPathForNode($nodeId, $username);
        
        if (!file_exists($dvswitchPath)) {
            throw new Exception("DVSwitch script not found at: {$dvswitchPath} for node {$nodeId}");
        }
        
        if (!is_executable($dvswitchPath)) {
            throw new Exception("DVSwitch script is not executable: {$dvswitchPath} for node {$nodeId}");
        }
        
        // Execute dvswitch.sh tune command
        $command = escapeshellarg($dvswitchPath) . ' tune ' . escapeshellarg($tgid);
        $output = [];
        $returnVar = 0;
        
        exec($command . ' 2>&1', $output, $returnVar);
        
        if ($returnVar !== 0) {
            $error = implode("\n", $output);
            $this->logger->error('DVSwitch talkgroup switch failed', [
                'node_id' => $nodeId,
                'tgid' => $tgid,
                'command' => $command,
                'error' => $error,
                'return_code' => $returnVar
            ]);
            throw new Exception("Failed to switch talkgroup: {$error}");
        }
        
        $this->logger->info('DVSwitch talkgroup switched', [
            'node_id' => $nodeId,
            'tgid' => $tgid,
            'output' => implode("\n", $output)
        ]);
        
        return [
            'success' => true,
            'node_id' => $nodeId,
            'tgid' => $tgid,
            'message' => "Switched node {$nodeId} to talkgroup: {$tgid}"
        ];
    }
    
    /**
     * Get nodes that have DVSwitch configured
     */
    public function getNodesWithDvswitch(?string $username = null): array
    {
        $availableNodes = $this->configService->getAvailableNodes($username);
        $nodesWithDvswitch = [];
        
        foreach ($availableNodes as $node) {
            $nodeId = (string)$node['id'];
            if ($this->isConfiguredForNode($nodeId, $username)) {
                $nodesWithDvswitch[] = [
                    'id' => $nodeId,
                    'host' => $node['host'] ?? '',
                    'system' => $node['system'] ?? 'Nodes'
                ];
            }
        }
        
        return $nodesWithDvswitch;
    }
}

