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
use SupermonNg\Application\Controllers\DvswitchController;
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
        if (session_status() === PHP_SESSION_NONE) {
            session_name('supermon61');
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
        if (session_status() !== PHP_SESSION_ACTIVE) {
            error_log('CSRF token endpoint: Session not active after start attempt');
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Session initialization failed',
                'csrf_token' => ''
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
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

/**
 * Register shared API route groups (auth base, astdb, performance, admin).
 * Used by both /api and /api/v1 to avoid duplication.
 */
$registerSharedApiRoutes = function (RouteCollectorProxy $group): void {
    $group->group('/auth', function (RouteCollectorProxy $g): void {
        $g->post('/login', [AuthController::class, 'login']);
        $g->post('/logout', [AuthController::class, 'logout']);
        $g->get('/me', [AuthController::class, 'me']);
    });

    $group->group('/astdb', function (RouteCollectorProxy $g): void {
        $g->get('/stats', [AstdbController::class, 'getStats']);
        $g->get('/health', [AstdbController::class, 'health']);
        $g->get('/search', [AstdbController::class, 'search']);
        $g->get('/nodes', [AstdbController::class, 'getNodes']);
        $g->get('/node/{id}', [AstdbController::class, 'getNode']);
        $g->post('/clear-cache', [AstdbController::class, 'clearCache']);
    });

    $group->group('/performance', function (RouteCollectorProxy $g): void {
        $g->get('/metrics', [PerformanceController::class, 'getMetrics']);
        $g->get('/config-stats', [PerformanceController::class, 'getConfigStats']);
        $g->get('/file-stats', [PerformanceController::class, 'getFileStats']);
        $g->post('/clear-caches', [PerformanceController::class, 'clearCaches']);
        $g->post('/cleanup-cache', [PerformanceController::class, 'cleanupCache']);
    });

    $group->group('/db-performance', function (RouteCollectorProxy $g): void {
        $g->get('/metrics', [DatabasePerformanceController::class, 'getMetrics']);
        $g->get('/database-stats', [DatabasePerformanceController::class, 'getDatabaseStats']);
        $g->get('/cache-stats', [DatabasePerformanceController::class, 'getCacheStats']);
        $g->post('/clear-query-cache', [DatabasePerformanceController::class, 'clearQueryCache']);
        $g->post('/clear-all-caches', [DatabasePerformanceController::class, 'clearAllCaches']);
        $g->post('/optimize-tables', [DatabasePerformanceController::class, 'optimizeTables']);
        $g->post('/cleanup-memory-cache', [DatabasePerformanceController::class, 'cleanupMemoryCache']);
    });

    $group->group('/http-performance', function (RouteCollectorProxy $g): void {
        $g->get('/metrics', [HttpPerformanceController::class, 'getMetrics']);
        $g->get('/http-stats', [HttpPerformanceController::class, 'getHttpStats']);
        $g->get('/middleware-stats', [HttpPerformanceController::class, 'getMiddlewareStats']);
        $g->get('/slow-middleware', [HttpPerformanceController::class, 'getSlowMiddleware']);
        $g->get('/middleware-optimization', [HttpPerformanceController::class, 'getMiddlewareOptimization']);
        $g->post('/reset-http-stats', [HttpPerformanceController::class, 'resetHttpStats']);
        $g->post('/reset-middleware-stats', [HttpPerformanceController::class, 'resetMiddlewareStats']);
        $g->get('/test-optimization', [HttpPerformanceController::class, 'testOptimization']);
    });

    $group->group('/session-performance', function (RouteCollectorProxy $g): void {
        $g->get('/metrics', [SessionPerformanceController::class, 'getMetrics']);
        $g->get('/session-stats', [SessionPerformanceController::class, 'getSessionStats']);
        $g->get('/auth-stats', [SessionPerformanceController::class, 'getAuthStats']);
        $g->get('/test-authentication', [SessionPerformanceController::class, 'testAuthentication']);
        $g->post('/cleanup-expired-sessions', [SessionPerformanceController::class, 'cleanupExpiredSessions']);
        $g->post('/clear-auth-cache', [SessionPerformanceController::class, 'clearAuthCache']);
        $g->post('/reset-session-stats', [SessionPerformanceController::class, 'resetSessionStats']);
        $g->post('/reset-auth-stats', [SessionPerformanceController::class, 'resetAuthStats']);
    });

    $group->group('/fileio-performance', function (RouteCollectorProxy $g): void {
        $g->get('/metrics', [FileIOPerformanceController::class, 'getMetrics']);
        $g->get('/external-process-stats', [FileIOPerformanceController::class, 'getExternalProcessStats']);
        $g->get('/file-io-stats', [FileIOPerformanceController::class, 'getFileIOStats']);
        $g->post('/clear-irlp-cache', [FileIOPerformanceController::class, 'clearIrlpCache']);
        $g->post('/clear-file-io-caches', [FileIOPerformanceController::class, 'clearFileIOCaches']);
        $g->post('/reset-external-process-stats', [FileIOPerformanceController::class, 'resetExternalProcessStats']);
        $g->post('/reset-file-io-stats', [FileIOPerformanceController::class, 'resetFileIOStats']);
        $g->get('/test-irlp-lookup', [FileIOPerformanceController::class, 'testIrlpLookup']);
    });

    $group->group('/admin', function (RouteCollectorProxy $g): void {
        $g->get('/users', [AdminController::class, 'listUsers']);
        $g->post('/users', [AdminController::class, 'createUser']);
        $g->put('/users/{id}', [AdminController::class, 'updateUser']);
        $g->delete('/users/{id}', [AdminController::class, 'deleteUser']);
        $g->post('/backup', [AdminController::class, 'backup']);
        $g->post('/restore', [AdminController::class, 'restore']);
        $g->post('/clear-cache', [AdminController::class, 'clearCache']);
    })->add(AdminAuthMiddleware::class);
};

// API v1 routes (subset: shared routes + v1-specific nodes/config/system)
$app->group('/api/v1', function (RouteCollectorProxy $group) use ($registerSharedApiRoutes): void {
    $registerSharedApiRoutes($group);

    $group->group('/nodes', function (RouteCollectorProxy $g): void {
        $g->get('', [NodeController::class, 'list']);
        $g->get('/available', [NodeController::class, 'available']);
        $g->get('/{id}', [NodeController::class, 'get']);
        $g->get('/{id}/status', [NodeController::class, 'status']);
        $g->post('/{id}/connect', [NodeController::class, 'connect']);
        $g->post('/{id}/disconnect', [NodeController::class, 'disconnect']);
        $g->post('/{id}/monitor', [NodeController::class, 'monitor']);
        $g->post('/{id}/local-monitor', [NodeController::class, 'localMonitor']);
        $g->post('/{id}/dtmf', [NodeController::class, 'dtmf']);
    })->add(ApiAuthMiddleware::class);

    $group->group('/system', function (RouteCollectorProxy $g): void {
        $g->get('/info', [SystemController::class, 'info']);
        $g->get('/stats', [SystemController::class, 'stats']);
        $g->get('/logs', [SystemController::class, 'getLogs']);
        $g->get('/client-ip', [SystemController::class, 'getClientIP']);
    });

    $group->group('/config', function (RouteCollectorProxy $g): void {
        $g->get('', [ConfigController::class, 'list']);
        $g->get('/{key}', [ConfigController::class, 'get']);
        $g->put('/{key}', [ConfigController::class, 'update']);
    });
});

// API routes without version prefix (frontend uses this; single source for full route set)
$app->group('/api', function (RouteCollectorProxy $group) use ($registerSharedApiRoutes): void {
    $registerSharedApiRoutes($group);

    $group->group('/auth', function (RouteCollectorProxy $g): void {
        $g->get('/check', [AuthController::class, 'check']);
    });

    $group->group('/nodes', function (RouteCollectorProxy $g): void {
        $g->get('', [NodeController::class, 'list']);
        $g->get('/available', [NodeController::class, 'available']);
        $g->get('/ami/status', [NodeController::class, 'getAmiStatus']);
        $g->get('/websocket/ports', [NodeController::class, 'getAllWebSocketPorts']);
        $g->get('/voter/status', [NodeController::class, 'voterStatus']);
        $g->get('/{id}', [NodeController::class, 'get']);
        $g->get('/{id}/status', [NodeController::class, 'status']);
        $g->get('/{id}/websocket/port', [NodeController::class, 'getWebSocketPort']);
        $g->post('/connect', [NodeController::class, 'connect']);
        $g->post('/disconnect', [NodeController::class, 'disconnect']);
        $g->post('/monitor', [NodeController::class, 'monitor']);
        $g->post('/local-monitor', [NodeController::class, 'localMonitor']);
        $g->post('/dtmf', [NodeController::class, 'dtmf']);
        $g->post('/rptstats', [NodeController::class, 'rptstats']);
        $g->post('/cpustats', [NodeController::class, 'cpustats']);
        $g->post('/database', [NodeController::class, 'database']);
        $g->post('/extnodes', [NodeController::class, 'extnodes']);
        $g->post('/fastrestart', [NodeController::class, 'fastrestart']);
        $g->post('/irlplog', [NodeController::class, 'irlplog']);
        $g->post('/linuxlog', [NodeController::class, 'linuxlog']);
        $g->post('/banallow', [NodeController::class, 'banallow']);
        $g->post('/banallow/action', [NodeController::class, 'banallowAction']);
        $g->post('/pigpio', [NodeController::class, 'pigpio']);
        $g->post('/pigpio/action', [NodeController::class, 'pigpioAction']);
        $g->post('/reboot', [NodeController::class, 'reboot']);
        $g->post('/smlog', [NodeController::class, 'smlog']);
        $g->post('/stats', [NodeController::class, 'stats']);
        $g->post('/webacclog', [NodeController::class, 'webacclog']);
        $g->post('/weberrlog', [NodeController::class, 'weberrlog']);
        $g->get('/{id}/lsnodes', [NodeController::class, 'lsnodes']);
        $g->get('/{id}/lsnodes/web', [NodeController::class, 'lsnodesWeb']);
    });

    $group->group('/dvswitch', function (RouteCollectorProxy $g): void {
        $g->get('/nodes', [DvswitchController::class, 'getNodes']);
        $g->get('/node/{nodeId}/modes', [DvswitchController::class, 'getModes']);
        $g->get('/node/{nodeId}/mode/{mode}/talkgroups', [DvswitchController::class, 'getTalkgroups']);
        $g->post('/node/{nodeId}/mode/{mode}', [DvswitchController::class, 'switchMode']);
        $g->post('/node/{nodeId}/tune/{tgid}', [DvswitchController::class, 'switchTalkgroup']);
    });

    $group->group('/config', function (RouteCollectorProxy $g): void {
        $g->get('/nodes', [ConfigController::class, 'getNodes']);
        $g->get('/user/preferences', [ConfigController::class, 'getUserPreferences']);
        $g->put('/user/preferences', [ConfigController::class, 'updateUserPreferences']);
        $g->get('/system-info', [ConfigController::class, 'getSystemInfo']);
        $g->get('/menu', [ConfigController::class, 'getMenu']);
        $g->get('/header-background', [ConfigController::class, 'getHeaderBackground']);
        $g->get('/display', [ConfigController::class, 'getDisplayConfig']);
        $g->put('/display', [ConfigController::class, 'updateDisplayConfig']);
        $g->get('/node-info', [ConfigController::class, 'getNodeInfo']);
        $g->post('/add-favorite', [ConfigController::class, 'addFavorite']);
        $g->get('/favorites', [ConfigController::class, 'getFavorites']);
        $g->post('/favorites/add', [ConfigController::class, 'addFavorite']);
        $g->delete('/favorites', [ConfigController::class, 'deleteFavorite']);
        $g->post('/favorites/execute', [ConfigController::class, 'executeFavorite']);
        $g->post('/asterisk/reload', [ConfigController::class, 'executeAsteriskReload']);
        $g->post('/asterisk/control', [ConfigController::class, 'executeAsteriskControl']);
        $g->get('/astlog', [ConfigController::class, 'getAstLog']);
        $g->post('/astlookup', [ConfigController::class, 'performAstLookup']);
        $g->post('/bubblechart', [ConfigController::class, 'getBubbleChart']);
        $g->get('/controlpanel', [ConfigController::class, 'getControlPanel']);
        $g->post('/controlpanel/execute', [ConfigController::class, 'executeControlPanelCommand']);
        $g->get('/configeditor/files', [ConfigController::class, 'getConfigEditorFiles']);
        $g->post('/configeditor/content', [ConfigController::class, 'getConfigFileContent']);
        $g->post('/configeditor/save', [ConfigController::class, 'saveConfigFile']);
    });

    $group->group('/database', function (RouteCollectorProxy $g): void {
        $g->get('/status', [DatabaseController::class, 'status']);
        $g->post('/generate', [DatabaseController::class, 'generate']);
        $g->post('/auto-update', [DatabaseController::class, 'autoUpdate']);
        $g->post('/force-update', [DatabaseController::class, 'forceUpdate']);
        $g->get('/search', [DatabaseController::class, 'search']);
        $g->get('/{id}', [DatabaseController::class, 'get']);
    });

    $group->group('/node-status', function (RouteCollectorProxy $g): void {
        $g->get('/config', [NodeStatusController::class, 'getConfig']);
        $g->put('/config', [NodeStatusController::class, 'updateConfig']);
        $g->post('/trigger-update', [NodeStatusController::class, 'triggerUpdate']);
        $g->get('/service-status', [NodeStatusController::class, 'getServiceStatus']);
    });

    $group->group('/system', function (RouteCollectorProxy $g): void {
        $g->get('/info', [SystemController::class, 'info']);
        $g->get('/stats', [SystemController::class, 'stats']);
        $g->post('/reload', [SystemController::class, 'reload']);
        $g->post('/start', [SystemController::class, 'start']);
        $g->post('/stop', [SystemController::class, 'stop']);
        $g->post('/fast-restart', [SystemController::class, 'fastRestart']);
        $g->post('/reboot', [SystemController::class, 'reboot']);
    });
});
