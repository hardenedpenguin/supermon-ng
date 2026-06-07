<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use SupermonNg\Services\ApiResponseHelper;
use SupermonNg\Services\SessionService;
use SupermonNg\Services\SystemHealthService;
use SupermonNg\Services\UserPermissionService;

final class SystemHealthController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SessionService $sessionService,
        private readonly UserPermissionService $userPermissionService,
        private readonly SystemHealthService $healthService
    ) {
    }

    public function getHealth(Request $request, Response $response): Response
    {
        if ($denied = $this->requireSysInfUser($response)) {
            return $denied;
        }

        try {
            return ApiResponseHelper::json($response, [
                'success' => true,
                'data' => $this->healthService->getHealth(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('System health check failed', ['error' => $e->getMessage()]);

            return ApiResponseHelper::error(
                $response,
                'Health check failed: ' . ApiResponseHelper::safeExceptionMessage($e),
                500
            );
        }
    }

    private function requireSysInfUser(Response $response): ?Response
    {
        $user = $this->sessionService->getCurrentUser();
        if ($user === null) {
            return ApiResponseHelper::error($response, 'Authentication required', 401);
        }
        if (!$this->userPermissionService->hasPermission($user, 'SYSINFUSER')) {
            return ApiResponseHelper::error($response, 'You are not authorized to view system health.', 403);
        }

        return null;
    }
}
