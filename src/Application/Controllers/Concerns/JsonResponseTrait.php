<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers\Concerns;

use Psr\Http\Message\ResponseInterface as Response;
use SupermonNg\Services\ApiResponseHelper;

trait JsonResponseTrait
{
    /**
     * @param array<string, mixed> $payload
     */
    protected function json(Response $response, array $payload, int $status = 200): Response
    {
        return ApiResponseHelper::json($response, $payload, $status);
    }

    protected function jsonFail(Response $response, string $message, int $status = 400, array $extra = []): Response
    {
        return ApiResponseHelper::error($response, $message, $status, array_merge(['success' => false], $extra));
    }

    protected function jsonFailException(Response $response, string $userMessage, \Throwable $e, int $status = 500): Response
    {
        return $this->jsonFail(
            $response,
            $userMessage . ': ' . ApiResponseHelper::safeExceptionMessage($e),
            $status
        );
    }

    protected function inferErrorStatus(string $message): int
    {
        $lower = strtolower($message);
        if (str_contains($lower, 'not authorized') || str_contains($lower, 'permission')) {
            return 403;
        }
        if (str_contains($lower, 'authentication required') || str_contains($lower, 'login')) {
            return 401;
        }
        if (str_contains($lower, 'not found')) {
            return 404;
        }
        if (str_contains($lower, 'could not connect to asterisk') || str_contains($lower, 'ami')) {
            return 502;
        }
        if (str_contains($lower, 'valid') || str_contains($lower, 'required') || str_contains($lower, 'invalid')) {
            return 400;
        }
        return 500;
    }
}
