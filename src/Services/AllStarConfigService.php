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
        
        // Add debugging to error log
        error_log("DEBUG: INI file path: " . $iniFile);
        error_log("DEBUG: File exists: " . (file_exists($iniFile) ? 'YES' : 'NO'));
        
        if (!file_exists($iniFile)) {
            error_log("DEBUG: File does not exist at: " . $iniFile);
            return [];
        }
        
        $config = $this->parseIniFile($iniFile);
        
        // Add debugging
        error_log("DEBUG: Config array keys: " . implode(', ', array_keys($config)));
        error_log("DEBUG: Config count: " . count($config));
        
        $nodes = [];
        foreach ($config as $nodeId => $nodeConfig) {
            error_log("DEBUG: Processing nodeId: " . $nodeId . ", is_array: " . (is_array($nodeConfig) ? 'YES' : 'NO'));
            if (is_array($nodeConfig)) {
                error_log("DEBUG: Node config keys: " . implode(', ', array_keys($nodeConfig)));
                error_log("DEBUG: Has host: " . (isset($nodeConfig['host']) ? 'YES' : 'NO'));
            }
            
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
                error_log("DEBUG: Added node: " . $nodeId);
            }
        }

        error_log("DEBUG: Final nodes count: " . count($nodes));

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
            return $standardAllmonIni;
        }

        // Load authini.inc to get user-to-INI mapping
        $inimap = [];
        if (file_exists($this->authIniFile)) {
            $inimap = $this->loadAuthIniMapping();
        }

        if (isset($inimap[$username]) && $inimap[$username] !== '') {
            $targetFile = $this->userFilesPath . $inimap[$username];
            if (file_exists($targetFile)) {
                return $targetFile;
            }
        }

        // Fallback to nolog.ini if user has no access
        $nologFile = $this->userFilesPath . 'nolog.ini';
        if (file_exists($nologFile)) {
            return $nologFile;
        }

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
        $config = $this->parseIniFile($iniFile);

        if (!isset($config[$nodeId])) {
            throw new Exception("Node $nodeId is not defined in $iniFile");
        }

        return $config[$nodeId];
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
     * Clear the configuration cache
     */
    public function clearCache(): void
    {
        $this->configCache = [];
        $this->logger->info("Configuration cache cleared");
    }
}


