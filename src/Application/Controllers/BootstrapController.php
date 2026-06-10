<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SupermonNg\Services\SetupService;

/**
 * Single bootstrap endpoint: auth + systemInfo + database metadata + nodes + setup flags.
 */
class BootstrapController
{
    public function __construct(
        private readonly AuthController $authController,
        private readonly ConfigController $configController,
        private readonly DatabaseController $databaseController,
        private readonly SetupService $setupService
    ) {
    }

    public function get(Request $request, Response $response): Response
    {
        $auth = $this->authController->getAuthData();
        $systemInfo = $this->configController->getSystemInfoData();
        $databaseStatus = $this->databaseController->getStatusData(false);
        $nodes = $this->configController->getNodesData();
        $setupStatus = $this->setupService->getStatus();

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'auth' => $auth,
                'systemInfo' => $systemInfo,
                'databaseStatus' => $databaseStatus,
                'nodes' => $nodes,
                'setup' => [
                    'needs_setup' => $setupStatus['needs_setup'],
                    'setup_complete' => $setupStatus['setup_complete'],
                ],
            ],
            'timestamp' => date('c')
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'private, max-age=60');
    }
}
