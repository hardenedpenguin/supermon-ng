<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class AuthController
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
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
     * Verify user credentials against .htpasswd file
     */
    private function verifyCredentials(string $username, string $password): bool
    {
        $htpasswdFile = $this->getUserFilesPath() . '.htpasswd';

        // Check if .htpasswd file exists
        if (!file_exists($htpasswdFile)) {
            $this->logger->warning('.htpasswd file not found');
            return false;
        }
        
        // Read .htpasswd file
        $lines = file($htpasswdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            $this->logger->error('Failed to read .htpasswd file');
            return false;
        }
        
        // Look for the user
        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue; // Skip malformed lines
            }
            
            $storedUsername = trim($parts[0]);
            $storedHash = trim($parts[1]);
            
            if ($storedUsername === $username) {
                // Verify password
                return $this->verifyPassword($password, $storedHash);
            }
        }
        
        return false; // User not found
    }
    
    /**
     * Verify password against stored hash
     * Supports multiple hash formats: MD5, SHA1, bcrypt, etc.
     */
    private function verifyPassword(string $password, string $storedHash): bool
    {
        // Check if it's a bcrypt hash (starts with $2y$)
        if (strpos($storedHash, '$2y$') === 0) {
            return password_verify($password, $storedHash);
        }
        
        // Check if it's an Apache MD5 hash (starts with $apr1$)
        if (strpos($storedHash, '$apr1$') === 0) {
            return $this->verifyApacheMd5($password, $storedHash);
        }
        
        // Check if it's a SHA1 hash (starts with {SHA})
        if (strpos($storedHash, '{SHA}') === 0) {
            $hash = base64_encode(sha1($password, true));
            return $storedHash === '{SHA}' . $hash;
        }
        
        // Check if it's a plain MD5 hash
        if (strlen($storedHash) === 32 && ctype_xdigit($storedHash)) {
            return md5($password) === $storedHash;
        }
        
        // Check if it's a plain text password (not recommended, but supported for legacy)
        if ($storedHash === $password) {
            $this->logger->warning('Plain text password detected for user');
            return true;
        }
        
        return false;
    }
    
    /**
     * Verify Apache MD5 password hash
     */
    private function verifyApacheMd5(string $password, string $storedHash): bool
    {
        // Extract salt from hash
        if (!preg_match('/^\$apr1\$([a-zA-Z0-9\/\.]{8})\$/', $storedHash, $matches)) {
            return false;
        }
        
        $salt = $matches[1];
        
        // Generate hash using Apache's MD5 algorithm
        $hash = $this->apacheMd5($password, $salt);
        
        return $storedHash === $hash;
    }
    
    /**
     * Generate Apache MD5 hash
     */
    private function apacheMd5(string $password, string $salt): string
    {
        $len = strlen($password);
        $text = $password . '$apr1$' . $salt;
        $bin = pack("H32", md5($password . $salt . $password));
        
        for ($i = $len; $i > 0; $i -= 16) {
            $text .= substr($bin, 0, min(16, $i));
        }
        
        for ($i = $len; $i > 0; $i >>= 1) {
            $text .= ($i & 1) ? chr(0) : $password[0];
        }
        
        $bin = pack("H32", md5($text));
        
        for ($i = 0; $i < 1000; $i++) {
            $new = ($i & 1) ? $password : $bin;
            if ($i % 3) $new .= $salt;
            if ($i % 7) $new .= $password;
            $new .= ($i & 1) ? $bin : $password;
            $bin = pack("H32", md5($new));
        }
        
        $tmp = '';
        for ($i = 0; $i < 5; $i++) {
            $k = $i + 6;
            $j = $i + 12;
            if ($j == 16) $j = 5;
            $tmp = $bin[$i] . $bin[$k] . $bin[$j] . $tmp;
        }
        
        $tmp = chr(0) . chr(0) . $bin[11] . $tmp;
        $tmp = strtr(strrev(substr(base64_encode($tmp), 2)), "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/", "./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz");
        
        return '$apr1$' . $salt . '$' . $tmp;
    }

    private function getUserPermissions(string $user): array
    {
        $authFile = $this->getUserFilesPath() . 'authusers.inc';

        if (!file_exists($authFile)) {
            // If no auth file exists, grant all permissions
            return [
                'PERMUSER' => true,
                'CONNECTUSER' => true,
                'DISCUSER' => true,
                'MONUSER' => true,
                'LMONUSER' => true,
                'DTMFUSER' => true,
                'ASTLKUSER' => true,
                'RSTATUSER' => true,
                'BUBLUSER' => true,
                'DVSWITCHUSER' => true,
                'FAVUSER' => true,
                'CTRLUSER' => true,
                'CFGEDUSER' => true,
                'ASTRELUSER' => true,
                'ASTSTRUSER' => true,
                'ASTSTPUSER' => true,
                'FSTRESUSER' => true,
                'RBTUSER' => true,
                'UPDUSER' => true,
                'HWTOUSER' => true,
                'WIKIUSER' => true,
                'CSTATUSER' => true,
                'ASTATUSER' => true,
                'EXNUSER' => true,
                'ACTNUSER' => true,
                'ALLNUSER' => true,
                'DBTUSER' => true,
                'GPIOUSER' => true,
                'LLOGUSER' => true,
                'ASTLUSER' => true,
                'CLOGUSER' => true,
                'IRLPLOGUSER' => true,
                'WLOGUSER' => true,
                'WERRUSER' => true,
                'BANUSER' => true,
                'SYSINFUSER' => true,
                'SUSBUSER' => true
            ];
        }

        // Include the auth file to get permission arrays
        include $authFile;
        
        $permissions = [];
        $permissionNames = [
            'PERMUSER', 'CONNECTUSER', 'DISCUSER', 'MONUSER', 'LMONUSER', 'DTMFUSER',
            'ASTLKUSER', 'RSTATUSER', 'BUBLUSER', 'DVSWITCHUSER', 'FAVUSER', 'CTRLUSER',
            'CFGEDUSER', 'ASTRELUSER', 'ASTSTRUSER', 'ASTSTPUSER', 'FSTRESUSER',
            'RBTUSER', 'UPDUSER', 'HWTOUSER', 'WIKIUSER', 'CSTATUSER',
            'ASTATUSER', 'EXNUSER', 'ACTNUSER', 'ALLNUSER',
            'DBTUSER', 'GPIOUSER', 'LLOGUSER', 'ASTLUSER', 'CLOGUSER', 'IRLPLOGUSER',
            'WLOGUSER', 'WERRUSER', 'BANUSER', 'SYSINFUSER', 'SUSBUSER'
        ];
        
        foreach ($permissionNames as $permission) {
            $permissions[$permission] = $this->hasPermission($user, $permission, $authFile);
        }
        
        return $permissions;
    }
    
    /**
     * Check if user has a specific permission
     */
    private function hasPermission(string $user, string $permission, string $authFile): bool
    {
        // Include the auth file to get permission arrays
        include $authFile;
        
        // Check if the permission array exists and user is in it
        if (isset($$permission) && is_array($$permission)) {
            return in_array($user, $$permission, true);
        }
        
        return false;
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
        
        // Check if user is logged in via HTTP Basic Auth
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            return $_SERVER['PHP_AUTH_USER'];
        }
        
        // Check if user is logged in via .htaccess/.htpasswd
        if (isset($_SERVER['REMOTE_USER'])) {
            return $_SERVER['REMOTE_USER'];
        }
        
        return null;
    }

    /**
     * Get default permissions when no user is logged in
     * These permissions allow basic functionality using allmon.ini configuration
     */
    private function getDefaultPermissions(): array
    {
        return [
            'CONNECTUSER' => true,
            'DISCUSER' => true,
            'MONUSER' => true,
            'LMONUSER' => true,
            'DTMFUSER' => false, // Disable sensitive features for anonymous users
            'ASTLKUSER' => true,
            'RSTATUSER' => true,
            'BUBLUSER' => true,
            'DVSWITCHUSER' => false, // Disable DVSwitch for anonymous users (requires configuration)
            'FAVUSER' => true,
            'CTRLUSER' => false, // Disable admin features for anonymous users
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
            'SYSINFUSER' => false
        ];
    }

    /**
     * Get no permissions when user is not authenticated
     * This disables all functionality for unauthenticated users
     */
    private function getNoPermissions(): array
    {
        return [
            'CONNECTUSER' => false,
            'DISCUSER' => false,
            'MONUSER' => false,
            'LMONUSER' => false,
            'DTMFUSER' => false,
            'ASTLKUSER' => false,
            'RSTATUSER' => false,
            'BUBLUSER' => false,
            'FAVUSER' => false,
            'CTRLUSER' => false,
            'CFGEDUSER' => false,
            'ASTRELUSER' => false,
            'ASTSTRUSER' => false,
            'ASTSTPUSER' => false,
            'FSTRESUSER' => false,
            'RBTUSER' => false,
            'UPDUSER' => false,
            'HWTOUSER' => false,
            'WIKIUSER' => false,
            'CSTATUSER' => false,
            'ASTATUSER' => false,
            'EXNUSER' => false,
            'ACTNUSER' => false,
            'ALLNUSER' => false,
            'DBTUSER' => false,
            'GPIOUSER' => false,
            'LLOGUSER' => false,
            'ASTLUSER' => false,
            'IRLPUSER' => false,
            'WLOGUSER' => false,
            'WERRUSER' => false,
            'BANUSER' => false,
            'SYSINFUSER' => false
        ];
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
