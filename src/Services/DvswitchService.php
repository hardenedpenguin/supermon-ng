<?php

namespace SupermonNg\Services;

use JsonException;
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

    /** @var array<string, list<array{name: string, entries: list<array{alias: string, public_tgid: string, internal_tune: string, network: string}>}>> */
    private array $nodeModesCache = [];

    private const TALKGROUP_REF_PREFIX = 'smngtg1:';

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

    private function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $b64 = strtr($data, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $out = base64_decode($b64, true);

        return is_string($out) ? $out : '';
    }

    /**
     * @param mixed $credentialsYaml
     * @return array<string, mixed>
     */
    private function normalizeCredentialsSection(mixed $credentialsYaml): array
    {
        if (!is_array($credentialsYaml)) {
            return [];
        }
        $out = [];
        foreach ($credentialsYaml as $name => $def) {
            if (is_string($name) && is_array($def)) {
                $out[$name] = $def;
            }
        }

        return $out;
    }

    private function buildTuneStringFromProfile(string $profileName, string $tgSuffix, array $credentials): string
    {
        if (!isset($credentials[$profileName]) || !is_array($credentials[$profileName])) {
            throw new Exception("DVSwitch config: unknown credential profile '{$profileName}'");
        }
        $c = $credentials[$profileName];
        $password = trim((string) ($c['password'] ?? ''));
        $server = trim((string) ($c['server'] ?? $c['host'] ?? ''));
        $port = trim((string) ($c['port'] ?? '62031'));
        if ($password === '' || $server === '') {
            throw new Exception("DVSwitch config: incomplete credential profile '{$profileName}' (password and server required)");
        }

        return $password . '@' . $server . ':' . $port . '!' . $tgSuffix;
    }

    private function isSensitiveTuneString(string $internal): bool
    {
        return str_contains($internal, '@');
    }

    /**
     * @param array<string, mixed> $tgRow
     * @param array<string, mixed> $credentials
     * @return array{internal_tune: string, public_tgid: string}
     */
    private function materializeTalkgroupRow(
        array $tgRow,
        array $credentials,
        string $nodeId,
        string $modeName,
        int $index,
    ): array {
        if (isset($tgRow['profile']) && is_string($tgRow['profile'])) {
            $tgPart = (string) ($tgRow['tg'] ?? $tgRow['tgid'] ?? '');
            if ($tgPart === '') {
                throw new Exception("DVSwitch config: profile row in mode {$modeName} requires 'tg' (talkgroup suffix after !)");
            }
            $internal = $this->buildTuneStringFromProfile($tgRow['profile'], $tgPart, $credentials);

            return [
                'internal_tune' => $internal,
                'public_tgid' => $this->encodeTalkgroupRef($nodeId, $modeName, $index),
            ];
        }
        if (isset($tgRow['tgid'])) {
            $internal = (string) $tgRow['tgid'];
            if ($internal === '') {
                return ['internal_tune' => '', 'public_tgid' => ''];
            }
            $public = $this->isSensitiveTuneString($internal)
                ? $this->encodeTalkgroupRef($nodeId, $modeName, $index)
                : $internal;

            return ['internal_tune' => $internal, 'public_tgid' => $public];
        }
        throw new Exception("DVSwitch config: talkgroup row in mode {$modeName} must use 'profile' + 'tg' or legacy 'tgid'");
    }

    private function encodeTalkgroupRef(string $nodeId, string $modeName, int $index): string
    {
        try {
            $payload = json_encode(['n' => $nodeId, 'm' => $modeName, 'i' => $index], JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new Exception('Failed to encode DVSwitch talkgroup reference');
        }

        return self::TALKGROUP_REF_PREFIX . $this->base64UrlEncode($payload);
    }

    /**
     * @return array{node: string, mode: string, i: int}|null
     */
    private function decodeTalkgroupRef(string $ref): ?array
    {
        if (!str_starts_with($ref, self::TALKGROUP_REF_PREFIX)) {
            return null;
        }
        $json = $this->base64UrlDecode(substr($ref, strlen(self::TALKGROUP_REF_PREFIX)));
        if ($json === '') {
            return null;
        }
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!is_array($data) || !isset($data['n'], $data['m']) || !array_key_exists('i', $data)) {
            return null;
        }

        return [
            'node' => (string) $data['n'],
            'mode' => (string) $data['m'],
            'i' => (int) $data['i'],
        ];
    }

    /**
     * Resolve a client-supplied tune token to the internal string used with dvswitch.sh.
     * Accepts smngtg1: opaque refs, legacy full connection strings that match the server config, or plain targets (YSF, numeric TG, etc.).
     *
     * @throws Exception
     */
    private function resolveTuneTarget(string $nodeId, ?string $username, string $clientToken): string
    {
        $token = trim($clientToken);
        if ($token === '') {
            return '';
        }
        $ref = $this->decodeTalkgroupRef($token);
        if ($ref !== null) {
            if ($ref['node'] !== $nodeId) {
                throw new Exception('Talkgroup reference does not match this node');
            }
            $enriched = $this->loadEnrichedModesForNode($nodeId, $username);
            foreach ($enriched as $mode) {
                if ($mode['name'] !== $ref['mode']) {
                    continue;
                }
                if (!isset($mode['entries'][$ref['i']])) {
                    throw new Exception('Invalid talkgroup reference (index out of range)');
                }

                return $mode['entries'][$ref['i']]['internal_tune'];
            }
            throw new Exception('Invalid talkgroup reference (mode not found)');
        }

        if (str_contains($token, '@')) {
            foreach ($this->loadEnrichedModesForNode($nodeId, $username) as $mode) {
                foreach ($mode['entries'] as $e) {
                    if ($e['internal_tune'] === $token) {
                        return $token;
                    }
                }
            }
            throw new Exception('Invalid talkgroup target');
        }

        return $token;
    }

    /**
     * @return list<array{name: string, entries: list<array{alias: string, public_tgid: string, internal_tune: string}>}>
     */
    private function loadEnrichedModesForNode(string $nodeId, ?string $username = null): array
    {
        $cacheKey = "{$nodeId}_{$username}";
        if (isset($this->nodeModesCache[$cacheKey])) {
            return $this->nodeModesCache[$cacheKey];
        }

        $configPath = $this->getConfigPathForNode($nodeId, $username);
        if (!file_exists($configPath)) {
            $this->logger->debug('DVSwitch config not found', [
                'node_id' => $nodeId,
                'config_path' => $configPath,
            ]);
            $this->nodeModesCache[$cacheKey] = [];

            return [];
        }

        try {
            $yaml = SymfonyYaml::parseFile($configPath);
        } catch (Exception $e) {
            $this->logger->error('DVSwitch YAML parse failed', [
                'node_id' => $nodeId,
                'config_path' => $configPath,
                'error' => $e->getMessage(),
            ]);
            $this->nodeModesCache[$cacheKey] = [];

            return [];
        }

        if (!is_array($yaml)) {
            $this->nodeModesCache[$cacheKey] = [];

            return [];
        }

        $credentials = $this->normalizeCredentialsSection($yaml['credentials'] ?? []);
        $modesYaml = $yaml['modes'] ?? null;
        if (!is_array($modesYaml)) {
            $this->nodeModesCache[$cacheKey] = [];

            return [];
        }

        $enriched = [];
        foreach ($modesYaml as $modeBlock) {
            if (!is_array($modeBlock) || $modeBlock === []) {
                continue;
            }
            $modeName = array_key_first($modeBlock);
            if ($modeName === null) {
                continue;
            }
            $details = $modeBlock[$modeName];
            $talkRaw = is_array($details) && isset($details['talkgroups']) && is_array($details['talkgroups'])
                ? $details['talkgroups']
                : [];
            $entries = [];
            foreach ($talkRaw as $tgRow) {
                if (!is_array($tgRow)) {
                    continue;
                }
                $mat = $this->materializeTalkgroupRow($tgRow, $credentials, $nodeId, (string) $modeName, count($entries));
                if ($mat['internal_tune'] === '') {
                    continue;
                }
                $alias = trim((string) ($tgRow['alias'] ?? ''));
                if ($alias === '') {
                    $alias = $mat['public_tgid'];
                }
                $network = trim((string) ($tgRow['network'] ?? ''));
                $entries[] = [
                    'alias' => $alias,
                    'public_tgid' => $mat['public_tgid'],
                    'internal_tune' => $mat['internal_tune'],
                    'network' => $network,
                ];
            }
            $enriched[] = ['name' => (string) $modeName, 'entries' => $entries];
        }

        $this->nodeModesCache[$cacheKey] = $enriched;
        $this->logger->debug('DVSwitch enriched modes loaded', [
            'node_id' => $nodeId,
            'modes_count' => count($enriched),
            'config_path' => $configPath,
        ]);

        return $enriched;
    }

    /**
     * Get all available modes for a specific node
     */
    public function getModes(string $nodeId, ?string $username = null): array
    {
        $result = [];
        foreach ($this->loadEnrichedModesForNode($nodeId, $username) as $mode) {
            $result[] = [
                'name' => $mode['name'],
                'talkgroups' => array_map(
                    static fn (array $e): array => [
                        'tgid' => $e['public_tgid'],
                        'alias' => $e['alias'],
                        'network' => $e['network'] ?? '',
                    ],
                    $mode['entries']
                ),
            ];
        }

        return $result;
    }

    /**
     * Get talkgroups for a specific mode and node
     */
    public function getTalkgroupsForMode(string $nodeId, string $modeName, ?string $username = null): array
    {
        foreach ($this->loadEnrichedModesForNode($nodeId, $username) as $mode) {
            if ($mode['name'] === $modeName) {
                return array_map(
                    static fn (array $e): array => [
                        'tgid' => $e['public_tgid'],
                        'alias' => $e['alias'],
                        'network' => $e['network'] ?? '',
                    ],
                    $mode['entries']
                );
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
     * Switch mode, then optionally tune to a talkgroup in one logical operation (single HTTP round-trip from the client).
     *
     * @throws Exception if mode switch fails, or if mode switched but tune fails (message notes partial success)
     */
    public function switchModeWithOptionalTalkgroup(string $nodeId, string $modeName, string $talkgroup, ?string $username = null): array
    {
        $modeResult = $this->switchMode($nodeId, $modeName, $username);
        $tg = trim($talkgroup);
        if ($tg === '') {
            return $modeResult;
        }

        try {
            $this->switchTalkgroup($nodeId, $tg, $username);
        } catch (Exception $e) {
            throw new Exception(
                "Mode was set to {$modeName}, but talkgroup tune failed: {$e->getMessage()}"
            );
        }

        return [
            'success' => true,
            'node_id' => $nodeId,
            'mode' => $modeName,
            'tgid' => $tg,
            'message' => "Switched node {$nodeId} to mode {$modeName} and talkgroup {$tg}",
            'talkgroups' => $modeResult['talkgroups'] ?? $this->getTalkgroupsForMode($nodeId, $modeName, $username),
        ];
    }
    
    /**
     * Switch to a specific talkgroup for a node
     */
    public function switchTalkgroup(string $nodeId, string $tgid, ?string $username = null): array
    {
        $clientToken = trim($tgid);
        $this->logger->warning('switchTalkgroup called', [
            'node_id' => $nodeId,
            'tgid' => str_contains($clientToken, '@') ? '[redacted]' : $clientToken,
            'username' => $username ?? 'null',
        ]);

        $resolved = $this->resolveTuneTarget($nodeId, $username, $clientToken);

        $execArg = $resolved;
        if (str_contains($execArg, '!')) {
            $parts = explode('!', $execArg);
            $execArg = (string) end($parts);
            $this->logger->info('Extracted talkgroup suffix for dvswitch.sh tune', [
                'extracted' => $execArg,
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
        $command = 'ABINFO=' . escapeshellarg($abinfoFile) . ' DVSWITCH_INI=' . escapeshellarg($dvswitchIni) . ' ' . escapeshellarg($this->dvswitchPath) . ' tune ' . escapeshellarg($execArg);

        $this->logger->warning('DVSwitch command being executed', [
            'node_id' => $nodeId,
            'tgid' => $execArg,
            'username' => $username ?? 'null',
            'abinfo_file' => $abinfoFile,
            'dvswitch_ini' => $dvswitchIni,
            'command' => $command,
        ]);

        $output = [];
        $returnVar = 0;

        $this->logger->debug('Executing DVSwitch tune command', [
            'node_id' => $nodeId,
            'tgid' => $execArg,
            'abinfo_file' => $abinfoFile,
            'command' => $command,
        ]);

        exec($command . ' 2>&1', $output, $returnVar);

        if ($returnVar !== 0) {
            $error = implode("\n", $output);
            $this->logger->error('DVSwitch talkgroup switch failed', [
                'node_id' => $nodeId,
                'tgid' => $execArg,
                'command' => $command,
                'error' => $error,
                'return_code' => $returnVar,
            ]);
            throw new Exception("Failed to switch talkgroup: {$error}");
        }

        $this->logger->info('DVSwitch talkgroup switched', [
            'node_id' => $nodeId,
            'tgid' => $execArg,
            'output' => implode("\n", $output),
        ]);

        return [
            'success' => true,
            'node_id' => $nodeId,
            'tgid' => $execArg,
            'message' => "Switched node {$nodeId} to talkgroup: {$execArg}",
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

