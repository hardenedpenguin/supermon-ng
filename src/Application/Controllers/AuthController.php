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
        
        // Verify credentials
        if ($this->verifyCredentials($username, $password)) {
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Store user in session
            $_SESSION['user'] = $username;
            $_SESSION['authenticated'] = true;
            $_SESSION['login_time'] = time();
            
            $this->logger->info('User logged in successfully', ['username' => $username]);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => ['name' => $username],
                    'authenticated' => true,
                    'permissions' => $this->getUserPermissions($username),
                    'config_source' => $this->getUserIniFile($username)
                ],
                'timestamp' => date('c')
            ]));
        } else {
            $this->logger->warning('Failed login attempt', ['username' => $username]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Invalid username or password',
                'data' => null,
                'timestamp' => date('c')
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->logger->info('Logout attempt');
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Clear session data
        $_SESSION = [];
        
        // Destroy the session
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        
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

    public function refresh(Request $request, Response $response): Response
    {
        $this->logger->info('Token refresh attempt');
        
        // Check if user is still logged in
        $user = $this->getCurrentUser();
        
        if ($user) {
            // Regenerate session ID for security
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            session_regenerate_id(true);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Session refreshed',
                'data' => [
                    'authenticated' => true,
                    'user' => ['name' => $user],
                    'permissions' => $this->getUserPermissions($user),
                    'config_source' => $this->getUserIniFile($user)
                ],
                'timestamp' => date('c')
            ]));
        } else {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Session expired',
                'data' => [
                    'authenticated' => false
                ],
                'timestamp' => date('c')
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

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

    public function check(Request $request, Response $response): Response
    {
        $this->logger->info('Auth check request');
        
        // Debug session information
        $this->logger->info('Session debug', [
            'session_status' => session_status(),
            'session_id' => session_id(),
            'session_data' => $_SESSION ?? [],
            'cookie_header' => $request->getHeaderLine('Cookie'),
            'user_agent' => $request->getHeaderLine('User-Agent')
        ]);
        
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
     * Verify user credentials against .htpasswd file
     */
    private function verifyCredentials(string $username, string $password): bool
    {
        $htpasswdFile = 'user_files/.htpasswd';
        
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
        $authFile = 'user_files/authusers.inc';
        
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
            'ASTLKUSER', 'RSTATUSER', 'BUBLUSER', 'FAVUSER', 'CTRLUSER',
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
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Debug session data
        $this->logger->info('getCurrentUser debug', [
            'session_status' => session_status(),
            'session_id' => session_id(),
            'session_data' => $_SESSION ?? [],
            'has_user' => isset($_SESSION['user']),
            'has_authenticated' => isset($_SESSION['authenticated']),
            'user_value' => $_SESSION['user'] ?? 'not_set',
            'authenticated_value' => $_SESSION['authenticated'] ?? 'not_set',
            'login_time' => $_SESSION['login_time'] ?? 'not_set'
        ]);
        
        // Check if user is logged in via session
        if (isset($_SESSION['user']) && !empty($_SESSION['user']) && isset($_SESSION['authenticated']) && $_SESSION['authenticated']) {
            // Check if session is not too old (24 hours)
            if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) < 86400) {
                $this->logger->info('User authenticated via session', ['user' => $_SESSION['user']]);
                return $_SESSION['user'];
            } else {
                // Session expired, clear it
                $this->logger->info('Session expired, clearing');
                session_destroy();
                return null;
            }
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
        $authIniFile = 'user_files/authini.inc';
        
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
}
