<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use SupermonNg\Services\ApiResponseHelper;
use SupermonNg\Services\SetupService;

final class SetupController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SetupService $setupService
    ) {
    }

    public function getStatus(Request $request, Response $response): Response
    {
        return ApiResponseHelper::json($response, [
            'success' => true,
            'data' => $this->setupService->getStatus(),
        ]);
    }

    public function createAdmin(Request $request, Response $response): Response
    {
        $status = $this->setupService->getStatus();
        if ($status['setup_complete']) {
            return ApiResponseHelper::error($response, 'Setup is already complete', 403);
        }

        $body = $this->parseJson($request);
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        $result = $this->setupService->createAdminUser($username, $password);
        if (!$result['success']) {
            return ApiResponseHelper::error($response, $result['message'], 400);
        }

        return ApiResponseHelper::json($response, $result);
    }

    public function getGlobalConfig(Request $request, Response $response): Response
    {
        return ApiResponseHelper::json($response, [
            'success' => true,
            'data' => $this->setupService->getGlobalConfig(),
        ]);
    }

    public function saveGlobalConfig(Request $request, Response $response): Response
    {
        $body = $this->parseJson($request);
        $result = $this->setupService->saveGlobalConfig($body);

        if (!$result['success']) {
            return ApiResponseHelper::error($response, $result['message'], 400);
        }

        return ApiResponseHelper::json($response, $result);
    }

    public function generateAllmon(Request $request, Response $response): Response
    {
        $body = $this->parseJson($request);
        $force = !empty($body['force']);
        $result = $this->setupService->generateAllmon($force);

        if (!$result['success']) {
            return ApiResponseHelper::error($response, $result['message'], 400);
        }

        return ApiResponseHelper::json($response, $result);
    }

    public function complete(Request $request, Response $response): Response
    {
        return ApiResponseHelper::json($response, $this->setupService->markComplete());
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJson(Request $request): array
    {
        $raw = (string) $request->getBody();
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
