<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SupermonNg\Services\VersionCheckService;

final class VersionCheckController
{
    public function __construct(
        private readonly VersionCheckService $versionCheckService
    ) {
    }

    public function get(Request $request, Response $response): Response
    {
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $this->versionCheckService->getUpdateCheck(),
            'timestamp' => date('c'),
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'private, max-age=300');
    }
}
