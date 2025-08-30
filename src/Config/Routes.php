<?php

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use SupermonNg\Application\Controllers\NodeController;
use SupermonNg\Application\Controllers\AuthController;
use SupermonNg\Application\Controllers\SystemController;
use SupermonNg\Application\Controllers\DatabaseController;
use SupermonNg\Application\Controllers\ConfigController;
use SupermonNg\Application\Controllers\AdminController;
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

// Static file routes
$app->get('/configeditor.html', function ($request, $response) {
    $filePath = __DIR__ . '/../../public/configeditor.html';
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'text/html');
    } else {
        return $response->withStatus(404)->withHeader('Content-Type', 'text/plain')->getBody()->write('File not found');
    }
});

// API v1 routes
$app->group('/api/v1', function (RouteCollectorProxy $group) {
    // Auth routes
    $group->group('/auth', function (RouteCollectorProxy $group) {
        $group->post('/login', [AuthController::class, 'login']);
        $group->post('/logout', [AuthController::class, 'logout']);
        $group->post('/refresh', [AuthController::class, 'refresh']);
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
    });

    // Database routes
    $group->group('/database', function (RouteCollectorProxy $group) {
        $group->get('/status', [DatabaseController::class, 'status']);
        $group->post('/generate', [DatabaseController::class, 'generate']);
        $group->get('/search', [DatabaseController::class, 'search']);
        $group->get('/{id}', [DatabaseController::class, 'get']);
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
        $group->post('/refresh', [AuthController::class, 'refresh']);
        $group->get('/me', [AuthController::class, 'me']);
        $group->get('/check', [AuthController::class, 'check']);
    });

    // Node routes
    $group->group('/nodes', function (RouteCollectorProxy $group) {
        $group->get('', [NodeController::class, 'list']);
        $group->get('/available', [NodeController::class, 'available']);
        $group->get('/ami/status', [NodeController::class, 'getAmiStatus']);
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
    });

    // Config routes
    $group->group('/config', function (RouteCollectorProxy $group) {
        $group->get('/nodes', [ConfigController::class, 'getNodes']);
        $group->get('/user/preferences', [ConfigController::class, 'getUserPreferences']);
        $group->put('/user/preferences', [ConfigController::class, 'updateUserPreferences']);
        $group->get('/system-info', [ConfigController::class, 'getSystemInfo']);
        $group->get('/menu', [ConfigController::class, 'getMenu']);
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
        $group->get('/search', [DatabaseController::class, 'search']);
        $group->get('/{id}', [DatabaseController::class, 'get']);
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
