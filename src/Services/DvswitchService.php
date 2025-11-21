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
    private string $dvswitchPath;
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
        
        // Hardcoded DVSwitch path
        $this->dvswitchPath = '/opt/MMDVM_Bridge/dvswitch.sh';
        $this->defaultConfigPath = $defaultConfigPath ?? $this->userFilesPath . 'dvswitch_config.yml';
    }
    
    /**
     * Get ABINFO file path for a specific node
     * ABINFO files are used by DVSwitch to track state per node
     * The file name uses a port number, not the node ID
     * 
     * Supports user-specific INI files (username-allmon.ini) for multi-user/multi-node setups.
     * If a user has a username-allmon.ini file configured, abinfo_file can be defined there.
     */
    private function getAbinfoFileForNode(string $nodeId, ?string $username = null): ?string
    {
        // Try user-specific config first (if username provided)
        if ($username) {
            try {
                $nodeConfig = $this->configService->getNodeConfig($nodeId, $username);
                
                // Check if node has a specific abinfo_file configured
                if (isset($nodeConfig['abinfo_file']) && !empty($nodeConfig['abinfo_file'])) {
                    return $nodeConfig['abinfo_file'];
                }
                
                // Check if node has abinfo_port configured (port number for ABINFO file)
                if (isset($nodeConfig['abinfo_port']) && !empty($nodeConfig['abinfo_port'])) {
                    return '/tmp/ABINFO_' . $nodeConfig['abinfo_port'] . '.json';
                }
                
                // Check if node has abinfo_suffix (will be combined with /tmp/ABINFO_)
                if (isset($nodeConfig['abinfo_suffix']) && !empty($nodeConfig['abinfo_suffix'])) {
                    return '/tmp/ABINFO_' . $nodeConfig['abinfo_suffix'] . '.json';
                }
                
                // Extract port from host configuration (format: host:port)
                if (isset($nodeConfig['host']) && !empty($nodeConfig['host'])) {
                    $hostParts = explode(':', $nodeConfig['host']);
                    if (count($hostParts) >= 2) {
                        $port = trim($hostParts[1]);
                        if (!empty($port)) {
                            return '/tmp/ABINFO_' . $port . '.json';
                        }
                    }
                }
            } catch (Exception $e) {
                $this->logger->debug("Node not found in user-specific config, trying default", [
                    'node_id' => $nodeId,
                    'username' => $username,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Fallback to default allmon.ini if user-specific config doesn't have the node
        try {
            $nodeConfig = $this->configService->getNodeConfig($nodeId, null);
            
            // Check if node has a specific abinfo_file configured
            if (isset($nodeConfig['abinfo_file']) && !empty($nodeConfig['abinfo_file'])) {
                return $nodeConfig['abinfo_file'];
            }
            
            // Check if node has abinfo_port configured (port number for ABINFO file)
            if (isset($nodeConfig['abinfo_port']) && !empty($nodeConfig['abinfo_port'])) {
                return '/tmp/ABINFO_' . $nodeConfig['abinfo_port'] . '.json';
            }
            
            // Check if node has abinfo_suffix (will be combined with /tmp/ABINFO_)
            if (isset($nodeConfig['abinfo_suffix']) && !empty($nodeConfig['abinfo_suffix'])) {
                return '/tmp/ABINFO_' . $nodeConfig['abinfo_suffix'] . '.json';
            }
            
            // Extract port from host configuration (format: host:port)
            if (isset($nodeConfig['host']) && !empty($nodeConfig['host'])) {
                $hostParts = explode(':', $nodeConfig['host']);
                if (count($hostParts) >= 2) {
                    $port = trim($hostParts[1]);
                    if (!empty($port)) {
                        return '/tmp/ABINFO_' . $port . '.json';
                    }
                }
            }
        } catch (Exception $e) {
            $this->logger->debug("Could not get node config for ABINFO file", [
                'node_id' => $nodeId,
                'error' => $e->getMessage()
            ]);
        }
        
        // Fallback: use node ID if port cannot be determined
        $this->logger->warning('Could not determine port for ABINFO file, using node ID', [
            'node_id' => $nodeId
        ]);
        return '/tmp/ABINFO_' . $nodeId . '.json';
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
        return file_exists($this->dvswitchPath) && is_executable($this->dvswitchPath);
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
        if (!file_exists($this->dvswitchPath)) {
            throw new Exception("DVSwitch script not found at: {$this->dvswitchPath} for node {$nodeId}");
        }
        
        if (!is_executable($this->dvswitchPath)) {
            throw new Exception("DVSwitch script is not executable: {$this->dvswitchPath} for node {$nodeId}");
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
        
        // Get ABINFO file for this node
        $abinfoFile = $this->getAbinfoFileForNode($nodeId, $username);
        
        // Execute dvswitch.sh mode command with ABINFO parameter
        $command = 'ABINFO=' . escapeshellarg($abinfoFile) . ' ' . escapeshellarg($this->dvswitchPath) . ' mode ' . escapeshellarg($modeName);
        
        $output = [];
        $returnVar = 0;
        
        $this->logger->debug('Executing DVSwitch mode command', [
            'node_id' => $nodeId,
            'mode' => $modeName,
            'abinfo_file' => $abinfoFile,
            'command' => $command
        ]);
        
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
        if (!file_exists($this->dvswitchPath)) {
            throw new Exception("DVSwitch script not found at: {$this->dvswitchPath} for node {$nodeId}");
        }
        
        if (!is_executable($this->dvswitchPath)) {
            throw new Exception("DVSwitch script is not executable: {$this->dvswitchPath} for node {$nodeId}");
        }
        
        // Get ABINFO file for this node
        $abinfoFile = $this->getAbinfoFileForNode($nodeId, $username);
        
        // Execute dvswitch.sh tune command with ABINFO parameter
        $command = 'ABINFO=' . escapeshellarg($abinfoFile) . ' ' . escapeshellarg($this->dvswitchPath) . ' tune ' . escapeshellarg($tgid);
        
        $output = [];
        $returnVar = 0;
        
        $this->logger->debug('Executing DVSwitch tune command', [
            'node_id' => $nodeId,
            'tgid' => $tgid,
            'abinfo_file' => $abinfoFile,
            'command' => $command
        ]);
        
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

