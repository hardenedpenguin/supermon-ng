<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class ConfigController
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function list(Request $request, Response $response): Response
    {
        $this->logger->info('Config list request');
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Config list endpoint - to be implemented',
            'timestamp' => date('c')
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $key = $args['key'] ?? null;
        $this->logger->info("Config get request for key: $key");
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => "Config get endpoint for key '$key' - to be implemented",
            'timestamp' => date('c')
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $key = $args['key'] ?? null;
        $this->logger->info("Config update request for key: $key");
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => "Config update endpoint for key '$key' - to be implemented",
            'timestamp' => date('c')
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getNodes(Request $request, Response $response): Response
    {
        $this->logger->info('Config nodes request');
        
        // Return node configuration from AllStar INI files
        $config = [];
        
        // Get the current user's INI file
        $iniFile = $this->getCurrentUserIniFile();
        $this->logger->info("Loading nodes from INI file: $iniFile");
        
        // Read from user-specific INI file
        $allmonIni = $iniFile;
        if (file_exists($allmonIni)) {
            $iniConfig = parse_ini_file($allmonIni, true);
            if ($iniConfig) {
                foreach ($iniConfig as $nodeId => $nodeConfig) {
                    if (is_array($nodeConfig) && isset($nodeConfig['host'])) {
                        $config[$nodeId] = $nodeConfig;
                    }
                }
            }
        }
        
        // Get default node from INI file
        $defaultNode = null;
        if (file_exists($iniFile)) {
            $iniConfig = parse_ini_file($iniFile, true);
            if (isset($iniConfig['ASL3+']['default_node'])) {
                $defaultNode = $iniConfig['ASL3+']['default_node'];
            }
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'config' => $config,
                'ini_file' => $iniFile,
                'default_node' => $defaultNode
            ],
            'timestamp' => date('c')
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getUserPreferences(Request $request, Response $response): Response
    {
        $this->logger->info('User preferences request');
        
        // For now, return default preferences
        // TODO: Implement user-specific preference storage
        $preferences = [
            'showDetail' => true,
            'displayedNodes' => 999,
            'showCount' => false,
            'showAll' => true
        ];
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $preferences
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function updateUserPreferences(Request $request, Response $response): Response
    {
        $this->logger->info('User preferences update request');
        
        $body = $request->getParsedBody() ?? [];
        
        // For now, just acknowledge the update
        // TODO: Implement user-specific preference storage
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Preferences updated successfully',
            'data' => $body
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getSystemInfo(Request $request, Response $response): Response
    {
        $this->logger->info('System info request');

        // Read system information from global.inc
        $systemInfo = $this->loadSystemInfo();

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $systemInfo
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getMenu(Request $request, Response $response): Response
    {
        $this->logger->info('Menu request');

        // Get current user (or null if not logged in)
        $currentUser = $this->getCurrentUser();
        
        // Get menu items from AllStar configuration
        $menuItems = $this->loadMenuItems($currentUser);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $menuItems
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get display configuration settings
     */
    public function getDisplayConfig(Request $request, Response $response): Response
    {
        $this->logger->info('Display config request');
        
        $defaults = $this->getDefaultDisplaySettings();
        $settings = $this->loadDisplaySettings($defaults);
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $settings
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Update display configuration settings
     */
    public function updateDisplayConfig(Request $request, Response $response): Response
    {
        $this->logger->info('Display config update request');
        
        $data = $request->getParsedBody();
        $defaults = $this->getDefaultDisplaySettings();
        
        // Validate and update settings
        $settings = [
            'number-displayed' => $data['number_displayed'] ?? $defaults['number-displayed'],
            'show-number'      => $data['show_number'] ?? $defaults['show-number'],
            'show-all'         => $data['show_all'] ?? $defaults['show-all'],
            'show-detailed'    => $data['show_detailed'] ?? $defaults['show-detailed']
        ];
        
        $this->validateDisplaySettings($settings, $defaults);
        $this->saveDisplaySettingsToCookies($settings);
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $settings
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function loadSystemInfo(): array
    {
        $globalIniFile = 'user_files/global.inc';
        $systemInfo = [
            'call' => 'W5GLE',
            'name' => 'Jory A. Pratt',
            'location' => 'Alvin, Texas',
            'title2' => 'ASL3+ Management Dashboard',
            'title3' => 'AllStarLink/IRLP/EchoLink/Digital - Bridging Control Center',
            'titleLogged' => 'Supermon-ng',
            'titleNotLogged' => 'Supermon-ng',
            'maintainer' => 'Jory A. Pratt, W5GLE',
            'background' => '',
            'backgroundColor' => 'black',
            'backgroundHeight' => '164px',
            'displayBackground' => 'black',
            'dvmUrl' => ''
        ];
        
        if (file_exists($globalIniFile)) {
            // Include the file to get the variables
            $CALL = '';
            $NAME = '';
            $LOCATION = '';
            $TITLE2 = '';
            $TITLE3 = '';
            $TITLE_LOGGED = '';
            $TITLE_NOT_LOGGED = '';
            $BACKGROUND = '';
            $BACKGROUND_COLOR = '';
            $BACKGROUND_HEIGHT = '';
            $DISPLAY_BACKGROUND = '';
            $DVM_URL = '';
            
            include $globalIniFile;
            
            // Update system info with values from global.inc
            if (!empty($CALL)) $systemInfo['call'] = $CALL;
            if (!empty($NAME)) $systemInfo['name'] = $NAME;
            if (!empty($LOCATION)) $systemInfo['location'] = $LOCATION;
            if (!empty($TITLE2)) $systemInfo['title2'] = $TITLE2;
            if (!empty($TITLE3)) $systemInfo['title3'] = $TITLE3;
            if (!empty($TITLE_LOGGED)) $systemInfo['titleLogged'] = $TITLE_LOGGED;
            if (!empty($TITLE_NOT_LOGGED)) $systemInfo['titleNotLogged'] = $TITLE_NOT_LOGGED;
            if (!empty($BACKGROUND)) $systemInfo['background'] = $BACKGROUND;
            if (!empty($BACKGROUND_COLOR)) $systemInfo['backgroundColor'] = $BACKGROUND_COLOR;
            if (!empty($BACKGROUND_HEIGHT)) $systemInfo['backgroundHeight'] = $BACKGROUND_HEIGHT;
            if (!empty($DISPLAY_BACKGROUND)) $systemInfo['displayBackground'] = $DISPLAY_BACKGROUND;
            if (!empty($DVM_URL)) $systemInfo['dvmUrl'] = $DVM_URL;
            
            // Create maintainer string
            $maintainerParts = [];
            if (!empty($NAME)) $maintainerParts[] = $NAME;
            if (!empty($CALL)) $maintainerParts[] = $CALL;
            if (!empty($maintainerParts)) {
                $systemInfo['maintainer'] = implode(', ', $maintainerParts);
            }
        }
        
        return $systemInfo;
    }

    private function getCurrentUser(): ?string
    {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is logged in via session
        $this->logger->info("Checking session for user", ['session_user' => $_SESSION['user'] ?? 'not set', 'session_id' => session_id()]);
        
        if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
            return $_SESSION['user'];
        }
        
        return null; // No user logged in
    }

    private function loadMenuItems(?string $username): array
    {
        // Determine which INI file to use
        $iniFile = $this->getIniFileName($username);
        
        $this->logger->info("Loading menu from INI file", ['file' => $iniFile, 'username' => $username]);
        
        if (!file_exists($iniFile)) {
            $this->logger->warning("INI file not found", ['file' => $iniFile]);
            return [];
        }

        $config = parse_ini_file($iniFile, true);
        
        $this->logger->info("Parsed INI config", ['sections' => array_keys($config)]);
        $this->logger->info("Sample config data", ['546050' => $config['546050'] ?? 'not found']);
        
        if (empty($config)) {
            $this->logger->warning("INI file is empty or invalid", ['file' => $iniFile]);
            return [];
        }

        $systems = [];
        
        foreach ($config as $name => $data) {
            $this->logger->info("Processing config section", ['name' => $name, 'data' => $data]);
            
            // Skip if menu is not enabled (check for both "yes" and "1")
            if (!isset($data['menu']) || ($data['menu'] !== "yes" && $data['menu'] !== "1" && $data['menu'] !== 1)) {
                $this->logger->info("Skipping section - menu not enabled", ['name' => $name, 'menu' => $data['menu'] ?? 'not set']);
                continue;
            }

            // Skip break sections
            if (strtolower((string)$name) == 'break') {
                $this->logger->info("Skipping break section", ['name' => $name]);
                continue;
            }

            // Determine system name - only use system field if it exists, otherwise add to main menu
            $sysName = isset($data['system']) ? $data['system'] : null;
            $this->logger->info("Menu item system assignment", ['name' => $name, 'system' => $sysName, 'data_system' => $data['system'] ?? 'not set']);

            // Determine URL
            $url = '';
            if (isset($data['url'])) {
                $url = $data['url'];
            } elseif (isset($data['rtcmnode'])) {
                $url = "voter.php?node={$data['rtcmnode']}";
            } elseif (isset($data['nodes'])) {
                $url = "link.php?nodes={$data['nodes']}";
            } else {
                $url = "link.php?nodes=$name";
            }

            // Check if URL should open in new tab
            $targetBlank = (substr($url, -1) == '>');
            if ($targetBlank) {
                $url = substr($url, 0, -1);
            }

            if ($sysName) {
                // Item has a system field, add to that system
                $systems[$sysName][] = [
                    'name' => $name,
                    'url' => $url,
                    'targetBlank' => $targetBlank
                ];
            } else {
                // Item has no system field, add directly to main menu items
                if (!isset($systems['mainItems'])) {
                    $systems['mainItems'] = [];
                }
                $systems['mainItems'][] = [
                    'name' => $name,
                    'url' => $url,
                    'targetBlank' => $targetBlank
                ];
            }
        }

        return $systems;
    }

    /**
     * Get the current user's INI file based on session
     */
    private function getCurrentUserIniFile(): string
    {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Get current user from session
        $currentUser = $_SESSION['user'] ?? null;
        
        return $this->getIniFileName($currentUser);
    }

    private function getIniFileName(?string $username): string
    {
        if (!$username) {
            // No user logged in, use default allmon.ini
            return "user_files/allmon.ini";
        }
        
        $authIniFile = 'user_files/authini.inc';
        
        if (!file_exists($authIniFile)) {
            return "user_files/allmon.ini";
        }
        
        // Include the authini file to get the INI mapping
        include $authIniFile;
        
        // Check if user has a specific INI file mapped
        if (isset($ININAME[$username])) {
            return "user_files/{$ININAME[$username]}";
        }
        
        return "user_files/allmon.ini";
    }

    /**
     * Get default display settings
     */
    private function getDefaultDisplaySettings(): array
    {
        return [
            'number-displayed' => "0",
            'show-number'      => "0",
            'show-all'         => "1",
            'show-detailed'    => "1"
        ];
    }

    /**
     * Load settings from cookies with defaults
     */
    private function loadDisplaySettings(array $defaults): array
    {
        $settings = $defaults;

        if (isset($_COOKIE['display-data']) && is_array($_COOKIE['display-data'])) {
            foreach ($defaults as $key => $defaultValue) {
                if (isset($_COOKIE['display-data'][$key])) {
                    $settings[$key] = $_COOKIE['display-data'][$key];
                }
            }
        }

        return $settings;
    }

    /**
     * Validate display settings
     */
    private function validateDisplaySettings(array &$currentSettings, array $defaultValues): void
    {
        if (!is_numeric($currentSettings['number-displayed']) || (int)$currentSettings['number-displayed'] < 0) {
            $currentSettings['number-displayed'] = $defaultValues['number-displayed'];
        }
        
        foreach (['show-number', 'show-all', 'show-detailed'] as $key) {
            if (!in_array($currentSettings[$key], ["0", "1"])) {
                $currentSettings[$key] = $defaultValues[$key];
            }
        }
    }

    /**
     * Save settings to cookies
     */
    private function saveDisplaySettingsToCookies(array $settings): void
    {
        $expiretime = 2147483645;
        $cookie_path = "/";

        foreach ($settings as $key => $value) {
            setcookie("display-data[{$key}]", $value, $expiretime, $cookie_path);
        }
    }

    /**
     * Execute Asterisk configuration reload
     */
    public function executeAsteriskReload(Request $request, Response $response): Response
    {
        $currentUser = $this->getCurrentUser();
        
        if (!$currentUser) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Authentication required'
            ]));
            return $response->withStatus(401);
        }

        // Check if user has ASTRELUSER permission
        if (!$this->hasUserPermission($currentUser, 'ASTRELUSER')) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'ASTRELUSER permission required'
            ]));
            return $response->withStatus(403);
        }

        $data = $request->getParsedBody();
        $localNode = $data['localnode'] ?? null;

        if (empty($localNode)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Local node not specified'
            ]));
            return $response->withStatus(400);
        }

        try {
            // Get user's INI file
            $userIniFile = $this->getUserIniFile($currentUser);
            
            if (!file_exists($userIniFile)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "Couldn't load supervisor INI file: $userIniFile"
                ]));
                return $response->withStatus(500);
            }

            $config = parse_ini_file($userIniFile, true);

            if (!isset($config[$localNode])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "Node $localNode is not defined in $userIniFile"
                ]));
                return $response->withStatus(400);
            }

            $amiHost = $config[$localNode]['host'] ?? null;
            $amiUser = $config[$localNode]['user'] ?? null;
            $amiPass = $config[$localNode]['passwd'] ?? null;

            if (empty($amiHost) || empty($amiUser) || empty($amiPass)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "AMI host, user, or password not configured for node $localNode"
                ]));
                return $response->withStatus(500);
            }

            // Include AMI functions
            require_once 'includes/amifunctions.inc';

            // Connect to AMI
            $fp = \SimpleAmiClient::connect($amiHost);
            if ($fp === FALSE) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "Could not connect to Asterisk Manager at $amiHost for node $localNode"
                ]));
                return $response->withStatus(500);
            }

            // Login to AMI
            if (\SimpleAmiClient::login($fp, $amiUser, $amiPass) === FALSE) {
                \SimpleAmiClient::logoff($fp);
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "Could not login to Asterisk Manager for node $localNode with user $amiUser"
                ]));
                return $response->withStatus(500);
            }

            $results = [];
            $results[] = "Reloading configurations for node - $localNode:";

            // Execute reload commands
            if (\SimpleAmiClient::command($fp, "rpt reload") !== false) {
                $results[] = "- rpt.conf reloaded successfully.";
            } else {
                $results[] = "- FAILED to reload rpt.conf.";
            }
            sleep(1);

            if (\SimpleAmiClient::command($fp, "iax2 reload") !== false) {
                $results[] = "- iax.conf reloaded successfully.";
            } else {
                $results[] = "- FAILED to reload iax.conf.";
            }
            sleep(1);

            if (\SimpleAmiClient::command($fp, "extensions reload") !== false) {
                $results[] = "- extensions.conf reloaded successfully.";
            } else {
                $results[] = "- FAILED to reload extensions.conf.";
            }

            \SimpleAmiClient::logoff($fp);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Asterisk configuration reload completed',
                'results' => $results
            ]));
            return $response->withStatus(200);

        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error executing Asterisk reload: ' . $e->getMessage()
            ]));
            return $response->withStatus(500);
        }
    }

    /**
     * Check if user has specific permission
     */
    private function hasUserPermission(string $user, string $permission): bool
    {
        // Default permissions for unauthenticated users (same as AuthController)
        $defaultPermissions = [
            'CONNECTUSER' => true,
            'DISCUSER' => true,
            'MONUSER' => true,
            'LMONUSER' => true,
            'DTMFUSER' => false,
            'ASTLKUSER' => true, // Allow lookup for unauthenticated users
            'RSTATUSER' => true,
            'BUBLUSER' => true,
            'FAVUSER' => true,
            'CTRLUSER' => true,
            'CFGEDUSER' => false,
            'ASTRELUSER' => false,
            'ASTSTRUSER' => false,
            'ASTSTPUSER' => false,
            'FSTRESUSER' => false,
            'RBTUSER' => false,
            'UPDUSER' => true,
            'HWTOUSER' => true,
            'WIKIUSER' => true,
            'CSTATUSER' => true,
            'ASTATUSER' => true,
            'EXNUSER' => true,
            'NINFUSER' => true,
            'ACTNUSER' => true,
            'ALLNUSER' => true,
            'DBTUSER' => true,
            'GPIOUSER' => false,
            'LLOGUSER' => true,
            'ASTLUSER' => true,
            'IRLPUSER' => false,
            'WLOGUSER' => true,
            'WERRUSER' => true,
            'BANUSER' => false,
            'SYSINFUSER' => true
        ];
        
        // For now, use default permissions for all users
        // In a real implementation, you would check against the user's actual permissions
        return $defaultPermissions[$permission] ?? false;
    }

    /**
     * Get user's INI file path
     */
    private function getUserIniFile(string $user): string
    {
        // Include common.inc to get $USERFILES constant
        include_once 'includes/common.inc';
        
        // Include authini.inc if it exists to get $ININAME mapping
        if (file_exists("$USERFILES/authini.inc")) {
            include_once "$USERFILES/authini.inc";
        }
        
        $standardAllmonIni = "$USERFILES/allmon.ini";
        
        // Use the same logic as the original get_ini_name function
        if (isset($ININAME) && isset($user)) {
            if (array_key_exists($user, $ININAME) && $ININAME[$user] !== "") {
                return $this->checkIniFile($USERFILES, $ININAME[$user]);
            } else {
                return $this->checkIniFile($USERFILES, "nolog.ini");
            }
        } else {
            return $standardAllmonIni;
        }
    }
    
    /**
     * Check if a specific INI file exists in the given directory
     */
    private function checkIniFile(string $fdir, string $fname): string
    {
        $targetFile = "$fdir/$fname";
        if (file_exists($targetFile)) {
            return $targetFile;
        } else {
            return "$fdir/allmon.ini";
        }
    }

    /**
     * Execute Asterisk start/stop operations
     */
    public function executeAsteriskControl(Request $request, Response $response): Response
    {
        $currentUser = $this->getCurrentUser();
        
        if (!$currentUser) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Authentication required'
            ]));
            return $response->withStatus(401);
        }

        $data = $request->getParsedBody();
        $action = $data['action'] ?? null;

        if (empty($action)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Action not specified'
            ]));
            return $response->withStatus(400);
        }

        try {
            if ($action === 'start') {
                // Check ASTSTRUSER permission for start operation
                if (!$this->hasUserPermission($currentUser, 'ASTSTRUSER')) {
                    $response->getBody()->write(json_encode([
                        'success' => false,
                        'message' => 'ASTSTRUSER permission required to start AllStar'
                    ]));
                    return $response->withStatus(403);
                }

                $command = 'sudo /usr/bin/astup.sh';
                $message = 'Starting up AllStar...';
                
            } elseif ($action === 'stop') {
                // Check ASTSTPUSER permission for stop operation
                if (!$this->hasUserPermission($currentUser, 'ASTSTPUSER')) {
                    $response->getBody()->write(json_encode([
                        'success' => false,
                        'message' => 'ASTSTPUSER permission required to stop AllStar'
                    ]));
                    return $response->withStatus(403);
                }

                $command = 'sudo /usr/bin/astdn.sh';
                $message = 'Shutting down AllStar...';
                
            } else {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Invalid action specified'
                ]));
                return $response->withStatus(400);
            }

            // Execute the command
            $output = [];
            $returnCode = 0;
            
            exec(escapeshellcmd($command), $output, $returnCode);

            if ($returnCode === 0) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => $message,
                    'output' => $output,
                    'action' => $action
                ]));
                return $response->withStatus(200);
            } else {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => $message . ' - Command failed',
                    'output' => $output,
                    'return_code' => $returnCode
                ]));
                return $response->withStatus(500);
            }

        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error executing Asterisk control: ' . $e->getMessage()
            ]));
            return $response->withStatus(500);
        }
    }

    /**
     * Get Asterisk log content
     */
    public function getAstLog(Request $request, Response $response): Response
    {
        $currentUser = $this->getCurrentUser();
        
        if (!$currentUser) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Authentication required'
            ]));
            return $response->withStatus(401);
        }

        // Check if user has ASTLUSER permission
        if (!$this->hasUserPermission($currentUser, 'ASTLUSER')) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'ASTLUSER permission required'
            ]));
            return $response->withStatus(403);
        }

        try {
            // Get the Asterisk log file path
            $logPath = $this->getAstLogPath();
            
            if (!$logPath) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Asterisk log file path not configured'
                ]));
                return $response->withStatus(500);
            }

            // Check if file exists and is readable
            if (!file_exists($logPath)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "Asterisk log file not found: $logPath"
                ]));
                return $response->withStatus(404);
            }

            if (!is_readable($logPath)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "Asterisk log file not readable: $logPath"
                ]));
                return $response->withStatus(403);
            }

            // Read the log file content
            $content = file_get_contents($logPath);
            if ($content === false) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Failed to read Asterisk log file'
                ]));
                return $response->withStatus(500);
            }

            // Get file modification time
            $lastModified = filemtime($logPath);
            $lastModifiedFormatted = $lastModified ? date('Y-m-d H:i:s', $lastModified) : 'Unknown';

            $response->getBody()->write(json_encode([
                'success' => true,
                'content' => $content,
                'path' => $logPath,
                'lastModified' => $lastModifiedFormatted
            ]));
            return $response->withStatus(200);

        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error reading Asterisk log: ' . $e->getMessage()
            ]));
            return $response->withStatus(500);
        }
    }

    /**
     * Get Asterisk log file path from configuration
     */
    private function getAstLogPath(): ?string
    {
        // Try to include the common.inc file to get the ASTERISK_LOG variable
        $commonIncPath = 'includes/common.inc';
        
        if (file_exists($commonIncPath)) {
            // Include the file to get the ASTERISK_LOG variable
            include $commonIncPath;
            
            // Check if ASTERISK_LOG is defined
            if (isset($ASTERISK_LOG) && !empty($ASTERISK_LOG)) {
                return $ASTERISK_LOG;
            }
        }
        
        // Default fallback path
        return '/var/log/asterisk/messages.log';
    }

    /**
     * Perform AllStar lookup across multiple networks
     */
    public function performAstLookup(Request $request, Response $response): Response
    {
        $currentUser = $this->getCurrentUser();
        
        // Allow lookup to proceed even without authentication (using default permissions)
        // The system is designed to work with default permissions for basic functionality
        if (!$currentUser) {
            $currentUser = 'default'; // Use default user for INI file resolution
        }

        // Check if user has ASTLKUSER permission
        if (!$this->hasUserPermission($currentUser, 'ASTLKUSER')) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'ASTLKUSER permission required'
            ]));
            return $response->withStatus(403);
        }

        $data = $request->getParsedBody();
        $lookupNode = trim($data['lookupNode'] ?? '');
        $localNode = trim($data['localNode'] ?? '');

        if (empty($lookupNode)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Please provide a node number or callsign to lookup'
            ]));
            return $response->withStatus(400);
        }

        if (empty($localNode)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Local node not specified'
            ]));
            return $response->withStatus(400);
        }

        try {
            // Get user's INI file
            $userIniFile = $this->getUserIniFile($currentUser);
            
            if (!file_exists($userIniFile)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "Couldn't load supervisor INI file: $userIniFile"
                ]));
                return $response->withStatus(500);
            }

            $config = parse_ini_file($userIniFile, true);

            if (!isset($config[$localNode])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "Node $localNode is not defined in $userIniFile"
                ]));
                return $response->withStatus(400);
            }

            $amiHost = $config[$localNode]['host'] ?? null;
            $amiUser = $config[$localNode]['user'] ?? null;
            $amiPass = $config[$localNode]['passwd'] ?? null;

            if (empty($amiHost) || empty($amiUser) || empty($amiPass)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "AMI host, user, or password not configured for node $localNode"
                ]));
                return $response->withStatus(500);
            }

            // Include AMI functions
            require_once 'includes/amifunctions.inc';

            // Connect to AMI
            $fp = \SimpleAmiClient::connect($amiHost);
            if ($fp === FALSE) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "Could not connect to Asterisk Manager at $amiHost for node $localNode"
                ]));
                return $response->withStatus(500);
            }

            // Login to AMI
            if (\SimpleAmiClient::login($fp, $amiUser, $amiPass) === FALSE) {
                \SimpleAmiClient::logoff($fp);
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "Could not login to Asterisk Manager for node $localNode with user $amiUser"
                ]));
                return $response->withStatus(500);
            }

            // Perform lookup
            $results = $this->performLookup($fp, $lookupNode, $localNode);

            \SimpleAmiClient::logoff($fp);

            $response->getBody()->write(json_encode([
                'success' => true,
                'results' => $results
            ]));
            return $response->withStatus(200);

        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error performing lookup: ' . $e->getMessage()
            ]));
            return $response->withStatus(500);
        }
    }

    /**
     * Perform the actual lookup across different networks
     */
    private function performLookup($fp, $lookupNode, $localNode): array
    {
        $results = [];
        $lookupNode = strtoupper($lookupNode);
        $intNode = (int)$lookupNode;

        // Determine lookup type and perform appropriate search
        if ("$intNode" != "$lookupNode") {
            // Do lookup by callsign
            $allstarResults = $this->doAllStarCallsignSearch($fp, $lookupNode, $localNode);
            if (!empty($allstarResults)) {
                $results[] = [
                    'type' => "AllStar Callsign Search for: \"$lookupNode\"",
                    'results' => $allstarResults
                ];
            }

            // Check EchoLink and IRLP for callsign searches
            $echolinkResults = $this->doEchoLinkCallsignSearch($fp, $lookupNode);
            if (!empty($echolinkResults)) {
                $results[] = [
                    'type' => "EchoLink Callsign Search for: \"$lookupNode\"",
                    'results' => $echolinkResults
                ];
            }

            $irlpResults = $this->doIrlpCallsignSearch($lookupNode);
            if (!empty($irlpResults)) {
                $results[] = [
                    'type' => "IRLP Callsign Search for: \"$lookupNode\"",
                    'results' => $irlpResults
                ];
            }

        } elseif ($intNode > 80000 && $intNode < 90000) {
            // Lookup by IRLP node number
            $irlpResults = $this->doIrlpNumberSearch($intNode);
            if (!empty($irlpResults)) {
                $results[] = [
                    'type' => "IRLP Node Number Search for: \"$lookupNode\"",
                    'results' => $irlpResults
                ];
            }

        } elseif ($intNode > 3000000) {
            // Lookup by EchoLink node number
            $echolinkResults = $this->doEchoLinkNumberSearch($fp, $intNode);
            if (!empty($echolinkResults)) {
                $results[] = [
                    'type' => "EchoLink Node Number Search for: \"$lookupNode\"",
                    'results' => $echolinkResults
                ];
            }

        } else {
            // Lookup by AllStar node number
            $allstarResults = $this->doAllStarNumberSearch($fp, $intNode, $localNode);
            if (!empty($allstarResults)) {
                $results[] = [
                    'type' => "AllStar Node Number Search for: \"$lookupNode\"",
                    'results' => $allstarResults
                ];
            }
        }

        return $results;
    }

    /**
     * Search AllStar database by callsign
     */
    private function doAllStarCallsignSearch($fp, $lookup, $localNode): array
    {
        $results = [];
        
        // Try multiple possible paths for the database
        $dbPaths = [
            'user_files/astdb.txt',
            '/var/www/html/supermon-ng/astdb.txt',
            '/var/www/html/supermon-ng/user_files/astdb.txt'
        ];
        
        $dbPath = null;
        foreach ($dbPaths as $path) {
            if (file_exists($path)) {
                $dbPath = $path;
                break;
            }
        }
        
        if (!$dbPath) {
            return $results;
        }

        $fh = fopen($dbPath, "r");
        if ($fh && flock($fh, LOCK_SH)) {
            while (($line = fgets($fh)) !== FALSE) {
                $arr_db = explode('|', trim($line));
                if (isset($arr_db[1]) && stripos($arr_db[1], $lookup) !== false) {
                    $node = trim($arr_db[0]);
                    $call = trim($arr_db[1]);
                    $desc = trim($arr_db[2] ?? '');
                    $qth = trim($arr_db[3] ?? '');
                    
                    $status = $this->getNodeStatus($node);
                    
                    $results[] = [
                        'node' => $node,
                        'callsign' => $call,
                        'description' => $desc,
                        'location' => $qth,
                        'status' => $status
                    ];
                }
            }
            flock($fh, LOCK_UN);
            fclose($fh);
        }

        return $results;
    }

    /**
     * Search AllStar database by node number
     */
    private function doAllStarNumberSearch($fp, $lookup, $localNode): array
    {
        $results = [];
        
        // Try multiple possible paths for the database
        $dbPaths = [
            'user_files/astdb.txt',
            '/var/www/html/supermon-ng/astdb.txt',
            '/var/www/html/supermon-ng/user_files/astdb.txt'
        ];
        
        $dbPath = null;
        foreach ($dbPaths as $path) {
            if (file_exists($path)) {
                $dbPath = $path;
                break;
            }
        }
        
        if (!$dbPath) {
            return $results;
        }

        $fh = fopen($dbPath, "r");
        if ($fh && flock($fh, LOCK_SH)) {
            while (($line = fgets($fh)) !== FALSE) {
                $arr_db = explode('|', trim($line));
                if (isset($arr_db[0]) && $arr_db[0] == $lookup) {
                    $node = trim($arr_db[0]);
                    $call = trim($arr_db[1]);
                    $desc = trim($arr_db[2] ?? '');
                    $qth = trim($arr_db[3] ?? '');
                    
                    $status = $this->getNodeStatus($node);
                    
                    $results[] = [
                        'node' => $node,
                        'callsign' => $call,
                        'description' => $desc,
                        'location' => $qth,
                        'status' => $status
                    ];
                }
            }
            flock($fh, LOCK_UN);
            fclose($fh);
        }

        return $results;
    }

    /**
     * Search EchoLink database by callsign
     */
    private function doEchoLinkCallsignSearch($fp, $lookup): array
    {
        $results = [];
        
        try {
            $ami = \SimpleAmiClient::command($fp, "echolink dbdump");
            
            if ($ami !== false && strpos($ami, 'No such command') === false) {
                $lines = explode("\n", $ami);
                foreach ($lines as $line) {
                    $parts = explode('|', trim($line));
                    if (count($parts) >= 3 && stripos($parts[1], $lookup) !== false) {
                        $results[] = [
                            'node' => $parts[0],
                            'callsign' => $parts[1],
                            'description' => '',
                            'location' => $parts[2],
                            'status' => 'Unknown'
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            // EchoLink lookup failed, continue
        }

        return $results;
    }

    /**
     * Search EchoLink database by node number
     */
    private function doEchoLinkNumberSearch($fp, $echonode): array
    {
        $results = [];
        $lookup = (int)substr("$echonode", 1);
        
        try {
            $ami = \SimpleAmiClient::command($fp, "echolink dbdump");
            
            if ($ami !== false && strpos($ami, 'No such command') === false) {
                $lines = explode("\n", $ami);
                foreach ($lines as $line) {
                    $parts = explode('|', trim($line));
                    if (count($parts) >= 3 && $parts[0] == $lookup) {
                        $results[] = [
                            'node' => $parts[0],
                            'callsign' => $parts[1],
                            'description' => '',
                            'location' => $parts[2],
                            'status' => 'Unknown'
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            // EchoLink lookup failed, continue
        }

        return $results;
    }

    /**
     * Search IRLP database by callsign
     */
    private function doIrlpCallsignSearch($lookup): array
    {
        $results = [];
        
        // Try multiple possible paths for the IRLP database
        $irlpPaths = [
            '/tmp/irlpdata.txt.gz',
            '/var/www/html/supermon-ng/irlpdata.txt.gz'
        ];
        
        $irlpPath = null;
        foreach ($irlpPaths as $path) {
            if (file_exists($path)) {
                $irlpPath = $path;
                break;
            }
        }
        
        if (!$irlpPath) {
            return $results;
        }

        try {
            $fh = gzopen($irlpPath, "r");
            if ($fh) {
                while (($line = gzgets($fh)) !== FALSE) {
                    $parts = explode('|', trim($line));
                    if (count($parts) >= 5 && stripos($parts[1], $lookup) !== false) {
                        $qth = trim($parts[2] . ", " . $parts[3] . " " . $parts[4]);
                        $results[] = [
                            'node' => $parts[0],
                            'callsign' => $parts[1],
                            'description' => '',
                            'location' => $qth,
                            'status' => 'Unknown'
                        ];
                    }
                }
                gzclose($fh);
            }
        } catch (Exception $e) {
            // IRLP lookup failed, continue
        }

        return $results;
    }

    /**
     * Search IRLP database by node number
     */
    private function doIrlpNumberSearch($irlpnode): array
    {
        $results = [];
        $lookup = (int)substr("$irlpnode", 1);
        
        // Try multiple possible paths for the IRLP database
        $irlpPaths = [
            '/tmp/irlpdata.txt.gz',
            '/var/www/html/supermon-ng/irlpdata.txt.gz'
        ];
        
        $irlpPath = null;
        foreach ($irlpPaths as $path) {
            if (file_exists($path)) {
                $irlpPath = $path;
                break;
            }
        }
        
        if (!$irlpPath) {
            return $results;
        }

        try {
            $fh = gzopen($irlpPath, "r");
            if ($fh) {
                while (($line = gzgets($fh)) !== FALSE) {
                    $parts = explode('|', trim($line));
                    if (count($parts) >= 5 && $parts[0] == $lookup) {
                        $qth = trim($parts[2] . ", " . $parts[3] . " " . $parts[4]);
                        $results[] = [
                            'node' => $parts[0],
                            'callsign' => $parts[1],
                            'description' => '',
                            'location' => $qth,
                            'status' => 'Unknown'
                        ];
                    }
                }
                gzclose($fh);
            }
        } catch (Exception $e) {
            // IRLP lookup failed, continue
        }

        return $results;
    }

    /**
     * Get node status using DNS query
     */
    private function getNodeStatus($node): string
    {
        try {
            $dnsQuery = shell_exec("nslookup $node 2>/dev/null");
            if (strpos($dnsQuery, 'NOT-FOUND') !== false) {
                return 'NOT FOUND';
            }
            return 'Unknown';
        } catch (Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Generate bubble chart URL and information
     */
    public function getBubbleChart(Request $request, Response $response): Response
    {
        $currentUser = $this->getCurrentUser();
        
        // Allow bubble chart to proceed even without authentication (using default permissions)
        if (!$currentUser) {
            $currentUser = 'default'; // Use default user for INI file resolution
        }

        // Check if user has BUBLUSER permission
        if (!$this->hasUserPermission($currentUser, 'BUBLUSER')) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'BUBLUSER permission required'
            ]));
            return $response->withStatus(403);
        }

        $data = $request->getParsedBody();
        $node = trim($data['node'] ?? '');
        $localNode = trim($data['localNode'] ?? '');

        // Determine which node to use (priority to 'node' parameter)
        $nodeToUse = '';
        $message = '';
        
        if ($node === '') {
            $nodeToUse = $localNode;
        } else {
            $nodeToUse = $node;
            $message = "Opening Bubble Chart for node " . htmlspecialchars($node);
        }

        if (empty($nodeToUse)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Please provide a node number'
            ]));
            return $response->withStatus(400);
        }

        // Build the stats URL
        $statsUrl = $this->buildBubbleChartStatsUrl($nodeToUse);

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => $message,
            'statsUrl' => $statsUrl,
            'node' => $nodeToUse
        ]));
        return $response->withStatus(200);
    }

    /**
     * Build stats URL for bubble chart
     */
    private function buildBubbleChartStatsUrl(string $node): string
    {
        $statsBaseUrl = "http://stats.allstarlink.org/getstatus.cgi";
        return $statsBaseUrl . "?" . urlencode($node);
    }

    /**
     * Get control panel configuration and available commands
     */
    public function getControlPanel(Request $request, Response $response): Response
    {
        $currentUser = $this->getCurrentUser();
        
        // Allow control panel to proceed even without authentication (using default permissions)
        if (!$currentUser) {
            $currentUser = 'default'; // Use default user for INI file resolution
        }

        // Check if user has CTRLUSER permission
        if (!$this->hasUserPermission($currentUser, 'CTRLUSER')) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'CTRLUSER permission required'
            ]));
            return $response->withStatus(403);
        }

        try {
            // Get control panel configuration
            $config = $this->getControlPanelConfig($currentUser);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'config' => $config
            ]));
            return $response->withStatus(200);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to load control panel configuration: ' . $e->getMessage()
            ]));
            return $response->withStatus(500);
        }
    }

    /**
     * Execute control panel command
     */
    public function executeControlCommand(Request $request, Response $response): Response
    {
        $currentUser = $this->getCurrentUser();
        
        // Allow control commands to proceed even without authentication (using default permissions)
        if (!$currentUser) {
            $currentUser = 'default'; // Use default user for INI file resolution
        }

        // Check if user has CTRLUSER permission
        if (!$this->hasUserPermission($currentUser, 'CTRLUSER')) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'CTRLUSER permission required'
            ]));
            return $response->withStatus(403);
        }

        $data = $request->getParsedBody();
        $command = trim($data['command'] ?? '');
        $node = trim($data['node'] ?? '');

        if (empty($command)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Command is required'
            ]));
            return $response->withStatus(400);
        }

        try {
            // Get control panel configuration
            $config = $this->getControlPanelConfig($currentUser);
            
            // Execute the command
            $result = $this->executeControlPanelCommand($command, $node, $config);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'result' => $result
            ]));
            return $response->withStatus(200);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to execute command: ' . $e->getMessage()
            ]));
            return $response->withStatus(500);
        }
    }

    /**
     * Get control panel configuration for user
     */
    private function getControlPanelConfig(string $user): array
    {
        include_once 'includes/common.inc';
        
        // Load control panel INI configuration
        $controlIniFile = $this->getControlPanelIniFile($user);
        
        if (!file_exists($controlIniFile)) {
            throw new \Exception("Control panel configuration file not found: $controlIniFile");
        }

        $config = parse_ini_file($controlIniFile, true);
        if ($config === false) {
            throw new \Exception("Failed to parse control panel configuration file");
        }

        return $config;
    }

    /**
     * Get control panel INI file path for user
     */
    private function getControlPanelIniFile(string $user): string
    {
        include_once 'includes/common.inc';
        
        // Ensure $USERFILES is defined
        if (!isset($USERFILES)) {
            $USERFILES = 'user_files';
        }
        
        if (file_exists("$USERFILES/authini.inc")) {
            include_once "$USERFILES/authini.inc";
        }

        $standardControlIni = "$USERFILES/controlpanel.ini";
        
        if (isset($CNTRLININAME) && isset($user)) {
            if (array_key_exists($user, $CNTRLININAME) && $CNTRLININAME[$user] !== "") {
                return $this->checkControlIniFile($USERFILES, $CNTRLININAME[$user]);
            } else {
                return $this->checkControlIniFile($USERFILES, "cntrlnolog.ini");
            }
        } else {
            return $standardControlIni;
        }
    }

    /**
     * Check if control INI file exists, return fallback if not
     */
    private function checkControlIniFile(string $fdir, string $fname): string
    {
        $fullPath = "$fdir/$fname";
        if (file_exists($fullPath)) {
            return $fullPath;
        }
        
        // Fallback to standard control panel INI
        $fallbackPath = "$fdir/controlpanel.ini";
        if (file_exists($fallbackPath)) {
            return $fallbackPath;
        }
        
        throw new \Exception("No control panel configuration file found");
    }

    /**
     * Execute control panel command
     */
    private function executeControlPanelCommand(string $command, string $node, array $config): array
    {
        // Map command to actual execution
        switch (strtolower($command)) {
            case 'rpt reload':
                return $this->executeRptReload($node, $config);
            case 'iax2 reload':
                return $this->executeIax2Reload($node, $config);
            case 'extensions reload':
                return $this->executeExtensionsReload($node, $config);
            case 'echolink dbdump':
                return $this->executeEchoLinkDbDump($node, $config);
            case 'astup':
                return $this->executeAstUp($node, $config);
            case 'astdn':
                return $this->executeAstDown($node, $config);
            default:
                throw new \Exception("Unknown command: $command");
        }
    }

    /**
     * Execute rpt reload command
     */
    private function executeRptReload(string $node, array $config): array
    {
        // For now, use shell command directly (AMI can be enabled later)
        $result = shell_exec("sudo /usr/bin/astup.sh rpt reload $node 2>&1");
        return [
            'command' => 'rpt reload',
            'node' => $node,
            'result' => $result,
            'method' => 'Shell'
        ];
    }

    /**
     * Execute iax2 reload command
     */
    private function executeIax2Reload(string $node, array $config): array
    {
        // For now, use shell command directly (AMI can be enabled later)
        $result = shell_exec("sudo /usr/bin/astup.sh iax2 reload 2>&1");
        return [
            'command' => 'iax2 reload',
            'node' => $node,
            'result' => $result,
            'method' => 'Shell'
        ];
    }

    /**
     * Execute extensions reload command
     */
    private function executeExtensionsReload(string $node, array $config): array
    {
        // For now, use shell command directly (AMI can be enabled later)
        $result = shell_exec("sudo /usr/bin/astup.sh extensions reload 2>&1");
        return [
            'command' => 'extensions reload',
            'node' => $node,
            'result' => $result,
            'method' => 'Shell'
        ];
    }

    /**
     * Execute echolink dbdump command
     */
    private function executeEchoLinkDbDump(string $node, array $config): array
    {
        // For now, use shell command directly (AMI can be enabled later)
        $result = shell_exec("sudo /usr/bin/astup.sh echolink dbdump 2>&1");
        return [
            'command' => 'echolink dbdump',
            'node' => $node,
            'result' => $result,
            'method' => 'Shell'
        ];
    }

    /**
     * Execute astup command
     */
    private function executeAstUp(string $node, array $config): array
    {
        $result = shell_exec("sudo /usr/bin/astup.sh 2>&1");
        return [
            'command' => 'astup',
            'node' => $node,
            'result' => $result,
            'method' => 'Shell'
        ];
    }

    /**
     * Execute astdn command
     */
    private function executeAstDown(string $node, array $config): array
    {
        $result = shell_exec("sudo /usr/bin/astdn.sh 2>&1");
        return [
            'command' => 'astdn',
            'node' => $node,
            'result' => $result,
            'method' => 'Shell'
        ];
    }

}
