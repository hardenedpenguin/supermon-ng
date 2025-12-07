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
     * Get DVSwitch.ini file path for a specific node
     * Defaults to /opt/MMDVM_Bridge/DVSwitch.ini if not configured
     * Can be overridden per-node with dvswitch_ini configuration option
     */
    private function getDvswitchIniForNode(string $nodeId, ?string $username = null): string
    {
        $defaultIni = '/opt/MMDVM_Bridge/DVSwitch.ini';
        
        // Try user-specific config first
        if ($username) {
            try {
                $nodeConfig = $this->configService->getNodeConfig($nodeId, $username);
                if (isset($nodeConfig['dvswitch_ini']) && !empty($nodeConfig['dvswitch_ini'])) {
                    $this->logger->warning("Using dvswitch_ini from user-specific config", [
                        'node_id' => $nodeId,
                        'username' => $username,
                        'dvswitch_ini' => $nodeConfig['dvswitch_ini']
                    ]);
                    return $nodeConfig['dvswitch_ini'];
                }
            } catch (Exception $e) {
                // Node not in user-specific config, continue to default
            }
        }
        
        // Try default allmon.ini
        try {
            $nodeConfig = $this->configService->getNodeConfig($nodeId, null);
            if (isset($nodeConfig['dvswitch_ini']) && !empty($nodeConfig['dvswitch_ini'])) {
                $this->logger->warning("Using dvswitch_ini from default config", [
                    'node_id' => $nodeId,
                    'dvswitch_ini' => $nodeConfig['dvswitch_ini']
                ]);
                return $nodeConfig['dvswitch_ini'];
            }
        } catch (Exception $e) {
            // Use default
        }
        
        $this->logger->warning("Using default DVSwitch.ini", [
            'node_id' => $nodeId,
            'default_ini' => $defaultIni
        ]);
        
        return $defaultIni;
    }
    
    /**
     * Get ABINFO file path for a specific node
     * ABINFO files are used by DVSwitch to track state per node
     * The file name uses a port number, not the node ID
     * Format: /tmp/ABInfo_{port}.json (e.g., /tmp/ABInfo_34001.json)
     * 
     * Supports user-specific INI files (username-allmon.ini) for multi-user/multi-node setups.
     * If a user has a username-allmon.ini file configured, abinfo_file can be defined there.
     * 
     * @throws Exception if neither abinfo_file nor abinfo_port is configured
     */
    private function getAbinfoFileForNode(string $nodeId, ?string $username = null): string
    {
        $this->logger->warning("Getting ABINFO file for node", [
            'node_id' => $nodeId,
            'username' => $username ?? 'null'
        ]);
        
        // Try user-specific config first (if username provided)
        if ($username) {
            try {
                $this->logger->warning("Attempting to get node config from user-specific INI", [
                    'node_id' => $nodeId,
                    'username' => $username
                ]);
                
                $nodeConfig = $this->configService->getNodeConfig($nodeId, $username);
                
                $this->logger->warning("Retrieved node config from user-specific INI", [
                    'node_id' => $nodeId,
                    'username' => $username,
                    'config_keys' => array_keys($nodeConfig),
                    'abinfo_file' => $nodeConfig['abinfo_file'] ?? 'not set',
                    'abinfo_port' => $nodeConfig['abinfo_port'] ?? 'not set',
                    'abinfo_suffix' => $nodeConfig['abinfo_suffix'] ?? 'not set'
                ]);
                
                // Check if node has a specific abinfo_file configured
                if (isset($nodeConfig['abinfo_file']) && !empty($nodeConfig['abinfo_file'])) {
                    $this->logger->warning("Using abinfo_file from user-specific config", [
                        'node_id' => $nodeId,
                        'username' => $username,
                        'abinfo_file' => $nodeConfig['abinfo_file']
                    ]);
                    return $nodeConfig['abinfo_file'];
                }
                
                // Check if node has abinfo_port configured (port number for ABInfo file)
                if (isset($nodeConfig['abinfo_port']) && !empty($nodeConfig['abinfo_port'])) {
                    $abinfoPath = '/tmp/ABInfo_' . $nodeConfig['abinfo_port'] . '.json';
                    $this->logger->warning("Using abinfo_port from user-specific config", [
                        'node_id' => $nodeId,
                        'username' => $username,
                        'abinfo_port' => $nodeConfig['abinfo_port'],
                        'abinfo_path' => $abinfoPath
                    ]);
                    return $abinfoPath;
                }
                
                // Check if node has abinfo_suffix (will be combined with /tmp/ABInfo_)
                if (isset($nodeConfig['abinfo_suffix']) && !empty($nodeConfig['abinfo_suffix'])) {
                    $abinfoPath = '/tmp/ABInfo_' . $nodeConfig['abinfo_suffix'] . '.json';
                    $this->logger->warning("Using abinfo_suffix from user-specific config", [
                        'node_id' => $nodeId,
                        'username' => $username,
                        'abinfo_suffix' => $nodeConfig['abinfo_suffix'],
                        'abinfo_path' => $abinfoPath
                    ]);
                    return $abinfoPath;
                }
                
                $this->logger->warning("Node config found but no ABINFO configuration present", [
                    'node_id' => $nodeId,
                    'username' => $username,
                    'config_keys' => array_keys($nodeConfig)
                ]);
            } catch (Exception $e) {
                $this->logger->warning("Node not found in user-specific config, trying default", [
                    'node_id' => $nodeId,
                    'username' => $username,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Fallback to default allmon.ini if user-specific config doesn't have the node
        $this->logger->warning("Attempting to get node config from default allmon.ini", [
            'node_id' => $nodeId
        ]);
        
        try {
            $nodeConfig = $this->configService->getNodeConfig($nodeId, null);
            
            $this->logger->warning("Retrieved node config from default allmon.ini", [
                'node_id' => $nodeId,
                'config_keys' => array_keys($nodeConfig),
                'abinfo_file' => $nodeConfig['abinfo_file'] ?? 'not set',
                'abinfo_port' => $nodeConfig['abinfo_port'] ?? 'not set',
                'abinfo_suffix' => $nodeConfig['abinfo_suffix'] ?? 'not set'
            ]);
            
            // Check if node has a specific abinfo_file configured
            if (isset($nodeConfig['abinfo_file']) && !empty($nodeConfig['abinfo_file'])) {
                $this->logger->warning("Using abinfo_file from default config", [
                    'node_id' => $nodeId,
                    'abinfo_file' => $nodeConfig['abinfo_file']
                ]);
                return $nodeConfig['abinfo_file'];
            }
            
            // Check if node has abinfo_port configured (port number for ABInfo file)
            if (isset($nodeConfig['abinfo_port']) && !empty($nodeConfig['abinfo_port'])) {
                $abinfoPath = '/tmp/ABInfo_' . $nodeConfig['abinfo_port'] . '.json';
                $this->logger->warning("Using abinfo_port from default config", [
                    'node_id' => $nodeId,
                    'abinfo_port' => $nodeConfig['abinfo_port'],
                    'abinfo_path' => $abinfoPath
                ]);
                return $abinfoPath;
            }
            
            // Check if node has abinfo_suffix (will be combined with /tmp/ABInfo_)
            if (isset($nodeConfig['abinfo_suffix']) && !empty($nodeConfig['abinfo_suffix'])) {
                $abinfoPath = '/tmp/ABInfo_' . $nodeConfig['abinfo_suffix'] . '.json';
                $this->logger->warning("Using abinfo_suffix from default config", [
                    'node_id' => $nodeId,
                    'abinfo_suffix' => $nodeConfig['abinfo_suffix'],
                    'abinfo_path' => $abinfoPath
                ]);
                return $abinfoPath;
            }
            
            $this->logger->warning("Node config found in default but no ABINFO configuration present", [
                'node_id' => $nodeId,
                'config_keys' => array_keys($nodeConfig)
            ]);
        } catch (Exception $e) {
            $this->logger->warning("Could not get node config for ABINFO file", [
                'node_id' => $nodeId,
                'error' => $e->getMessage()
            ]);
        }
        
        // No valid configuration found - fail with warning
        $errorMessage = "ABINFO file not configured for node {$nodeId}. " .
                       "Please set either 'abinfo_file' (full path) or 'abinfo_port' (port number) " .
                       "in allmon.ini or username-allmon.ini for this node. " .
                       "Example: abinfo_port=34001 (creates /tmp/ABInfo_34001.json)";
        
        $this->logger->warning($errorMessage, [
            'node_id' => $nodeId,
            'username' => $username
        ]);
        
        throw new Exception($errorMessage);
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
        // Check if dvswitch.sh exists and is executable
        if (!file_exists($this->dvswitchPath) || !is_executable($this->dvswitchPath)) {
            return false;
        }
        
        // Check if node has ABINFO configuration
        try {
            $this->getAbinfoFileForNode($nodeId, $username);
            return true;
        } catch (Exception $e) {
            // Node doesn't have ABINFO configured
            return false;
        }
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
        $this->logger->warning("switchMode called", [
            'node_id' => $nodeId,
            'mode' => $modeName,
            'username' => $username ?? 'null'
        ]);
        
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
        
        // Get DVSwitch.ini file for this node
        $dvswitchIni = $this->getDvswitchIniForNode($nodeId, $username);
        
        // Execute dvswitch.sh mode command with ABINFO and DVSWITCH_INI parameters
        $command = 'ABINFO=' . escapeshellarg($abinfoFile) . ' DVSWITCH_INI=' . escapeshellarg($dvswitchIni) . ' ' . escapeshellarg($this->dvswitchPath) . ' mode ' . escapeshellarg($modeName);
        
        $this->logger->warning("DVSwitch command being executed", [
            'node_id' => $nodeId,
            'mode' => $modeName,
            'username' => $username ?? 'null',
            'abinfo_file' => $abinfoFile,
            'dvswitch_ini' => $dvswitchIni,
            'command' => $command
        ]);
        
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
        $this->logger->warning("switchTalkgroup called", [
            'node_id' => $nodeId,
            'tgid' => $tgid,
            'username' => $username ?? 'null'
        ]);
        
        // Extract talkgroup number from connection string format if needed
        // Format: password@server:port!talkgroup or just talkgroup number
        // STFU's txTg command expects just the talkgroup number
        $originalTgid = $tgid;
        if (strpos($tgid, '!') !== false) {
            // Extract talkgroup from format: password@server:port!talkgroup
            $parts = explode('!', $tgid);
            $tgid = end($parts);
            $this->logger->info('Extracted talkgroup from connection string', [
                'original' => $originalTgid,
                'extracted' => $tgid
            ]);
        }
        
        if (!file_exists($this->dvswitchPath)) {
            throw new Exception("DVSwitch script not found at: {$this->dvswitchPath} for node {$nodeId}");
        }
        
        if (!is_executable($this->dvswitchPath)) {
            throw new Exception("DVSwitch script is not executable: {$this->dvswitchPath} for node {$nodeId}");
        }
        
        // Get ABINFO file for this node
        $abinfoFile = $this->getAbinfoFileForNode($nodeId, $username);
        
        // Get DVSwitch.ini file for this node
        $dvswitchIni = $this->getDvswitchIniForNode($nodeId, $username);
        
        // Execute dvswitch.sh tune command with ABINFO and DVSWITCH_INI parameters
        $command = 'ABINFO=' . escapeshellarg($abinfoFile) . ' DVSWITCH_INI=' . escapeshellarg($dvswitchIni) . ' ' . escapeshellarg($this->dvswitchPath) . ' tune ' . escapeshellarg($tgid);
        
        $this->logger->warning("DVSwitch command being executed", [
            'node_id' => $nodeId,
            'tgid' => $tgid,
            'username' => $username ?? 'null',
            'abinfo_file' => $abinfoFile,
            'dvswitch_ini' => $dvswitchIni,
            'command' => $command
        ]);
        
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

