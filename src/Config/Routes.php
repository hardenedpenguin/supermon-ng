<?php

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use SupermonNg\Application\Controllers\NodeController;
use SupermonNg\Application\Controllers\AuthController;
use SupermonNg\Application\Controllers\SystemController;
use SupermonNg\Application\Controllers\DatabaseController;
use SupermonNg\Application\Controllers\ConfigController;
use SupermonNg\Application\Controllers\NodeStatusController;
use SupermonNg\Application\Controllers\AdminController;
use SupermonNg\Application\Controllers\AstdbController;
use SupermonNg\Application\Controllers\PerformanceController;
use SupermonNg\Application\Controllers\DatabasePerformanceController;
use SupermonNg\Application\Controllers\HttpPerformanceController;
use SupermonNg\Application\Controllers\SessionPerformanceController;
use SupermonNg\Application\Controllers\FileIOPerformanceController;
use SupermonNg\Application\Middleware\ApiAuthMiddleware;
use SupermonNg\Application\Middleware\AdminAuthMiddleware;

/** @var App $app */
global $app;

// Health check endpoint
$app->get('/health', function ($request, $response) {
    $response->getBody()->write(json_encode([
        'status' => 'healthy',
        'timestamp' => date('c'),
        'version' => $_ENV['API_VERSION'] ?? '1.0.0'
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Simple test endpoint to verify basic functionality
$app->get('/api/test', function ($request, $response) {
    $response->getBody()->write(json_encode([
        'success' => true,
        'message' => 'API is working',
        'timestamp' => date('c'),
        'php_version' => PHP_VERSION,
        'session_status' => session_status()
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// CSRF token endpoint - MUST be before middleware that might interfere
$app->get('/api/csrf-token', function ($request, $response) {
    try {
        // Session should already be started by middleware, but ensure it's active
        if (session_status() === PHP_SESSION_NONE) {
            // Use same session configuration as middleware
            session_name('supermon61');
            
            // Detect HTTPS for secure cookies
            $isSecure = false;
            $serverParams = $request->getServerParams();
            if (($serverParams['HTTPS'] ?? '') === 'on' ||
                ($serverParams['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ||
                ($serverParams['HTTP_X_FORWARDED_SSL'] ?? '') === 'on' ||
                ($serverParams['SERVER_PORT'] ?? '') == '443') {
                $isSecure = true;
            }
            
            session_set_cookie_params([
                'lifetime' => 86400,
                'path' => '/supermon-ng',
                'domain' => '',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            
            session_start();
        }
        
        // Ensure session is active
        if (session_status() !== PHP_SESSION_ACTIVE) {
            error_log('CSRF token endpoint: Session not active after start attempt');
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Session initialization failed',
                'csrf_token' => ''
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
        
        // Generate CSRF token if it doesn't exist
        if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        $token = $_SESSION['csrf_token'] ?? '';
        
        if (empty($token)) {
            error_log('CSRF token endpoint: Generated token is empty');
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to generate CSRF token',
                'csrf_token' => ''
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'csrf_token' => $token
        ]));
        return $response->withHeader('Content-Type', 'application/json');
        
    } catch (\Exception $e) {
        error_log('CSRF token endpoint error: ' . $e->getMessage());
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage(),
            'csrf_token' => ''
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// API v1 routes
$app->group('/api/v1', function (RouteCollectorProxy $group) {
    // Auth routes
    $group->group('/auth', function (RouteCollectorProxy $group) {
        $group->post('/login', [AuthController::class, 'login']);
        $group->post('/logout', [AuthController::class, 'logout']);
        $group->get('/me', [AuthController::class, 'me']);
    });

    // Node routes (protected by API auth)
    $group->group('/nodes', function (RouteCollectorProxy $group) {
        $group->get('', [NodeController::class, 'list']);
        $group->get('/available', [NodeController::class, 'available']);
        $group->get('/{id}', [NodeController::class, 'get']);
        $group->get('/{id}/status', [NodeController::class, 'status']);
        $group->post('/{id}/connect', [NodeController::class, 'connect']);
        $group->post('/{id}/disconnect', [NodeController::class, 'disconnect']);
        $group->post('/{id}/monitor', [NodeController::class, 'monitor']);
        $group->post('/{id}/local-monitor', [NodeController::class, 'localMonitor']);
        $group->post('/{id}/dtmf', [NodeController::class, 'dtmf']);
    })->add(ApiAuthMiddleware::class);

    // System routes
    $group->group('/system', function (RouteCollectorProxy $group) {
        $group->get('/info', [SystemController::class, 'info']);
        $group->get('/stats', [SystemController::class, 'stats']);
        $group->get('/logs', [SystemController::class, 'getLogs']);
        $group->get('/client-ip', [SystemController::class, 'getClientIP']);
    });

    // Database routes (removed - using non-versioned routes in /api group instead)

    // ASTDB routes (Phase 7 optimization)
    $group->group('/astdb', function (RouteCollectorProxy $group) {
        $group->get('/stats', [AstdbController::class, 'getStats']);
        $group->get('/health', [AstdbController::class, 'health']);
        $group->get('/search', [AstdbController::class, 'search']);
        $group->get('/nodes', [AstdbController::class, 'getNodes']);
        $group->get('/node/{id}', [AstdbController::class, 'getNode']);
        $group->post('/clear-cache', [AstdbController::class, 'clearCache']);
    });
    
    // Performance monitoring routes (Phase 3 optimization)
    $group->group('/performance', function (RouteCollectorProxy $group) {
        $group->get('/metrics', [PerformanceController::class, 'getMetrics']);
        $group->get('/config-stats', [PerformanceController::class, 'getConfigStats']);
        $group->get('/file-stats', [PerformanceController::class, 'getFileStats']);
        $group->post('/clear-caches', [PerformanceController::class, 'clearCaches']);
        $group->post('/cleanup-cache', [PerformanceController::class, 'cleanupCache']);
    });
    
    // Database performance monitoring routes (Phase 6 optimization)
    $group->group('/db-performance', function (RouteCollectorProxy $group) {
        $group->get('/metrics', [DatabasePerformanceController::class, 'getMetrics']);
        $group->get('/database-stats', [DatabasePerformanceController::class, 'getDatabaseStats']);
        $group->get('/cache-stats', [DatabasePerformanceController::class, 'getCacheStats']);
        $group->post('/clear-query-cache', [DatabasePerformanceController::class, 'clearQueryCache']);
        $group->post('/clear-all-caches', [DatabasePerformanceController::class, 'clearAllCaches']);
        $group->post('/optimize-tables', [DatabasePerformanceController::class, 'optimizeTables']);
        $group->post('/cleanup-memory-cache', [DatabasePerformanceController::class, 'cleanupMemoryCache']);
    });
    
    // HTTP performance monitoring routes (Phase 7 optimization)
    $group->group('/http-performance', function (RouteCollectorProxy $group) {
        $group->get('/metrics', [HttpPerformanceController::class, 'getMetrics']);
        $group->get('/http-stats', [HttpPerformanceController::class, 'getHttpStats']);
        $group->get('/middleware-stats', [HttpPerformanceController::class, 'getMiddlewareStats']);
        $group->get('/slow-middleware', [HttpPerformanceController::class, 'getSlowMiddleware']);
        $group->get('/middleware-optimization', [HttpPerformanceController::class, 'getMiddlewareOptimization']);
        $group->post('/reset-http-stats', [HttpPerformanceController::class, 'resetHttpStats']);
        $group->post('/reset-middleware-stats', [HttpPerformanceController::class, 'resetMiddlewareStats']);
        $group->get('/test-optimization', [HttpPerformanceController::class, 'testOptimization']);
    });
    
    // Session performance monitoring routes (Phase 8 optimization)
    $group->group('/session-performance', function (RouteCollectorProxy $group) {
        $group->get('/metrics', [SessionPerformanceController::class, 'getMetrics']);
        $group->get('/session-stats', [SessionPerformanceController::class, 'getSessionStats']);
        $group->get('/auth-stats', [SessionPerformanceController::class, 'getAuthStats']);
        $group->get('/test-authentication', [SessionPerformanceController::class, 'testAuthentication']);
        $group->post('/cleanup-expired-sessions', [SessionPerformanceController::class, 'cleanupExpiredSessions']);
        $group->post('/clear-auth-cache', [SessionPerformanceController::class, 'clearAuthCache']);
        $group->post('/reset-session-stats', [SessionPerformanceController::class, 'resetSessionStats']);
        $group->post('/reset-auth-stats', [SessionPerformanceController::class, 'resetAuthStats']);
    });
    
    // File I/O performance monitoring routes (Phase 9 optimization)
    $group->group('/fileio-performance', function (RouteCollectorProxy $group) {
        $group->get('/metrics', [FileIOPerformanceController::class, 'getMetrics']);
        $group->get('/external-process-stats', [FileIOPerformanceController::class, 'getExternalProcessStats']);
        $group->get('/file-io-stats', [FileIOPerformanceController::class, 'getFileIOStats']);
        $group->post('/clear-irlp-cache', [FileIOPerformanceController::class, 'clearIrlpCache']);
        $group->post('/clear-file-io-caches', [FileIOPerformanceController::class, 'clearFileIOCaches']);
        $group->post('/reset-external-process-stats', [FileIOPerformanceController::class, 'resetExternalProcessStats']);
        $group->post('/reset-file-io-stats', [FileIOPerformanceController::class, 'resetFileIOStats']);
        $group->get('/test-irlp-lookup', [FileIOPerformanceController::class, 'testIrlpLookup']);
    });

    // Config routes
    $group->group('/config', function (RouteCollectorProxy $group) {
        $group->get('', [ConfigController::class, 'list']);
        $group->get('/{key}', [ConfigController::class, 'get']);
        $group->put('/{key}', [ConfigController::class, 'update']);
    });

    // Admin routes (protected by admin auth)
    $group->group('/admin', function (RouteCollectorProxy $group) {
        $group->get('/users', [AdminController::class, 'listUsers']);
        $group->post('/users', [AdminController::class, 'createUser']);
        $group->put('/users/{id}', [AdminController::class, 'updateUser']);
        $group->delete('/users/{id}', [AdminController::class, 'deleteUser']);
        $group->post('/backup', [AdminController::class, 'backup']);
        $group->post('/restore', [AdminController::class, 'restore']);
        $group->post('/clear-cache', [AdminController::class, 'clearCache']);
    })->add(AdminAuthMiddleware::class);
});

// API routes without version prefix (for frontend compatibility)
$app->group('/api', function (RouteCollectorProxy $group) {
    // Auth routes
    $group->group('/auth', function (RouteCollectorProxy $group) {
        $group->post('/login', [AuthController::class, 'login']);
        $group->post('/logout', [AuthController::class, 'logout']);
        $group->get('/me', [AuthController::class, 'me']);
        $group->get('/check', [AuthController::class, 'check']);
    });

    // Node routes
    $group->group('/nodes', function (RouteCollectorProxy $group) {
        $group->get('', [NodeController::class, 'list']);
        $group->get('/available', [NodeController::class, 'available']);
        $group->get('/ami/status', [NodeController::class, 'getAmiStatus']);
        
        // Voter Route (must come before variable routes)
        $group->get('/voter/status', [NodeController::class, 'voterStatus']);
        
        $group->get('/{id}', [NodeController::class, 'get']);
        $group->get('/{id}/status', [NodeController::class, 'status']);
        $group->post('/connect', [NodeController::class, 'connect']);
        $group->post('/disconnect', [NodeController::class, 'disconnect']);
        $group->post('/monitor', [NodeController::class, 'monitor']);
        $group->post('/local-monitor', [NodeController::class, 'localMonitor']);
        $group->post('/dtmf', [NodeController::class, 'dtmf']);
        $group->post('/rptstats', [NodeController::class, 'rptstats']);
        
               // CPU Stats Route
       $group->post('/cpustats', [NodeController::class, 'cpustats']);
       
       // Database Route
       $group->post('/database', [NodeController::class, 'database']);
       
               // ExtNodes Route
        $group->post('/extnodes', [NodeController::class, 'extnodes']);
        
        // FastRestart Route
        $group->post('/fastrestart', [NodeController::class, 'fastrestart']);
        
        // IRLP Log Route
        $group->post('/irlplog', [NodeController::class, 'irlplog']);
        
        // Linux Log Route
        $group->post('/linuxlog', [NodeController::class, 'linuxlog']);
        
        // Ban/Allow Routes
        $group->post('/banallow', [NodeController::class, 'banallow']);
        $group->post('/banallow/action', [NodeController::class, 'banallowAction']);
        
        // Pi GPIO Routes
        $group->post('/pigpio', [NodeController::class, 'pigpio']);
        $group->post('/pigpio/action', [NodeController::class, 'pigpioAction']);
        
        // Reboot Route
        $group->post('/reboot', [NodeController::class, 'reboot']);
        
        // SMLog Route
        $group->post('/smlog', [NodeController::class, 'smlog']);
        
        // Stats Route
        $group->post('/stats', [NodeController::class, 'stats']);
        
        // Web Access Log Route
        $group->post('/webacclog', [NodeController::class, 'webacclog']);
        
        // Web Error Log Route
        $group->post('/weberrlog', [NodeController::class, 'weberrlog']);
        
        // Lsnod Routes
        $group->get('/{id}/lsnodes', [NodeController::class, 'lsnodes']);
        $group->get('/{id}/lsnodes/web', [NodeController::class, 'lsnodesWeb']);
    });

    // Config routes
    $group->group('/config', function (RouteCollectorProxy $group) {
        $group->get('/nodes', [ConfigController::class, 'getNodes']);
        $group->get('/user/preferences', [ConfigController::class, 'getUserPreferences']);
        $group->put('/user/preferences', [ConfigController::class, 'updateUserPreferences']);
        $group->get('/system-info', [ConfigController::class, 'getSystemInfo']);
        $group->get('/menu', [ConfigController::class, 'getMenu']);
        $group->get('/header-background', [ConfigController::class, 'getHeaderBackground']);
        $group->get('/display', [ConfigController::class, 'getDisplayConfig']);
        $group->put('/display', [ConfigController::class, 'updateDisplayConfig']);
        $group->get('/node-info', [ConfigController::class, 'getNodeInfo']);
        $group->post('/add-favorite', [ConfigController::class, 'addFavorite']);
        $group->get('/favorites', [ConfigController::class, 'getFavorites']);
        $group->post('/favorites/add', [ConfigController::class, 'addFavorite']);
        $group->delete('/favorites', [ConfigController::class, 'deleteFavorite']);
        $group->post('/favorites/execute', [ConfigController::class, 'executeFavorite']);
        $group->post('/asterisk/reload', [ConfigController::class, 'executeAsteriskReload']);
        $group->post('/asterisk/control', [ConfigController::class, 'executeAsteriskControl']);
        $group->get('/astlog', [ConfigController::class, 'getAstLog']);
        $group->post('/astlookup', [ConfigController::class, 'performAstLookup']);
        $group->post('/bubblechart', [ConfigController::class, 'getBubbleChart']);
                    $group->get('/controlpanel', [ConfigController::class, 'getControlPanel']);
            $group->post('/controlpanel/execute', [ConfigController::class, 'executeControlPanelCommand']);
            $group->get('/configeditor/files', [ConfigController::class, 'getConfigEditorFiles']);
            $group->post('/configeditor/content', [ConfigController::class, 'getConfigFileContent']);
            $group->post('/configeditor/save', [ConfigController::class, 'saveConfigFile']);
    });

    // Database routes
    $group->group('/database', function (RouteCollectorProxy $group) {
        $group->get('/status', [DatabaseController::class, 'status']);
        $group->post('/generate', [DatabaseController::class, 'generate']);
        $group->post('/auto-update', [DatabaseController::class, 'autoUpdate']);
        $group->post('/force-update', [DatabaseController::class, 'forceUpdate']);
        $group->get('/search', [DatabaseController::class, 'search']);
        $group->get('/{id}', [DatabaseController::class, 'get']);
    });

    // ASTDB routes (Phase 7 optimization)
    $group->group('/astdb', function (RouteCollectorProxy $group) {
        $group->get('/stats', [AstdbController::class, 'getStats']);
        $group->get('/health', [AstdbController::class, 'health']);
        $group->get('/search', [AstdbController::class, 'search']);
        $group->get('/nodes', [AstdbController::class, 'getNodes']);
        $group->get('/node/{id}', [AstdbController::class, 'getNode']);
        $group->post('/clear-cache', [AstdbController::class, 'clearCache']);
    });
    
    // Performance monitoring routes (Phase 3 optimization)
    $group->group('/performance', function (RouteCollectorProxy $group) {
        $group->get('/metrics', [PerformanceController::class, 'getMetrics']);
        $group->get('/config-stats', [PerformanceController::class, 'getConfigStats']);
        $group->get('/file-stats', [PerformanceController::class, 'getFileStats']);
        $group->post('/clear-caches', [PerformanceController::class, 'clearCaches']);
        $group->post('/cleanup-cache', [PerformanceController::class, 'cleanupCache']);
    });
    
    // Database performance monitoring routes (Phase 6 optimization)
    $group->group('/db-performance', function (RouteCollectorProxy $group) {
        $group->get('/metrics', [DatabasePerformanceController::class, 'getMetrics']);
        $group->get('/database-stats', [DatabasePerformanceController::class, 'getDatabaseStats']);
        $group->get('/cache-stats', [DatabasePerformanceController::class, 'getCacheStats']);
        $group->post('/clear-query-cache', [DatabasePerformanceController::class, 'clearQueryCache']);
        $group->post('/clear-all-caches', [DatabasePerformanceController::class, 'clearAllCaches']);
        $group->post('/optimize-tables', [DatabasePerformanceController::class, 'optimizeTables']);
        $group->post('/cleanup-memory-cache', [DatabasePerformanceController::class, 'cleanupMemoryCache']);
    });
    
    // HTTP performance monitoring routes (Phase 7 optimization)
    $group->group('/http-performance', function (RouteCollectorProxy $group) {
        $group->get('/metrics', [HttpPerformanceController::class, 'getMetrics']);
        $group->get('/http-stats', [HttpPerformanceController::class, 'getHttpStats']);
        $group->get('/middleware-stats', [HttpPerformanceController::class, 'getMiddlewareStats']);
        $group->get('/slow-middleware', [HttpPerformanceController::class, 'getSlowMiddleware']);
        $group->get('/middleware-optimization', [HttpPerformanceController::class, 'getMiddlewareOptimization']);
        $group->post('/reset-http-stats', [HttpPerformanceController::class, 'resetHttpStats']);
        $group->post('/reset-middleware-stats', [HttpPerformanceController::class, 'resetMiddlewareStats']);
        $group->get('/test-optimization', [HttpPerformanceController::class, 'testOptimization']);
    });
    
    // Session performance monitoring routes (Phase 8 optimization)
    $group->group('/session-performance', function (RouteCollectorProxy $group) {
        $group->get('/metrics', [SessionPerformanceController::class, 'getMetrics']);
        $group->get('/session-stats', [SessionPerformanceController::class, 'getSessionStats']);
        $group->get('/auth-stats', [SessionPerformanceController::class, 'getAuthStats']);
        $group->get('/test-authentication', [SessionPerformanceController::class, 'testAuthentication']);
        $group->post('/cleanup-expired-sessions', [SessionPerformanceController::class, 'cleanupExpiredSessions']);
        $group->post('/clear-auth-cache', [SessionPerformanceController::class, 'clearAuthCache']);
        $group->post('/reset-session-stats', [SessionPerformanceController::class, 'resetSessionStats']);
        $group->post('/reset-auth-stats', [SessionPerformanceController::class, 'resetAuthStats']);
    });
    
    // File I/O performance monitoring routes (Phase 9 optimization)
    $group->group('/fileio-performance', function (RouteCollectorProxy $group) {
        $group->get('/metrics', [FileIOPerformanceController::class, 'getMetrics']);
        $group->get('/external-process-stats', [FileIOPerformanceController::class, 'getExternalProcessStats']);
        $group->get('/file-io-stats', [FileIOPerformanceController::class, 'getFileIOStats']);
        $group->post('/clear-irlp-cache', [FileIOPerformanceController::class, 'clearIrlpCache']);
        $group->post('/clear-file-io-caches', [FileIOPerformanceController::class, 'clearFileIOCaches']);
        $group->post('/reset-external-process-stats', [FileIOPerformanceController::class, 'resetExternalProcessStats']);
        $group->post('/reset-file-io-stats', [FileIOPerformanceController::class, 'resetFileIOStats']);
        $group->get('/test-irlp-lookup', [FileIOPerformanceController::class, 'testIrlpLookup']);
    });

    // Node Status routes
    $group->group('/node-status', function (RouteCollectorProxy $group) {
        $group->get('/config', [NodeStatusController::class, 'getConfig']);
        $group->put('/config', [NodeStatusController::class, 'updateConfig']);
        $group->post('/trigger-update', [NodeStatusController::class, 'triggerUpdate']);
        $group->get('/service-status', [NodeStatusController::class, 'getServiceStatus']);
    });

    // System routes
    $group->group('/system', function (RouteCollectorProxy $group) {
        $group->get('/info', [SystemController::class, 'info']);
        $group->get('/stats', [SystemController::class, 'stats']);
        $group->post('/reload', [SystemController::class, 'reload']);
        $group->post('/start', [SystemController::class, 'start']);
        $group->post('/stop', [SystemController::class, 'stop']);
        $group->post('/fast-restart', [SystemController::class, 'fastRestart']);
        $group->post('/reboot', [SystemController::class, 'reboot']);
    });
    
    
});

// Legacy routes (for backward compatibility)
$app->group('/legacy', function (RouteCollectorProxy $group) {
    $group->get('/nodes', function ($request, $response) {
        $response->getBody()->write(json_encode([
            'message' => 'Legacy endpoint - use /api/v1/nodes instead',
            'timestamp' => date('c')
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });
});
