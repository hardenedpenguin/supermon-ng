<?php

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use SupermonNg\Application\Controllers\NodeController;
use SupermonNg\Application\Controllers\AuthController;
use SupermonNg\Application\Controllers\DatabaseController;
use SupermonNg\Application\Controllers\ConfigController;
use SupermonNg\Application\Controllers\NodeStatusController;
use SupermonNg\Application\Controllers\AdminController;
use SupermonNg\Application\Controllers\AstdbController;
use SupermonNg\Application\Controllers\DvswitchController;
use SupermonNg\Application\Controllers\AnnouncementsController;
use SupermonNg\Application\Controllers\BootstrapController;
use SupermonNg\Application\Controllers\VersionCheckController;
use SupermonNg\Application\Controllers\SetupController;
use SupermonNg\Application\Controllers\SystemHealthController;
use SupermonNg\Application\Middleware\AdminAuthMiddleware;
use SupermonNg\Application\Middleware\RequireAuthMiddleware;
use SupermonNg\Support\AppBasePath;

/** @var App $app */
global $app;

$requireAuth = RequireAuthMiddleware::class;

// Health check (no version prefix)
$app->get('/health', function ($request, $response) {
    $response->getBody()->write(json_encode([
        'status' => 'healthy',
        'timestamp' => date('c'),
        'version' => $_ENV['API_VERSION'] ?? '1.0.0',
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Dev-only diagnostic (disabled in production)
if (($_ENV['APP_ENV'] ?? 'production') !== 'production') {
    $app->get('/api/v1/test', function ($request, $response) {
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'API v1 is working',
            'timestamp' => date('c'),
            'php_version' => PHP_VERSION,
            'session_status' => session_status(),
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });
}

// CSRF token — public, must run before state-changing routes
$app->get('/api/v1/csrf-token', function ($request, $response) {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('supermon61');
            $isSecure = false;
            $serverParams = $request->getServerParams();
            if (($serverParams['HTTPS'] ?? '') === 'on'
                || ($serverParams['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
                || ($serverParams['HTTP_X_FORWARDED_SSL'] ?? '') === 'on'
                || ($serverParams['SERVER_PORT'] ?? '') == '443') {
                $isSecure = true;
            }
            session_set_cookie_params([
                'lifetime' => 86400,
                'path' => AppBasePath::cookiePath(),
                'domain' => '',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Session initialization failed',
                'csrf_token' => '',
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $token = $_SESSION['csrf_token'] ?? '';
        $response->getBody()->write(json_encode([
            'success' => true,
            'csrf_token' => $token,
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $isProd = ($_ENV['APP_ENV'] ?? 'production') === 'production';
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $isProd ? 'Server error' : ('Server error: ' . $e->getMessage()),
            'csrf_token' => '',
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

/**
 * All application API routes (v1 only).
 */
$app->group('/api/v1', function (RouteCollectorProxy $group) use ($requireAuth): void {
    $group->get('/bootstrap', [BootstrapController::class, 'get']);
    $group->get('/version/check', [VersionCheckController::class, 'get']);

    $group->group('/auth', function (RouteCollectorProxy $g): void {
        $g->post('/login', [AuthController::class, 'login']);
        $g->post('/logout', [AuthController::class, 'logout']);
        $g->get('/me', [AuthController::class, 'me']);
        $g->get('/check', [AuthController::class, 'check']);
    });

    // ASTDB read endpoints are public; cache clear requires admin
    $group->group('/astdb', function (RouteCollectorProxy $g): void {
        $g->get('/stats', [AstdbController::class, 'getStats']);
        $g->get('/health', [AstdbController::class, 'health']);
        $g->get('/search', [AstdbController::class, 'search']);
        $g->get('/nodes', [AstdbController::class, 'getNodes']);
        $g->get('/node/{id}', [AstdbController::class, 'getNode']);
        $g->post('/clear-cache', [AstdbController::class, 'clearCache'])
            ->add(AdminAuthMiddleware::class);
    });

    $group->group('/setup', function (RouteCollectorProxy $g): void {
        $g->get('/status', [SetupController::class, 'getStatus']);
        $g->get('/global-config', [SetupController::class, 'getGlobalConfig']);
        $g->post('/admin', [SetupController::class, 'createAdmin']);
        $g->post('/global-config', [SetupController::class, 'saveGlobalConfig']);
        $g->post('/generate-allmon', [SetupController::class, 'generateAllmon']);
        $g->post('/complete', [SetupController::class, 'complete']);
    });

    $group->group('/system', function (RouteCollectorProxy $g) use ($requireAuth): void {
        $g->get('/health', [SystemHealthController::class, 'getHealth'])->add($requireAuth);
    });

    $group->group('/admin', function (RouteCollectorProxy $g): void {
        $g->post('/generate-local-allmon', [AdminController::class, 'generateLocalAllmon']);
        $g->get('/config/export', [AdminController::class, 'exportConfig']);
        $g->post('/config/import', [AdminController::class, 'importConfig']);
        $g->post('/import/allscan-favorites', [AdminController::class, 'importAllScanFavorites']);
        $g->post('/import/allmon3-nodes', [AdminController::class, 'importAllmon3Nodes']);
    })->add(AdminAuthMiddleware::class);

    $group->group('/nodes', function (RouteCollectorProxy $g) use ($requireAuth): void {
        $g->get('', [NodeController::class, 'list']);
        $g->get('/available', [NodeController::class, 'available']);
        $g->get('/ami/status', [NodeController::class, 'getAmiStatus']);
        $g->get('/websocket/ports', [NodeController::class, 'getAllWebSocketPorts']);
        $g->get('/voter/status', [NodeController::class, 'voterStatus']);
        $g->get('/{id}/websocket/token', [NodeController::class, 'getWebSocketToken'])->add($requireAuth);
        $g->get('/{id}/websocket/port', [NodeController::class, 'getWebSocketPort']);
        $g->get('/{id}', [NodeController::class, 'get']);
        $g->get('/{id}/status', [NodeController::class, 'status']);
        // State-changing/privileged actions also require an authenticated
        // session (defense in depth on top of the per-method permission checks).
        $g->post('/connect', [NodeController::class, 'connect'])->add($requireAuth);
        $g->post('/disconnect', [NodeController::class, 'disconnect'])->add($requireAuth);
        $g->post('/monitor', [NodeController::class, 'monitor'])->add($requireAuth);
        $g->post('/local-monitor', [NodeController::class, 'localMonitor'])->add($requireAuth);
        $g->post('/dtmf', [NodeController::class, 'dtmf'])->add($requireAuth);
        $g->post('/rptstats', [NodeController::class, 'rptstats'])->add($requireAuth);
        $g->post('/cpustats', [NodeController::class, 'cpustats'])->add($requireAuth);
        $g->post('/database', [NodeController::class, 'database'])->add($requireAuth);
        $g->post('/extnodes', [NodeController::class, 'extnodes'])->add($requireAuth);
        $g->post('/fastrestart', [NodeController::class, 'fastrestart'])->add($requireAuth);
        $g->post('/irlplog', [NodeController::class, 'irlplog'])->add($requireAuth);
        $g->post('/linuxlog', [NodeController::class, 'linuxlog'])->add($requireAuth);
        $g->post('/banallow', [NodeController::class, 'banallow'])->add($requireAuth);
        $g->post('/banallow/action', [NodeController::class, 'banallowAction'])->add($requireAuth);
        $g->post('/pigpio', [NodeController::class, 'pigpio'])->add($requireAuth);
        $g->post('/pigpio/action', [NodeController::class, 'pigpioAction'])->add($requireAuth);
        $g->post('/reboot', [NodeController::class, 'reboot'])->add($requireAuth);
        $g->post('/smlog', [NodeController::class, 'smlog'])->add($requireAuth);
        $g->post('/stats', [NodeController::class, 'stats'])->add($requireAuth);
        $g->post('/webacclog', [NodeController::class, 'webacclog'])->add($requireAuth);
        $g->post('/weberrlog', [NodeController::class, 'weberrlog'])->add($requireAuth);
        $g->get('/{id}/lsnodes', [NodeController::class, 'lsnodes']);
        $g->get('/{id}/lsnodes/web', [NodeController::class, 'lsnodesWeb']);
    });

    $group->group('/dvswitch', function (RouteCollectorProxy $g) use ($requireAuth): void {
        $g->get('/nodes', [DvswitchController::class, 'getNodes']);
        $g->get('/node/{nodeId}/modes', [DvswitchController::class, 'getModes']);
        $g->get('/node/{nodeId}/mode/{mode}/talkgroups', [DvswitchController::class, 'getTalkgroups']);
        $g->post('/node/{nodeId}/mode/{mode}', [DvswitchController::class, 'switchMode'])->add($requireAuth);
        $g->post('/node/{nodeId}/tune/{tgid}', [DvswitchController::class, 'switchTalkgroup'])->add($requireAuth);
        $g->post('/restart-bridges', [DvswitchController::class, 'restartBridges'])->add($requireAuth);
    });

    $group->group('/announcements', function (RouteCollectorProxy $g) use ($requireAuth): void {
        $g->get('', [AnnouncementsController::class, 'getStatus'])->add($requireAuth);
        $g->post('/play', [AnnouncementsController::class, 'play'])->add($requireAuth);
        $g->post('/upload', [AnnouncementsController::class, 'upload'])->add($requireAuth);
        $g->post('/tts', [AnnouncementsController::class, 'tts'])->add($requireAuth);
        $g->get('/voices', [AnnouncementsController::class, 'listVoices'])->add($requireAuth);
        $g->post('/voices/install', [AnnouncementsController::class, 'installVoice'])->add($requireAuth);
        $g->delete('/{name}', [AnnouncementsController::class, 'delete'])->add($requireAuth);
        $g->get('/schedules', [AnnouncementsController::class, 'listSchedules'])->add($requireAuth);
        $g->post('/schedules', [AnnouncementsController::class, 'addSchedule'])->add($requireAuth);
        $g->patch('/schedules/{id}/enabled', [AnnouncementsController::class, 'toggleSchedule'])->add($requireAuth);
        $g->delete('/schedules/{id}', [AnnouncementsController::class, 'deleteSchedule'])->add($requireAuth);
    });

    $group->group('/config', function (RouteCollectorProxy $g) use ($requireAuth): void {
        $g->get('/nodes', [ConfigController::class, 'getNodes']);
        $g->get('/user/preferences', [ConfigController::class, 'getUserPreferences']);
        $g->put('/user/preferences', [ConfigController::class, 'updateUserPreferences'])->add($requireAuth);
        $g->get('/system-info', [ConfigController::class, 'getSystemInfo']);
        $g->get('/menu', [ConfigController::class, 'getMenu']);
        $g->get('/global-lint', [ConfigController::class, 'lintGlobalInc']);
        $g->get('/header-background', [ConfigController::class, 'getHeaderBackground']);
        $g->get('/display', [ConfigController::class, 'getDisplayConfig']);
        $g->put('/display', [ConfigController::class, 'updateDisplayConfig'])->add($requireAuth);
        $g->get('/node-info', [ConfigController::class, 'getNodeInfo']);
        $g->post('/add-favorite', [ConfigController::class, 'addFavorite'])->add($requireAuth);
        $g->get('/favorites', [ConfigController::class, 'getFavorites']);
        $g->post('/favorites/add', [ConfigController::class, 'addFavorite'])->add($requireAuth);
        $g->delete('/favorites', [ConfigController::class, 'deleteFavorite'])->add($requireAuth);
        $g->post('/favorites/execute', [ConfigController::class, 'executeFavorite'])->add($requireAuth);
        $g->post('/asterisk/reload', [ConfigController::class, 'executeAsteriskReload'])->add($requireAuth);
        $g->post('/asterisk/control', [ConfigController::class, 'executeAsteriskControl'])->add($requireAuth);
        $g->get('/astlog', [ConfigController::class, 'getAstLog']);
        $g->post('/astlookup', [ConfigController::class, 'performAstLookup'])->add($requireAuth);
        $g->post('/bubblechart', [ConfigController::class, 'getBubbleChart'])->add($requireAuth);
        $g->get('/controlpanel', [ConfigController::class, 'getControlPanel']);
        $g->post('/controlpanel/execute', [ConfigController::class, 'executeControlPanelCommand'])->add($requireAuth);
        $g->get('/configeditor/files', [ConfigController::class, 'getConfigEditorFiles']);
        $g->post('/configeditor/content', [ConfigController::class, 'getConfigFileContent'])->add($requireAuth);
        $g->post('/configeditor/save', [ConfigController::class, 'saveConfigFile'])->add($requireAuth);
    });

    $group->group('/database', function (RouteCollectorProxy $g) use ($requireAuth): void {
        $g->get('/status', [DatabaseController::class, 'status']);
        $g->post('/generate', [DatabaseController::class, 'generate'])->add($requireAuth);
        $g->post('/auto-update', [DatabaseController::class, 'autoUpdate'])->add($requireAuth);
        $g->post('/force-update', [DatabaseController::class, 'forceUpdate'])->add($requireAuth);
        $g->get('/search', [DatabaseController::class, 'search']);
        $g->get('/{id}', [DatabaseController::class, 'get']);
    });

    $group->group('/node-status', function (RouteCollectorProxy $g) use ($requireAuth): void {
        $g->get('/config', [NodeStatusController::class, 'getConfig'])->add($requireAuth);
        $g->get('/service-status', [NodeStatusController::class, 'getServiceStatus'])->add($requireAuth);
        $g->put('/config', [NodeStatusController::class, 'updateConfig'])->add($requireAuth);
        $g->post('/trigger-update', [NodeStatusController::class, 'triggerUpdate'])->add($requireAuth);
    });
});

// Legacy /api/* → 410 Gone (clients must use /api/v1)
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'], '/api/{routes:.+}', function ($request, $response) {
    $response->getBody()->write(json_encode([
        'success' => false,
        'error' => 'API version removed',
        'message' => 'Use /api/v1/ instead of /api/',
    ]));
    return $response->withStatus(410)->withHeader('Content-Type', 'application/json');
});
// Legacy GET /api/csrf-token is covered by /api/{routes:.+} above (410 + message to use /api/v1/csrf-token).
