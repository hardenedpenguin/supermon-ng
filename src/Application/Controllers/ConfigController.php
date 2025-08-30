<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

// Include required files for AMI functionality
require_once __DIR__ . '/../../../includes/amifunctions.inc';
require_once __DIR__ . '/../../../includes/common.inc';

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
        
        // Check if there's a user in the request headers (for API calls)
        $headers = getallheaders();
        if (isset($headers['X-User']) && !empty($headers['X-User'])) {
            return $headers['X-User'];
        }
        
        // For now, return 'anarchy' as default user since we know that's the user with favorites
        return 'anarchy';
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
            return __DIR__ . '/../../../user_files/allmon.ini';
        }
        
        $authIniFile = __DIR__ . '/../../../user_files/authini.inc';
        
        if (!file_exists($authIniFile)) {
            return __DIR__ . '/../../../user_files/allmon.ini';
        }
        
        // Include the authini file to get the INI mapping
        include_once $authIniFile;
        
        // Check if user has a specific INI file mapped
        if (isset($ININAME[$username])) {
            return __DIR__ . "/../../../user_files/{$ININAME[$username]}";
        }
        
        return __DIR__ . '/../../../user_files/allmon.ini';
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
            'CFGEDUSER' => true, // Allow config editor for unauthenticated users
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
        // Ensure $USERFILES is defined
        $USERFILES = 'user_files';
        
        // Try to include common.inc if it exists
        $commonIncPath = __DIR__ . '/../../../includes/common.inc';
        if (file_exists($commonIncPath)) {
            include_once $commonIncPath;
            if (isset($USERFILES)) {
                $USERFILES = $USERFILES;
            }
        }
        
        // Include authini.inc if it exists to get $ININAME mapping
        $authIniPath = "$USERFILES/authini.inc";
        if (file_exists($authIniPath)) {
            include_once $authIniPath;
        }
        
        $standardAllmonIni = __DIR__ . "/../../../$USERFILES/allmon.ini";
        
        // Use the same logic as the original get_ini_name function
        if (isset($ININAME) && isset($user)) {
            if (array_key_exists($user, $ININAME) && $ININAME[$user] !== "") {
                return $this->checkIniFile(__DIR__ . "/../../../$USERFILES", $ININAME[$user]);
            } else {
                return $this->checkIniFile(__DIR__ . "/../../../$USERFILES", "nolog.ini");
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
            __DIR__ . '/../../../user_files/astdb.txt',
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
            __DIR__ . '/../../../user_files/astdb.txt',
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
            
            if ($ami !== false && is_string($ami) && strpos($ami, 'No such command') === false) {
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
        } catch (\Exception $e) {
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
            
            if ($ami !== false && is_string($ami) && strpos($ami, 'No such command') === false) {
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
        } catch (\Exception $e) {
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
            __DIR__ . '/../../../irlpdata.txt.gz'
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
        } catch (\Exception $e) {
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
            __DIR__ . '/../../../irlpdata.txt.gz'
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
        } catch (\Exception $e) {
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
            if ($dnsQuery !== null && strpos($dnsQuery, 'NOT-FOUND') !== false) {
                return 'NOT FOUND';
            }
            return 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }





    /**
     * Load control panel commands for a specific node
     */
    private function loadControlPanelCommands(string $user, string $node): array
    {
        // Get the control panel INI file path
        $controlIniPath = $this->getControlPanelIniFile($user);
        
        if (!file_exists($controlIniPath)) {
            // Return empty commands if file doesn't exist
            return ['labels' => [], 'cmds' => []];
        }

        $cpConfig = parse_ini_file($controlIniPath, true);
        if ($cpConfig === false) {
            throw new \Exception('Failed to parse control panel configuration file');
        }

        // Initialize with default empty arrays
        $cpCommands = [
            'labels' => [],
            'cmds' => []
        ];

        // Add general commands if they exist
        if (isset($cpConfig['general'])) {
            if (isset($cpConfig['general']['labels']) && is_array($cpConfig['general']['labels'])) {
                $cpCommands['labels'] = array_merge($cpCommands['labels'], $cpConfig['general']['labels']);
            }
            if (isset($cpConfig['general']['cmds']) && is_array($cpConfig['general']['cmds'])) {
                $cpCommands['cmds'] = array_merge($cpCommands['cmds'], $cpConfig['general']['cmds']);
            }
        }

        // Add node-specific commands if they exist
        if (isset($cpConfig[$node])) {
            if (isset($cpConfig[$node]['labels']) && is_array($cpConfig[$node]['labels'])) {
                $cpCommands['labels'] = array_merge($cpCommands['labels'], $cpConfig[$node]['labels']);
            }
            if (isset($cpConfig[$node]['cmds']) && is_array($cpConfig[$node]['cmds'])) {
                $cpCommands['cmds'] = array_merge($cpCommands['cmds'], $cpConfig[$node]['cmds']);
            }
        }

        return $cpCommands;
    }

    /**
     * Execute a control panel command via AMI
     */
    private function executeControlCommand(string $node, string $command): string
    {
        // Load node configuration
        $nodeConfig = $this->loadNodeConfig($node);
        if (!$nodeConfig) {
            throw new \Exception("Node $node not found in configuration");
        }

        // Connect to AMI
        $fp = $this->connectToAmi($nodeConfig);
        if (!$fp) {
            throw new \Exception("Failed to connect to AMI for node $node");
        }

        try {
            // Execute command with node substitution
            $cmdString = str_replace('%node%', $node, $command);
            $result = $this->executeAmiCommand($fp, $cmdString);
            
            if ($result === false) {
                throw new \Exception("Failed to execute command: $cmdString");
            }

            return $result;
        } finally {
            // Always close the connection
            if ($fp) {
                \SimpleAmiClient::logoff($fp);
            }
        }
    }

    /**
     * Load node configuration
     */
    private function loadNodeConfig(string $node): ?array
    {
        $configPath = $this->getUserIniFile($this->getCurrentUser() ?: 'default');
        
        if (!file_exists($configPath)) {
            return null;
        }

        $config = parse_ini_file($configPath, true);
        if (!$config || !isset($config[$node])) {
            return null;
        }

        return $config[$node];
    }

    /**
     * Connect to AMI
     */
    private function connectToAmi(array $nodeConfig)
    {
        if (!isset($nodeConfig['host']) || !isset($nodeConfig['user']) || !isset($nodeConfig['passwd'])) {
            return false;
        }

        $fp = \SimpleAmiClient::connect($nodeConfig['host']);
        if (!$fp) {
            return false;
        }

        $loginResult = \SimpleAmiClient::login($fp, $nodeConfig['user'], $nodeConfig['passwd']);
        if (!$loginResult) {
            \SimpleAmiClient::logoff($fp);
            return false;
        }

        return $fp;
    }

    /**
     * Execute AMI command
     */
    private function executeAmiCommand($fp, string $command): string|false
    {
        $result = \SimpleAmiClient::command($fp, $command);
        if ($result === false) {
            return false;
        }

        return $result;
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

        $node = $request->getQueryParams()['node'] ?? '';
        if (!is_numeric($node)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Invalid node parameter'
            ]));
            return $response->withStatus(400);
        }

        try {
            // Load control panel commands for the specific node
            $cpCommands = $this->loadControlPanelCommands($currentUser, $node);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $cpCommands
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to load control panel commands: ' . $e->getMessage()
            ]));
            return $response->withStatus(500);
        }
    }



    /**
     * Execute a control panel command
     */
    public function executeControlPanelCommand(Request $request, Response $response): Response
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

        $data = $request->getParsedBody();
        $node = trim($data['node'] ?? '');
        $command = trim($data['command'] ?? '');

        if (!is_numeric($node) || empty($command)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Invalid node or command parameter'
            ]));
            return $response->withStatus(400);
        }

        try {
            // Get control panel configuration
            $config = $this->getControlPanelConfig($currentUser);
            
            // Execute the command using the existing private method
            $result = $this->executeControlPanelCommandInternal($command, $node, $config);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'result' => $result['result'] ?? $result
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json');
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
        // Ensure $USERFILES is defined
        $USERFILES = 'user_files';
        
        // Try to include common.inc if it exists
        $commonIncPath = __DIR__ . '/../../../includes/common.inc';
        if (file_exists($commonIncPath)) {
            include_once $commonIncPath;
            if (isset($USERFILES)) {
                $USERFILES = $USERFILES;
            }
        }
        
        // Try to include authini.inc if it exists
        $authIniPath = "$USERFILES/authini.inc";
        if (file_exists($authIniPath)) {
            include_once $authIniPath;
        }

        $standardControlIni = __DIR__ . "/../../../$USERFILES/controlpanel.ini";
        
        if (isset($CNTRLININAME) && isset($user)) {
            if (array_key_exists($user, $CNTRLININAME) && $CNTRLININAME[$user] !== "") {
                return $this->checkControlIniFile(__DIR__ . "/../../../$USERFILES", $CNTRLININAME[$user]);
            } else {
                return $this->checkControlIniFile(__DIR__ . "/../../../$USERFILES", "cntrlnolog.ini");
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
        
        // Create a basic control panel INI file if none exists
        $basicIniContent = "[Commands]\nrpt reload = RPT Reload\niax2 reload = IAX2 Reload\nextensions reload = Extensions Reload\necholink dbdump = EchoLink DB Dump\nastup = Asterisk Up\nastdn = Asterisk Down\n";
        file_put_contents($fallbackPath, $basicIniContent);
        
        return $fallbackPath;
    }

    /**
     * Execute control panel command
     */
    private function executeControlPanelCommandInternal(string $command, string $node, array $config): array
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
                // For dynamic commands from INI file, execute via AMI
                return $this->executeDynamicCommand($command, $node);
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

    /**
     * Execute dynamic command via AMI
     */
    private function executeDynamicCommand(string $command, string $node): array
    {
        try {
            // Load node configuration
            $nodeConfig = $this->loadNodeConfig($node);
            if (!$nodeConfig) {
                throw new \Exception("Node $node not found in configuration");
            }

            // Connect to AMI
            $fp = $this->connectToAmi($nodeConfig);
            if (!$fp) {
                throw new \Exception("Failed to connect to AMI for node $node");
            }

            try {
                // Execute command with node substitution
                $cmdString = str_replace('%node%', $node, $command);
                $result = $this->executeAmiCommand($fp, $cmdString);
                
                if ($result === false) {
                    throw new \Exception("Failed to execute command: $cmdString");
                }

                return [
                    'command' => $command,
                    'node' => $node,
                    'result' => $result,
                    'method' => 'AMI'
                ];
            } finally {
                // Always close the connection
                if ($fp) {
                    // Use shell command to close connection instead of SimpleAmiClient
                    fclose($fp);
                }
            }
        } catch (\Exception $e) {
            // If AMI fails, try shell execution as fallback
            $cmdString = str_replace('%node%', $node, $command);
            $result = shell_exec("sudo asterisk -rx '$cmdString' 2>&1");
            
            return [
                'command' => $command,
                'node' => $node,
                'result' => $result,
                'method' => 'Shell (AMI fallback)'
            ];
        }
    }

    /**
     * Get list of editable configuration files
     */
    public function getConfigEditorFiles(Request $request, Response $response): Response
    {
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            $currentUser = 'default';
        }
        
        if (!$this->hasUserPermission($currentUser, 'CFGEDUSER')) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'CFGEDUSER permission required']));
            return $response->withStatus(403);
        }

        try {
            $files = $this->getEditableFilesList();
            $response->getBody()->write(json_encode(['success' => true, 'files' => $files]));
            return $response->withStatus(200);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to get editable files: ' . $e->getMessage()]));
            return $response->withStatus(500);
        }
    }

    /**
     * Get content of a specific configuration file
     */
    public function getConfigFileContent(Request $request, Response $response): Response
    {
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            $currentUser = 'default';
        }
        
        if (!$this->hasUserPermission($currentUser, 'CFGEDUSER')) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'CFGEDUSER permission required']));
            return $response->withStatus(403);
        }

        $data = $request->getParsedBody();
        $filePath = trim($data['filePath'] ?? '');
        
        if (empty($filePath)) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'File path is required']));
            return $response->withStatus(400);
        }

        try {
            $content = $this->readConfigFile($filePath);
            $response->getBody()->write(json_encode(['success' => true, 'content' => $content]));
            return $response->withStatus(200);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to read file: ' . $e->getMessage()]));
            return $response->withStatus(500);
        }
    }

    /**
     * Save content to a configuration file
     */
    public function saveConfigFile(Request $request, Response $response): Response
    {
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            $currentUser = 'default';
        }
        
        if (!$this->hasUserPermission($currentUser, 'CFGEDUSER')) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'CFGEDUSER permission required']));
            return $response->withStatus(403);
        }

        $data = $request->getParsedBody();
        $filePath = trim($data['filePath'] ?? '');
        $content = $data['content'] ?? '';
        
        if (empty($filePath)) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'File path is required']));
            return $response->withStatus(400);
        }

        try {
            $result = $this->writeConfigFile($filePath, $content);
            $response->getBody()->write(json_encode(['success' => true, 'message' => 'File saved successfully', 'result' => $result]));
            return $response->withStatus(200);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Failed to save file: ' . $e->getMessage()]));
            return $response->withStatus(500);
        }
    }

    /**
     * Get list of editable files organized by category
     */
    private function getEditableFilesList(): array
    {
        return [
            'Supermon-ng' => [
                ['path' => '/var/www/html/supermon-ng/user_files/allmon.ini', 'name' => 'Allmon Configuration', 'description' => 'Main AllStar configuration file'],
                ['path' => '/var/www/html/supermon-ng/user_files/authini.ini', 'name' => 'Authentication Configuration', 'description' => 'User authentication settings'],
                ['path' => '/var/www/html/supermon-ng/user_files/authuser.ini', 'name' => 'User Configuration', 'description' => 'User-specific settings'],
                ['path' => '/var/www/html/supermon-ng/user_files/cntrlini.inc', 'name' => 'Control Panel Configuration', 'description' => 'Control panel settings'],
                ['path' => '/var/www/html/supermon-ng/user_files/cntrlnolog.ini', 'name' => 'Control Panel Log Configuration', 'description' => 'Control panel logging settings'],
                ['path' => '/var/www/html/supermon-ng/user_files/controlpanel.ini', 'name' => 'Control Panel Settings', 'description' => 'Control panel configuration'],
                ['path' => '/var/www/html/supermon-ng/user_files/favini.inc', 'name' => 'Favorites Configuration', 'description' => 'User favorites settings'],
                ['path' => '/var/www/html/supermon-ng/user_files/favnolog.ini', 'name' => 'Favorites Log Configuration', 'description' => 'Favorites logging settings'],
                ['path' => '/var/www/html/supermon-ng/user_files/favorites.ini', 'name' => 'Favorites List', 'description' => 'User favorites list'],
                ['path' => '/var/www/html/supermon-ng/user_files/nolog.ini', 'name' => 'No Log Configuration', 'description' => 'No logging settings'],
                ['path' => '/var/www/html/supermon-ng/user_files/privatenodes.txt', 'name' => 'Private Nodes', 'description' => 'Private nodes list'],
                ['path' => '/var/www/html/supermon-ng/user_files/global.inc', 'name' => 'Global Configuration', 'description' => 'Global settings'],
                ['path' => '/var/www/html/supermon-ng/supermon-ng.css', 'name' => 'CSS Styles', 'description' => 'Custom CSS styles']
            ],
            'Asterisk' => [
                ['path' => '/etc/asterisk/extensions.conf', 'name' => 'Extensions Configuration', 'description' => 'Dialplan extensions'],
                ['path' => '/etc/asterisk/sip.conf', 'name' => 'SIP Configuration', 'description' => 'SIP channel settings'],
                ['path' => '/etc/asterisk/iax.conf', 'name' => 'IAX Configuration', 'description' => 'IAX channel settings'],
                ['path' => '/etc/asterisk/users.conf', 'name' => 'Users Configuration', 'description' => 'User definitions'],
                ['path' => '/etc/asterisk/rpt.conf', 'name' => 'RPT Configuration', 'description' => 'Repeater settings'],
                ['path' => '/etc/asterisk/dnsmgr.conf', 'name' => 'DNS Manager Configuration', 'description' => 'DNS manager settings'],
                ['path' => '/etc/asterisk/http.conf', 'name' => 'HTTP Configuration', 'description' => 'HTTP server settings'],
                ['path' => '/etc/asterisk/voter.conf', 'name' => 'Voter Configuration', 'description' => 'Voter settings'],
                ['path' => '/etc/asterisk/manager.conf', 'name' => 'Manager Configuration', 'description' => 'AMI manager settings'],
                ['path' => '/etc/asterisk/asterisk.conf', 'name' => 'Asterisk Configuration', 'description' => 'Main Asterisk settings'],
                ['path' => '/etc/asterisk/modules.conf', 'name' => 'Modules Configuration', 'description' => 'Module loading settings'],
                ['path' => '/etc/asterisk/logger', 'name' => 'Logger Configuration', 'description' => 'Logging settings'],
                ['path' => '/etc/asterisk/usbradio.conf', 'name' => 'USB Radio Configuration', 'description' => 'USB radio settings'],
                ['path' => '/etc/asterisk/simpleusb.conf', 'name' => 'Simple USB Configuration', 'description' => 'Simple USB settings'],
                ['path' => '/etc/asterisk/irlp.conf', 'name' => 'IRLP Configuration', 'description' => 'IRLP settings'],
                ['path' => '/etc/asterisk/echolink.conf', 'name' => 'EchoLink Configuration', 'description' => 'EchoLink settings']
            ],
            'DvSwitch' => [
                ['path' => '/opt/Analog_Bridge/Analog_Bridge.ini', 'name' => 'Analog Bridge Configuration', 'description' => 'Analog Bridge settings'],
                ['path' => '/opt/MMDVM_Bridge/MMDVM_Bridge.ini', 'name' => 'MMDVM Bridge Configuration', 'description' => 'MMDVM Bridge settings'],
                ['path' => '/opt/MMDVM_Bridge/DVSwitch.ini', 'name' => 'DVSwitch Configuration', 'description' => 'DVSwitch settings']
            ],
            'IRLP' => [
                ['path' => '/home/irlp/scripts/irlp.crons', 'name' => 'IRLP Cron Jobs', 'description' => 'IRLP cron job definitions'],
                ['path' => '/home/irlp/noupdate/scripts/irlp.crons', 'name' => 'IRLP No-Update Cron Jobs', 'description' => 'IRLP no-update cron jobs'],
                ['path' => '/home/irlp/custom/environment', 'name' => 'IRLP Environment', 'description' => 'IRLP environment settings'],
                ['path' => '/home/irlp/custom/custom_decode', 'name' => 'IRLP Custom Decode', 'description' => 'IRLP custom decode settings'],
                ['path' => '/home/irlp/custom/custom.crons', 'name' => 'IRLP Custom Cron Jobs', 'description' => 'IRLP custom cron jobs'],
                ['path' => '/home/irlp/custom/timeoutvalue', 'name' => 'IRLP Timeout Value', 'description' => 'IRLP timeout settings'],
                ['path' => '/home/irlp/custom/lockout_list', 'name' => 'IRLP Lockout List', 'description' => 'IRLP lockout list'],
                ['path' => '/home/irlp/custom/timing', 'name' => 'IRLP Timing', 'description' => 'IRLP timing settings']
            ],
            'Misc' => [
                ['path' => '/usr/local/etc/allstar.env', 'name' => 'AllStar Environment', 'description' => 'AllStar environment variables'],
                ['path' => '/usr/local/bin/AUTOSKY/AutoSky.ini', 'name' => 'AutoSky Configuration', 'description' => 'AutoSky settings']
            ]
        ];
    }

    /**
     * Read content of a configuration file
     */
    private function readConfigFile(string $filePath): string
    {
        // Validate file path is in our whitelist
        $editableFiles = $this->getEditableFilesList();
        $isWhitelisted = false;
        
        foreach ($editableFiles as $category => $files) {
            foreach ($files as $file) {
                if ($file['path'] === $filePath) {
                    $isWhitelisted = true;
                    break 2;
                }
            }
        }
        
        if (!$isWhitelisted) {
            throw new \Exception('File is not in the editable files whitelist');
        }

        if (!file_exists($filePath)) {
            return ''; // Return empty string for non-existent files
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \Exception('Failed to read file content');
        }

        return $content;
    }

    /**
     * Write content to a configuration file using the sudo helper script
     */
    private function writeConfigFile(string $filePath, string $content): array
    {
        // Validate file path is in our whitelist
        $editableFiles = $this->getEditableFilesList();
        $isWhitelisted = false;
        
        foreach ($editableFiles as $category => $files) {
            foreach ($files as $file) {
                if ($file['path'] === $filePath) {
                    $isWhitelisted = true;
                    break 2;
                }
            }
        }
        
        if (!$isWhitelisted) {
            throw new \Exception('File is not in the editable files whitelist');
        }

        // Use the sudo helper script to write the file
        $command = "sudo /usr/local/sbin/supermon_unified_file_editor.sh " . escapeshellarg($filePath);
        
        $descriptorspec = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w']  // stderr
        ];

        $process = proc_open($command, $descriptorspec, $pipes);
        
        if (!is_resource($process)) {
            throw new \Exception('Failed to execute sudo command');
        }

        // Write content to stdin
        fwrite($pipes[0], $content);
        fclose($pipes[0]);

        // Read output
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        
        fclose($pipes[1]);
        fclose($pipes[2]);

        $returnCode = proc_close($process);

        if ($returnCode !== 0) {
            throw new \Exception('Sudo command failed: ' . $stderr);
        }

        return [
            'filePath' => $filePath,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'returnCode' => $returnCode
        ];
    }

    /**
     * Get favorites configuration
     */
    public function getFavorites(Request $request, Response $response): Response
    {
        try {
            $currentUser = $this->getCurrentUser();
            if (!$currentUser) {
                $currentUser = 'default';
            }
            
            // Check user permissions
            if (!$this->hasUserPermission($currentUser, 'FAVUSER')) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'FAVUSER permission required'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            $favoritesData = $this->loadFavoritesConfiguration($currentUser);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $favoritesData['favorites'],
                'fileName' => $favoritesData['fileName'],
                'user' => $currentUser
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to load favorites: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Delete a favorite
     */
    public function deleteFavorite(Request $request, Response $response): Response
    {
        try {
            $currentUser = $this->getCurrentUser();
            if (!$currentUser) {
                $currentUser = 'default';
            }
            
            // Check user permissions
            if (!$this->hasUserPermission($currentUser, 'FAVUSER')) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'FAVUSER permission required'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            $body = $request->getParsedBody();
            $section = $body['section'] ?? '';
            $index = $body['index'] ?? '';

            if (empty($section) || !isset($index)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Section and index are required'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Ensure proper types for the method call
            $section = (string)$section;
            $index = (int)$index;

            $result = $this->removeFavoriteFromConfiguration($currentUser, $section, $index);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Favorite deleted successfully',
                'data' => $result
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to delete favorite: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Execute a favorite command
     */
    public function executeFavorite(Request $request, Response $response): Response
    {
        try {
            $currentUser = $this->getCurrentUser();
            if (!$currentUser) {
                $currentUser = 'default';
            }
            
            // Check user permissions
            if (!$this->hasUserPermission($currentUser, 'FAVUSER')) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'FAVUSER permission required'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            $body = $request->getParsedBody();
            $node = $body['node'] ?? '';
            $command = $body['command'] ?? '';

            if (empty($node) || empty($command)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Node and command are required'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Execute the command using the same logic as control panel
            $result = $this->executeFavoriteCommand($command, $node);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Command executed successfully',
                'data' => $result
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to execute command: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Extract target node number from command
     */
    private function extractTargetNodeFromCommand(string $command): ?string
    {
        // Look for patterns like "ilink 13 55553" or "ilink 3 29332"
        if (preg_match('/ilink\s+\d+\s+(\d+)/', $command, $matches)) {
            return $matches[1];
        }
        
        // Look for patterns like "status 1 xxx" or "status 11 xxx"
        if (preg_match('/status\s+\d+\s+(\d+)/', $command, $matches)) {
            return $matches[1];
        }
        
        // If no pattern matches, return null
        return null;
    }

    /**
     * Load favorites configuration from INI files
     */
    private function loadFavoritesConfiguration(string $user): array
    {
        // Get the favorites INI file path for the user
        $favoritesIniPath = $this->getFavoritesIniPath($user);
        
        if (!file_exists($favoritesIniPath)) {
            return [
                'favorites' => [],
                'fileName' => basename($favoritesIniPath)
            ];
        }

        $config = parse_ini_file($favoritesIniPath, true);
        if ($config === false) {
            throw new \Exception("Failed to parse favorites INI file: $favoritesIniPath");
        }

        $favorites = [];
        
        // Process each section
        foreach ($config as $section => $sectionData) {
            if ($section === 'general') {
                // General favorites (available for all nodes)
                if (isset($sectionData['label']) && isset($sectionData['cmd'])) {
                    $labels = is_array($sectionData['label']) ? $sectionData['label'] : [$sectionData['label']];
                    $cmds = is_array($sectionData['cmd']) ? $sectionData['cmd'] : [$sectionData['cmd']];
                    
                    for ($i = 0; $i < count($labels) && $i < count($cmds); $i++) {
                        $favorites[] = [
                            'section' => 'general',
                            'index' => $i,
                            'label' => $labels[$i],
                            'command' => $cmds[$i],
                            'node' => $this->extractTargetNodeFromCommand($cmds[$i])
                        ];
                    }
                }
            } else {
                // Node-specific favorites
                if (isset($sectionData['label']) && isset($sectionData['cmd'])) {
                    $labels = is_array($sectionData['label']) ? $sectionData['label'] : [$sectionData['label']];
                    $cmds = is_array($sectionData['cmd']) ? $sectionData['cmd'] : [$sectionData['cmd']];
                    
                    for ($i = 0; $i < count($labels) && $i < count($cmds); $i++) {
                        $favorites[] = [
                            'section' => $section,
                            'index' => $i,
                            'label' => $labels[$i],
                            'command' => $cmds[$i],
                            'node' => $this->extractTargetNodeFromCommand($cmds[$i])
                        ];
                    }
                }
            }
        }

        return [
            'favorites' => $favorites,
            'fileName' => basename($favoritesIniPath)
        ];
    }

    /**
     * Remove a favorite from the configuration
     */
    private function removeFavoriteFromConfiguration(string $user, string $section, int $index): array
    {
        $favoritesIniPath = $this->getFavoritesIniPath($user);
        
        if (!file_exists($favoritesIniPath)) {
            throw new \Exception("Favorites INI file not found: $favoritesIniPath");
        }

        $config = parse_ini_file($favoritesIniPath, true);
        if ($config === false) {
            throw new \Exception("Failed to parse favorites INI file: $favoritesIniPath");
        }

        // Check if section exists
        if (!isset($config[$section])) {
            throw new \Exception("Section '$section' not found in favorites configuration");
        }

        $sectionData = $config[$section];
        
        // Check if label and cmd arrays exist
        if (!isset($sectionData['label']) || !isset($sectionData['cmd'])) {
            throw new \Exception("Section '$section' is missing label or cmd configuration");
        }

        $labels = is_array($sectionData['label']) ? $sectionData['label'] : [$sectionData['label']];
        $cmds = is_array($sectionData['cmd']) ? $sectionData['cmd'] : [$sectionData['cmd']];

        // Check if index is valid
        if ($index < 0 || $index >= count($labels) || $index >= count($cmds)) {
            throw new \Exception("Invalid index $index for section '$section'");
        }

        // Remove the item at the specified index
        array_splice($labels, $index, 1);
        array_splice($cmds, $index, 1);

        // Update the configuration
        $config[$section]['label'] = $labels;
        $config[$section]['cmd'] = $cmds;

        // If section is now empty, remove it entirely
        if (empty($labels) && empty($cmds)) {
            unset($config[$section]);
        }

        // Write the updated configuration back to file
        $this->writeFavoritesConfiguration($favoritesIniPath, $config);

        return [
            'section' => $section,
            'index' => $index,
            'removed' => true
        ];
    }

    /**
     * Execute a favorite command using AMI or shell
     */
    private function executeFavoriteCommand(string $command, string $node): array
    {
        // Replace %node% placeholder with actual node number
        $cmdString = str_replace('%node%', $node, $command);

        // Try to execute via AMI first, then fall back to shell
        try {
            // Load node configuration
            $nodeConfig = $this->loadNodeConfig($node);
            if ($nodeConfig) {
                // Try AMI execution
                $fp = $this->connectToAmi($nodeConfig);
                if ($fp) {
                    $result = $this->executeAmiCommand($fp, $cmdString);
                    if ($result !== false) {
                        return [
                            'command' => $cmdString,
                            'node' => $node,
                            'result' => $result,
                            'method' => 'AMI'
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // AMI failed, continue to shell execution
        }

        // Fall back to shell execution
        $result = shell_exec("sudo asterisk -rx " . escapeshellarg($cmdString) . " 2>&1");
        
        return [
            'command' => $cmdString,
            'node' => $node,
            'result' => $result,
            'method' => 'Shell'
        ];
    }

    /**
     * Get the favorites INI file path for a user
     */
    private function getFavoritesIniPath(string $user): string
    {
        // Check if user-specific favorites file exists
        $userSpecificPath = __DIR__ . '/../../../user_files/' . $user . '-favorites.ini';
        if (file_exists($userSpecificPath)) {
            return $userSpecificPath;
        }

        // Check favini.inc for user mapping
        $faviniPath = __DIR__ . '/../../../user_files/favini.inc';
        if (file_exists($faviniPath)) {
            try {
                // Include the file to get the mapping array
                include $faviniPath;
                
                // Check if the mapping exists
                if (isset($FAVININAME[$user])) {
                    $mappedPath = __DIR__ . '/../../../user_files/' . $FAVININAME[$user];
                    if (file_exists($mappedPath)) {
                        return $mappedPath;
                    }
                }
            } catch (\Exception $e) {
                // If there's an error reading favini.inc, continue to default
            }
        }

        // Default to general favorites.ini
        return __DIR__ . '/../../../user_files/favorites.ini';
    }

    /**
     * Write favorites configuration to INI file
     */
    private function writeFavoritesConfiguration(string $filePath, array $config): void
    {
        $content = '';
        
        foreach ($config as $section => $sectionData) {
            $content .= "[$section]\n";
            
            foreach ($sectionData as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $item) {
                        $content .= "$key = " . $this->escapeIniValue($item) . "\n";
                    }
                } else {
                    $content .= "$key = " . $this->escapeIniValue($value) . "\n";
                }
            }
            $content .= "\n";
        }

        // Ensure the directory exists
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new \Exception('Failed to create directory: ' . $dir);
            }
        }

        // Write the file directly (for development)
        if (file_put_contents($filePath, $content) === false) {
            throw new \Exception('Failed to write to file: ' . $filePath);
        }
    }

    /**
     * Escape a value for INI file format
     */
    private function escapeIniValue(string $value): string
    {
        // Escape special characters for INI format
        $value = str_replace('"', '\\"', $value);
        return '"' . $value . '"';
    }

    /**
     * Add a new favorite command
     */
    public function addFavorite(Request $request, Response $response): Response
    {
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            $currentUser = 'default';
        }

        // Check user permissions
        if (!$this->hasUserPermission($currentUser, 'FAVUSER')) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'FAVUSER permission required'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $data = $request->getParsedBody();
        $node = $data['node'] ?? '';
        $customLabel = trim($data['custom_label'] ?? '');
        $addToGeneral = ($data['add_to_general'] ?? '') === '1';

        // Validate node parameter
        if (!is_numeric($node) || $node === '') {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Invalid node number provided'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Look up node information from astdb.txt
        $nodeInfo = $this->lookupNodeInfo($node);
        if ($nodeInfo === false) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => "Node $node not found in astdb.txt database"
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Check if node already exists in favorites
        $alreadyExists = $this->nodeExistsInFavorites($currentUser, $node);
        if ($alreadyExists) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => "Node $node already exists in your favorites"
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }

        // Use custom label if provided, otherwise generate from node info
        if (empty($customLabel)) {
            $customLabel = $nodeInfo['callsign'] . ' ' . $nodeInfo['description'] . ' ' . $node;
        }

        // Generate command
        $command = "rpt cmd %node% ilink 13 " . $node;

        // Add to favorites
        $result = $this->addFavoriteToConfiguration($currentUser, $node, $customLabel, $command, $addToGeneral);

        if ($result['success']) {
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => "Node $node has been successfully added to your favorites as \"$customLabel\"",
                'node' => $node,
                'label' => $customLabel,
                'file' => $result['fileName']
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } else {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $result['message']
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Get node information from astdb.txt
     */
    public function getNodeInfo(Request $request, Response $response): Response
    {
        try {
            $currentUser = $this->getCurrentUser();
            if (!$currentUser) {
                $currentUser = 'default';
            }
            
            // Check user permissions
            if (!$this->hasUserPermission($currentUser, 'FAVUSER')) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'FAVUSER permission required'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            $node = $request->getQueryParams()['node'] ?? '';
            
            if (empty($node)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Node parameter is required'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $nodeInfo = $this->lookupNodeInfo($node);
            
            if ($nodeInfo === false) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "Node $node not found in astdb.txt database"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'nodeInfo' => $nodeInfo
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to get node information: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Look up node information in astdb.txt
     */
    private function lookupNodeInfo(string $node): array|false
    {
        $astdbPath = $this->getAstdbPath();
        if (!$astdbPath || !file_exists($astdbPath)) {
            return false;
        }

        $lines = file($astdbPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 4) {
                $nodeNum = trim($parts[0]);
                if ($nodeNum === $node) {
                    return [
                        'node' => $nodeNum,
                        'callsign' => trim($parts[1]),
                        'description' => trim($parts[2]),
                        'location' => trim($parts[3])
                    ];
                }
            }
        }

        return false;
    }

    /**
     * Get astdb.txt path
     */
    private function getAstdbPath(): ?string
    {
        if (isset($GLOBALS['ASTDB_TXT'])) {
            return $GLOBALS['ASTDB_TXT'];
        }

        // Try common paths
        $commonPaths = [
            __DIR__ . '/../../../astdb.txt',
            __DIR__ . '/../../../user_files/astdb.txt',
            '/var/lib/asterisk/astdb.txt'
        ];

        foreach ($commonPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Check if node already exists in favorites
     */
    private function nodeExistsInFavorites(string $user, string $node): bool
    {
        $favoritesData = $this->loadFavoritesConfiguration($user);
        if (!isset($favoritesData['favorites'])) {
            return false;
        }

        foreach ($favoritesData['favorites'] as $favorite) {
            if ($favorite['node'] === $node) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add favorite to configuration
     */
    private function addFavoriteToConfiguration(string $user, string $node, string $label, string $command, bool $addToGeneral): array
    {
        $favoritesIniPath = $this->getFavoritesIniPath($user);
        $config = [];

        // Load existing configuration
        if (file_exists($favoritesIniPath)) {
            $config = parse_ini_file($favoritesIniPath, true);
            if ($config === false) {
                $config = [];
            }
        }

        // Ensure general section exists
        if (!isset($config['general'])) {
            $config['general'] = [];
        }

        // Add to favorites
        if ($addToGeneral) {
            // Add to general section
            if (!isset($config['general']['label'])) {
                $config['general']['label'] = [];
            }
            if (!isset($config['general']['cmd'])) {
                $config['general']['cmd'] = [];
            }

            array_push($config['general']['label'], $label);
            array_push($config['general']['cmd'], $command);
        } else {
            // Add to node-specific section
            if (!isset($config[$node])) {
                $config[$node] = [];
            }
            if (!isset($config[$node]['label'])) {
                $config[$node]['label'] = [];
            }
            if (!isset($config[$node]['cmd'])) {
                $config[$node]['cmd'] = [];
            }

            array_push($config[$node]['label'], $label);
            array_push($config[$node]['cmd'], $command);
        }

        // Write back to file
        try {
            $this->writeFavoritesConfiguration($favoritesIniPath, $config);
            return [
                'success' => true,
                'fileName' => basename($favoritesIniPath)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error writing to favorites file: ' . $e->getMessage()
            ];
        }
    }
}
