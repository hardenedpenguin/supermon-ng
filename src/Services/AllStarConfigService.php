<?php

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;
use Exception;

/**
 * AllStar Configuration Service
 * 
 * Reads AllStar configuration from INI files (allmon.ini, user-specific INI files)
 * instead of hardcoded environment variables. This provides better integration
 * with existing AllStar setups.
 */
class AllStarConfigService
{
    private LoggerInterface $logger;
    private string $userFilesPath;
    private string $authIniFile;
    private array $configCache = [];

    public function __construct(
        LoggerInterface $logger,
        string $userFilesPath = 'user_files/'
    ) {
        $this->logger = $logger;
        $this->userFilesPath = rtrim($userFilesPath, '/') . '/';
        $this->authIniFile = $this->userFilesPath . 'authini.inc';
    }

    /**
     * Get AMI configuration for a specific node
     */
    public function getAmiConfig(string $nodeId, ?string $username = null): array
    {
        $cacheKey = "{$nodeId}_{$username}";
        
        if (isset($this->configCache[$cacheKey])) {
            return $this->configCache[$cacheKey];
        }

        $iniFile = $this->getIniFileName($username);
        $config = $this->parseIniFile($iniFile);

        if (!isset($config[$nodeId])) {
            throw new Exception("Node $nodeId is not defined in $iniFile");
        }

        $nodeConfig = $config[$nodeId];
        
        // Parse host:port format
        $hostParts = explode(':', $nodeConfig['host'] ?? 'localhost:5038');
        $host = $hostParts[0] ?? 'localhost';
        $port = $hostParts[1] ?? '5038';

        $amiConfig = [
            'host' => $host,
            'port' => (int)$port,
            'username' => $nodeConfig['user'] ?? 'admin',
            'password' => $nodeConfig['passwd'] ?? '',
            'timeout' => 30
        ];

        $this->configCache[$cacheKey] = $amiConfig;
        
        $this->logger->info("Loaded AMI config for node", [
            'node_id' => $nodeId,
            'host' => $host,
            'port' => $port,
            'username' => $amiConfig['username']
        ]);

        return $amiConfig;
    }

    /**
     * Get all available nodes from the configuration
     */
    public function getAvailableNodes(?string $username = null): array
    {
        $iniFile = $this->getIniFileName($username);
        
        if (!file_exists($iniFile)) {
            return [];
        }
        
        $config = $this->parseIniFile($iniFile);
        
        $nodes = [];
        foreach ($config as $nodeId => $nodeConfig) {
            // Skip non-node sections like [Hubs], [ASL3+], etc.
            if (is_array($nodeConfig) && isset($nodeConfig['host'])) {
                $nodes[] = [
                    'id' => $nodeId,
                    'host' => $nodeConfig['host'],
                    'user' => $nodeConfig['user'] ?? 'admin',
                    'system' => $nodeConfig['system'] ?? 'Nodes',
                    'menu' => $nodeConfig['menu'] ?? 'yes',
                    'hideNodeURL' => $nodeConfig['hideNodeURL'] ?? 'no'
                ];
            }
        }

        $this->logger->info("Loaded available nodes", [
            'count' => count($nodes),
            'ini_file' => $iniFile
        ]);

        return $nodes;
    }

    /**
     * Get the INI file name based on username (following original logic)
     */
    private function getIniFileName(?string $username): string
    {
        $standardAllmonIni = $this->userFilesPath . 'allmon.ini';
        
        if (!$username) {
            $this->logger->warning("No username provided, using standard allmon.ini", [
                'ini_file' => $standardAllmonIni
            ]);
            return $standardAllmonIni;
        }

        $this->logger->warning("Resolving INI file for username", [
            'username' => $username,
            'auth_ini_file' => $this->authIniFile
        ]);

        // Load authini.inc to get user-to-INI mapping
        $inimap = [];
        if (file_exists($this->authIniFile)) {
            $inimap = $this->loadAuthIniMapping();
            $this->logger->warning("Loaded authini.inc mapping", [
                'username' => $username,
                'mapping_found' => isset($inimap[$username]),
                'mapped_file' => $inimap[$username] ?? 'not found'
            ]);
        } else {
            $this->logger->warning("authini.inc file not found", [
                'auth_ini_file' => $this->authIniFile
            ]);
        }

        if (isset($inimap[$username]) && $inimap[$username] !== '') {
            $targetFile = $this->userFilesPath . $inimap[$username];
            if (file_exists($targetFile)) {
                $this->logger->warning("Using user-specific INI file", [
                    'username' => $username,
                    'ini_file' => $targetFile
                ]);
                return $targetFile;
            } else {
                $this->logger->warning("Mapped INI file does not exist", [
                    'username' => $username,
                    'mapped_file' => $inimap[$username],
                    'target_file' => $targetFile
                ]);
            }
        }

        // Fallback to nolog.ini if user has no access
        $nologFile = $this->userFilesPath . 'nolog.ini';
        if (file_exists($nologFile)) {
            $this->logger->warning("Using nolog.ini fallback", [
                'username' => $username,
                'ini_file' => $nologFile
            ]);
            return $nologFile;
        }

        $this->logger->warning("Using standard allmon.ini as fallback", [
            'username' => $username,
            'ini_file' => $standardAllmonIni
        ]);
        return $standardAllmonIni;
    }

    /**
     * Load the authini.inc file to get user-to-INI mapping
     */
    private function loadAuthIniMapping(): array
    {
        if (!file_exists($this->authIniFile)) {
            return [];
        }

        // Include the file to get $ININAME array
        $ININAME = [];
        include $this->authIniFile;
        
        return $ININAME ?? [];
    }

    /**
     * Parse INI file with error handling
     */
    private function parseIniFile(string $iniFile): array
    {
        if (!file_exists($iniFile)) {
            $this->logger->warning("INI file not found", ['file' => $iniFile]);
            return [];
        }

        $config = parse_ini_file($iniFile, true);
        
        if ($config === false) {
            $this->logger->error("Failed to parse INI file", ['file' => $iniFile]);
            throw new Exception("Failed to parse INI file: $iniFile");
        }

        return $config;
    }

    /**
     * Get node configuration for a specific node
     */
    public function getNodeConfig(string $nodeId, ?string $username = null): array
    {
        $iniFile = $this->getIniFileName($username);
        $this->logger->warning("Getting node config", [
            'node_id' => $nodeId,
            'username' => $username ?? 'null',
            'ini_file' => $iniFile
        ]);
        
        $config = $this->parseIniFile($iniFile);

        if (!isset($config[$nodeId])) {
            $this->logger->warning("Node not found in INI file", [
                'node_id' => $nodeId,
                'username' => $username ?? 'null',
                'ini_file' => $iniFile,
                'available_nodes' => array_keys($config)
            ]);
            throw new Exception("Node $nodeId is not defined in $iniFile");
        }

        $nodeConfig = $config[$nodeId];
        $this->logger->warning("Node config retrieved", [
            'node_id' => $nodeId,
            'username' => $username ?? 'null',
            'ini_file' => $iniFile,
            'config_keys' => array_keys($nodeConfig)
        ]);

        return $nodeConfig;
    }

    /**
     * Check if a node exists in the configuration
     */
    public function nodeExists(string $nodeId, ?string $username = null): bool
    {
        try {
            $iniFile = $this->getIniFileName($username);
            $config = $this->parseIniFile($iniFile);
            return isset($config[$nodeId]);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get the path to the user files directory
     */
    public function getUserFilesPath(): string
    {
        return $this->userFilesPath;
    }

    /**
     * Get all nodes from all *allmon.ini files (allmon.ini and username-allmon.ini)
     * This is used to create WebSocket services for all configured nodes
     */
    public function getAllNodesFromAllIniFiles(): array
    {
        $allNodes = [];
        $nodeIdsSeen = [];
        
        // Find all *allmon.ini files in user_files directory
        $iniFiles = glob($this->userFilesPath . '*allmon.ini');
        
        if (empty($iniFiles)) {
            $this->logger->warning("No *allmon.ini files found", [
                'path' => $this->userFilesPath
            ]);
            return [];
        }
        
        $this->logger->info("Found INI files for WebSocket services", [
            'files' => array_map('basename', $iniFiles),
            'count' => count($iniFiles)
        ]);
        
        // Load nodes from each INI file
        foreach ($iniFiles as $iniFile) {
            try {
                $config = $this->parseIniFile($iniFile);
                
                foreach ($config as $nodeId => $nodeConfig) {
                    // Skip non-node sections like [Hubs], [ASL3+], etc.
                    if (!is_array($nodeConfig) || !isset($nodeConfig['host'])) {
                        continue;
                    }
                    
                    // Only add if we haven't seen this node ID yet (avoid duplicates)
                    if (!isset($nodeIdsSeen[$nodeId])) {
                        $allNodes[] = [
                            'id' => $nodeId,
                            'host' => $nodeConfig['host'],
                            'user' => $nodeConfig['user'] ?? 'admin',
                            'system' => $nodeConfig['system'] ?? 'Nodes',
                            'menu' => $nodeConfig['menu'] ?? 'yes',
                            'hideNodeURL' => $nodeConfig['hideNodeURL'] ?? 'no'
                        ];
                        $nodeIdsSeen[$nodeId] = true;
                    }
                }
            } catch (Exception $e) {
                $this->logger->warning("Failed to load nodes from INI file", [
                    'file' => basename($iniFile),
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->logger->info("Loaded all nodes from all INI files", [
            'total_count' => count($allNodes),
            'unique_node_ids' => array_keys($nodeIdsSeen),
            'ini_files_checked' => count($iniFiles)
        ]);
        
        return $allNodes;
    }
    
    /**
     * Find node configuration in any INI file (for WebSocket router)
     * Returns the node config if found, or null if not found
     */
    public function findNodeConfigInAnyIniFile(string $nodeId): ?array
    {
        // First try default allmon.ini
        try {
            $config = $this->getNodeConfig($nodeId, null);
            return $config;
        } catch (Exception $e) {
            // Node not found in default, continue to check other files
        }
        
        // Find all *allmon.ini files and check each one
        $iniFiles = glob($this->userFilesPath . '*allmon.ini');
        
        foreach ($iniFiles as $iniFile) {
            try {
                $config = $this->parseIniFile($iniFile);
                
                if (isset($config[$nodeId]) && is_array($config[$nodeId])) {
                    return $config[$nodeId];
                }
            } catch (Exception $e) {
                // Continue to next file
                continue;
            }
        }
        
        return null;
    }

    /**
     * Clear the configuration cache
     */
    public function clearCache(): void
    {
        $this->configCache = [];
        $this->logger->info("Configuration cache cleared");
    }
}


