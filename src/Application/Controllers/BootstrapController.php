<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SupermonNg\Services\VersionCheckService;

/**
 * Single bootstrap endpoint: auth + systemInfo + databaseStatus + nodes for faster first load.
 */
class BootstrapController
{
    public function __construct(
        private readonly AuthController $authController,
        private readonly ConfigController $configController,
        private readonly DatabaseController $databaseController,
        private readonly VersionCheckService $versionCheckService
    ) {
    }

    public function get(Request $request, Response $response): Response
    {
        $auth = $this->authController->getAuthData();
        $systemInfo = $this->configController->getSystemInfoData();
        $databaseStatus = $this->databaseController->getStatusData();
        $nodes = $this->configController->getNodesData();
        $updateCheck = $this->versionCheckService->getUpdateCheck();

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'auth' => $auth,
                'systemInfo' => $systemInfo,
                'databaseStatus' => $databaseStatus,
                'nodes' => $nodes,
                'updateCheck' => $updateCheck,
            ],
            'timestamp' => date('c')
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'private, max-age=60');
    }
}
