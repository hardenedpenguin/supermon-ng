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
            $fp = SimpleAmiClient::connect($amiHost);
            if ($fp === FALSE) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "Could not connect to Asterisk Manager at $amiHost for node $localNode"
                ]));
                return $response->withStatus(500);
            }

            // Login to AMI
            if (SimpleAmiClient::login($fp, $amiUser, $amiPass) === FALSE) {
                SimpleAmiClient::logoff($fp);
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "Could not login to Asterisk Manager for node $localNode with user $amiUser"
                ]));
                return $response->withStatus(500);
            }

            $results = [];
            $results[] = "Reloading configurations for node - $localNode:";

            // Execute reload commands
            if (SimpleAmiClient::command($fp, "rpt reload") !== false) {
                $results[] = "- rpt.conf reloaded successfully.";
            } else {
                $results[] = "- FAILED to reload rpt.conf.";
            }
            sleep(1);

            if (SimpleAmiClient::command($fp, "iax2 reload") !== false) {
                $results[] = "- iax.conf reloaded successfully.";
            } else {
                $results[] = "- FAILED to reload iax.conf.";
            }
            sleep(1);

            if (SimpleAmiClient::command($fp, "extensions reload") !== false) {
                $results[] = "- extensions.conf reloaded successfully.";
            } else {
                $results[] = "- FAILED to reload extensions.conf.";
            }

            SimpleAmiClient::logoff($fp);

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
        // This is a simplified permission check
        // In a real implementation, you would check against the user's actual permissions
        // For now, we'll assume the user has the permission if they're authenticated
        return !empty($user);
    }

    /**
     * Get user's INI file path
     */
    private function getUserIniFile(string $user): string
    {
        // Use the same logic as the original get_ini_name function
        if ($user === 'default' || empty($user)) {
            return 'user_files/allmon.ini';
        }
        return "user_files/{$user}.ini";
    }

}
