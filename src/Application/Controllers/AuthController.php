<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use SupermonNg\Services\HtpasswdService;

class AuthController
{
    private LoggerInterface $logger;
    private HtpasswdService $htpasswd;

    public function __construct(LoggerInterface $logger, ?HtpasswdService $htpasswd = null)
    {
        $this->logger = $logger;
        $this->htpasswd = $htpasswd ?? new HtpasswdService($this->getUserFilesPath());
    }

    public function login(Request $request, Response $response): Response
    {
        $this->logger->info('Login attempt');
        
        // Get request body
        $body = $request->getParsedBody();
        $username = $body['username'] ?? '';
        $password = $body['password'] ?? '';
        
        // Validate input
        if (empty($username) || empty($password)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Username and password are required',
                'data' => null,
                'timestamp' => date('c')
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // Use session-based authentication
        return $this->sessionBasedLogin($username, $password, $response);
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->logger->info('Logout attempt');
        
        // Get current user from session
        $user = $this->getCurrentUser();
        
        if ($user) {
            $this->logger->info('User logged out successfully', ['username' => $user]);
        }
        
        // Clear session data
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Logout successful',
            'data' => [
                'authenticated' => false
            ],
            'timestamp' => date('c')
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }


    public function me(Request $request, Response $response): Response
    {
        $this->logger->info('User info request');
        
        // Check if user is actually logged in via session
        $user = $this->getCurrentUser();
        
        if ($user) {
            // User is logged in
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'user' => ['name' => $user],
                    'authenticated' => true,
                    'permissions' => $this->getUserPermissions($user),
                    'config_source' => $this->getUserIniFile($user)
                ],
                'timestamp' => date('c')
            ]));
        } else {
            // No user logged in - return unauthenticated data with default permissions
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'user' => null,
                    'authenticated' => false,
                    'permissions' => $this->getDefaultPermissions(),
                    'config_source' => 'allmon.ini'
                ],
                'timestamp' => date('c')
            ]));
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Return auth payload for bootstrap (same structure as /auth/me data).
     */
    public function getAuthData(): array
    {
        $user = $this->getCurrentUser();
        if ($user) {
            return [
                'user' => ['name' => $user],
                'authenticated' => true,
                'permissions' => $this->getUserPermissions($user),
                'config_source' => $this->getUserIniFile($user)
            ];
        }
        return [
            'user' => null,
            'authenticated' => false,
            'permissions' => $this->getDefaultPermissions(),
            'config_source' => 'allmon.ini'
        ];
    }

    public function check(Request $request, Response $response): Response
    {
        $this->logger->info('Auth check request');

        if (($_ENV['APP_ENV'] ?? 'production') !== 'production') {
            $this->logger->debug('Session debug', [
                'session_status' => session_status(),
                'session_id' => session_id(),
                'uri' => (string) $request->getUri()
            ]);
        }

        // Check if user is actually logged in via session
        $user = $this->getCurrentUser();
        
        if ($user) {
            // User is logged in
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'authenticated' => true,
                    'user' => ['name' => $user],
                    'permissions' => $this->getUserPermissions($user),
                    'config_source' => $this->getUserIniFile($user)
                ],
                'timestamp' => date('c')
            ]));
        } else {
            // No user logged in - return unauthenticated data with default permissions
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'authenticated' => false,
                    'user' => null,
                    'permissions' => $this->getDefaultPermissions(),
                    'config_source' => 'allmon.ini'
                ],
                'timestamp' => date('c')
            ]));
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get user files directory path (trailing slash).
     */
    private function getUserFilesPath(): string
    {
        $path = $_ENV['USER_FILES_PATH'] ?? dirname(__DIR__, 3) . '/user_files/';
        return rtrim($path, '/') . '/';
    }

    /**
     * Verify user credentials against .htpasswd file (delegated to the shared
     * HtpasswdService so login and per-request identity use one implementation).
     */
    private function verifyCredentials(string $username, string $password): bool
    {
        return $this->htpasswd->verify($username, $password);
    }
    
    /**
     * All permission keys returned by /auth/me (must stay in sync with API enforcement).
     *
     * @return list<string>
     */
    private static function permissionNames(): array
    {
        return [
            'PERMUSER', 'CONNECTUSER', 'DISCUSER', 'MONUSER', 'LMONUSER', 'DTMFUSER',
            'ASTLKUSER', 'RSTATUSER', 'BUBLUSER', 'DVSWITCHUSER', 'ANNOUNCEUSER',
            'ANNOUNCEGLOBALUSER', 'ANNOUNCESCHEDUSER', 'FAVUSER', 'CTRLUSER',
            'CFGEDUSER', 'ASTRELUSER', 'ASTSTRUSER', 'ASTSTPUSER', 'FSTRESUSER',
            'RBTUSER', 'UPDUSER', 'HWTOUSER', 'WIKIUSER', 'CSTATUSER',
            'ASTATUSER', 'EXNUSER', 'ACTNUSER', 'ALLNUSER',
            'DBTUSER', 'GPIOUSER', 'LLOGUSER', 'ASTLUSER', 'CLOGUSER', 'IRLPLOGUSER',
            'WLOGUSER', 'WERRUSER', 'BANUSER', 'SYSINFUSER', 'SUSBUSER', 'SMLOGUSER',
        ];
    }

    private function getUserPermissions(string $user): array
    {
        $authFile = $this->getUserFilesPath() . 'authusers.inc';
        $permissionNames = self::permissionNames();

        if (!file_exists($authFile)) {
            // If no auth file exists, grant all permissions
            return array_fill_keys($permissionNames, true);
        }

        /** @noinspection PhpUnusedLocalVariableInspection */
        include $authFile;

        $permissions = [];
        foreach ($permissionNames as $permission) {
            if (isset($$permission) && is_array($$permission)) {
                $permissions[$permission] = in_array($user, $$permission, true);
            } else {
                $permissions[$permission] = false;
            }
        }

        return $permissions;
    }

    /**
     * Get the currently logged in user from session
     */
    private function getCurrentUser(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
        if (!$isProduction) {
            $this->logger->debug('getCurrentUser', [
                'session_status' => session_status(),
                'has_user' => isset($_SESSION['user']),
                'has_authenticated' => isset($_SESSION['authenticated'])
            ]);
        }

        // Check if user is logged in via session
        if (isset($_SESSION['user']) && $_SESSION['user'] !== '' && isset($_SESSION['authenticated']) && $_SESSION['authenticated']) {
            if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) < 86400) {
                if (!$isProduction) {
                    $this->logger->debug('User authenticated via session', ['user' => $_SESSION['user']]);
                }
                return $_SESSION['user'];
            }
            if (!$isProduction) {
                $this->logger->debug('Session expired, clearing');
            }
            session_destroy();
            return null;
        }
        
        // HTTP Basic Auth: only trust PHP_AUTH_USER after re-verifying the
        // password, since PHP populates it from the client's Authorization
        // header regardless of whether the web server enforced auth.
        if (!empty($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            if ($this->htpasswd->verify((string) $_SERVER['PHP_AUTH_USER'], (string) $_SERVER['PHP_AUTH_PW'])) {
                return $_SERVER['PHP_AUTH_USER'];
            }
        }
        
        // REMOTE_USER is set by the web server post-authentication (not a
        // client-settable request header), so it is safe to trust.
        if (isset($_SERVER['REMOTE_USER']) && $_SERVER['REMOTE_USER'] !== '') {
            return $_SERVER['REMOTE_USER'];
        }
        
        return null;
    }

    /**
     * Default permissions when no user is logged in — all false so the UI matches API enforcement
     * (NodeController / ConfigController deny actions without authusers.inc membership).
     */
    private function getDefaultPermissions(): array
    {
        return array_fill_keys(self::permissionNames(), false);
    }

    /**
     * Get the user's specific INI file based on authini.inc mapping
     */
    private function getUserIniFile(string $username): string
    {
        $authIniFile = $this->getUserFilesPath() . 'authini.inc';

        if (!file_exists($authIniFile)) {
            return 'allmon.ini';
        }
        
        // Include the authini file to get the INI mapping
        include $authIniFile;
        
        // Check if user has a specific INI file mapped
        if (isset($ININAME[$username])) {
            return $ININAME[$username];
        }
        
        return 'allmon.ini';
    }
    
    /**
     * Fallback session-based login when JWT service is not available
     */
    private function sessionBasedLogin(string $username, string $password, Response $response): Response
    {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Use .htpasswd authentication (same as verifyCredentials method)
        if ($this->verifyCredentials($username, $password)) {
            // Rotate the session id on privilege change to prevent session fixation.
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }

            // Set session data
            $_SESSION['user'] = $username;
            $_SESSION['authenticated'] = true;
            $_SESSION['login_time'] = time();
            
            $this->logger->info('User logged in successfully (session-based)', ['username' => $username]);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $username,
                    'authenticated' => true,
                    'session_id' => session_id()
                ],
                'timestamp' => date('c')
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
        }
        
        $this->logger->warning('Login failed (session-based)', ['username' => $username]);
        
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Invalid username or password',
            'data' => null,
            'timestamp' => date('c')
        ]));
        
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }
}
